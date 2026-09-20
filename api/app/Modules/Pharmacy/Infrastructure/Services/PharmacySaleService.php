<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Services;

use App\Exceptions\DomainException;
use App\Modules\Pharmacy\Domain\Exceptions\PharmacyInvalidTransitionException;
use App\Modules\Pharmacy\Domain\Exceptions\PharmacyPrescriptionRequiredException;
use App\Modules\Pharmacy\Domain\Models\PharmacyPrescription;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Domain\Models\PharmacySale;
use App\Modules\Pharmacy\Domain\Models\PharmacySaleLine;
use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ventes comptoir — PHARMA-005 (#7802).
 *
 * `create()` est transactionnel : panier validé, ordonnance exigée si un
 * produit est `prescription_required` OU `is_controlled` (PHARMA-006),
 * délivrance FEFO ligne par ligne (mouvements `sale` liés à la vente),
 * totaux calculés SERVEUR (bcmath sur décimaux, jamais confiés au client),
 * prix et taux de taxe figés dans les lignes.
 *
 * `void()` conserve la vente (`voided`) et ré-crédite les lots d'ORIGINE
 * par mouvements `return` (contre-passation, jamais d'effacement).
 */
class PharmacySaleService
{
    public const REFERENCE_TYPE = 'pharmacy_sale';

    public function __construct(private readonly PharmacyStockService $stock) {}

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines
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
            throw new DomainException((string) __('pharmacy.empty_sale'), 422, 'PHARMACY_EMPTY_SALE');
        }

        return DB::transaction(function () use ($companyId, $lines, $paymentMethod, $customerName, $prescriptionId, $employeeId): PharmacySale {
            // Lien vente ↔ ordonnance (PHARMA-006) : l'ordonnance référencée
            // doit exister DANS le tenant (aucune fuite cross-tenant).
            if ($prescriptionId !== null) {
                $prescriptionExists = PharmacyPrescription::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->whereKey($prescriptionId)
                    ->exists();

                if (! $prescriptionExists) {
                    throw new DomainException((string) __('pharmacy.prescription_not_found'), 422, 'PHARMACY_PRESCRIPTION_NOT_FOUND');
                }
            }

            /** @var array<int, PharmacyProduct> $products */
            $products = [];

            foreach ($lines as $line) {
                /** @var PharmacyProduct|null $product */
                $product = PharmacyProduct::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->whereKey($line['product_id'])
                    ->first();

                if ($product === null) {
                    throw new DomainException((string) __('pharmacy.product_not_found'), 404, 'PHARMACY_PRODUCT_NOT_FOUND');
                }

                // Ordonnance exigée : produit sous prescription OU contrôlé
                // (inscrit à l'ordonnancier, PHARMA-006).
                if (($product->prescription_required || $product->is_controlled) && $prescriptionId === null) {
                    throw new PharmacyPrescriptionRequiredException($product->name);
                }

                $products[$line['product_id']] = $product;
            }

            $sale = new PharmacySale([
                'number' => $this->nextNumber($companyId),
                'sold_at' => Carbon::now(),
                'customer_name' => $customerName,
                'prescription_id' => $prescriptionId,
                'payment_method' => $paymentMethod,
                'total_amount' => '0.00',
                'status' => 'completed',
                'sold_by_employee_id' => $employeeId,
            ]);
            $sale->company_id = $companyId;
            $sale->save();

            $total = '0.00';

            foreach ($lines as $line) {
                $product = $products[$line['product_id']];
                /** @var numeric-string $unitPrice */
                $unitPrice = (string) $product->sale_price;
                $lineTotal = bcmul($unitPrice, (string) $line['quantity'], 2);
                $total = bcadd($total, $lineTotal, 2);

                $saleLine = new PharmacySaleLine([
                    'sale_id' => $sale->id,
                    'product_id' => (int) $product->getAttribute('id'),
                    'quantity' => $line['quantity'],
                    'unit_price' => $unitPrice,
                    'tax_rate' => (string) $product->tax_rate,
                    'line_total' => $lineTotal,
                ]);
                $saleLine->company_id = $companyId;
                $saleLine->save();

                // FEFO : refus lots périmés / stock insuffisant → rollback
                // complet de la vente (aucun effet partiel).
                $this->stock->dispenseFefo(
                    $companyId,
                    (int) $product->getAttribute('id'),
                    $line['quantity'],
                    $employeeId,
                    self::REFERENCE_TYPE,
                    (int) $sale->id,
                    'sale',
                );
            }

            $sale->total_amount = $total;
            $sale->save();

            return $sale;
        });
    }

    /**
     * Annulation : la vente est CONSERVÉE (`voided`), le stock des lots
     * d'origine est ré-crédité par mouvements `return`.
     */
    public function void(PharmacySale $sale, string $reason, ?int $employeeId = null): PharmacySale
    {
        if ($sale->status !== 'completed') {
            throw new PharmacyInvalidTransitionException($sale->status, 'voided');
        }

        if (trim($reason) === '') {
            throw new DomainException((string) __('pharmacy.void_reason_required'), 422, 'PHARMACY_REASON_REQUIRED');
        }

        $companyId = (string) $sale->company_id;

        return DB::transaction(function () use ($sale, $reason, $employeeId, $companyId): PharmacySale {
            /** @var \Illuminate\Database\Eloquent\Collection<int, PharmacyStockMovement> $movements */
            $movements = PharmacyStockMovement::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('type', 'sale')
                ->where('reference_type', self::REFERENCE_TYPE)
                ->where('reference_id', $sale->id)
                ->get();

            /** @var list<array{batch_id: int, quantity: int}> $returnLines */
            $returnLines = [];

            foreach ($movements as $movement) {
                $returnLines[] = [
                    'batch_id' => (int) $movement->batch_id,
                    'quantity' => (int) abs($movement->quantity_delta),
                ];
            }

            $this->stock->returnToBatches(
                $companyId,
                $returnLines,
                $reason,
                $employeeId,
                self::REFERENCE_TYPE,
                (int) $sale->id,
            );

            $sale->status = 'voided';
            $sale->void_reason = $reason;
            $sale->voided_at = Carbon::now();
            $sale->save();

            return $sale;
        });
    }

    /**
     * Numéro `VT-YYYY-XXXXX` séquencé par tenant (verrou pessimiste).
     */
    private function nextNumber(string $companyId): string
    {
        $year = Carbon::now()->format('Y');
        $prefix = 'VT-'.$year.'-';

        /** @var PharmacySale|null $last */
        $last = PharmacySale::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->lockForUpdate()
            ->first();

        $sequence = $last === null ? 1 : ((int) substr($last->number, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
