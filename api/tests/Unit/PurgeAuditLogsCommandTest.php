<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

use App\Core\Tenant\Domain\Models\Company;

/**
 * audit:purge — rétention RGPD des audit logs (issue #1474).
 * La matrice de conformité référençait cette commande sans qu'elle existe.
 */
class PurgeAuditLogsCommandTest extends TestCase
{
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // The Unit suite may run on a fresh DB without tenant migrations.
        // Mirror the canonical schema: immutable audit logs only have created_at
        // (no updated_at — AuditLog::$timestamps = false, see the migration
        // 2026_05_10_000001_create_audit_logs_table.php) + les colonnes
        // additives #5439 (module, request_id) écrites par AuditLog::record().
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->nullable()->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('action', 30);
                $table->string('module', 100)->nullable()->index();
                $table->string('request_id', 64)->nullable()->index();
                $table->string('auditable_type', 100)->nullable();
                $table->unsignedBigInteger('auditable_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // La commande purge TENANT PAR TENANT (TenantManager::withinTenant,
        // pattern biometric:purge-expired) : sans société en base, le foreach
        // ne traite rien et la purge ne s'exécute pas. Une société par défaut
        // (schéma shared_tenants) suffit — les lignes d'audit insérées dans le
        // schéma ambiant (shared_tenants) sont celles visées par withinTenant.
        $this->company = Company::factory()->create(['country' => 'DZ']);
    }

    protected function tearDown(): void
    {
        DB::table('audit_logs')->delete();
        parent::tearDown();
    }

    private function createAuditLog(int $monthsAgo, ?int $companyId = null): void
    {
        // The audit_logs table is immutable (AuditLog::$timestamps = false) and
        // only has `created_at` — no `updated_at` column. Inserting updated_at
        // causes SQLSTATE[42703] on the real tenant schema.
        DB::table('audit_logs')->insert([
            'company_id' => $companyId,
            'action' => 'test.purge',
            'created_at' => now()->subMonths($monthsAgo)->toDateTimeString(),
        ]);
    }

    public function test_purges_old_logs(): void
    {
        // Rétention par défaut #5439 = 36 mois : seuls les logs > 36 mois
        // sont purgés.
        $this->createAuditLog(40);   // 40 mois → à purger
        $this->createAuditLog(38);   // 38 mois → à purger
        $this->createAuditLog(10);   // 10 mois → conservé
        $this->createAuditLog(2);    // 2 mois → conservé

        /** @var PendingCommand $cmd */
        $cmd = $this->artisan('audit:purge');
        $cmd->expectsOutputToContain('2');
        $cmd->assertSuccessful();
        // PendingCommand exécute la commande paresseusement (__destruct) :
        // sans run() explicite, les assertions DB ci-dessous tournent AVANT
        // la commande et le test échoue à tort (CI rouge 2026-08-09).
        $cmd->run();

        // 2 logs métier conservés (10 et 2 mois) + 1 trace RGPD audit.purge.
        $this->assertSame(2, DB::table('audit_logs')->where('action', '!=', 'audit.purge')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'audit.purge')->count());
    }

    public function test_older_than_option(): void
    {
        $this->createAuditLog(13);   // 13 mois → à purger avec --older-than=12
        $this->createAuditLog(6);    // 6 mois → conservé

        /** @var PendingCommand $cmd */
        $cmd = $this->artisan('audit:purge', ['--older-than' => 12]);
        $cmd->expectsOutputToContain('1');
        $cmd->assertSuccessful();
        $cmd->run(); // exécution explicite (cf. test_purges_old_logs)

        // 1 log métier conservé (6 mois) + 1 trace RGPD audit.purge.
        $this->assertSame(1, DB::table('audit_logs')->where('action', '!=', 'audit.purge')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'audit.purge')->count());
    }

    public function test_noop_when_nothing_is_expired(): void
    {
        $this->createAuditLog(1);

        /** @var PendingCommand $cmd */
        $cmd = $this->artisan('audit:purge');
        $cmd->expectsOutputToContain('0');
        $cmd->assertSuccessful();
        $cmd->run(); // exécution immédiate avant assertions DB (PendingCommand est lazy, cf. convention A-1)

        // Aucune suppression → aucune trace audit.purge parasite.
        $this->assertSame(1, DB::table('audit_logs')->count());
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'audit.purge')->count());
    }
}
