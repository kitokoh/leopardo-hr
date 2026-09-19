<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureKillSwitchService;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $sector
 * @property string $country
 * @property string $city
 * @property string|null $address
 * @property string $email
 * @property string|null $phone
 * @property int|null $plan_id
 * @property string $schema_name
 * @property string $tenancy_type
 * @property string $status
 * @property Carbon|null $subscription_start
 * @property Carbon|null $subscription_end
 * @property string $language
 * @property string $timezone
 * @property string $currency
 * @property string|null $notes
 * @property int|null $referrer_partner_id
 * @property array<mixed> $features
 * @property array<mixed> $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class Company extends Model
{
    // Issue #7711 (exceptions BelongsToCompany) — PAS de trait : Company EST
    // la frontière tenant elle-même (registre public.companies, résolu
    // pré-tenant par TenantMiddleware et les surfaces plateforme).

    /** @use HasFactory<\Database\Factories\CompanyFactory> */
    use HasFactory;

    use HasUuids;

    public $incrementing = false;

    // Registre des sociétés = public.companies (tous les tenants, quel que
    // soit leur schema_name). Sans qualification explicite, la résolution
    // passait par le search_path (ex. shared_tenants) et tombait sur la
    // table shadow vide shared_tenants.companies → $employee->company null →
    // invitations/onboarding silencieusement sautés (constaté en prod).
    protected $table = 'public.companies';

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'sector',
        'country',
        'city',
        'address',
        'email',
        'phone',
        'plan_id',
        'schema_name',
        'tenancy_type',
        'status',
        'subscription_start',
        'subscription_end',
        'language',
        'timezone',
        'currency',
        'notes',
        'features',
        'metadata',
    ];

    protected $casts = [
        'features' => 'array',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'features' => '{}',
        'metadata' => '{}',
    ];

    /**
     * Liste des modules connus de la plateforme (APV L.08).
     * L ajout d un nouveau module passe par cette constante + une entree dans docs/REFERENTIEL_PRODUIT/ROADMAP.md (la ROADMAP racine aspirationnelle est archivee, issue #6698).
     */
    public const KNOWN_MODULES = [
        'rh',
        'finance',
        'cameras',
        'muhasebe',
        'leo_ai',
        // #5742 (CRM PRE) : module CRM client — opt-in plateforme, désactivé
        // par défaut (ADR-CRM-004). Activation = PATCH features plateforme ;
        // gate serveur `crm.enabled` sur les routes /api/v1/crm/*.
        'crm',
        'fuel_station',
        'edumanager',
        'restaurant',
        // #7220 (audit 2026-09-10) : verticale Agence de voyage. `travelagency`
        // est le code du TravelAgencyManifest et le flag posé par
        // `ActivateTravelAgencyAction`, mais il était ABSENT de ce registre :
        // l'admin plateforme (PATCH /platform/companies/{company}/features)
        // reconstruit `features` à partir de KNOWN_MODULES et ne pouvait donc
        // jamais activer ni exposer la verticale Travel. Fail-closed conservé
        // (défaut false, `rh` seul actif par défaut).
        'travelagency',
        // #7235 (audit 2026-09-12) : la comptabilité est un module HORIZONTAL
        // (transverse à tous les secteurs) et le module serveur existe
        // (`app/Modules/Accounting`, routes `/api/v1/accounting/*`) — mais il
        // était absent de ce registre, donc jamais reconstruit par l'admin
        // plateforme (`PlatformCompanyFeatureController::update`) ni remonté
        // par `/auth/me`. Il est désormais de premier ordre, comme demandé.
        'accounting',
        // BC-27 SHOWCASE (#6862) — module HORIZONTAL « Site vitrine » : le flag
        // tenant est `company_showcase` (`ShowcaseFeatures::COMPANY_SHOWCASE`),
        // lu par le middleware `module.showcase`. Il doit figurer ici pour que
        // l'admin plateforme (`PlatformCompanyFeatureController::update`)
        // reconstruise et expose la clé, au même titre que `accounting`/#7235.
        'company_showcase',
        // #7432 — Formation : outil HORIZONTAL (toute entreprise forme, quel que
        // soit son secteur). Il était déjà dans `HORIZONTAL_TOOLS` et dans le
        // sous-menu RH de la barre client, mais ABSENT de ce registre : comme
        // pour `travelagency` (#7220) et `accounting` (#7235), l'interrupteur
        // « Centre de Formation » de la fiche entreprise était donc reconstruit
        // à partir de ce même registre… sans la clé — le switch admin
        // s'affichait et **jetait silencieusement** la valeur envoyée.
        // L'entrée manquante rend l'interrupteur réellement agissant.
        'training',
        // BC-24 TRAVEL / #7400 — module HORIZONTAL « Flotte & suivi des
        // véhicules » (outil transverse : toute PME de terrain a des
        // véhicules). Il doit figurer ici pour que l'admin plateforme
        // (`PlatformCompanyFeatureController::update`) reconstruise et expose
        // la clé, sans quoi le module ne serait ni activable ni mesurable.
        // Fail-closed conservé (défaut false) ; le gate serveur `module.fleet`
        // reste à trancher (voir #7400) — les routes Fleet sont aujourd'hui
        // sous `api.manager` et la surface client est ouverte par capacité
        // (`can_view_fleet`).
        'fleet',
        // BC-17 RETAIL (#7672) — module vendeur générique : produits &
        // catégories (fondations backend). Flag tenant `retail`
        // (`RetailFeatures::RETAIL`), lu par le middleware `module.retail`.
        // Fail-closed conservé (défaut false).
        'retail',
        // BC-29 COMMUNICATION / #7685 (R0) — module HORIZONTAL « Communication »
        // (boîte mail connectée + IA, spec MODULE_COMMUNICATION_EMAIL_IA.md,
        // exception freeze #7644). Transversal : activable pour n'importe quel
        // profil de tenant. Présent ici pour que l'admin plateforme
        // (`PATCH /platform/companies/{company}/features`) reconstruise et
        // expose la clé (même leçon que `travelagency` #7220 / `accounting`
        // #7235). Fail-closed conservé (défaut false) ; gate serveur
        // `module.communication` sur les routes /api/v1/communication/*.
        'communication',
    ];

    /**
     * #7235 — Type d'activité déclaré à l'inscription.
     *
     * `company` : entreprise traditionnelle (équipe, pointage, RH…).
     * `solo`    : indépendant / solo — pas d'outils d'équipe (pointage,
     *             gestion des employés) dans l'interface.
     */
    public const TYPE_COMPANY = 'company';

    public const TYPE_SOLO = 'solo';

    /**
     * #7235 — Outils HORIZONTAUX (transverses, utiles quel que soit le
     * secteur) proposés à l'inscription et pilotables par le client. Les
     * clés sont celles du catalogue client (`front/web/src/lib/client-features.ts`)
     * pour que la sélection soit directement résolvable côté interface.
     *
     * Les VERTICALES (restaurant, fuel_station, edumanager, travelagency…)
     * ne sont pas ici : elles passent par le catalogue de solutions
     * (`SolutionCatalogue`) et `solutions[]`.
     */
    public const HORIZONTAL_TOOLS = [
        'employees',
        'attendance',
        'absences',
        'contracts',
        'payroll',
        'training',
        'reports',
        'accounting',
        'crm',
        'marketing',
        // BC-27 SHOWCASE (#6862) — le site vitrine est un outil transverse à
        // tous les secteurs (commerce, service, industrie). La clé catalogue
        // est `showcase` (front `client-features.ts`) ; le feature flag tenant
        // correspondant est `company_showcase` (cf. `mirroredFeatures`).
        'showcase',
        // BC-19 DEVICE (#7476) — « chaque entrepreneur aura besoin d'avoir des
        // caméras » (retour propriétaire) : le module Caméras devient un outil
        // horizontal auto-activable par le client. La surface web
        // (`client-features.ts`, #7425) et le flag plateforme `cameras`
        // (`config/feature-flags.php`) existaient déjà : seule l'activation par
        // le client manquait (elle était réservée à la console plateforme).
        'cameras',
    ];

    /**
     * Correspondance outil horizontal (`metadata.modules`, catalogue client) =>
     * feature flag plateforme (`config/feature-flags.php`). Les clés ABSENTES
     * de cette table ne sont pas des flags plateforme : leur activation vit
     * uniquement dans `metadata.modules`, que le client web consomme
     * directement (`explicitToolValue` de `client-features.ts`).
     *
     * Source unique partagée par le provisioning (`ProvisionGuidedTrial`) et
     * l'auto-activation côté tenant (`CompanyModuleController`) — sans import
     * cross-BC : la correspondance est littérale (#7235, BC-27 #6862, #7322).
     *
     * @var array<string, string>
     */
    public const HORIZONTAL_TOOL_FEATURES = [
        'accounting' => 'accounting',
        'crm' => 'crm',
        'showcase' => 'company_showcase',
        // #7432 — la Formation est horizontale ET possède un flag plateforme
        // du même nom (`config/feature-flags.php`) : l'auto-activation par le
        // client (`POST /company/modules/training/activate`) doit donc écrire
        // les deux sources de vérité (`metadata.modules.training` ET
        // `companies.features.training`), comme `showcase`.
        'training' => 'training',
        // #7476 — le flag plateforme `cameras` existe : l'auto-activation côté
        // client doit donc écrire LES DEUX sources de vérité
        // (`metadata.modules.cameras` ET `companies.features.cameras`), comme
        // `showcase` et `training`. La console plateforme garde le kill switch.
        'cameras' => 'cameras',
    ];

    /**
     * #7322 — active un outil horizontal pour CE tenant : écrit la sélection
     * client (`metadata.modules[key] = true`) ET le flag plateforme miroir
     * lorsqu'il existe (`HORIZONTAL_TOOL_FEATURES`).
     *
     * ⚠️ N'appelle PAS `save()` : l'appelant persiste (le contrôleur tenant le
     * fait par une requête qualifiée `public.companies` pour éviter le piège
     * search_path). Retourne `true` si l'outil vient d'être activé, `false`
     * s'il l'était déjà (idempotence).
     *
     * @throws \InvalidArgumentException clé hors `HORIZONTAL_TOOLS` (fail-closed)
     */
    public function activateHorizontalTool(string $key): bool
    {
        if (! in_array($key, self::HORIZONTAL_TOOLS, true)) {
            throw new \InvalidArgumentException("Outil horizontal inconnu : {$key}");
        }

        $metadata = $this->metadata ?? [];
        $modules = is_array($metadata['modules'] ?? null) ? $metadata['modules'] : [];

        $alreadyActive = ($modules[$key] ?? null) === true;

        $modules[$key] = true;
        $metadata['modules'] = $modules;
        $this->metadata = $metadata;

        if (isset(self::HORIZONTAL_TOOL_FEATURES[$key])) {
            $this->setFeature(self::HORIZONTAL_TOOL_FEATURES[$key], true);
        }

        return ! $alreadyActive;
    }

    /**
     * #7235 — Outils HORIZONTAUX d'ÉQUIPE. Un profil `solo` (indépendant)
     * n'en a aucun usage : ils sont explicitement désactivés à l'inscription
     * pour ne pas encombrer son interface (demande produit : « le solo n'a pas
     * besoin des outils de pointage ou de gestion d'employés »).
     */
    public const TEAM_TOOLS = [
        'employees',
        'attendance',
        'absences',
        'contracts',
        'payroll',
        'training',
    ];

    /**
     * #7423 — PLANCHER D'ACCÈS garanti : le socle RH qu'un indépendant
     * (`solo`) garde **quel que soit son profil et sa sélection**.
     *
     * Demande du propriétaire (2026-09-14) : « j'aimerais qu'il ait quand même
     * accès au minimum […] au moins accès au module RH ». Un solo n'a pas
     * d'équipe à piloter, mais il travaille : il se pointe, pose ses congés et
     * reçoit ses bulletins. `TEAM_TOOLS` (pilotage d'ÉQUIPE) reste donc
     * désactivé pour lui — mais le sous-ensemble ci-dessous est forcé à `true`,
     * y compris si la sélection d'inscription ne le coche pas (le plancher est
     * un MINIMUM, pas un défaut).
     *
     * Source de vérité : cette constante est appliquée côté serveur
     * (`HorizontalToolSelection::resolve`, `CompanyModuleController::activate`)
     * — un client ne se garde pas lui-même ; `front/web/src/lib/client-features.ts`
     * ne fait que la refléter pour l'affichage.
     */
    public const SOLO_FLOOR_TOOLS = [
        'attendance',
        'absences',
        'payroll',
    ];

    /**
     * #7423 — un outil d'équipe est-il au plancher garanti du profil `solo` ?
     */
    public static function isSoloFloorTool(string $key): bool
    {
        return in_array(strtolower(trim($key)), self::SOLO_FLOOR_TOOLS, true);
    }

    /**
     * #7235 — Profil d'activité du tenant (`company` par défaut, fail-safe :
     * une valeur inconnue retombe sur le profil complet, jamais sur le profil
     * réduit).
     */
    public function companyType(): string
    {
        $type = $this->metadata['company_type'] ?? null;

        return $type === self::TYPE_SOLO ? self::TYPE_SOLO : self::TYPE_COMPANY;
    }

    public function isSolo(): bool
    {
        return $this->companyType() === self::TYPE_SOLO;
    }

    /**
     * #7235 — Sélection explicite des outils horizontaux faite à l'inscription
     * (`metadata.modules`). `null` = aucune sélection déclarée (tenants
     * historiques / inscription rapide) → l'interface garde son comportement
     * antérieur, aucun client existant n'est verrouillé par surprise.
     *
     * @return array<string, bool>|null
     */
    public function moduleSelection(): ?array
    {
        $modules = $this->metadata['modules'] ?? null;

        if (! is_array($modules)) {
            return null;
        }

        $selection = [];

        foreach ($modules as $key => $enabled) {
            if (is_string($key) && is_bool($enabled)) {
                $selection[$key] = $enabled;
            }
        }

        return $selection === [] ? null : $selection;
    }

    /**
     * Indique si un module (ou une sous-feature) est actif pour cette company.
     * Le module RH est actif par defaut (base de l app).
     *
     * MAT-010 (#5868) : kill switch global consulté en premier — un
     * interrupteur actif coupe la feature pour TOUTE la plateforme
     * (fail-closed), sans suppression de données. Point d'intégration unique :
     * tous les gates (middleware module, FeatureFlag, resources) héritent du
     * kill switch via cette méthode.
     */
    public function hasFeature(string $key): bool
    {
        if (app(FeatureKillSwitchService::class)->isKilled($key)) {
            return false;
        }

        $features = $this->features ?? [];

        // #7400 — le défaut d'un module est déclaré UNE SEULE FOIS, dans le
        // registre (`config/feature-flags.php`), qui est la source de vérité
        // revendiquée par son propre docblock. L'ancien `if ($key === 'rh')`
        // recodait ce défaut en dur : tout flag déclaré `default => true` dans
        // le registre était donc silencieusement remis à `false` dès que la
        // company portait une carte `features`, et le registre mentait.
        //
        // Sans effet observable aujourd'hui : `rh` est le seul flag en
        // `default => true`, et le cas particulier lui rendait déjà `true`.
        // C'est le piège posé au prochain flag activé par défaut qui est retiré.
        $default = (bool) config("feature-flags.flags.{$key}.default", false);

        return (bool) ($features[$key] ?? $default);
    }

    /**
     * Toggle explicite d une feature (reserve super-admin / commande console).
     */
    public function setFeature(string $key, bool $enabled): void
    {
        $features = $this->features ?? [];
        $features[$key] = $enabled;
        $this->features = $features;
    }

    /**
     * Retourne une chaine search_path securisee pour PostgreSQL.
     * Echappe le nom du schema (whitelist alphanumeric/underscore) pour eviter les injections SQL.
     */
    public function getSafeSearchPath(): string
    {
        $schema = preg_replace('/[^a-zA-Z0-9_]/', '', $this->schema_name ?: 'shared_tenants') ?: 'shared_tenants';

        return '"'.$schema.'",public';
    }

    protected static function booted(): void
    {
        static::creating(function (self $company): void {
            if ($company->tenancy_type === 'schema') {
                abort(422, __('errors.COMPANY_SCHEMA_MODE_LOCKED'));
            }
        });

        static::saved(function (self $company): void {
            if (! $company->wasChanged('status')) {
                return;
            }

            if (! in_array($company->status, ['suspended', 'expired'], true)) {
                return;
            }

            // Les employes vivent dans le schema tenant ('shared_tenants' en MVP),
            // alors que l update d une company via le super-admin web s execute
            // avec search_path=public. On etend le search_path temporairement
            // pour que la revocation des tokens (Sanctum) voie les relations.
            if (DB::getDriverName() === 'pgsql') {
                $previous = DB::selectOne('SHOW search_path')->search_path ?? 'public';
                DB::statement('SET search_path TO '.$company->getSafeSearchPath());
                try {
                    Employee::withoutGlobalScopes()
                        ->where('company_id', $company->id)
                        ->get()
                        ->each(fn (Employee $employee) => $employee->tokens()->delete());
                } finally {
                    DB::statement("SET search_path TO {$previous}");
                }
            } else {
                Employee::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->get()
                    ->each(fn (Employee $employee) => $employee->tokens()->delete());
            }
        });
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'company_id');
    }

    /** @return HasMany<BiometricEnrollmentRequest, $this> */
    public function biometricRequests(): HasMany
    {
        return $this->hasMany(BiometricEnrollmentRequest::class, 'company_id');
    }

    /** @return HasMany<AttendanceKiosk, $this> */
    public function attendanceKiosks(): HasMany
    {
        return $this->hasMany(AttendanceKiosk::class, 'company_id');
    }
}
