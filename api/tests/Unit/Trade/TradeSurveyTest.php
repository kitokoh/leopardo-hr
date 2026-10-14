<?php

declare(strict_types=1);

namespace Tests\Unit\Trade;

use App\Core\Solutions\Survey\SolutionSurveyEngine;
use App\Modules\Trade\Domain\Survey\TradeSurvey;
use PHPUnit\Framework\TestCase;

/**
 * TRADE-002 (#8477, BC-36 TRADE) — critères d'acceptation du survey « Trade ».
 *
 * Le survey traduit le parcours vitrine : périmètre (International / Domestique),
 * puis rôle. Ces tests verrouillent les trois exigences d'acceptation ainsi que
 * la cohérence des CLÉS i18n avec les catalogues de `api/lang/{fr,en,tr,ar}`.
 *
 * Test PUR (aucune DB, aucun boot Laravel) : il doit rester exécutable partout,
 * y compris sans base — c'est le filet de sécurité du moteur déterministe.
 */
class TradeSurveyTest extends TestCase
{
    private TradeSurvey $survey;

    private SolutionSurveyEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->survey = new TradeSurvey;
        $this->engine = new SolutionSurveyEngine;
    }

    // ── Exigence n° 1 : périmètre International / Domestique ────────────────

    public function test_scope_offers_exactly_international_and_domestic(): void
    {
        $scope = $this->question('scope');

        self::assertSame('select', $scope['type']);
        self::assertSame(
            ['international', 'domestic'],
            array_column($scope['options'], 'value'),
        );
    }

    // ── Exigence n° 2 : importateur / exportateur / les deux ────────────────

    public function test_international_role_offers_importer_exporter_and_both(): void
    {
        $role = $this->question('international_role');

        self::assertSame(
            ['importer', 'exporter', 'both'],
            array_column($role['options'], 'value'),
            'Une entreprise doit pouvoir importer ET exporter (aucun enfermement).',
        );
    }

    // ── Exigence n° 3 : grossiste / vente au détail ─────────────────────────

    public function test_domestic_role_offers_wholesaler_and_retailer(): void
    {
        $role = $this->question('domestic_role');

        self::assertSame(
            ['wholesaler', 'retailer'],
            array_column($role['options'], 'value'),
        );
    }

    // ── Comportement du moteur (règles déterministes) ───────────────────────

    public function test_empty_answers_never_switch_silently_to_domestic(): void
    {
        $keys = $this->suggestKeys([]);

        // Défaut = international + importateur : l'espace acheteur, pas le vendeur.
        self::assertContains('trade_workspace', $keys);
        self::assertContains('importer_workspace', $keys);
        self::assertContains('freight_quotes', $keys);
        self::assertContains('customs_assist', $keys);

        self::assertNotContains('exporter_workspace', $keys);
        self::assertNotContains('wholesale_workspace', $keys);
        self::assertNotContains('retail_workspace', $keys);
    }

    public function test_both_enables_the_two_workspaces(): void
    {
        $result = $this->engine->suggest($this->survey, [
            'scope' => 'international',
            'international_role' => 'both',
        ]);

        $keys = array_column($result['packages'], 'key');

        self::assertContains('exporter_workspace', $keys);
        self::assertContains('importer_workspace', $keys);

        // La règle « les deux » (priorité 95) doit gagner la raison du package.
        $exporter = $this->package($result, 'exporter_workspace');
        self::assertSame('solutions.trade.reason.both', $exporter['reason_key']);
    }

    public function test_domestic_wholesaler_and_retailer_are_mutually_exclusive(): void
    {
        $wholesale = $this->suggestKeys(['scope' => 'domestic', 'domestic_role' => 'wholesaler']);
        self::assertContains('wholesale_workspace', $wholesale);
        self::assertNotContains('retail_workspace', $wholesale);
        self::assertNotContains('importer_workspace', $wholesale);
        self::assertNotContains('exporter_workspace', $wholesale);

        $retail = $this->suggestKeys(['scope' => 'domestic', 'domestic_role' => 'retailer']);
        self::assertContains('retail_workspace', $retail);
        self::assertNotContains('wholesale_workspace', $retail);
    }

    public function test_base_workspace_is_always_required_and_ranked_first(): void
    {
        foreach ([['scope' => 'international'], ['scope' => 'domestic']] as $answers) {
            $result = $this->engine->suggest($this->survey, $answers);

            self::assertSame('trade_workspace', $result['packages'][0]['key']);
            self::assertTrue($result['packages'][0]['required']);
        }
    }

    /**
     * Garde anti-sur-promesse (§3.1 de la spec) : l'app mobile terrain est
     * cataloguée pour l'affichage « bientôt disponible », mais AUCUNE règle ne
     * doit jamais la suggérer comme opérationnelle.
     */
    public function test_mobile_field_is_catalogued_but_never_suggested(): void
    {
        self::assertArrayHasKey('mobile_field', $this->survey->packages());
        self::assertArrayNotHasKey(
            'required',
            $this->survey->packages()['mobile_field'],
            'L\'app mobile terrain n\'est pas livrée : jamais `required`.',
        );

        $allAnswers = [
            'scope' => 'international',
            'international_role' => 'both',
            'domestic_role' => 'wholesaler',
            'needs_customs_help' => true,
            'needs_freight_quotes' => true,
            'needs_quality_control' => true,
        ];

        self::assertNotContains('mobile_field', $this->suggestKeys($allAnswers));
    }

    // ── Parité i18n : catalogue serveur = packages + raisons ────────────────
    //
    // Partage d'autorité, identique à RestaurantSurvey :
    //   - questions + options → catalogue FRONT `SOLUTION_LABELS` (vitrine) ;
    //   - packages + raisons  → catalogue LARAVEL `lang/{fr,en,tr,ar}/solutions.php`
    //     (rendu du PDF pack par SolutionPackPdfGenerator).
    // Le backend n'expose que des CLÉS pour les questions ; il ne duplique pas
    // leurs libellés.

    public function test_package_and_reason_keys_resolve_in_the_four_locale_catalogues(): void
    {
        $keys = $this->packageAndReasonKeys();

        self::assertNotEmpty($keys);

        foreach (['fr', 'en', 'tr', 'ar'] as $locale) {
            $catalogue = require dirname(__DIR__, 3)."/lang/{$locale}/solutions.php";

            foreach ($keys as $key) {
                $value = $this->resolveDotted($catalogue, $key);

                self::assertIsString($value, "[{$locale}] clé manquante ou non scalaire : {$key}");
                self::assertNotSame('', trim($value), "[{$locale}] traduction vide : {$key}");
            }
        }
    }

    /**
     * Garde anti-dérive : le catalogue serveur ne doit PAS porter les blocs
     * `question`/`option` (source unique = catalogue front). Les y réintroduire
     * recrée la clé ambiguë
     *
     *     'question' => ['scope' => '…', 'option' => [...]]
     *
     * où PHP écrase silencieusement la chaîne par le tableau.
     */
    public function test_server_catalogue_does_not_duplicate_question_labels(): void
    {
        foreach (['fr', 'en', 'tr', 'ar'] as $locale) {
            $catalogue = require dirname(__DIR__, 3)."/lang/{$locale}/solutions.php";

            foreach (['question', 'option'] as $block) {
                self::assertArrayNotHasKey(
                    $block,
                    $catalogue['trade'],
                    "[{$locale}] le catalogue serveur ne porte pas les libellés de question.",
                );
            }

            self::assertArrayHasKey('package', $catalogue['trade']);
            self::assertArrayHasKey('reason', $catalogue['trade']);
        }
    }

    public function test_rule_reason_keys_are_all_backed_by_a_package(): void
    {
        $packages = $this->survey->packages();

        foreach ($this->survey->rules() as $rule) {
            self::assertArrayHasKey(
                $rule['package'],
                $packages,
                "Règle orpheline : package [{$rule['package']}] absent du catalogue.",
            );
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** @param  array<string, mixed>  $answers */
    private function suggestKeys(array $answers): array
    {
        return array_column($this->engine->suggest($this->survey, $answers)['packages'], 'key');
    }

    /** @param  array<string, mixed>  $result */
    private function package(array $result, string $key): array
    {
        foreach ($result['packages'] as $package) {
            if ($package['key'] === $key) {
                return $package;
            }
        }

        self::fail("Package [{$key}] absent du résultat.");
    }

    /** @return array<string, mixed> */
    private function question(string $key): array
    {
        foreach ($this->survey->questions() as $question) {
            if ($question['key'] === $key) {
                return $question;
            }
        }

        self::fail("Question [{$key}] absente du survey Trade.");
    }

    /**
     * Clés portées par le catalogue LARAVEL : packages + raisons uniquement.
     *
     * @return list<string>
     */
    private function packageAndReasonKeys(): array
    {
        $keys = array_column($this->survey->packages(), 'label_key');

        foreach ($this->survey->rules() as $rule) {
            $keys[] = $rule['reason_key'];
        }

        return array_values(array_unique($keys));
    }

    /**
     * Résout `solutions.trade.question.scope` dans le tableau Laravel imbriqué
     * `['trade' => ['question' => ['scope' => '…']]]`.
     *
     * @param  array<string, mixed>  $catalogue
     */
    private function resolveDotted(array $catalogue, string $key): mixed
    {
        // Le préfixe `solutions.` est le nom du fichier de traduction.
        $segments = explode('.', preg_replace('/^solutions\./', '', $key));

        $value = $catalogue;
        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
