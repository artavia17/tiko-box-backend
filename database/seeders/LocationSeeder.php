<?php

namespace Database\Seeders;

use App\Models\Canton;
use App\Models\District;
use App\Models\Province;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Provincias, cantones y distritos de Costa Rica.
 *
 * El dataset vive en database/data/cr-locations.json para que el seeder no
 * dependa de la red. Se generó desde ubicaciones.paginasweb.cr
 * (7 provincias, 82 cantones, 484 distritos).
 */
class LocationSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $path = database_path('data/cr-locations.json');

        if (! is_file($path)) {
            throw new RuntimeException("No se encontró el dataset de ubicaciones en {$path}");
        }

        /** @var array<int, array{id:int, name:string, cantons:array}> $provinces */
        $provinces = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($provinces as $provinceData) {
            $province = Province::updateOrCreate(
                ['code' => $provinceData['id']],
                ['name' => $provinceData['name']],
            );

            foreach ($provinceData['cantons'] as $cantonData) {
                $canton = Canton::updateOrCreate(
                    ['province_id' => $province->id, 'code' => $cantonData['id']],
                    ['name' => $cantonData['name']],
                );

                foreach ($cantonData['districts'] as $districtData) {
                    District::updateOrCreate(
                        ['canton_id' => $canton->id, 'code' => $districtData['id']],
                        ['name' => $districtData['name']],
                    );
                }
            }
        }

        $this->command?->info(sprintf(
            'Ubicaciones cargadas: %d provincias, %d cantones, %d distritos.',
            Province::count(),
            Canton::count(),
            District::count(),
        ));
    }
}
