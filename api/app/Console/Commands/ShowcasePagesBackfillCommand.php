<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Core\Tenant\TenantManager;
use App\Modules\Pages\Domain\Enums\PageStatus;
use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageBlockModel;
use App\Modules\Showcase\Domain\Enums\PageProjectionStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Support\ShowcasePageProjection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * #8727 — Backfill additif et idempotent `company_showcases` → `pages`.
 *
 * ## Position dans la séquence
 *
 * Lot 1 (livré) : `showcase:pages-inventory`, qui **mesure**.
 * Lot 2 (ceci)  : recopie les vitrines projetables vers Pages/Blocks.
 * Lot 3+        : double lecture, bascule, puis seulement retrait du legacy.
 *
 * Aucune donnée Showcase n'est modifiée ni supprimée : le legacy reste la
 * source de vérité tant que la bascule n'est pas faite. Cette commande ne fait
 * qu'**ajouter** des lignes `pages` / `page_blocks`.
 *
 * ## Idempotence
 *
 * La clé de correspondance est `pages.site_id` (unique). Une vitrine dont le
 * Site porte déjà une Page est **sautée**, jamais réécrite : relancer la
 * commande N fois produit le même état qu'une seule exécution. C'est la
 * propriété qui autorise à l'exécuter en production, sous supervision, sans
 * craindre un doublon au second passage.
 *
 * ## Dry-run par défaut
 *
 * Sans `--apply`, la commande n'écrit rien : une migration de données se relit
 * avant de s'écrire. Le plan est calculé une seule fois et c'est exactement lui
 * qui est appliqué ou affiché — pas deux chemins de code susceptibles de
 * diverger.
 *
 * ## Cas non projetables — jamais résolus ici
 *
 * Une vitrine sans établissement, ou sur un tenant à plusieurs établissements,
 * n'est pas projetable : `pages.site_id` est UNIQUE (une Page par établissement
 * en V1), donc choisir un Site serait une décision produit déguisée en
 * heuristique. Ces vitrines sont **laissées intactes** et comptées comme
 * bloquantes — même règle que l'inventaire.
 *
 * ## Isolation tenant
 *
 * Chaque tenant est traité dans `withinTenant()`. Une erreur sur un tenant
 * (schéma absent, migration non passée) n'interrompt pas les autres : elle est
 * remontée et le lot continue — un tenant cassé est en soi une information.
 */
class ShowcasePagesBackfillCommand extends Command
{
    protected $signature = 'showcase:pages-backfill
        {--apply : Écrit réellement en base (sans ce drapeau : simulation seule)}
        {--tenant= : Restreint à un tenant (slug de société)}
        {--report= : Chemin du rapport JSON}';

    protected $description = '#8727 — Backfill additif et idempotent des vitrines Showcase vers les Pages. Simulation par défaut.';

    public function handle(TenantManager $tenants): int
    {
        $apply = (bool) $this->option('apply');
        $tenantFilter = $this->option('tenant');
        $tenantFilter = is_string($tenantFilter) ? $tenantFilter : '';

        $companies = $tenantFilter !== ''
            ? Company::query()->where('slug', $tenantFilter)->orderBy('slug')->get()
            : Company::query()->orderBy('slug')->get();

        if ($companies->isEmpty()) {
            $this->error($tenantFilter !== ''
                ? sprintf('Aucun tenant avec le slug « %s ».', $tenantFilter)
                : 'Aucune société trouvée — rien à backfiller.');

            return self::FAILURE;
        }

        $this->line('');
        $this->line('Migration Showcase → Pages (#8727) — '.($apply ? 'MODE APPLIQUÉ' : 'SIMULATION'));

        $totals = [
            'to_create' => 0,
            'blocks' => 0,
            'skipped_existing' => 0,
            'blocked' => 0,
        ];
        $details = [];
        $blockedDetail = [];
        $failed = [];

        foreach ($companies as $company) {
            try {
                $result = $tenants->withinTenant($company, function () use ($company, $apply): array {
                    return $this->processTenant($company, $apply);
                });
            } catch (Throwable $e) {
                $failed[] = [
                    'company_id' => (string) $company->id,
                    'company_slug' => (string) $company->slug,
                    'error' => $e->getMessage(),
                ];

                continue;
            }

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $result[$key];
            }
            $details[] = $result['detail'];
            foreach ($result['blocked_detail'] as $blocked) {
                $blockedDetail[] = $blocked;
            }
        }

