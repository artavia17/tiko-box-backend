<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un ticket de soporte: una consulta con su hilo de respuestas. */
#[Fillable([
    'user_id',
    'assigned_to',
    'category',
    'subject',
    'status',
    'last_message_at',
    'customer_read_message_id',
    'staff_read_message_id',
    'closed_at',
    'closed_by',
])]
class SupportTicket extends Model
{
    public const ABIERTO = 'abierto';

    /** Alguien de administración lo tomó y lo está atendiendo. */
    public const EN_PROCESO = 'en_proceso';

    public const CERRADO = 'cerrado';

    /**
     * Con qué puede necesitar ayuda un cliente.
     *
     * Son las áreas del negocio, no una lista genérica: el cliente elige lo
     * que le pasa y el ticket llega ya clasificado.
     */
    public const CATEGORIES = [
        'paquete' => 'Un paquete',
        'prealerta' => 'Una prealerta',
        'pago' => 'Pagos y facturación',
        'direccion' => 'Mi dirección en Miami',
        'cuenta' => 'Mi cuenta',
        'otro' => 'Otra cosa',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status !== self::CERRADO;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<SupportMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'support_ticket_id')->oldest('id');
    }

    /**
     * Cuántos mensajes tiene sin leer uno de los dos lados.
     *
     * Se cuenta contra el último mensaje leído y no con una bandera por
     * mensaje: así dos administradores leyendo a la vez no se pisan el
     * contador.
     */
    public function unreadFor(string $side): int
    {
        $since = $side === 'cliente'
            ? $this->customer_read_message_id
            : $this->staff_read_message_id;

        return $this->messages()
            // Los propios nunca cuentan como sin leer.
            ->when(
                $side === 'cliente',
                fn ($query) => $query->where('user_id', '!=', $this->user_id),
                fn ($query) => $query->where('user_id', $this->user_id),
            )
            ->when($since, fn ($query) => $query->where('id', '>', $since))
            ->count();
    }
}
