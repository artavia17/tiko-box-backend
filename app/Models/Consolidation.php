<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un grupo de 2 a 10 prealertas del mismo cliente que se procesan juntas.
 *
 * El estado no se guarda: se deduce de las fechas. Guardarlo obligaría a
 * correr un proceso periódico para vencer las ventanas, y el día que ese
 * proceso falle el cliente vería "Activo" en algo que venció hace una semana.
 */
#[Fillable([
    'user_id',
    'activated_at',
    'expires_at',
    'requested_at',
    'processed_at',
    'generated_package_id',
])]
class Consolidation extends Model
{
    public const INACTIVO = 'inactivo';

    public const ACTIVO = 'activo';

    public const PRONTA_EXPIRACION = 'pronta_expiracion';

    public const COMPLETADO = 'completado';

    public const SOLICITADO = 'solicitado';

    public const PENDIENTE = 'pendiente';

    public const PROCESADO = 'procesado';

    /** Estados en los que todavía se puede meter o sacar prealertas. */
    public const OPEN_STATES = [self::INACTIVO, self::ACTIVO, self::PRONTA_EXPIRACION];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Prealert, $this> */
    public function prealerts(): HasMany
    {
        return $this->hasMany(Prealert::class);
    }

    /** @return BelongsTo<Package, $this> */
    public function generatedPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'generated_package_id');
    }

    /** Cuántas de sus prealertas ya tienen el paquete en bodega. */
    public function receivedCount(): int
    {
        return $this->prealerts->where('status', '!=', 'pendiente')->count();
    }

    public function allArrived(): bool
    {
        return $this->prealerts->isNotEmpty()
            && $this->receivedCount() === $this->prealerts->count();
    }

    /** El estado que ve el cliente, deducido de los hechos. */
    public function status(): string
    {
        if ($this->processed_at) {
            return self::PROCESADO;
        }

        if ($this->requested_at) {
            return self::SOLICITADO;
        }

        if (! $this->activated_at) {
            return self::INACTIVO;
        }

        if ($this->expires_at?->isPast()) {
            return self::PENDIENTE;
        }

        if ($this->allArrived()) {
            return self::COMPLETADO;
        }

        $soon = (int) config('tikabox.consolidation.expiring_soon_hours');

        return $this->expires_at?->diffInHours(now(), absolute: true) <= $soon
            ? self::PRONTA_EXPIRACION
            : self::ACTIVO;
    }

    /** Arranca la ventana cuando llega el primer paquete del grupo. */
    public function activateOnFirstArrival(): void
    {
        if ($this->activated_at || $this->processed_at) {
            return;
        }

        $this->update([
            'activated_at' => now(),
            'expires_at' => now()->addDays((int) config('tikabox.consolidation.window_days')),
        ]);
    }

    public function isOpen(): bool
    {
        return in_array($this->status(), self::OPEN_STATES, true);
    }

    /** Se puede pedir el envío anticipado mientras la ventana siga corriendo. */
    public function canBeRequested(): bool
    {
        return in_array(
            $this->status(),
            [self::ACTIVO, self::PRONTA_EXPIRACION, self::COMPLETADO],
            true,
        );
    }

    /** Solo se borra mientras no haya llegado nada. */
    public function canBeDeleted(): bool
    {
        return $this->status() === self::INACTIVO;
    }

    public function hasRoom(): bool
    {
        return $this->prealerts->count() < (int) config('tikabox.consolidation.max_prealerts');
    }
}
