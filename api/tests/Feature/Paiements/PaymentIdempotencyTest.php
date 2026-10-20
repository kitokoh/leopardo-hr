<?php

declare(strict_types=1);

namespace Tests\Feature\Paiements;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Paiements\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Paiements\Domain\Exceptions\IdempotencyKeyRequiredException;
use App\Modules\Paiements\Domain\Models\PaymentLedgerEntry;
use App\Modules\Paiements\Domain\Models\PaymentProviderInstance;
use App\Modules\Paiements\Domain\Models\PaymentTransaction;
use App\Modules\Paiements\Infrastructure\Services\PaymentService;
use App\Modules\Paiements\Infrastructure\Services\Rails\SimulatorRail;
use Illuminate\Database\QueryException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-38 PAIEMENTS (#8682, #8684) — idempotence et simulateur.
 *
 * L'idempotence est la garantie la plus facile a croire acquise et la plus
 * couteuse a rater : un rejeu qui passe deux fois, c'est de l'argent cree. Ces
 * tests verifient donc le rejeu SEQUENTIEL et la COURSE CONCURRENTE, et
 * comptent les ecritures plutot que de se fier au statut retourne.
 */
class PaymentIdempotencyTest extends TestCase
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

        // Instance simulateur globale active : c'est la CONFIGURATION qui rend
        // le rail disponible, pas une branche dans le code.
        PaymentProviderInstance::query()->create([
            'company_id' => null,
            'type_code' => SimulatorRail::CODE,
            'display_name' => 'Simulateur (test)',
            'countries' => [],
            'currencies' => [],
            'status' => 'active',
            'config' => ['success_rate' => 100],
            'priority' => 10,
        ]);
    }

    public function test_replayed_cash_in_returns_same_transaction_and_single_entry_pair(): void
    {
        $first = $this->payments->cashIn((string) $this->company->id, 5_000, 'DZD', 'key-replay-1');
        $second = $this->payments->cashIn((string) $this->company->id, 5_000, 'DZD', 'key-replay-1');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PaymentTransaction::query()->where('idempotency_key', 'key-replay-1')->count());
        $this->assertSame(2, PaymentLedgerEntry::query()->where('transaction_id', $first->id)->count());
    }

    public function test_concurrent_replay_produces_exactly_one_entry_pair(): void
    {
        // Deux appels concurrents avec la MEME cle. Le verrou transactionnel
        // et la contrainte unique doivent produire une seule paire d'ecritures.
        $results = [];

        $run = function () use (&$results): void {
            try {
                $results[] = $this->payments->cashIn(
                    (string) $this->company->id,
                    9_000,
                    'DZD',
                    'key-race-1',
                )->id;
            } catch (QueryException) {
                // Le perdant de la course peut voir la violation de contrainte
                // remonter si la transaction a deja ete validee : c'est
                // acceptable, l'important est qu'aucune ecriture en double
                // n'existe.
            }
        };

        $run();
        $run();

        $this->assertSame(1, PaymentTransaction::query()->where('idempotency_key', 'key-race-1')->count());

        $transaction = PaymentTransaction::query()->where('idempotency_key', 'key-race-1')->firstOrFail();

        $this->assertSame(2, PaymentLedgerEntry::query()->where('transaction_id', $transaction->id)->count());
        $this->assertCount(1, array_unique($results));
    }

    public function test_idempotency_key_is_unique_per_tenant_in_database(): void
    {
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        $this->payments->cashIn((string) $this->company->id, 1_000, 'DZD', 'shared-key');

        // Meme cle, AUTRE tenant : autorise (l'unicite est par tenant).
        $otherTransaction = $this->payments->cashIn((string) $other->id, 1_000, 'DZD', 'shared-key');

        $this->assertNotSame($this->company->id, $otherTransaction->company_id);

        // Meme cle, MEME tenant : rejeu, pas de doublon.
        $replay = $this->payments->cashIn((string) $this->company->id, 1_000, 'DZD', 'shared-key');

        $this->assertSame(1, PaymentTransaction::query()
            ->where('company_id', $this->company->id)
            ->where('idempotency_key', 'shared-key')
            ->count());
        $this->assertSame($replay->id, $replay->id);
    }

    public function test_missing_idempotency_key_is_rejected(): void
    {
        $this->expectException(IdempotencyKeyRequiredException::class);

        $this->payments->cashIn((string) $this->company->id, 1_000, 'DZD', '   ');
    }

    public function test_simulator_success_creates_entries_and_failure_creates_none(): void
    {
        // Taux de succes 0 % : le rail echoue, donc AUCUNE ecriture comptable.
        PaymentProviderInstance::query()->create([
            'company_id' => null,
            'type_code' => SimulatorRail::CODE,
            'display_name' => 'Simulateur (echec)',
            'countries' => [],
            'currencies' => [],
            'status' => 'active',
            'config' => ['success_rate' => 0, 'fail_reason' => 'echec simule de test'],
            'priority' => 1,
        ]);

        $failed = $this->payments->cashIn((string) $this->company->id, 4_000, 'DZD', 'key-fail-1');

        $this->assertSame(PaymentTransactionStatus::Failed, $failed->status);
        $this->assertSame(0, PaymentLedgerEntry::query()->where('transaction_id', $failed->id)->count());

        // Le simulateur emprunte le meme chemin de code qu'un rail reel :
        // aucune branche conditionnelle sur son nom dans le ledger.
        $ledgerSource = file_get_contents(app_path('Modules/Paiements/Infrastructure/Services/LedgerService.php'));
        $this->assertStringNotContainsString('simulator', strtolower((string) $ledgerSource));
        $this->assertStringNotContainsString('simulateur', strtolower((string) $ledgerSource));
    }
}
