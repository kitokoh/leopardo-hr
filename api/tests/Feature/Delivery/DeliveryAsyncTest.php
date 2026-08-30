<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Delivery\Application\Jobs\CloseDeliveryRouteJob;
use App\Modules\Delivery\Application\Jobs\ExportDeliveryReportJob;
use App\Modules\Delivery\Application\Services\DeliveryReportService;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryDeadLetter;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use App\Modules\Delivery\Domain\Models\DeliveryStop;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-26-D07 (#6295) — Asynchronisme du module Delivery.
 *
 * Jobs tenant-scoped (pattern GenerateBankExportJob) : clôture de tournée
 * asynchrone idempotente, export JSON déterministe, retry borné, DLQ
 * `delivery_dead_letters` + rejeu `delivery:replay-dlq` sans doublon,
 * contexte tenant restauré en fin de job (contrat BC-02).
 */
class DeliveryAsyncTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $company->setFeature('delivery', true);
        $company->save();
        $this->company = $company;
    }

    private function createDelivery(int $codMinor = 0): Delivery
    {
        /** @var Delivery $delivery */
        $delivery = Delivery::query()->create([
            'company_id' => $this->company->id,
            'reference' => 'DLV-2026-'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'source' => 'manual',
            'type' => 'parcel',
            'status' => 'delivered',
            'cod_amount_minor' => $codMinor,
            'dropoff_contact' => 'Client',
            'dropoff_address' => 'Alger',
        ]);

        return $delivery;
    }

    private function createClosedRoute(?string $status = 'assigned'): DeliveryRoute
    {
        /** @var DeliveryRoute $route */
        $route = DeliveryRoute::query()->create([
            'company_id' => $this->company->id,
            'route_date' => now()->toDateString(),
            'driver_id' => null,
            'status' => $status,
        ]);

        foreach ([1000, 2000, 0] as $i => $cod) {
            $delivery = $this->createDelivery($cod);
            DeliveryStop::query()->create([
                'company_id' => $this->company->id,
                'route_id' => $route->id,
                'delivery_id' => $delivery->id,
                'sort_order' => $i + 1,
                'status' => 'delivered',
                'address' => $delivery->dropoff_address,
            ]);
        }

        return $route;
    }

    // ── Clôture asynchrone idempotente ─────────────────────────────────────

    public function test_close_job_is_idempotent_and_deterministic(): void
    {
        $route = $this->createClosedRoute();

        CloseDeliveryRouteJob::dispatchSync($route->id, $this->company->id);
        $first = $route->fresh();

        // Deuxième exécution : même résultat (clôture idempotente, 2 recalculs
        // → mêmes totaux — exigence sortie BC-26).
        CloseDeliveryRouteJob::dispatchSync($route->id, $this->company->id);
        $second = $route->fresh();

        self::assertSame('completed', $second->status);
        self::assertSame($first->deliveries_count, $second->deliveries_count);
        self::assertSame($first->delivered_count, $second->delivered_count);
        self::assertSame(3, $second->deliveries_count);
        self::assertSame(3, $second->delivered_count);
        // COD attendu = somme des COD des livraisons livrées.
        self::assertSame(3000, $second->cod_collected_minor);
        self::assertNotNull($second->closed_at);
    }

    public function test_close_job_restores_tenant_context(): void
    {
        $route = $this->createClosedRoute();

        /** @var TenantManager $tenants */
        $tenants = app(TenantManager::class);
        self::assertFalse($tenants->hasTenant());

        CloseDeliveryRouteJob::dispatchSync($route->id, $this->company->id);

        // Contexte restauré en fin de job (contrat BC-02, finally).
        self::assertFalse($tenants->hasTenant());
    }

    public function test_close_job_isolation_between_tenants(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $other->setFeature('delivery', true);
        $other->save();

        $routeA = $this->createClosedRoute();
        $routeB = DeliveryRoute::query()->create([
            'company_id' => $other->id,
            'route_date' => now()->toDateString(),
            'status' => 'assigned',
        ]);

        CloseDeliveryRouteJob::dispatchSync($routeA->id, $this->company->id);

        // Tenant B : intouché.
        self::assertSame('assigned', $routeB->fresh()->status);
        self::assertSame('completed', $routeA->fresh()->status);
    }

    // ── DLQ : échec → dead letter → rejeu ─────────────────────────────────

    public function test_close_job_records_dead_letter_on_failure(): void
    {
        $job = new CloseDeliveryRouteJob(999999, $this->company->id);

        $job->failed(new \RuntimeException('Route introuvable'));

        self::assertDatabaseHas('delivery_dead_letters', [
            'company_id' => $this->company->id,
            'job_class' => CloseDeliveryRouteJob::class,
            'status' => 'new',
        ]);
    }

    public function test_export_job_writes_deterministic_snapshot(): void
    {
        Storage::fake('local');

        $this->createDelivery(500);

        $job = new ExportDeliveryReportJob(
            $this->company->id,
            now()->subDays(30)->format('Y-m-d'),
            now()->format('Y-m-d'),
            'run-1',
        );

        $job->handle(app(DeliveryReportService::class));

        $filePath = sprintf(
            'delivery_reports/%s/%s_%s_run-1.json',
            $this->company->id,
            now()->subDays(30)->format('Y-m-d'),
            now()->format('Y-m-d'),
        );

        Storage::disk('local')->assertExists($filePath);
        $first = Storage::disk('local')->get($filePath);

        // Rejeu avec la même runKey : même contenu (déterministe, zéro doublon).
        $job->handle(app(DeliveryReportService::class));
        $second = Storage::disk('local')->get($filePath);

        self::assertSame($first, $second);
        self::assertStringContainsString('"success_rate_pct"', $first);
    }

    public function test_replay_dlq_redispatches_original_jobs_without_duplication(): void
    {
        Queue::fake();

        $route = $this->createClosedRoute();

        DeliveryDeadLetter::query()->create([
            'company_id' => $this->company->id,
            'job_class' => CloseDeliveryRouteJob::class,
            'payload' => ['route_id' => $route->id, 'company_id' => $this->company->id],
            'queue' => 'delivery',
            'error' => 'boom',
            'attempts' => 3,
            'status' => 'new',
        ]);
        DeliveryDeadLetter::query()->create([
            'company_id' => $this->company->id,
            'job_class' => ExportDeliveryReportJob::class,
            'payload' => ['company_id' => $this->company->id, 'from' => '2026-08-01', 'to' => '2026-08-30', 'run_key' => 'r2'],
            'queue' => 'delivery',
            'error' => 'boom',
            'attempts' => 3,
            'status' => 'new',
        ]);

        $this->artisan('delivery:replay-dlq')->assertExitCode(0);

        Queue::assertPushed(CloseDeliveryRouteJob::class);
        Queue::assertPushed(ExportDeliveryReportJob::class);

        self::assertSame(0, DeliveryDeadLetter::query()->where('status', 'new')->count());
        self::assertSame(2, DeliveryDeadLetter::query()->where('status', 'replayed')->count());
    }

    public function test_replay_dlq_marks_unknown_job_failed(): void
    {
        Queue::fake();

        DeliveryDeadLetter::query()->create([
            'company_id' => $this->company->id,
            'job_class' => 'App\\Unknown\\Job',
            'payload' => [],
            'queue' => 'delivery',
            'error' => 'boom',
            'attempts' => 3,
            'status' => 'new',
        ]);

        $this->artisan('delivery:replay-dlq')->assertExitCode(1);

        self::assertSame(1, DeliveryDeadLetter::query()->where('status', 'failed')->count());
    }

    // ── Dispatch par les commandes console ────────────────────────────────

    public function test_console_commands_dispatch_jobs(): void
    {
        Queue::fake();

        $route = $this->createClosedRoute();

        $this->artisan('delivery:close-route', ['route' => $route->id, 'company' => $this->company->id])
            ->assertExitCode(0);
        Queue::assertPushed(CloseDeliveryRouteJob::class);

        $this->artisan('delivery:export-report', ['company' => $this->company->id])
            ->assertExitCode(0);
        Queue::assertPushed(ExportDeliveryReportJob::class);
    }
}
