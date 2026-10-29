<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Core\Tenant\TenantManager;
use App\Modules\Pages\Domain\Enums\PageStatus;
use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageBlockModel;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Support\ShowcasePageProjection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * #8727 — Contrôle de parité Showcase ↔ Pages « sans perte ».
 *
 * ## Pourquoi une commande séparée du backfill
 *
 * Le backfill écrit ce qu'il croit juste ; cette commande **relit** ce qui est
 * en base et le compare au legacy. Si les deux vivaient dans le même code, le
 * contrôle validerait par construction ce que le backfill vient d'écrire — il
 * ne prouverait rien. Ici, la comparaison est faite indépendamment, sur les
 * lignes réelles.
 *
 * ## Ce que « sans perte » signifie concrètement
 *
 * Pour chaque vitrine projetable (un seul Site, pas encore migrée ou déjà
 * migrée), la Page correspondante doit satisfaire :
 *
 * 1. **même slug** — sinon l'URL publique change et les liens en circulation
 *    cassent ;
 * 2. **même statut de publication** — une vitrine publiée doit être publiée,
 *    jamais moins (perte de visibilité) ;
 * 3. **même nombre de sections/blocs** ;
 * 4. **même ordre** — `sort_order` Showcase → `position` Pages ;
 * 5. **même type** — `hero` → `core.hero` ;
 * 6. **même contenu à la clé près** — c'est la contrainte la plus forte, et
 *    celle que `CorePageBlocks` rend tenable en réutilisant les mêmes noms de
 *    propriétés.
 *
 * Toute divergence est listée avec sa cause. Le code de sortie est non nul dès
 * la première divergence : une migration de données ne doit pas « réussir » en
 * ayant silencieusement perdu un champ.
 */
class ShowcasePagesParityCommand extends Command
{
    protected $signature = 'showcase:pages-parity
        {--tenant= : Restreint à un tenant (slug de société)}
        {--report= : Chemin du rapport JSON}';

    protected $description = '#8727 — Vérifie que la migration Showcase → Pages est sans perte (contenu, ordre, types, statuts).';

