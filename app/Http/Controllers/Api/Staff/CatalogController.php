<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\CatalogOption;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Las listas de transportistas y tiendas que usa el almacén. */
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
    public function destroy(CatalogOption $option): JsonResponse
    {
        // Los paquetes guardan el nombre, no el id, así que borrar una opción
        // no rompe el historial; solo deja de ofrecerse de aquí en adelante.
        $used = Package::where($option->type === 'carrier' ? 'courier' : 'store', $option->name)
            ->count();

        $option->delete();

        return response()->json([
            'message' => $used > 0
                ? "Se quitó de la lista. Los {$used} paquetes que ya la usaban la conservan."
                : 'Se quitó de la lista.',
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CatalogOption>  $options
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
