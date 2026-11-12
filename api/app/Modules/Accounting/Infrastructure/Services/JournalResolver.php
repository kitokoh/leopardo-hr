<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Enums\JournalType;
use App\Modules\Accounting\Domain\Enums\PaymentMethod;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingJournal;

/**
 * Résolution et provisionnement des journaux comptables — issue #9370.
 *
 * Deux responsabilités, volontairement séparées du posting :
 *
 *   1. **Seed par pays** : chaque référentiel nomme ses journaux différemment
 *      (`VE/AC/BQ/CA` en PCG, `VE/AC/BQ/CA/OD` en OHADA, `SATIS/ALIS/BANKA/KASA`
 *      en Tekdüzen, `SAL/PUR/BNK/CSH` en plans anglo-saxons). Le seed s'appuie
 *      sur la famille de référentiel déjà utilisée pour le plan comptable, afin
 *      qu'un tenant turc reçoive des codes de journal turcs.
 *
 *   2. **Résolution par source** : une écriture dérivée d'un document de vente
 *      va au journal des ventes, un document d'achat au journal des achats, un
 *      paiement au journal de trésorerie correspondant au moyen de paiement.
 *      C'est ce qui remplace l'heuristique sur le préfixe de compte.
 */
final class JournalResolver
{
    /**
     * Journaux par défaut d'une famille de référentiel.
     *
     * `type` → [code, libellé, famille de compte de trésorerie par défaut].
     * L'ordre du tableau est l'ordre d'affichage.
     *
     * @var array<string, array<string, array{0: string, 1: string, 2: string|null}>>
     */
    public const DEFAULT_JOURNALS = [
        AccountingChartOfAccounts::FAMILY_PCG => [
            JournalType::Sale->value => ['VE', 'Journal des ventes', null],
            JournalType::Purchase->value => ['AC', 'Journal des achats', null],
            JournalType::Bank->value => ['BQ', 'Journal de banque', 'bank'],
            JournalType::Cash->value => ['CA', 'Journal de caisse', 'cash'],
            JournalType::Payroll->value => ['PA', 'Journal de paie', null],
            JournalType::Misc->value => ['OD', 'Opérations diverses', null],
        ],
        AccountingChartOfAccounts::FAMILY_SYSCOHADA => [
            JournalType::Sale->value => ['VE', 'Journal des ventes', null],
            JournalType::Purchase->value => ['AC', 'Journal des achats', null],
            JournalType::Bank->value => ['BQ', 'Journal de banque', 'bank'],
            JournalType::Cash->value => ['CA', 'Journal de caisse', 'cash'],
            JournalType::Payroll->value => ['PA', 'Journal de paie', null],
            JournalType::Misc->value => ['OD', 'Opérations diverses', null],
        ],
        // Tekdüzen Hesap Planı — SATIS (satış), ALIS (alış), BANKA, KASA, MAHSUP.
        AccountingChartOfAccounts::FAMILY_TEKDUZEN => [
            JournalType::Sale->value => ['SATIS', 'Satış günlüğü', null],
            JournalType::Purchase->value => ['ALIS', 'Alış günlüğü', null],
            JournalType::Bank->value => ['BANKA', 'Banka günlüğü', 'bank'],
            JournalType::Cash->value => ['KASA', 'Kasa günlüğü', 'cash'],
            JournalType::Payroll->value => ['PERS', 'Personel günlüğü', null],
            JournalType::Misc->value => ['MAHSUP', 'Mahsup günlüğü', null],
        ],
        // Plans anglo-saxons (UK / US / CA) : SAL, PUR, BNK, CSH, PAY, GEN.
        AccountingChartOfAccounts::FAMILY_UK => [
            JournalType::Sale->value => ['SAL', 'Sales journal', null],
            JournalType::Purchase->value => ['PUR', 'Purchase journal', null],
            JournalType::Bank->value => ['BNK', 'Bank journal', 'bank'],
            JournalType::Cash->value => ['CSH', 'Cash journal', 'cash'],
            JournalType::Payroll->value => ['PAY', 'Payroll journal', null],
            JournalType::Misc->value => ['GEN', 'General journal', null],
        ],
    ];

    /**
     * Journaux par défaut d'un pays (famille de référentiel résolue par
     * `AccountingChartOfAccounts::familyFor()`).
     *
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    public static function defaultsFor(?string $country): array
    {
        $family = AccountingChartOfAccounts::familyFor($country);

        return self::DEFAULT_JOURNALS[$family] ?? self::DEFAULT_JOURNALS[AccountingChartOfAccounts::FAMILY_PCG];
    }

    /**
     * Provisionne les journaux par défaut d'une entreprise (idempotent).
     *
     * Appelé à l'activation du module et de façon paresseuse par
     * `resolveFor()` : une entreprise créée avant cette évolution reçoit ses
     * journaux au premier posting, sans script de migration de données.
     *
     * @return array<string, string> type => code
     */
    public function seedDefaults(string $companyId, ?string $country, ChartAccountResolver $resolver): array
    {
        /** @var array<string, string> $codes */
        $codes = [];

        foreach (self::defaultsFor($country) as $type => $definition) {
            [$code, $label, $treasuryFamily] = $definition;

            AccountingJournal::query()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                [
                    'label' => $label,
                    'type' => $type,
                    'default_account_code' => $treasuryFamily !== null ? $resolver->accountCode($treasuryFamily) : null,
                    'is_active' => true,
                ],
            );

            $codes[$type] = $code;
        }

        return $codes;
    }

    /**
     * Code du journal d'un ensemble d'écritures dérivé du type de document.
     *
     * @param  list<AccountingJournal>  $journals
     */
    public function forDocumentType(array $journals, DocumentType $type): string
    {
        $wanted = $type->isPurchase() ? JournalType::Purchase : JournalType::Sale;

        return $this->codeOf($journals, $wanted);
    }

    /**
     * Code du journal de trésorerie d'un moyen de paiement.
     *
     * @param  list<AccountingJournal>  $journals
     */
    public function forPaymentMethod(array $journals, PaymentMethod $method): string
    {
        $wanted = $method === PaymentMethod::Cash ? JournalType::Cash : JournalType::Bank;

        return $this->codeOf($journals, $wanted);
    }

    /**
     * Journaux actifs d'une entreprise, indexés par type.
     *
     * @return list<AccountingJournal>
     */
    public function journalsFor(string $companyId): array
    {
        /** @var list<AccountingJournal> $journals */
        $journals = AccountingJournal::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->all();

        return $journals;
    }

    /**
     * Recherche un journal par type ; retombe sur le journal d'opérations
     * diverses, puis sur un code littéral, pour ne **jamais** échouer un
     * posting parce qu'un journal n'a pas été provisionné.
     *
     * @param  list<AccountingJournal>  $journals
     */
    private function codeOf(array $journals, JournalType $wanted): string
    {
        foreach ($journals as $journal) {
            if ($journal->type === $wanted->value) {
                return (string) $journal->code;
            }
        }

        foreach ($journals as $journal) {
            if ($journal->type === JournalType::Misc->value) {
                return (string) $journal->code;
            }
        }

        return 'OD';
    }

    /**
     * Code de journal d'un document, en tenant compte du cas particulier des
     * avoirs : un avoir suit le journal de la facture qu'il annule.
     */
    public function forDocument(AccountingDocument $document): string
    {
        $journals = $this->journalsFor((string) $document->company_id);

        $type = DocumentType::tryFrom((string) $document->type) ?? DocumentType::Invoice;

        return $this->forDocumentType($journals, $type);
    }
}
