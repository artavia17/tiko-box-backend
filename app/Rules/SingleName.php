<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Un nombre por campo, no el nombre completo.
 *
 * La gente escribe "Juan Carlos Rodríguez Soto" en la casilla del nombre y
 * después el casillero queda a nombre de nadie. Acá se corta eso.
 *
 * Una sola palabra por campo. Quien tenga un nombre compuesto —María José,
 * De la Cruz— escribe la primera parte acá y arregla el resto con soporte:
 * se prefirió eso antes que seguir recibiendo casilleros a nombre de nadie.
 */
class SingleName implements ValidationRule
{
    public function __construct(private readonly string $label) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $palabras = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($palabras) > 1) {
            $fail("Escribí solo {$this->label}, no el nombre completo.");
        }
    }
}
