<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alta y edición de cuentas desde administración.
 *
 * Lo que se protege acá es que un alta de mostrador quede utilizable en el
 * acto: si la cuenta nace sin confirmar, la persona no puede entrar y el
 * alta no sirvió de nada.
 */
class StaffUserManagementTest extends TestCase
{
    use RefreshDatabase;

    /** Token de personal: el middleware exige rol y habilidad del token. */
    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);
        $admin->withAccessToken(
            $admin->createToken('test', ['staff'])->accessToken
        );

        return $admin;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Diego',
            'last_name' => 'Rodríguez',
            'email' => 'diego@example.com',
            'phone' => '71550572',
            'identification' => '1-2345-6789',
            'password' => 'secret-password',
            'role' => 'cliente',
        ], $overrides);
    }

    public function test_una_cuenta_creada_por_administracion_queda_confirmada(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/staff/users', $this->payload());

        $response->assertCreated();
        $response->assertJsonPath('data.email_verified', true);

        $this->assertNotNull(
            User::where('email', 'diego@example.com')->first()->email_verified_at,
            'El alta de mostrador tiene que quedar confirmada para que la persona pueda entrar.'
        );
    }

    public function test_se_puede_crear_una_cuenta_pendiente_de_confirmar(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(
            '/api/staff/users',
            $this->payload(['email_verified' => false])
        );

        $response->assertCreated();
        $response->assertJsonPath('data.email_verified', false);
    }

    public function test_administracion_puede_confirmar_una_cuenta_trabada(): void
    {
        $this->actingAsAdmin();

        $stuck = User::factory()->unverified()->create([
            'role' => 'cliente',
            'first_name' => 'Ana',
            'last_name' => 'Mora',
        ]);

        $response = $this->putJson("/api/staff/users/{$stuck->id}", [
            'first_name' => 'Ana',
            'last_name' => 'Mora',
            'email' => $stuck->email,
            'role' => 'cliente',
            'email_verified' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.email_verified', true);
        $this->assertNotNull($stuck->fresh()->email_verified_at);
    }

    public function test_una_edicion_que_no_manda_el_campo_no_toca_la_confirmacion(): void
    {
        $this->actingAsAdmin();

        $verified = User::factory()->create([
            'role' => 'cliente',
            'first_name' => 'Ana',
            'last_name' => 'Mora',
        ]);

        $this->putJson("/api/staff/users/{$verified->id}", [
            'first_name' => 'Ana María',
            'last_name' => 'Mora',
            'email' => $verified->email,
            'role' => 'cliente',
        ])->assertOk();

        $this->assertNotNull($verified->fresh()->email_verified_at);
    }

    public function test_un_empleado_no_puede_administrar_cuentas(): void
    {
        $employee = User::factory()->create(['role' => 'empleado']);

        $this->actingAs($employee);
        $employee->withAccessToken(
            $employee->createToken('test', ['staff'])->accessToken
        );

        $this->postJson('/api/staff/users', $this->payload())->assertForbidden();
        $this->getJson('/api/staff/users')->assertForbidden();
    }
}
