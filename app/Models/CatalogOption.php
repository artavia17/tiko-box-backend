<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Una opción de las listas del almacén: transportista, tienda o forma de pago. */
#[Fillable(['type', 'name'])]
class CatalogOption extends Model
{
    public const TYPES = ['carrier', 'store', 'payment_method'];

    /**
     * Las formas de pago con las que arranca el sistema.
     *
     * Viven acá y no en una base vacía: sin ellas el primer cobro no se
     * podría registrar hasta que alguien se acuerde de crearlas.
     */
    public const DEFAULT_PAYMENT_METHODS = [
        'Efectivo',
        'SINPE Móvil',
        'Transferencia',
        'Tarjeta',
    ];

    /** @return list<string> */
    public static function paymentMethods(): array
    {
        return static::where('type', 'payment_method')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }
}
