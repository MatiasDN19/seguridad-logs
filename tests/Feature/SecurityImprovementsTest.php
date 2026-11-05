<?php

namespace Tests\Feature;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Actividad integradora: detección por cuenta y exportación de evidencias.
 */
class SecurityImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(string $email, string $password, string $ip)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/security-login', ['email' => $email, 'password' => $password]);
    }

    // ---------- Mejora 1: detección por cuenta ----------

    public function test_ataque_distribuido_bloquea_la_cuenta(): void
    {
        $user = User::factory()->create();

        // 5 IPs distintas, un fallo cada una: ninguna IP llega al umbral de 5
        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3', '10.0.0.4', '10.0.0.5'] as $ip) {
            $this->attempt($user->email, 'incorrecta', $ip)->assertStatus(401);
        }

        // Desde una IP nueva, aun con la contraseña correcta, la cuenta está bloqueada
        $this->attempt($user->email, 'password', '10.0.0.6')
            ->assertStatus(429)
            ->assertJson(['message' => 'Acceso temporalmente restringido.']);

        $this->assertGuest();
        $this->assertDatabaseHas('login_attempts', ['status' => 'blocked', 'ip_address' => '10.0.0.6', 'identifier' => $user->email]);
    }

    public function test_tres_fallos_contra_una_cuenta_no_bloquean(): void
    {
        $user = User::factory()->create();

        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
            $this->attempt($user->email, 'incorrecta', $ip);
        }

        $this->attempt($user->email, 'password', '10.0.0.4')->assertOk();
    }

    public function test_el_bloqueo_de_una_cuenta_no_afecta_a_otras(): void
    {
        $victima = User::factory()->create();
        $otro = User::factory()->create();

        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3', '10.0.0.4', '10.0.0.5'] as $ip) {
            $this->attempt($victima->email, 'incorrecta', $ip);
        }

        $this->attempt($otro->email, 'password', '10.0.0.9')->assertOk();
    }

    public function test_el_bloqueo_de_cuenta_vence_a_los_diez_minutos(): void
    {
        $user = User::factory()->create();

        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3', '10.0.0.4', '10.0.0.5'] as $ip) {
            $this->attempt($user->email, 'incorrecta', $ip);
        }
        $this->attempt($user->email, 'password', '10.0.0.6')->assertStatus(429);

        // Pasados 16 minutos vence el bloqueo y los fallos salen de la ventana de 15
        $this->travel(16)->minutes();

        $this->attempt($user->email, 'password', '10.0.0.6')->assertOk();
    }

    public function test_el_panel_muestra_la_cuenta_atacada_como_distribuida(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $victima = User::factory()->create(['email' => 'victima@example.com']);

        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
            $this->attempt($victima->email, 'incorrecta', $ip);
        }

        $this->actingAs($admin)
            ->get('/security-dashboard')
            ->assertOk()
            ->assertSee('Cuentas atacadas')
            ->assertSee('victima@example.com')
            ->assertSee('distribuido');
    }

    // ---------- Mejora 2: exportación de evidencias ----------

    public function test_invitado_no_puede_exportar(): void
    {
        $this->get('/security-dashboard/export')->assertRedirect('/security-login');
    }

    public function test_usuario_comun_no_puede_exportar(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/security-dashboard/export')
            ->assertForbidden();
    }

    public function test_admin_descarga_el_csv(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        LoginAttempt::create(['identifier' => 'a@example.com', 'ip_address' => '10.0.0.1', 'status' => 'failed', 'created_at' => now()]);
        LoginAttempt::create(['identifier' => 'b@example.com', 'ip_address' => '10.0.0.2', 'status' => 'blocked', 'created_at' => now()]);

        $response = $this->actingAs($admin)->get('/security-dashboard/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=evidencias-login-', $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('id,created_at_utc,status,ip_address,identifier,user_agent', $csv);
        $this->assertStringContainsString('failed,10.0.0.1,a@example.com', $csv);
        $this->assertStringContainsString('blocked,10.0.0.2,b@example.com', $csv);
    }
}
