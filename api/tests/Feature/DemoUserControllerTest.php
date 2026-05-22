<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoUserControllerTest extends TestCase
{
    public function test_demo_users_expose_operational_personas(): void
    {
        $response = $this->getJson('/api/v1/demo-users')
            ->assertOk()
            ->assertJsonPath('data.super_admin.role', 'super_admin')
            ->assertJsonPath('data.companies.0.slug', 'techcorp-algerie');

        $users = collect($response->json('data.companies.0.users'));

        $this->assertTrue($users->contains(fn (array $user): bool => $user['manager_role'] === 'principal'));
        $this->assertTrue($users->contains(fn (array $user): bool => $user['manager_role'] === 'rh'));
        $this->assertTrue($users->contains(fn (array $user): bool => $user['manager_role'] === 'dept'));
        $this->assertTrue($users->contains(fn (array $user): bool => $user['manager_role'] === 'comptable'));
        $this->assertTrue($users->contains(fn (array $user): bool => $user['manager_role'] === 'superviseur'));
        $this->assertTrue($users->contains(fn (array $user): bool => $user['role'] === 'employee'));

        $this->assertSame('kiosk-supervisor', $users->firstWhere('manager_role', 'superviseur')['surface']);
        $this->assertSame('/me', $users->firstWhere('role', 'employee')['primary_path']);
    }
}
