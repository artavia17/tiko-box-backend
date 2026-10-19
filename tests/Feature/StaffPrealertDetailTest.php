<?php

namespace Tests\Feature;

use App\Models\Prealert;
use App\Models\PrealertItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El almacén tiene que ver lo mismo que el cliente declaró.
 *
 * Si falta un dato, alguien lo termina preguntando por WhatsApp o aforando
 * el paquete a ojo, que es justo lo que la prealerta evita.
 */
class StaffPrealertDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_prealerta_llega_con_todo_lo_que_declaro_el_cliente(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $admin->withAccessToken($admin->createToken('test', ['staff'])->accessToken);

        $customer = User::factory()->create(['role' => 'cliente']);

        $prealert = Prealert::create([
            'user_id' => $customer->id,
            'tracking_number' => 'TBA123',
            'origin' => 'Miami',
            'courier' => 'Amazon Logistics',
            'is_retained' => true,
            'retention_note' => 'Falta la factura original',
        ]);

        PrealertItem::create([
            'prealert_id' => $prealert->id,
            'quantity' => 2,
            'description' => 'Audífonos',
            'amount' => 30,
        ]);

        $fila = collect($this->getJson('/api/staff/prealerts')->json('data'))
            ->firstWhere('id', $prealert->id);

        $this->assertSame('Amazon Logistics', $fila['courier']);
        $this->assertTrue($fila['is_retained']);
        $this->assertSame('Falta la factura original', $fila['retention_note']);

        $this->assertCount(1, $fila['items']);
        $this->assertSame('Audífonos', $fila['items'][0]['description']);
        $this->assertSame(2, $fila['items'][0]['quantity']);

        // 2 × $30: el valor declarado es lo que aduana cobra.
        $this->assertEqualsWithDelta(60, $fila['declared_value'], 0.001);
    }
}
