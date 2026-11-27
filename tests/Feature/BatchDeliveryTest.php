<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Entrega de varios paquetes a la vez.
 *
 * En el mostrador la gente retira todo junto, firma una vez y paga una vez.
 * Lo que se cuida acá es que esa firma no termine cubriendo paquetes de otro.
 */
class BatchDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function signIn(string $role = 'empleado'): User
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user);
        $user->withAccessToken($user->createToken('test', ['staff'])->accessToken);

        return $user;
    }

    private function package(User $customer, array $overrides = []): Package
    {
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

    private function payload(array $ids, array $overrides = []): array
    {
        return array_merge([
            'package_ids' => $ids,
            'delivered_to_name' => 'María Rodríguez',
            'delivered_to_identification' => '1-2345-6789',
            'signature' => UploadedFile::fake()->image('firma.png'),
            'collected_by' => 'Henry',
            'payment_method' => 'SINPE Móvil',
        ], $overrides);
    }

    public function test_se_entregan_varios_y_devuelve_lo_cobrado(): void
    {
        $this->signIn();
        $customer = User::factory()->create(['role' => 'cliente']);

        $uno = $this->package($customer);
        $dos = $this->package($customer, ['total' => 20]);

        $response = $this->post(
            '/api/staff/packages/deliver-batch',
            $this->payload([$uno->id, $dos->id]),
        );

        $response->assertOk()->assertJsonCount(2, 'data');
        $this->assertEqualsWithDelta(33.0, $response->json('meta.collected'), 0.001);

        $this->assertSame('entregado', $uno->fresh()->status);
        $this->assertSame('SINPE Móvil', $dos->fresh()->payment_method);
        $this->assertSame('Henry', $dos->fresh()->collected_by);
    }

    /** Una firma vale por lo que esa persona recibió, no por lo de otro. */
    public function test_no_se_mezclan_paquetes_de_clientes_distintos(): void
    {
        $this->signIn();

        $uno = $this->package(User::factory()->create(['role' => 'cliente']));
        $dos = $this->package(User::factory()->create(['role' => 'cliente']));

        $this->post('/api/staff/packages/deliver-batch', $this->payload([$uno->id, $dos->id]))
            ->assertJsonValidationErrors('package_ids');

        $this->assertSame('bodega', $uno->fresh()->status);
    }

    public function test_quien_cobro_y_como_se_pago_son_obligatorios(): void
    {
        $this->signIn();
        $customer = User::factory()->create(['role' => 'cliente']);
        $package = $this->package($customer);

        $this->post('/api/staff/packages/deliver-batch', $this->payload([$package->id], [
            'collected_by' => '',
            'payment_method' => '',
        ]))->assertJsonValidationErrors(['collected_by', 'payment_method']);
    }

    public function test_no_se_reentrega_lo_ya_entregado(): void
    {
        $this->signIn();
        $customer = User::factory()->create(['role' => 'cliente']);

        $entregado = $this->package($customer, ['status' => 'entregado']);
        $pendiente = $this->package($customer);

        $this->post('/api/staff/packages/deliver-batch',
            $this->payload([$entregado->id, $pendiente->id]))
            ->assertJsonValidationErrors('package_ids');

        // Ninguno se mueve: o entra la tanda entera o no entra ninguna.
        $this->assertSame('bodega', $pendiente->fresh()->status);
    }

    public function test_un_paquete_anulado_no_se_entrega(): void
    {
        $this->signIn();
        $customer = User::factory()->create(['role' => 'cliente']);

        $anulado = $this->package($customer, ['voided_at' => now()]);

        $this->post('/api/staff/packages/deliver-batch', $this->payload([$anulado->id]))
            ->assertJsonValidationErrors('package_ids');
    }
}
