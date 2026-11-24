<?php

namespace Tests\Feature;

use App\Models\ManualInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Facturas emitidas a mano.
 *
 * Lo que se cuida acá es que una factura vieja no se pueda tocar: ya se le
 * entregó a alguien, y a veces a Amazon.
 */
class ManualInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = 'admin'): User
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user);
        $user->withAccessToken($user->createToken('test', ['staff'])->accessToken);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'María Rodríguez Soto',
            'locker_code' => 'SJO0024955',
            'tracking_number' => 'TBA336225304853',
            'order_number' => '112-5646920-0090608',
            'store' => 'Amazon',
            'issued_on' => '2026-11-23',
            'weight_lb' => 2,
            'total' => 13,
        ], $overrides);
    }

    public function test_el_administrador_emite_una_factura(): void
    {
        $admin = $this->signIn();

        $this->postJson('/api/staff/manual-invoices', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.customer_name', 'María Rodríguez Soto')
            ->assertJsonPath('data.issued_by', $admin->fullName())
            ->assertJsonPath('data.editable', true);
    }

    public function test_un_empleado_no_emite_facturas(): void
    {
        $this->signIn('empleado');

        $this->postJson('/api/staff/manual-invoices', $this->payload())->assertForbidden();
    }

    public function test_quedan_listadas_para_volver_a_bajarlas(): void
    {
        $this->signIn();

        $this->postJson('/api/staff/manual-invoices', $this->payload());
        $this->postJson('/api/staff/manual-invoices', $this->payload([
            'tracking_number' => 'TBA999',
        ]));

        $this->getJson('/api/staff/manual-invoices')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_se_busca_por_rastreo(): void
    {
        $this->signIn();

        $this->postJson('/api/staff/manual-invoices', $this->payload());
        $this->postJson('/api/staff/manual-invoices', $this->payload([
            'tracking_number' => 'TBA999',
        ]));

        $this->getJson('/api/staff/manual-invoices?search=TBA999')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tracking_number', 'TBA999');
    }

    public function test_el_mismo_dia_se_corrige_y_se_borra(): void
    {
        $this->signIn();

        $id = $this->postJson('/api/staff/manual-invoices', $this->payload())->json('data.id');

        $this->putJson("/api/staff/manual-invoices/{$id}", $this->payload(['weight_lb' => 3]))
            ->assertOk()
            ->assertJsonPath('data.weight_lb', 3);

        $this->deleteJson("/api/staff/manual-invoices/{$id}")->assertOk();
        $this->assertNull(ManualInvoice::find($id));
    }

    /** Pasado el día, el documento ya está en manos de alguien más. */
    public function test_una_factura_de_ayer_no_se_toca(): void
    {
        $this->signIn();

        $id = $this->postJson('/api/staff/manual-invoices', $this->payload())->json('data.id');

        ManualInvoice::find($id)->forceFill([
            'created_at' => now()->subHours(ManualInvoice::EDITABLE_HOURS + 1),
        ])->save();

        $this->putJson("/api/staff/manual-invoices/{$id}", $this->payload(['weight_lb' => 9]))
            ->assertJsonValidationErrors('invoice');

        $this->deleteJson("/api/staff/manual-invoices/{$id}")
            ->assertJsonValidationErrors('invoice');

        $this->assertNotNull(ManualInvoice::find($id));
        $this->getJson('/api/staff/manual-invoices')->assertJsonPath('data.0.editable', false);
    }
}
