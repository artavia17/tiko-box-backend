<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consolidation;
use App\Models\Prealert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Consolidaciones del cliente.
 *
 * El dueño sale siempre del token, nunca de un parámetro: si el cliente
 * pudiera mandar el id de otro, consolidar paquetes ajenos sería cuestión de
 * cambiar un número en la petición.
 */
class ConsolidationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $consolidations = Consolidation::where('user_id', $request->user()->id)
            ->with('prealerts')
            ->latest()
            ->get();

        return response()->json([
            'data' => $consolidations->map($this->present(...)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prealert_ids' => [
                'required',
                'array',
                'min:'.config('tikabox.consolidation.min_prealerts'),
                'max:'.config('tikabox.consolidation.max_prealerts'),
            ],
            'prealert_ids.*' => ['integer'],
        ], [
            'prealert_ids.min' => 'Una consolidación necesita al menos 2 paquetes.',
            'prealert_ids.max' => 'No se pueden consolidar más de 10 paquetes.',
        ]);

        $ids = array_unique($data['prealert_ids']);

        $consolidation = DB::transaction(function () use ($request, $ids) {
            // Dentro de la transacción y con bloqueo: dos pestañas abiertas no
            // pueden meter la misma prealerta en dos consolidaciones.
            $prealerts = Prealert::where('user_id', $request->user()->id)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            $this->assertEligible($prealerts, count($ids));

            $consolidation = Consolidation::create(['user_id' => $request->user()->id]);

            Prealert::whereIn('id', $prealerts->pluck('id'))
                ->update(['consolidation_id' => $consolidation->id]);

            return $consolidation;
        });

        return response()->json([
            'data' => $this->present($consolidation->fresh('prealerts')),
        ], 201);
    }

    public function show(Request $request, Consolidation $consolidation): JsonResponse
    {
        $this->authorizeOwner($request, $consolidation);

        return response()->json([
            'data' => $this->present($consolidation->load('prealerts'), detailed: true),
        ]);
    }

    /** Suma una prealerta a una consolidación que todavía admite cambios. */
    public function addPrealert(Request $request, Consolidation $consolidation): JsonResponse
    {
        $this->authorizeOwner($request, $consolidation);

        $data = $request->validate(['prealert_id' => ['required', 'integer']]);

        DB::transaction(function () use ($request, $consolidation, $data) {
            $consolidation->load('prealerts');

            if (! $consolidation->isOpen()) {
                throw ValidationException::withMessages([
                    'prealert_id' => 'Esta consolidación ya no admite cambios.',
                ]);
            }

            if (! $consolidation->hasRoom()) {
                throw ValidationException::withMessages([
                    'prealert_id' => 'Esta consolidación ya tiene 10 paquetes.',
                ]);
            }

            $prealert = Prealert::where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->find($data['prealert_id']);

            $this->assertEligible(collect([$prealert])->filter(), 1);

            $prealert->update(['consolidation_id' => $consolidation->id]);
        });

        return response()->json([
            'data' => $this->present($consolidation->fresh('prealerts'), detailed: true),
        ]);
    }

    /** Saca una prealerta, siempre que no deje el grupo por debajo de 2. */
    public function removePrealert(
        Request $request,
        Consolidation $consolidation,
        Prealert $prealert,
    ): JsonResponse {
        $this->authorizeOwner($request, $consolidation);

        $consolidation->load('prealerts');

        abort_unless($prealert->consolidation_id === $consolidation->id, 404);

        if (! in_array($consolidation->status(), [
            ...Consolidation::OPEN_STATES,
            Consolidation::COMPLETADO,
        ], true)) {
            throw ValidationException::withMessages([
                'prealert' => 'Esta consolidación ya no admite cambios.',
            ]);
        }

        $min = (int) config('tikabox.consolidation.min_prealerts');

        if ($consolidation->prealerts->count() - 1 < $min) {
            throw ValidationException::withMessages([
                'prealert' => "Una consolidación no puede quedar con menos de {$min} paquetes. Eliminala si ya no la querés.",
            ]);
        }

        $prealert->update(['consolidation_id' => null]);

        return response()->json([
            'data' => $this->present($consolidation->fresh('prealerts'), detailed: true),
        ]);
    }

    /**
     * Pide cerrar la consolidación sin esperar a que venza la ventana.
     *
     * Es irreversible: los paquetes que no hayan llegado se sacan del grupo y
     * siguen su camino por separado.
     */
    public function request(Request $request, Consolidation $consolidation): JsonResponse
    {
        $this->authorizeOwner($request, $consolidation);
        $consolidation->load('prealerts');

        if (! $consolidation->canBeRequested()) {
            throw ValidationException::withMessages([
                'consolidation' => 'Todavía no se puede solicitar el envío de esta consolidación.',
            ]);
        }

        DB::transaction(function () use ($consolidation) {
            // Lo que no llegó deja de ser parte del grupo y se procesa solo.
            $consolidation->prealerts()
                ->where('status', 'pendiente')
                ->update(['consolidation_id' => null]);

            $consolidation->update(['requested_at' => now()]);
        });

        return response()->json([
            'data' => $this->present($consolidation->fresh('prealerts'), detailed: true),
        ]);
    }

    public function destroy(Request $request, Consolidation $consolidation): JsonResponse
    {
        $this->authorizeOwner($request, $consolidation);
        $consolidation->load('prealerts');

        if (! $consolidation->canBeDeleted()) {
            throw ValidationException::withMessages([
                'consolidation' => 'Solo se puede eliminar una consolidación a la que no ha llegado ningún paquete.',
            ]);
        }

        DB::transaction(function () use ($consolidation) {
            $consolidation->prealerts()->update(['consolidation_id' => null]);
            $consolidation->delete();
        });

        return response()->json(['message' => 'Consolidación eliminada.']);
    }

    /**
     * Comprueba que las prealertas se puedan consolidar.
     *
     * @param  Collection<int, Prealert>  $prealerts
     */
    private function assertEligible($prealerts, int $expected): void
    {
        if ($prealerts->count() !== $expected) {
            throw ValidationException::withMessages([
                'prealert_ids' => 'Alguna de esas prealertas no existe o no es tuya.',
            ]);
        }

        if ($prealerts->firstWhere('consolidation_id', '!==', null)) {
            throw ValidationException::withMessages([
                'prealert_ids' => 'Alguna de esas prealertas ya está en otra consolidación.',
            ]);
        }

        // Retener es no despachar; consolidar es despachar junto. No conviven.
        if ($prealerts->firstWhere('is_retained', true)) {
            throw ValidationException::withMessages([
                'prealert_ids' => 'No podés consolidar y retener un paquete al mismo tiempo.',
            ]);
        }
    }

    private function authorizeOwner(Request $request, Consolidation $consolidation): void
    {
        abort_unless($consolidation->user_id === $request->user()->id, 404);
    }

    /** @return array<string, mixed> */
    private function present(Consolidation $consolidation, bool $detailed = false): array
    {
        $base = [
            'id' => $consolidation->id,
            'status' => $consolidation->status(),
            'packages_count' => $consolidation->prealerts->count(),
            'received_count' => $consolidation->receivedCount(),
            'activated_at' => $consolidation->activated_at?->toDateString(),
            'expires_at' => $consolidation->expires_at?->toDateString(),
            'requested_at' => $consolidation->requested_at?->toDateString(),
            'processed_at' => $consolidation->processed_at?->toDateString(),
            // Que la pantalla no vuelva a deducir los estados por su cuenta:
            // dos copias de la misma regla terminan diciendo cosas distintas.
            'can_be_requested' => $consolidation->canBeRequested(),
            'can_be_deleted' => $consolidation->canBeDeleted(),
            'can_change_prealerts' => $consolidation->isOpen(),
            'has_room' => $consolidation->hasRoom(),
            'created_at' => $consolidation->created_at->toDateString(),
        ];

        if (! $detailed) {
            return $base;
        }

        return [
            ...$base,
            'prealerts' => $consolidation->prealerts->map(fn (Prealert $prealert) => [
                'id' => $prealert->id,
                'tracking_number' => $prealert->tracking_number,
                'courier' => $prealert->courier,
                'status' => $prealert->status,
                'arrived' => $prealert->status !== 'pendiente',
            ])->values(),
        ];
    }
}
