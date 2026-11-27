<?php

namespace Tests\Feature;

use App\Models\CatalogOption;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las listas del almacén: transportistas, tiendas y formas de pago.
 *
 * Lo que se cuida acá es el historial: los registros guardan el nombre y no
 * el id, así que renombrar tiene que arrastrar lo ya guardado.
 */
class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = 'admin'): User
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user);
        $user->withAccessToken($user->createToken('test', ['staff'])->accessToken);

        return $user;
    }

    private function package(array $overrides = []): Package
    {
        $customer = User::factory()->create(['role' => 'cliente']);

        return Package::create(array_merge([
            'user_id' => $customer->id,
            'tracking_number' => 'TBA'.fake()->unique()->numerify('########'),
            'weight_lb' => 2,
            'price_per_pound' => 6.5,
            'total' => 13,
            'status' => 'bodega',
            'received_at' => now(),
        ], $overrides));
    }

    public function test_se_corrige_el_nombre_de_una_tienda(): void
    {
        $this->signIn();

        $option = CatalogOption::create(['type' => 'store', 'name' => 'Amazonn']);

        $this->putJson("/api/staff/catalog/{$option->id}", ['name' => 'Amazon'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Amazon');
    }

    /** Corregir la lista y dejar el historial con la falta no sirve. */
    public function test_renombrar_arrastra_lo_ya_guardado(): void
    {
        $this->signIn();

        $option = CatalogOption::create(['type' => 'store', 'name' => 'Amazonn']);
        $package = $this->package(['store' => 'Amazonn']);

        $this->putJson("/api/staff/catalog/{$option->id}", ['name' => 'Amazon'])->assertOk();

        $this->assertSame('Amazon', $package->fresh()->store);
    }

    public function test_renombrar_una_forma_de_pago_toca_los_paquetes_cobrados(): void
    {
        $this->signIn();

        $option = CatalogOption::where('type', 'payment_method')
            ->where('name', 'SINPE Móvil')
            ->firstOrFail();

        $package = $this->package(['payment_method' => 'SINPE Móvil']);

        $this->putJson("/api/staff/catalog/{$option->id}", ['name' => 'SINPE'])->assertOk();

        $this->assertSame('SINPE', $package->fresh()->payment_method);
    }

    public function test_no_se_repite_un_nombre_dentro_de_la_misma_lista(): void
    {
        $this->signIn();

        CatalogOption::create(['type' => 'store', 'name' => 'Amazon']);
        $otra = CatalogOption::create(['type' => 'store', 'name' => 'Walmart']);

        $this->putJson("/api/staff/catalog/{$otra->id}", ['name' => 'Amazon'])
            ->assertJsonValidationErrors('name');
    }

    public function test_un_empleado_no_renombra_las_listas(): void
    {
        $this->signIn('empleado');

        $option = CatalogOption::create(['type' => 'store', 'name' => 'Amazon']);

        $this->putJson("/api/staff/catalog/{$option->id}", ['name' => 'Otra'])
            ->assertForbidden();
    }
}
