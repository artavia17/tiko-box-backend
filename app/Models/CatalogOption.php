<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Una opción de las listas del almacén: un transportista o una tienda. */
#[Fillable(['type', 'name'])]
class CatalogOption extends Model
{
    public const TYPES = ['carrier', 'store'];
}
