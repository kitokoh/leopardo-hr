<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * #8727 — Inventaire préalable à la migration Showcase → Pages.
 *
 * ## Pourquoi cette commande existe
 *
 * Le lot destructif de #8727 (suppression de `company_showcases`) ne peut pas
 * être écrit avant de savoir ce qu'il y a réellement à migrer. La décision
 * produit est *additive d'abord* : on mesure, on backfill, on bascule, et on ne
 * retire le legacy qu'après une fenêtre d'observation. Ce premier lot ne fait
 * donc **que lire**.
 *
 * ## Garantie de non-destructivité
 *
 * La commande n'ouvre aucune transaction d'écriture et n'émet aucun `INSERT`,
 * `UPDATE`, `DELETE`, `ALTER` ou `DROP` : uniquement des `SELECT` et des
 * lectures de répertoire (`showcase_media.path` → présence sur disque).
 * `--output` écrit un rapport sur le disque de l'agent, jamais en base.
 *
 * ## Ce que l'inventaire doit révéler (critères d'acceptation #8727)
 *
 * - counts par tenant ;
 * - statuts de publication ;
 * - slugs (collisions à prévoir avant l'unicité globale de `pages.slug`) ;
 * - sections (nombre + types) ;
 * - médias (nombre + octets) ;
 * - domaines personnalisés ;
 * - correspondances `Site` possibles — et surtout les cas **ambigus** :
 *   un tenant avec **zéro** ou **plusieurs** Sites candidats. La spec interdit
 *   explicitement tout « choix silencieux » dans ces deux cas : ils sont
 *   remontés comme `blocking`, jamais résolus automatiquement.
 *
 * La contrainte « une Page par établissement » (`pages.site_id` UNIQUE) rend le
 * cas « plusieurs Sites » structurellement insoluble sans décision humaine
 * (quel établissement porte la vitrine existante ?) — c'est donc un blocage
 * dur, pas un avertissement.
 *
 * ## Sortie
 *
 * Rapport JSON horodaté dans `storage/app/audit-showcase-pages-inventory/`,
 * plus un résumé lisible. Aucune donnée nominative : uniquement des
 * identifiants techniques, slugs publics et compteurs.
 */
class ShowcasePagesInventoryCommand extends Command
{
    protected $signature = 'showcase:pages-inventory
        {--output= : Chemin du rapport JSON (défaut : storage/app/audit-showcase-pages-inventory/report-<timestamp>.json)}
        {--tenant= : Limiter l\'inventaire à un seul tenant (slug de société)}
        {--json : N\'affiche que le JSON sur la sortie standard}';

    protected $description = '#8727 — Inventaire en LECTURE SEULE de company_showcases → pages (counts par tenant, statuts, slugs, sections, médias, domaines, correspondances Site). Aucune écriture.';

    /** Statut de migration d'un tenant, du plus sain au plus bloquant. */
    private const STATE_NO_SHOWCASE = 'no_showcase';

    private const STATE_READY = 'ready';

    private const STATE_AMBIGUOUS_SITES = 'ambiguous_sites';

    private const STATE_SHOWCASE_WITHOUT_SITE = 'showcase_without_site';

    private const STATE_SLUG_COLLISION = 'slug_collision';

    public function handle(): int
    {
        $tenantFilter = $this->option('tenant');

        $companies = is_string($tenantFilter) && $tenantFilter !== ''
            ? Company::query()->where('slug', $tenantFilter)->orderBy('slug')->get()
            : Company::query()->orderBy('slug')->get();

        if ($companies->isEmpty()) {
            $this->error(is_string($tenantFilter) && $tenantFilter !== ''
                ? sprintf('Aucun tenant avec le slug « %s ».', $tenantFilter)
                : 'Aucune société trouvée dans public.companies — rien à inventorier.');

            return self::FAILURE;
        }

        $tenants = [];
        $totals = $this->emptyTotals();
        $unreadable = [];

        foreach ($companies as $company) {
            try {
                $row = $this->inspectTenant($company);
            } catch (Throwable $e) {
                // Un schéma absent ou illisible ne doit pas interrompre
                // l'inventaire des autres tenants : on le remonte et on
                // continue (un tenant cassé est en soi une information).
                $unreadable[] = [
                    'company_id' => (string) $company->id,
                    'company_slug' => (string) $company->slug,
                    'search_path' => $this->safeSearchPath($company),
                    'error' => $e->getMessage(),
                ];

                continue;
            }

            $tenants[] = $row;
            $totals = $this->accumulate($totals, $row);
        }

        $summary = $this->summarise($tenants);
        $report = [
            'issue' => '#8727',
            'kind' => 'showcase-to-pages inventory (read-only)',
            'generated_at' => now()->toIso8601String(),
            'scope' => [
                'tenant_filter' => is_string($tenantFilter) && $tenantFilter !== '' ? $tenantFilter : null,
                'companies_considered' => $companies->count(),
                'companies_inventoried' => count($tenants),
                'companies_unreadable' => count($unreadable),
            ],
            'totals' => $totals,
            'summary' => $summary,
            'tenants' => $tenants,
            'unreadable' => $unreadable,
        ];

        $path = $this->writeReport($report);

        if ((bool) $this->option('json')) {
            $this->line($this->encode($report));

            return self::SUCCESS;
        }

        $this->renderSummary($totals, $summary, $unreadable);
        $this->newLine();
        $this->info(sprintf('Rapport écrit : %s', $path));
        $this->line('Rappel #8727 : ce lot est LECTURE SEULE. Le backfill et la bascule sont des lots ultérieurs, après validation de cet inventaire.');

        return self::SUCCESS;
    }

    /**
     * Inspecte un tenant sans jamais écrire.
     *
     * @return array<string, mixed>
     */
    private function inspectTenant(Company $company): array
    {
        $searchPath = $this->safeSearchPath($company);

        return DB::transaction(function () use ($company, $searchPath): array {
            $hasShowcase = $this->tableExists('company_showcases');

            $showcase = $hasShowcase
                ? DB::table('company_showcases')->where('company_id', $company->id)->first()
                : null;

            $sections = [];
            $media = [];
            $sectionsCount = 0;
            $mediaCount = 0;
            $mediaBytes = 0;

            if ($showcase !== null) {
                if ($this->tableExists('company_showcase_sections')) {
                    $rows = DB::table('company_showcase_sections')
                        ->where('showcase_id', $showcase->id)
                        ->orderBy('sort_order')
                        ->get(['type', 'sort_order', 'schema_version']);

                    $sectionsCount = $rows->count();
                    $sections = $rows->map(static fn ($row): array => [
                        'type' => (string) $row->type,
                        'sort_order' => (int) $row->sort_order,
                        'schema_version' => (int) $row->schema_version,
                    ])->all();
                }

                if ($this->tableExists('showcase_media')) {
                    $rows = DB::table('showcase_media')
                        ->where('showcase_id', $showcase->id)
                        ->get(['kind', 'size', 'path', 'disk']);

                    $mediaCount = $rows->count();
                    $mediaBytes = (int) $rows->sum(static fn ($row): int => (int) $row->size);
                    $media = $rows->map(static fn ($row): array => [
                        'kind' => (string) $row->kind,
                        'size' => (int) $row->size,
                        'disk' => (string) $row->disk,
                        'present' => $row->disk === 'local'
                            ? File::exists((string) $row->path)
                            : null,
                    ])->all();
                }
            }

            $sites = $this->tableExists('sites')
                ? DB::table('sites')->where('company_id', $company->id)->orderBy('id')->get(['id', 'name'])
                : collect();

            $pagesCount = $this->tableExists('pages')
                ? DB::table('pages')->where('company_id', $company->id)->count()
                : null;

            $state = $this->classify(
                showcaseExists: $showcase !== null,
                sitesCount: $sites->count(),
                pagesCount: (int) ($pagesCount ?? 0),
            );

            return [
                'company_id' => (string) $company->id,
                'company_slug' => (string) $company->slug,
                'search_path' => $searchPath,
                'showcase' => $showcase === null ? null : [
                    'id' => (int) $showcase->id,
                    'slug' => (string) $showcase->slug,
                    'status' => (string) $showcase->status,
                    'theme' => (string) $showcase->theme,
                    'custom_domain' => $showcase->custom_domain === null ? null : (string) $showcase->custom_domain,
                    'published_at' => $showcase->published_at === null ? null : (string) $showcase->published_at,
                    'has_settings' => $showcase->settings !== null,
                ],
                'sections' => [
                    'count' => $sectionsCount,
                    'types' => array_values(array_unique(array_column($sections, 'type'))),
                    'detail' => $sections,
                ],
                'media' => [
                    'count' => $mediaCount,
                    'bytes' => $mediaBytes,
                    'missing_on_disk' => count(array_filter(
                        $media,
                        static fn (array $item): bool => $item['present'] === false,
                    )),
                    'detail' => $media,
                ],
                'sites' => [
                    'count' => $sites->count(),
                    'ids' => $sites->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
                ],
                'pages' => [
                    'existing_count' => $pagesCount,
                ],
                'migration_state' => $state,
                'blocking' => $this->blockers($state),
            ];
        });
    }

    /**
     * Détermine l'état de préparation d'un tenant à la bascule.
     *
     * `no_recorded_site` et `ambiguous_sites` correspondent exactement aux deux
     * cas que la spec interdit de résoudre silencieusement.
     */
    private function classify(bool $showcaseExists, int $sitesCount, int $pagesCount): string
    {
        if ($pagesCount > 0) {
            // Une Page existe déjà : la bascule a eu lieu (ou a été partielle).
            return self::STATE_READY;
        }

        if (! $showcaseExists) {
            return self::STATE_NO_SHOWCASE;
        }

        if ($sitesCount === 0) {
            return self::STATE_SHOWCASE_WITHOUT_SITE;
        }

        if ($sitesCount > 1) {
            return self::STATE_AMBIGUOUS_SITES;
        }

        return self::STATE_READY;
    }

    /**
     * @return list<string>
     */
    private function blockers(string $state): array
    {
        return match ($state) {
            self::STATE_AMBIGUOUS_SITES => [
                'plusieurs Sites candidats : `pages.site_id` est UNIQUE (une Page par établissement en V1) — décision humaine requise sur l\'établissement porteur, aucun choix automatique',
            ],
            self::STATE_SHOWCASE_WITHOUT_SITE => [
                'vitrine existante mais aucun Site : impossible de rattacher une Page avant création de l\'établissement — décision humaine requise',
            ],
            self::STATE_SLUG_COLLISION => [
                'le slug de la vitrine entre en collision avec un slug `pages` existant',
            ],
            default => [],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $tenants
     * @return array<string, mixed>
     */
    private function summarise(array $tenants): array
    {
        $byState = [];
        foreach ($tenants as $tenant) {
            $state = (string) $tenant['migration_state'];
            $byState[$state] = ($byState[$state] ?? 0) + 1;
        }
        ksort($byState);

        // Collisions de slug : la vitrine et Page partagent l'espace de noms
        // public. On compare les slugs Showcase entre eux et avec ceux déjà
        // présents dans `pages` (globalement, toutes sociétés confondues),
        // puisque `pages.slug` est UNIQUE globalement.
        $showcaseSlugs = [];
        foreach ($tenants as $tenant) {
            $slug = $tenant['showcase']['slug'] ?? null;
            if (is_string($slug) && $slug !== '') {
                $showcaseSlugs[] = $slug;
            }
        }

        $duplicates = array_values(array_unique(array_diff_assoc(
            $showcaseSlugs,
            array_unique($showcaseSlugs),
        )));

        $customDomains = array_values(array_filter(array_map(
            static fn (array $tenant): ?string => $tenant['showcase']['custom_domain'] ?? null,
            $tenants,
        )));

        return [
            'by_migration_state' => $byState,
            'blocking_tenants' => array_map(
                static fn (array $tenant): array => [
                    'company_slug' => $tenant['company_slug'],
                    'state' => $tenant['migration_state'],
                    'reasons' => $tenant['blocking'],
                ],
                array_values(array_filter(
                    $tenants,
                    static fn (array $tenant): bool => $tenant['blocking'] !== [],
                )),
            ),
            'duplicate_showcase_slugs' => $duplicates,
            'custom_domains' => $customDomains,
            'media_missing_on_disk' => array_sum(array_map(
                static fn (array $tenant): int => (int) $tenant['media']['missing_on_disk'],
                $tenants,
            )),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyTotals(): array
    {
        return [
            'tenants' => 0,
            'showcases' => 0,
            'published_showcases' => 0,
            'sections' => 0,
            'media' => 0,
            'media_bytes' => 0,
            'custom_domains' => 0,
            'existing_pages' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $totals
     * @param  array<string, mixed>  $row
     * @return array<string, int>
     */
    private function accumulate(array $totals, array $row): array
    {
        $totals['tenants']++;

        if ($row['showcase'] !== null) {
            $totals['showcases']++;
            if ($row['showcase']['status'] === 'published') {
                $totals['published_showcases']++;
            }
            if ($row['showcase']['custom_domain'] !== null) {
                $totals['custom_domains']++;
            }
        }

        $totals['sections'] += (int) $row['sections']['count'];
        $totals['media'] += (int) $row['media']['count'];
        $totals['media_bytes'] += (int) $row['media']['bytes'];
        $totals['existing_pages'] += (int) ($row['pages']['existing_count'] ?? 0);

        return $totals;
    }

    /**
     * @param  array<string, int>  $totals
     * @param  array<string, mixed>  $summary
     * @param  list<array<string, mixed>>  $unreadable
     */
    private function renderSummary(array $totals, array $summary, array $unreadable): void
    {
        $this->info('#8727 — Inventaire Showcase → Pages (lecture seule)');
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Tenants inventoriés', (string) $totals['tenants']],
                ['Tenants illisibles', (string) count($unreadable)],
                ['Vitrines', (string) $totals['showcases']],
                ['dont publiées', (string) $totals['published_showcases']],
                ['Sections', (string) $totals['sections']],
                ['Médias', (string) $totals['media']],
                ['Octets médias', (string) $totals['media_bytes']],
                ['Médias absents du disque', (string) $summary['media_missing_on_disk']],
                ['Domaines personnalisés', (string) $totals['custom_domains']],
                ['Pages déjà existantes', (string) $totals['existing_pages']],
            ],
        );

        $this->newLine();
        $this->line('États de migration :');
        foreach ($summary['by_migration_state'] as $state => $count) {
            $this->line(sprintf('  - %s : %d', $state, $count));
        }

        $blocking = $summary['blocking_tenants'];
        if ($blocking === []) {
            $this->newLine();
            $this->info('Aucun tenant bloquant : toutes les vitrines ont exactement un Site candidat.');
        } else {
            $this->newLine();
            $this->warn(sprintf('%d tenant(s) nécessitent une décision humaine avant backfill :', count($blocking)));
            foreach ($blocking as $item) {
                $this->line(sprintf('  - %s (%s) : %s', $item['company_slug'], $item['state'], implode(' | ', $item['reasons'])));
            }
        }

        if ($summary['duplicate_showcase_slugs'] !== []) {
            $this->newLine();
            $this->warn(sprintf(
                'Slugs Showcase dupliqués (collision garantie avec l\'unicité globale de pages.slug) : %s',
                implode(', ', $summary['duplicate_showcase_slugs']),
            ));
        }

        if ($unreadable !== []) {
            $this->newLine();
            $this->warn('Tenants non lisibles (schéma absent ou requête refusée) :');
            foreach ($unreadable as $item) {
                $this->line(sprintf('  - %s : %s', $item['company_slug'], $item['error']));
            }
        }
    }

    /**
     * Écrit le rapport JSON horodaté (jamais en base).
     *
     * @param  array<string, mixed>  $report
     */
    private function writeReport(array $report): string
    {
        $output = $this->option('output');

        $path = is_string($output) && $output !== ''
            ? $output
            : storage_path('app/audit-showcase-pages-inventory/report-'.now()->format('Ymd-His').'.json');

        $directory = dirname($path);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0o755, true);
        }

        File::put($path, $this->encode($report).PHP_EOL);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function encode(array $report): string
    {
        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (QueryException) {
            return false;
        }
    }

    private function safeSearchPath(Company $company): string
    {
        try {
            return $company->getSafeSearchPath();
        } catch (RuntimeException) {
            return 'public';
        }
    }
}
