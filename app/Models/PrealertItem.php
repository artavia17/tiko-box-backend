<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una línea del contenido declarado: qué viene, cuántos y cuánto vale. */
#[Fillable(['prealert_id', 'quantity', 'description', 'amount'])]
class PrealertItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'amount' => 'float',
        ];
    }

    /** Lo que vale esta línea: el unitario por la cantidad. */
    public function subtotal(): float
    {
        return round($this->quantity * $this->amount, 2);
    }

    /** @return BelongsTo<Prealert, $this> */
    public function prealert(): BelongsTo
    {
        return $this->belongsTo(Prealert::class);
    }
}
