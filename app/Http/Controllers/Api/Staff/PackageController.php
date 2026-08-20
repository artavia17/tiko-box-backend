<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackagePhoto;
use App\Models\Prealert;
use App\Services\PackageTracker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Registro y seguimiento de paquetes desde el almacén. */
class PackageController extends Controller
{
    /** El cliente y su dirección: sin ella no se puede entregar el paquete. */
    private const CUSTOMER_RELATIONS = [
        'user',
        'photos',
        'events.createdBy',
        'priceAdjustedBy',
        'user.defaultShippingAddress.province',
        'user.defaultShippingAddress.canton',
        'user.defaultShippingAddress.district',
    ];

    public function index(Request $request): JsonResponse
    {
        $packages = Package::with(self::CUSTOMER_RELATIONS)
            ->when($request->query('search'), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('tracking_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('locker_code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->query('status'), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'data' => collect($packages->items())->map($this->present(...)),
            'meta' => [
                'current_page' => $packages->currentPage(),
                'last_page' => $packages->lastPage(),
                'total' => $packages->total(),
            ],
        ]);
    }

    /**
     * Registra un paquete recibido en Miami.
     *
     * El casillero es el mismo para toda la operación, así que no sirve para
     * saber de quién es la caja: el cliente se elige por id desde el buscador
     * de la app.
     */
    public function store(Request $request, PackageTracker $tracker): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'tracking_number' => ['required', 'string', 'max:60'],
            // Un solo bulto, o varios que se suman en un mismo envío.
            'weight_lb' => ['required_without:weights', 'nullable', 'numeric', 'min:0.01', 'max:500'],
            'weights' => ['required_without:weight_lb', 'nullable', 'array', 'min:1', 'max:30'],
            'weights.*' => ['numeric', 'min:0.01', 'max:500'],
            // Cobrar el peso tal cual, sin el mínimo de una libra.
            'exact_weight' => ['nullable', 'boolean'],
            'courier' => ['nullable', 'string', 'max:60'],
            'store' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:500'],
            'photos' => ['nullable', 'array', 'max:8'],
            'photos.*' => [File::types(['png', 'jpg', 'jpeg', 'webp'])->max(8192)],
        ], [
            'photos.*.mimes' => 'Las fotos deben ser PNG, JPG o WEBP.',
            'photos.*.max' => 'Cada foto puede pesar hasta 8 MB.',
            'photos.max' => 'Hasta 8 fotos por paquete.',
        ]);

        $customer = User::customers()->find($data['customer_id']);

        if (! $customer) {
            throw ValidationException::withMessages([
                'customer_id' => 'No encontramos ese cliente.',
            ]);
        }

        $tracking = strtoupper(trim($data['tracking_number']));

        if (Package::where('user_id', $customer->id)->where('tracking_number', $tracking)->exists()) {
            throw ValidationException::withMessages([
                'tracking_number' => 'Ese paquete ya está registrado para este cliente.',
            ]);
        }

        $weights = collect($data['weights'] ?? [$data['weight_lb']])
            ->map(fn ($weight) => round((float) $weight, 2))
            ->filter()
            ->values();

        $weight = round((float) $weights->sum(), 2);
        $exact = filter_var($data['exact_weight'] ?? false, FILTER_VALIDATE_BOOL);

        $pricePerPound = (float) config('tikabox.price_per_pound');

        // Se cobra un mínimo aunque pese menos, salvo que se pida lo exacto.
        $billable = $exact
            ? $weight
            : max($weight, (float) config('tikabox.minimum_weight_lb'));

        $package = DB::transaction(function () use ($customer, $data, $tracking, $weight, $weights, $exact, $pricePerPound, $billable, $request) {
            // Si el cliente lo había prealertado, se enlaza y se marca recibida.
            $prealert = Prealert::where('user_id', $customer->id)
                ->where('tracking_number', $tracking)
                ->first();

            $prealert?->update(['status' => 'recibido']);

            return Package::create([
                'user_id' => $customer->id,
                'registered_by' => $request->user()->id,
                'prealert_id' => $prealert?->id,
                'tracking_number' => $tracking,
                'courier' => $data['courier'] ?? null,
                'store' => $data['store'] ?? null,
                'description' => $data['description'] ?? null,
                'weight_lb' => $weight,
                // Solo tiene sentido guardarlo si vino más de un bulto.
                'weight_breakdown' => $weights->count() > 1 ? $weights->all() : null,
                'exact_weight' => $exact,
                'price_per_pound' => $pricePerPound,
                'total' => round($billable * $pricePerPound, 2),
                'status' => 'recibido',
                'received_at' => now(),
            ]);
        });

        foreach ($request->file('photos') ?? [] as $photo) {
            PackagePhoto::create([
                'package_id' => $package->id,
                'path' => $photo->store("packages/{$customer->id}", 'local'),
            ]);
        }

        $fresh = $package->fresh()->load(self::CUSTOMER_RELATIONS);
        $tracker->record($fresh, $request->user());

        return response()->json([
            'data' => $this->present($fresh),
        ], 201);
    }

    public function updateStatus(Request $request, Package $package, PackageTracker $tracker): JsonResponse
    {
        $data = $request->validate([
            // 'entregado' no entra acá: la entrega se firma, ver deliver().
            'status' => ['required', Rule::in(['recibido', 'en_transito', 'listo'])],
            'note' => ['nullable', 'string', 'max:200'],
        ], [
            'status.in' => 'Para entregar el paquete hay que registrar la firma de quien lo recibe.',
        ]);

        // Sin cambio real no se avisa: nadie quiere el mismo correo dos veces.
        if ($package->status === $data['status']) {
            return response()->json(['data' => $this->present($package->load(self::CUSTOMER_RELATIONS))]);
        }

        $package->update(['status' => $data['status'], 'delivered_at' => null]);

        $fresh = $package->fresh()->load(self::CUSTOMER_RELATIONS);
        $tracker->record($fresh, $request->user(), $data['note'] ?? null);

        return response()->json(['data' => $this->present($fresh)]);
    }

    /**
     * Entrega el paquete con constancia: quién lo recibió y su firma.
     *
     * La firma llega como imagen desde el panel; se guarda en el disco
     * privado porque es un dato personal del cliente.
     */
    public function deliver(Request $request, Package $package, PackageTracker $tracker): JsonResponse
    {
        $data = $request->validate([
            'delivered_to_name' => ['required', 'string', 'max:120'],
            'delivered_to_identification' => ['nullable', 'string', 'max:30'],
            'signature' => ['required', File::types(['png', 'jpg', 'jpeg'])->max(2048)],
        ], [
            'delivered_to_name.required' => 'Escribí el nombre de quien recibe.',
            'signature.required' => 'Falta la firma de quien recibe.',
        ]);

        if ($package->status === 'entregado') {
            throw ValidationException::withMessages([
                'status' => 'Este paquete ya figura como entregado.',
            ]);
        }

        $package->update([
            'status' => 'entregado',
            'delivered_at' => now(),
            'delivered_to_name' => $data['delivered_to_name'],
            'delivered_to_identification' => $data['delivered_to_identification'] ?? null,
            'signature_path' => $request->file('signature')->store(
                "signatures/{$package->user_id}",
                'local',
            ),
        ]);

        $fresh = $package->fresh()->load(self::CUSTOMER_RELATIONS);
        $tracker->record($fresh, $request->user(), 'Recibido por '.$data['delivered_to_name']);

        return response()->json(['data' => $this->present($fresh)]);
    }

    /**
     * Precio especial para un cliente.
     *
     * El cobro por tarifa queda guardado aparte para saber cuánto se rebajó, y
     * el ajuste anota quién lo hizo: es plata de la empresa.
     */
    public function adjustPrice(Request $request, Package $package): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Solo administración cambia el precio.');

        $data = $request->validate([
            'total' => ['required', 'numeric', 'min:0', 'max:100000'],
            'note' => ['required', 'string', 'max:200'],
        ], [
            'note.required' => 'Escribí por qué se le hace precio.',
        ]);

        if ($package->status === 'entregado') {
            throw ValidationException::withMessages([
                'total' => 'Este paquete ya se entregó y se cobró.',
            ]);
        }

        $package->update([
            // La primera vez se guarda lo que daba la tarifa; después no se pisa.
            'original_total' => $package->original_total ?? $package->total,
            'total' => round((float) $data['total'], 2),
            'price_note' => $data['note'],
            'price_adjusted_by' => $request->user()->id,
            'price_adjusted_at' => now(),
        ]);

        return response()->json([
            'data' => $this->present($package->fresh()->load(self::CUSTOMER_RELATIONS)),
        ]);
    }

    /** Una de las fotos de la caja al llegar al almacén. */
    public function photo(Request $request, Package $package, PackagePhoto $photo): StreamedResponse
    {
        abort_unless(
            $package->user_id === $request->user()->id || $request->user()->isStaff(),
            404,
        );

        abort_unless($photo->package_id === $package->id, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($photo->path), 404);

        return $disk->response(
            $photo->path,
            'paquete-'.$package->tracking_number.'.'.pathinfo($photo->path, PATHINFO_EXTENSION),
            ['Content-Disposition' => 'inline'],
        );
    }

    /** La firma guardada, para el comprobante. */
    public function signature(Request $request, Package $package): StreamedResponse
    {
        // El dueño del paquete y el personal pueden verla; nadie más.
        abort_unless(
            $package->user_id === $request->user()->id || $request->user()->isStaff(),
            404,
        );

        abort_unless($package->signature_path, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($package->signature_path), 404);

        return $disk->response(
            $package->signature_path,
            'firma-'.$package->tracking_number.'.png',
            ['Content-Disposition' => 'inline'],
        );
    }

    public function destroy(Package $package): JsonResponse
    {
        if ($package->status === 'entregado') {
            throw ValidationException::withMessages([
                'status' => 'Un paquete entregado no se puede eliminar.',
            ]);
        }

        $package->delete();

        return response()->json(['message' => 'Paquete eliminado.']);
    }

    /** Resumen para la pantalla de inicio de la app. */
    public function summary(): JsonResponse
    {
        return response()->json([
            'data' => [
                'recibidos_hoy' => Package::whereDate('received_at', today())->count(),
                'en_transito' => Package::where('status', 'en_transito')->count(),
                'listos' => Package::where('status', 'listo')->count(),
                'entregados_mes' => Package::where('status', 'entregado')
                    ->whereMonth('delivered_at', now()->month)
                    ->whereYear('delivered_at', now()->year)
                    ->count(),
                'clientes' => User::customers()->count(),
            ],
        ]);
    }

    /**
     * A dónde hay que llevarle el paquete.
     *
     * @return array<string, mixed>|null
     */
    private function address(User $customer): ?array
    {
        $address = $customer->defaultShippingAddress;

        if (! $address) {
            return null;
        }

        return [
            'province' => $address->province?->name,
            'canton' => $address->canton?->name,
            'district' => $address->district?->name,
            'exact_address' => $address->exact_address,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
        ];
    }

    /** @return array<string, mixed> */
    private function present(Package $package): array
    {
        return [
            'id' => $package->id,
            'tracking_number' => $package->tracking_number,
            'courier' => $package->courier,
            'store' => $package->store,
            'description' => $package->description,
            'weight_lb' => $package->weight_lb,
            'weight_breakdown' => $package->weight_breakdown,
            'exact_weight' => $package->exact_weight,
            'price_per_pound' => $package->price_per_pound,
            'total' => $package->total,
            'original_total' => $package->original_total,
            'price_note' => $package->price_note,
            'price_adjusted_by' => $package->priceAdjustedBy?->fullName(),
            'status' => $package->status,
            'received_at' => $package->received_at?->toDateTimeString(),
            'delivered_at' => $package->delivered_at?->toDateTimeString(),
            'delivered_to_name' => $package->delivered_to_name,
            'delivered_to_identification' => $package->delivered_to_identification,
            'has_signature' => (bool) $package->signature_path,
            'photos' => $package->photos->map(fn (PackagePhoto $photo) => ['id' => $photo->id]),
            // Cada paso, con quién lo hizo: es el historial del paquete.
            'events' => $package->events->map(fn ($event) => [
                'id' => $event->id,
                'status' => $event->status,
                'note' => $event->note,
                'at' => $event->created_at->toDateTimeString(),
                'by' => $event->createdBy?->fullName(),
            ]),
            'customer' => [
                'id' => $package->user->id,
                'locker_code' => $package->user->locker_code,
                'full_name' => $package->user->fullName(),
                'phone' => $package->user->phone,
                'address' => $this->address($package->user),
            ],
        ];
    }
}
