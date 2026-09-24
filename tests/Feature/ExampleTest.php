<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guest_visiting_root_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_visiting_root_is_redirected_to_dashboard(): void
    {
        $this->actingAsAdmin();

        $response = $this->get('/');

        $response->assertRedirect('/menu-utama');
    }
}
