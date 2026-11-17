<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un archivo adjunto a un mensaje de soporte. */
#[Fillable(['support_message_id', 'path', 'name', 'mime', 'size'])]
class SupportAttachment extends Model
{
    /** Si el navegador la puede mostrar en línea en vez de descargarla. */
    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function isAudio(): bool
    {
        return str_starts_with((string) $this->mime, 'audio/');
    }

    /** @return BelongsTo<SupportMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class, 'support_message_id');
    }
}
