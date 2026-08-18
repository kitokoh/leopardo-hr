<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #4496 — password_hash, chemins biométriques et email_bounced_at ne
 * sont plus mass-assignables sur Employee : un futur `create($request->all())`
 * ou oubli d'allowlist ne peut plus écraser silencieusement un mot de passe
 * ou des références biométriques (inversé du durcissement #3597/#3677).
 */
class EmployeeSensitiveFillableTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_sensitive_fields_are_not_written_by_mass_assignment(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => 'Sensitive QA',
            'sector' => 'tech',
            'country' => 'DZ',
        ]);

        $employee = new Employee([
            'first_name' => 'Mass',
            'last_name' => 'Assignment',
            'email' => 'mass-assignment@example.test',
            'company_id' => $company->id,
            'biometric_face_reference_path' => 'face/attacker.jpg',
            'biometric_fingerprint_reference_path' => 'finger/attacker.jpg',
            'email_bounced_at' => now(),
        ]);

        // #4496 : les champs sensibles passés au constructeur (mass
        // assignment) sont abandonnés par le $fillable — vérifié sur le
        // modèle AVANT le save (pattern #3597/#3677).
        $this->assertNull($employee->password_hash, 'password_hash ne doit pas être mass-assignable.');
        $this->assertNull($employee->company_id, 'company_id ne doit pas être mass-assignable.');
        $this->assertNull($employee->biometric_face_reference_path, 'Référence biométrique visage non mass-assignable.');
        $this->assertNull($employee->biometric_fingerprint_reference_path, 'Référence biométrique empreinte non mass-assignable.');
        $this->assertNull($employee->email_bounced_at, 'email_bounced_at non mass-assignable.');

        // Écriture légitime explicite (pattern #4496) — seule voie autorisée
        // pour poser password_hash (NOT NULL sur les vraies migrations, F-13).
        $employee->forceFill(['password_hash' => Hash::make('attacker-controlled')])->save();

        $fresh = $employee->fresh();
        $this->assertNotNull($fresh);

        $this->assertTrue(Hash::check('attacker-controlled', (string) $fresh->password_hash));
        $this->assertNull($fresh->biometric_face_reference_path, 'Référence biométrique visage non mass-assignable.');
        $this->assertNull($fresh->biometric_fingerprint_reference_path, 'Référence biométrique empreinte non mass-assignable.');
        $this->assertNull($fresh->email_bounced_at, 'email_bounced_at non mass-assignable.');
    }

    public function test_sensitive_fields_can_still_be_set_explicitly(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => 'Explicit QA',
            'sector' => 'tech',
            'country' => 'DZ',
        ]);

        // F-13 : employees.password_hash est NOT NULL sur les vraies
        // migrations (pas de défaut comme dans l'ancienne fixture mvp) — on
        // le pose explicitement au premier save (pattern #4496, hors fillable).
        $employee = new Employee([
            'first_name' => 'Explicit',
            'last_name' => 'Writer',
            'email' => 'explicit-writer@example.test',
        ]);
        $employee->forceFill(['password_hash' => Hash::make('legit-password')])->save();
        $employee->company_id = $company->id;

        // Écriture légitime hors fillable (pattern #4496 : les services
        // autorisés posent ces champs explicitement).
        $employee->forceFill([
            'biometric_face_reference_path' => 'face/legit.jpg',
            'biometric_fingerprint_reference_path' => 'finger/legit.jpg',
            'email_bounced_at' => now(),
        ])->save();

        $fresh = $employee->fresh();
        $this->assertNotNull($fresh);

        $this->assertTrue(Hash::check('legit-password', (string) $fresh->password_hash));
        $this->assertSame('face/legit.jpg', $fresh->biometric_face_reference_path);
        $this->assertSame('finger/legit.jpg', $fresh->biometric_fingerprint_reference_path);
        $this->assertNotNull($fresh->email_bounced_at);
    }
}
