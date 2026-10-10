<?php

declare(strict_types=1);

namespace App\Core\Feature\Domain;

/**
 * BOS-011 (#8198, ADR-0026, spec #8148) — Registre unifié modules / features /
 * solutions : SOURCE DÉCLARATIVE UNIQUE, en code PHP versionné (pas de table).
 *
 * Avant ce registre, l'activation d'un module exigeait 3 enregistrements
 * manuels cohérents (`config/feature-flags.php`, `Company::KNOWN_MODULES`,
 * `Company::HORIZONTAL_TOOL_FEATURES` — plus `metadata.modules` côté client) :
 * 5 incidents de désynchronisation documentés (#7220, #7235, #7432, #7785,
 * #7976) dont une vivante (`fleet` dans KNOWN_MODULES, absent du registre de
 * flags). Ici, chaque clé est déclarée UNE SEULE FOIS et les trois listes
 * historiques deviennent des DÉRIVATIONS CALCULÉES :
 *
 *   - {@see flags()}            → projection au format `config/feature-flags.flags`
 *                                 (consommée par FeatureFlagRegistry / Company::hasFeature) ;
 *   - {@see knownModules()}     → remplace `Company::KNOWN_MODULES` (allowlist
 *                                 de la console plateforme) ;
 *   - {@see horizontalTools()}  → remplace `Company::HORIZONTAL_TOOLS` ;
 *   - {@see horizontalMirrors()} → remplace `Company::HORIZONTAL_TOOL_FEATURES`.
 *
 * Champs d'une entrée :
 *   - kind               : nature déclarée — module | solution | horizontal_tool ;
 *   - scope              : portée legacy (compat projection flags) — module | solution ;
 *   - default            : défaut fail-closed (seul `rh` est actif par défaut — socle) ;
 *   - since              : version d'introduction au registre ;
 *   - killable           : false ⇒ tout kill switch est refusé (fail-closed, FR-7) ;
 *   - platform_flag      : la clé est un flag tenant (résoluble via companies.features) ;
 *   - exposed_in_flags   : la clé figure dans la projection flags (`FeatureFlag::for`,
 *                          `/auth/me`). false pour les flags résolus par gate sans
 *                          exposition historique (`delivery`, `b2b_catalog`) :
 *                          enregistrés pour la garde FR-6, contrat préservé ;
 *   - platform_exposable : la clé appartient à l'allowlist console plateforme
 *                          (KNOWN_MODULES dérivé) ;
 *   - metadata_mirror    : clé `metadata.modules` miroir du flag (double écriture
 *                          transitoire, retirée en BOS-012), null sinon ;
 *   - horizontal_order   : rang legacy dans HORIZONTAL_TOOLS (ordre contractuel
 *                          préservé), null si ce n'est pas un outil horizontal ;
 *   - known_order        : rang legacy dans KNOWN_MODULES (ordre contractuel
 *                          préservé), null si non exposable.
 *
 * Bascule dual-read (ADR-0026 §2) : ce registre est la source du mode
 * `registry` et le candidat comparé du mode `dual` — voir
 * {@see \App\Core\Feature\Infrastructure\Services\ModuleRegistryGateway}.
 * Le retrait des listes legacy est BOS-012.
 *
 * @phpstan-type ModuleEntry array{
 *     kind: string,
 *     scope: string,
 *     default: bool,
 *     since: string,
 *     killable: bool,
 *     platform_flag: bool,
 *     exposed_in_flags: bool,
 *     platform_exposable: bool,
 *     metadata_mirror: ?string,
 *     horizontal_order: ?int,
 *     known_order: ?int,
 *     description: string,
 * }
 * @phpstan-type FlagProjection array{scope: string, default: bool, since: string, killable: bool, description: string}
 */
final class ModuleRegistry
{
    /**
     * Version du registre (semver contenu : toute addition/retrait de clé ou
     * changement de défaut la fait monter). 1.1.0 : entrée `fleet` ajoutée
     * (désync corrigée), `delivery`/`b2b_catalog` enregistrés (garde FR-6).
     * 1.2.0 : entrée `geo` (BC-33, GEO-02/#8351) ajoutée.
     * 1.3.0 : entrée `vtc` (BC-34, VTC-01/#8357) ajoutée.
     */
    public const VERSION = '1.3.0';

