<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Library\Domain\Enums\LibraryPurchaseStatus;
use App\Modules\Library\Domain\Models\LibraryPurchase;
use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Infrastructure\Services\LibraryPurchaseService;
use App\Shared\Contracts\Payments\CashInGatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-40 LIBRARY / BC-38 PAIEMENTS (#9238) — achat à l'unité et vente en gros.
 *
 * Chaque test couvre un critère d'acceptation de l'issue :
 *  1. fail-closed : une ressource payante non achetée n'ouvre AUCUN accès, et
 *     l'accès n'est accordé qu'après un paiement confirmé et idempotent ;
 *  2. aucun montant côté client : le prix est recalculé serveur (paliers +
 *     ventilation TVA) ;
 *  3. le parcours est couvert ici (succès, échec, rejeu, montant falsifié) ;
 *  4. isolation : l'achat d'un tenant n'ouvre rien dans un autre.
 *
 * ⚠️ Les tests tournent sous un VRAI contexte tenant (`current_company`), comme
 * en production : c'est ce qui fait fonctionner le scope `BelongsToCompany`.
 * La passerelle de paiement est un FAUX explicite : aucun appel réseau.
 */
class LibraryPurchaseTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private FakeCashInGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->otherCompany = $other;

        app()->instance('current_company', $this->company);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current_company');
        app()->forgetInstance('tenant_scope_required');

        parent::tearDown();
    }

    private function service(string $initiationStatus = 'succeeded'): LibraryPurchaseService
    {
        $this->gateway = new FakeCashInGateway($initiationStatus);

        return new LibraryPurchaseService(DB::connection(), $this->gateway);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resource(array $overrides = []): LibraryResource
    {
        /** @var LibraryResource $resource */
        $resource = LibraryResource::query()->create(array_merge([
            'title' => 'Manuel payant',
            'slug' => 'manuel-payant',
            'document_type' => 'book',
            'language' => 'fr',
            'access_model' => 'purchase',
            'status' => 'published',
            'price_minor' => 1000,
            'currency' => 'DZD',
            'license_id' => 'proprietary',
            'rights_holder' => 'Test',
            'allow_download' => true,
            'allow_redistribution' => false,
        ], $overrides));

        return $resource;
    }

    private function buyer(?Company $company = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => ($company ?? $this->company)->id]);

        return $employee;
    }

    public function test_a_pending_purchase_grants_no_access(): void
    {
        $resource = $this->resource();
        $buyer = $this->buyer();
        $service = $this->service();

        $purchase = $service->start($resource, $buyer, 1, 'key-pending');

        $this->assertSame(LibraryPurchaseStatus::Pending, $purchase->status);
        $this->assertNull($purchase->invoice_number, 'Une intention non payée ne consomme aucun numéro de facture.');
        $this->assertFalse($service->hasPaidAccess((string) $this->company->id, (int) $buyer->id, (int) $resource->id));
    }

    public function test_confirm_refuses_a_payment_that_is_not_succeeded(): void
    {
        $resource = $this->resource();
        $buyer = $this->buyer();

        // La passerelle annonce `processing` à l'initiation : ce n'est PAS un
        // paiement confirmé.
        $service = $this->service('processing');
        $purchase = $service->start($resource, $buyer, 1, 'key-processing');

        try {
            $service->confirm($purchase);
            $this->fail('Un paiement non confirmé ne doit jamais ouvrir l’accès.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('purchase', $exception->errors());
        }

        $this->assertSame(
            LibraryPurchaseStatus::Pending,
            $purchase->refresh()->status,
            'La vente doit rester en attente.',
        );
        $this->assertFalse($service->hasPaidAccess((string) $this->company->id, (int) $buyer->id, (int) $resource->id));
    }

    public function test_confirm_grants_access_and_allocates_one_invoice_even_on_replay(): void
    {
        $resource = $this->resource();
        $buyer = $this->buyer();
        $service = $this->service();

        $purchase = $service->start($resource, $buyer, 1, 'key-paid');

        $confirmed = $service->confirm($purchase);

        $this->assertSame(LibraryPurchaseStatus::Paid, $confirmed->status);
        $this->assertNotNull($confirmed->paid_at);
        $this->assertSame('LIB-'.now()->year.'-000001', $confirmed->invoice_number);

        // Rejeu du webhook : même vente, même facture, aucun second effet.
        $replayed = $service->confirm($confirmed);

        $this->assertSame($confirmed->invoice_number, $replayed->invoice_number);
        $this->assertSame(1, LibraryPurchase::query()->where('company_id', $this->company->id)->count());
        $this->assertTrue($service->hasPaidAccess((string) $this->company->id, (int) $buyer->id, (int) $resource->id));
    }

    public function test_the_second_invoice_of_a_tenant_follows_the_sequence(): void
    {
        $buyer = $this->buyer();
        $service = $this->service();

        $first = $service->confirm($service->start($this->resource(['slug' => 'livre-a']), $buyer, 1, 'key-a'));
        $second = $service->confirm($service->start($this->resource(['slug' => 'livre-b']), $buyer, 1, 'key-b'));

        $this->assertSame('LIB-'.now()->year.'-000001', $first->invoice_number);
        $this->assertSame('LIB-'.now()->year.'-000002', $second->invoice_number);
    }

    public function test_server_side_price_applies_wholesale_tier_and_vat(): void
    {
        $resource = $this->resource([
            'price_minor' => 1000,
            'vat_rate' => 0.19,
            'wholesale_tiers' => [
                ['min_qty' => 10, 'unit_price_minor' => 800],
                ['min_qty' => 50, 'unit_price_minor' => 700],
            ],
        ]);

        $buyer = $this->buyer();
        $service = $this->service();

        $purchase = $service->start($resource, $buyer, 10, 'key-wholesale');

        $this->assertSame(800, (int) $purchase->unit_price_minor);
        $this->assertSame(8000, (int) $purchase->total_minor);
        // TTC 8000 à 19 % → TVA 1277, HT 6723 : `net + vat === total` exactement.
        $this->assertSame(1277, (int) $purchase->vat_minor);
        $this->assertSame(6723, (int) $purchase->net_minor);
        $this->assertSame(8000, (int) $purchase->net_minor + (int) $purchase->vat_minor);
        $this->assertSame('DZD', (string) $purchase->currency);
    }

    public function test_a_payment_with_a_different_amount_never_grants_access(): void
    {
        $resource = $this->resource();
        $buyer = $this->buyer();
        $service = $this->service();
        $purchase = $service->start($resource, $buyer, 1, 'key-tampered');

        // Le fournisseur confirme un montant différent de la vente figée.
        $this->gateway->setAmount((int) $purchase->payment_transaction_id, 1);

        $this->expectException(ValidationException::class);

        $service->confirm($purchase);
    }

    public function test_a_purchase_never_opens_access_in_another_tenant(): void
    {
        $resource = $this->resource();
        $buyer = $this->buyer();
        $service = $this->service();

        $service->confirm($service->start($resource, $buyer, 1, 'key-tenant'));

        $this->assertTrue($service->hasPaidAccess((string) $this->company->id, (int) $buyer->id, (int) $resource->id));
        $this->assertFalse($service->hasPaidAccess((string) $this->otherCompany->id, (int) $buyer->id, (int) $resource->id));
        $this->assertFalse($service->hasPaidAccess((string) $this->company->id, (int) $buyer->id + 999, (int) $resource->id));
    }

    public function test_a_free_resource_cannot_be_bought(): void
    {
        $resource = $this->resource(['access_model' => 'free', 'slug' => 'gratuit']);
        $buyer = $this->buyer();

        $this->expectException(ValidationException::class);

        $this->service()->start($resource, $buyer, 1, 'key-free');
    }
}

