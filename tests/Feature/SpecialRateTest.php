<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Precio especial por libra al registrar el paquete.
 *
 * Se le hace precio cobrándole la libra más barata que la de lista. Queda
 * guardado en los mismos campos que el precio especial que se hace después,
 * así se ve solo en la lista del cliente, en la del almacén y en la factura.
 */
class SpecialRateTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user);
        $user->withAccessToken($user->createToken('test', ['staff'])->accessToken);

        return $user;
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'cliente']);
    }

    /** 10 lb a la tarifa de lista de $6.5 son $65.00. */
    private function payload(User $customer, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $customer->id,
            'tracking_number' => 'TBA123456789',
            'weight_lb' => 10,
        ], $overrides);
    }

    public function test_una_tarifa_mas_barata_baja_el_total_y_guarda_la_de_lista(): void
    {
        $this->signIn('admin');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer, [
            'price_per_pound' => 4,
        ]))->assertCreated();

        $package = Package::first();

        $this->assertEqualsWithDelta(40.00, (float) $package->total, 0.001);
        $this->assertEqualsWithDelta(65.00, (float) $package->original_total, 0.001);
        $this->assertEqualsWithDelta(4.00, (float) $package->price_per_pound, 0.001);
        $this->assertNotNull($package->price_adjusted_at);
    }

    public function test_sin_tarifa_especial_se_cobra_la_de_lista_y_no_queda_rastro(): void
    {
        $this->signIn('admin');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer))->assertCreated();

        $package = Package::first();

        $this->assertEqualsWithDelta(65.00, (float) $package->total, 0.001);
        $this->assertEqualsWithDelta(6.50, (float) $package->price_per_pound, 0.001);
        $this->assertNull($package->original_total);
        $this->assertNull($package->price_note);
        $this->assertNull($package->price_adjusted_at);
    }

    public function test_no_se_puede_cobrar_la_libra_mas_cara_que_la_de_lista(): void
    {
        $this->signIn('admin');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer, [
            'price_per_pound' => 9,
        ]))->assertJsonValidationErrors('price_per_pound');

        $this->assertSame(0, Package::count());
    }

    public function test_un_empleado_no_puede_hacer_precio(): void
    {
        $this->signIn('empleado');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer, [
            'price_per_pound' => 4,
        ]))->assertForbidden();

        $this->assertSame(0, Package::count());
    }

    public function test_un_empleado_si_puede_registrar_a_la_tarifa_de_lista(): void
    {
        $this->signIn('empleado');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer))->assertCreated();

        $this->assertEqualsWithDelta(65.00, (float) Package::first()->total, 0.001);
    }

    public function test_el_minimo_de_una_libra_sigue_aplicando_con_tarifa_especial(): void
    {
        $this->signIn('admin');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer, [
            'weight_lb' => 0.4,
            'price_per_pound' => 4,
        ]))->assertCreated();

        // Pesa menos de una libra, así que se cobra una a la tarifa pactada.
        $this->assertEqualsWithDelta(4.00, (float) Package::first()->total, 0.001);
    }

    public function test_el_numero_de_solicitud_se_guarda_al_registrar(): void
    {
        $this->signIn('admin');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer, [
            'request_number' => 'SH173530',
        ]))->assertCreated();

        $this->assertSame('SH173530', Package::first()->request_number);
    }

    public function test_sin_numero_de_solicitud_el_paquete_se_registra_igual(): void
    {
        $this->signIn('admin');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer))->assertCreated();

        $this->assertNull(Package::first()->request_number);
    }

    public function test_hacerle_precio_despues_usa_la_misma_tarifa_por_libra(): void
    {
        $this->signIn('admin');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer))->assertCreated();
        $package = Package::first();

        $this->patchJson("/api/staff/packages/{$package->id}/price", [
            'price_per_pound' => 4,
        ])->assertOk();

        $package->refresh();

        // 10 lb a $4 en vez de $6.50.
        $this->assertEqualsWithDelta(40.00, (float) $package->total, 0.001);
        $this->assertEqualsWithDelta(65.00, (float) $package->original_total, 0.001);
        $this->assertEqualsWithDelta(4.00, (float) $package->price_per_pound, 0.001);
    }

    public function test_al_hacer_precio_tampoco_se_puede_cobrar_mas_que_la_lista(): void
    {
        $this->signIn('admin');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer))->assertCreated();
        $package = Package::first();

        $this->patchJson("/api/staff/packages/{$package->id}/price", [
            'price_per_pound' => 9,
        ])->assertJsonValidationErrors('price_per_pound');
    }

    public function test_hacer_precio_respeta_el_minimo_de_una_libra(): void
    {
        $this->signIn('admin');
        $customer = $this->customer();

        $this->postJson(
            '/api/staff/packages',
            $this->payload($customer, ['weight_lb' => 0.4])
        )->assertCreated();

        $package = Package::first();

        $this->patchJson("/api/staff/packages/{$package->id}/price", [
            'price_per_pound' => 4,
        ])->assertOk();

        // Pesa menos de una libra: se cobra una a la tarifa pactada.
        $this->assertEqualsWithDelta(4.00, (float) $package->fresh()->total, 0.001);
    }

    public function test_un_empleado_no_puede_hacerle_precio_a_un_paquete(): void
    {
        $this->signIn('admin');
        $customer = $this->customer();
        $this->postJson('/api/staff/packages', $this->payload($customer))->assertCreated();
        $package = Package::first();

        $employee = User::factory()->create(['role' => 'empleado']);
        $this->actingAs($employee);
        $employee->withAccessToken($employee->createToken('test', ['staff'])->accessToken);

        $this->patchJson("/api/staff/packages/{$package->id}/price", [
            'price_per_pound' => 4,
        ])->assertForbidden();
    }
}
