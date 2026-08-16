<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #4496 — password_hash + chemins biométriques ne sont plus mass-assignables.
 *
 * `Employee::$fillable` contenait `password_hash`, `biometric_face_reference_path`,
 * `biometric_fingerprint_reference_path` et `email_bounced_at` : un futur
 * `Employee::create($request->all())` aurait pu écraser silencieusement le mot
 * de passe ou les références biométriques (inversé du correctif #3597).
 */
final class EmployeeSensitiveFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_hash_is_not_mass_assignable(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::query()->create([
            'first_name' => 'Mass',
            'last_name' => 'Assign',
            'email' => 'mass-assign@leopardo.test',
            'company_id' => $company->id,
            'password_hash' => 'HACKED_BY_PAYLOAD',
        ]);

        $this->assertNull($employee->fresh()?->password_hash, 'password_hash ne doit pas être écrit via mass-assignment');
    }

    public function test_biometric_reference_paths_are_not_mass_assignable(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::query()->create([
            'first_name' => 'Bio',
            'last_name' => 'Metric',
            'email' => 'bio.metric@leopardo.test',
            'company_id' => $company->id,
            'biometric_face_reference_path' => 's3://attacker/face.jpg',
            'biometric_fingerprint_reference_path' => 's3://attacker/finger.bin',
            'email_bounced_at' => now(),
        ]);

        $fresh = $employee->fresh();

        $this->assertNotNull($fresh);
        $this->assertNull($fresh->biometric_face_reference_path);
        $this->assertNull($fresh->biometric_fingerprint_reference_path);
        $this->assertNull($fresh->email_bounced_at);
    }

    public function test_legit_service_write_still_persists_password_hash(): void
    {
        // L'écriture légitime (services dédiés) passe par forceFill/setter explicite.
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::query()->create([
            'first_name' => 'Legit',
            'last_name' => 'Write',
            'email' => 'legit.write@leopardo.test',
            'company_id' => $company->id,
        ]);

        $employee->forceFill(['password_hash' => bcrypt('secret')])->save();

        $fresh = $employee->fresh();

        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->password_hash);
        $this->assertTrue(password_verify('secret', (string) $fresh->password_hash));
    }
}
