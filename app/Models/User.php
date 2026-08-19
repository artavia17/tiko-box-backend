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
    'role',
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

    /** Personal del almacén y administradores. */
    public function isStaff(): bool
    {
        return in_array($this->role, ['empleado', 'admin'], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** @return HasMany<ShippingAddress, $this> */
    public function shippingAddresses(): HasMany
    {
        return $this->hasMany(ShippingAddress::class);
    }

    /** @return HasMany<AuthorizedPerson, $this> */
    public function authorizedPersons(): HasMany
    {
        return $this->hasMany(AuthorizedPerson::class);
    }

    /** @return HasMany<Prealert, $this> */
    public function prealerts(): HasMany
    {
        return $this->hasMany(Prealert::class);
    }

    /**
     * Cliente es quien tiene casillero, sin importar sus permisos: el dueño
     * del negocio también compra y recibe paquetes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     */
    public function scopeCustomers($query): void
    {
        $query->whereNotNull('locker_code');
    }

    /** ¿Tiene casillero para recibir paquetes? */
    public function isCustomer(): bool
    {
        return $this->locker_code !== null;
    }

    /** @return HasMany<Package, $this> */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    /** @return HasOne<ShippingAddress, $this> */
    public function defaultShippingAddress(): HasOne
    {
        return $this->hasOne(ShippingAddress::class)->where('is_default', true);
    }
}
