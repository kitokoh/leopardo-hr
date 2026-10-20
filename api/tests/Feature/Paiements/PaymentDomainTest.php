<?php

declare(strict_types=1);

namespace Tests\Feature\Paiements;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Paiements\Domain\Enums\LedgerDirection;
use App\Modules\Paiements\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Paiements\Domain\Enums\PaymentTransactionType;
use App\Modules\Paiements\Domain\Enums\WalletOwnerType;
use App\Modules\Paiements\Domain\Exceptions\ImmutableLedgerEntryException;
use App\Modules\Paiements\Domain\Exceptions\UnbalancedLedgerEntryException;
use App\Modules\Paiements\Domain\Models\PaymentLedgerEntry;
use App\Modules\Paiements\Domain\Models\Wallet;
use App\Modules\Paiements\Infrastructure\Services\LedgerService;
use App\Modules\Paiements\Infrastructure\Services\PaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-38 PAIEMENTS (#8680, #8681) — socle domaine.
 *
 * Ce que ces tests protegent, dans l'ordre d'importance :
 *   1. le solde est DERIVE du ledger (jamais stocke) ;
 *   2. la partie double tient (2 ecritures, somme debit == somme credit) ;
 *   3. le ledger est IMMUABLE (update et delete rejetes) ;
 *   4. la contre-passation ramene le solde a son etat anterieur.
 */
class PaymentDomainTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private LedgerService $ledger;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        $this->ledger = app(LedgerService::class);
        $this->payments = app(PaymentService::class);
    }

    public function test_wallet_balance_is_derived_from_ledger_and_never_stored(): void
    {
        $wallet = $this->payments->walletFor(
            (string) $this->company->id,
            WalletOwnerType::Tenant,
            (string) $this->company->id,
            'DZD',
        );

        $this->assertSame(0, $wallet->balance());

        // Aucune colonne de solde ne doit exister : le solde est une vue du
        // journal, pas un etat a maintenir.
        /** @var list<object{column_name: string}> $columns */
        $columns = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'payment_wallets'");
        $names = array_map(static fn (object $c): string => $c->column_name, $columns);

        $this->assertNotContains('balance', $names);
        $this->assertNotContains('cached_balance', $names);
    }

    public function test_wallet_owner_currency_is_unique(): void
    {
        $this->payments->walletFor((string) $this->company->id, WalletOwnerType::Tenant, 'owner-1', 'DZD');

        // Le doublon est tente dans un SAVEPOINT : la violation de contrainte
        // avorte la sous-transaction, pas la transaction de test (sinon
        // PostgreSQL laisse la session en 25P02 et tous les tests suivants
        // echouent en cascade).
        $rejected = false;

        try {
            DB::transaction(function (): void {
                Wallet::query()->create([
                    'company_id' => $this->company->id,
                    'owner_type' => WalletOwnerType::Tenant->value,
                    'owner_id' => 'owner-1',
                    'currency' => 'DZD',
                ]);
            });
        } catch (QueryException) {
            $rejected = true;
        }

        $this->assertTrue($rejected, 'Un wallet en doublon (owner_type, owner_id, devise) doit etre rejete par la base.');

        $this->assertSame(1, Wallet::query()
            ->where('company_id', $this->company->id)
            ->where('owner_type', WalletOwnerType::Tenant->value)
            ->where('owner_id', 'owner-1')
            ->where('currency', 'DZD')
            ->count());
    }

    public function test_double_entry_posting_creates_exactly_two_linked_entries(): void
    {
        $debit = $this->payments->walletFor((string) $this->company->id, WalletOwnerType::Platform, 'platform', 'DZD');
        $credit = $this->payments->walletFor((string) $this->company->id, WalletOwnerType::Tenant, 'tenant-x', 'DZD');

        $groupId = $this->ledger->post(
            (string) $this->company->id,
            (int) $debit->id,
            (int) $credit->id,
            25_000,
            'DZD',
        );

        $entries = PaymentLedgerEntry::query()->forGroup($groupId)->get();

        $this->assertCount(2, $entries);
        $this->assertSame(1, $entries->where('direction', LedgerDirection::Debit)->count());
        $this->assertSame(1, $entries->where('direction', LedgerDirection::Credit)->count());

        $inspection = $this->ledger->inspectGroup((string) $this->company->id, $groupId);

        $this->assertTrue($inspection['balanced']);
        $this->assertSame($inspection['debits'], $inspection['credits']);
        $this->assertSame(0, $inspection['count'] % 2);

        // Le solde suit le sens des ecritures.
        $this->assertSame(-25_000, $debit->balance());
        $this->assertSame(25_000, $credit->balance());
    }

    public function test_unbalanced_or_non_positive_amount_is_rejected_before_any_write(): void
    {
        $a = $this->payments->walletFor((string) $this->company->id, WalletOwnerType::Platform, 'platform', 'DZD');
        $b = $this->payments->walletFor((string) $this->company->id, WalletOwnerType::Tenant, 'tenant-y', 'DZD');

        $before = PaymentLedgerEntry::query()->count();

        try {
            $this->ledger->post((string) $this->company->id, (int) $a->id, (int) $b->id, 0, 'DZD');
            $this->fail('Un montant nul doit etre rejete.');
        } catch (UnbalancedLedgerEntryException) {
            // attendu
        }

        try {
            $this->ledger->post((string) $this->company->id, (int) $a->id, (int) $b->id, -100, 'DZD');
            $this->fail('Un montant negatif doit etre rejete.');
        } catch (UnbalancedLedgerEntryException) {
            // attendu
        }

        $this->assertSame($before, PaymentLedgerEntry::query()->count());
    }

    public function test_ledger_entry_cannot_be_updated(): void
    {
        $a = $this->payments->walletFor((string) $this->company->id, WalletOwnerType::Platform, 'platform', 'DZD');
        $b = $this->payments->walletFor((string) $this->company->id, WalletOwnerType::Tenant, 'tenant-z', 'DZD');

        $groupId = $this->ledger->post((string) $this->company->id, (int) $a->id, (int) $b->id, 1_000, 'DZD');

        $entry = PaymentLedgerEntry::query()->forGroup($groupId)->firstOrFail();

        // Le garde `saving` du modele refuse toute modification d'une ligne
        // persistee : une correction passe par une contre-passation.
        $this->expectException(ImmutableLedgerEntryException::class);

        $entry->amount = 999_999;
        $entry->save();
    }

    public function test_ledger_entry_cannot_be_deleted(): void
    {
        $a = $this->payments->walletFor((string) $this->company->id, WalletOwnerType::Platform, 'platform', 'DZD');
        $b = $this->payments->walletFor((string) $this->company->id, WalletOwnerType::Tenant, 'tenant-z', 'DZD');

        $groupId = $this->ledger->post((string) $this->company->id, (int) $a->id, (int) $b->id, 1_000, 'DZD');

        $entry = PaymentLedgerEntry::query()->forGroup($groupId)->firstOrFail();

        // Le garde `deleting` refuse toute suppression : le journal est
        // append-only, une erreur se compense, elle ne disparait pas.
        $this->expectException(ImmutableLedgerEntryException::class);

        $entry->delete();
    }

    public function test_reversal_restores_previous_balance_and_keeps_both_groups(): void
    {
        $a = $this->payments->walletFor((string) $this->company->id, WalletOwnerType::Platform, 'platform', 'DZD');
        $b = $this->payments->walletFor((string) $this->company->id, WalletOwnerType::Tenant, 'tenant-r', 'DZD');

        $groupId = $this->ledger->post((string) $this->company->id, (int) $a->id, (int) $b->id, 7_500, 'DZD');

        $this->assertSame(7_500, $b->balance());

        $reversalGroupId = $this->ledger->reverse((string) $this->company->id, $groupId);

        $this->assertNotSame($groupId, $reversalGroupId);
        $this->assertSame(0, $b->balance());
        $this->assertSame(0, $a->balance());

        // Les deux groupes restent dans l'historique : la correction est
        // auditable, elle n'efface rien.
        $this->assertSame(2, PaymentLedgerEntry::query()->forGroup($groupId)->count());
        $this->assertSame(2, PaymentLedgerEntry::query()->forGroup($reversalGroupId)->count());
        $this->assertTrue($this->ledger->inspectGroup((string) $this->company->id, $reversalGroupId)['balanced']);
    }

    public function test_transaction_status_machine_rejects_forbidden_transitions(): void
    {
        $this->assertFalse(PaymentTransactionStatus::Succeeded->canTransitionTo(PaymentTransactionStatus::Pending));
        $this->assertFalse(PaymentTransactionStatus::Cancelled->canTransitionTo(PaymentTransactionStatus::Succeeded));
        $this->assertTrue(PaymentTransactionStatus::Pending->canTransitionTo(PaymentTransactionStatus::Processing));
        $this->assertTrue(PaymentTransactionStatus::Processing->canTransitionTo(PaymentTransactionStatus::Succeeded));
        $this->assertTrue(PaymentTransactionStatus::Succeeded->isTerminal());
    }

    public function test_internal_transfer_has_no_provider_instance(): void
    {
        $from = $this->payments->walletFor((string) $this->company->id, WalletOwnerType::Tenant, 'from', 'DZD');
        $to = $this->payments->walletFor((string) $this->company->id, WalletOwnerType::Client, 'to', 'DZD');

        $transaction = $this->payments->internalTransfer(
            companyId: (string) $this->company->id,
            amount: 3_000,
            currency: 'DZD',
            idempotencyKey: 'transfer-1',
            fromWalletId: (int) $from->id,
            toWalletId: (int) $to->id,
        );

        $this->assertNull($transaction->provider_instance_id);
        $this->assertSame(PaymentTransactionType::InternalTransfer, $transaction->type);
        $this->assertSame(PaymentTransactionStatus::Succeeded, $transaction->status);
        $this->assertSame(-3_000, $from->balance());
        $this->assertSame(3_000, $to->balance());
    }
}