    /**
     * Source déclarative unique. L'ordre de déclaration reproduit l'ordre
     * legacy de `config/feature-flags.flags` (+ `fleet` en fin — ajout
     * additif, contrat `/auth/me` préservé clé par clé) : la projection
     * {@see flags()} est octet-identique à la liste legacy.
     *
     * @var array<string, ModuleEntry>
     */
    private const ENTRIES = [
        'ai_cloud_allowed' => [
            'kind' => 'solution',
            'scope' => 'solution',
            'default' => false,
            'since' => '4.24.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => false,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => null,
            'description' => 'Assistant IA (BC-23) : autorise l\'envoi vers les fournisseurs LLM cloud (RGPD — minimisation + audit requis).',
        ],
        'rh' => [
            'kind' => 'module',
            'scope' => 'module',
            'default' => true,
            'since' => '4.0.0',
            'killable' => false,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 1,
            'description' => 'Module RH — socle de l\'application (non killable).',
        ],
        'finance' => [
            'kind' => 'module',
            'scope' => 'module',
            'default' => false,
            'since' => '4.10.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 2,
            'description' => 'Module Finance (paie, comptabilité, dépenses).',
        ],
        'cameras' => [
            'kind' => 'horizontal_tool',
            'scope' => 'module',
            'default' => false,
            'since' => '4.16.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => 'cameras',
            'horizontal_order' => 12,
            'known_order' => 3,
            'description' => 'Caméras & vidéosurveillance (BC-19 DEVICE).',
        ],
        'muhasebe' => [
            'kind' => 'module',
            'scope' => 'module',
            'default' => false,
            'since' => '4.16.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 4,
            'description' => 'Module comptabilité Turquie (muhasebe).',
        ],
        'leo_ai' => [
            'kind' => 'module',
            'scope' => 'module',
            'default' => false,
            'since' => '4.16.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 5,
            'description' => 'Assistant IA Leopardo (BC-23 AI).',
        ],
        'training' => [
            'kind' => 'horizontal_tool',
            'scope' => 'module',
            'default' => false,
            'since' => '4.25.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => 'training',
            'horizontal_order' => 6,
            'known_order' => 16,
            'description' => 'Module Formation — outil horizontal (BC-04 HR) : catalogue, sessions, inscriptions.',
        ],
        'fuel_station' => [
            'kind' => 'solution',
            'scope' => 'solution',
            'default' => false,
            'since' => '4.24.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 7,
            'description' => 'Solution FuelStation — pilote terrain (BC-15 FUEL).',
        ],
        'accounting' => [
            'kind' => 'horizontal_tool',
            'scope' => 'module',
            'default' => false,
            'since' => '4.25.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => 'accounting',
            'horizontal_order' => 8,
            'known_order' => 14,
            'description' => 'Comptabilité (journaux, grand livre, balance, FEC, lettrage).',
        ],
        'crm' => [
            'kind' => 'horizontal_tool',
            'scope' => 'module',
            'default' => false,
            'since' => '4.25.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => 'crm',
            'horizontal_order' => 9,
            'known_order' => 6,
            'description' => 'CRM client (comptes, contacts, opportunités, pipeline) — espace tenant.',
        ],
        'restaurant' => [
            'kind' => 'solution',
            'scope' => 'solution',
            'default' => false,
            'since' => '4.25.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 10,
            'description' => 'Solution Restaurant (POS, cuisine, réservations, stock).',
        ],
        'restaurantmanager' => [
            'kind' => 'solution',
            'scope' => 'solution',
            'default' => false,
            'since' => '4.34.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 11,
            'description' => 'Verticale RestaurantManager (POS & caisse, commandes, réservations, stock/COGS, livraison, fidélité).',
        ],
        'edumanager' => [
            'kind' => 'solution',
            'scope' => 'solution',
            'default' => false,
            'since' => '4.25.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 8,
            'description' => 'Solution EduManager (établissements scolaires, classes, notes).',
        ],
        'healthmanager' => [
            'kind' => 'solution',
            'scope' => 'solution',
            'default' => false,
            'since' => '4.34.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 9,
            'description' => 'Solution HealthManager (hôpitaux et cliniques privées : patients, rendez-vous, hospitalisations, facturation des soins).',
        ],
        'travelagency' => [
            'kind' => 'solution',
            'scope' => 'solution',
            'default' => false,
            'since' => '4.25.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 12,
            'description' => 'Solution Agence de voyage (ventes, réservations, check-in).',
        ],
        'pharmacy' => [
            'kind' => 'solution',
            'scope' => 'solution',
            'default' => false,
            'since' => '4.26.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 13,
            'description' => 'Solution PharmaManager (référentiel produits, stock par lots, achats, ventes comptoir, ordonnancier).',
        ],
        'company_showcase' => [
            'kind' => 'horizontal_tool',
            'scope' => 'module',
            'default' => false,
            'since' => '4.32.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => 'showcase',
            'horizontal_order' => 11,
            'known_order' => 15,
            'description' => 'Site vitrine public de l\'entreprise (création 1-clic, sections, thème, publication).',
        ],
        'retail' => [
            'kind' => 'solution',
            'scope' => 'solution',
            'default' => false,
            'since' => '4.33.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 18,
            'description' => 'Module Retail — vendeur générique (BC-17) : produits, catégories, publication.',
        ],
        'communication' => [
            'kind' => 'module',
            'scope' => 'module',
            'default' => false,
            'since' => '4.33.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 19,
            'description' => 'Communication (boîte mail connectée + IA) : intégrations Gmail, classification, relances et réponses assistées.',
        ],
        'hospitality' => [
            'kind' => 'solution',
            'scope' => 'solution',
            'default' => false,
            'since' => '4.34.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 20,
            'description' => 'Solution HospitalityManager (hôtels, résidences, locations : établissements, inventaire, réservations, baux et loyers).',
        ],
        'fleet' => [
            'kind' => 'module',
            'scope' => 'module',
            'default' => false,
            'since' => '4.34.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 17,
            'description' => 'Module Fleet — flotte & suivi des véhicules (outil horizontal BC-24/#7400 ; gate module.fleet).',
        ],
        'delivery' => [
            'kind' => 'module',
            'scope' => 'module',
            'default' => false,
            'since' => '4.34.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => false,
            'platform_exposable' => false,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => null,
            'description' => 'Module Delivery — moteur de livraison dernier-kilomètre (BC-26, DELIVERY-101 #6282 ; gate `module.delivery`, DeliveryManifest). Résolu via companies.features ; enregistré par #8198 (garde FR-6) sans exposition dans la projection flags (contrat /auth/me inchangé).',
        ],
        'b2b_catalog' => [
            'kind' => 'module',
            'scope' => 'module',
            'default' => false,
            'since' => '4.34.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => false,
            'platform_exposable' => false,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => null,
            'description' => 'Catalogue B2B (BC-28, #6881 ; gate `module.catalog` → b2b_catalog, CatalogFeatures::B2B_CATALOG). Résolu via companies.features ; enregistré par #8198 (garde FR-6) sans exposition dans la projection flags.',
        ],
        'employees' => [
            'kind' => 'horizontal_tool',
            'scope' => 'module',
            'default' => false,
            'since' => '4.0.0',
            'killable' => false,
            'platform_flag' => false,
            'exposed_in_flags' => false,
            'platform_exposable' => false,
            'metadata_mirror' => null,
            'horizontal_order' => 1,
            'known_order' => null,
            'description' => 'Outil horizontal Employés (dossiers, organigramme) — vit dans metadata.modules (HORIZONTAL_TOOLS #7235), aucun flag plateforme.',
        ],
        'attendance' => [
            'kind' => 'horizontal_tool',
            'scope' => 'module',
            'default' => false,
            'since' => '4.0.0',
            'killable' => false,
            'platform_flag' => false,
            'exposed_in_flags' => false,
            'platform_exposable' => false,
            'metadata_mirror' => null,
            'horizontal_order' => 2,
            'known_order' => null,
            'description' => 'Outil horizontal Pointage (présences, kiosques) — vit dans metadata.modules, aucun flag plateforme.',
        ],
        'absences' => [
            'kind' => 'horizontal_tool',
            'scope' => 'module',
            'default' => false,
            'since' => '4.0.0',
            'killable' => false,
            'platform_flag' => false,
            'exposed_in_flags' => false,
            'platform_exposable' => false,
            'metadata_mirror' => null,
            'horizontal_order' => 3,
            'known_order' => null,
            'description' => 'Outil horizontal Congés & absences — vit dans metadata.modules, aucun flag plateforme.',
        ],
        'contracts' => [
            'kind' => 'horizontal_tool',
            'scope' => 'module',
            'default' => false,
            'since' => '4.0.0',
            'killable' => false,
            'platform_flag' => false,
            'exposed_in_flags' => false,
            'platform_exposable' => false,
            'metadata_mirror' => null,
            'horizontal_order' => 4,
            'known_order' => null,
            'description' => 'Outil horizontal Contrats — vit dans metadata.modules, aucun flag plateforme.',
        ],
        'payroll' => [
            'kind' => 'horizontal_tool',
            'scope' => 'module',
            'default' => false,
            'since' => '4.0.0',
            'killable' => false,
            'platform_flag' => false,
            'exposed_in_flags' => false,
            'platform_exposable' => false,
            'metadata_mirror' => null,
            'horizontal_order' => 5,
            'known_order' => null,
            'description' => 'Outil horizontal Paie — vit dans metadata.modules, aucun flag plateforme.',
        ],
        'reports' => [
            'kind' => 'horizontal_tool',
            'scope' => 'module',
            'default' => false,
            'since' => '4.0.0',
            'killable' => false,
            'platform_flag' => false,
            'exposed_in_flags' => false,
            'platform_exposable' => false,
            'metadata_mirror' => null,
            'horizontal_order' => 7,
            'known_order' => null,
            'description' => 'Outil horizontal Rapports — vit dans metadata.modules, aucun flag plateforme.',
        ],
        'marketing' => [
            'kind' => 'horizontal_tool',
            'scope' => 'module',
            'default' => false,
            'since' => '4.0.0',
            'killable' => false,
            'platform_flag' => false,
            'exposed_in_flags' => false,
            'platform_exposable' => false,
            'metadata_mirror' => null,
            'horizontal_order' => 10,
            'known_order' => null,
            'description' => 'Outil horizontal Marketing — vit dans metadata.modules, aucun flag plateforme.',
        ],
        // BC-33 GEO (GEO-02/#8351) — ajout additif en fin de liste (ordre
        // préservé — contrat /auth/me : la clé apparaît en dernier, résolue
        // false par défaut comme tout module opt-in). Enregistrement
        // simultané dans config/feature-flags.php + Company::KNOWN_MODULES
        // (leçon #7220/#7235 ; parité imposée par tests/Unit/Core/Feature).
        'geo' => [
            'kind' => 'module',
            'scope' => 'module',
            'default' => false,
            'since' => '4.35.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 21,
            'description' => 'Core géospatial transverse (BC-33, GEO-02/#8351 ; gate `module.geo`) : calculs de positionnement PostGIS — distance, plus-proches, dans-un-rayon — réutilisables par toutes les verticales (VTC en premier, BC-34).',
        ],
        // BC-34 VTC (VTC-01/#8357) — ajout additif en fin de liste (ordre
        // préservé — contrat /auth/me). Code du VtcManifest : activation
        // refusée si `geo` inactif (SolutionActivator fail-closed).
        // Enregistrement simultané dans config/feature-flags.php +
        // Company::KNOWN_MODULES (parité imposée par tests/Unit/Core/Feature).
        'vtc' => [
            'kind' => 'solution',
            'scope' => 'solution',
            'default' => false,
            'since' => '4.35.0',
            'killable' => true,
            'platform_flag' => true,
            'exposed_in_flags' => true,
            'platform_exposable' => true,
            'metadata_mirror' => null,
            'horizontal_order' => null,
            'known_order' => 22,
            'description' => 'Solution VTC/taxi (BC-34, VTC-01/#8357 ; gate `module.vtc`) : réservation de courses, dispatch au plus proche chauffeur via le core géospatial `geo` (requis), tarification et suivi.',
        ],
    ];

