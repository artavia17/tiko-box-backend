<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\PaymentReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Reporte de pago del cliente y su confirmación.
 *
 * Lo que se protege acá es plata: que el monto no lo ponga el cliente, que
 * nadie reporte dos veces la misma factura, y que confirmar sea potestad de
 * un administrador.
 */
class PaymentReportTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        $customer = User::factory()->create(['role' => 'cliente']);

        $this->actingAs($customer);
        $customer->withAccessToken($customer->createToken('app', ['*'])->accessToken);

        Storage::fake('local');

        return $customer;
    }

    private function package(User $customer, array $overrides = []): Package
    {
        return Package::create(array_merge([
            'user_id' => $customer->id,
            'tracking_number' => 'TBA'.fake()->unique()->numerify('########'),
            'weight_lb' => 2,
            'price_per_pound' => 6.5,
            'total' => 13.0,
            'status' => 'bodega',
            'received_at' => now(),
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'method' => 'sinpe',
            'reference' => '884412',
            'paid_on' => now()->toDateString(),
            'comment' => 'Pagado desde el BAC',
        ], $overrides);
    }

    public function test_el_cliente_reporta_un_pago(): void
    {
        $customer = $this->customer();
        $package = $this->package($customer);

        $this->post("/api/packages/{$package->id}/payments", $this->payload([
            'receipt' => UploadedFile::fake()->create('comprobante.pdf', 20, 'application/pdf'),
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', PaymentReport::PENDIENTE)
            ->assertJsonPath('data.has_receipt', true)
            ->assertJsonPath('data.method_label', 'SINPE Móvil');
    }

    /** El monto sale del paquete: si viniera del formulario, se podría mentir. */
    public function test_el_monto_no_lo_decide_el_cliente(): void
    {
        $customer = $this->customer();
        $package = $this->package($customer);

        $response = $this->post("/api/packages/{$package->id}/payments", $this->payload(['amount' => 1]));

        $response->assertCreated();
        $this->assertEqualsWithDelta(13.0, $response->json('data.amount'), 0.001);
    }

    public function test_solo_se_aceptan_las_tres_formas_de_pago(): void
    {
        $customer = $this->customer();
        $package = $this->package($customer);

        $this->postJson("/api/packages/{$package->id}/payments", $this->payload(['method' => 'tarjeta']))
            ->assertJsonValidationErrors('method');
    }

    public function test_la_fecha_del_pago_no_puede_ser_futura(): void
    {
        $customer = $this->customer();
        $package = $this->package($customer);

        $this->postJson("/api/packages/{$package->id}/payments", $this->payload([
            'paid_on' => now()->addDay()->toDateString(),
        ]))->assertJsonValidationErrors('paid_on');
    }

    public function test_no_se_reporta_dos_veces_la_misma_factura(): void
    {
        $customer = $this->customer();
        $package = $this->package($customer);

        $this->postJson("/api/packages/{$package->id}/payments", $this->payload())->assertCreated();

        $this->postJson("/api/packages/{$package->id}/payments", $this->payload())
            ->assertJsonValidationErrors('package');
    }

    public function test_no_se_reporta_el_pago_de_un_paquete_anulado(): void
    {
        $customer = $this->customer();
        $package = $this->package($customer, ['voided_at' => now()]);

        $this->postJson("/api/packages/{$package->id}/payments", $this->payload())
            ->assertJsonValidationErrors('package');
    }

    public function test_nadie_reporta_el_pago_de_un_paquete_ajeno(): void
    {
        $ajeno = $this->package(User::factory()->create(['role' => 'cliente']));

        $this->customer();

        $this->postJson("/api/packages/{$ajeno->id}/payments", $this->payload())
            ->assertNotFound();
    }

    public function test_el_administrador_confirma_el_pago(): void
    {
        $customer = $this->customer();
        $package = $this->package($customer);

        $report = $this->postJson("/api/packages/{$package->id}/payments", $this->payload())
            ->json('data.id');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $admin->withAccessToken($admin->createToken('test', ['staff'])->accessToken);

        $this->postJson("/api/staff/packages/{$package->id}/payments/{$report}/verify")
            ->assertOk()
            ->assertJsonPath('data.status', PaymentReport::VERIFICADO);

        // Revisado una sola vez: no se confirma lo ya confirmado.
        $this->postJson("/api/staff/packages/{$package->id}/payments/{$report}/verify")
            ->assertJsonValidationErrors('report');
    }

    public function test_un_empleado_no_confirma_pagos(): void
    {
        $customer = $this->customer();
        $package = $this->package($customer);

        $report = $this->postJson("/api/packages/{$package->id}/payments", $this->payload())
            ->json('data.id');

        $empleado = User::factory()->create(['role' => 'empleado']);
        $this->actingAs($empleado);
        $empleado->withAccessToken($empleado->createToken('test', ['staff'])->accessToken);

        $this->postJson("/api/staff/packages/{$package->id}/payments/{$report}/verify")
            ->assertForbidden();
    }

    /** Rechazar sin decir por qué deja al cliente sin saber qué corregir. */
    public function test_rechazar_exige_un_motivo_y_permite_volver_a_reportar(): void
    {
        $customer = $this->customer();
        $package = $this->package($customer);

        $report = $this->postJson("/api/packages/{$package->id}/payments", $this->payload())
            ->json('data.id');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $admin->withAccessToken($admin->createToken('test', ['staff'])->accessToken);

        $this->postJson("/api/staff/packages/{$package->id}/payments/{$report}/reject")
            ->assertJsonValidationErrors('review_note');

        $this->postJson("/api/staff/packages/{$package->id}/payments/{$report}/reject", [
            'review_note' => 'No aparece el depósito.',
        ])->assertOk()->assertJsonPath('data.status', PaymentReport::RECHAZADO);

        // Rechazado, el cliente puede volver a intentarlo.
        $this->actingAs($customer);
        $customer->withAccessToken($customer->createToken('app2', ['*'])->accessToken);

        $this->postJson("/api/packages/{$package->id}/payments", $this->payload(['reference' => '99']))
            ->assertCreated();
    }

    public function test_el_paquete_muestra_el_estado_del_pago(): void
    {
        $customer = $this->customer();
        $package = $this->package($customer);

        $this->postJson("/api/packages/{$package->id}/payments", $this->payload())->assertCreated();

        $this->getJson("/api/packages/{$package->id}")
            ->assertOk()
            ->assertJsonPath('data.payment.status', PaymentReport::PENDIENTE)
            ->assertJsonPath('data.payment.reference', '884412');
    }
}
