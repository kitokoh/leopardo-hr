<?php

declare(strict_types=1);

namespace Tests\Unit\Trade;

use App\Core\Feature\Domain\ModuleRegistry;
use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Enums\SolutionIndustry;
use App\Core\Solutions\SolutionCatalogue;
use App\Core\Solutions\Survey\SolutionSurveyRegistry;
use App\Http\Middleware\Trade\TradeRoleResolver;
use App\Modules\Trade\Domain\Manifests\TradeManifest;
use App\Modules\Trade\Domain\Survey\TradeSurvey;
use PHPUnit\Framework\TestCase;

/**
 * TRADE-002 (#8477, BC-36 TRADE) — garde de cohérence de la verticale Trade.
 *
 * Verrouille les invariants que la leçon #7220/#7235 a rendus critiques : une
 * verticale n'est opérationnelle que si son code est déclaré aux TROIS points
 * d'enregistrement obligatoires, et si les objets qui doivent partager ce code
 * (manifest, survey, matrice de rôles) ne divergent pas silencieusement.
 *
 * Test PUR (aucune DB, aucun boot Laravel) : il reste exécutable partout, y
 * compris sans base — c'est le filet de sécurité des critères d'acceptation 4,
 * 5, 6 et 8 de la mission.
 */
class TradeVerticalConsistencyTest extends TestCase
{
    private TradeManifest $manifest;

    private TradeSurvey $survey;