    /**
     * @param  array<string, ModuleEntry>|null  $entries  Source alternative
     *                                                    (tests — ex. démonstration « 1 seul enregistrement » du critère
     *                                                    d'acceptation 1) ; null ⇒ source canonique {@see ENTRIES}.
     */
    public function __construct(
        private readonly ?array $entries = null,
    ) {}

    public function version(): string
    {
        return self::VERSION;
    }

    /**
     * @return array<string, ModuleEntry>
     */
    public function entries(): array
    {
        return $this->entries ?? self::ENTRIES;
    }

    /**
     * @return ModuleEntry|null
     */
    public function entry(string $key): ?array
    {
        return $this->entries()[$key] ?? null;
    }

    /**
     * La clé est-elle un flag tenant connu du registre ? (fail-closed ailleurs)
     */
    public function isKnown(string $key): bool
    {
        $entry = $this->entry($key);

        return $entry !== null && $entry['platform_flag'] === true;
    }

    /**
     * FR-7 : un flag déclaré `killable: false` (socle `rh`) refuse tout kill ;
     * une clé inconnue n'est jamais killable (refus fail-closed + audit).
     */
    public function isKillable(string $key): bool
    {
        $entry = $this->entry($key);

        return $entry !== null && $entry['platform_flag'] === true && $entry['killable'] === true;
    }

