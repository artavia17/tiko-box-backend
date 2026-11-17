<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Revisión de los archivos que sube un cliente.
 *
 * Son dos capas distintas y conviene no confundirlas:
 *
 * 1. Lo que siempre corre: el tipo real del archivo se lee de su contenido,
 *    no del nombre ni de lo que diga el navegador, y se compara contra una
 *    lista de lo permitido. Esto para lo que de verdad se usa para atacar:
 *    ejecutables, scripts y documentos con macros disfrazados de imagen.
 *
 * 2. El antivirus de verdad (ClamAV), que solo corre si está instalado en el
 *    servidor. Sin él no hay búsqueda de firmas de virus: la lista de la
 *    capa 1 evita lo evidente, pero no detecta un PDF malicioso.
 */
class FileScanner
{
    /** Lo que se acepta: tipo real del archivo => extensiones que le calzan. */
    public const ALLOWED = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif'],
        'image/heic' => ['heic'],
        'application/pdf' => ['pdf'],
        'text/plain' => ['txt', 'log', 'csv'],
        'text/csv' => ['csv'],
        'audio/mpeg' => ['mp3'],
        'audio/mp4' => ['m4a', 'mp4'],
        'audio/aac' => ['aac'],
        'audio/ogg' => ['ogg', 'oga'],
        'audio/webm' => ['webm'],
        'audio/wav' => ['wav'],
        'audio/x-wav' => ['wav'],
        'video/mp4' => ['mp4'],
        'video/quicktime' => ['mov'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
    ];

    /**
     * Revisa un archivo. Devuelve el motivo del rechazo, o null si pasa.
     */
    public function reject(UploadedFile $file): ?string
    {
        // El tipo real sale del contenido. El nombre y lo que declara el
        // navegador los escribe quien sube, así que no valen como prueba.
        $mime = (string) $file->getMimeType();
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! isset(self::ALLOWED[$mime])) {
            return 'No aceptamos ese tipo de archivo.';
        }

        // La extensión tiene que calzar con el contenido: un ejecutable
        // renombrado a .png se delata justo acá.
        if (! in_array($extension, self::ALLOWED[$mime], true)) {
            return 'El archivo no coincide con su extensión.';
        }

        return $this->virus($file);
    }

    /**
     * Pasa el archivo por ClamAV si el servidor lo tiene.
     *
     * Devuelve null cuando no hay antivirus instalado: bloquear todas las
     * subidas por eso dejaría el soporte inservible, así que se registra y
     * se sigue con la validación de tipo.
     */
    private function virus(UploadedFile $file): ?string
    {
        $binary = (string) config('tikabox.clamav');

        if ($binary === '' || ! is_executable($binary)) {
            Log::debug('Sin antivirus: el archivo pasa solo por la validación de tipo.');

            return null;
        }

        $process = new Process([$binary, '--no-summary', '--stdout', $file->getRealPath()]);
        $process->setTimeout(30);
        $process->run();

        // 0 limpio, 1 infectado, 2 error del propio antivirus.
        return match ($process->getExitCode()) {
            0 => null,
            1 => 'Ese archivo tiene un virus y no lo podemos recibir.',
            default => $this->unavailable($process->getErrorOutput()),
        };
    }

    private function unavailable(string $error): ?string
    {
        Log::warning('El antivirus falló; el archivo pasa solo por la validación de tipo.', [
            'error' => $error,
        ]);

        return null;
    }
}
