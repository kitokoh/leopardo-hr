<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
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
 *
 * #8021 (suivi #8002) — étend la preuve aux surfaces les plus PRIVILÉGIÉES :
 * les comptes plateforme (super_admins) ne portaient qu'un `min:12` — un mot
 * de passe de 12 caractères sans chiffre était accepté à la création alors
 * qu'il était refusé au changement.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshTenantDatabase;

    private const WEAK = 'weakpass'; // 8 caractères, sans chiffre — refusé partout

    /** 12 caractères SANS chiffre : longueur suffisante, robustesse insuffisante. */
    private const TWELVE_CHARS_WITHOUT_DIGIT = 'nodigitshere';

    private const COMPLIANT = 'Platform-2026-Pass'; // 12+ caractères AVEC chiffre

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

    public function test_platform_team_creation_rejects_a_long_password_without_digit(): void
    {
        // Surface la plus privilégiée du SaaS (super_admins) : la création d'un
        // collaborateur interne passe par le contrôleur plateforme.
        $owner = $this->platformAccount('owner.policy@leopardo.test');
        Sanctum::actingAs($owner, ['*'], 'super_admin_api');

        // Locale verrouillée : on vérifie le message DE LA RÈGLE manquante
        // (chiffre), pas une traduction locale (le dépôt porte en/fr/ar/tr).
        app()->setLocale('en');

        $response = $this->postJson('/api/v1/platform/team', [
            'name' => 'Sans Chiffre',
            'email' => 'sans.chiffre@leopardo.test',
            'password' => self::TWELVE_CHARS_WITHOUT_DIGIT,
            'platform_role' => 'ops',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
        // Le message pointe la règle exacte (Password::numbers) et non la seule
        // longueur : c'est la preuve que le renforcement est bien appliqué.
        $this->assertStringContainsString(
            'at least one number',
            (string) $response->json('errors.password.0'),
        );
        $this->assertDatabaseMissing('super_admins', ['email' => 'sans.chiffre@leopardo.test']);
    }

    public function test_platform_user_update_applies_the_same_policy_and_stays_optional(): void
    {
        $owner = $this->platformAccount('owner.users@leopardo.test');
        $target = $this->platformAccount('target.users@leopardo.test');

        Sanctum::actingAs($owner, ['*'], 'super_admin_api');

        $this->patchJson("/api/v1/platform/users/{$target->id}", [
            'password' => self::TWELVE_CHARS_WITHOUT_DIGIT,
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        // `sometimes` préservé : sans champ `password`, la mise à jour passe
        // (le mot de passe existant reste inchangé).
        $this->patchJson("/api/v1/platform/users/{$target->id}", ['name' => 'Renommé'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renommé');
    }

    private function platformAccount(string $email): SuperAdmin
    {
        /** @var SuperAdmin $account */
        $account = new SuperAdmin([
            'name' => 'Platform Owner',
            'email' => $email,
        ]);
        $account->forceFill([
            'password_hash' => Hash::make(self::COMPLIANT),
            'status' => 'active',
            'platform_role' => 'super_admin',
        ])->save();

        return $account;
    }
}
