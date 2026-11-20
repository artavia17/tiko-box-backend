<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Un ajuste editable desde administración. */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Los ajustes que existen, con lo que valen si nadie los tocó.
     *
     * La lista vive acá y no en la base: así un ajuste nuevo llega con el
     * código y no hay que acordarse de insertarlo a mano en producción.
     */
    public const DEFAULTS = [
        'whatsapp_location_message' => "¡Hola, [nombre]! Le escribo de Tikabox.\n\n".
            "Estamos preparando la entrega de tu paquete. ¿Me compartís tu ubicación para llegarte bien el día que salgamos? :)\n\n".
            '¡Gracias!',
    ];

    /**
     * Todos los ajustes, con los valores por defecto donde no hay nada.
     *
     * No se llama all() porque ese nombre ya es de Eloquent y significa otra
     * cosa: quien lo lea esperaría una colección de modelos.
     *
     * @return array<string, string>
     */
    public static function values(): array
    {
        return [
            ...self::DEFAULTS,
            ...static::query()->pluck('value', 'key')->all(),
        ];
    }
}
