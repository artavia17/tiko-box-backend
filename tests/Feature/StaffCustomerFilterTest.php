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

    public function test_el_almacen_filtra_por_paquetes_activos(): void
    {
        $this->actingAsAdmin();

        $this->customerWithPackage('Aduanera', 'aduanas');
        $this->customerWithPackage('Ruteada', 'en_ruta');
        $this->customerWithPackage('Entregada', 'entregado');

        $activos = $this->getJson('/api/staff/packages?status=activos')->json('data');
        $this->assertCount(2, $activos);

        // Un estado concreto sigue funcionando igual.
        $this->assertCount(
            1,
            $this->getJson('/api/staff/packages?status=aduanas')->json('data')
        );

        // Y sin filtro vienen todos.
        $this->assertCount(3, $this->getJson('/api/staff/packages')->json('data'));
    }

    public function test_se_busca_un_paquete_por_su_numero_de_solicitud(): void
    {
        $this->actingAsAdmin();

        $customer = User::factory()->create(['role' => 'cliente', 'name' => 'Ana Mora']);

        Package::create([
            'user_id' => $customer->id,
            'tracking_number' => 'TBA999',
            'request_number' => 'SH173530',
            'weight_lb' => 2,
            'price_per_pound' => 6.5,
            'total' => 13,
            'status' => 'recibido',
            'received_at' => now(),
        ]);

        $this->customerWithPackage('Otra', 'recibido');

        $paquetes = $this->getJson('/api/staff/packages?search=SH173530')->json('data');

        $this->assertCount(1, $paquetes);
        $this->assertSame('SH173530', $paquetes[0]['request_number']);
    }

    public function test_se_llega_al_cliente_por_el_numero_de_solicitud(): void
    {
        $this->actingAsAdmin();

        $duenio = User::factory()->create(['role' => 'cliente', 'name' => 'Ana Mora']);

        Package::create([
            'user_id' => $duenio->id,
            'tracking_number' => 'TBA999',
            'request_number' => 'SH173530',
            'weight_lb' => 2,
            'price_per_pound' => 6.5,
            'total' => 13,
            'status' => 'recibido',
            'received_at' => now(),
        ]);

        $this->customerWithPackage('Otra', 'recibido');

        $ids = collect($this->getJson('/api/staff/customers?search=SH173530')->json('data'))
            ->pluck('id');

        $this->assertSame([$duenio->id], $ids->all());
    }
}
