<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Api\SupportPresenter;
use App\Http\Controllers\Controller;
use App\Models\SupportAttachment;
use App\Models\SupportTicket;
use App\Services\FileScanner;
use App\Services\SupportDesk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** La bandeja de soporte. Cualquier administrador puede tomar un ticket. */
class SupportController extends Controller
{
    public function __construct(
        private readonly SupportDesk $desk,
        private readonly FileScanner $scanner,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'abiertos');

        $tickets = SupportTicket::query()
            ->with(['user', 'agent'])
            ->when($status === 'cerrados',
                fn ($query) => $query->where('status', SupportTicket::CERRADO),
                fn ($query) => $query->where('status', '!=', SupportTicket::CERRADO),
            )
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('subject', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('locker_code', 'like', "%{$search}%"));
                });
            })
            // Sin tomar primero: son los que nadie está mirando.
            ->orderByRaw('assigned_to IS NOT NULL')
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json([
            'data' => $tickets->map(fn (SupportTicket $ticket) => [
                ...SupportPresenter::summary($ticket, 'staff'),
                'customer' => $ticket->user?->fullName(),
                'locker_code' => $ticket->user?->locker_code,
            ]),
            'meta' => [
                'categories' => SupportTicket::CATEGORIES,
                'open_total' => SupportTicket::where('status', '!=', SupportTicket::CERRADO)->count(),
                'unassigned_total' => SupportTicket::where('status', SupportTicket::ABIERTO)
                    ->whereNull('assigned_to')
                    ->count(),
            ],
        ]);
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        $this->desk->markRead($ticket, 'staff');

        return response()->json([
            'data' => SupportPresenter::detail($ticket->fresh()->load([
                'user',
                'agent',
                'messages.author',
                'messages.attachments',
            ]), 'staff'),
        ]);
    }

    /** Tomarlo evita que dos administradores respondan lo mismo a la vez. */
    public function claim(Request $request, SupportTicket $ticket): JsonResponse
    {
        return response()->json([
            'data' => SupportPresenter::summary(
                $this->desk->claim($ticket, $request->user())->load('agent'),
                'staff',
            ),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:4000'],
            'files' => ['nullable', 'array', 'max:'.SupportDesk::MAX_FILES],
            'files.*' => ['file', 'max:'.SupportDesk::MAX_FILE_KB],
        ]);

        $files = array_values(array_filter((array) $request->file('files')));

        foreach ($files as $index => $file) {
            if ($motivo = $this->scanner->reject($file)) {
                throw ValidationException::withMessages(["files.{$index}" => $motivo]);
            }
        }

        if (! ($data['body'] ?? null) && $files === []) {
            throw ValidationException::withMessages(['body' => 'Escribí algo o adjuntá un archivo.']);
        }

        $this->desk->post($ticket, $request->user(), $data['body'] ?? null, $files);

        return response()->json([
            'data' => SupportPresenter::detail($ticket->fresh()->load([
                'user',
                'agent',
                'messages.author',
                'messages.attachments',
            ]), 'staff'),
        ]);
    }

    public function close(Request $request, SupportTicket $ticket): JsonResponse
    {
        return response()->json([
            'data' => SupportPresenter::summary(
                $this->desk->close($ticket, $request->user())->load('agent'),
                'staff',
            ),
        ]);
    }

    public function attachment(SupportTicket $ticket, SupportAttachment $attachment): StreamedResponse
    {
        return SupportPresenter::download($ticket, $attachment);
    }
}
