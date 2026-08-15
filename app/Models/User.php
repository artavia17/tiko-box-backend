<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'first_name',
    'last_name',
    'second_last_name',
    'identification_type',
    'identification',
    'phone',
    'locker_code',
    'name',
    'email',
    'password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Nombre completo tal y como debe aparecer en el casillero.
     */
    public function fullName(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
            $this->second_last_name,
        ])));
    }

    /** @return HasMany<ShippingAddress, $this> */
    public function shippingAddresses(): HasMany
    {
        return $this->hasMany(ShippingAddress::class);
    }

    /**  HasMany<AuthorizedPerson, $this> */
    public function authorizedPersons(): HasMany
    {
        return $this->hasMany(AuthorizedPerson::class);
    }

    /**  HasMany<Prealert, $this> */
    public function prealerts(): HasMany
    {
        return $this->hasMany(Prealert::class);
    }

    /**  HasOne<ShippingAddress, $this> */
    public function defaultShippingAddress(): HasOne
    {
        return $this->hasOne(ShippingAddress::class)->where('is_default', true);
    }
}
