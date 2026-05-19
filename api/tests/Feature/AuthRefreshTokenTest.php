<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AuthRefreshTokenTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::factory()->create();

        $this->manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_refresh_token_returns_new_token(): void
    {
        $token = $this->manager->createToken('api', ['*']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/auth/refresh-token');

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'token_expires_at',
            ])
            ->assertJson(['token_type' => 'Bearer']);
    }

    public function test_refresh_token_returns_different_token(): void
    {
        $token = $this->manager->createToken('api', ['*']);
        $plainText = $token->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$plainText)
            ->postJson('/api/v1/auth/refresh-token')
            ->assertOk();

        $newToken = $response->json('token');
        $this->assertNotSame($plainText, $newToken);
        $this->assertNotEmpty($newToken);
    }

    public function test_refresh_token_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/refresh-token')
            ->assertUnauthorized();
    }

    public function test_refresh_token_preserves_abilities(): void
    {
        $token = $this->manager->createToken('api', ['read', 'write']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/auth/refresh-token');

        $response->assertOk();
        $this->assertNotEmpty($response->json('token'));
    }
}
