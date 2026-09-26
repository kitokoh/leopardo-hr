<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #8124 — le verrouillage anti-brute-force ne doit pas devenir un déni de
 * service ciblé.
 *
 * Avant : le verrou (`locked_until` / `platform_login_<email>:lock`) était
 * évalué AVANT la vérification du mot de passe. Un attaquant connaissant
 * l'email pouvait rejouer 5 échecs toutes les 15 min et rendre le compte
 * inutilisable — le titulaire LÉGITIME recevait 423 même avec le bon mot de
 * passe.
 *
 * Après : les identifiants VALIDES passent (verrou levé, tentatives remises à
 * zéro, événement tracé) ; les tentatives FAUSSES sur un compte verrouillé
 * conservent le contrat existant (423), sans prolonger le verrou.
 */
class LockoutValidCredentialsTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_tenant_login_with_valid_credentials_overrides_active_lock(): void
    {
        $employee = $this->lockedEmployee();

        $this->postJson('/api/v1/auth/login', [
            'email' => $employee->email,
            'password' => 'password123',
        ])->assertOk();

        // Le verrou et le compteur sont levés : le titulaire n'est pas puni.
        $fresh = $employee->fresh();
        $this->assertInstanceOf(Employee::class, $fresh);
        $this->assertNull($fresh->locked_until);
        $this->assertSame(0, (int) $fresh->failed_login_attempts);
    }

    public function test_tenant_login_with_invalid_credentials_keeps_the_lock(): void
    {
        $employee = $this->lockedEmployee();

        $this->postJson('/api/v1/auth/login', [
            'email' => $employee->email,
            'password' => 'mauvais-mot-de-passe',
        ])->assertStatus(423);

        // Le verrou n'est pas prolongé par une tentative fausse.
        $fresh = $employee->fresh();
        $this->assertInstanceOf(Employee::class, $fresh);
        $this->assertNotNull($fresh->locked_until);
    }

    public function test_platform_login_with_valid_credentials_overrides_active_lock(): void
    {
        $superAdmin = new SuperAdmin(['name' => 'Locked Admin', 'email' => 'locked-admin@leopardo.test']);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123'), 'status' => 'active'])->save();

        Cache::put('platform_login_'.$superAdmin->email.':lock', true, now()->addMinutes(15));

        $this->postJson('/api/v1/platform/auth/login', [
            'email' => $superAdmin->email,
            'password' => 'password123',
        ])->assertOk();

        $this->assertNull(Cache::get('platform_login_'.$superAdmin->email.':lock'));
    }

    public function test_platform_login_with_invalid_credentials_keeps_the_lock(): void
    {
        $superAdmin = new SuperAdmin(['name' => 'Locked Admin 2', 'email' => 'locked-admin2@leopardo.test']);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123'), 'status' => 'active'])->save();

        Cache::put('platform_login_'.$superAdmin->email.':lock', true, now()->addMinutes(15));

        $this->postJson('/api/v1/platform/auth/login', [
            'email' => $superAdmin->email,
            'password' => 'mauvais',
        ])->assertStatus(423);
    }

    public function test_repeated_failed_attempts_still_lock_the_tenant_account(): void
    {
        $company = $this->company();
        $employee = new Employee(['email' => 'brute-force@company.test']);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
            'failed_login_attempts' => 0,
        ])->save();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'brute-force@company.test',
                'password' => 'faux',
            ])->assertStatus(401);
        }

        $fresh = $employee->fresh();
        $this->assertInstanceOf(Employee::class, $fresh);
        $this->assertNotNull($fresh->locked_until, 'la protection anti-brute-force reste active');
    }

    private function lockedEmployee(): Employee
    {
        $company = $this->company();

        $employee = new Employee(['email' => 'locked.owner@company.test']);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(10),
        ])->save();

        return $employee;
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::query()->create([
            'name' => 'Lockout Co',
            'slug' => 'lockout-co-'.uniqid(),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'lockout@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        return $company;
    }
}
