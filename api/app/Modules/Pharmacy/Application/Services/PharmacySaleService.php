<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Services;

use App\Modules\Pharmacy\Domain\Exceptions\PharmacyPrescriptionRequiredException;
use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Domain\Models\PharmacySale;
use App\Modules\Pharmacy\Domain\Models\PharmacySaleLine;
use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * PHARMA-005 (#7802) — ventes comptoir (POS) de l'officine.
 *
 * `create()` est TRANSACTIONNEL : valide le panier (produits ACTIFS du
 * tenant), exige `prescription_id` dès qu'un produit `prescription_required`
 * est présent (422 PHARMACY_PRESCRIPTION_REQUIRED), délivre chaque ligne en
 * FEFO via PharmacyStockService::dispenseFefo() (#7800 — stock insuffisant
 * → 422 PHARMACY_INSUFFICIENT_STOCK et AUCUN effet partiel, la transaction
 * est annulée), fige prix unitaire et taux de taxe dans les lignes et
 * calcule les totaux CÔTÉ SERVEUR (jamais confiés au client). Numéro
 * `VT-YYYY-XXXXX` séquencé par tenant.
 *
 * `void()` (raison obligatoire) re-crédite les lots d'ORIGINE de la
 * délivrance (mouvements `return` reconstruits depuis le journal immuable
 * des mouvements `sale` de la vente) et passe la vente `voided` — elle
 * n'est jamais supprimée.
 *
 * Pas de facade Laravel ici (pureté de couche Application, garde #6568) :
 * la connexion est injectée via ConnectionInterface.
 */
final class PharmacySaleService
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly PharmacyStockService $stockService,
    ) {}

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines
     *
     * @throws ValidationException|PharmacyPrescriptionRequiredException
     */
    public function create(
        string $companyId,
        array $lines,
        string $paymentMethod,
        ?string $customerName = null,
        ?int $prescriptionId = null,
        ?int $employeeId = null,
    ): PharmacySale {
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'Une vente doit porter au moins une ligne.',
            ]);
        }

        /** @var PharmacySale $sale */
        $sale = $this->connection->transaction(
            function () use ($companyId, $lines, $paymentMethod, $customerName, $prescriptionId, $employeeId): PharmacySale {
                $products = [];

                foreach ($lines as $index => $line) {
                    /** @var PharmacyProduct|null $product */
                    $product = PharmacyProduct::query()
                        ->where('company_id', $companyId)
                        ->where('status', 'active')
                        ->find($line['product_id']);

                    if (! $product instanceof PharmacyProduct) {
                        throw ValidationException::withMessages([
                            'lines.'.$index.'.product_id' => 'Produit introuvable ou archive dans ce tenant.',
                        ]);
                    }

                    if ($product->prescription_required && $prescriptionId === null) {
                        throw new PharmacyPrescriptionRequiredException;
                    }

                    $products[$index] = $product;
                }

                $sale = PharmacySale::query()->create([
                    'company_id' => $companyId,
                    'number' => $this->nextNumber($companyId),
                    'sold_at' => Carbon::now(),
                    'customer_name' => $customerName,
                    'prescription_id' => $prescriptionId,
                    'payment_method' => $paymentMethod,
                    'total_amount' => '0.00',
                    'status' => PharmacySale::STATUS_COMPLETED,
                    'sold_by_employee_id' => $employeeId,
                ]);

                $totalCents = 0;

                foreach ($lines as $index => $line) {
                    $product = $products[$index];
                    $quantity = $line['quantity'];

                    // Prix et taxe FIGÉS à la vente, total ligne calculé
                    // serveur en CENTIMES entiers (jamais de flottant
                    // persisté — les colonnes restent decimal).
                    $unitPrice = (string) $product->sale_price;
                    $unitCents = (int) round((float) $unitPrice * 100);
                    $lineCents = $unitCents * $quantity;
                    $totalCents += $lineCents;

                    PharmacySaleLine::query()->create([
                        'company_id' => $companyId,
                        'sale_id' => (int) $sale->id,
                        'product_id' => (int) $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'tax_rate' => (string) $product->tax_rate,
                        'line_total' => $this->centsToDecimal($lineCents),
                    ]);

                    // Délivrance FEFO : mouvements `sale` référencés sur la
                    // vente (transaction imbriquée = savepoint — un échec
                    // annule TOUTE la vente, aucun effet partiel).
                    $this->stockService->dispenseFefo(
                        product: $product,
                        quantity: $quantity,
                        type: PharmacyStockMovement::TYPE_SALE,
                        referenceType: 'pharmacy_sale',
                        referenceId: (int) $sale->id,
                        employeeId: $employeeId,
                    );
                }

                $sale->forceFill(['total_amount' => $this->centsToDecimal($totalCents)])->save();

                return $sale->refresh();
            }
        );

        return $sale;
    }

    /**
     * Annule une vente `completed` (raison OBLIGATOIRE) : les lots d'origine
     * sont re-crédités par des mouvements `return` reconstruits depuis le
     * journal immuable, la vente passe `voided` et reste consultable.
     *
     * @throws ValidationException 422 si la vente est déjà annulée.
     */
    public function void(PharmacySale $sale, string $reason, ?int $employeeId = null): PharmacySale
    {
        /** @var PharmacySale $voided */
        $voided = $this->connection->transaction(function () use ($sale, $reason, $employeeId): PharmacySale {
            $companyId = (string) $sale->company_id;

            /** @var PharmacySale $locked */
            $locked = PharmacySale::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail((int) $sale->id);

            if ($locked->status !== PharmacySale::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'status' => 'Seule une vente terminee peut etre annulee.',
                ]);
            }

            /** @var \Illuminate\Database\Eloquent\Collection<int, PharmacyStockMovement> $saleMovements */
            $saleMovements = PharmacyStockMovement::query()
                ->where('company_id', $companyId)
                ->where('reference_type', 'pharmacy_sale')
                ->where('reference_id', (int) $locked->id)
                ->where('type', PharmacyStockMovement::TYPE_SALE)
                ->orderBy('id')
                ->get();

            foreach ($saleMovements as $movement) {
                /** @var PharmacyBatch $batch */
                $batch = PharmacyBatch::query()
                    ->where('company_id', $companyId)
                    ->findOrFail($movement->batch_id);

                $this->stockService->credit(
                    batch: $batch,
                    quantity: abs($movement->quantity_delta),
                    type: PharmacyStockMovement::TYPE_RETURN,
                    reason: $reason,
                    referenceType: 'pharmacy_sale',
                    referenceId: (int) $locked->id,
                    employeeId: $employeeId,
                );
            }

            $locked->forceFill([
                'status' => PharmacySale::STATUS_VOIDED,
                'void_reason' => $reason,
                'voided_at' => Carbon::now(),
                'voided_by_employee_id' => $employeeId,
            ])->save();

            return $locked->refresh();
        });

        return $voided;
    }

    /**
     * Numéro `VT-YYYY-XXXXX` séquencé par tenant — à appeler DANS une
     * transaction (verrou sur les ventes du tenant pour l'année).
     */
    private function nextNumber(string $companyId): string
    {
        $year = Carbon::now()->year;
        $prefix = sprintf('VT-%d-', $year);

        /** @var string|null $last */
        $last = PharmacySale::query()
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $next = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return sprintf('%s%05d', $prefix, $next);
    }

    /**
     * Centimes entiers → chaîne decimal à 2 décimales (ex. 12050 → "120.50").
     */
    private function centsToDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
