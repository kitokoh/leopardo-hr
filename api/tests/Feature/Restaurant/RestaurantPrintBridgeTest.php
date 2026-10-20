<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Enums\PrintJobStatus;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * REST-OS-3 (Mission Leopardo Restaurant OS) — Surface du BRIDGE d'impression
 * LOCAL (poste cuisine).
 *
 * Couvre : authentification par jeton partagé fail-closed (désactivé → 403,
 * jeton invalide → 401), file de récupération, réclamation atomique (409 au
 * second claim), accusé d'impression réelle, échec (retour en file), et
 * isolation cross-tenant (404).
 */
class RestaurantPrintBridgeTest extends TestCase
{
    use RefreshTenantDatabase;

    private const TOKEN = 'bridge-test-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'restaurantmanager.printing.bridge_enabled' => true,
            'restaurantmanager.printing.bridge_token_hash' => hash('sha256', self::TOKEN),
        ]);
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    /**
     * @return array<string, string>
     */
    private function headers(Company $company, ?string $token = self::TOKEN): array
    {
        return [
            'X-Restaurant-Print-Bridge-Token' => $token ?? '',
            'X-Company-Id' => $company->id,
        ];
    }

    /**
     * @return array{0: RestaurantBranch, 1: RestaurantPrintJob}
     */
    private function job(Company $company, PrintJobStatus $status = PrintJobStatus::PENDING): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $status): array {
            $branch = RestaurantBranch::factory()->create();
            $taxRate = RestaurantTaxRate::factory()->create(['rate_bps' => 0]);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1000,
                'currency' => $branch->currency,
                'tax_rate_id' => $taxRate->id,
                'is_available' => true,
            ]);

            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'status' => 'in_preparation',
                'currency' => $branch->currency,
            ]);

            RestaurantOrderItem::query()->create([
                'company_id' => $company->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price_minor' => 1000,
                'line_total_minor' => 1000,
                'tax_minor' => 0,
                'status' => 'active',
                'line_index' => 1,
            ]);

            $job = RestaurantPrintJob::factory()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'order_id' => $order->id,
                'ticket_kind' => PrintTicketKind::KITCHEN->value,
                'status' => $status->value,
            ]);

            return [$branch, $job];
        });
    }

    public function test_health_is_reachable_with_a_valid_token(): void
    {
        $company = $this->company();

        $this->getJson('/api/v1/restaurant/printing/bridge/health', $this->headers($company))
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok');
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $company = $this->company();

        $this->getJson('/api/v1/restaurant/printing/bridge/health', $this->headers($company, 'wrong-token'))
            ->assertStatus(401);
    }

    public function test_the_bridge_is_forbidden_when_disabled(): void
    {
        config(['restaurantmanager.printing.bridge_enabled' => false]);
        $company = $this->company();

        $this->getJson('/api/v1/restaurant/printing/bridge/health', $this->headers($company))
            ->assertStatus(403);
    }

    public function test_jobs_lists_claimable_jobs_for_the_branch(): void
    {
        $company = $this->company();
        [$branch] = $this->job($company);

        $this->getJson(
            "/api/v1/restaurant/printing/bridge/jobs?branch_id={$branch->id}",
            $this->headers($company),
        )
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_claim_returns_the_escpos_payload_and_marks_the_job_claimed(): void
    {
        $company = $this->company();
        [, $job] = $this->job($company);

        $this->postJson("/api/v1/restaurant/printing/bridge/jobs/{$job->id}/claim", [], $this->headers($company))
            ->assertStatus(200)
            ->assertJsonPath('job.status', 'claimed')
            ->assertJsonPath('encoding', 'escpos')
            ->assertJsonStructure(['payload_base64', 'payload_sha256']);

        $this->assertSame(PrintJobStatus::CLAIMED, $job->refresh()->status);
    }

    public function test_a_second_claim_is_rejected(): void
    {
        $company = $this->company();
        [, $job] = $this->job($company);

        $this->postJson("/api/v1/restaurant/printing/bridge/jobs/{$job->id}/claim", [], $this->headers($company))
            ->assertStatus(200);

        $this->postJson("/api/v1/restaurant/printing/bridge/jobs/{$job->id}/claim", [], $this->headers($company))
            ->assertStatus(409);
    }

    public function test_ack_marks_the_job_printed_only_on_explicit_acknowledgement(): void
    {
        $company = $this->company();
        [, $job] = $this->job($company);

        $this->postJson("/api/v1/restaurant/printing/bridge/jobs/{$job->id}/claim", [], $this->headers($company))
            ->assertStatus(200)
            ->assertJsonPath('job.status', 'claimed');

        // L'impression n'est PAS réputée réussie par la simple émission.
        $this->assertSame(PrintJobStatus::CLAIMED, $job->refresh()->status);

        $this->postJson("/api/v1/restaurant/printing/bridge/jobs/{$job->id}/ack", [], $this->headers($company))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'printed');

        $this->assertNotNull($job->refresh()->printed_at);
    }

    public function test_ack_is_idempotent(): void
    {
        $company = $this->company();
        [, $job] = $this->job($company);

        $this->postJson("/api/v1/restaurant/printing/bridge/jobs/{$job->id}/ack", [], $this->headers($company))
            ->assertStatus(200);
        $this->postJson("/api/v1/restaurant/printing/bridge/jobs/{$job->id}/ack", [], $this->headers($company))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'printed');
    }

    public function test_fail_returns_the_job_to_the_queue_with_the_reason(): void
    {
        $company = $this->company();
        [, $job] = $this->job($company);

        $this->postJson(
            "/api/v1/restaurant/printing/bridge/jobs/{$job->id}/fail",
            ['error' => 'paper out'],
            $this->headers($company),
        )
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.last_error', 'paper out');
    }

    public function test_a_job_from_another_tenant_is_not_found(): void
    {
        $company = $this->company();
        $foreign = $this->company();
        [, $foreignJob] = $this->job($foreign);

        $this->postJson("/api/v1/restaurant/printing/bridge/jobs/{$foreignJob->id}/claim", [], $this->headers($company))
            ->assertStatus(404);
    }
}
