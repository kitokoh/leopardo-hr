<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Privacy\PiiFieldRegistry;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * MAT-011 (#5869) — classification PII et cycle de vie.
 *
 * Vérifie la cohérence du registre machine `config/privacy.php` :
 * - chaque champ sensible anonymisé par `gdpr:anonymize-employee` possède
 *   une politique déclarée ;
 * - les champs marqués `encrypted` correspondent aux casts `encrypted`
 *   réels d'`Employee` ;
 * - chaque champ déclaré existe comme colonne réelle (pas de champ fantôme) ;
 * - entités/champs inconnus → aucun accès (null / []).
 */
class PiiFieldRegistryTest extends TestCase
{
    use CreatesMvpSchema;

    private PiiFieldRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->registry = app(PiiFieldRegistry::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_every_gdpr_anonymized_field_has_a_policy(): void
    {
        // Miroir canonique de la map d'anonymisation de
        // `gdpr:anonymize-employee` (champs PII uniquement).
        $anonymizedByCommand = [
            'first_name',
            'middle_name',
            'last_name',
            'preferred_name',
            'email',
            'personal_email',
            'recovery_email',
            'personal_phone',
            'phone',
            'address_line',
            'postal_code',
            'date_of_birth',
            'place_of_birth',
            'gender',
            'nationality',
            'marital_status',
            'national_id',
            'iban',
            'bank_account',
            'zkteco_id',
            'photo_path',
            'biometric_face_reference_path',
            'biometric_fingerprint_reference_path',
            'biometric_consent_at',
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_relation',
            'password_hash',
            'two_fa_secret',
            'two_fa_recovery_codes',
            'extra_data',
        ];

        $declared = $this->registry->anonymizedFields('employee');

        foreach ($anonymizedByCommand as $field) {
            $this->assertContains($field, $declared, "Champ PII [{$field}] sans politique d'anonymisation");
        }
    }

    public function test_encrypted_fields_match_employee_casts(): void
    {
        $casts = (new Employee)->getCasts();

        $this->assertSame(['bank_account', 'iban', 'national_id'], $this->registry->encryptedFields('employee'));

        foreach ($this->registry->encryptedFields('employee') as $field) {
            $this->assertSame('encrypted', $casts[$field] ?? null, "[{$field}] doit être casté encrypted");
        }
    }

    public function test_every_declared_field_is_a_real_column(): void
    {
        foreach ($this->registry->entityFields('employee') as $field => $policy) {
            $this->assertTrue(
                Schema::hasColumn('employees', $field),
                "Champ déclaré [{$field}] introuvable dans la table employees"
            );
            $this->assertArrayHasKey('category', $policy);
            $this->assertArrayHasKey('encrypted', $policy);
            $this->assertArrayHasKey('anonymized', $policy);
            $this->assertArrayHasKey('exported', $policy);
            $this->assertArrayHasKey('access', $policy);
            $this->assertArrayHasKey('retention', $policy);
            $this->assertArrayHasKey('legal_basis', $policy);
        }
    }

    public function test_exported_fields_include_identity_and_contact(): void
    {
        $exported = $this->registry->exportedFields('employee');

        foreach (['first_name', 'last_name', 'email', 'personal_email', 'phone', 'date_of_birth'] as $field) {
            $this->assertContains($field, $exported, "[{$field}] doit être inclus dans le bundle d'export RGPD");
        }
    }

    public function test_unknown_entity_and_field_have_no_policy(): void
    {
        $this->assertNull($this->registry->policy('employee', 'nonexistent_field'));
        $this->assertFalse($this->registry->isPii('employee', 'nonexistent_field'));
        $this->assertSame([], $this->registry->entityFields('nonexistent_entity'));
        $this->assertFalse($this->registry->isPii('nonexistent_entity', 'email'));
    }
}
