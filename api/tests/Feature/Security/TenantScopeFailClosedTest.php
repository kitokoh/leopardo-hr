<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Exceptions\MissingTenantContextException;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #3727 (audit 360° A-04) — le scope global `BelongsToCompany` sautait
 * en silence sans `current_company` : une requête HTTP sans tenant résolu
 * lisait TOUTES les compagnies (fail-open). Ce test verrouille le fail-closed :
 *   - config `tenant.fail_closed_scope` true (défaut HTTP) → exception 403 ;
 *   - config false → ancien comportement conservé + warning journalisé ;
 *   - les requêtes avec tenant lié restent filtrées par company_id.
 */
class TenantScopeFailClosedTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        if (! \Illuminate\Support\Facades\Schema::hasTable($this->moduleTable('webhook_endpoints'))) {
            \Illuminate\Support\Facades\Schema::create($this->moduleTable('webhook_endpoints'), function ($table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('url');
                $table->json('events')->nullable();
                $table->string('secret')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TABLE IF EXISTS webhook_endpoints CASCADE');
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function moduleTable(string $table): string
    {
        return $table;
    }

    public function test_scope_throws_when_fail_closed_and_no_company_bound(): void
    {
        config(['tenant.fail_closed_scope' => true]);
        $this->assertFalse(app()->bound('current_company'));

        $this->expectException(MissingTenantContextException::class);

        WebhookEndpoint::query()->get();
    }

    public function test_scope_skips_with_warning_when_tolerant_and_no_company_bound(): void
    {
        config(['tenant.fail_closed_scope' => false]);
        Log::spy();

        $rows = WebhookEndpoint::query()->get();

        $this->assertCount(0, $rows);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message): bool => str_contains($message, 'Tenant scope contourné'));
    }

    public function test_scope_filters_by_company_when_tenant_bound(): void
    {
        config(['tenant.fail_closed_scope' => true]);

        DB::table('webhook_endpoints')->insert([
            ['company_id' => '11111111-1111-1111-1111-111111111111', 'url' => 'https://a.example.com/hook', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => '22222222-2222-2222-2222-222222222222', 'url' => 'https://b.example.com/hook', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $company = new \App\Core\Tenant\Domain\Models\Company();
        $company->setAttribute('id', '11111111-1111-1111-1111-111111111111');
        $company->setAttribute('name', 'Tenant A');
        $company->exists = true;
        app()->instance('current_company', $company);

        try {
            $rows = WebhookEndpoint::query()->get();
        } finally {
            app()->forgetInstance('current_company');
        }

        $this->assertCount(1, $rows);
        $this->assertSame('11111111-1111-1111-1111-111111111111', (string) $rows->first()->company_id);
    }
}
