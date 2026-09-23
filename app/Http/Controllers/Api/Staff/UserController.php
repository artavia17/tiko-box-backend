<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Alta y mantenimiento de cuentas, sean de clientes o del personal. */
class UserController extends Controller
{
    /** Todas las cuentas, con filtro por rol y búsqueda. */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $role = $request->query('role');

        $users = User::query()
            ->when(in_array($role, ['cliente', 'empleado', 'admin'], true),
                fn ($query) => $query->where('role', $role))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    foreach (['name', 'email', 'identification', 'phone', 'locker_code'] as $field) {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                });
            })
            ->withCount('packages')
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => collect($users->items())->map($this->present(...)),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /** Alta manual, para dar de alta personal o un cliente en mostrador. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'second_last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')],
            'identification' => ['nullable', 'string', 'max:30', Rule::unique('users', 'identification')],
            'identification_type' => ['nullable', Rule::in(['nacional', 'extranjero'])],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['cliente', 'empleado', 'admin'])],
            // Los permisos y el casillero son cosas distintas: quien
            // administra el negocio también compra.
            'receives_packages' => ['nullable', 'boolean'],
            'email_verified' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            ...collect($data)->except(['receives_packages', 'email_verified'])->all(),
            'name' => trim("{$data['first_name']} {$data['last_name']}"),
            'locker_code' => $this->lockerFor($data),
        ]);

        // Un alta de mostrador la hace alguien de adentro que ya tiene a la
        // persona enfrente, así que el correo se da por bueno salvo que
        // pidan lo contrario.
        $this->setVerification($user, $data['email_verified'] ?? true);

        return response()->json(['data' => $this->present($user->loadCount('packages'))], 201);
    }

    /** Edición de datos y de rol. */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'second_last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'identification' => ['nullable', 'string', 'max:30', Rule::unique('users', 'identification')->ignore($user->id)],
            'role' => ['required', Rule::in(['cliente', 'empleado', 'admin'])],
            'password' => ['nullable', 'string', 'min:8'],
            'receives_packages' => ['nullable', 'boolean'],
            'email_verified' => ['nullable', 'boolean'],
        ]);

        $this->guardLastAdmin($request, $user, $data['role']);

        $user->update([
            ...collect($data)->except(['password', 'receives_packages', 'email_verified'])->all(),
            'name' => trim("{$data['first_name']} {$data['last_name']}"),
            'locker_code' => $this->lockerFor($data, $user),
            ...(filled($data['password'] ?? null) ? ['password' => $data['password']] : []),
        ]);

        // Solo se toca si viene en la petición: una edición de datos no
        // debería confirmar ni desconfirmar a nadie sin querer.
        if ($request->has('email_verified')) {
            $this->setVerification($user, $data['email_verified']);
        }

        return response()->json(['data' => $this->present($user->fresh()->loadCount('packages'))]);
    }

    /**
     * Marca o quita la confirmación del correo.
     *
     * Va aparte y con forceFill porque `email_verified_at` no es asignable en
     * masa a propósito: así ningún formulario puede darse por confirmado solo
     * mandando el campo. Solo esta pantalla, que es de administración, lo hace
     * a mano.
     */
    private function setVerification(User $user, mixed $verified): void
    {
        $verified = filter_var($verified, FILTER_VALIDATE_BOOL);

        $user->forceFill([
            // Si ya estaba confirmada se respeta la fecha original.
            'email_verified_at' => $verified ? ($user->email_verified_at ?? now()) : null,
        ])->save();
    }

    /**
     * El casillero que le toca a la cuenta.
     *
     * Un cliente siempre lo tiene; al personal se le da solo si además
     * recibe paquetes. Es el mismo código para toda la operación.
     *
     * @param  array<string, mixed>  $data
     */
    private function lockerFor(array $data, ?User $user = null): ?string
    {
        $wantsLocker = $data['role'] === 'cliente'
            || filter_var($data['receives_packages'] ?? false, FILTER_VALIDATE_BOOL);

        if (! $wantsLocker) {
            return null;
        }

        return $user?->locker_code ?? config('tikabox.locker.code');
    }

    /**
     * Quedarse sin administradores dejaría el panel sin quien lo gobierne, y
     * nadie debería poder quitarse a sí mismo el acceso por error.
     */
    private function guardLastAdmin(Request $request, User $user, string $newRole): void
    {
        if ($user->role !== 'admin' || $newRole === 'admin') {
            return;
        }

        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'role' => 'No podés quitarte a vos mismo el rol de administrador.',
            ]);
        }

        if (User::where('role', 'admin')->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Tiene que quedar al menos un administrador.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'second_last_name' => $user->second_last_name,
            'full_name' => $user->fullName(),
            'email' => $user->email,
            'phone' => $user->phone,
            'identification' => $user->identification,
            'identification_type' => $user->identification_type,
            'role' => $user->role,
            'locker_code' => $user->locker_code,
            'receives_packages' => $user->isCustomer(),
            'packages_count' => $user->packages_count ?? 0,
            'email_verified' => $user->hasVerifiedEmail(),
            'created_at' => $user->created_at?->toDateString(),
        ];
    }
}
