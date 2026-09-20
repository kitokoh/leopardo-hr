<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Exceptions\HealthBillingException;
use App\Modules\HealthManager\Domain\Models\HealthCareAct;
use App\Modules\HealthManager\Domain\Models\HealthInvoice;
use App\Modules\HealthManager\Domain\Models\HealthInvoiceItem;
use App\Modules\HealthManager\Domain\Models\HealthInvoicePayment;
use Illuminate\Support\Facades\DB;

/**
 * Règles métier de la facturation des soins — HC-007 (#7791, spec §4).
 *
 * - Numérotation `HINV-YYYY-NNNN` séquentielle PAR TENANT ET PAR ANNÉE,
 *   générée serveur dans la transaction de création (verrou pessimiste sur
 *   le dernier numéro + contrainte UNIQUE(company_id, number) en filet :
 *   deux créations concurrentes n'obtiennent jamais le même numéro).
 * - Totaux recalculés SERVEUR (Σ line_total − discount ≥ 0) ; les montants
 *   fournis par le client sont ignorés ; prix unitaire FIGÉ depuis le
 *   catalogue à la création (jamais recalculé ensuite).
 * - Paiements : uniquement sur issued/partially_paid, cumul ≤ total
 *   (sur-paiement 422 HEALTH_OVERPAYMENT), cumul = total → `paid`.
 * - Annulation : jamais sur une facture payée (422) ; une facture émise
 *   n'est JAMAIS supprimée physiquement (annulation seulement).
 *
 * Arithmétique monétaire en CENTIMES entiers (aucun flottant cumulé).
 */
final class HealthInvoiceService
{
    /**
     * Crée une facture BROUILLON : lignes figées + totaux serveur + numéro.
     *
     * @param  array<string, mixed>  $data  payload validé (StoreHealthInvoiceRequest)
     */
    public function createDraft(Employee $actor, array $data): HealthInvoice
    {
        $companyId = (string) $actor->company_id;

        return DB::transaction(function () use ($companyId, $data): HealthInvoice {
            $subtotalCents = 0;
            /** @var list<array{care_act_id: int|null, label: string, unit_price: string, quantity: int, line_total: string}> $lines */
            $lines = [];

            /** @var list<array<string, mixed>> $items */
            $items = $data['items'];

            foreach ($items as $item) {
                $careActId = isset($item['care_act_id']) ? (int) $item['care_act_id'] : null;

                if ($careActId !== null) {
                    // Prix FIGÉ depuis le catalogue — le prix client est ignoré.
                    /** @var HealthCareAct $act */
                    $act = HealthCareAct::query()
                        ->where('company_id', $companyId)
                        ->findOrFail($careActId);

                    $unitCents = self::cents((string) $act->price);
                    $label = isset($item['label']) && is_string($item['label']) && $item['label'] !== ''
                        ? $item['label']
                        : $act->label;
                } else {
                    // Ligne libre : libellé + prix fournis (validés en amont).
                    $unitCents = self::cents((string) $item['unit_price']);
                    $label = (string) $item['label'];
                }

                $quantity = (int) $item['quantity'];
                $lineCents = $unitCents * $quantity;
                $subtotalCents += $lineCents;

                $lines[] = [
                    'care_act_id' => $careActId,
                    'label' => $label,
                    'unit_price' => self::decimal($unitCents),
                    'quantity' => $quantity,
                    'line_total' => self::decimal($lineCents),
                ];
            }

            $discountCents = self::cents((string) ($data['discount'] ?? '0'));
            $totalCents = $subtotalCents - $discountCents;

            if ($totalCents < 0) {
                throw HealthBillingException::discountExceedsSubtotal();
            }

            /** @var HealthInvoice $invoice */
            $invoice = HealthInvoice::query()->create([
                'company_id' => $companyId,
                'number' => $this->nextNumber($companyId),
                'patient_id' => (int) $data['patient_id'],
                'status' => HealthInvoice::STATUS_DRAFT,
                'currency' => strtoupper((string) ($data['currency'] ?? currentCompany()->currency)),
                'subtotal' => self::decimal($subtotalCents),
                'discount' => self::decimal($discountCents),
                'total' => self::decimal($totalCents),
                'amount_paid' => '0.00',
            ]);

            foreach ($lines as $line) {
                HealthInvoiceItem::query()->create(array_merge($line, [
                    'company_id' => $companyId,
                    'invoice_id' => (int) $invoice->getAttribute('id'),
                ]));
            }

            return $invoice;
        });
    }

