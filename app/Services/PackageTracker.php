<?php

namespace App\Services;

use App\Mail\PackageStatusMail;
use App\Models\Package;
use App\Models\PackageEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Registra cada paso del paquete y avisa al cliente por correo.
 *
 * Pasa por acá todo cambio de estado para que el seguimiento y el aviso
 * nunca queden desincronizados con lo que muestra el panel.
 */
class PackageTracker
{
    /**
     * El recorrido del paquete, en orden. Agregar un paso es agregarlo acá:
     * la validación y las pantallas leen de esta lista.
     */
    public const DESCRIPTIONS = [
        'recibido' => 'Llegó a nuestro almacén en Miami y ya está a tu nombre.',
        'en_transito' => 'Va camino a Costa Rica.',
        'aduanas' => 'Está en aduanas en Costa Rica. Apenas salga te lo llevamos.',
        'listo' => 'Está en Costa Rica, listo para entregarte.',
        'entregado' => 'Te lo entregamos con la firma de quien lo recibió.',
    ];

    /** @return list<string> */
    public static function statuses(): array
    {
        return array_keys(self::DESCRIPTIONS);
    }

    /** Los que puede poner el almacén: entregar exige firma, va aparte. */
    public static function manualStatuses(): array
    {
        return array_values(array_diff(self::statuses(), ['entregado']));
    }

    /**
     * Deja constancia del estado actual y manda el correo.
     *
     * @param  User|null  $actor  Quién lo movió, si fue alguien del almacén.
     */
    public function record(Package $package, ?User $actor = null, ?string $note = null): PackageEvent
    {
        $event = PackageEvent::create([
            'package_id' => $package->id,
            'created_by' => $actor?->id,
            'status' => $package->status,
            'note' => $note,
        ]);

        $this->notify($package);

        return $event;
    }

    /**
     * El correo no puede tumbar el registro del paquete: si el servidor de
     * correo falla, queda en el log y el estado igual se guarda.
     */
    private function notify(Package $package): void
    {
        $customer = $package->user;

        if (! $customer?->email) {
            return;
        }

        try {
            Mail::to($customer->email)->send(new PackageStatusMail($package->fresh('user')));
        } catch (\Throwable $exception) {
            Log::warning('No se pudo enviar el aviso del paquete.', [
                'package_id' => $package->id,
                'status' => $package->status,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
