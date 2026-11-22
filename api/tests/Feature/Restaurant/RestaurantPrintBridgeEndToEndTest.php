<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
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
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * REST-OS-6 — Parcours COMPLET du bridge d'impression, de la mise en file
 * (côté application, authentifié Sanctum) jusqu'à l'accusé d'impression
 * (côté poste cuisine, authentifié par jeton partagé).
 *
 * `RestaurantPrintBridgeTest` couvre la surface du bridge prise isolément, en
 * fabriquant directement des travaux en base. Ce test couvre la COUTURE entre
 * les deux surfaces — celle qui casse en production :
 *
 *   POST /restaurant/orders/{order}/print-jobs   (serveur, Sanctum)
 *     → GET  /restaurant/printing/bridge/jobs    (bridge, jeton)
 *     → POST .../claim                           (bridge, jeton)
 *     → GET  .../payload                         (bridge, jeton)
 *     → POST .../ack                             (bridge, jeton)
 *     → POST /restaurant/print-jobs/{job}/requeue (serveur, Sanctum)
 *     → GET  .../jobs                            (le bridge voit la révision 2)
 *
 * Ce qu'il verrouille :
 *  - le travail mis en file par l'application est bien CELUI que le bridge
 *    récupère (même tenant, même succursale, même commande) ;
 *  - le flux ESC/POS rendu par le bridge est identique à celui dont le hash a
 *    été calculé à la mise en file (`payload_sha256` == `payload_hash`) ;
 *  - l'impression n'est jamais réputée réussie sans accusé explicite ;
 *  - une réimpression crée une NOUVELLE révision, jamais un rejeu ;
 *  - un employé non gestionnaire ne peut pas mettre en file (403) ;
 *  - un travail d'un autre tenant est invisible (404) des deux côtés.
 */
class RestaurantPrintBridgeEndToEndTest extends TestCase
{
    use RefreshTenantDatabase;

    private const TOKEN = 'bridge-e2e-token';

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

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    /**
     * @return array<string, string>
     */
    private function bridgeHeaders(Company $company, ?string $token = self::TOKEN): array
    {
        return [
            'X-Restaurant-Print-Bridge-Token' => $token ?? '',
            'X-Company-Id' => (string) $company->id,
        ];
    }

