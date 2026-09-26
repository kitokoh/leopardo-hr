<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-002 (#8144) — rétention des logs d'audit IA.
 *
 * `ai_audit_logs` stocke prompt et réponse en clair : la purge doit supprimer
 * UNIQUEMENT les lignes plus vieilles que la rétention configurée (défaut
 * 90 j), être idempotente, respectueuse du périmètre `--company` et sans
 * effet en `--dry-run`.
 */
class PurgeAiAuditLogsTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_purges_only_rows_older_than_the_configured_retention(): void
    {
        [$company, $employee] = $this->fixture();
        $recent = $this->insertLog($company->id, $employee->id, now()->subDay());
        $old = $this->insertLog($company->id, $employee->id, now()->subDays(91));

        $this->assertSame(0, Artisan::call('ai:purge-audit-logs'));

        $this->assertTrue($this->exists($recent), 'la ligne récente (J-1) doit être conservée');
        $this->assertFalse($this->exists($old), 'la ligne au-delà de la rétention (J-91) doit être purgée');
    }

    public function test_uses_the_configured_retention_by_default(): void
    {
        config(['ai.audit_log_retention_days' => 30]);
        [$company, $employee] = $this->fixture();
        $kept = $this->insertLog($company->id, $employee->id, now()->subDays(29));
        $purged = $this->insertLog($company->id, $employee->id, now()->subDays(31));

        $this->assertSame(0, Artisan::call('ai:purge-audit-logs'));

        $this->assertTrue($this->exists($kept), 'à J-29 pour une rétention de 30 j : conservée');
        $this->assertFalse($this->exists($purged), 'à J-31 pour une rétention de 30 j : purgée');
    }

    public function test_older_than_option_overrides_the_config(): void
    {
        config(['ai.audit_log_retention_days' => 90]);
        [$company, $employee] = $this->fixture();
        $row = $this->insertLog($company->id, $employee->id, now()->subDays(15));

        $this->assertSame(0, Artisan::call('ai:purge-audit-logs', ['--older-than' => 10]));

        $this->assertFalse($this->exists($row), '--older-than=10 doit purger une ligne de 15 jours');
    }

    public function test_dry_run_deletes_nothing(): void
    {
        [$company, $employee] = $this->fixture();
        $old = $this->insertLog($company->id, $employee->id, now()->subDays(120));

        $this->assertSame(0, Artisan::call('ai:purge-audit-logs', ['--dry-run' => true]));

        $this->assertTrue($this->exists($old), '--dry-run ne supprime rien');
    }

    public function test_company_option_is_scoped(): void
    {
        [$companyA, $employeeA] = $this->fixture();
        [$companyB, $employeeB] = $this->fixture();

        $rowA = $this->insertLog($companyA->id, $employeeA->id, now()->subDays(120));
        $rowB = $this->insertLog($companyB->id, $employeeB->id, now()->subDays(120));

        $this->assertSame(0, Artisan::call('ai:purge-audit-logs', ['--company' => (string) $companyA->id]));

        $this->assertFalse($this->exists($rowA));
        $this->assertTrue($this->exists($rowB), 'la société non ciblée est intacte');
    }

    public function test_second_run_is_idempotent(): void
    {
        [$company, $employee] = $this->fixture();
        $this->insertLog($company->id, $employee->id, now()->subDays(120));
        $this->insertToolExecution($company->id, $employee->id, now()->subDays(120));

        $this->assertSame(0, Artisan::call('ai:purge-audit-logs'));
        $this->assertSame(0, Artisan::call('ai:purge-audit-logs'));

        $this->assertSame(0, (int) DB::table('ai_audit_logs')->count());
        $this->assertSame(0, (int) DB::table('ai_tool_executions')->count());
    }

    /**
     * #8164 — `ai_tool_executions` (`tool_input` sanitizé mais `result_summary`
     * /`error` potentiellement porteurs de PII) relève de la MÊME rétention :
     * la purge doit couvrir les deux tables avec le même seuil.
     */
    public function test_purges_tool_executions_older_than_the_retention(): void
    {
        [$company, $employee] = $this->fixture();
        $recent = $this->insertToolExecution($company->id, $employee->id, now()->subDay());
        $old = $this->insertToolExecution($company->id, $employee->id, now()->subDays(91));

        $this->assertSame(0, Artisan::call('ai:purge-audit-logs'));

        $this->assertTrue($this->toolExecutionExists($recent), 'l\'exécution récente (J-1) doit être conservée');
        $this->assertFalse($this->toolExecutionExists($old), 'l\'exécution au-delà de la rétention (J-91) doit être purgée');
    }

    public function test_company_option_scopes_tool_executions_too(): void
    {
        [$companyA, $employeeA] = $this->fixture();
        [$companyB, $employeeB] = $this->fixture();

        $rowA = $this->insertToolExecution($companyA->id, $employeeA->id, now()->subDays(120));
        $rowB = $this->insertToolExecution($companyB->id, $employeeB->id, now()->subDays(120));

        $this->assertSame(0, Artisan::call('ai:purge-audit-logs', ['--company' => (string) $companyA->id]));

        $this->assertFalse($this->toolExecutionExists($rowA));
        $this->assertTrue($this->toolExecutionExists($rowB), 'les exécutions de la société non ciblée sont intactes');
    }

    public function test_dry_run_deletes_no_tool_execution(): void
    {
        [$company, $employee] = $this->fixture();
        $old = $this->insertToolExecution($company->id, $employee->id, now()->subDays(120));

        $this->assertSame(0, Artisan::call('ai:purge-audit-logs', ['--dry-run' => true]));

        $this->assertTrue($this->toolExecutionExists($old), '--dry-run ne supprime aucune exécution d\'outil');
    }

    public function test_invalid_retention_option_fails(): void
    {
        $this->assertSame(1, Artisan::call('ai:purge-audit-logs', ['--older-than' => 'abc']));
        $this->assertSame(1, Artisan::call('ai:purge-audit-logs', ['--older-than' => 0]));
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function fixture(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        return [$company, $employee];
    }

    private function insertLog(string $companyId, int $employeeId, \DateTimeInterface $createdAt): int
    {
        return (int) DB::table('ai_audit_logs')->insertGetId([
            'company_id' => $companyId,
            'user_id' => $employeeId,
            'conversation_id' => null,
            'prompt' => 'question',
            'response' => 'reponse',
            'tools_called' => json_encode([]),
            'provider' => 'fake',
            'model' => 'test-model',
            'input_tokens' => 1,
            'output_tokens' => 1,
            'cost_cents' => 0,
            'duration_ms' => 1,
            'error' => null,
            'created_at' => $createdAt->format('Y-m-d H:i:sP'),
        ]);
    }

    private function exists(int $id): bool
    {
        return DB::table('ai_audit_logs')->where('id', $id)->exists();
    }

    private function insertToolExecution(string $companyId, int $employeeId, \DateTimeInterface $createdAt): int
    {
        return (int) DB::table('ai_tool_executions')->insertGetId([
            'company_id' => $companyId,
            'user_id' => $employeeId,
            'conversation_id' => null,
            'pending_action_id' => null,
            'tool_name' => 'list_employees',
            'tool_input' => json_encode(['query' => 'dupont']),
            'stage' => 'executed',
            'success' => true,
            'result_summary' => '1 employé trouvé : dupont@example.test',
            'error' => null,
            'source' => 'assistant',
            'created_at' => $createdAt->format('Y-m-d H:i:sP'),
        ]);
    }

    private function toolExecutionExists(int $id): bool
    {
        return DB::table('ai_tool_executions')->where('id', $id)->exists();
    }
}
