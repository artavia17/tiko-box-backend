<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ajustes editables desde administración. */
class SettingTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user);
        $user->withAccessToken($user->createToken('test', ['staff'])->accessToken);

        return $user;
    }

    /** Sin nada guardado, lo que contesta es el valor del código. */
    public function test_sin_guardar_nada_devuelve_los_valores_por_defecto(): void
    {
        $this->signIn('empleado');

        $this->getJson('/api/staff/settings')
            ->assertOk()
            ->assertJsonPath(
                'data.whatsapp_location_message',
                Setting::DEFAULTS['whatsapp_location_message'],
            );
    }

    public function test_el_administrador_cambia_el_mensaje(): void
    {
        $this->signIn('admin');

        $this->putJson('/api/staff/settings', [
            'whatsapp_location_message' => 'Hola [nombre], mandanos tu ubicación.',
        ])
            ->assertOk()
            ->assertJsonPath('data.whatsapp_location_message', 'Hola [nombre], mandanos tu ubicación.');

        $this->signIn('empleado');

        $this->getJson('/api/staff/settings')
            ->assertJsonPath('data.whatsapp_location_message', 'Hola [nombre], mandanos tu ubicación.');
    }

    public function test_un_empleado_no_cambia_los_ajustes(): void
    {
        $this->signIn('empleado');

        $this->putJson('/api/staff/settings', [
            'whatsapp_location_message' => 'Lo que sea',
        ])->assertForbidden();
    }

    public function test_el_mensaje_no_puede_quedar_vacio(): void
    {
        $this->signIn('admin');

        $this->putJson('/api/staff/settings', ['whatsapp_location_message' => ''])
            ->assertJsonValidationErrors('whatsapp_location_message');
    }
}
