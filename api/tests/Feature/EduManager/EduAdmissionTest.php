<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Exceptions\TenantContextMissingException;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Application\Services\AdmissionService;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Policies\EduAdmissionPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5820 (EDU-004) — dossier d'inscription (admission) et conversion
 * vers élève, SANS coupler les tables scolaires au CRM client.
 *
 * Couvre : création bornée aux gestionnaires du tenant, conversion
 * IDEMPOTENTE (2 appels = 1 élève), détection de doublons (nom + contact),
 * traçabilité du consentement contact, refus cross-tenant, et PII jamais
 * exposée en clair (contact_reference chiffrée au repos).
 */
class EduAdmissionTest extends TestCase
{
    use RefreshTenantDatabase;
    use WithFaker;

    private Company $company;

    private Company $otherCompany;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->otherCompany = $other;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->manager = $manager;

        // Contexte tenant courant : requis par le scope BelongsToCompany et
        // par currentCompany() dans AdmissionService (pattern VatDeclarationTest).
        app()->instance('current_company', $company);
        Sanctum::actingAs($manager);
    }

    public function test_manager_can_create_and_view_admission(): void
    {
        $policy = app(EduAdmissionPolicy::class);
        $admission = $this->admission($this->company, 'ADM-2026-0002');

        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->create($this->manager));
        $this->assertTrue($policy->view($this->manager, $admission));
        $this->assertTrue($policy->update($this->manager, $admission));
        $this->assertTrue($policy->delete($this->manager, $admission));
        $this->assertTrue($policy->convert($this->manager, $admission));

        // Un employé simple ne gère pas les dossiers (PII).
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
        $this->assertFalse($policy->viewAny($employee));
        $this->assertFalse($policy->create($employee));
        $this->assertFalse($policy->view($employee, $admission));
    }

    public function test_admission_number_is_unique_per_tenant(): void
    {
        $this->admission($this->company, 'ADM-UNIQ-001');
        // Même numéro chez un AUTRE tenant : autorisé (unicité par tenant).
        $this->admission($this->otherCompany, 'ADM-UNIQ-001');

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL ne
        // doit pas empoisonner la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        try {
            DB::transaction(function (): void {
                $this->admission($this->company, 'ADM-UNIQ-001');
            });
            $this->fail('Doublon du numéro dans le même tenant aurait dû lever QueryException.');
        } catch (QueryException) {
            // Attendu — UNIQUE(company_id, admission_number).
        }

        // Le doublon n'a pas été inséré (et l'autre tenant garde le sien).
        $this->assertSame(1, EduAdmission::query()->where('admission_number', 'ADM-UNIQ-001')->count());
    }

    public function test_conversion_is_idempotent(): void
    {
        $admission = $this->admission($this->company, 'ADM-2026-0001', [
            'applicant_name' => 'Yasmine Khelifi',
            'contact_reference' => 'yasmine@example.com',
        ]);

        $service = app(AdmissionService::class);

        $first = $service->convert($admission, ['birth_date_encrypted' => '2016-05-12']);
        $second = $service->convert($admission, []);

        // Idempotence : 2 appels → 1 seul élève, même instance métier.
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, EduStudent::query()->count());

        // Rattachement + statut + traçage de la décision.
        $this->assertSame($first->id, $admission->student_id);
        $this->assertSame(EduAdmission::STATUS_ENROLLED, $admission->status);
        $this->assertSame($this->manager->id, $admission->decided_by);
        $this->assertNotNull($admission->decided_at);

        // L'élève est bien issu du dossier (display_name, statut actif, tenant).
        $this->assertSame('Yasmine Khelifi', $first->display_name);
        $this->assertSame(EduStudent::STATUS_ACTIVE, $first->status);
        $this->assertSame($this->company->id, $first->company_id);
        $this->assertSame('2016-05-12', $first->birth_date_encrypted);
    }

    public function test_duplicate_detection_matches_name_and_contact(): void
    {
        $this->admission($this->company, 'ADM-DUP-001', [
            'applicant_name' => '  Aïcha Benali ',
            'contact_reference' => 'aicha@example.com',
        ]);

        $service = app(AdmissionService::class);

        // Même nom (normalisé) + même contact → doublon potentiel.
        $this->assertTrue($service->detectDuplicates('aicha benali', 'aicha@example.com'));
        // Même nom mais contact différent → pas le même candidat.
        $this->assertFalse($service->detectDuplicates('Aïcha Benali', 'autre@example.com'));
        // Contact seul, nom différent → pas de doublon.
        $this->assertFalse($service->detectDuplicates('Yacine Benali', 'aicha@example.com'));
        // Nom seul (contact non fourni) → doublon potentiel conservateur.
        $this->assertTrue($service->detectDuplicates('aicha benali'));
    }

    public function test_consent_is_tracked_with_timestamp(): void
    {
        $admission = $this->admission($this->company, 'ADM-CONS-001');

        $this->assertFalse($admission->consent_marketing);
        $this->assertNull($admission->consent_at);

        app(AdmissionService::class)->giveConsent($admission);

        $this->assertTrue($admission->consent_marketing);
        $this->assertNotNull($admission->consent_at);

        // Tracé en base, pas seulement en mémoire.
        $raw = DB::table('edu_admissions')->where('id', $admission->id)->first();
        $this->assertSame(true, (bool) $raw->consent_marketing);
        $this->assertNotNull($raw->consent_at);
    }

    public function test_cross_tenant_conversion_is_refused(): void
    {
        $otherAdmission = $this->admission($this->otherCompany, 'ADM-XT-0001');

        // Policy : le gestionnaire du tenant A ne convertit pas un dossier du tenant B.
        $this->assertFalse(app(EduAdmissionPolicy::class)->convert($this->manager, $otherAdmission));

        try {
            app(AdmissionService::class)->convert($otherAdmission, []);
            $this->fail('La conversion cross-tenant aurait dû lever TenantContextMissingException.');
        } catch (TenantContextMissingException) {
            // Attendu — refus avant toute écriture.
        }

        // Aucun élève créé, dossier intact.
        $this->assertSame(0, EduStudent::query()->count());
        $this->assertNull($otherAdmission->student_id);
        $this->assertSame(EduAdmission::STATUS_PENDING, $otherAdmission->status);
    }

    public function test_contact_reference_is_encrypted_at_rest(): void
    {
        $clear = 'contact-'.Str::upper(Str::random(8));
        $this->admission($this->company, 'ADM-PII-0001', ['contact_reference' => $clear]);

        /** @var EduAdmission $admission */
        $admission = EduAdmission::query()->where('admission_number', 'ADM-PII-0001')->firstOrFail();

        // Via le modèle (cast `encrypted`) : valeur claire.
        $this->assertSame($clear, $admission->contact_reference);

        // Au repos : enveloppe Laravel base64('{"iv":...') — jamais le clair.
        $raw = DB::table('edu_admissions')->where('id', $admission->id)->value('contact_reference');
        $this->assertIsString($raw);
        $this->assertNotSame($clear, $raw);
        $this->assertStringStartsWith('eyJ', (string) $raw);
        $this->assertSame($clear, Crypt::decryptString((string) $raw));
    }

    private function admission(Company $company, string $number, array $overrides = []): EduAdmission
    {
        /** @var EduAdmission $admission */
        $admission = EduAdmission::query()->create(array_merge([
            'company_id' => $company->id,
            'admission_number' => $number,
            'applicant_name' => $this->faker->name(),
            'status' => EduAdmission::STATUS_PENDING,
            'consent_marketing' => false,
            'submitted_at' => now(),
        ], $overrides));

        return $admission;
    }
}
