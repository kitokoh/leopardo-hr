<?php

declare(strict_types=1);

namespace Tests\Feature\Paiements;

use App\Core\Tenant\Domain\Contracts\TenantScopedEvent;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\PaymentCashOutCompleted;
use App\Events\PaymentCashOutRequested;
use App\Events\PaymentReconciliationDiscrepancy;
use App\Events\PaymentTransactionFailed;
use App\Events\PaymentTransactionSucceeded;
use App\Modules\Paiements\Domain\Models\PaymentProviderInstance;
use App\Modules\Paiements\Infrastructure\Services\PaymentService;
use App\Modules\Paiements\Infrastructure\Services\Rails\SimulatorRail;
use App\Modules\Paiements\Infrastructure\Services\ReconciliationService;
use Illuminate\Support\Facades\Event;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-38 PAIEMENTS (#8712) — emission des evenements de domaine.
 *
 * L'ecart P-2 de l'audit readiness : le BC Paiements ne publiait AUCUN
 * evenement (`event()`/`dispatch()` absents de tout `Modules/Paiements`), donc
 * aucun BC consommateur ne pouvait reagir sans importer les modeles. Ces tests
 * verrouillent trois proprietes qui ne se voient pas a la lecture :
 *
 *  1. l'evenement PART — `Event::fake()` + `assertDispatched`, pas une
 *     assertion sur un service interne ;
 *  2. il porte le BON tenant — chaque evenement implemente
 *     `TenantScopedEvent` et `tenantCompanyId()` renvoie la compagnie de la
 *     transaction, jamais celle du contexte ambiant (fail-closed sinon) ;
 *  3. il ne part PAS pour un etat rollback ni pour un echec de rail — sinon
 *     un consommateur crediterait un solde pour de l'argent qui n'a pas bouge.
 */
class PaymentDomainEventsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        $this->payments = app(PaymentService::class);

        PaymentProviderInstance::query()->create([
            'company_id' => null,
            'type_code' => SimulatorRail::CODE,
            'display_name' => 'Simulateur (evenements)',
            'countries' => [],
            'currencies' => [],
            'status' => 'active',
            'config' => ['success_rate' => 100],
            'priority' => 10,
        ]);
    }

    public function test_successful_cash_in_dispatches_succeeded_event_with_payment_payload(): void
    {
        Event::fake([PaymentTransactionSucceeded::class]);

        $transaction = $this->payments->cashIn((string) $this->company->id, 5_000, 'DZD', 'evt-cashin-1');

        Event::assertDispatched(
            PaymentTransactionSucceeded::class,
            function (PaymentTransactionSucceeded $event) use ($transaction): bool {
                return $event->transactionId === (int) $transaction->id
                    && $event->amount === 5_000
                    && $event->currency === 'DZD'
                    && $event->type === 'cash_in'
                    && $event->companyId === (string) $this->company->id;
            }
        );
    }

    public function test_every_payment_event_carries_its_tenant_contract(): void
    {
        // Contrat `TenantScopedEvent` : sans lui, `TenantEventDispatcher` ne
        // peut pas poser le contexte et l'evenement echapperait au tenant.
        $events = [
            new PaymentTransactionSucceeded('company-1', 1, 'cash_in', 100, 'DZD'),
            new PaymentTransactionFailed('company-1', 1, 'cash_out', 'reason'),
            new PaymentCashOutRequested('company-1', 1, 100, 'DZD'),
            new PaymentCashOutCompleted('company-1', 1, 100, 'DZD'),
            new PaymentReconciliationDiscrepancy('company-1', 'DZD', 10, 5, 5),
        ];

        foreach ($events as $event) {
            $this->assertInstanceOf(TenantScopedEvent::class, $event);
            $this->assertSame('company-1', $event->tenantCompanyId());
        }
    }

    public function test_cash_out_dispatches_requested_then_completed_and_both_are_distinct(): void
    {
        Event::fake([PaymentCashOutRequested::class, PaymentCashOutCompleted::class]);

        $transaction = $this->payments->cashOut((string) $this->company->id, 2_500, 'DZD', 'evt-cashout-1');

        Event::assertDispatched(
            PaymentCashOutRequested::class,
            fn (PaymentCashOutRequested $event): bool => $event->transactionId === (int) $transaction->id
        );

        Event::assertDispatched(
            PaymentCashOutCompleted::class,
            fn (PaymentCashOutCompleted $event): bool => $event->transactionId === (int) $transaction->id
        );
    }

    public function test_failed_rail_dispatches_failure_event_without_success_event(): void
    {
        // Taux de succes 0 % : le rail echoue, donc aucune ecriture comptable
        // et AUCUN `transaction.succeeded`.
        PaymentProviderInstance::query()->create([
            'company_id' => null,
            'type_code' => SimulatorRail::CODE,
            'display_name' => 'Simulateur (echec evenements)',
            'countries' => [],
            'currencies' => [],
            'status' => 'active',
            'config' => ['success_rate' => 0, 'fail_reason' => 'echec simule de test'],
            'priority' => 1,
        ]);

        Event::fake([PaymentTransactionFailed::class, PaymentTransactionSucceeded::class]);

        $transaction = $this->payments->cashIn((string) $this->company->id, 1_000, 'DZD', 'evt-fail-1');

        Event::assertDispatched(
            PaymentTransactionFailed::class,
            function (PaymentTransactionFailed $event) use ($transaction): bool {
                return $event->transactionId === (int) $transaction->id
                    && $event->failureReason === 'echec simule de test';
            }
        );

        Event::assertNotDispatched(PaymentTransactionSucceeded::class);
    }

    public function test_replayed_transaction_does_not_re_emit_the_event(): void
    {
        // Un rejeu d'idempotence retourne la transaction existante SANS effet
        // de bord : publier l'evenement une seconde fois ferait crediter deux
        // fois un consommateur qui n'est pas idempotent lui-meme.
        Event::fake([PaymentTransactionSucceeded::class]);

        $this->payments->cashIn((string) $this->company->id, 7_000, 'DZD', 'evt-replay-1');

        Event::assertDispatchedTimes(PaymentTransactionSucceeded::class, 1);

        $this->payments->cashIn((string) $this->company->id, 7_000, 'DZD', 'evt-replay-1');

        Event::assertDispatchedTimes(PaymentTransactionSucceeded::class, 1);
    }

    public function test_reconciliation_without_discrepancy_dispatches_nothing(): void
    {
        Event::fake([PaymentReconciliationDiscrepancy::class]);

        /** @var PaymentProviderInstance $instance */
        $instance = PaymentProviderInstance::query()->firstOrFail();

        // Solde declare == solde calcule depuis le ledger (0) : aucune alerte.
        app(ReconciliationService::class)->reconcile(
            (string) $this->company->id,
            $instance,
            'DZD',
            0,
        );

        Event::assertNotDispatched(PaymentReconciliationDiscrepancy::class);
    }

    public function test_reconciliation_with_discrepancy_dispatches_the_alert(): void
    {
        Event::fake([PaymentReconciliationDiscrepancy::class]);

        /** @var PaymentProviderInstance $instance */
        $instance = PaymentProviderInstance::query()->firstOrFail();

        app(ReconciliationService::class)->reconcile(
            (string) $this->company->id,
            $instance,
            'DZD',
            125_000,
        );

        Event::assertDispatched(
            PaymentReconciliationDiscrepancy::class,
            function (PaymentReconciliationDiscrepancy $event): bool {
                // Le signe compte : declared - computed, jamais l'inverse.
                return $event->difference === 125_000
                    && $event->declaredBalance === 125_000
                    && $event->currency === 'DZD';
            }
        );
    }
}