    /**
     * Commande servable : une succursale, un produit taxé, une ligne.
     *
     * @return array{0: RestaurantBranch, 1: RestaurantOrder}
     */
    private function order(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            $branch = RestaurantBranch::factory()->create();
            $taxRate = RestaurantTaxRate::factory()->create(['rate_bps' => 0]);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 2500,
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
                'quantity' => 2,
                'unit_price_minor' => 2500,
                'line_total_minor' => 5000,
                'tax_minor' => 0,
                'status' => 'active',
                'line_index' => 1,
            ]);

            return [$branch, $order];
        });
    }

    public function test_the_full_print_cycle_from_enqueue_to_acknowledgement(): void
    {
        $company = $this->company();
        $this->principal($company);
        [$branch, $order] = $this->order($company);

        // 1. L'application met le ticket cuisine en file.
        $enqueued = $this->postJson("/api/v1/restaurant/orders/{$order->id}/print-jobs")
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.revision', 1)
            ->assertJsonPath('data.ticket_kind', PrintTicketKind::KITCHEN->value);

        $jobId = (int) $enqueued->json('data.id');
        $this->assertGreaterThan(0, $jobId);

        // 2. Le bridge voit exactement ce travail dans la file de la succursale.
        $this->getJson(
            "/api/v1/restaurant/printing/bridge/jobs?branch_id={$branch->id}",
            $this->bridgeHeaders($company),
        )
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $jobId)
            ->assertJsonPath('data.0.order_id', $order->id);

        // 3. Réclamation : le bridge reçoit le flux ESC/POS.
        $claim = $this->postJson(
            "/api/v1/restaurant/printing/bridge/jobs/{$jobId}/claim",
            ['printer_ref' => 'kitchen-01'],
            $this->bridgeHeaders($company),
        )
            ->assertStatus(200)
            ->assertJsonPath('job.status', 'claimed')
            ->assertJsonPath('encoding', 'escpos')
            ->assertJsonStructure(['payload_base64', 'payload_sha256']);

        $payload = base64_decode((string) $claim->json('payload_base64'), true);
        $this->assertIsString($payload);
        $this->assertNotSame('', $payload, 'Le flux ESC/POS ne doit pas être vide.');

        // Le hash annoncé par le bridge est celui calculé à la mise en file :
        // c'est ce qui garantit que le papier sort avec le bon contenu.
        $job = RestaurantPrintJob::query()->findOrFail($jobId);
        $this->assertSame($job->payload_hash, $claim->json('payload_sha256'));
        $this->assertSame(hash('sha256', $payload), $claim->json('payload_sha256'));

        // 4. Le flux reste re-lisible sans re-réclamer (redémarrage du bridge).
        $this->getJson(
            "/api/v1/restaurant/printing/bridge/jobs/{$jobId}/payload",
            $this->bridgeHeaders($company),
        )
            ->assertStatus(200)
            ->assertJsonPath('payload_sha256', $claim->json('payload_sha256'));

        // 5. Aucune impression n'est réputée réussie sans accusé explicite.
        $this->assertSame(PrintJobStatus::CLAIMED, $job->refresh()->status);
        $this->assertNull($job->printed_at);

        $this->postJson(
            "/api/v1/restaurant/printing/bridge/jobs/{$jobId}/ack",
            ['printer_ref' => 'kitchen-01'],
            $this->bridgeHeaders($company),
        )
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'printed');

        $job->refresh();
        $this->assertSame(PrintJobStatus::PRINTED, $job->status);
        $this->assertNotNull($job->printed_at);

        // 6. Un travail imprimé sort de la file de récupération.
        $this->getJson(
            "/api/v1/restaurant/printing/bridge/jobs?branch_id={$branch->id}",
            $this->bridgeHeaders($company),
        )
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_a_requeue_creates_a_new_revision_the_bridge_can_pick_up(): void
    {
        $company = $this->company();
        $this->principal($company);
        [$branch, $order] = $this->order($company);

        $jobId = (int) $this->postJson("/api/v1/restaurant/orders/{$order->id}/print-jobs")
            ->assertStatus(201)
            ->json('data.id');

        $this->postJson(
            "/api/v1/restaurant/printing/bridge/jobs/{$jobId}/claim",
            [],
            $this->bridgeHeaders($company),
        )->assertStatus(200);

        $this->postJson(
            "/api/v1/restaurant/printing/bridge/jobs/{$jobId}/ack",
            [],
            $this->bridgeHeaders($company),
        )->assertStatus(200);

        // Réimpression explicite : nouvelle révision, pas un rejeu du travail.
        $requeued = $this->postJson("/api/v1/restaurant/print-jobs/{$jobId}/requeue")
            ->assertStatus(201)
            ->assertJsonPath('data.revision', 2)
            ->assertJsonPath('data.status', 'pending');

        $newJobId = (int) $requeued->json('data.id');
        $this->assertNotSame($jobId, $newJobId);

        // Le bridge ne voit QUE la nouvelle révision : l'ancienne est imprimée.
        $this->getJson(
            "/api/v1/restaurant/printing/bridge/jobs?branch_id={$branch->id}",
            $this->bridgeHeaders($company),
        )
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newJobId)
            ->assertJsonPath('data.0.revision', 2);
    }

    public function test_enqueue_is_idempotent_for_the_same_order(): void
    {
        $company = $this->company();
        $this->principal($company);
        [, $order] = $this->order($company);

        $first = $this->postJson("/api/v1/restaurant/orders/{$order->id}/print-jobs")
            ->assertStatus(201)
            ->json('data.id');

        // Double clic serveur / retry réseau : même travail, pas de doublon.
        $second = $this->postJson("/api/v1/restaurant/orders/{$order->id}/print-jobs")
            ->assertStatus(201)
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, RestaurantPrintJob::query()->where('order_id', $order->id)->count());
    }

    public function test_an_ordinary_employee_cannot_enqueue_a_print_job(): void
    {
        $company = $this->company();
        $this->ordinaryEmployee($company);
        [, $order] = $this->order($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/print-jobs")
            ->assertStatus(403);

        $this->assertSame(0, RestaurantPrintJob::query()->where('order_id', $order->id)->count());
    }

    public function test_the_bridge_cannot_claim_a_job_enqueued_by_another_tenant(): void
    {
        $company = $this->company();
        $this->principal($company);
        [, $order] = $this->order($company);

        $jobId = (int) $this->postJson("/api/v1/restaurant/orders/{$order->id}/print-jobs")
            ->assertStatus(201)
            ->json('data.id');

        $foreign = $this->company();

        // Le jeton est valide, mais le travail appartient à un autre tenant.
        $this->postJson(
            "/api/v1/restaurant/printing/bridge/jobs/{$jobId}/claim",
            [],
            $this->bridgeHeaders($foreign),
        )->assertStatus(404);

        $this->assertSame(PrintJobStatus::PENDING, RestaurantPrintJob::query()->findOrFail($jobId)->status);
    }

    public function test_the_bridge_cannot_ack_a_job_of_another_tenant(): void
    {
        $company = $this->company();
        $this->principal($company);
        [, $order] = $this->order($company);

        $jobId = (int) $this->postJson("/api/v1/restaurant/orders/{$order->id}/print-jobs")
            ->assertStatus(201)
            ->json('data.id');

        $foreign = $this->company();

        $this->postJson(
            "/api/v1/restaurant/printing/bridge/jobs/{$jobId}/ack",
            [],
            $this->bridgeHeaders($foreign),
        )->assertStatus(404);

        $this->assertSame(PrintJobStatus::PENDING, RestaurantPrintJob::query()->findOrFail($jobId)->status);
    }
}
