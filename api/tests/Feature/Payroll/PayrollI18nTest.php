<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Payroll\Infrastructure\Services\PayrollLineLabels;
use Tests\TestCase;

/**
 * Issue #5257 — i18n paie ×4 : parité des catalogues, localisation des noms
 * de lignes de bulletin et stabilité des messages API (locale par défaut EN).
 *
 * Garde anti-régression : tout littéral utilisateur ajouté au module Payroll
 * doit passer par `__('payroll.*')` — le scan CI dédié (check-payroll-i18n.py)
 * bloque les régressions ; ce test fige la parité ×4 et le mapping des labels.
 */
class PayrollI18nTest extends TestCase
{
    private const LANGS = ['fr', 'en', 'tr', 'ar'];

    /**
     * @return list<array{string}>
     */
    public static function langsProvider(): array
    {
        return array_map(fn (string $lang): array => [$lang], self::LANGS);
    }

    /**
     * Parité des clés ×4 : chaque catalogue payroll.php porte exactement les
     * mêmes clés (DoD « Parité des clés ×4 »).
     */
    public function test_payroll_catalog_keys_are_identical_across_all_four_locales(): void
    {
        $keySets = [];
        foreach (self::LANGS as $lang) {
            $content = (string) file_get_contents(lang_path("{$lang}/payroll.php"));
            preg_match_all("/^\s*'([a-z0-9_]+)'\s*=>/m", $content, $matches);
            $keySets[$lang] = $matches[1];
        }

        $base = $keySets['fr'];
        sort($base);

        foreach (self::LANGS as $lang) {
            $keys = $keySets[$lang];
            sort($keys);
            $this->assertSame($base, $keys, "payroll.php ({$lang}) : clés divergentes.");
        }

        $this->assertGreaterThanOrEqual(70, count($base), 'Le catalogue payroll.* a régressé sous 70 clés.');
    }

    /**
     * Les noms de lignes du moteur (libellés figés FR) sont localisés via
     * PayrollLineLabels ; un libellé inconnu (composant personnalisé) retombe
     * sur le libellé brut sans erreur.
     */
    public function test_line_labels_are_localized_with_fallback(): void
    {
        app()->setLocale('en');

        $this->assertSame('Base salary', PayrollLineLabels::label('Salaire de base'));
        $this->assertSame('Overtime', PayrollLineLabels::label('Heures supplémentaires'));
        $this->assertSame('Paid leave indemnity', PayrollLineLabels::label('Indemnité de congés payés'));
        $this->assertSame('Income tax', PayrollLineLabels::label('Impot sur le revenu'));
        $this->assertSame('Employer contributions', PayrollLineLabels::label('Cotisations patronales'));

        // Fallback : libellé personnalisé (composant métier) → libellé brut.
        $this->assertSame('Prime terrain', PayrollLineLabels::label('Prime terrain'));
    }

    /**
     * Les messages API localisés (locale par défaut EN) restent identiques aux
     * littéraux historiques — les clients qui matchent les messages ne cassent
     * pas ; la version FR/TR/AR est servie selon la locale de la requête.
     */
    public function test_api_messages_keep_english_default_semantics(): void
    {
        app()->setLocale('en');

        $this->assertSame('Payroll run cannot be recalculated in current status.', __('payroll.run_cannot_recalculate'));
        $this->assertSame('Bulk payment processing started.', __('payroll.bulk_started'));
        $this->assertSame('Advance must be manager-approved before declaring payment.', __('payroll.advance_manager_approve_first'));
        $this->assertSame('Payroll run not found.', __('payroll.run_not_found'));
        $this->assertSame('Tax slab deleted successfully.', __('payroll.tax_slab_deleted'));
    }

    /**
     * Les messages localisés existent aussi dans les 3 autres locales
     * (parité de contenu — pas seulement de clés).
     */
    public function test_key_messages_translated_in_all_locales(): void
    {
        foreach (['bulk_started', 'run_not_found', 'advance_manager_approve_first', 'line_base_salary'] as $key) {
            foreach (self::LANGS as $lang) {
                $value = trans("payroll.{$key}", [], $lang);
                $this->assertNotSame("payroll.{$key}", $value, "payroll.{$key} manquant en {$lang}.");
            }
        }
    }
}
