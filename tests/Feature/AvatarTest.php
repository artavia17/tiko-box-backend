<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Foto de perfil.
 *
 * Lo que se cuida acá es que sea privada: la cara de un cliente no puede
 * quedar en una URL que cualquiera adivine.
 */
class AvatarTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El disco falso se arma una vez por prueba y no en cada sesión: hacerlo
     * al entrar borraba la foto que la prueba acababa de subir.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function signIn(?User $user = null, array $abilities = ['*']): User
    {
        $user ??= User::factory()->create(['role' => 'cliente']);

        $this->actingAs($user);
        $user->withAccessToken($user->createToken('app', $abilities)->accessToken);

        return $user;
    }

    public function test_el_usuario_sube_su_foto(): void
    {
        $user = $this->signIn();

        $this->post('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->image('yo.jpg'),
        ])
            ->assertOk()
            ->assertJsonPath('data.has_avatar', true);

        Storage::disk('local')->assertExists($user->fresh()->avatar_path);
    }

    public function test_solo_se_aceptan_imagenes(): void
    {
        $this->signIn();

        $this->postJson('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
        ])->assertJsonValidationErrors('avatar');
    }

    /** Cambiar de foto no debería ir dejando archivos sueltos en el disco. */
    public function test_al_cambiarla_se_borra_la_anterior(): void
    {
        $user = $this->signIn();

        $this->post('/api/me/avatar', ['avatar' => UploadedFile::fake()->image('uno.jpg')]);
        $primera = $user->fresh()->avatar_path;

        $this->post('/api/me/avatar', ['avatar' => UploadedFile::fake()->image('dos.jpg')]);
        $segunda = $user->fresh()->avatar_path;

        $this->assertNotSame($primera, $segunda);
        Storage::disk('local')->assertMissing($primera);
        Storage::disk('local')->assertExists($segunda);
    }

    public function test_se_puede_quitar(): void
    {
        $user = $this->signIn();

        $this->post('/api/me/avatar', ['avatar' => UploadedFile::fake()->image('yo.jpg')]);
        $ruta = $user->fresh()->avatar_path;

        $this->deleteJson('/api/me/avatar')
            ->assertOk()
            ->assertJsonPath('data.has_avatar', false);

        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_un_cliente_no_ve_la_foto_de_otro(): void
    {
        $otro = $this->signIn();
        $this->post('/api/me/avatar', ['avatar' => UploadedFile::fake()->image('yo.jpg')]);

        // Entra alguien más y pide la foto ajena.
        $this->signIn(User::factory()->create(['role' => 'cliente']));

        $this->get("/api/users/{$otro->id}/avatar")->assertNotFound();
    }

    /** El almacén sí: es lo que le deja reconocer a quien llega a retirar. */
    public function test_el_almacen_ve_la_foto_de_un_cliente(): void
    {
        $cliente = $this->signIn();
        $this->post('/api/me/avatar', ['avatar' => UploadedFile::fake()->image('yo.jpg')]);

        $this->signIn(User::factory()->create(['role' => 'empleado']), ['staff']);

        $this->get("/api/users/{$cliente->id}/avatar")->assertOk();
    }

    public function test_sin_foto_no_hay_nada_que_servir(): void
    {
        $user = $this->signIn();

        $this->get("/api/users/{$user->id}/avatar")->assertNotFound();
    }
}
