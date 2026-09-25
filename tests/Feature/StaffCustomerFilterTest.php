<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filtro del directorio de clientes por el estado de sus paquetes.
 *
 * Sirve para contestar "¿a quién tengo trabado en aduanas?" sin recorrer la
 * lista entera a mano.
 */
class StaffCustomerFilterTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);
        $admin->withAccessToken(
            $admin->createToken('test', ['staff'])->accessToken
        );

        return $admin;
    }

    private function customerWithPackage(string $name, string $status): User
    {
        $customer = User::factory()->create([
            'role' => 'cliente',
            'first_name' => $name,
            'last_name' => 'Prueba',
            'name' => "{$name} Prueba",
        ]);

        Package::create([
            'user_id' => $customer->id,
            'tracking_number' => "TBA{$customer->id}",
            'weight_lb' => 2,
            'price_per_pound' => 6.5,
            'total' => 13,
            'status' => $status,
            'received_at' => now(),
        ]);

        return $customer;
    }

    public function test_filtra_por_un_estado_concreto(): void
    {
        $admin = $this->actingAsAdmin();

        $enAduanas = $this->customerWithPackage('Aduanera', 'aduanas');
        $this->customerWithPackage('Transitoria', 'en_transito');

        $response = $this->getJson('/api/staff/customers?status=aduanas');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($enAduanas->id));
        $this->assertCount(1, $ids->reject(fn ($id) => $id === $admin->id));
    }

    public function test_activos_junta_todo_lo_que_no_se_entrego(): void
    {
        $admin = $this->actingAsAdmin();

        $enAduanas = $this->customerWithPackage('Aduanera', 'aduanas');
        $enRuta = $this->customerWithPackage('Ruteada', 'en_ruta');
        $entregado = $this->customerWithPackage('Entregada', 'entregado');

        $ids = collect($this->getJson('/api/staff/customers?status=activos')->json('data'))
            ->pluck('id');

        $this->assertTrue($ids->contains($enAduanas->id));
        $this->assertTrue($ids->contains($enRuta->id));
        $this->assertFalse(
            $ids->contains($entregado->id),
            'Quien solo tiene entregas no sigue en movimiento.'
        );
        $this->assertFalse($ids->contains($admin->id), 'El admin no tiene paquetes.');
    }

    public function test_entregado_no_es_un_filtro_y_no_recorta_la_lista(): void
    {
        $this->actingAsAdmin();

        $this->customerWithPackage('Aduanera', 'aduanas');
        $this->customerWithPackage('Entregada', 'entregado');

        $todos = $this->getJson('/api/staff/customers')->json('meta.total');
        $conEntregado = $this->getJson('/api/staff/customers?status=entregado')->json('meta.total');

        $this->assertSame($todos, $conEntregado);
    }

    public function test_un_estado_inventado_se_ignora(): void
    {
        $this->actingAsAdmin();
        $this->customerWithPackage('Aduanera', 'aduanas');

        $todos = $this->getJson('/api/staff/customers')->json('meta.total');
        $basura = $this->getJson('/api/staff/customers?status=cualquier-cosa')->json('meta.total');

        $this->assertSame($todos, $basura);
    }

    public function test_el_filtro_convive_con_la_busqueda(): void
    {
        $this->actingAsAdmin();

        $this->customerWithPackage('Aduanera', 'aduanas');
        $otra = $this->customerWithPackage('Otra', 'aduanas');

        $ids = collect(
            $this->getJson('/api/staff/customers?status=aduanas&search=Otra')->json('data')
        )->pluck('id');

        $this->assertSame([$otra->id], $ids->all());
    }
}
