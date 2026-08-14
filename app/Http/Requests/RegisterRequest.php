<?php

namespace App\Http\Requests;

use App\Models\Canton;
use App\Models\District;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Paso 1: información personal
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'second_last_name' => ['nullable', 'string', 'max:100'],
            'identification_type' => ['required', Rule::in(['nacional', 'extranjero'])],
            'identification' => [
                'required',
                'string',
                Rule::unique('users', 'identification'),
                // Nacional: 1-2345-6789. Extranjero: hasta 10 alfanuméricos en mayúscula.
                $this->input('identification_type') === 'extranjero'
                    ? 'regex:/^[A-Z0-9]{6,10}$/'
                    : 'regex:/^\d-\d{4}-\d{4}$/',
            ],
            'phone' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],

            // Paso 2: dirección de envío en Costa Rica
            'province_id' => ['required', 'integer', Rule::exists('provinces', 'id')],
            'canton_id' => ['required', 'integer', Rule::exists('cantons', 'id')],
            'district_id' => ['required', 'integer', Rule::exists('districts', 'id')],
            'exact_address' => ['required', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],

            // Paso 3: datos de la cuenta
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * El cantón debe pertenecer a la provincia y el distrito al cantón.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $canton = Canton::find($this->input('canton_id'));

                if ($canton && $canton->province_id !== (int) $this->input('province_id')) {
                    $validator->errors()->add('canton_id', 'El cantón no pertenece a la provincia seleccionada.');
                }

                $district = District::find($this->input('district_id'));

                if ($district && $district->canton_id !== (int) $this->input('canton_id')) {
                    $validator->errors()->add('district_id', 'El distrito no pertenece al cantón seleccionado.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identification.regex' => $this->input('identification_type') === 'extranjero'
                ? 'El DIMEX o pasaporte debe tener entre 6 y 10 caracteres, solo letras y números.'
                : 'La cédula debe tener el formato 1-2345-6789.',
            'phone.regex' => 'El teléfono debe tener el formato 8888-8888.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'primer apellido',
            'second_last_name' => 'segundo apellido',
            'identification' => 'número de cédula',
            'phone' => 'teléfono',
            'province_id' => 'provincia',
            'canton_id' => 'cantón',
            'district_id' => 'distrito',
            'exact_address' => 'dirección exacta',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
        ];
    }
}
