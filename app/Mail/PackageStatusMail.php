<?php

namespace App\Mail;

use App\Models\Package;
use App\Services\PackageTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso al cliente cada vez que su paquete avanza. */
class PackageStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Título del correo según el estado. */
    private const SUBJECTS = [
        'recibido' => 'Recibimos tu paquete en Miami',
        'en_transito' => 'Tu paquete va camino a Costa Rica',
        'aduanas' => 'Tu paquete está en aduanas',
        'listo' => 'Tu paquete ya está en Costa Rica',
        'entregado' => 'Entregamos tu paquete',
    ];

    public function __construct(public Package $package) {}

    public function envelope(): Envelope
    {
        $subject = self::SUBJECTS[$this->package->status] ?? 'Novedades de tu paquete';

        return new Envelope(subject: "{$subject} · {$this->package->tracking_number}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.package-status',
            with: [
                'firstName' => $this->package->user?->first_name ?? '',
                'package' => $this->package,
                'description' => PackageTracker::DESCRIPTIONS[$this->package->status] ?? '',
                'steps' => $this->steps(),
                'dashboardUrl' => rtrim((string) config('app.frontend_url'), '/').'/dashboard/paquetes',
            ],
        );
    }

    /**
     * Los cuatro pasos con su estado, para dibujar el recorrido en el correo.
     *
     * @return list<array{key: string, label: string, done: bool, current: bool}>
     */
    private function steps(): array
    {
        $order = PackageTracker::statuses();
        $labels = [
            'recibido' => 'Recibido en Miami',
            'en_transito' => 'En tránsito',
            'aduanas' => 'En aduanas',
            'listo' => 'En Costa Rica',
            'entregado' => 'Entregado',
        ];

        $current = array_search($this->package->status, $order, true);
        $current = $current === false ? 0 : $current;

        return array_map(fn (string $key, int $index) => [
            'key' => $key,
            'label' => $labels[$key],
            'done' => $index <= $current,
            'current' => $index === $current,
        ], $order, array_keys($order));
    }
}
