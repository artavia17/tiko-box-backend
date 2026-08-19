<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aviso del cliente de que viene un paquete en camino al casillero. */
#[Fillable([
    'user_id',
    'tracking_number',
    'invoice_path',
    'origin',
    'expected_arrival',
    'status',
])]
class Prealert extends Model
{
    protected function casts(): array
    {
        return ['expected_arrival' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
