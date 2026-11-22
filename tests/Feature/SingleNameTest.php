<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un nombre por campo.
 *
 * La gente escribía el nombre completo en la casilla del nombre y el
 * casillero quedaba a nombre de nadie.
 */
class SingleNameTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Rodríguez',
            'second_last_name' => 'Soto',
            'identification_type' => 'nacional',
            'identification' => '1-2345-6789',
            'phone' => '8888-8888',
            'email' => 'juan@ejemplo.test',
            'password' => 'Contrasena123',
            'password_confirmation' => 'Contrasena123',
        ], $overrides);
    }

    public function test_el_nombre_completo_en_la_casilla_del_nombre_se_rechaza(): void
    {
        $this->postJson('/api/register', $this->payload([
            'first_name' => 'Juan Carlos Rodríguez Soto',
        ]))->assertJsonValidationErrors('first_name');
    }

    public function test_tampoco_pasa_en_los_apellidos(): void
    {
        $this->postJson('/api/register', $this->payload([
            'last_name' => 'Rodríguez Soto Jiménez',
        ]))->assertJsonValidationErrors('last_name');
    }

    /** Dos palabras tampoco: una casilla, un nombre. */
    public function test_un_nombre_compuesto_tambien_se_rechaza(): void
    {
        $this->postJson('/api/register', $this->payload(['first_name' => 'María José']))
            ->assertJsonValidationErrors('first_name');
    }

    public function test_una_sola_palabra_pasa(): void
    {
        // El registro pide además la dirección, que no viene al caso acá: lo
        // que se comprueba es que el nombre no sea lo que lo frena.
        $this->postJson('/api/register', $this->payload(['first_name' => 'Alonso']))
            ->assertJsonMissingValidationErrors('first_name');
    }
}
