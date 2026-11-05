<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_redirects_guest_users_to_the_sign_in_page(): void
    {
        $this->get('/')->assertRedirect('/home');
        $this->get('/home')->assertRedirect('/login');
    }
}
