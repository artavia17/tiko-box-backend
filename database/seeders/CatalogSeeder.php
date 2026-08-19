<?php

namespace Database\Seeders;

use App\Models\CatalogOption;
use Illuminate\Database\Seeder;

/** Listas iniciales de transportistas y tiendas. */
class CatalogSeeder extends Seeder
{
    /** Los transportistas con los que trabaja el almacén. */
    private const CARRIERS = [
        'AAA COOPER', 'Amazon Logistic', 'Averitt', 'Customer', 'DHL',
        'Entregado por Cliente', 'Estes Express', 'FEDEX Express', 'FEDEX Freight',
        'FEDEX Ground', 'GOFO', 'Lasership', 'Old Dominion Freight', 'OTROS',
        'R+L Carriers', 'Ready Courier', 'Roadrunner', 'Saia LTL Freight',
        'Southeastern Freight Lines', 'SPEEDX', 'SPX', 'TForce Freight', 'TNT',
        'UPS', 'USPS', 'XPO Logistics', 'YRC Freight',
    ];

    /** Tiendas frecuentes; el resto se agrega desde el panel. */
    private const STORES = [
        'Amazon', 'eBay', 'Walmart', 'Shein', 'Temu', 'AliExpress', 'Best Buy',
        'Target', 'Home Depot', 'Nike', 'Adidas', 'Apple', 'Otra',
    ];

    public function run(): void
    {
        foreach (self::CARRIERS as $name) {
            CatalogOption::firstOrCreate(['type' => 'carrier', 'name' => $name]);
        }

        foreach (self::STORES as $name) {
            CatalogOption::firstOrCreate(['type' => 'store', 'name' => $name]);
        }

        $this->command?->info('Catálogo listo.');
    }
}
