<?php

namespace App\Http\Controllers\Api;

use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cómo se ve un ticket desde cualquier pantalla.
 *
 * Una sola versión para el cliente y para administración: con dos, lo que
 * cada lado ve del mismo ticket terminaría sin coincidir.
 */
class SupportPresenter
{
    /** @return array<string, mixed> */
    public static function summary(SupportTicket $ticket, string $side): array
    {
        return [
            'id' => $ticket->id,
            'category' => $ticket->category,
            'category_label' => SupportTicket::CATEGORIES[$ticket->category] ?? $ticket->category,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'open' => $ticket->isOpen(),
            'agent' => $ticket->agent?->fullName(),
            'unread' => $ticket->unreadFor($side),
            'last_message_at' => $ticket->last_message_at?->toDateTimeString(),
            'closed_at' => $ticket->closed_at?->toDateTimeString(),
            'created_at' => $ticket->created_at->toDateTimeString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(SupportTicket $ticket, string $side): array
    {
        return [
            ...self::summary($ticket, $side),
            'customer' => [
                'id' => $ticket->user?->id,
                'full_name' => $ticket->user?->fullName(),
                'locker_code' => $ticket->user?->locker_code,
                'has_avatar' => (bool) $ticket->user?->avatar_path,
            ],
            'messages' => $ticket->messages->map(fn (SupportMessage $message) => [
                'id' => $message->id,
                'body' => $message->body,
                // De qué lado viene se decide por el dueño del ticket, no
                // por el rol: un administrador que abre su propio ticket es
                // el cliente de esa conversación. Cada pantalla decide con
                // esto de qué lado dibujar la burbuja.
                'from_customer' => $message->user_id === $ticket->user_id,
                'author' => $message->author?->fullName(),
                'author_id' => $message->user_id,
                'at' => $message->created_at->toDateTimeString(),
                'attachments' => $message->attachments->map(fn (SupportAttachment $file) => [
                    'id' => $file->id,
                    'name' => $file->name,
                    'mime' => $file->mime,
                    'size' => $file->size,
                    'is_image' => $file->isImage(),
                    'is_audio' => $file->isAudio(),
                ])->values(),
            ])->values(),
        ];
    }

    /** Entrega un adjunto que ya fue autorizado por quien llama. */
    public static function download(SupportTicket $ticket, SupportAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->message?->support_ticket_id === $ticket->id, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);

        // nosniff: aunque el tipo se validó al subir, el navegador no debe
        // reinterpretar el contenido por su cuenta.
        return $disk->response($attachment->path, $attachment->name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
