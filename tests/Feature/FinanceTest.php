<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finanzas: costo, venta y ganancia por paquete.
 *
 * Lo que se cuida acá es que los números sean los del momento en que pasó
 * cada cosa, y no se recalculen con las tarifas de hoy.
 */
class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user);
        $user->withAccessToken($user->createToken('test', ['staff'])->accessToken);

        return $user;
    }

    private function package(array $overrides = []): Package
    {
        return Package::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'cliente'])->id,
            'tracking_number' => 'TBA'.fake()->unique()->numerify('#########'),
            'weight_lb' => 10,
            'price_per_pound' => 6.5,
            'total' => 65,
            'cost' => 34.90,
            'exchange_rate' => 466,
            'status' => 'recibido',
            'received_at' => now(),
        ], $overrides));
    }

    public function test_calcula_costo_venta_y_ganancia_por_paquete(): void
    {
        $this->signIn('admin');
        $this->package();

        $response = $this->getJson('/api/staff/finances');

        $response->assertOk();
        $row = $response->json('data.rows.0');

        $this->assertEqualsWithDelta(34.90, $row['cost'], 0.001);
        $this->assertEqualsWithDelta(65.00, $row['revenue'], 0.001);
        $this->assertEqualsWithDelta(30.10, $row['profit'], 0.001);

        // En colones, al cambio que tenía ese paquete.
        $this->assertEqualsWithDelta(34.90 * 466, $row['cost_crc'], 0.01);
        $this->assertEqualsWithDelta(30.10 * 466, $row['profit_crc'], 0.01);
        $this->assertFalse($row['estimated']);
    }

    public function test_los_totales_suman_lo_que_muestran_las_filas(): void
    {
        $this->signIn('admin');
        $this->package();
        $this->package(['weight_lb' => 2, 'total' => 13, 'cost' => 6.98]);

        $totals = $this->getJson('/api/staff/finances')->json('data.totals');

        $this->assertSame(2, $totals['packages']);
        $this->assertEqualsWithDelta(41.88, $totals['cost'], 0.001);
        $this->assertEqualsWithDelta(78.00, $totals['revenue'], 0.001);
        $this->assertEqualsWithDelta(36.12, $totals['profit'], 0.001);
    }

    public function test_un_paquete_viejo_sin_costo_se_estima_y_queda_marcado(): void
    {
        $this->signIn('admin');
        $this->package(['cost' => null, 'exchange_rate' => null]);

        $row = $this->getJson('/api/staff/finances')->json('data.rows.0');

        // 10 lb por la tarifa de costo configurada.
        $this->assertEqualsWithDelta(10 * config('tikabox.cost_per_pound'), $row['cost'], 0.01);
        $this->assertTrue($row['estimated'], 'Tiene que avisar que ese costo es estimado.');
        $this->assertSame(1, $this->getJson('/api/staff/finances')->json('data.estimated'));
    }

    public function test_el_tipo_de_cambio_viejo_no_se_pisa_con_el_de_hoy(): void
    {
        $this->signIn('admin');
        $this->package(['exchange_rate' => 500]);

        config(['tikabox.exchange_rate' => 600]);

        $row = $this->getJson('/api/staff/finances')->json('data.rows.0');

        $this->assertEqualsWithDelta(500, $row['exchange_rate'], 0.01);
        $this->assertEqualsWithDelta(65 * 500, $row['revenue_crc'], 0.01);
    }

    public function test_se_puede_corregir_el_costo_con_la_factura_del_proveedor(): void
    {
        $this->signIn('admin');
        $package = $this->package();

        $response = $this->patchJson("/api/staff/finances/packages/{$package->id}/cost", [
            'cost' => 40,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.estimated', false);
        $this->assertEqualsWithDelta(25.00, $response->json('data.profit'), 0.001);
        $this->assertEqualsWithDelta(40.00, (float) $package->fresh()->cost, 0.001);
    }

    public function test_el_resumen_por_mes_agrupa_el_ano(): void
    {
        $this->signIn('admin');
        $this->package(['received_at' => now()->setMonth(9)->setDay(15)]);
        $this->package(['received_at' => now()->setMonth(9)->setDay(20)]);
        $this->package(['received_at' => now()->setMonth(10)->setDay(2)]);

        $months = $this->getJson('/api/staff/finances')->json('data.months');

        $this->assertCount(2, $months);
        $this->assertSame(2, $months[0]['packages']);
        $this->assertSame(1, $months[1]['packages']);
    }

    public function test_un_empleado_no_ve_las_finanzas(): void
    {
        $this->signIn('empleado');

        $this->getJson('/api/staff/finances')->assertForbidden();
    }
}
