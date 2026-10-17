<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantPrintJobRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Enums\PrintJobStatus;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * REST-OS-2 (Mission Leopardo Restaurant OS) — Persistance des travaux
 * d'impression.
 *
 * Couvre : statut par défaut `pending`, unicité (company, commande, type,
 * révision) — un rejeu ne crée jamais de doublon (contrainte DB) —, et la
 * résolution par clé métier scopée au tenant (hors tenant → null).
 */
class RestaurantPrintJobPersistenceTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array{0: Company, 1: RestaurantBranch, 2: RestaurantOrder}
     */
    private function context(): array
    {
        $company = Company::factory()->create();
        $company->setFeature('restaurantmanager', true);
        $company->save();

        Sanctum::actingAs(Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]));

        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);
        $order = RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
        ]);

        return [$company, $branch, $order];
    }

    public function test_a_print_job_defaults_to_pending(): void
    {
        [$company, $branch, $order] = $this->context();

        $job = RestaurantPrintJob::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'ticket_kind' => PrintTicketKind::KITCHEN->value,
            'revision' => 1,
        ]);

        $this->assertSame(PrintJobStatus::PENDING, $job->status);
        $this->assertSame(0, $job->attempts);
        $this->assertNull($job->printed_at);
    }

    public function test_the_business_key_is_unique_per_tenant(): void
    {
        [$company, $branch, $order] = $this->context();

        $attributes = [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'ticket_kind' => PrintTicketKind::KITCHEN->value,
            'revision' => 1,
        ];

        RestaurantPrintJob::create($attributes);

        // Rejeu de la meme cle metier : la contrainte unique doit refuser.
        // Le second INSERT est isole dans un SAVEPOINT (transaction imbriquee)
        // afin que la violation n'avorte pas la transaction du test sous
        // PostgreSQL (sinon le teardown echoue en « current transaction is
        // aborted »). Correction detectee par l'execution reelle (REST-OS-2).
        $violated = false;

        try {
            DB::transaction(static fn () => RestaurantPrintJob::create($attributes));
        } catch (QueryException) {
            $violated = true;
        }

        $this->assertTrue(
            $violated,
            'La cle metier (company, commande, type, revision) doit etre unique par tenant.'
        );
    }

    public function test_the_repository_resolves_a_job_by_business_key_scoped_to_tenant(): void
    {
        [$company, $branch, $order] = $this->context();

        RestaurantPrintJob::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'ticket_kind' => PrintTicketKind::KITCHEN->value,
            'revision' => 2,
        ]);

        $repository = app(RestaurantPrintJobRepositoryInterface::class);

        $found = $repository->findByKey($company->id, $order->id, PrintTicketKind::KITCHEN, 2);
        $this->assertInstanceOf(RestaurantPrintJob::class, $found);

        // Autre tenant ⇒ invisible (null, jamais 404 fuyant des données).
        $this->assertNull(
            $repository->findByKey('00000000-0000-0000-0000-000000000000', $order->id, PrintTicketKind::KITCHEN, 2)
        );
    }
}
