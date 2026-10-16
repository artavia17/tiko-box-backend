<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Aviso del cliente de que viene un paquete en camino al casillero. */
#[Fillable([
    'user_id',
    'consolidation_id',
    'tracking_number',
    'courier',
    'is_retained',
    'retention_note',
    'invoice_path',
    'origin',
    'expected_arrival',
    'status',
])]
class Prealert extends Model
{
    protected function casts(): array
    {
        return [
            'expected_arrival' => 'date',
            'is_retained' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<PrealertItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PrealertItem::class);
    }

    /** El valor declarado total, que es lo que mira aduana. */
    public function declaredValue(): float
    {
        return round($this->items->sum(fn (PrealertItem $item) => $item->subtotal()), 2);
    }

    /** @return BelongsTo<Consolidation, $this> */
    public function consolidation(): BelongsTo
    {
        return $this->belongsTo(Consolidation::class);
    }
}