    /**
     * Émet un brouillon (draft → issued, horodatage `issued_at`).
     */
    public function issue(HealthInvoice $invoice): HealthInvoice
    {
        return DB::transaction(function () use ($invoice): HealthInvoice {
            $locked = $this->lock($invoice);

            if ($locked->status !== HealthInvoice::STATUS_DRAFT) {
                throw HealthBillingException::invalidStatus();
            }

            $locked->update([
                'status' => HealthInvoice::STATUS_ISSUED,
                'issued_at' => now(),
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Encaisse un paiement (issued/partially_paid uniquement, cumul ≤ total).
     *
     * @param  array<string, mixed>  $data  payload validé (PayHealthInvoiceRequest)
     */
    public function pay(HealthInvoice $invoice, array $data): HealthInvoice
    {
        return DB::transaction(function () use ($invoice, $data): HealthInvoice {
            $locked = $this->lock($invoice);

            $payable = [HealthInvoice::STATUS_ISSUED, HealthInvoice::STATUS_PARTIALLY_PAID];

            if (! in_array($locked->status, $payable, true)) {
                throw HealthBillingException::invalidStatus();
            }

            $amountCents = self::cents((string) $data['amount']);
            $paidCents = self::cents($locked->amount_paid);
            $totalCents = self::cents($locked->total);

            if ($paidCents + $amountCents > $totalCents) {
                throw HealthBillingException::overpayment();
            }

            HealthInvoicePayment::query()->create([
                'company_id' => $locked->company_id,
                'invoice_id' => (int) $locked->getAttribute('id'),
                'amount' => self::decimal($amountCents),
                'method' => (string) $data['method'],
                'paid_at' => $data['paid_at'] ?? now(),
                'reference' => isset($data['reference']) ? (string) $data['reference'] : null,
            ]);

            $newPaidCents = $paidCents + $amountCents;

            $locked->update([
                'amount_paid' => self::decimal($newPaidCents),
                'status' => $newPaidCents === $totalCents
                    ? HealthInvoice::STATUS_PAID
                    : HealthInvoice::STATUS_PARTIALLY_PAID,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Annule une facture (jamais une facture payée — spec §4). Une facture
     * émise n'est JAMAIS supprimée physiquement : l'annulation est le seul
     * état terminal accessible.
     */
    public function cancel(HealthInvoice $invoice): HealthInvoice
    {
        return DB::transaction(function () use ($invoice): HealthInvoice {
            $locked = $this->lock($invoice);

            $terminal = [HealthInvoice::STATUS_PAID, HealthInvoice::STATUS_CANCELLED];

            if (in_array($locked->status, $terminal, true)) {
                throw HealthBillingException::invalidStatus();
            }

            $locked->update(['status' => HealthInvoice::STATUS_CANCELLED]);

            return $locked->refresh();
        });
    }

    /**
     * Prochain numéro `HINV-YYYY-NNNN` du tenant pour l'année courante.
     *
     * À appeler DANS une transaction : verrou pessimiste sur la dernière
     * facture de l'année (sérialise les créations concurrentes du tenant) ;
     * la contrainte UNIQUE(company_id, number) reste le filet ultime.
     */
    private function nextNumber(string $companyId): string
    {
        $prefix = sprintf('HINV-%s-', now()->format('Y'));

        /** @var string|null $last */
        $last = HealthInvoice::query()
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->lockForUpdate()
            ->value('number');

        $next = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return sprintf('%s%04d', $prefix, $next);
    }

    /**
     * Relit la facture SOUS VERROU (sérialise issue/pay/cancel concurrents).
     */
    private function lock(HealthInvoice $invoice): HealthInvoice
    {
        /** @var HealthInvoice $locked */
        $locked = HealthInvoice::query()
            ->whereKey($invoice->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $locked;
    }

    /**
     * Montant décimal (chaîne) → centimes entiers (aucun flottant cumulé).
     */
    private static function cents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * Centimes entiers → chaîne décimale `#.##` (colonnes numeric(12,2)).
     */
    private static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
