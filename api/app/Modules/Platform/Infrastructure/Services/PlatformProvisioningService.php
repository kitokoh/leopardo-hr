<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8626 / #8627 (Lot 6) — Console superadmin du provisionnement.
 *
 * ## Le problème que ce service résout
 *
 * Le portefeuille (`PlatformCompanyHealthService`) répond « comment va cette
 * entreprise ? ». Il ne répond PAS à la question qu'un opérateur de plateforme
 * se pose pendant qu'un client s'inscrit : « où en est le provisionnement, et
 * qu'est-ce qui a cassé ? ». Aucun écran n'exposait les étapes franchies, les
 * échecs, ni les tenants nés sans établissement — il fallait ouvrir chaque
 * fiche une par une.
 *
 * ## Règle d'isolation (non négociable)
 *
 * Cette vue est CROSS-TENANT par construction : elle n'expose donc que des
 * **faits de provisionnement** (statuts, compteurs, étapes, codes d'erreur,
 * dates). Aucune ligne métier client (employés, ventes, pointages, montants)
 * ne sort d'ici — c'est la condition posée par le plan d'implémentation
 * (« ne pas exposer d'agrégats permettant de reconstituer des données
 * clientes »). Les noms/emails d'utilisateurs ne sont jamais renvoyés.
 */
final class PlatformProvisioningService
{
    /**
     * Seuil au-delà duquel un provisioning `pending` est considéré comme
     * bloqué (« stalled »).
     *
     * 15 minutes : le job asynchrone `ProvisionDemoTenantJob` traite un
     * sandbox en quelques secondes. Au-delà d'un quart d'heure sans transition,
     * il ne progresse plus — l'opérateur doit pouvoir le voir sans surveiller
     * les logs.
     */
    public const STALL_THRESHOLD_MINUTES = 15;

    public function __construct(
        private readonly SolutionCatalogue $catalogue,
    ) {}

    /**
     * Vue agrégée du provisionnement.
     *
     * @return array{
     *     summary: array{tenants:int, without_site:int, onboarding_incomplete:int, stalled_provisionings:int, verticals:int},
     *     tenants: list<array<string,mixed>>,
     *     failures: list<array<string,mixed>>,
     *     catalogue: list<array<string,mixed>>
     * }
     */
    public function overview(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $tenants = $this->tenants($limit);
        $failures = $this->failures();

        return [
            'summary' => [
                'tenants' => Company::query()->count(),
                // Un tenant né sans lieu d'exploitation ne peut pas démarrer :
                // c'est le symptôme d'onboarding le plus fréquent (gap T-01).
                'without_site' => $this->companiesWithoutSite(),
                'onboarding_incomplete' => $this->onboardingIncomplete(),
                'stalled_provisionings' => $this->stalledProvisionings(),
                'verticals' => count($this->catalogue->codes()),
            ],
            'tenants' => $tenants,
            'failures' => $failures,
            'catalogue' => $this->catalogue(),
        ];
    }

