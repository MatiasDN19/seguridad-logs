<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz manda a los invitados al formulario de login.
     */
    public function test_la_raiz_redirige_al_login(): void
    {
        $this->get('/')->assertRedirect('/security-login');
    }

    public function test_el_formulario_de_login_carga(): void
    {
        $this->get('/security-login')
            ->assertOk()
            ->assertSee('Iniciar sesión');
    }
}
