<?php

declare(strict_types=1);

namespace App\Modules\Library\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Library\Domain\Enums\LibraryAccessModel;
use App\Modules\Library\Domain\Enums\LibraryPurchaseStatus;
use App\Modules\Library\Domain\Models\LibraryPurchase;
use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Domain\Support\LibraryPricing;
use App\Shared\Contracts\Payments\CashInGatewayInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * BC-40 LIBRARY / BC-38 PAIEMENTS (#9238) — achat à l'unité et vente en gros.
 *
 * Trois garanties, concentrées ici parce qu'elles ne peuvent pas être
 * déléguées à l'interface :
 *
 * 1. **Le prix ne vient jamais du client.** Il est recalculé côté serveur par
 *    `LibraryPricing` (prix catalogue + paliers de gros + TVA de la ressource)
 *    au moment de la vente, puis FIGÉ sur la ligne. Un payload qui annonce
 *    « 1 centime » n'a aucun effet : il n'est même pas lu.
 *
 * 2. **L'accès est fail-closed.** `start()` crée une vente `pending` qui ne
 *    donne AUCUN droit. Seul `confirm()` ouvre l'accès, et il ne le fait
 *    qu'après avoir relu le statut du paiement **en base**, jamais depuis la
 *    requête. Un rejeu de webhook retombe sur la même vente (idempotent).
 *
 * 3. **La facture est allouée à la confirmation, pas à la commande.** Une
 *    intention abandonnée ne consomme donc aucun numéro fiscal ; la séquence
 *    est par tenant, sous verrou de ligne, et `UNIQUE (company_id,
 *    invoice_number)` en base interdit tout doublon.
 */
final class LibraryPurchaseService
{
    /** Plafond de sécurité : au-delà, c'est une erreur de saisie, pas un achat. */
    private const MAX_QUANTITY = 1000;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly CashInGatewayInterface $payments,
    ) {}

    /**
     * Initie (ou retourne, idempotent) la vente d'une ressource payante.
     *
     * Une vente déjà `paid` pour la même clé d'idempotence est retournée telle
     * quelle : on ne refacture jamais un achat existant.
     *
     * @throws ValidationException
     */
    public function start(
        LibraryResource $resource,
        Employee $buyer,
        int $quantity,
        string $idempotencyKey,
    ): LibraryPurchase {
        $this->assertSellable($resource, $quantity);

        $companyId = (string) $buyer->company_id;
        $key = trim($idempotencyKey) !== ''
            ? trim($idempotencyKey)
            : sprintf('library-%s-resource-%d-qty-%d', (string) $buyer->id, (int) $resource->id, $quantity);

        $existing = LibraryPurchase::query()
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing instanceof LibraryPurchase) {
            return $existing;
        }

        $quote = LibraryPricing::quote(
            $resource->access_model,
            (int) $resource->price_minor,
            $this->wholesaleTiers($resource),
            $quantity,
            $this->vatRate($resource),
        );

        /** @var LibraryPurchase $purchase */
        $purchase = $this->connection->transaction(
            fn (): LibraryPurchase => LibraryPurchase::query()->create([
                'company_id' => $companyId,
                'employee_id' => (int) $buyer->id,
                'resource_id' => (int) $resource->id,
                'quantity' => $quote['quantity'],
                'unit_price_minor' => $quote['unit_price_minor'],
                'total_minor' => $quote['total_minor'],
                'net_minor' => $quote['net_minor'],
                'vat_minor' => $quote['vat_minor'],
                'vat_rate' => $quote['vat_rate'],
                'currency' => (string) $resource->currency,
                'status' => LibraryPurchaseStatus::Pending->value,
                'idempotency_key' => $key,
            ]),
        );

        // Encaissement HORS transaction d'écriture : un rail peut être lent ou
        // indisponible. En cas d'échec, la vente reste `pending` (aucun accès
        // n'est ouvert) et l'erreur est explicite — jamais un 500 opaque.
        try {
            $result = $this->payments->cashIn(
                $companyId,
                (int) $purchase->total_minor,
                (string) $purchase->currency,
                'library-purchase-'.$purchase->id,
                null,
                [
                    'library_resource_id' => (int) $resource->id,
                    'library_purchase_id' => (int) $purchase->id,
                ],
            );
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'resource' => 'PAYMENT_PROVIDER_UNAVAILABLE',
            ]);
        }

        $purchase->forceFill([
            'payment_transaction_id' => $result['transaction_id'],
            'payment_reference' => $result['external_reference'] ?? (string) $result['transaction_id'],
        ])->save();

        return $purchase->refresh();
    }

    /**
     * Confirme la vente après paiement — IDEMPOTENT.
     *
     * Rejoue sans effet sur une vente déjà `paid` (aucune seconde facture,
     * aucun second octroi). Le statut du paiement est relu en base : un client
     * qui appellerait cette route sans avoir payé obtient 422, pas un accès.
     *
     * @throws ValidationException
     */
    public function confirm(LibraryPurchase $purchase): LibraryPurchase
    {
        /** @var LibraryPurchase $confirmed */
        $confirmed = $this->connection->transaction(function () use ($purchase): LibraryPurchase {
            /** @var LibraryPurchase|null $locked */
            $locked = LibraryPurchase::query()
                ->where('company_id', (string) $purchase->company_id)
                ->whereKey((int) $purchase->id)
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof LibraryPurchase) {
                throw ValidationException::withMessages(['purchase' => 'PURCHASE_NOT_FOUND']);
            }

            // Rejeu : déjà payée (ou remboursée) — aucun second effet.
            if ($locked->status->grantsAccess() || $locked->status === LibraryPurchaseStatus::Refunded) {
                return $locked;
            }

            if ($locked->payment_transaction_id === null) {
                throw ValidationException::withMessages(['purchase' => 'PAYMENT_NOT_INITIATED']);
            }

            $payment = $this->payments->transactionStatus(
                (int) $locked->payment_transaction_id,
                (string) $locked->company_id,
            );

            if ($payment === null || $payment['status'] !== 'succeeded') {
                throw ValidationException::withMessages(['purchase' => 'PAYMENT_NOT_CONFIRMED']);
            }

            // Garde anti-manipulation : le montant encaissé doit correspondre
            // EXACTEMENT à la vente figée. Un écart = on n'ouvre rien.
            if ($payment['amount_minor'] !== (int) $locked->total_minor
                || $payment['currency'] !== (string) $locked->currency) {
                throw ValidationException::withMessages(['purchase' => 'PAYMENT_AMOUNT_MISMATCH']);
            }

            $locked->forceFill([
                'status' => LibraryPurchaseStatus::Paid->value,
                'invoice_number' => $this->allocateInvoiceNumber((string) $locked->company_id),
                'paid_at' => Carbon::now(),
            ])->save();

            return $locked->refresh();
        });

        return $confirmed;
    }

    /**
     * Le visiteur a-t-il acheté cette ressource (et non remboursé) ?
     *
     * C'est la SEULE source du drapeau `purchased` passé à
     * `LibraryAccessPolicy`. Aucun appelant ne doit se fier à un drapeau fourni
     * par le client.
     */
    public function hasPaidAccess(string $companyId, int $employeeId, int $resourceId): bool
    {
        return LibraryPurchase::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('resource_id', $resourceId)
            ->where('status', LibraryPurchaseStatus::Paid->value)
            ->exists();
    }

    /**
     * Numéro de facture suivant pour un tenant, sous verrou.
     *
     * On verrouille les lignes déjà facturées du tenant : deux confirmations
     * concurrentes se sérialisent donc sur ces lignes, et la séquence ne
     * produit ni doublon ni trou. `UNIQUE (company_id, invoice_number)` est le
     * filet ultime si une écriture contournait ce chemin.
     */
    private function allocateInvoiceNumber(string $companyId): string
    {
        $year = Carbon::now()->year;

        $existing = LibraryPurchase::query()
            ->where('company_id', $companyId)
            ->whereNotNull('invoice_number')
            ->lockForUpdate()
            ->pluck('invoice_number');

        $max = 0;

        foreach ($existing as $number) {
            if (preg_match('/^LIB-'.$year.'-(\d{6})$/', (string) $number, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return LibraryPricing::invoiceNumber($year, $max + 1);
    }

    /**
     * @throws ValidationException
     */
    private function assertSellable(LibraryResource $resource, int $quantity): void
    {
        if ($resource->access_model !== LibraryAccessModel::Purchase) {
            throw ValidationException::withMessages(['resource' => 'RESOURCE_NOT_FOR_SALE']);
        }

        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw ValidationException::withMessages(['quantity' => 'QUANTITY_OUT_OF_RANGE']);
        }

        if (! $resource->isPubliclyVisible()) {
            throw ValidationException::withMessages(['resource' => 'RESOURCE_NOT_FOR_SALE']);
        }
    }

    /**
     * @return list<array{min_qty: int, unit_price_minor: int}>
     */
    private function wholesaleTiers(LibraryResource $resource): array
    {
        $normalized = [];

        // Le type des paliers est garanti par le modèle
        // (`array<int, array{min_qty: int, unit_price_minor: int}>|null`) :
        // aucune validation de forme à refaire ici, seul le cast en entier.
        foreach ($resource->wholesale_tiers ?? [] as $tier) {
            $normalized[] = [
                'min_qty' => (int) $tier['min_qty'],
                'unit_price_minor' => (int) $tier['unit_price_minor'],
            ];
        }

        return $normalized;
    }

    /**
     * Taux de TVA de la ressource ; `0.0` si non renseigné (pas de TVA
     * inventée : la ventilation reste alors neutre et la facture dit 0).
     */
    private function vatRate(LibraryResource $resource): float
    {
        $rate = $resource->vat_rate;

        if (! is_numeric($rate)) {
            return 0.0;
        }

        $float = (float) $rate;

        return $float >= 0.0 && $float <= 1.0 ? $float : 0.0;
    }
}
