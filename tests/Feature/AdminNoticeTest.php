<?php

namespace Tests\Feature;

use App\Mail\NewCustomerMail;
use App\Mail\NewPrealertMail;
use App\Models\Prealert;
use App\Models\User;
use App\Services\AdminNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Avisos internos a administración.
 *
 * Lo importante no es solo que el correo salga, sino que no salga de más
 * (empleados y clientes no tienen por qué recibirlo) ni tumbe la operación
 * si el servidor de correo está caído.
 */
class AdminNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $overrides = []): array
    {
        $province = \DB::table('provinces')->insertGetId(['code' => 1, 'name' => 'San José']);
        $canton = \DB::table('cantons')->insertGetId(['province_id' => $province, 'code' => 1, 'name' => 'Central']);
        $district = \DB::table('districts')->insertGetId(['canton_id' => $canton, 'code' => 1, 'name' => 'Carmen']);

        return array_merge([
            'first_name' => 'Diego',
            'last_name' => 'Rodríguez',
            'identification_type' => 'nacional',
            'identification' => '1-2345-6789',
            'phone' => '7155-0572',
            'email' => 'diego@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'province_id' => $province,
            'canton_id' => $canton,
            'district_id' => $district,
            'exact_address' => '100 metros al norte',
            'latitude' => 9.93,
            'longitude' => -84.08,
        ], $overrides);
    }

    public function test_el_registro_avisa_a_cada_administrador(): void
    {
        Mail::fake();

        $uno = User::factory()->create(['role' => 'admin', 'email' => 'admin1@tikabox.cr']);
        $dos = User::factory()->create(['role' => 'admin', 'email' => 'admin2@tikabox.cr']);
        User::factory()->create(['role' => 'empleado', 'email' => 'bodega@tikabox.cr']);
        User::factory()->create(['role' => 'cliente', 'email' => 'otro@example.com']);

        $this->postJson('/api/register', $this->registration())->assertCreated();

        Mail::assertSent(NewCustomerMail::class, fn ($mail) => $mail->hasTo($uno->email));
        Mail::assertSent(NewCustomerMail::class, fn ($mail) => $mail->hasTo($dos->email));

        // Uno por administrador, y nadie más en la lista.
        Mail::assertSent(NewCustomerMail::class, 2);
    }

    public function test_la_prealerta_avisa_a_administracion(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@tikabox.cr']);
        $customer = User::factory()->create(['role' => 'cliente']);

        $this->actingAs($customer);
        $customer->withAccessToken($customer->createToken('test', ['*'])->accessToken);

        Storage::fake('local');

        $this->post('/api/prealerts', [
            'tracking_number' => 'TBA123456789',
            'origin' => 'Miami',
            'invoice' => UploadedFile::fake()->create('factura.pdf', 40, 'application/pdf'),
        ])->assertCreated();

        Mail::assertSent(
            NewPrealertMail::class,
            fn ($mail) => $mail->hasTo($admin->email)
                && $mail->prealert->tracking_number === 'TBA123456789'
        );
    }

    public function test_sin_administradores_el_registro_sigue_funcionando(): void
    {
        Mail::fake();

        $this->postJson('/api/register', $this->registration())->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'diego@example.com']);
        Mail::assertNotSent(NewCustomerMail::class);
    }

    public function test_si_el_correo_falla_el_aviso_se_traga_el_error(): void
    {
        User::factory()->create(['role' => 'admin', 'email' => 'admin@tikabox.cr']);
        $customer = User::factory()->create(['role' => 'cliente']);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));
        Log::shouldReceive('warning')->once();

        // No explota: un aviso interno no puede tumbar lo que el cliente hizo.
        app(AdminNotifier::class)->customerRegistered($customer);
    }

    /**
     * Con Mail::fake() la plantilla nunca se arma, así que una variable mal
     * puesta en el Blade no la vería nadie hasta producción.
     */
    public function test_las_plantillas_se_arman_con_los_datos_reales(): void
    {
        $customer = User::factory()->create([
            'role' => 'cliente',
            'first_name' => 'Diego',
            'last_name' => 'Rodríguez',
        ]);

        $html = (new NewCustomerMail($customer))->render();
        $this->assertStringContainsString('Diego Rodríguez', $html);
        $this->assertStringContainsString($customer->email, $html);

        $prealert = Prealert::create([
            'user_id' => $customer->id,
            'tracking_number' => 'TBA999',
            'origin' => 'Miami',
        ]);

        $html = (new NewPrealertMail($prealert->load('user')))->render();
        $this->assertStringContainsString('TBA999', $html);
        $this->assertStringContainsString('Diego Rodríguez', $html);
    }
}
