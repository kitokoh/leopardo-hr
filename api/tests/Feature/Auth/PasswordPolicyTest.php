<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7995 — politique de mots de passe UNIQUE (norme #5620) :
 * `Password::min(12)->numbers()` + `NotCommonPassword` via
 * App\Shared\Rules\PasswordPolicy sur TOUTES les surfaces de création de
 * compte. Avant #7995, quatre surfaces acceptaient `min:8` sans robustesse :
 * `password123` passait à la création alors qu'il était refusé au changement.
 *
 * Ces tests verrouillent le rejet (422 / erreur de validation) d'un mot de
 * passe faible sur chacune des 4 surfaces corrigées.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshTenantDatabase;

    private const WEAK = 'weakpass'; // 8 caractères, sans chiffre — refusé partout

    public function test_employee_create_rejects_weak_password(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/employees', [
            'first_name' => 'Faible',
            'last_name' => 'Motdepasse',
            'email' => 'faible.mdp@example.test',
            'password' => self::WEAK,
            'role' => 'employee',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_employee_update_rejects_weak_password(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->putJson('/api/v1/employees/'.$employee->id, [
            'password' => self::WEAK,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_market_buyer_register_rejects_weak_password(): void
    {
        // Surface PUBLIQUE (aucun compte requis) — le même standard s'impose.
        $this->postJson('/api/v1/public/market/account/register', [
            'name' => 'Acheteur Test',
            'email' => 'acheteur.faible@example.test',
            'password' => self::WEAK,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_web_invitation_accept_rejects_weak_password(): void
    {
        // La validation précède toute résolution du jeton : un jeton bidon
        // suffit à éprouver la règle (le service n'est jamais atteint).
        $response = $this->from('/activate/jeton-bidon')->post('/activate/jeton-bidon', [
            'password' => self::WEAK,
            'password_confirmation' => self::WEAK,
        ]);

        $response->assertRedirect('/activate/jeton-bidon');
        $response->assertSessionHasErrors(['password']);
    }
}
