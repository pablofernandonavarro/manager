<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz no tiene pantalla propia: manda al dashboard, y sin sesión el dashboard
     * manda al login. (El test de ejemplo de Laravel esperaba un 200 que esta app nunca dio.)
     */
    public function test_la_raiz_redirige_al_dashboard_y_sin_sesion_al_login(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
