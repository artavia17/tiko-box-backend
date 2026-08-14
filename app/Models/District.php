<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['canton_id', 'code', 'name'])]
class District extends Model
{
    /** @return BelongsTo<Canton, $this> */
    public function canton(): BelongsTo
    {
        return $this->belongsTo(Canton::class);
    }
}
