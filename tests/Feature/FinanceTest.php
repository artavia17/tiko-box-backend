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

    public function test_un_paquete_sin_costo_queda_pendiente_y_fuera_de_los_totales(): void
    {
        $this->signIn('admin');
        $this->package();
        $this->package(['cost' => null]);

        $report = $this->getJson('/api/staff/finances')->json('data');

        $sinCosto = collect($report['rows'])->firstWhere('cost', null);

        $this->assertNotNull($sinCosto, 'La fila tiene que aparecer igual.');
        $this->assertNull($sinCosto['profit'], 'Sin costo no hay ganancia que mostrar.');
        $this->assertSame(1, $report['pending']);

        // Los totales solo cuentan lo que sí tiene costo: sumar una venta sin
        // su costo daría una ganancia inflada.
        $this->assertSame(1, $report['totals']['packages']);
        $this->assertEqualsWithDelta(65.00, $report['totals']['revenue'], 0.001);
        $this->assertEqualsWithDelta(30.10, $report['totals']['profit'], 0.001);
    }

    public function test_el_costo_se_digita_al_registrar_y_no_se_inventa(): void
    {
        $this->signIn('admin');
        $customer = User::factory()->create(['role' => 'cliente']);

        // Sin costo: queda pendiente.
        $this->postJson('/api/staff/packages', [
            'customer_id' => $customer->id,
            'tracking_number' => 'TBA111',
            'weight_lb' => 10,
        ])->assertCreated();

        $this->assertNull(Package::where('tracking_number', 'TBA111')->first()->cost);

        // Con costo: se guarda tal cual lo digitaron.
        $this->postJson('/api/staff/packages', [
            'customer_id' => $customer->id,
            'tracking_number' => 'TBA222',
            'weight_lb' => 10,
            'cost' => 28.75,
        ])->assertCreated();

        $this->assertEqualsWithDelta(
            28.75,
            (float) Package::where('tracking_number', 'TBA222')->first()->cost,
            0.001,
        );
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
