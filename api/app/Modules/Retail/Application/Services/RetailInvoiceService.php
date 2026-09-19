<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Services;

use App\Modules\Retail\Domain\Enums\RetailOrderStatus;
use App\Modules\Retail\Domain\Models\RetailInvoiceSequence;
use App\Modules\Retail\Domain\Models\RetailOrder;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * BC-17 RETAIL (#7813) — Numerotation LEGALE des factures de vente.
 *
 * Attribue un numero de facture `FAC-YYYY-NNNNNN` par tenant : compteur
 * dedie `retail_invoice_sequences` (un par company_id + annee civile),
 * incremente sous TRANSACTION avec `lockForUpdate` — deux generations
 * concurrentes ne peuvent JAMAIS produire le meme numero, et la sequence
 * est continue (sans trou : le numero n'est consomme qu'au commit qui
 * l'ecrit sur la commande).
 *
 * Le numero est attribue a la PREMIERE generation de la facture puis
 * STABLE : tout appel ulterieur retourne la commande telle quelle
 * (idempotence). Seules les commandes `completed` sont facturables (une
 * facture legale ne peut pas porter sur un brouillon ni une annulation
 * posterieure a l'emission — la facture emise reste valable, l'avoir est
 * hors perimetre v1).
 *
 * Concu pour TOUTES les sources de commandes (`pos` aujourd'hui, `online`
 * quand la boutique web arrivera — PR #7817) : aucune dependance a la
 * session de caisse, seule la commande compte.
 *
 * Pas de facade Laravel ici (purete de couche Application, garde #6568) :
 * la connexion est injectee via ConnectionInterface.
 */
final class RetailInvoiceService
{
    /** Prefixe du numero legal (format `FAC-YYYY-NNNNNN`). */
    public const NUMBER_PREFIX = 'FAC';

    /** Largeur du compteur dans le numero (zero-padded). */
    public const NUMBER_PAD = 6;

    public function __construct(private readonly ConnectionInterface $connection) {}

    /**
     * Attribue le numero legal a la commande si elle n'en a pas encore,
     * puis retourne la commande a jour. Idempotent : un numero deja
     * attribue n'est JAMAIS modifie.
     *
     * @throws ValidationException 422 si la commande n'est pas `completed`.
     */
    public function ensureInvoiceNumber(RetailOrder $order): RetailOrder
    {
        if ($order->invoice_number !== null) {
            return $order;
        }

        if ($order->status !== RetailOrderStatus::Completed) {
            throw ValidationException::withMessages([
                'order' => 'Only completed orders can be invoiced.',
            ]);
        }

        /** @var RetailOrder $invoiced */
        $invoiced = $this->connection->transaction(function () use ($order): RetailOrder {
            // Relecture verrouillee de la commande : si un appel concurrent
            // vient d'attribuer le numero, on le retourne tel quel (stable).
            /** @var RetailOrder $fresh */
            $fresh = RetailOrder::query()
                ->where('company_id', (string) $order->company_id)
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($fresh->invoice_number !== null) {
                return $fresh;
            }

            $issuedAt = Carbon::now();
            $number = $this->nextNumber((string) $fresh->company_id, (int) $issuedAt->year);

            $fresh->forceFill([
                'invoice_number' => $number,
                'invoiced_at' => $issuedAt,
            ])->save();

            return $fresh;
        });

        return $invoiced;
    }

    /**
     * Reserve le prochain numero du compteur (tenant, annee) sous verrou.
     *
     * DOIT etre appele DANS une transaction : le `lockForUpdate` serialise
     * les increments concurrents, l'unique `(company_id, year)` couvre la
     * course sur la creation initiale du compteur (la violation est isolee
     * dans un savepoint puis la ligne gagnante est relue — pattern #6954).
     */
    private function nextNumber(string $companyId, int $year): string
    {
        $sequence = $this->lockSequence($companyId, $year);

        if (! $sequence instanceof RetailInvoiceSequence) {
            try {
                // Transaction IMBRIQUEE (savepoint) : si un concurrent cree
                // la ligne en meme temps, la violation d'unicite est
                // rollbackee SANS avorter la transaction englobante.
                $this->connection->transaction(function () use ($companyId, $year): void {
                    RetailInvoiceSequence::query()->create([
                        'company_id' => $companyId,
                        'year' => $year,
                        'next_number' => 1,
                    ]);
                });
            } catch (QueryException) {
                // Le concurrent a gagne la course : sa ligne existe, on la
                // verrouille ci-dessous.
            }

            $sequence = $this->lockSequence($companyId, $year);

            if (! $sequence instanceof RetailInvoiceSequence) {
                // Course perdue ET ligne invisible : etat inattendu.
                throw new \RuntimeException(sprintf(
                    'Unable to acquire retail invoice sequence for company %s year %d.',
                    $companyId,
                    $year,
                ));
            }
        }

        $reserved = $sequence->next_number;
        $sequence->next_number = $reserved + 1;
        $sequence->save();

        return sprintf(
            '%s-%d-%s',
            self::NUMBER_PREFIX,
            $year,
            str_pad((string) $reserved, self::NUMBER_PAD, '0', STR_PAD_LEFT),
        );
    }

    private function lockSequence(string $companyId, int $year): ?RetailInvoiceSequence
    {
        /** @var RetailInvoiceSequence|null $sequence */
        $sequence = RetailInvoiceSequence::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        return $sequence;
    }
}
