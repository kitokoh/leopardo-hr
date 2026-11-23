<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Domain\Models\EduFeePayment;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9791 — encaissement en lot et exports CSV du guichet.
 *
 * Verrouille les deux garanties qui font qu'un lot est utilisable au guichet :
 *
 * 1. **Validation intégrale avant écriture** : une seule ligne fautive annule
 *    le lot ENTIER et la réponse désigne la ligne (index) — sinon le caissier
 *    cherche laquelle, parmi trente, a échoué, et un lot à moitié appliqué
 *    rend le rapprochement comptable indémêlable.
 * 2. **Exports bornés au tenant** : l'export ne doit jamais élargir le
 *    périmètre de la liste (une fuite par export serait invisible à l'écran).
 */
class EduFeeBatchAndExportTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyB;

    private Employee $principalA;

    private Employee $lambdaA;

    private EduAcademicYear $yearA;

    /** @var list<EduStudent> */
    private array $studentsA = [];

    protected function setUp(): void
    {
        parent::setUp();

        // #9859 — `$this->companyA` était écrit sans jamais être lu (PHPStan
        // `property.onlyWritten`) : le scénario dual-tenant ne se sert du
        // tenant A que par ses ids locaux. On garde `$companyB`, qui est lu
        // par les tests d'isolation.
        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['edumanager' => true],
        ]);

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['edumanager' => true],
        ]);
        $this->companyB = $companyB;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;

        /** @var EduAcademicYear $yearA */
        $yearA = EduAcademicYear::query()->create([
            'company_id' => $companyA->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);
        $this->yearA = $yearA;

        foreach (['Lina Benali', 'Yacine Haddad', 'Nour Cherif'] as $i => $name) {
            /** @var EduStudent $student */
            $student = EduStudent::query()->create([
                'company_id' => $companyA->id,
                'student_number' => 'STU-000'.($i + 1),
                'display_name' => $name,
                'status' => EduStudent::STATUS_ACTIVE,
            ]);
            $this->studentsA[] = $student;
        }
    }

    private function baseUrl(): string
    {
        return '/api/v1/edu-manager';
    }

    private function createFeeType(): int
    {
        Sanctum::actingAs($this->principalA);

        $response = $this->postJson($this->baseUrl().'/fee-types', [
            'code' => 'TUITION',
            'label' => 'Scolarité annuelle',
            'amount' => 50000,
            'currency' => 'DZD',
            'billing_frequency' => 'once',
        ]);

        $response->assertStatus(201);

        return (int) $response->json('data.id');
    }

    private function createCharge(int $feeTypeId, int $studentIndex, string $externalId): int
    {
        Sanctum::actingAs($this->principalA);

        $response = $this->postJson($this->baseUrl().'/fee-charges', [
            'student_id' => (int) $this->studentsA[$studentIndex]->getAttribute('id'),
            'fee_type_id' => $feeTypeId,
            'academic_year_id' => (int) $this->yearA->getAttribute('id'),
            'external_id' => $externalId,
        ]);

        $response->assertStatus(201);

        return (int) $response->json('data.id');
    }

    /**
     * Le cas nominal : trois élèves d'une classe règlent le même jour.
     */
    public function test_batch_records_every_line_and_reports_summary(): void
    {
        $feeTypeId = $this->createFeeType();
        $c1 = $this->createCharge($feeTypeId, 0, 'ext-batch-1');
        $c2 = $this->createCharge($feeTypeId, 1, 'ext-batch-2');
        $c3 = $this->createCharge($feeTypeId, 2, 'ext-batch-3');

        Sanctum::actingAs($this->principalA);

        $response = $this->postJson($this->baseUrl().'/fee-charges/batch-payments', [
            'method' => 'cash',
            'reference' => 'BORD-2026-09',
            'external_id' => 'lot-rentree-1',
            'lines' => [
                ['charge_id' => $c1, 'amount' => 50000],
                ['charge_id' => $c2, 'amount' => 20000],
                ['charge_id' => $c3, 'amount' => 10000],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('summary.lines', 3)
            ->assertJsonPath('summary.recorded', 3)
            ->assertJsonPath('summary.total_amount', 80000)
            ->assertJsonPath('summary.currency', 'DZD');

        // Les statuts suivent le cumul : soldé, partiel, partiel.
        $this->assertSame(EduFeeCharge::STATUS_PAID, EduFeeCharge::query()->findOrFail($c1)->status);
        $this->assertSame(EduFeeCharge::STATUS_PARTIAL, EduFeeCharge::query()->findOrFail($c2)->status);
        $this->assertSame(EduFeeCharge::STATUS_PARTIAL, EduFeeCharge::query()->findOrFail($c3)->status);

        // Le solde restant est juste pour chaque ligne.
        $this->assertSame(30000.0, (float) $response->json('data.1.remaining_amount'));
        $this->assertSame(40000.0, (float) $response->json('data.2.remaining_amount'));

        // La référence de bordereau est portée par chaque encaissement.
        $this->assertSame(3, EduFeePayment::query()->where('reference', 'BORD-2026-09')->count());
    }

    /**
     * Une ligne invalide annule le lot ENTIER et désigne la ligne fautive.
     *
     * C'est la garantie qui compte : un lot à moitié appliqué laisserait des
     * écritures comptables orphelines et un état de caisse faux.
     */
    public function test_one_invalid_line_aborts_the_whole_batch(): void
    {
        $feeTypeId = $this->createFeeType();
        $c1 = $this->createCharge($feeTypeId, 0, 'ext-abort-1');
        $c2 = $this->createCharge($feeTypeId, 1, 'ext-abort-2');

        Sanctum::actingAs($this->principalA);

        // Ligne 1 : 60 000 sur une charge de 50 000 → surdébit.
        $response = $this->postJson($this->baseUrl().'/fee-charges/batch-payments', [
            'method' => 'cash',
            'lines' => [
                ['charge_id' => $c1, 'amount' => 10000],
                ['charge_id' => $c2, 'amount' => 60000],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'EDU_FEE_BATCH_INVALID')
            ->assertJsonPath('errors.0.index', 1)
            ->assertJsonPath('errors.0.charge_id', $c2)
            ->assertJsonPath('errors.0.error', 'EDU_FEE_OVERPAYMENT')
            ->assertJsonPath('summary.recorded', 0);

        // RIEN n'a été écrit — même la ligne valide.
        $this->assertSame(0, EduFeePayment::query()->count());
        $this->assertSame(EduFeeCharge::STATUS_PENDING, EduFeeCharge::query()->findOrFail($c1)->status);
    }

    /**
     * Les refus métier sont tous détectés AVANT écriture, et indexés.
     */
    public function test_batch_reports_each_business_refusal_by_index(): void
    {
        $feeTypeId = $this->createFeeType();
        $c1 = $this->createCharge($feeTypeId, 0, 'ext-refus-1');
        $c2 = $this->createCharge($feeTypeId, 1, 'ext-refus-2');

        // Charge soldée : plus aucun encaissement possible.
        Sanctum::actingAs($this->principalA);
        $this->postJson($this->baseUrl()."/fee-charges/{$c2}/payments", [
            'amount' => 50000,
            'method' => 'cash',
            'external_id' => 'ext-settle-c2',
        ])->assertStatus(201);

        $response = $this->postJson($this->baseUrl().'/fee-charges/batch-payments', [
            'method' => 'cash',
            'lines' => [
                ['charge_id' => $c1, 'amount' => 1000],
                ['charge_id' => $c2, 'amount' => 1000],          // terminale
                ['charge_id' => 999999, 'amount' => 1000],       // inexistante
                ['charge_id' => $c1, 'amount' => 1000],          // doublon
            ],
        ]);

        $response->assertStatus(422);

        /** @var list<array{index: int, error: string}> $errorRows */
        $errorRows = (array) $response->json('errors');

        // #9859 — `collect(...)->keyBy('index')` rendait une valeur NULLABLE
        // pour chaque clé, donc `$errors[1]['error']` était une lecture sur
        // `...|null` (PHPStan `offsetAccess.notFound`). Une table d'index
        // explicite dit exactement ce que le test affirme.
        /** @var array<int, string> $errorsByIndex */
        $errorsByIndex = [];

        foreach ($errorRows as $row) {
            $errorsByIndex[(int) $row['index']] = (string) $row['error'];
        }

        $this->assertSame('EDU_FEE_TERMINAL', $errorsByIndex[1] ?? null);
        $this->assertSame('EDU_FEE_CHARGE_NOT_FOUND', $errorsByIndex[2] ?? null);
        $this->assertSame('EDU_FEE_BATCH_DUPLICATE', $errorsByIndex[3] ?? null);

        // Aucune écriture, y compris pour la ligne 0 qui était valide.
        $this->assertSame(1, EduFeePayment::query()->count()); // seulement le règlement de c2
    }

    /**
     * Isolation : une charge d'un autre tenant est « introuvable », jamais
     * encaissée — et le lot est refusé en bloc.
     */
    public function test_batch_never_touches_another_tenant(): void
    {
        $feeTypeId = $this->createFeeType();
        $c1 = $this->createCharge($feeTypeId, 0, 'ext-iso-1');

        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($principalB);

        $this->postJson($this->baseUrl().'/fee-charges/batch-payments', [
            'method' => 'cash',
            'lines' => [['charge_id' => $c1, 'amount' => 1000]],
        ])->assertStatus(422)
            ->assertJsonPath('errors.0.error', 'EDU_FEE_CHARGE_NOT_FOUND');

        $this->assertSame(0, EduFeePayment::query()->count());
    }

    /**
     * Idempotence : rejouer le même bordereau ne double aucun encaissement.
     */
    public function test_batch_is_idempotent_on_batch_external_id(): void
    {
        $feeTypeId = $this->createFeeType();
        $c1 = $this->createCharge($feeTypeId, 0, 'ext-idem-1');

        Sanctum::actingAs($this->principalA);

        $payload = [
            'method' => 'transfer',
            'external_id' => 'lot-idem-1',
            'lines' => [['charge_id' => $c1, 'amount' => 20000]],
        ];

        $this->postJson($this->baseUrl().'/fee-charges/batch-payments', $payload)->assertStatus(201);
        $this->postJson($this->baseUrl().'/fee-charges/batch-payments', $payload)->assertStatus(201);

        // Un seul encaissement réel malgré deux appels.
        $this->assertSame(1, EduFeePayment::query()->count());
        $this->assertSame(20000.0, (float) EduFeeCharge::query()->findOrFail($c1)->payments()->sum('amount'));
    }

    /**
     * RBAC : employé lambda 403, non authentifié 401.
     */
    public function test_batch_requires_direction(): void
    {
        $feeTypeId = $this->createFeeType();
        $c1 = $this->createCharge($feeTypeId, 0, 'ext-rbac-1');

        $payload = [
            'method' => 'cash',
            'lines' => [['charge_id' => $c1, 'amount' => 1000]],
        ];

        Sanctum::actingAs($this->lambdaA);
        $this->postJson($this->baseUrl().'/fee-charges/batch-payments', $payload)->assertStatus(403);

        Auth::forgetGuards();
        $this->postJson($this->baseUrl().'/fee-charges/batch-payments', $payload)->assertStatus(401);
    }

    /**
     * Export du journal : en-tête, lignes, totaux, et périmètre borné au tenant.
     */
    public function test_payments_export_contains_rows_totals_and_stays_in_tenant(): void
    {
        $feeTypeId = $this->createFeeType();
        $c1 = $this->createCharge($feeTypeId, 0, 'ext-csv-1');

        Sanctum::actingAs($this->principalA);
        $this->postJson($this->baseUrl()."/fee-charges/{$c1}/payments", [
            'amount' => 12000,
            'method' => 'cash',
            'reference' => 'RECU-001',
            'external_id' => 'ext-csv-pay-1',
        ])->assertStatus(201);

        $response = $this->get($this->baseUrl().'/fee-payments/export.csv');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();

        // BOM UTF-8 pour Excel.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('date;eleve;matricule;facturation;montant;devise;moyen;reference', $csv);
        $this->assertStringContainsString('Lina Benali', $csv);
        $this->assertStringContainsString('RECU-001', $csv);
        $this->assertStringContainsString('12000.00', $csv);
        $this->assertStringContainsString('TOTAL', $csv);

        // Isolation : le tenant B exporte un fichier vide (en-tête + TOTAL 0).
        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($principalB);

        $other = $this->get($this->baseUrl().'/fee-payments/export.csv');
        $other->assertOk();
        $this->assertStringNotContainsString('Lina Benali', $other->streamedContent());
    }

    /**
     * Export du rapprochement : totaux ET écart débit − crédit dans le fichier.
     */
    public function test_accounting_export_carries_totals_and_balance(): void
    {
        $feeTypeId = $this->createFeeType();
        $c1 = $this->createCharge($feeTypeId, 0, 'ext-csv-acc-1');

        Sanctum::actingAs($this->principalA);
        $this->postJson($this->baseUrl()."/fee-charges/{$c1}/payments", [
            'amount' => 50000,
            'method' => 'cash',
            'external_id' => 'ext-csv-acc-pay-1',
        ])->assertStatus(201);

        $response = $this->get($this->baseUrl().'/fee-accounting-entries/export.csv');
        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('date;source;source_id;compte;intitule;debit;credit;reference', $csv);
        $this->assertStringContainsString('TOTAL', $csv);
        $this->assertStringContainsString('EQUILIBRE', $csv);

        // Le contrôle d'équilibre doit être à zéro : les lignes sont équilibrées.
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $balanceLine = null;
        foreach ($lines as $line) {
            if (str_starts_with($line, 'EQUILIBRE')) {
                $balanceLine = $line;
            }
        }

        $this->assertNotNull($balanceLine, 'La ligne EQUILIBRE doit être présente.');
        $cells = explode(';', $balanceLine);
        $this->assertSame('0.00', $cells[5], 'L\'écart débit − crédit doit être nul.');
    }

    /**
     * Les exports sont réservés à la direction.
     */
    public function test_exports_require_direction(): void
    {
        Sanctum::actingAs($this->lambdaA);

        $this->get($this->baseUrl().'/fee-payments/export.csv')->assertStatus(403);
        $this->get($this->baseUrl().'/fee-accounting-entries/export.csv')->assertStatus(403);
    }
}
