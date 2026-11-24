<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una factura emitida a mano por administración. */
#[Fillable([
    'issued_by',
    'user_id',
    'customer_name',
    'locker_code',
    'tracking_number',
    'order_number',
    'store',
    'issued_on',
    'weight_lb',
    'total',
    'note',
])]
class ManualInvoice extends Model
{
    /**
     * Cuánto tiempo se puede corregir una factura.
     *
     * Un día. Pasado eso ya se le entregó a alguien —y a veces a Amazon—,
     * así que cambiarla o borrarla sería alterar un documento que alguien
     * más ya tiene en la mano.
     */
    public const EDITABLE_HOURS = 24;

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'weight_lb' => 'float',
            'total' => 'float',
        ];
    }

    public function isEditable(): bool
    {
        return $this->created_at->gt(now()->subHours(self::EDITABLE_HOURS));
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
