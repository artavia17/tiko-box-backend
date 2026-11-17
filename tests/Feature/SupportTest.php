<?php

namespace Tests\Feature;

use App\Mail\SupportClosedMail;
use App\Mail\SupportMessageMail;
use App\Mail\SupportReplyMail;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Soporte por tickets.
 *
 * Lo que se cuida acá: que nadie lea el ticket de otro, que los adjuntos no
 * sean cualquier cosa, y que el aviso por correo salga para el lado correcto.
 */
class SupportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
    }

    private function signIn(?User $user = null, array $abilities = ['*']): User
    {
        $user ??= User::factory()->create(['role' => 'cliente']);

        $this->actingAs($user);
        $user->withAccessToken($user->createToken('app', $abilities)->accessToken);

        return $user;
    }

    private function admin(): User
    {
        return $this->signIn(User::factory()->create(['role' => 'admin']), ['staff']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category' => 'paquete',
            'subject' => 'Mi paquete no aparece',
            'body' => 'Lo compré hace dos semanas y sigue sin figurar.',
        ], $overrides);
    }

    public function test_el_cliente_abre_un_ticket(): void
    {
        $this->signIn();

        $this->postJson('/api/support', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', SupportTicket::ABIERTO)
            ->assertJsonPath('data.category_label', 'Un paquete')
            ->assertJsonCount(1, 'data.messages');
    }

    public function test_la_categoria_tiene_que_ser_una_de_las_nuestras(): void
    {
        $this->signIn();

        $this->postJson('/api/support', $this->payload(['category' => 'migrations']))
            ->assertJsonValidationErrors('category');
    }

    public function test_abrir_un_ticket_le_avisa_a_administracion(): void
    {
        User::factory()->create(['role' => 'admin', 'email' => 'jefe@tikabox.test']);
        $this->signIn();

        $this->postJson('/api/support', $this->payload())->assertCreated();

        Mail::assertSent(SupportMessageMail::class);
        Mail::assertNotSent(SupportReplyMail::class);
    }

    public function test_nadie_lee_el_ticket_de_otro(): void
    {
        $this->signIn();
        $ajeno = $this->postJson('/api/support', $this->payload())->json('data.id');

        $this->signIn(User::factory()->create(['role' => 'cliente']));

        $this->getJson("/api/support/{$ajeno}")->assertNotFound();
        $this->postJson("/api/support/{$ajeno}/messages", ['body' => 'Hola'])->assertNotFound();
    }

    public function test_un_administrador_toma_el_ticket_y_responde(): void
    {
        $customer = $this->signIn();
        $id = $this->postJson('/api/support', $this->payload())->json('data.id');

        $this->admin();

        $this->postJson("/api/staff/support/{$id}/claim")
            ->assertOk()
            ->assertJsonPath('data.status', SupportTicket::EN_PROCESO);

        $this->postJson("/api/staff/support/{$id}/messages", ['body' => 'Lo estamos viendo.'])
            ->assertOk()
            ->assertJsonCount(2, 'data.messages');

        // El aviso va al cliente, no de vuelta a administración.
        Mail::assertSent(SupportReplyMail::class, fn ($mail) => $mail->hasTo($customer->email));
    }

    /** Responder sin haberlo tomado igual lo pone en proceso y lo asigna. */
    public function test_responder_asigna_el_ticket_solo(): void
    {
        $this->signIn();
        $id = $this->postJson('/api/support', $this->payload())->json('data.id');

        $admin = $this->admin();

        $this->postJson("/api/staff/support/{$id}/messages", ['body' => 'Ya lo vimos.'])->assertOk();

        $ticket = SupportTicket::find($id);
        $this->assertSame(SupportTicket::EN_PROCESO, $ticket->status);
        $this->assertSame($admin->id, $ticket->assigned_to);
    }

    public function test_un_empleado_no_entra_a_la_bandeja(): void
    {
        $this->signIn();
        $id = $this->postJson('/api/support', $this->payload())->json('data.id');

        $this->signIn(User::factory()->create(['role' => 'empleado']), ['staff']);

        $this->getJson('/api/staff/support')->assertForbidden();
        $this->postJson("/api/staff/support/{$id}/claim")->assertForbidden();
    }

    public function test_un_ticket_cerrado_no_admite_mensajes(): void
    {
        $this->signIn();
        $id = $this->postJson('/api/support', $this->payload())->json('data.id');

        $this->postJson("/api/support/{$id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', SupportTicket::CERRADO);

        $this->postJson("/api/support/{$id}/messages", ['body' => 'Una cosa más'])
            ->assertJsonValidationErrors('ticket');
    }

    /** Cerrarlo uno mismo no se avisa: el cliente ya sabe que lo cerró. */
    public function test_cerrar_desde_administracion_le_avisa_al_cliente(): void
    {
        $customer = $this->signIn();
        $id = $this->postJson('/api/support', $this->payload())->json('data.id');

        $this->postJson("/api/support/{$id}/close");
        Mail::assertNotSent(SupportClosedMail::class);

        $otro = $this->signIn($customer);
        $segundo = $this->postJson('/api/support', $this->payload())->json('data.id');

        $this->admin();
        $this->postJson("/api/staff/support/{$segundo}/close")->assertOk();

        Mail::assertSent(SupportClosedMail::class, fn ($mail) => $mail->hasTo($customer->email));
    }

    public function test_se_adjunta_una_imagen(): void
    {
        $this->signIn();

        $this->post('/api/support', $this->payload([
            'files' => [UploadedFile::fake()->image('caja.jpg')],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.messages.0.attachments.0.is_image', true);
    }

    /** Un ejecutable renombrado a .png se delata por su contenido. */
    public function test_no_se_adjunta_un_ejecutable(): void
    {
        $this->signIn();

        $this->post('/api/support', $this->payload([
            'files' => [UploadedFile::fake()->create('virus.exe', 20, 'application/x-msdownload')],
        ]))->assertJsonValidationErrors('files.0');
    }

    /**
     * Una nota de voz del navegador.
     *
     * Un WebM de solo audio se detecta como video/webm, no como audio/webm:
     * dejarlo fuera de la lista rechazaba toda nota de voz.
     */
    public function test_se_adjunta_una_nota_de_voz(): void
    {
        $this->signIn();

        $this->post('/api/support', $this->payload([
            'files' => [UploadedFile::fake()->create('nota-de-voz.webm', 40, 'video/webm')],
        ]))->assertCreated();
    }

    public function test_lo_no_leido_se_limpia_al_abrir_el_ticket(): void
    {
        $customer = $this->signIn();
        $id = $this->postJson('/api/support', $this->payload())->json('data.id');

        $this->admin();
        $this->postJson("/api/staff/support/{$id}/messages", ['body' => 'Respuesta']);

        $this->signIn($customer);

        $this->getJson('/api/support/unread')->assertJsonPath('data.unread', 1);

        $this->getJson("/api/support/{$id}")->assertOk();

        $this->getJson('/api/support/unread')->assertJsonPath('data.unread', 0);
    }
}
