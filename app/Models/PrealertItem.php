<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['prealert_id', 'quantity', 'description', 'price'])]
class PrealertItem extends Model
{
    protected function casts(): array
    {
        return ['price' => 'float'];
    }

    /** @return BelongsTo<Prealert, $this> */
    public function prealert(): BelongsTo
    {
        return $this->belongsTo(Prealert::class);
    }
}
