<?php

namespace Tests\Feature;

use Laravel\Socialite\Facades\Socialite;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class AuthGoogleSignInTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_google_callback_creates_new_ordinary_user()
    {
        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn('google@example.com');
        $abstractUser->shouldReceive('getName')->andReturn('Google User');
        $abstractUser->shouldReceive('offsetGet')->with('given_name')->andReturn('Google');
        $abstractUser->shouldReceive('offsetGet')->with('family_name')->andReturn('User');

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider = \Mockery::mock('Laravel\Socialite\Two\GoogleProvider'));
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        $response = $this->getJson('/api/v1/auth/google/callback');

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'email'], 'token']);

        $this->assertDatabaseHas('employees', [
            'email' => 'google@example.com',
            'role' => 'ordinary',
        ]);
    }
}