        $this->line('  à créer        : '.$totals['to_create'].' Page(s) / '.$totals['blocks'].' bloc(s)');
        $this->line('  déjà migrées   : '.$totals['skipped_existing'].' (idempotence — inchangées)');
        $this->line('  bloquantes     : '.$totals['blocked']);
        if ($failed !== []) {
            $this->warn('  tenants en erreur : '.count($failed));
        }
        $this->line('');

        $path = $this->writeReport($apply, $tenantFilter, $totals, $details, $blockedDetail, $failed);
        $this->line('  rapport : '.$path);

        if (! $apply) {
            $this->line('  ℹ️  Simulation : rien n’a été écrit. Relancer avec --apply pour appliquer.');
        }

        if ($totals['blocked'] > 0) {
            // Code de sortie non nul : une migration partielle doit être
            // visible dans un pipeline, pas seulement dans la sortie console.
            $this->warn('  ⚠️  '.$totals['blocked'].' vitrine(s) NON projetable(s) — décision humaine requise.');
        }

        // Une migration partielle doit être visible dans un pipeline, pas
        // seulement dans la sortie console : une vitrine bloquée ou un tenant
        // en erreur rendent la commande non verte.
        return ($failed === [] && $totals['blocked'] === 0) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Traite un tenant : planifie, puis applique si demandé.
     *
     * @return array{
     *     to_create: int,
     *     blocks: int,
     *     skipped_existing: int,
     *     blocked: int,
     *     detail: array<string, mixed>,
     *     blocked_detail: list<array<string, mixed>>
     * }
     */
    private function processTenant(Company $company, bool $apply): array
    {
        $plan = [];
        $blocked = 0;
        $blockedDetail = [];
        $skipped = 0;
        $blockCount = 0;

        $showcases = CompanyShowcase::query()
            ->select(['id', 'company_id', 'slug', 'status', 'published_at'])
            ->orderBy('id')
            ->get();

        foreach ($showcases as $showcase) {
            /** @var CompanyShowcase $showcase */
            $sites = Site::query()->where('company_id', $company->id)->pluck('id');

            if ($sites->isEmpty()) {
                $blocked++;
                $blockedDetail[] = [
                    'company_slug' => (string) $company->slug,
                    'showcase_id' => (int) $showcase->id,
                    'reason' => 'showcase_without_site',
                ];

                continue;
            }

            if ($sites->count() > 1) {
                // Insoluble par du code : `pages.site_id` est UNIQUE. Choisir un
                // établissement reviendrait à décider lequel porte la vitrine.
                $blocked++;
                $blockedDetail[] = [
                    'company_slug' => (string) $company->slug,
                    'showcase_id' => (int) $showcase->id,
                    'reason' => 'ambiguous_sites',
                    'candidates' => $sites->count(),
                ];

                continue;
            }

            $siteId = (int) $sites->first();

            if (Page::query()->where('site_id', $siteId)->exists()) {
                $skipped++;

                continue;
            }

            $sections = CompanyShowcaseSection::query()
                ->where('showcase_id', $showcase->id)
                ->ordered()
                ->get();

            $blockCount += $sections->count();

            $plan[] = [
                'company_slug' => (string) $company->slug,
                'showcase_id' => (int) $showcase->id,
                'site_id' => $siteId,
                'slug' => ShowcasePageProjection::slug((string) $showcase->slug),
                'status' => ShowcasePageProjection::pageStatus($showcase->status)->value,
                'published_at' => $showcase->published_at?->toIso8601String(),
                'sections' => $sections->count(),
            ];

            if ($apply) {
                $this->applyOne($company, $plan[array_key_last($plan)]);
            }
        }

        return [
            'to_create' => count($plan),
            'blocks' => $blockCount,
            'skipped_existing' => $skipped,
            'blocked' => $blocked,
            'detail' => [
                'company_slug' => (string) $company->slug,
                'pages_created' => $apply ? count($plan) : 0,
                'pages_planned' => count($plan),
                'skipped_existing' => $skipped,
                'blocked' => $blocked,
            ],
            'blocked_detail' => $blockedDetail,
        ];
    }

