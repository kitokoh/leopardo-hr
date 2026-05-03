<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class AuthGoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            Schema::create('employees', function ($table) {
                $table->increments('id');
                $table->uuid('company_id')->nullable();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->string('password_hash');
                $table->string('role')->default('employee');
                $table->string('status')->default('active');
                $table->timestamps();
            });

            Schema::create('personal_access_tokens', function ($table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

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