    public function handle(TenantManager $tenants): int
    {
        $tenantFilter = $this->option('tenant');
        $tenantFilter = is_string($tenantFilter) ? $tenantFilter : '';

        $companies = $tenantFilter !== ''
            ? Company::query()->where('slug', $tenantFilter)->orderBy('slug')->get()
            : Company::query()->orderBy('slug')->get();

        if ($companies->isEmpty()) {
            $this->error('Aucune société trouvée.');

            return self::FAILURE;
        }

        $results = [];
        $mismatches = [];
        $pending = 0;
        $failed = [];

        foreach ($companies as $company) {
            try {
                $result = $tenants->withinTenant(
                    $company,
                    fn (): array => $this->compareTenant($company),
                );
            } catch (Throwable $e) {
                $failed[] = ['company_slug' => (string) $company->slug, 'error' => $e->getMessage()];

                continue;
            }

            $results[] = $result['summary'];
            $pending += $result['pending'];
            foreach ($result['mismatches'] as $mismatch) {
                $mismatches[] = $mismatch;
            }
        }

        $this->line('');
        $this->line('Parité Showcase → Pages (#8727)');
        foreach ($results as $row) {
            $this->line(sprintf(
                '  %-24s vitrines=%d  comparées=%d  conformes=%d  divergentes=%d  non migrées=%d',
                (string) $row['company_slug'],
                $row['showcases'],
                $row['compared'],
                $row['ok'],
                $row['divergent'],
                $row['not_migrated'],
            ));
        }
        $this->line('');

        if ($mismatches !== []) {
            $this->error('  ❌ '.count($mismatches).' divergence(s) — la migration n’est PAS sans perte.');
            foreach (array_slice($mismatches, 0, 20) as $mismatch) {
                $this->line(sprintf(
                    '     %s · vitrine #%s · %s',
                    (string) $mismatch['company_slug'],
                    (string) $mismatch['showcase_id'],
                    (string) $mismatch['reason'],
                ));
            }
            if (count($mismatches) > 20) {
                $this->line('     … '.(count($mismatches) - 20).' de plus dans le rapport.');
            }
        } else {
            $this->line('  ✅ Aucune divergence : la migration est sans perte sur le périmètre inspecté.');
        }

        if ($pending > 0) {
            $this->warn('  ℹ️  '.$pending.' vitrine(s) projetable(s) pas encore migrée(s) — lancer showcase:pages-backfill --apply.');
        }

        $path = $this->writeReport($tenantFilter, $results, $mismatches, $pending, $failed);
        $this->line('  rapport : '.$path);

        // Les vitrines non encore migrées ne sont PAS une divergence : le
        // contrôle porte sur ce qui a été migré. Elles restent visibles.
        return ($mismatches === [] && $failed === []) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{
     *     summary: array<string, mixed>,
     *     mismatches: list<array<string, mixed>>,
     *     pending: int
     * }
     */
    private function compareTenant(Company $company): array
    {
        $showcases = CompanyShowcase::query()->select(['id', 'company_id', 'slug', 'status'])->orderBy('id')->get();
        $sites = Site::query()->where('company_id', $company->id)->pluck('id');

        $compared = 0;
        $ok = 0;
        $notMigrated = 0;
        $mismatches = [];

        foreach ($showcases as $showcase) {
            /** @var CompanyShowcase $showcase */
            // Les vitrines non projetables ne sont pas comparées : elles n'ont
            // pas de Page cible par construction (même règle que le backfill).
            if ($sites->count() !== 1) {
                continue;
            }

            $page = Page::query()->where('site_id', (int) $sites->first())->first();

            if ($page === null) {
                $notMigrated++;

                continue;
            }

            $compared++;

            $sectionRows = CompanyShowcaseSection::query()
                ->where('showcase_id', $showcase->id)
                ->ordered()
                ->get();

            $blockRows = PageBlockModel::query()
                ->where('page_id', $page->id)
                ->orderBy('position')
                ->get();

            $reasons = [];

            if ($page->slug !== ShowcasePageProjection::slug((string) $showcase->slug)) {
                $reasons[] = sprintf('slug « %s » ≠ « %s »', $page->slug, $showcase->slug);
            }

            // La projection (Domain Showcase) rend un enum LOCAL à Showcase :
            // la traduction vers `PageStatus` appartient à cette couche, seule
            // autorisée à connaître le module Pages (règle d'isolation #5584).
            $expectedStatus = PageStatus::from(
                ShowcasePageProjection::pageStatus($showcase->status)->value
            );
            if ($page->status !== $expectedStatus) {
                $reasons[] = sprintf('statut %s ≠ attendu %s', $page->status->value, $expectedStatus->value);
            }

            if ($sectionRows->count() !== $blockRows->count()) {
                $reasons[] = sprintf(
                    'nombre de blocs %d ≠ %d section(s) Showcase',
                    $blockRows->count(),
                    $sectionRows->count(),
                );
            } else {
                foreach ($sectionRows as $index => $section) {
                    /** @var CompanyShowcaseSection $section */
                    $block = $blockRows->get($index);
                    if ($block === null) {
                        continue;
                    }

                    $expectedType = ShowcasePageProjection::blockType($section->type);

                    if ($expectedType === null) {
                        $reasons[] = sprintf('section #%d : type %s sans équivalent Pages', $section->id, $section->type->value);

                        continue;
                    }

                    if ($block->type !== $expectedType) {
                        $reasons[] = sprintf('ordre/type bloc %d : %s ≠ %s', $index, $block->type, $expectedType);
                    }

                    $expectedContent = ShowcasePageProjection::blockContent($section->content);
                    $actualContent = $block->content;

                    if ($expectedContent !== $actualContent) {
                        $reasons[] = sprintf(
                            'contenu bloc %d (%s) divergent : %s',
                            $index,
                            $expectedType,
                            implode(', ', $this->diffKeys($expectedContent, $actualContent)),
                        );
                    }
                }
            }

            if ($reasons === []) {
                $ok++;
            } else {
                $mismatches[] = [
                    'company_slug' => (string) $company->slug,
                    'showcase_id' => (int) $showcase->id,
                    'page_id' => (int) $page->id,
                    'reason' => implode(' ; ', $reasons),
                ];
            }
        }

        return [
            'summary' => [
                'company_slug' => (string) $company->slug,
                'showcases' => $showcases->count(),
                'compared' => $compared,
                'ok' => $ok,
                'divergent' => count($mismatches),
                'not_migrated' => $notMigrated,
            ],
            'mismatches' => $mismatches,
            'pending' => $notMigrated,
        ];
    }

    /**
     * Liste les clés qui diffèrent entre deux contenus — pour un message
     * exploitable (« contenu divergent » ne dit pas quoi corriger).
     *
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     * @return list<string>
     */
    private function diffKeys(array $expected, array $actual): array
    {
        $keys = array_unique([...array_keys($expected), ...array_keys($actual)]);
        $diff = [];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $expected) || ! array_key_exists($key, $actual)) {
                $diff[] = (string) $key;

                continue;
            }
            if ($expected[$key] !== $actual[$key]) {
                $diff[] = (string) $key;
            }
        }

        return $diff === [] ? ['ordre des clés'] : $diff;
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @param  list<array<string, mixed>>  $mismatches
     * @param  list<array<string, mixed>>  $failed
     */
    private function writeReport(string $tenantFilter, array $results, array $mismatches, int $pending, array $failed): string
    {
        $path = $this->option('report');
        if (! is_string($path) || $path === '') {
            $path = storage_path('app/audit-showcase-pages-parity/report-'.now()->format('Ymd-His').'.json');
        }

        File::ensureDirectoryExists(dirname($path));

        $payload = [
            'issue' => '#8727',
            'kind' => 'showcase-to-pages parity (lossless check)',
            'generated_at' => now()->toIso8601String(),
            'scope' => ['tenant_filter' => $tenantFilter !== '' ? $tenantFilter : null],
            'lossless' => $mismatches === [] && $failed === [],
            'pending_backfill' => $pending,
            'tenants' => $results,
            'mismatches' => $mismatches,
            'failed' => $failed,
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \RuntimeException('Impossible d’encoder le rapport de parité.');
        }

        File::put($path, $encoded);

        return $path;
    }
}
