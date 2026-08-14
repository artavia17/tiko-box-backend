<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $address = $this->whenLoaded('defaultShippingAddress');

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'second_last_name' => $this->second_last_name,
            'full_name' => $this->fullName(),
            'identification' => $this->identification,
            'phone' => $this->phone,
            'email' => $this->email,
            'locker_code' => $this->locker_code,
            'shipping_address' => $address && $address->exists ? [
                'province' => $address->province?->name,
                'canton' => $address->canton?->name,
                'district' => $address->district?->name,
                'exact_address' => $address->exact_address,
                'latitude' => $address->latitude,
                'longitude' => $address->longitude,
            ] : null,
        ];
    }
}
