<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\CatalogOption;
use App\Models\Package;
use App\Models\PaymentReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Las listas de transportistas, tiendas y formas de pago del almacén. */
class CatalogController extends Controller
{
    /** Lo lee cualquiera del personal: es lo que llena los selects. */
    public function index(): JsonResponse
    {
        $options = CatalogOption::orderBy('name')->get();

        return response()->json([
            'data' => [
                'carriers' => $this->present($options, 'carrier'),
                'stores' => $this->present($options, 'store'),
                'payment_methods' => $this->present($options, 'payment_method'),
            ],
        ]);
    }

    /** Alta: solo administración. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(CatalogOption::TYPES)],
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('catalog_options', 'name')->where('type', $request->input('type')),
            ],
        ], [
            'name.unique' => 'Ya está en la lista.',
        ]);

        $option = CatalogOption::create([
            'type' => $data['type'],
            'name' => trim($data['name']),
        ]);

        return response()->json(['data' => ['id' => $option->id, 'name' => $option->name]], 201);
    }

    /**
     * Alta en lote: se pega la lista y se agrega lo que falte.
     *
     * Sirve para traer las listas de otro sistema sin teclearlas una por una.
     */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(CatalogOption::TYPES)],
            'names' => ['required', 'array', 'min:1', 'max:500'],
            'names.*' => ['string', 'max:80'],
        ]);

        $existing = CatalogOption::where('type', $data['type'])
            ->pluck('name')
            ->map(fn (string $name) => mb_strtolower($name))
            ->all();

        $added = 0;
        $skipped = 0;
        $seen = [];

        foreach ($data['names'] as $raw) {
            $name = trim($raw);
            $key = mb_strtolower($name);

            // Se ignoran las vacías, las repetidas dentro del propio pegado y
            // las que ya estaban en la lista.
            if ($name === '' || in_array($key, $seen, true) || in_array($key, $existing, true)) {
                $skipped++;

                continue;
            }

            CatalogOption::create(['type' => $data['type'], 'name' => $name]);
            $seen[] = $key;
            $added++;
        }

        return response()->json([
            'data' => ['added' => $added, 'skipped' => $skipped],
            'message' => $added === 0
                ? 'Ya estaban todas en la lista.'
                : "Se agregaron {$added}".($skipped > 0 ? ", {$skipped} ya estaban." : '.'),
        ]);
    }

    /** Baja: solo administración. */
    /**
     * Corrige el nombre de una opción.
     *
     * Los registros guardan el nombre y no el id, así que el cambio se
     * arrastra a lo ya guardado: si no, corregir "Amazonn" dejaría la lista
     * bien y el historial con la falta de ortografía para siempre.
     */
    public function update(Request $request, CatalogOption $option): JsonResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('catalog_options', 'name')
                    ->where('type', $option->type)
                    ->ignore($option->id),
            ],
        ], [
            'name.unique' => 'Ya hay otra con ese nombre.',
        ]);

        $before = $option->name;
        $after = trim($data['name']);

        if ($before === $after) {
            return response()->json([
                'data' => ['id' => $option->id, 'name' => $option->name],
                'message' => 'No cambió nada.',
            ]);
        }

        $updated = DB::transaction(function () use ($option, $before, $after) {
            $option->update(['name' => $after]);

            $column = $this->columnFor($option->type);
            $count = Package::where($column, $before)->update([$column => $after]);

            if ($option->type === 'payment_method') {
                $count += PaymentReport::where('method', $before)
                    ->update(['method' => $after]);
            }

            return $count;
        });

        return response()->json([
            'data' => ['id' => $option->id, 'name' => $option->name],
            'message' => $updated > 0
                ? "Se renombró. Los {$updated} registros que la usaban quedaron al día."
                : 'Se renombró.',
        ]);
    }

    public function destroy(CatalogOption $option): JsonResponse
    {
        // Los paquetes guardan el nombre, no el id, así que borrar una opción
        // no rompe el historial; solo deja de ofrecerse de aquí en adelante.
        $used = Package::where($this->columnFor($option->type), $option->name)->count();

        // Una forma de pago se usa además en lo que reportan los clientes.
        if ($option->type === 'payment_method') {
            $used += PaymentReport::where('method', $option->name)->count();
        }

        $option->delete();

        return response()->json([
            'message' => $used > 0
                ? "Se quitó de la lista. Los {$used} paquetes que ya la usaban la conservan."
                : 'Se quitó de la lista.',
        ]);
    }

    /** En qué columna del paquete vive cada tipo de lista. */
    private function columnFor(string $type): string
    {
        return match ($type) {
            'carrier' => 'courier',
            'payment_method' => 'payment_method',
            default => 'store',
        };
    }

    /**
     * @param  Collection<int, CatalogOption>  $options
     * @return list<array{id: int, name: string}>
     */
    private function present($options, string $type): array
    {
        return $options
            ->where('type', $type)
            ->map(fn (CatalogOption $option) => ['id' => $option->id, 'name' => $option->name])
            ->values()
            ->all();
    }
}