    private ModuleRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifest = new TradeManifest;
        $this->survey = new TradeSurvey;
        $this->registry = new ModuleRegistry;
    }

    // ── Conformité du manifest au contrat Core v2 ───────────────────────────

    public function test_manifest_is_conform_to_the_core_contract_v2(): void
    {
        self::assertInstanceOf(SolutionManifest::class, $this->manifest);
        self::assertSame('trade', $this->manifest->code());
        self::assertNotSame('', $this->manifest->name());
        self::assertSame(SolutionIndustry::InternationalTrade, $this->manifest->industry());
        self::assertContains(
            $this->manifest->maturity(),
            ['pilot', 'production', 'placeholder'],
            'Maturité hors vocabulaire du contrat Core v2.',
        );
        self::assertNotSame('', $this->manifest->description());

        $permissions = $this->manifest->permissions();
        self::assertNotSame([], $permissions, 'Aucune permission déclarée.');

        foreach ($permissions as $code => $label) {
            self::assertIsString($code, 'Clé de permission non string (liste legacy ?).');
            self::assertIsString($label, "Libellé non string pour {$code}.");
            self::assertNotSame('', trim($label), "Libellé vide pour {$code}.");
            self::assertStringStartsWith('trade.', $code, "Permission hors espace de noms trade : {$code}.");
        }

        foreach (['requiredModules', 'optionalModules', 'sensitiveData'] as $method) {
            foreach ($this->manifest->{$method}() as $entry) {
                self::assertIsString($entry, "Entrée non string dans {$method}().");
                self::assertNotSame('', trim($entry), "Entrée vide dans {$method}().");
            }
        }
    }

    /**
     * Les données commerciales et documentaires doivent être déclarées
     * sensibles : le superadmin ne doit jamais y accéder par défaut
     * (mission § Espace superadmin, dernier alinéa).
     */
    public function test_sensitive_data_declaration_covers_commercial_and_document_data(): void
    {
        $sensitive = $this->manifest->sensitiveData();

        foreach (['commercial_terms', 'pricing', 'trade_documents'] as $expected) {
            self::assertContains(
                $expected,
                $sensitive,
                "Donnée sensible [{$expected}] non déclarée : la protection par défaut du superadmin ne s'applique pas.",
            );
        }
    }

    // ── Les trois points d'enregistrement obligatoires (leçon #7220) ────────

    public function test_trade_is_declared_in_the_declarative_module_registry(): void
    {
        self::assertTrue($this->registry->isKnown('trade'), 'Clé `trade` absente du ModuleRegistry.');

        $entry = $this->registry->entry('trade');
        self::assertNotNull($entry);
        self::assertSame('solution', $entry['kind']);
        self::assertSame('solution', $entry['scope']);
        self::assertFalse($entry['default'], 'La verticale doit rester fail-closed (défaut false).');
        self::assertTrue($this->registry->isKillable('trade'), 'Une verticale doit être kill-switchable.');

        self::assertContains(
            'trade',
            $this->registry->knownModules(),
            'Clé `trade` absente de l’allowlist knownModules().',
        );
    }

    public function test_trade_flag_is_declared_in_the_legacy_feature_flag_registry(): void
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 3).'/config/feature-flags.php';

        self::assertArrayHasKey('flags', $config);
        self::assertArrayHasKey(
            'trade',
            $config['flags'],
            'Clé `trade` absente de config/feature-flags.php (projection legacy du dual-read).',
        );

        self::assertSame('solution', $config['flags']['trade']['scope']);
        self::assertFalse($config['flags']['trade']['default'], 'Fail-closed : le défaut doit rester false.');
        self::assertTrue($config['flags']['trade']['killable']);
    }

    /**
     * Troisième point : `Company::KNOWN_MODULES`. Il vit dans un modèle Eloquent
     * (non instanciable sans boot), donc la garde lit la CONSTANTE à la source.
     *
     * C'est exactement le point dont l'oubli a provoqué les incidents #7220 /
     * #7235 : sans cette entrée, l'admin plateforme ne peut JAMAIS activer la
     * verticale, alors que le ModuleRegistry la déclare connue.
     */
    public function test_trade_is_listed_in_the_company_known_modules_constant(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3).'/app/Core/Tenant/Domain/Models/Company.php');
        self::assertIsString($source);

        $start = strpos($source, 'public const KNOWN_MODULES');
        self::assertNotFalse($start, 'Constante KNOWN_MODULES introuvable.');

        $end = strpos($source, '];', $start);
        self::assertNotFalse($end, 'Fin de la constante KNOWN_MODULES introuvable.');

        $constSource = substr($source, $start, $end - $start);

        self::assertMatchesRegularExpression(
            "/^[[:space:]]*'trade',[[:space:]]*$/m",
            $constSource,
            'Clé `trade` absente de Company::KNOWN_MODULES — l’admin plateforme ne pourra pas activer la verticale (leçon #7220/#7235).',
        );
    }

    // ── Cohérence manifest ↔ survey ↔ rôles ────────────────────────────────

    /**
     * Critère d'acceptation n° 4 : le survey et le manifest décrivent la MÊME
     * solution. Un code divergent ferait répondre le survey sur une solution
     * que le catalogue ne sait pas activer.
     */
    public function test_survey_code_matches_the_manifest_code(): void
    {
        self::assertSame($this->manifest->code(), $this->survey->code());
        self::assertSame('trade', $this->survey->code());
    }

    /**
     * Critère d'acceptation n° 5 : les rôles fonctionnels de la verticale ne
     * remplacent pas le RBAC plateforme, et chaque rôle de la matrice est adossé
     * à une permission réellement déclarée au manifest.
     *
     * La correspondance est bijective : un rôle sans permission serait
     * inattribuable, une permission sans rôle serait morte.
     */
    public function test_role_matrix_maps_exactly_onto_the_manifest_permissions(): void
    {
        $declared = array_keys($this->manifest->permissions());

        $fromRoles = array_map(
            static fn (string $role): string => "trade.{$role}",
            TradeRoleResolver::SUPPORTED_ROLES,
        );

        sort($declared);
        sort($fromRoles);

        self::assertSame(
            $declared,
            $fromRoles,
            'Divergence entre TradeRoleResolver::SUPPORTED_ROLES et TradeManifest::permissions().',
        );
    }

    /**
     * Le survey doit être résoluble par le registre sous le code du manifest —
     * c'est le chemin réellement emprunté par `GET /solutions/trade/survey`.
     */
    public function test_survey_registry_resolves_trade_under_the_manifest_code(): void
    {
        $catalogue = new SolutionCatalogue;
        $catalogue->register($this->manifest->code(), fn (): SolutionManifest => $this->manifest);

        $registry = new SolutionSurveyRegistry($catalogue);
        $registry->register($this->survey->code(), fn (): TradeSurvey => $this->survey);

        self::assertTrue($registry->has($this->manifest->code()));
        self::assertSame($this->survey, $registry->resolve($this->manifest->code()));
    }
}