    /**
     * Catalogue des verticales livrées, avec maturité RÉELLE.
     *
     * La maturité vient du `SolutionManifest` lui-même (`pilot`,
     * `production`, `placeholder`) : l'écran ne la redéclare pas, sinon les
     * deux finiraient par diverger et l'opérateur activerait une verticale
     * en croyant qu'elle est en production.
     *
     * @return list<array{code: string, name: string, maturity: string, industry: string, description: string, required_modules: list<string>, optional_modules: list<string>}>
     */
    public function catalogue(): array
    {
        $rows = [];

        foreach ($this->catalogue->codes() as $code) {
            $manifest = $this->catalogue->resolve($code);

            $rows[] = [
                'code' => $manifest->code(),
                'name' => $manifest->name(),
                'maturity' => $manifest->maturity(),
                'industry' => $manifest->industry()->value,
                'description' => $manifest->description(),
                'required_modules' => $manifest->requiredModules(),
                'optional_modules' => $manifest->optionalModules(),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * Provisionings en échec ou bloqués (`trial_provisionings`).
     *
     * Répond à « qu'est-ce qui a cassé récemment ? » sans ouvrir les logs.
     *
     * @return list<array<string,mixed>>
     */
    public function failures(): array
    {
        if (! Schema::hasTable('public.trial_provisionings')) {
            return [];
        }

        $rows = DB::table('public.trial_provisionings')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $stalledBefore = now()->subMinutes(self::STALL_THRESHOLD_MINUTES);

        $failures = [];

        foreach ($rows as $row) {
            $isFailed = (string) $row->status === 'failed';
            // `created_at` arrive en chaîne depuis le query builder : la
            // conversion doit être explicite, `now()->parse()` n'existe pas.
            $createdAt = $row->created_at !== null ? Carbon::parse((string) $row->created_at) : null;
            $isStalled = (string) $row->status === 'pending'
                && $createdAt !== null
                && $createdAt->lessThan($stalledBefore);

            if (! $isFailed && ! $isStalled) {
                continue;
            }

            $failures[] = [
                // L'email est MASQUÉ : un opérateur a besoin de reconnaître la
                // ligne, pas de collecter des adresses depuis un écran de
                // diagnostic (RGPD — minimisation).
                'reference' => $this->maskEmail((string) $row->email),
                'token_prefix' => substr((string) $row->provisioning_token, 0, 8),
                'state' => $isFailed ? 'failed' : 'stalled',
                'error' => $row->error !== null ? mb_substr((string) $row->error, 0, 300) : null,
                'company_id' => $row->company_id,
                'created_at' => $row->created_at,
            ];
        }

        return $failures;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function tenants(int $limit): array
    {
        $companies = Company::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($companies->isEmpty()) {
            return [];
        }

        /** @var list<string> $ids */
        $ids = array_values($companies->pluck('id')->map(static fn ($id): string => (string) $id)->all());

        $siteCounts = $this->siteCounts($ids);

        return array_values($companies->map(function (Company $company) use ($siteCounts): array {
            $id = (string) $company->id;
            $sites = $siteCounts[$id] ?? 0;

            return [
                'id' => $id,
                'name' => $company->name,
                'slug' => $company->slug,
                'status' => $company->status,
                'country' => $company->country,
                'currency' => $company->currency,
                // Intention Trade portée jusqu'au tenant (ONB-02).
                'market_type' => $company->market_type,
                'trade_profile' => $company->trade_profile,
                'created_at' => $company->created_at?->toIso8601String(),
                // Faits de provisionnement uniquement — aucun agrégat métier.
                'sites' => $sites,
                'has_site' => $sites > 0,
                'provisioning_step' => $this->provisioningStep($company, $sites),
                // `features` est casté `array` par le modèle : les clés
                // actives sont les seules qui intéressent l'opérateur.
                'modules_active' => array_keys(array_filter(
                    $company->features,
                    static fn ($enabled): bool => (bool) $enabled,
                )),
            ];
        })->all());
    }

    /**
     * Étape de provisionnement atteinte, dérivée de faits vérifiables.
     *
     * L'ordre reflète le parcours réel d'onboarding universel : le tenant
     * existe → il a un établissement → son onboarding est initialisé →
     * il a activé un module métier. On ne prétend pas connaître les étapes
     * futures : `blocked` signale explicitement l'absence d'établissement.
     */
    private function provisioningStep(Company $company, int $sites): string
    {
        if ($sites === 0) {
            return 'blocked_no_site';
        }

        if (! $this->hasOnboardingSteps((string) $company->id)) {
            return 'site_ready';
        }

        $hasModule = array_filter($company->features, static fn ($v): bool => (bool) $v) !== [];

        return $hasModule ? 'operational' : 'onboarding_started';
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, int>
     */
    private function siteCounts(array $ids): array
    {
        if (! Schema::hasTable('sites')) {
            return [];
        }

        // Through the MODEL, not the query builder : `Site` carries no
        // `$attributes` defaults precisely so that a minimal `sites` schema
        // (test fixture) still accepts the SELECT.
        $rows = Site::query()
            ->whereIn('company_id', $ids)
            ->groupBy('company_id')
            ->selectRaw('company_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'company_id');

        $counts = [];

        foreach ($rows as $id => $count) {
            $counts[(string) $id] = (int) $count;
        }

        return $counts;
    }

    private function companiesWithoutSite(): int
    {
        if (! Schema::hasTable('sites')) {
            return 0;
        }

        $withSite = Site::query()->distinct()->pluck('company_id')->all();

        $query = Company::query();

        // Liste vide → PAS de `whereNotIn` : la colonne `id` est un UUID en
        // PostgreSQL et une sentinelle textuelle ferait échouer la requête
        // (« invalid input syntax for type uuid »). Une liste vide de sites
        // signifie simplement que TOUTES les entreprises sont concernées.
        if ($withSite !== []) {
            $query->whereNotIn('id', $withSite);
        }

        return $query->count();
    }

    private function onboardingIncomplete(): int
    {
        if (! Schema::hasTable('onboarding_steps')) {
            return 0;
        }

        // Un tenant « incomplet » = des étapes existent mais aucune n'est
        // terminée. On ne compte PAS les tenants sans aucune étape seedée :
        // ce sont ceux qui n'ont pas encore démarré l'onboarding (autre
        // diagnostic, déjà couvert par `site_ready`).
        $started = DB::table('onboarding_steps')->distinct()->pluck('company_id')->all();

        if ($started === []) {
            return 0;
        }

        $completed = DB::table('onboarding_steps')
            ->whereIn('status', ['completed', 'skipped'])
            ->distinct()
            ->pluck('company_id')
            ->all();

        return count(array_diff(array_map('strval', $started), array_map('strval', $completed)));
    }

    private function stalledProvisionings(): int
    {
        if (! Schema::hasTable('public.trial_provisionings')) {
            return 0;
        }

        return DB::table('public.trial_provisionings')
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(self::STALL_THRESHOLD_MINUTES))
            ->count();
    }

    private function hasOnboardingSteps(string $companyId): bool
    {
        if (! Schema::hasTable('onboarding_steps')) {
            return false;
        }

        return DB::table('onboarding_steps')->where('company_id', $companyId)->exists();
    }

    /**
     * Masque un email en gardant sa forme reconnaissable : `j***n@e***.com`.
     */
    private function maskEmail(string $email): string
    {
        $at = strpos($email, '@');

        if ($at === false || $at === 0) {
            return '***';
        }

        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        $dot = strrpos($domain, '.');

        $maskedLocal = $local[0].'***'.(strlen($local) > 1 ? substr($local, -1) : '');
        $maskedDomain = $dot === false
            ? '***'
            : $domain[0].'***'.substr($domain, $dot);

        return $maskedLocal.'@'.$maskedDomain;
    }
}
