<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Paquete recibido en el almacén y facturado al cliente. */
#[Fillable([
    'user_id',
    'registered_by',
    'prealert_id',
    'tracking_number',
    'courier',
    'store',
    'description',
    'photo_path',
    'weight_lb',
    'price_per_pound',
    'total',
    'status',
    'received_at',
    'delivered_at',
    'delivered_to_name',
    'delivered_to_identification',
    'signature_path',
])]
class Package extends Model
{
    protected function casts(): array
    {
        return [
            'weight_lb' => 'float',
            'price_per_pound' => 'float',
            'total' => 'float',
            'received_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /** @return HasMany<PackageEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PackageEvent::class)->oldest();
    }

    /** @return BelongsTo<Prealert, $this> */
    public function prealert(): BelongsTo
    {
        return $this->belongsTo(Prealert::class);
    }
}
