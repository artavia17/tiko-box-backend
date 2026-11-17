<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportAttachment;
use App\Models\SupportTicket;
use App\Services\FileScanner;
use App\Services\SupportDesk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Soporte visto por el cliente.
 *
 * El dueño sale siempre del token, nunca de un parámetro: si el cliente
 * pudiera mandar el id de otro, leer tickets ajenos sería cuestión de
 * cambiar un número en la petición.
 */
class SupportController extends Controller
{
    public function __construct(
        private readonly SupportDesk $desk,
        private readonly FileScanner $scanner,
    ) {}

    /** Los tickets del cliente, los abiertos primero. */
    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->with('agent')
            ->orderByRaw("CASE status WHEN 'cerrado' THEN 1 ELSE 0 END")
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json([
            'data' => $tickets->map(fn (SupportTicket $ticket) => SupportPresenter::summary($ticket, 'cliente')),
            'meta' => [
                'categories' => SupportTicket::CATEGORIES,
                'unread' => $tickets->sum(fn (SupportTicket $ticket) => $ticket->unreadFor('cliente')),
            ],
        ]);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeOwner($request, $ticket);

        // Abrirlo es leerlo: así el aviso desaparece solo.
        $this->desk->markRead($ticket, 'cliente');

        return response()->json([
            'data' => SupportPresenter::detail($ticket->fresh()->load([
                'agent',
                'messages.author',
                'messages.attachments',
            ]), 'cliente'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', array_keys(SupportTicket::CATEGORIES))],
            'subject' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:4000'],
            'files' => ['nullable', 'array', 'max:'.SupportDesk::MAX_FILES],
            'files.*' => ['file', 'max:'.SupportDesk::MAX_FILE_KB],
        ], $this->messages());

        $files = $this->checked($request);

        $ticket = $this->desk->open(
            $request->user(),
            $data['category'],
            $data['subject'],
            $data['body'],
            $files,
        );

        return response()->json([
            'data' => SupportPresenter::detail(
                $ticket->load(['agent', 'messages.author', 'messages.attachments']),
                'cliente',
            ),
        ], 201);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeOwner($request, $ticket);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:4000'],
            'files' => ['nullable', 'array', 'max:'.SupportDesk::MAX_FILES],
            'files.*' => ['file', 'max:'.SupportDesk::MAX_FILE_KB],
        ], $this->messages());

        $files = $this->checked($request);

        // Un mensaje sin texto ni archivo no es un mensaje.
        if (! ($data['body'] ?? null) && $files === []) {
            throw ValidationException::withMessages(['body' => 'Escribí algo o adjuntá un archivo.']);
        }

        $this->desk->post($ticket, $request->user(), $data['body'] ?? null, $files);

        return response()->json([
            'data' => SupportPresenter::detail($ticket->fresh()->load([
                'agent',
                'messages.author',
                'messages.attachments',
            ]), 'cliente'),
        ]);
    }

    /** El cliente también puede dar por resuelto su ticket. */
    public function close(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeOwner($request, $ticket);

        return response()->json([
            'data' => SupportPresenter::summary(
                $this->desk->close($ticket, $request->user())->load('agent'),
                'cliente',
            ),
        ]);
    }

    /** Sin leer, para el aviso del panel. Se consulta seguido: va liviano. */
    public function unread(Request $request): JsonResponse
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->where('status', '!=', SupportTicket::CERRADO)
            ->get();

        $unread = $tickets->sum(fn (SupportTicket $ticket) => $ticket->unreadFor('cliente'));

        return response()->json([
            'data' => [
                'unread' => $unread,
                // Para que el aviso pueda nombrar el ticket.
                'ticket' => $tickets
                    ->first(fn (SupportTicket $ticket) => $ticket->unreadFor('cliente') > 0)
                    ?->only(['id', 'subject']),
            ],
        ]);
    }

    public function attachment(
        Request $request,
        SupportTicket $ticket,
        SupportAttachment $attachment,
    ): StreamedResponse {
        $this->authorizeOwner($request, $ticket);

        return SupportPresenter::download($ticket, $attachment);
    }

    /**
     * Revisa los archivos antes de guardarlos.
     *
     * @return array<int, UploadedFile>
     */
    private function checked(Request $request): array
    {
        $files = array_values(array_filter((array) $request->file('files')));

        foreach ($files as $index => $file) {
            if ($motivo = $this->scanner->reject($file)) {
                throw ValidationException::withMessages(["files.{$index}" => $motivo]);
            }
        }

        return $files;
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'category.required' => 'Elegí con qué necesitás ayuda.',
            'subject.required' => 'Ponele un asunto.',
            'body.required' => 'Contanos qué pasa.',
            'files.max' => 'Máximo '.SupportDesk::MAX_FILES.' archivos por mensaje.',
            'files.*.max' => 'Cada archivo puede pesar hasta 15 MB.',
        ];
    }

    private function authorizeOwner(Request $request, SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
    }
}
