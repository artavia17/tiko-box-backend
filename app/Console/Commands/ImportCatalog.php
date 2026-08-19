<?php

namespace App\Console\Commands;

use App\Models\CatalogOption;
use Illuminate\Console\Command;

/**
 * Carga transportistas o tiendas desde un archivo o desde la propia línea de
 * comandos, para traer listas de otro sistema sin pasar por el panel.
 */
class ImportCatalog extends Command
{
    protected $signature = 'catalog:import
        {type : carrier o store}
        {file? : Archivo con un nombre por línea}
        {--names= : Nombres separados por coma, en vez del archivo}';

    protected $description = 'Agrega transportistas o tiendas al catálogo';

    public function handle(): int
    {
        $type = $this->argument('type');

        if (! in_array($type, CatalogOption::TYPES, true)) {
            $this->error('El tipo debe ser carrier o store.');

            return self::FAILURE;
        }

        $names = $this->names();

        if ($names === null) {
            return self::FAILURE;
        }

        if ($names === []) {
            $this->warn('No había nombres que agregar.');

            return self::SUCCESS;
        }

        $existing = CatalogOption::where('type', $type)
            ->pluck('name')
            ->map(fn (string $name) => mb_strtolower($name))
            ->all();

        $added = 0;
        $skipped = 0;
        $seen = [];

        foreach ($names as $name) {
            $key = mb_strtolower($name);

            if (in_array($key, $seen, true) || in_array($key, $existing, true)) {
                $skipped++;

                continue;
            }

            CatalogOption::create(['type' => $type, 'name' => $name]);
            $seen[] = $key;
            $added++;

            $this->line("  + {$name}");
        }

        $label = $type === 'carrier' ? 'transportistas' : 'tiendas';
        $this->info("Se agregaron {$added} {$label}".($skipped > 0 ? ", {$skipped} ya estaban." : '.'));

        return self::SUCCESS;
    }

    /**
     * De dónde salen los nombres: del archivo, de la opción --names o de lo
     * que se le pase por la entrada estándar.
     *
     * @return list<string>|null  Null si el archivo no existe.
     */
    private function names(): ?array
    {
        $raw = null;

        if ($file = $this->argument('file')) {
            if (! is_readable($file)) {
                $this->error("No pudimos leer el archivo: {$file}");

                return null;
            }

            $raw = (string) file_get_contents($file);
        } elseif ($option = $this->option('names')) {
            $raw = (string) $option;
        } elseif (! stream_isatty(STDIN)) {
            // Permite: cat lista.txt | php artisan catalog:import carrier
            $raw = (string) stream_get_contents(STDIN);
        }

        if ($raw === null) {
            $this->error('Pasá un archivo, --names o mandá la lista por la entrada estándar.');

            return null;
        }

        return collect(preg_split('/[\r\n,;]+/', $raw) ?: [])
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->values()
            ->all();
    }
}
