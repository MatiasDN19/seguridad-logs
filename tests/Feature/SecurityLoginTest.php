<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Las pruebas obligatorias del enunciado, automatizadas.
 */
class SecurityLoginTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(string $email, string $password, string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/security-login', ['email' => $email, 'password' => $password]);
    }

    public function test_login_valido_responde_200_y_registra_success(): void
    {
        $user = User::factory()->create();

        $this->attempt($user->email, 'password')
            ->assertOk()
            ->assertJson(['message' => 'Autenticación correcta']);

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('login_attempts', ['status' => 'success', 'identifier' => $user->email]);
    }

    public function test_contrasena_incorrecta_responde_401_y_registra_failed(): void
    {
        $user = User::factory()->create();

        $this->attempt($user->email, 'incorrecta')->assertStatus(401);
        $this->attempt($user->email, 'incorrecta')->assertStatus(401);

        $this->assertDatabaseCount('login_attempts', 2);
        $this->assertDatabaseHas('login_attempts', ['status' => 'failed']);
        $this->assertGuest();
    }

    public function test_el_email_se_guarda_en_minusculas(): void
    {
        $user = User::factory()->create(['email' => 'mixto@example.com']);

        $this->attempt('MiXtO@Example.com', 'incorrecta');

        $this->assertDatabaseHas('login_attempts', ['identifier' => 'mixto@example.com']);
    }

    public function test_el_quinto_fallo_bloquea_la_ip(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 4; $i++) {
            $this->attempt($user->email, 'incorrecta')->assertStatus(401);
        }
        $this->assertFalse(Cache::has('security:block:'.hash('sha256', '10.0.0.1')));

        // El quinto fallo todavía responde 401, pero ya deja la IP bloqueada
        $this->attempt($user->email, 'incorrecta')->assertStatus(401);
        $this->assertTrue(Cache::has('security:block:'.hash('sha256', '10.0.0.1')));
    }

    public function test_con_la_ip_bloqueada_responde_429_aun_con_la_contrasena_correcta(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->attempt($user->email, 'incorrecta');
        }

        $this->attempt($user->email, 'password')
            ->assertStatus(429)
            ->assertJson(['message' => 'Acceso temporalmente restringido.']);

        $this->assertGuest();
        $this->assertDatabaseHas('login_attempts', ['status' => 'blocked']);
    }

    public function test_el_bloqueo_de_una_ip_no_afecta_a_otra(): void
    {
        $atacada = User::factory()->create();
        $otro = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->attempt($atacada->email, 'incorrecta', '10.0.0.1');
        }

        // Otra cuenta desde otra IP entra normalmente.
        // (La cuenta atacada sigue bloqueada desde cualquier IP: eso lo cubre la detección por cuenta.)
        $this->attempt($otro->email, 'password', '10.0.0.2')->assertOk();
    }

    public function test_despues_de_diez_minutos_vuelve_a_admitirse(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->attempt($user->email, 'incorrecta');
        }
        $this->attempt($user->email, 'password')->assertStatus(429);

        // Se adelanta el reloj 11 minutos: vence el bloqueo y los fallos salen de la ventana de 5 min
        $this->travel(11)->minutes();

        $this->attempt($user->email, 'password')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_valida_email_y_password(): void
    {
        $this->postJson('/security-login', ['email' => 'no-es-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    // ---------- Panel protegido ----------

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/security-dashboard')->assertRedirect('/security-login');
    }

    public function test_usuario_comun_recibe_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/security-dashboard')
            ->assertForbidden();
    }

    public function test_admin_ve_el_panel_con_las_ips_sospechosas(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        for ($i = 1; $i <= 3; $i++) {
            $this->attempt($user->email, 'incorrecta', '10.0.0.9');
        }

        $this->actingAs($admin)
            ->get('/security-dashboard')
            ->assertOk()
            ->assertSee('IPs sospechosas')
            ->assertSee('10.0.0.9')
            ->assertSee('Alerta');
    }
}
