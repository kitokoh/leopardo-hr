<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Services;

use App\Modules\Accounting\Domain\Enums\JournalType;
use App\Modules\Accounting\Domain\Exceptions\PeriodClosedException;
use App\Modules\Accounting\Domain\Exceptions\UnbalancedJournalEntryException;
use App\Modules\Accounting\Domain\Models\AccountingChartAccount;
use App\Modules\Accounting\Domain\Models\AccountingClosedPeriod;
use App\Modules\Accounting\Domain\Models\AccountingJournal;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Domain\Models\AccountingManualEntry;
use App\Modules\Accounting\Infrastructure\Services\ChartAccountResolver;
use App\Modules\Accounting\Infrastructure\Services\JournalResolver;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Saisie d'écriture manuelle — journal d'opérations diverses (issue #9369).
 *
 * Le module n'offrait **aucune** saisie libre : toutes les écritures étaient
 * dérivées de documents ou de paiements par `JournalPostingService`. Il était
 * donc impossible de comptabiliser une écriture d'ouverture, une régularisation
 * de TVA, une charge à payer (408), une dotation, un virement interne 512 ↔ 53
 * ou une correction d'erreur — ce qui rend le module inexploitable par un
 * comptable, malgré un plan comptable et des états financiers corrects.
 *
 * Différences assumées avec le posting dérivé :
 *
 *  - une écriture manuelle est **multi-lignes sur un même compte** : le
 *    discriminant `line_no` lève la contrainte d'idempotence
 *    `(company, source, source_id, account_code)` ;
 *  - elle est **supprimable et modifiable** (les écritures dérivées, non : elles
 *    se re-postent) ;
 *  - ses comptes sont **validés contre le plan comptable du tenant** — un
 *    compte inexistant ou inactif est refusé, là où le posting dérivé crée le
 *    compte implicitement.
 */
final class ManualJournalEntryService
{
    /** Tolérance d'équilibre, identique à celle du posting dérivé. */
    private const TOLERANCE = 0.005;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly ?JournalResolver $journals = null,
    ) {}

    /**
     * Crée une écriture manuelle équilibrée.
     *
     * @param  array{entry_date: string, description?: string|null, journal_code?: string|null, piece?: string|null, lines: list<array{account_code: string, debit?: float|int|string, credit?: float|int|string, description?: string|null}>}  $payload
     * @return array{manual_ref: string, piece: string, lines: int}
     */
    public function create(string $companyId, array $payload, ?string $createdBy = null): array
    {
        $entryDate = Carbon::parse($payload['entry_date'])->startOfDay();
        $this->guardPeriodOpen($companyId, $entryDate);

        $lines = $this->normaliseLines($companyId, $payload['lines']);
        $this->guardBalanced($lines);

        $journalCode = $this->resolveJournalCode($companyId, $payload['journal_code'] ?? null);
        $piece = $payload['piece'] ?? $this->nextPiece($companyId, $entryDate);

        $manualRef = (string) Str::uuid();

        $this->db->transaction(function () use ($companyId, $manualRef, $entryDate, $journalCode, $piece, $payload, $createdBy, $lines): void {
            /** @var AccountingManualEntry $manual */
            $manual = AccountingManualEntry::create([
                'company_id' => $companyId,
                'manual_ref' => $manualRef,
                'entry_date' => $entryDate,
                'period' => $entryDate->format('Y-m'),
                'journal_code' => $journalCode,
                'piece' => $piece,
                'description' => $payload['description'] ?? null,
                'created_by' => $createdBy,
            ]);

            $this->writeLines($manual, $lines, $journalCode);
        });

        return ['manual_ref' => $manualRef, 'piece' => $piece, 'lines' => count($lines)];
    }

    /**
     * Remplace intégralement les lignes d'une écriture manuelle.
     *
     * La modification est **totale** (et non ligne à ligne) : c'est le seul
     * moyen de garantir l'équilibre débit/crédit du groupe après édition.
     *
     * @param  array{entry_date: string, description?: string|null, journal_code?: string|null, lines: list<array{account_code: string, debit?: float|int|string, credit?: float|int|string, description?: string|null}>}  $payload
     */
    public function update(AccountingManualEntry $manual, array $payload): void
    {
        // Ni l'ancienne ni la nouvelle date ne doivent tomber dans une période
        // clôturée : sinon on sortirait une écriture d'un exercice arrêté.
        $this->guardPeriodOpen((string) $manual->company_id, $manual->entry_date);

        $entryDate = Carbon::parse($payload['entry_date'])->startOfDay();
        $this->guardPeriodOpen((string) $manual->company_id, $entryDate);

        $lines = $this->normaliseLines((string) $manual->company_id, $payload['lines']);
        $this->guardBalanced($lines);

        $journalCode = $this->resolveJournalCode(
            (string) $manual->company_id,
            $payload['journal_code'] ?? $manual->journal_code,
        );

        $this->db->transaction(function () use ($manual, $entryDate, $journalCode, $payload, $lines): void {
            AccountingJournalEntry::query()
                ->where('company_id', $manual->company_id)
                ->where('source_type', 'manual')
                ->where('manual_ref', $manual->manual_ref)
                ->delete();

            $manual->fill([
                'entry_date' => $entryDate,
                'period' => $entryDate->format('Y-m'),
                'journal_code' => $journalCode,
                'description' => $payload['description'] ?? $manual->description,
            ])->save();

            $this->writeLines($manual, $lines, $journalCode);
        });
    }

    /**
     * Supprime une écriture manuelle (lignes + en-tête).
     */
    public function delete(AccountingManualEntry $manual): void
    {
        $this->guardPeriodOpen((string) $manual->company_id, $manual->entry_date);

        $this->db->transaction(function () use ($manual): void {
            AccountingJournalEntry::query()
                ->where('company_id', $manual->company_id)
                ->where('source_type', 'manual')
                ->where('manual_ref', $manual->manual_ref)
                ->delete();

            $manual->delete();
        });
    }

    /**
     * Refuse toute écriture dans une période clôturée (409 côté API).
     */
    private function guardPeriodOpen(string $companyId, Carbon $entryDate): void
    {
        $period = $entryDate->format('Y-m');

        $closed = AccountingClosedPeriod::query()
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->exists();

        if ($closed) {
            throw new PeriodClosedException($period);
        }
    }

    /**
     * Contrôle le plan comptable du tenant : chaque code doit exister et être
     * actif. Si le tenant n'a encore aucun plan comptable chargé, on tolère les
     * comptes du référentiel pays (le plan est alors provisionné par le posting)
     * — mais jamais un code inventé.
     *
     * @param  list<array{account_code: string, debit?: float|int|string, credit?: float|int|string, description?: string|null}>  $lines
     * @return list<array{account: string, account_label: string, debit: float, credit: float, description: string|null}>
     */
    private function normaliseLines(string $companyId, array $lines): array
    {
        $accounts = AccountingChartAccount::query()
            ->where('company_id', $companyId)
            ->get(['code', 'label', 'is_active'])
            ->keyBy('code');

        $normalised = [];

        foreach ($lines as $index => $line) {
            $code = (string) $line['account_code'];
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            // Une ligne est soit au débit, soit au crédit — jamais les deux
            // (la base porte aussi une contrainte CHECK en ce sens).
            if ($debit > 0 && $credit > 0) {
                throw new \App\Modules\Accounting\Domain\Exceptions\DocumentWorkflowException(
                    __('accounting.errors.manual_line_debit_credit_exclusive', ['line' => $index + 1])
                );
            }

            if ($debit === 0.0 && $credit === 0.0) {
                throw new \App\Modules\Accounting\Domain\Exceptions\DocumentWorkflowException(
                    __('accounting.errors.manual_line_empty', ['line' => $index + 1])
                );
            }

            /** @var AccountingChartAccount|null $account */
            $account = $accounts->get($code);

            if ($accounts->isNotEmpty()) {
                if ($account === null) {
                    throw new \App\Modules\Accounting\Domain\Exceptions\DocumentWorkflowException(
                        __('accounting.errors.manual_account_unknown', ['code' => $code])
                    );
                }

                if (! $account->is_active) {
                    throw new \App\Modules\Accounting\Domain\Exceptions\DocumentWorkflowException(
                        __('accounting.errors.manual_account_inactive', ['code' => $code])
                    );
                }
            }

            $normalised[] = [
                'account' => $code,
                'account_label' => (string) ($account->label ?? $code),
                'debit' => $debit,
                'credit' => $credit,
                'description' => $line['description'] ?? null,
            ];
        }

        return $normalised;
    }

    /**
     * @param  list<array{account: string, account_label: string, debit: float, credit: float, description: string|null}>  $lines
     */
    private function guardBalanced(array $lines): void
    {
        $debit = round((float) array_sum(array_column($lines, 'debit')), 2);
        $credit = round((float) array_sum(array_column($lines, 'credit')), 2);

        if (abs($debit - $credit) > self::TOLERANCE) {
            throw new UnbalancedJournalEntryException($debit, $credit, 'manual');
        }
    }

    /**
     * Journal cible : le code fourni s'il est actif chez le tenant, sinon le
     * journal d'opérations diverses (jamais un journal de vente pour un OD).
     */
    private function resolveJournalCode(string $companyId, ?string $requested): string
    {
        $journals = ($this->journals ?? new JournalResolver)->journalsFor($companyId);

        // Entreprise antérieure à l'entité journal (#9370) : on ne peut pas
        // valider un code contre une liste vide, et refuser une mise à jour
        // d'écriture existante serait absurde. On provisionne alors les
        // journaux du référentiel, puis on reprend la résolution normale.
        if ($journals === []) {
            $company = \App\Core\Tenant\Domain\Models\Company::query()
                ->withoutGlobalScopes()
                ->find($companyId);

            $resolver = $company !== null
                ? ChartAccountResolver::forCompany($company)
                : ChartAccountResolver::forCountry(null);

            ($this->journals ?? new JournalResolver)
                ->seedDefaults($companyId, $company?->country, $resolver);

            $journals = ($this->journals ?? new JournalResolver)->journalsFor($companyId);
        }

        if (is_string($requested) && $requested !== '') {
            foreach ($journals as $journal) {
                if ($journal->code === $requested && $journal->is_active) {
                    return $requested;
                }
            }

            // Un code demandé mais inconnu/inactif ne doit pas être ignoré en
            // silence : l'utilisateur croit avoir choisi son journal.
            throw new \App\Modules\Accounting\Domain\Exceptions\DocumentWorkflowException(
                __('accounting.errors.manual_journal_unknown', ['code' => $requested])
            );
        }

        foreach ($journals as $journal) {
            if ($journal->type === JournalType::Misc->value) {
                return (string) $journal->code;
            }
        }

        return 'OD';
    }

    /**
     * Numéro de pièce séquentiel par mois : `OD-202608-0001`.
     *
     * Le compteur se calcule sur le plus grand numéro existant du mois plutôt
     * que sur un COUNT (qui se répète après une suppression et produirait deux
     * fois la même pièce).
     */
    private function nextPiece(string $companyId, Carbon $entryDate): string
    {
        $prefix = 'OD-'.$entryDate->format('Ym').'-';

        $last = AccountingJournalEntry::query()
            ->where('company_id', $companyId)
            ->where('source_type', 'manual')
            ->where('piece', 'like', $prefix.'%')
            ->orderByDesc('piece')
            ->value('piece');

        $sequence = 1;

        if (is_string($last) && str_starts_with($last, $prefix)) {
            $sequence = (int) substr($last, strlen($prefix)) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array{account: string, account_label: string, debit: float, credit: float, description: string|null}>  $lines
     */
    private function writeLines(AccountingManualEntry $manual, array $lines, string $journalCode): void
    {
        $lineNo = 1;

        foreach ($lines as $line) {
            AccountingJournalEntry::create([
                'company_id' => $manual->company_id,
                'entry_date' => $manual->entry_date,
                'period' => $manual->period,
                'journal_code' => $journalCode,
                'line_no' => $lineNo,
                'manual_ref' => $manual->manual_ref,
                'source_type' => 'manual',
                'source_id' => (int) $manual->id,
                'account_code' => $line['account'],
                'account_label' => $line['account_label'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'piece' => $manual->piece,
                'description' => $line['description'] ?? $manual->description,
            ]);

            $lineNo++;
        }
    }
}
