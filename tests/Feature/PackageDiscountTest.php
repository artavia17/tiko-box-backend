<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Descuentos aplicados al registrar el paquete.
 *
 * Se guardan en los mismos campos que el precio especial que se hace
 * después, así el descuento se ve solo en la lista del cliente, en la del
 * almacén y en la factura, sin tocar ninguna de esas pantallas.
 */
class PackageDiscountTest extends TestCase
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

    /** 5 lb × $6.5 = $32.50 de tarifa. */
    private function payload(User $customer, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $customer->id,
            'tracking_number' => 'TBA123456789',
            'weight_lb' => 5,
        ], $overrides);
    }

    public function test_un_descuento_por_monto_baja_el_total_y_guarda_la_tarifa(): void
    {
        $this->signIn('admin');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer, [
            'discount_type' => 'monto',
            'discount_value' => 10,
            'discount_note' => 'Cliente frecuente',
        ]))->assertCreated();

        $package = Package::first();

        $this->assertEqualsWithDelta(22.50, (float) $package->total, 0.001);
        $this->assertEqualsWithDelta(32.50, (float) $package->original_total, 0.001);
        $this->assertSame('Cliente frecuente', $package->price_note);
        $this->assertNotNull($package->price_adjusted_at);
    }

    public function test_un_descuento_por_porcentaje_se_calcula_sobre_la_tarifa(): void
    {
        $this->signIn('admin');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer, [
            'discount_type' => 'porcentaje',
            'discount_value' => 10,
            'discount_note' => 'Promoción',
        ]))->assertCreated();

        $package = Package::first();

        // 10% de $32.50 son $3.25.
        $this->assertEqualsWithDelta(29.25, (float) $package->total, 0.001);
        $this->assertEqualsWithDelta(32.50, (float) $package->original_total, 0.001);
    }

    public function test_sin_descuento_no_queda_rastro_de_ajuste(): void
    {
        $this->signIn('admin');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer))->assertCreated();

        $package = Package::first();

        $this->assertEqualsWithDelta(32.50, (float) $package->total, 0.001);
        $this->assertNull($package->original_total);
        $this->assertNull($package->price_note);
        $this->assertNull($package->price_adjusted_at);
    }

    public function test_un_empleado_no_puede_descontar(): void
    {
        $this->signIn('empleado');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer, [
            'discount_type' => 'monto',
            'discount_value' => 10,
            'discount_note' => 'Porque sí',
        ]))->assertForbidden();

        $this->assertSame(0, Package::count());
    }

    public function test_un_empleado_si_puede_registrar_sin_descuento(): void
    {
        $this->signIn('empleado');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer))->assertCreated();

        $this->assertEqualsWithDelta(32.50, (float) Package::first()->total, 0.001);
    }

    public function test_el_descuento_no_puede_pasarse_del_total(): void
    {
        $this->signIn('admin');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer, [
            'discount_type' => 'monto',
            'discount_value' => 50,
            'discount_note' => 'Regalado',
        ]))->assertJsonValidationErrors('discount_value');

        $this->postJson('/api/staff/packages', $this->payload($customer, [
            'discount_type' => 'porcentaje',
            'discount_value' => 120,
            'discount_note' => 'Regalado',
        ]))->assertJsonValidationErrors('discount_value');
    }

    public function test_el_descuento_exige_motivo(): void
    {
        $this->signIn('admin');
        $customer = $this->customer();

        $this->postJson('/api/staff/packages', $this->payload($customer, [
            'discount_type' => 'monto',
            'discount_value' => 10,
        ]))->assertJsonValidationErrors('discount_note');
    }
}
