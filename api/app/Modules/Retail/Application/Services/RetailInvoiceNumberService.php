<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Services;

use App\Modules\Retail\Domain\Enums\RetailOrderStatus;
use App\Modules\Retail\Domain\Models\RetailOrder;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * BC-17 RETAIL (#7813) — Numérotation LÉGALE des factures par tenant.
 *
 * `FAC-YYYY-NNNNNN` : séquence annuelle PAR TENANT, assignée à la PREMIÈRE
 * génération de la facture puis IMMUABLE (les rejeux re-rendent le même
 * numéro — pas de trou, pas de renumérotation). Transactionnel avec verrou
 * de ligne ; l'index unique partiel (company_id, invoice_number) est le
 * filet de sécurité de la course (23505 → retry séquence suivante, même
 * mécanique que DeliveryReference/CreateDeliveryAction).
 *
 * Facturables : commandes `completed` uniquement (POS encaissée ou commande
 * web confirmée #7808) — 422 sinon.
 *
 * Pas de facade Laravel (pureté de couche Application, garde #6568).
 */
final class RetailInvoiceNumberService
{
    private const MAX_ATTEMPTS = 10;

    public function __construct(private readonly ConnectionInterface $connection) {}

    /**
     * Retourne la commande avec son numéro de facture (assigné si absent).
     *
     * @throws ValidationException 422 si la commande n'est pas facturable.
     */
    public function assign(RetailOrder $order): RetailOrder
    {
        /** @var RetailOrder $invoiced */
        $invoiced = $this->connection->transaction(function () use ($order): RetailOrder {
            /** @var RetailOrder $locked */
            $locked = RetailOrder::query()
                ->where('company_id', (string) $order->company_id)
                ->lockForUpdate()
                ->findOrFail((int) $order->id);

            if ($locked->invoice_number !== null) {
                return $locked;
            }

            if ($locked->status !== RetailOrderStatus::Completed) {
                throw ValidationException::withMessages([
                    'order' => 'ORDER_NOT_INVOICEABLE',
                ]);
            }

            $companyId = (string) $locked->company_id;
            $year = (int) Carbon::now()->format('Y');

            $sequence = $this->nextSequence($companyId, $year);

            for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
                $candidate = sprintf('FAC-%d-%06d', $year, $sequence + $attempt);

                try {
                    $locked->forceFill([
                        'invoice_number' => $candidate,
                        'invoiced_at' => Carbon::now(),
                    ])->save();

                    return $locked->refresh();
                } catch (QueryException $exception) {
                    if ($exception->getCode() !== '23505') {
                        throw $exception;
                    }
                    // Course sur la séquence : le perdant retente le numéro
                    // suivant (l'index unique par tenant est le filet).
                }
            }

            throw ValidationException::withMessages([
                'invoice_number' => 'Unable to allocate a unique invoice number; please retry.',
            ]);
        });

        return $invoiced;
    }

    /**
     * Prochaine séquence annuelle du tenant : max des numéros déjà émis
     * cette année + 1 (jamais de réutilisation après annulation — la
     * numérotation légale ne renuméro te pas).
     */
    private function nextSequence(string $companyId, int $year): int
    {
        /** @var string|null $max */
        $max = RetailOrder::query()
            ->where('company_id', $companyId)
            ->where('invoice_number', 'like', sprintf('FAC-%d-%%', $year))
            ->max('invoice_number');

        if (! is_string($max) || $max === '') {
            return 1;
        }

        return (int) substr($max, -6) + 1;
    }
}