    /**
     * Défaut versionné du flag (false pour une clé inconnue — fail-closed).
     */
    public function defaultFor(string $key): bool
    {
        $entry = $this->entry($key);

        if ($entry === null || $entry['platform_flag'] !== true) {
            return false;
        }

        return $entry['default'];
    }

    /**
     * Dérivation : projection au format historique `config/feature-flags.flags`
     * (clés plateforme exposées, ordre de déclaration = ordre legacy).
     *
     * @return array<string, FlagProjection>
     */
    public function flags(): array
    {
        $flags = [];

        foreach ($this->entries() as $key => $entry) {
            if ($entry['platform_flag'] !== true || $entry['exposed_in_flags'] !== true) {
                continue;
            }

            $flags[$key] = [
                'scope' => $entry['scope'],
                'default' => $entry['default'],
                'since' => $entry['since'],
                'killable' => $entry['killable'],
                'description' => $entry['description'],
            ];
        }

        return $flags;
    }

    /**
     * Dérivation : allowlist console plateforme (remplace KNOWN_MODULES),
     * dans l'ordre contractuel legacy (`known_order`).
     *
     * @return list<string>
     */
    public function knownModules(): array
    {
        $ranked = [];

        foreach ($this->entries() as $key => $entry) {
            if ($entry['platform_exposable'] === true && $entry['known_order'] !== null) {
                $ranked[$key] = $entry['known_order'];
            }
        }

        asort($ranked);

        return array_keys($ranked);
    }

    /**
     * Dérivation : outils horizontaux du catalogue client (remplace
     * HORIZONTAL_TOOLS), dans l'ordre contractuel legacy (`horizontal_order`).
     * La clé exposée est le miroir metadata lorsqu'il existe (`showcase`
     * pour le flag `company_showcase`), sinon la clé d'entrée.
     *
     * @return list<string>
     */
    public function horizontalTools(): array
    {
        $ranked = [];

        foreach ($this->entries() as $key => $entry) {
            if ($entry['horizontal_order'] !== null) {
                $toolKey = $entry['metadata_mirror'] ?? $key;
                $ranked[$toolKey] = $entry['horizontal_order'];
            }
        }

        asort($ranked);

        return array_keys($ranked);
    }

    /**
     * Dérivation : correspondance outil horizontal ⇒ flag plateforme
     * (remplace HORIZONTAL_TOOL_FEATURES).
     *
     * @return array<string, string>
     */
    public function horizontalMirrors(): array
    {
        $mirrors = [];

        foreach ($this->entries() as $key => $entry) {
            if ($entry['metadata_mirror'] !== null) {
                $mirrors[$entry['metadata_mirror']] = $key;
            }
        }

        return $mirrors;
    }
}
