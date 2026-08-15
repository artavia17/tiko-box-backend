<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Aviso del cliente de que viene un paquete en camino al casillero. */
#[Fillable([
    'user_id',
    'tracking_number',
    'origin',
    'courier',
    'currency',
    'expected_arrival',
    'status',
    'notes',
])]
class Prealert extends Model
{
    protected function casts(): array
    {
        return ['expected_arrival' => 'date'];
    }

    /** Valor declarado del paquete: la suma de sus artículos. */
    public function declaredValue(): float
    {
        return (float) $this->items->sum(fn (PrealertItem $item) => $item->quantity * $item->price);
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
}
