<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Exceptions\InvalidLetteringException;
use App\Modules\Accounting\Domain\Exceptions\LetteringAlreadyUsedException;
use App\Modules\Accounting\Domain\Exceptions\UnbalancedLetteringException;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lettrage des comptes de tiers — issue #5422.
 *
 * Le lettrage rapproche les débits et les crédits d'un même compte (ex.
 * une facture 411 débit et son encaissement 411 crédit) en posant une
 * lettre commune (`letter`, colonne ajoutée par la migration
 * 2026_08_25_000002) + l'horodatage `lettered_at` (trace d'audit).
 *
 * Gardes (le lettrage est refusé plutôt que d'écrire un rapprochement
 * incohérent) :
 *   - au moins deux écritures sélectionnées ;
 *   - toutes les écritures sur le MÊME compte (sinon
 *     InvalidLetteringException / LETTERING_INVALID) ;
 *   - aucune écriture déjà lettrée avec une AUTRE lettre (sinon
 *     InvalidLetteringException / LETTERING_ALREADY_USED) — re-lettrer avec
 *     la même lettre reste accepté (idempotent) ;
 *   - Σ débits = Σ crédits à 0.005 près (sinon
 *     UnbalancedLetteringException / LETTERING_UNBALANCED).
 *
 * Note : les colonnes `letter`/`lettered_at` ne sont pas (encore) dans le
 * $fillable du modèle AccountingJournalEntry — les écritures sont donc
 * lues via getAttribute() et mises à jour par update() de builder (mass
 * update, qui ne passe pas par $fillable), sans toucher au modèle.
 */
final class LetteringService
{
    private const TOLERANCE = 0.005;

    /**
     * Pose la lettre `letter` sur les écritures `entryIds` (même compte,
     * équilibrées). Retourne la lettre, le nombre d'écritures lettrées et
     * le compte concerné.
     *
     * @param  array<int, int>  $entryIds
     * @return array{letter: string, count: int, account_code: string}
     */
    public function letter(string $companyId, string $letter, array $entryIds): array
    {
        $entryIds = $this->normalizeEntryIds($entryIds);

        $entries = AccountingJournalEntry::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $entryIds)
            ->orderBy('id')
            ->get();

        if ($entries->count() < 2) {
            throw new InvalidLetteringException();
        }

        $accountCodes = $entries->pluck('account_code')->unique();
        if ($accountCodes->count() !== 1) {
            throw new InvalidLetteringException();
        }

        $alreadyLettered = $entries->filter(
            static fn (AccountingJournalEntry $entry): bool => $entry->getAttribute('letter') !== null
                && $entry->getAttribute('letter') !== $letter
        );

        if ($alreadyLettered->isNotEmpty()) {
            throw new LetteringAlreadyUsedException();
        }

        $debit = round((float) $entries->sum('debit'), 2);
        $credit = round((float) $entries->sum('credit'), 2);

        if (abs($debit - $credit) > self::TOLERANCE) {
            throw new UnbalancedLetteringException($debit, $credit);
        }

        $accountCode = (string) $accountCodes->first();

        DB::transaction(function () use ($companyId, $entryIds, $letter): void {
            AccountingJournalEntry::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $entryIds)
                ->update([
                    'letter' => $letter,
                    'lettered_at' => Carbon::now(),
                ]);
        });

        return [
            'letter' => $letter,
            'count' => $entries->count(),
            'account_code' => $accountCode,
        ];
    }

    /**
     * Retire la lettre `letter` de toutes les écritures de l'entreprise qui
     * la portent. Retourne le nombre d'écritures délettrées.
     */
    public function unletter(string $companyId, string $letter): int
    {
        return AccountingJournalEntry::query()
            ->where('company_id', $companyId)
            ->where('letter', $letter)
            ->update([
                'letter' => null,
                'lettered_at' => null,
            ]);
    }

    /**
     * Normalise les identifiants : cast int + déduplication (le doublon
     * d'un même id ne compte pas pour deux écritures).
     *
     * @param  array<int, int>  $entryIds
     * @return list<int>
     */
    private function normalizeEntryIds(array $entryIds): array
    {
        $ids = array_map(static fn (mixed $id): int => (int) $id, $entryIds);

        return array_values(array_unique($ids));
    }
}