    /**
     * Écrit une Page et ses blocs pour une vitrine.
     *
     * @param  array<string, mixed>  $row
     */
    private function applyOne(Company $company, array $row): void
    {
        DB::transaction(function () use ($company, $row): void {
            /** @var Page $page */
            $page = Page::query()->create([
                'company_id' => $company->id,
                'site_id' => (int) $row['site_id'],
                'slug' => (string) $row['slug'],
                'status' => PageStatus::from((string) $row['status']),
                // Contrainte `pages_published_requires_date` : une Page publiée
                // sans date est rejetée par la base, donc on recopie celle de
                // la vitrine pour ne pas antidater la mise en ligne.
                // La projection raisonne sur SON enum (Domain Showcase, sans
                // dépendance à Pages) : la traduction vers `PageStatus` se fait
                // ici, en Infrastructure, où le module Pages est légitime.
                'published_at' => ShowcasePageProjection::effectivePublishedAt(
                    PageProjectionStatus::from((string) $row['status']),
                    is_string($row['published_at'] ?? null)
                        ? \Carbon\CarbonImmutable::parse((string) $row['published_at'])
                        : null,
                ),
            ]);

            $sections = CompanyShowcaseSection::query()
                ->where('showcase_id', (int) $row['showcase_id'])
                ->ordered()
                ->get();

            $position = 0;
            foreach ($sections as $section) {
                /** @var CompanyShowcaseSection $section */
                $blockType = ShowcasePageProjection::blockType($section->type);

                if ($blockType === null) {
                    // Type sans équivalent Pages : on ne crée pas de bloc
                    // « inconnu » qui serait stocké sans pouvoir être rendu.
                    // Le contrôle de parité le signalera.
                    continue;
                }

                $content = $section->content;

                PageBlockModel::query()->create([
                    'company_id' => $company->id,
                    'page_id' => $page->id,
                    'block_uid' => 'showcase-'.$section->id,
                    'type' => $blockType,
                    'schema_version' => 1,
                    'position' => $position++,
                    'content' => ShowcasePageProjection::blockContent($content),
                ]);
            }
        });
    }

    /**
     * @param  array<string, int>  $totals
     * @param  list<array<string, mixed>>  $details
     * @param  list<array<string, mixed>>  $blockedDetail
     * @param  list<array<string, mixed>>  $failed
     */
    private function writeReport(
        bool $apply,
        string $tenantFilter,
        array $totals,
        array $details,
        array $blockedDetail,
        array $failed,
    ): string {
        $path = $this->option('report');
        if (! is_string($path) || $path === '') {
            $path = storage_path('app/audit-showcase-pages-backfill/report-'.now()->format('Ymd-His').'.json');
        }

        File::ensureDirectoryExists(dirname($path));

        $payload = [
            'issue' => '#8727',
            'kind' => 'showcase-to-pages backfill (additive, idempotent)',
            'generated_at' => now()->toIso8601String(),
            'applied' => $apply,
            'scope' => ['tenant_filter' => $tenantFilter !== '' ? $tenantFilter : null],
            'totals' => $totals,
            'tenants' => $details,
            'blocked_detail' => $blockedDetail,
            'failed' => $failed,
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('Impossible d’encoder le rapport de backfill.');
        }

        File::put($path, $encoded);

        return $path;
    }
}
