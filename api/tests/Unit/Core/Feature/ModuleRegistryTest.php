<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Feature;

use App\Core\Feature\Domain\ModuleRegistry;
use App\Core\Tenant\Domain\Models\Company;
use PHPUnit\Framework\TestCase;

/**
 * BOS-011 (#8198, ADR-0026) — registre unifié : dérivations et parité avec
 * les listes legacy pendant la transition dual-read.
 *
 * Critères d'acceptation couverts :
 *  - (1) « ajouter un module = 1 seul enregistrement » : démontré avec un
 *    module fictif injecté au constructeur — toutes les dérivations en
 *    découlent sans toucher un second fichier ;
 *  - (5) garde « module non enregistré = échec » : la parité stricte entre
 *    les dérivations et les listes legacy est assertée ici (complétée par
 *    {@see ModuleRegistryGuardTest} pour les clés référencées par le code).
 *
 * Test pur (aucune base) : le registre est un PHP versionné sans dépendance.
 */
class ModuleRegistryTest extends TestCase
{
    private ModuleRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ModuleRegistry();
    }

    public function test_every_entry_is_complete_and_well_formed(): void
    {
        $requiredFields = [
            'kind', 'scope', 'default', 'since', 'killable',
            'platform_flag', 'exposed_in_flags', 'platform_exposable',
            'metadata_mirror', 'horizontal_order', 'known_order', 'description',
        ];

        foreach ($this->registry->entries() as $key => $entry) {
            foreach ($requiredFields as $field) {
                self::assertArrayHasKey($field, $entry, "Entrée « {$key} » : champ « {$field} » manquant.");
            }

            self::assertContains($entry['kind'], ['module', 'solution', 'horizontal_tool'], "Entrée « {$key} » : kind inconnu.");
            self::assertContains($entry['scope'], ['module', 'solution'], "Entrée « {$key} » : scope inconnu.");
            self::assertNotSame('', $entry['description'], "Entrée « {$key} » : description vide (revue de style registre).");
            self::assertNotSame('', $entry['since'], "Entrée « {$key} » : since vide (toute nouvelle clé exige since + description).");

            if ($entry['platform_exposable']) {
                self::assertTrue($entry['platform_flag'], "Entrée « {$key} » : exposable plateforme ⇒ doit être un flag.");
                self::assertNotNull($entry['known_order'], "Entrée « {$key} » : exposable sans known_order.");
            }

            if ($entry['metadata_mirror'] !== null) {
                self::assertNotNull($entry['horizontal_order'], "Entrée « {$key} » : miroir metadata ⇒ outil horizontal ordonné.");
            }

            if ($entry['platform_flag'] === false) {
                self::assertFalse($entry['exposed_in_flags'], "Entrée « {$key} » : non-flag ne peut pas être exposé.");
            }
        }
    }

    public function test_flags_projection_is_byte_identical_to_legacy_config(): void
    {
        /** @var array{flags: array<string, array{scope: string, default: bool, since: string, killable: bool, description: string}>} $config */
        $config = require dirname(__DIR__, 4).'/config/feature-flags.php';

        // Parité structurelle (FR-2) : la projection dérivée EST la liste
        // legacy — contenu ET ordre (le contrat /auth/me dépend de l'ordre).
        self::assertSame($config['flags'], $this->registry->flags());
    }

    public function test_known_modules_derivation_matches_legacy_const(): void
    {
        self::assertSame(Company::KNOWN_MODULES, $this->registry->knownModules());
    }

    public function test_horizontal_tools_derivation_matches_legacy_const(): void
    {
        self::assertSame(Company::HORIZONTAL_TOOLS, $this->registry->horizontalTools());
    }

    public function test_horizontal_mirrors_derivation_matches_legacy_const(): void
    {
        self::assertEquals(Company::HORIZONTAL_TOOL_FEATURES, $this->registry->horizontalMirrors());
    }

    public function test_fleet_desync_is_fixed(): void
    {
        // Désync vivante mesurée au 2026-09-26 (spec §1) : `fleet` était dans
        // KNOWN_MODULES mais absent du registre de flags.
        self::assertTrue($this->registry->isKnown('fleet'));
        self::assertContains('fleet', $this->registry->knownModules());
        self::assertArrayHasKey('fleet', $this->registry->flags());
        self::assertFalse($this->registry->defaultFor('fleet'));
    }

    public function test_fail_closed_semantics(): void
    {
        self::assertFalse($this->registry->isKnown('module_inconnu'));
        self::assertFalse($this->registry->isKillable('module_inconnu'));
        self::assertFalse($this->registry->defaultFor('module_inconnu'));

        // FR-7 : le socle non killable.
        self::assertTrue($this->registry->isKnown('rh'));
        self::assertFalse($this->registry->isKillable('rh'));
        self::assertTrue($this->registry->defaultFor('rh'));
    }

    public function test_adding_a_module_is_a_single_registration(): void
    {
        // Critère d'acceptation 1 : UN SEUL enregistrement (aucune autre
        // liste à éditer) ⇒ allowlist plateforme, projection flags et
        // résolution de défaut en découlent structurellement.
        $entries = $this->registry->entries();
        $entries['demomodule'] = [
            'kind' => 'solution',
            'scope' => 'solution',
            'default' => false,
            'since' => '9.9.9',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 99,
            'description' => 'Module fictif — démonstration du critère « 1 seul enregistrement ».',
        ];

        $fictional = new ModuleRegistry($entries);

        self::assertContains('demomodule', $fictional->knownModules(), 'Allowlist plateforme dérivée du seul enregistrement.');
        self::assertArrayHasKey('demomodule', $fictional->flags(), 'Projection flags dérivée du seul enregistrement.');
        self::assertTrue($fictional->isKnown('demomodule'));
        self::assertTrue($fictional->isKillable('demomodule'));
        self::assertFalse($fictional->defaultFor('demomodule'));
    }
}
