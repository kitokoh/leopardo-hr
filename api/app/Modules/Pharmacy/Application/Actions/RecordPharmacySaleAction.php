<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacySale;
use App\Modules\Pharmacy\Infrastructure\Services\PharmacySaleService;

/**
 * Cas d'usage : encaissement d'une vente comptoir (POS) — PHARMA-005 (#7802).
 *
 * Consommé par `POST /api/v1/pharmacy/sales` (PharmacySaleController::store).
 * La politique d'accès (feature flag solution + Policy create) reste au
 * niveau interface ; l'Action porte le cas d'usage nommable. La délivrance
 * FEFO, le contrôle d'ordonnance et les totaux figés restent dans
 * PharmacySaleService (Infrastructure — logique éprouvée explicitement hors
 * périmètre BOS-024c).
 */
class RecordPharmacySaleAction
{
    public function __construct(
        private readonly PharmacySaleService $sales,
    ) {}

    /**
     * @param  array{payment_method: string, customer_name?: string|null, prescription_id?: int|null, lines: list<array{product_id: int, quantity: int}>}  $payload
     */
    public function execute(Employee $actor, array $payload): PharmacySale
    {
        return $this->sales->create(
            (string) $actor->company_id,
            array_map(static fn (array $line): array => [
                'product_id' => (int) $line['product_id'],
                'quantity' => (int) $line['quantity'],
            ], $payload['lines']),
            $payload['payment_method'],
            $payload['customer_name'] ?? null,
            isset($payload['prescription_id']) ? (int) $payload['prescription_id'] : null,
            $actor->id,
        );
    }
}
