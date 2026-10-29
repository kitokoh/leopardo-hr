<?php

declare(strict_types=1);

namespace Tests\Feature\Showcase;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8727 — backfill additif + contrôle de parité Showcase → Pages.
 *
 * Ces tests verrouillent les trois propriétés qui rendent la migration
 * exécutable en production :
 *
 * 1. **Sans perte** — contenu, ordre, types et statuts sont préservés, ce qui
 *    est le critère central de l'issue ;
 * 2. **Idempotence** — deux exécutions produisent le même état qu'une seule ;
 * 3. **Prudence** — sans `--apply` rien n'est écrit, et les cas ambigus ne sont
 *    jamais tranchés automatiquement.
 */
class ShowcasePagesBackfillCommandTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $slug): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'slug' => $slug,
        ]);

        return $company;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function showcase(Company $company, array $overrides = []): int
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $overrides): int {
            return (int) DB::table('company_showcases')->insertGetId(array_merge([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => 'published',
                'theme' => 'default',
                'created_at' => now(),
                'updated_at' => now(),
            ], $overrides));
        });
    }

    private function site(Company $company, string $name = 'Siège'): int
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $name): int {
            return (int) DB::table('sites')->insertGetId([
                'company_id' => $company->id,
                'name' => $name,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function section(Company $company, int $showcaseId, string $type, array $content, int $sortOrder): int
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $showcaseId, $type, $content, $sortOrder): int {
            return (int) DB::table('company_showcase_sections')->insertGetId([
                'company_id' => $company->id,
                'showcase_id' => $showcaseId,
                'type' => $type,
                'content' => json_encode($content, JSON_THROW_ON_ERROR),
                'sort_order' => $sortOrder,
                'schema_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function report(string $path): array
    {
        $raw = File::get($path);
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function seedProjectable(Company $company): int
    {
        $showcaseId = $this->showcase($company);
        $this->site($company);
        $this->section($company, $showcaseId, 'hero', ['heading' => 'Bienvenue', 'subheading' => 'Chez nous'], 0);
        $this->section($company, $showcaseId, 'features', [
            'items' => [['title' => 'Rapide', 'description' => 'En 5 min']],
        ], 1);

        return $showcaseId;
    }

    public function test_dry_run_writes_nothing(): void
    {
        $company = $this->company('dry-run-co');
        $this->seedProjectable($company);
        $report = storage_path('app/test/backfill-dry.json');

        $exit = Artisan::call('showcase:pages-backfill', ['--tenant' => 'dry-run-co', '--report' => $report]);

        $this->assertSame(0, $exit);
        $data = $this->report($report);
        $this->assertFalse($data['applied']);
        $this->assertSame(1, $data['totals']['to_create']);
        $this->assertSame(2, $data['totals']['blocks']);

        // Rien n'a été écrit : c'est la garantie qui autorise une répétition.
        app(TenantManager::class)->withinTenant($company, function (): void {
            $this->assertSame(0, DB::table('pages')->count());
            $this->assertSame(0, DB::table('page_blocks')->count());
        });
    }

    public function test_apply_creates_page_and_blocks_preserving_content_and_order(): void
    {
        $company = $this->company('apply-co');
        $showcaseId = $this->showcase($company);
        $siteId = $this->site($company);
        // Ordre volontairement inversé à l'insertion : c'est `sort_order` qui
        // fait foi, pas l'ordre de création des lignes.
        $this->section($company, $showcaseId, 'contact', ['title' => 'Nous écrire'], 5);
        $this->section($company, $showcaseId, 'hero', ['heading' => 'Bienvenue'], 0);

        $report = storage_path('app/test/backfill-apply.json');
        $exit = Artisan::call('showcase:pages-backfill', [
            '--tenant' => 'apply-co',
            '--apply' => true,
            '--report' => $report,
        ]);

        $this->assertSame(0, $exit);

        app(TenantManager::class)->withinTenant($company, function () use ($siteId): void {
            $page = DB::table('pages')->where('site_id', $siteId)->first();
            $this->assertNotNull($page);
            $this->assertSame('apply-co', $page->slug);
            $this->assertSame('published', $page->status);

            $blocks = DB::table('page_blocks')->where('page_id', $page->id)->orderBy('position')->get();
            $this->assertCount(2, $blocks);

            $first = $blocks->get(0);
            $second = $blocks->get(1);
            $this->assertNotNull($first);
            $this->assertNotNull($second);

            // `sort_order` 0 avant 5 → hero avant contact.
            $this->assertSame('core.hero', $first->type);
            $this->assertSame('core.contact', $second->type);

            $heroContent = json_decode((string) $first->content, true);
            $this->assertIsArray($heroContent);
            $this->assertSame('Bienvenue', $heroContent['heading']);
        });
    }

    public function test_backfill_is_idempotent(): void
    {
        $company = $this->company('idem-co');
        $this->seedProjectable($company);

        $first = Artisan::call('showcase:pages-backfill', [
            '--tenant' => 'idem-co',
            '--apply' => true,
            '--report' => storage_path('app/test/idem-1.json'),
        ]);
        $this->assertSame(0, $first);

        // Seconde exécution : la vitrine est déjà migrée, donc sautée.
        $second = Artisan::call('showcase:pages-backfill', [
            '--tenant' => 'idem-co',
            '--apply' => true,
            '--report' => storage_path('app/test/idem-2.json'),
        ]);
        $this->assertSame(0, $second);

        $data = $this->report(storage_path('app/test/idem-2.json'));
        $this->assertSame(0, $data['totals']['to_create']);
        $this->assertSame(1, $data['totals']['skipped_existing']);

        // Une seule Page, deux blocs : aucune duplication.
        app(TenantManager::class)->withinTenant($company, function (): void {
            $this->assertSame(1, DB::table('pages')->count());
            $this->assertSame(2, DB::table('page_blocks')->count());
        });
    }

    public function test_showcase_without_site_is_blocked_not_guessed(): void
    {
        $company = $this->company('no-site-co');
        $this->showcase($company);
        // Aucun Site créé.

        $exit = Artisan::call('showcase:pages-backfill', [
            '--tenant' => 'no-site-co',
            '--apply' => true,
            '--report' => storage_path('app/test/no-site.json'),
        ]);

        $data = $this->report(storage_path('app/test/no-site.json'));
        $this->assertSame(1, $data['totals']['blocked']);
        $this->assertSame('showcase_without_site', $data['blocked_detail'][0]['reason']);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $this->assertSame(0, DB::table('pages')->count());
        });

        // Une migration partielle doit être visible dans un pipeline.
        $this->assertSame(1, $exit);
    }

    public function test_ambiguous_sites_are_blocked_because_pages_site_id_is_unique(): void
    {
        $company = $this->company('multi-site-co');
        $this->showcase($company);
        $this->site($company, 'Siège');
        $this->site($company, 'Succursale');

        Artisan::call('showcase:pages-backfill', [
            '--tenant' => 'multi-site-co',
            '--apply' => true,
            '--report' => storage_path('app/test/multi-site.json'),
        ]);

        $data = $this->report(storage_path('app/test/multi-site.json'));
        $this->assertSame(1, $data['totals']['blocked']);
        $this->assertSame('ambiguous_sites', $data['blocked_detail'][0]['reason']);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $this->assertSame(0, DB::table('pages')->count());
        });
    }

    public function test_parity_reports_lossless_after_backfill(): void
    {
        $company = $this->company('parity-ok-co');
        $this->seedProjectable($company);

        Artisan::call('showcase:pages-backfill', [
            '--tenant' => 'parity-ok-co',
            '--apply' => true,
            '--report' => storage_path('app/test/parity-backfill.json'),
        ]);

        $exit = Artisan::call('showcase:pages-parity', [
            '--tenant' => 'parity-ok-co',
            '--report' => storage_path('app/test/parity-ok.json'),
        ]);

        $data = $this->report(storage_path('app/test/parity-ok.json'));
        $this->assertTrue($data['lossless']);
        $this->assertSame([], $data['mismatches']);
        $this->assertSame(0, $exit);
    }

    public function test_parity_detects_a_lost_field(): void
    {
        $company = $this->company('parity-bad-co');
        $showcaseId = $this->showcase($company);
        $siteId = $this->site($company);
        $this->section($company, $showcaseId, 'hero', ['heading' => 'Bienvenue', 'subheading' => 'Nous sommes ouverts'], 0);

        Artisan::call('showcase:pages-backfill', [
            '--tenant' => 'parity-bad-co',
            '--apply' => true,
            '--report' => storage_path('app/test/parity-bad-backfill.json'),
        ]);

        // Perte simulée : le bloc perd `subheading`. C'est exactement le type
        // d'écart qu'un backfill bâclé produirait, et que le contrôle doit
        // refuser de laisser passer.
        app(TenantManager::class)->withinTenant($company, function () use ($siteId): void {
            $page = DB::table('pages')->where('site_id', $siteId)->first();
            $this->assertNotNull($page);
            DB::table('page_blocks')->where('page_id', $page->id)->update([
                'content' => json_encode(['heading' => 'Bienvenue'], JSON_THROW_ON_ERROR),
            ]);
        });

        $exit = Artisan::call('showcase:pages-parity', [
            '--tenant' => 'parity-bad-co',
            '--report' => storage_path('app/test/parity-bad.json'),
        ]);

        $data = $this->report(storage_path('app/test/parity-bad.json'));
        $this->assertFalse($data['lossless']);
        $this->assertNotEmpty($data['mismatches']);
        $this->assertStringContainsString('subheading', (string) $data['mismatches'][0]['reason']);
        $this->assertSame(1, $exit);
    }

    public function test_parity_counts_unmigrated_without_calling_it_a_divergence(): void
    {
        $company = $this->company('parity-pending-co');
        $this->seedProjectable($company);

        // Pas de backfill : la vitrine est projetable mais pas migrée.
        $exit = Artisan::call('showcase:pages-parity', [
            '--tenant' => 'parity-pending-co',
            '--report' => storage_path('app/test/parity-pending.json'),
        ]);

        $data = $this->report(storage_path('app/test/parity-pending.json'));
        $this->assertSame([], $data['mismatches']);
        $this->assertSame(1, $data['pending_backfill']);
        // Non migré ≠ divergent : le contrôle ne doit pas mentir sur l'état.
        $this->assertTrue($data['lossless']);
        $this->assertSame(0, $exit);
    }
}