/**
 * Faux contrat d'encaissement : enregistre les appels et rend un statut
 * configurable, SANS réseau ni rail réel. `processing` reproduit un rail
 * asynchrone (aucun accès avant le webhook).
 */
final class FakeCashInGateway implements CashInGatewayInterface
{
    /** @var array<int, array{status: string, amount_minor: int, currency: string}> */
    public array $transactions = [];

    public function __construct(private readonly string $initiationStatus) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{transaction_id: int, status: string, external_reference: string|null}
     */
    public function cashIn(
        string $companyId,
        int $amountMinor,
        string $currency,
        string $idempotencyKey,
        ?string $country = null,
        array $metadata = [],
    ): array {
        $id = count($this->transactions) + 1;

        $this->transactions[$id] = [
            'status' => $this->initiationStatus,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
        ];

        return [
            'transaction_id' => $id,
            'status' => $this->initiationStatus,
            'external_reference' => 'ext-'.$id,
        ];
    }

    /**
     * @return array{status: string, amount_minor: int, currency: string}|null
     */
    public function transactionStatus(int $transactionId, ?string $companyId = null): ?array
    {
        return $this->transactions[$transactionId] ?? null;
    }

    public function setAmount(int $transactionId, int $amountMinor): void
    {
        if (isset($this->transactions[$transactionId])) {
            $this->transactions[$transactionId]['amount_minor'] = $amountMinor;
        }
    }
}
