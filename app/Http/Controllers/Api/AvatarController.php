<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Foto de perfil.
 *
 * Va en el disco privado y se sirve con el token, no por URL pública: es la
 * cara de un cliente, no un recurso que deba poder adivinarse.
 */
class AvatarController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ], [
            'avatar.required' => 'Elegí una foto.',
            'avatar.mimes' => 'La foto debe ser PNG, WEBP o JPG.',
            'avatar.max' => 'La foto no puede pesar más de 4 MB.',
        ]);

        $user = $request->user();
        $anterior = $user->avatar_path;

        $user->forceFill([
            'avatar_path' => $request->file('avatar')->store("avatars/{$user->id}", 'local'),
        ])->save();

        // El archivo viejo se borra después de guardar el nuevo: al revés,
        // un fallo al subir dejaría al usuario sin ninguna de las dos.
        if ($anterior) {
            Storage::disk('local')->delete($anterior);
        }

        return response()->json(['data' => new UserResource(self::withRelations($user))]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        return response()->json(['data' => new UserResource(self::withRelations($user))]);
    }

    /**
     * Las mismas relaciones que carga /me.
     *
     * UserResource da por hecho que la dirección viene cargada; devolver el
     * usuario pelado hace reventar la respuesta.
     */
    private static function withRelations(User $user): User
    {
        return $user->fresh()->load([
            'authorizedPersons',
            'defaultShippingAddress.province',
            'defaultShippingAddress.canton',
            'defaultShippingAddress.district',
        ]);
    }

    /**
     * Devuelve la foto de un usuario.
     *
     * Cada quien ve la suya; el almacén ve la de cualquier cliente, que es lo
     * que le permite reconocer a quien llega a retirar un paquete.
     */
    public function show(Request $request, User $user): StreamedResponse
    {
        $propia = $user->id === $request->user()->id;

        // Por el rol y no por tokenCan('staff'): los tokens de cliente se
        // emiten con la habilidad '*', que en Sanctum concede cualquiera, así
        // que preguntar por la habilidad dejaba a un cliente ver la foto de
        // cualquier otro.
        abort_unless($propia || $request->user()->isStaff(), 404);
        abort_unless($user->avatar_path, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($user->avatar_path), 404);

        return $disk->response($user->avatar_path);
    }
}
