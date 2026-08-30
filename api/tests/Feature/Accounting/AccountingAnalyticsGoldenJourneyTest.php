<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Database\Seeders\AccountingAnalyticsPilotSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-22-D12 (issue #6244) — golden journey Analytics end-to-end.
 *
 * Parcours « ouvrir le dashboard comptable → lire les agrégats → exporter les
 * impayés » sur le seed pilote synthétique (#6244) :
 *   - seed reproductible et anti-production (MAT-012) ;
 *   - agrégats déterministes : deux lectures du dashboard → mêmes totaux ;
 *   - cohérence avec le seed : période large fixe (J-90 → aujourd'hui) pour
 *     des assertions stables quelle que soit la date d'exécution ;
 *   - export CSV impayés téléchargeable et sanitisé (CsvCellSanitizer) ;
 *   - état vide : un tenant sans données → agrégats à zéro, liste vide ;
 *   - RBAC : un employé non-manager n'accède pas au dashboard (403).
 */
class AccountingAnalyticsGoldenJourneyTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $pilot;

    protected function setUp(): void
    {
        parent::setUp();

        (new AccountingAnalyticsPilotSeeder)->run();

        /** @var Company $pilot */
        $pilot = Company::query()->where('slug', 'analytics-pilot-001')->firstOrFail();
        $this->pilot = $pilot;
    }

    private function periodQuery(): array
    {
        // Période large couvrant TOUTES les dates du seed (relatives à J-60)
        // → agrégats stables quelle que soit la date d'exécution.
        return [
            'from' => now()->subDays(90)->toDateString(),
            'to' => now()->toDateString(),
        ];
    }

    public function test_pilot_seed_is_reentrant(): void
    {
        (new AccountingAnalyticsPilotSeeder)->run();

        // Réentrant : toujours un seul tenant pilote, une seule vitrine.
        $this->assertSame(1, Company::query()->where('slug', 'analytics-pilot-001')->count());
        $this->assertSame(7, DB::table('accounting_documents')->where('company_id', $this->pilot->id)->count());
    }

    public function test_dashboard_aggregates_match_seed_and_are_deterministic(): void
    {
        Sanctum::actingAs($this->manager($this->pilot));

        $query = '/api/v1/accounting/dashboard?'.http_build_query($this->periodQuery());

        $first = $this->getJson($query)->assertOk();
        $second = $this->getJson($query)->assertOk();

        // Déterminisme : deux lectures → mêmes agrégats.
        $this->assertSame($first->json('data'), $second->json('data'));

        // Cohérence avec le seed (période J-90 → aujourd'hui) :
        // 7 documents client émis (devis, 2 factures, proforma, avoir,
        // bordereau, reçu), 2 encaissements, 5 impayés (hors avoir négatif
        // et facture payée).
        $first->assertJsonPath('data.invoices.count', 7);
        $first->assertJsonPath('data.collections.count', 2);
        $first->assertJsonPath('data.outstanding.count', 5);
        $first->assertJsonPath('data.snapshot.source', 'live');

        $totalDue = $first->json('data.outstanding.total_due');
        $this->assertIsNumeric($totalDue);
        $this->assertGreaterThan(0, (float) $totalDue);
    }

    public function test_export_csv_outstanding_is_downloadable_and_sanitized(): void
    {
        Sanctum::actingAs($this->manager($this->pilot));

        $response = $this->get('/api/v1/accounting/dashboard/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = (string) $response->getContent();
        $this->assertStringContainsString('number,contact,issue_date,due_date,days_late,total_ttc,paid_amount,due_amount,status', $csv);

        // Autant de lignes de données que d'impayés du seed (5).
        $lines = array_values(array_filter(array_map('trim', explode("\n", $csv)), static fn (string $line): bool => $line !== ''));
        $this->assertCount(6, $lines); // 1 entête + 5 impayés

        // Sanitisation CSV : aucune cellule ne commence par une formule
        // (=, +, -, @) — CsvCellSanitizer sur numéro et contact.
        foreach (array_slice($lines, 1) as $line) {
            $this->assertMatchesRegularExpression('/^(?![\=\+\-@])/', $line, "Cellule potentiellement injectable : {$line}");
        }
    }

    public function test_empty_tenant_returns_zeroed_aggregates(): void
    {
        /** @var Company $empty */
        $empty = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'timezone' => 'UTC']);

        Sanctum::actingAs($this->manager($empty));

        $response = $this->getJson('/api/v1/accounting/dashboard?'.http_build_query($this->periodQuery()))->assertOk();

        $response->assertJsonPath('data.invoices.count', 0);
        $response->assertJsonPath('data.collections.count', 0);

        $outstanding = $response->json('data.outstanding.list');
        $this->assertIsArray($outstanding);
        $this->assertSame([], $outstanding);

        // Export d'un tenant vide : entête seule, pas de crash.
        $csv = (string) $this->get('/api/v1/accounting/dashboard/export')->getContent();
        $this->assertStringContainsString('number,contact,issue_date', $csv);
    }

    public function test_rbac_blocks_non_manager(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->pilot->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/accounting/dashboard')->assertForbidden();
        $this->getJson('/api/v1/accounting/dashboard/export')->assertForbidden();
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'comptable',
            'status' => 'active',
        ]);

        return $manager;
    }
}
