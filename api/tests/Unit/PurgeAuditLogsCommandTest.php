<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * audit:purge — rétention RGPD des audit logs (issue #1474).
 * La matrice de conformité référençait cette commande sans qu'elle existe.
 */
class PurgeAuditLogsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The Unit suite runs on a fresh in-memory DB with no tenant schema —
        // create the minimal audit_logs table if it does not exist.
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function ($table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('action');
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        DB::table('audit_logs')->delete();
        parent::tearDown();
    }

    private function createAuditLog(int $monthsAgo, ?int $companyId = null): void
    {
        DB::table('audit_logs')->insert([
            'company_id' => $companyId,
            'action' => 'test.purge',
            'created_at' => now()->subMonths($monthsAgo)->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_purges_only_logs_older_than_retention_window(): void
    {
        $this->createAuditLog(30);   // 30 mois → à purger
        $this->createAuditLog(25);   // 25 mois → à purger
        $this->createAuditLog(10);   // 10 mois → conservé
        $this->createAuditLog(2);    // 2 mois → conservé

        $this->artisan('audit:purge')
            ->expectsOutputToContain('2')
            ->assertSuccessful();

        $this->assertSame(2, DB::table('audit_logs')->count());
    }

    public function test_older_than_option_overrides_default(): void
    {
        $this->createAuditLog(13);   // 13 mois → à purger avec --older-than=12
        $this->createAuditLog(6);    // 6 mois → conservé

        $this->artisan('audit:purge', ['--older-than' => 12])
            ->expectsOutputToContain('1')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('audit_logs')->count());
    }

    public function test_noop_when_nothing_is_expired(): void
    {
        $this->createAuditLog(1);

        $this->artisan('audit:purge')
            ->expectsOutputToContain('0')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('audit_logs')->count());
    }
}
