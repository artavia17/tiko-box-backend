<?php

namespace App\Services;

use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Las reglas del soporte, en un solo lugar.
 *
 * Las usan la pantalla del cliente y la de administración; duplicadas, las
 * dos versiones se irían separando y el historial terminaría diciendo cosas
 * distintas según quién lo mire.
 */
class SupportDesk
{
    /** Lo que no se puede adjuntar: lo que se ejecuta. */
    public const BLOCKED_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'com', 'msi', 'sh', 'app', 'scr', 'jar',
        'php', 'phar', 'js', 'vbs', 'ps1', 'dll',
    ];

    public const MAX_FILES = 5;

    /** 15 MB: alcanza para un audio largo o una foto sin comprimir. */
    public const MAX_FILE_KB = 15360;

    public function __construct(private readonly SupportNotifier $notifier) {}

    /**
     * Abre un ticket con su primer mensaje.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function open(
        User $customer,
        string $category,
        string $subject,
        string $body,
        array $files = [],
    ): SupportTicket {
        $ticket = SupportTicket::create([
            'user_id' => $customer->id,
            'category' => $category,
            'subject' => trim($subject),
            'status' => SupportTicket::ABIERTO,
        ]);

        $this->post($ticket, $customer, $body, $files);

        return $ticket->fresh();
    }

    /**
     * Agrega un mensaje y avisa al otro lado.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function post(
        SupportTicket $ticket,
        User $author,
        ?string $body,
        array $files = [],
    ): SupportMessage {
        if (! $ticket->isOpen()) {
            throw ValidationException::withMessages([
                'ticket' => 'Este ticket está cerrado. Abrí uno nuevo si seguís necesitando ayuda.',
            ]);
        }

        $message = DB::transaction(function () use ($ticket, $author, $body, $files) {
            $message = SupportMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $author->id,
                'body' => $body ? trim($body) : null,
            ]);

            foreach ($files as $file) {
                SupportAttachment::create([
                    'support_message_id' => $message->id,
                    'path' => $file->store("support/{$ticket->id}", 'local'),
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            $delCliente = $author->id === $ticket->user_id;

            $ticket->forceFill([
                'last_message_at' => now(),
                // Quien escribe ya leyó todo lo suyo.
                ...($delCliente
                    ? ['customer_read_message_id' => $message->id]
                    : ['staff_read_message_id' => $message->id]),
                // La primera respuesta de administración lo pone en proceso
                // sin que nadie tenga que acordarse de marcarlo.
                ...($delCliente || $ticket->status !== SupportTicket::ABIERTO
                    ? []
                    : ['status' => SupportTicket::EN_PROCESO, 'assigned_to' => $author->id]),
            ])->save();

            return $message;
        });

        // Fuera de la transacción: un correo lento o caído no puede dejar el
        // mensaje a medio guardar.
        $this->notifier->messagePosted($message->load('attachments'), $ticket->fresh());

        return $message;
    }

    /** Lo toma quien va a atenderlo, para que no lo trabajen dos a la vez. */
    public function claim(SupportTicket $ticket, User $agent): SupportTicket
    {
        if (! $ticket->isOpen()) {
            throw ValidationException::withMessages([
                'ticket' => 'Este ticket ya está cerrado.',
            ]);
        }

        $ticket->forceFill([
            'assigned_to' => $agent->id,
            'status' => SupportTicket::EN_PROCESO,
        ])->save();

        return $ticket->fresh();
    }

    /** Lo cierra cualquiera de los dos lados. */
    public function close(SupportTicket $ticket, User $who): SupportTicket
    {
        if (! $ticket->isOpen()) {
            return $ticket;
        }

        $ticket->forceFill([
            'status' => SupportTicket::CERRADO,
            'closed_at' => now(),
            'closed_by' => $who->id,
        ])->save();

        $this->notifier->ticketClosed($ticket->fresh(), $who);

        return $ticket->fresh();
    }

    /** Deja constancia de hasta qué mensaje leyó uno de los dos lados. */
    public function markRead(SupportTicket $ticket, string $side): void
    {
        $ultimo = $ticket->messages()->max('id');

        if (! $ultimo) {
            return;
        }

        $ticket->forceFill([
            $side === 'cliente' ? 'customer_read_message_id' : 'staff_read_message_id' => $ultimo,
        ])->save();
    }
}
