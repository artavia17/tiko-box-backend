<?php

namespace Tests\Feature;

use App\Models\Consolidation;
use App\Models\Package;
use App\Models\Prealert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consolidaciones: agrupar de 2 a 10 paquetes y cerrarlos como uno.
 *
 * Las reglas que se cuidan acá son las que cuestan plata si fallan: el
 * tamaño del grupo, que nadie toque lo ajeno, y que una consolidación que ya
 * se cerró no admita cambios.
 */
class ConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(?User $customer = null): User
    {
        $customer ??= User::factory()->create(['role' => 'cliente']);

        $this->actingAs($customer);
        $customer->withAccessToken($customer->createToken('app', ['*'])->accessToken);

        return $customer;
    }

    private function prealert(User $customer, array $overrides = []): Prealert
    {
        return Prealert::create(array_merge([
            'user_id' => $customer->id,
            'tracking_number' => 'TBA'.fake()->unique()->numerify('########'),
            'courier' => 'Amazon Logistics',
            'origin' => 'Miami',
            'status' => 'pendiente',
        ], $overrides));
    }

    public function test_se_consolidan_dos_prealertas(): void
    {
        $customer = $this->signIn();
        $uno = $this->prealert($customer);
        $dos = $this->prealert($customer);

        $response = $this->postJson('/api/consolidations', [
            'prealert_ids' => [$uno->id, $dos->id],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.packages_count', 2);
        // Nadie ha llegado todavía, así que la ventana no corre.
        $response->assertJsonPath('data.status', Consolidation::INACTIVO);

        $this->assertNotNull($uno->fresh()->consolidation_id);
    }

    public function test_una_sola_prealerta_no_es_una_consolidacion(): void
    {
        $customer = $this->signIn();

        $this->postJson('/api/consolidations', [
            'prealert_ids' => [$this->prealert($customer)->id],
        ])->assertJsonValidationErrors('prealert_ids');
    }

    public function test_no_se_pueden_consolidar_mas_de_diez(): void
    {
        $customer = $this->signIn();

        $ids = collect(range(1, 11))
            ->map(fn () => $this->prealert($customer)->id)
            ->all();

        $this->postJson('/api/consolidations', ['prealert_ids' => $ids])
            ->assertJsonValidationErrors('prealert_ids');
    }

    public function test_no_se_consolidan_prealertas_de_otro_cliente(): void
    {
        $customer = $this->signIn();
        $ajena = $this->prealert(User::factory()->create(['role' => 'cliente']));

        $this->postJson('/api/consolidations', [
            'prealert_ids' => [$this->prealert($customer)->id, $ajena->id],
        ])->assertJsonValidationErrors('prealert_ids');

        $this->assertNull($ajena->fresh()->consolidation_id);
    }

    public function test_un_paquete_retenido_no_se_consolida(): void
    {
        $customer = $this->signIn();

        $this->postJson('/api/consolidations', [
            'prealert_ids' => [
                $this->prealert($customer)->id,
                $this->prealert($customer, [
                    'is_retained' => true,
                    'retention_note' => 'Esperando otra compra',
                ])->id,
            ],
        ])->assertJsonValidationErrors('prealert_ids');
    }

    public function test_una_prealerta_no_puede_estar_en_dos_consolidaciones(): void
    {
        $customer = $this->signIn();
        $compartida = $this->prealert($customer);

        $this->postJson('/api/consolidations', [
            'prealert_ids' => [$compartida->id, $this->prealert($customer)->id],
        ])->assertCreated();

        $this->postJson('/api/consolidations', [
            'prealert_ids' => [$compartida->id, $this->prealert($customer)->id],
        ])->assertJsonValidationErrors('prealert_ids');
    }

    public function test_la_ventana_arranca_cuando_llega_el_primer_paquete(): void
    {
        $customer = $this->signIn();
        $uno = $this->prealert($customer);
        $this->prealert($customer);

        $consolidation = Consolidation::create(['user_id' => $customer->id]);
        Prealert::where('user_id', $customer->id)
            ->update(['consolidation_id' => $consolidation->id]);

        $this->assertSame(Consolidation::INACTIVO, $consolidation->load('prealerts')->status());

        // Llega el primero.
        $uno->update(['status' => 'recibido']);
        $consolidation->activateOnFirstArrival();

        $consolidation->refresh()->load('prealerts');

        $this->assertSame(Consolidation::ACTIVO, $consolidation->status());
        $this->assertEqualsWithDelta(
            config('tikabox.consolidation.window_days'),
            $consolidation->activated_at->diffInDays($consolidation->expires_at),
            0.01,
        );
    }

    public function test_quitar_una_prealerta_no_puede_dejar_menos_de_dos(): void
    {
        $customer = $this->signIn();
        $uno = $this->prealert($customer);
        $dos = $this->prealert($customer);

        $id = $this->postJson('/api/consolidations', [
            'prealert_ids' => [$uno->id, $dos->id],
        ])->json('data.id');

        $this->deleteJson("/api/consolidations/{$id}/prealerts/{$uno->id}")
            ->assertJsonValidationErrors('prealert');

        $this->assertNotNull($uno->fresh()->consolidation_id);
    }

    public function test_solicitar_el_envio_libera_lo_que_no_llego(): void
    {
        $customer = $this->signIn();
        $llego = $this->prealert($customer);
        $noLlego = $this->prealert($customer);

        $id = $this->postJson('/api/consolidations', [
            'prealert_ids' => [$llego->id, $noLlego->id],
        ])->json('data.id');

        $llego->update(['status' => 'recibido']);
        Consolidation::find($id)->activateOnFirstArrival();

        $this->postJson("/api/consolidations/{$id}/request")
            ->assertOk()
            ->assertJsonPath('data.status', Consolidation::SOLICITADO);

        // El que no llegó sale del grupo y sigue por su cuenta.
        $this->assertNull($noLlego->fresh()->consolidation_id);
        $this->assertNotNull($llego->fresh()->consolidation_id);
    }

    public function test_no_se_solicita_una_consolidacion_sin_nada_adentro(): void
    {
        $customer = $this->signIn();

        $id = $this->postJson('/api/consolidations', [
            'prealert_ids' => [
                $this->prealert($customer)->id,
                $this->prealert($customer)->id,
            ],
        ])->json('data.id');

        // Inactiva: no ha llegado nada.
        $this->postJson("/api/consolidations/{$id}/request")
            ->assertJsonValidationErrors('consolidation');
    }

    public function test_solo_se_elimina_mientras_no_haya_llegado_nada(): void
    {
        $customer = $this->signIn();
        $uno = $this->prealert($customer);
        $dos = $this->prealert($customer);

        $id = $this->postJson('/api/consolidations', [
            'prealert_ids' => [$uno->id, $dos->id],
        ])->json('data.id');

        $this->deleteJson("/api/consolidations/{$id}")->assertOk();
        $this->assertNull($uno->fresh()->consolidation_id);

        // Con un paquete ya recibido, no.
        $otra = $this->postJson('/api/consolidations', [
            'prealert_ids' => [$uno->fresh()->id, $dos->fresh()->id],
        ])->json('data.id');

        $uno->update(['status' => 'recibido']);
        Consolidation::find($otra)->activateOnFirstArrival();

        $this->deleteJson("/api/consolidations/{$otra}")
            ->assertJsonValidationErrors('consolidation');
    }

    public function test_nadie_ve_la_consolidacion_de_otro(): void
    {
        $otro = User::factory()->create(['role' => 'cliente']);
        $ajena = Consolidation::create(['user_id' => $otro->id]);

        $this->signIn();

        $this->getJson("/api/consolidations/{$ajena->id}")->assertNotFound();
        $this->deleteJson("/api/consolidations/{$ajena->id}")->assertNotFound();
    }

    public function test_el_almacen_cierra_la_consolidacion_en_un_solo_paquete(): void
    {
        $customer = User::factory()->create(['role' => 'cliente']);
        $this->signIn($customer);

        $uno = $this->prealert($customer);
        $dos = $this->prealert($customer);

        $id = $this->postJson('/api/consolidations', [
            'prealert_ids' => [$uno->id, $dos->id],
        ])->json('data.id');

        $uno->update(['status' => 'recibido']);
        $dos->update(['status' => 'recibido']);
        Consolidation::find($id)->activateOnFirstArrival();

        // Ahora el almacén.
        $staff = User::factory()->create(['role' => 'empleado']);
        $this->actingAs($staff);
        $staff->withAccessToken($staff->createToken('test', ['staff'])->accessToken);

        $response = $this->postJson("/api/staff/consolidations/{$id}/process", [
            'tracking_number' => 'CONS-001',
            'weight_lb' => 3,
        ]);

        $response->assertCreated();

        // Tres libras exactas a la tarifa: el sentido de consolidar es pagar
        // una vez por el bulto y no el mínimo por cada caja.
        $this->assertEqualsWithDelta(19.5, $response->json('data.total'), 0.001);

        $package = Package::find($response->json('data.package_id'));
        $this->assertTrue((bool) $package->exact_weight);
        $this->assertSame($customer->id, $package->user_id);

        $consolidation = Consolidation::find($id)->load('prealerts');
        $this->assertSame(Consolidation::PROCESADO, $consolidation->status());
        $this->assertSame($package->id, $consolidation->generated_package_id);
    }

    public function test_una_consolidacion_procesada_no_se_procesa_dos_veces(): void
    {
        $customer = User::factory()->create(['role' => 'cliente']);
        $this->signIn($customer);

        $uno = $this->prealert($customer);
        $dos = $this->prealert($customer);

        $id = $this->postJson('/api/consolidations', [
            'prealert_ids' => [$uno->id, $dos->id],
        ])->json('data.id');

        $uno->update(['status' => 'recibido']);
        $dos->update(['status' => 'recibido']);

        $staff = User::factory()->create(['role' => 'empleado']);
        $this->actingAs($staff);
        $staff->withAccessToken($staff->createToken('test', ['staff'])->accessToken);

        $payload = ['tracking_number' => 'CONS-002', 'weight_lb' => 2];

        $this->postJson("/api/staff/consolidations/{$id}/process", $payload)->assertCreated();
        $this->postJson("/api/staff/consolidations/{$id}/process", $payload)
            ->assertJsonValidationErrors('consolidation');
    }
}
