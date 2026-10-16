<?php

namespace Tests\Feature;

use App\Models\Prealert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Contenido declarado, transportista y retención de una prealerta.
 *
 * Lo que se protege acá es la declaración de aduana: cantidad, descripción y
 * valor son los datos con los que se calculan impuestos, así que el servidor
 * no puede aceptarlos a medias.
 */
class PrealertDetailTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $customer = User::factory()->create(['role' => 'cliente']);

        $this->actingAs($customer);
        $customer->withAccessToken($customer->createToken('app', ['*'])->accessToken);

        Storage::fake('local');

        return $customer;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tracking_number' => 'tba123456789',
            'courier' => 'Amazon Logistics',
            'expected_arrival' => now()->addDays(5)->toDateString(),
            'items' => [
                ['quantity' => 2, 'description' => 'Camisas', 'amount' => 25.50],
                ['quantity' => 1, 'description' => 'Pantalón', 'amount' => 40.00],
            ],
            'invoice' => UploadedFile::fake()->create('factura.pdf', 40, 'application/pdf'),
        ], $overrides);
    }

    public function test_guarda_el_contenido_declarado_y_su_valor(): void
    {
        $this->signIn();

        $response = $this->post('/api/prealerts', $this->payload());

        $response->assertCreated();
        $response->assertJsonCount(2, 'data.items');

        // 2 × 25.50 + 1 × 40 = 91.00
        $this->assertEqualsWithDelta(91.0, $response->json('data.declared_value'), 0.001);
        $this->assertEqualsWithDelta(51.0, $response->json('data.items.0.subtotal'), 0.001);
        $response->assertJsonPath('data.courier', 'Amazon Logistics');
    }

    public function test_el_tracking_se_normaliza_a_mayusculas(): void
    {
        $this->signIn();

        $this->post('/api/prealerts', $this->payload(['tracking_number' => '  tba999  ']))
            ->assertCreated()
            ->assertJsonPath('data.tracking_number', 'TBA999');
    }

    public function test_no_se_acepta_una_prealerta_sin_articulos(): void
    {
        $this->signIn();

        $this->post('/api/prealerts', $this->payload(['items' => []]))
            ->assertJsonValidationErrors('items');
    }

    public function test_un_articulo_sin_valor_se_rechaza(): void
    {
        $this->signIn();

        $this->post('/api/prealerts', $this->payload([
            'items' => [['quantity' => 1, 'description' => 'Algo', 'amount' => 0]],
        ]))->assertJsonValidationErrors('items.0.amount');
    }

    public function test_el_transportista_es_obligatorio(): void
    {
        $this->signIn();

        $payload = $this->payload();
        unset($payload['courier']);

        $this->post('/api/prealerts', $payload)->assertJsonValidationErrors('courier');
    }

    public function test_no_se_puede_esperar_un_paquete_en_una_fecha_pasada(): void
    {
        $this->signIn();

        $this->post('/api/prealerts', $this->payload([
            'expected_arrival' => now()->subDay()->toDateString(),
        ]))->assertJsonValidationErrors('expected_arrival');
    }

    public function test_retener_exige_decir_por_que(): void
    {
        $this->signIn();

        $this->post('/api/prealerts', $this->payload(['is_retained' => true]))
            ->assertJsonValidationErrors('retention_note');
    }

    public function test_una_prealerta_retenida_guarda_su_motivo(): void
    {
        $this->signIn();

        $this->post('/api/prealerts', $this->payload([
            'is_retained' => true,
            'retention_note' => 'Espero otra compra para juntarlas',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.is_retained', true)
            ->assertJsonPath('data.retention_note', 'Espero otra compra para juntarlas');
    }

    public function test_quitar_la_retencion_borra_el_motivo(): void
    {
        $this->signIn();

        $this->post('/api/prealerts', $this->payload([
            'is_retained' => true,
            'retention_note' => 'Por ahora no',
        ]))->assertCreated();

        $prealert = Prealert::first();

        $this->post("/api/prealerts/{$prealert->id}", $this->payload([
            '_method' => 'PUT',
            'is_retained' => false,
            'invoice' => null,
        ]))->assertOk()->assertJsonPath('data.retention_note', null);

        $this->assertFalse($prealert->fresh()->is_retained);
    }

    public function test_editar_conserva_una_fecha_que_ya_paso(): void
    {
        $customer = $this->signIn();

        // Una prealerta vieja, con la fecha ya vencida.
        $prealert = Prealert::create([
            'user_id' => $customer->id,
            'tracking_number' => 'TBA777',
            'expected_arrival' => now()->subDays(10)->toDateString(),
            'origin' => 'Miami',
        ]);

        $this->post("/api/prealerts/{$prealert->id}", $this->payload([
            '_method' => 'PUT',
            'tracking_number' => 'TBA777',
            'expected_arrival' => now()->subDays(10)->toDateString(),
            'invoice' => null,
        ]))->assertOk();
    }

    public function test_al_editar_los_articulos_reemplazan_a_los_anteriores(): void
    {
        $this->signIn();

        $this->post('/api/prealerts', $this->payload())->assertCreated();
        $prealert = Prealert::first();

        $this->post("/api/prealerts/{$prealert->id}", $this->payload([
            '_method' => 'PUT',
            'items' => [['quantity' => 3, 'description' => 'Zapatos', 'amount' => 30]],
            'invoice' => null,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items');

        $this->assertEqualsWithDelta(
            90.0,
            $this->getJson('/api/prealerts')->json('data.0.declared_value'),
            0.001,
        );

        $this->assertSame(1, $prealert->fresh()->items()->count());
    }
}
