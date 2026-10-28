<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_home_sends_signed_in_users_to_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->followingRedirects()->get('/')->assertOk()->assertSee('Welcome back');
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Intruder', 'email' => 'x@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertStatus(404);

        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }
}
