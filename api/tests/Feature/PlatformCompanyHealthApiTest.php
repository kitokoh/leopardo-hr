<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Modules\Onboarding\Application\Actions\SeedDefaultSteps;
use App\Modules\Platform\Infrastructure\Services\PlatformCompanyHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PlatformCompanyHealthApiTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_super_admin_can_view_company_health_and_adoption_metrics(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-08 10:00:00', 'UTC'));

        DB::table('plans')->insert([
            'id' => 1,
            'name' => 'Operations',
            'price_monthly' => 99,
            'price_yearly' => 948,
            'max_employees' => 250,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $company = Company::factory()->create([
            'plan_id' => 1,
            'timezone' => 'UTC',
            'currency' => 'DZD',
            'features' => ['rh' => true, 'finance' => true],
            'metadata' => [
                'attendance_geofence' => [
                    'lat' => 36.7525,
                    'lng' => 3.0420,
                    'radius_meters' => 100,
                ],
            ],
        ]);
        $employeeA = Employee::factory()->create(['company_id' => $company->id, 'salary_base' => 173330]);
        $employeeB = Employee::factory()->create(['company_id' => $company->id, 'salary_base' => 120000]);

        // #7300 — « onboarding » côté back-office = progression CANONIQUE
        // (checklist setup). Le tenant de ce test est un client sain : ses
        // étapes setup doivent donc être réellement complétées, pas seulement
        // déduites d'une échelle parallèle.
        app()->instance('current_company', $company);
        $this->seedOnboardingSteps();
        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'date' => '2026-05-07',
            'check_in' => Carbon::parse('2026-05-07 08:20:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-07 17:00:00', 'UTC'),
            'late_minutes' => 20,
            'status' => 'late',
        ]);
        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
            'date' => '2026-05-08',
            'check_in' => Carbon::parse('2026-05-08 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-08 17:00:00', 'UTC'),
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $response = $this->getJson("/api/v1/platform/companies/{$company->id}/health");

        $response->assertOk();
        $response->assertJsonPath('data.company.id', $company->id);
        $response->assertJsonPath('data.company.slug', $company->slug);
        $response->assertJsonPath('data.company.created_at', $company->created_at?->toIso8601String());
        $response->assertJsonPath('data.plan.name', 'Operations');
        $response->assertJsonPath('data.subscription.mrr', 99);
        $response->assertJsonPath('data.features.active.finance', true);
        $response->assertJsonPath('data.adoption.risk_level', 'low');
        $response->assertJsonPath('data.adoption.employees.total', 2);
        $response->assertJsonPath('data.adoption.employees.payroll_ready', 2);
        $response->assertJsonPath('data.adoption.attendance.logs_30d', 2);
        $response->assertJsonPath('data.adoption.attendance.active_employees_30d', 2);
        $response->assertJsonPath('data.adoption.onboarding.progress_percent', 100);
        // #7300 — le back-office expose désormais la progression canonique
        // (`onboarding_steps`) ET l'adoption observée, explicitement distinctes.
        $response->assertJsonPath('data.adoption.onboarding.source', 'onboarding_steps');
        $response->assertJsonPath('data.adoption.onboarding.go_live_ready', true);
        $response->assertJsonPath('data.adoption.onboarding.observed.progress_percent', 100);
        $response->assertJsonPath('data.adoption.anomalies.total_30d', 1);
        $response->assertJsonPath('data.adoption.anomalies.business_impact.late_minutes', 20);
        $response->assertJsonPath('data.next_actions.0.key', 'prepare_upsell');

        Carbon::setTestNow();
    }

    public function test_company_health_flags_high_risk_when_subscription_is_not_active(): void
    {
        DB::table('plans')->insert([
            'id' => 1,
            'name' => 'Pilot',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'max_employees' => 30,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $company = Company::factory()->suspended()->create([
            'plan_id' => 1,
            'timezone' => 'UTC',
        ]);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $response = $this->getJson("/api/v1/platform/companies/{$company->id}/health");

        $response->assertOk();
        $response->assertJsonPath('data.adoption.risk_level', 'high');
        $response->assertJsonPath('data.next_actions.0.key', 'reactivate_subscription');
    }

    public function test_super_admin_can_view_portfolio_health_summary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-08 10:00:00', 'UTC'));

        DB::table('plans')->insert([
            ['id' => 1, 'name' => 'Pilot', 'price_monthly' => 29, 'price_yearly' => 290, 'max_employees' => 30, 'trial_days' => 14, 'is_active' => true],
            ['id' => 2, 'name' => 'Operations', 'price_monthly' => 99, 'price_yearly' => 948, 'max_employees' => 250, 'trial_days' => 14, 'is_active' => true],
        ]);

        $healthy = Company::factory()->create([
            'plan_id' => 2,
            'timezone' => 'UTC',
            'metadata' => [
                'attendance_geofence' => [
                    'lat' => 36.7525,
                    'lng' => 3.0420,
                    'radius_meters' => 100,
                ],
            ],
        ]);
        $risky = Company::factory()->suspended()->create([
            'plan_id' => 1,
            'timezone' => 'UTC',
        ]);

        $employee = Employee::factory()->create(['company_id' => $healthy->id, 'salary_base' => 173330]);

        app()->instance('current_company', $healthy);
        AttendanceLog::factory()->create([
            'company_id' => $healthy->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-08',
            'check_in' => Carbon::parse('2026-05-08 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-08 17:00:00', 'UTC'),
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $response = $this->getJson('/api/v1/platform/companies/health?limit=10');

        $response->assertOk();
        $response->assertJsonPath('data.summary.companies', 2);
        $response->assertJsonPath('data.summary.active_companies', 1);
        $response->assertJsonPath('data.summary.mrr', 128);
        $response->assertJsonPath('data.summary.risk.high', 1);
        $response->assertJsonPath('data.summary.risk.low', 1);

        $items = collect($response->json('data.items'));
        $healthyItem = $items->firstWhere('company.id', $healthy->id);
        $riskyItem = $items->firstWhere('company.id', $risky->id);

        $this->assertSame('low', $healthyItem['risk_level']);
        $this->assertSame('prepare_upsell', $healthyItem['next_action']['key']);
        $this->assertSame('high', $riskyItem['risk_level']);
        $this->assertSame('reactivate_subscription', $riskyItem['next_action']['key']);

        Carbon::setTestNow();
    }

    public function test_platform_company_detail_endpoints_resolve_public_company_after_tenant_search_path(): void
    {
        DB::table('plans')->insert([
            'id' => 1,
            'name' => 'Pilot',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'max_employees' => 30,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $company = Company::factory()->create([
            'plan_id' => 1,
            'timezone' => 'UTC',
            'features' => ['rh' => true],
        ]);

        DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');
        DB::statement('CREATE TABLE IF NOT EXISTS shared_tenants.companies (LIKE public.companies INCLUDING ALL)');
        DB::statement('SET search_path TO shared_tenants,public');

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $this->getJson("/api/v1/platform/companies/{$company->id}/health")
            ->assertOk()
            ->assertJsonPath('data.company.id', $company->id);

        $this->getJson("/api/v1/platform/companies/{$company->id}/subscription")
            ->assertOk()
            ->assertJsonPath('data.company_id', $company->id);

        $this->getJson("/api/v1/platform/companies/{$company->id}/features")
            ->assertOk()
            ->assertJsonPath('data.company_id', $company->id);
    }

    /**
     * #7302/#7339 — le coût d'une PAGE ne doit PAS suivre le nombre de sociétés
     * hors page.
     *
     * Avant #7302, `portfolio()` appelait `build()` en boucle : ~15 requêtes par
     * société (dont 3 `SET search_path` et un `Company::find` redondant dans les
     * anomalies). Mesuré sur 45 sociétés réelles : **674 requêtes** et ~27 s en
     * production. #7339 ajoute la pagination : la page mesurée reste de 5
     * sociétés, et l'on passe de 5 à 15 sociétés — 10 sont donc **hors page**.
     * Le nombre de requêtes doit rester borné et identique.
     */
    public function test_portfolio_query_count_does_not_grow_with_company_count(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-08 10:00:00', 'UTC'));

        DB::table('plans')->insert([
            'id' => 1,
            'name' => 'Pilot',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'max_employees' => 30,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        // 5 sociétés, page de 5 : aucune société hors page.
        $this->seedPortfolioCompanies(5);
        Cache::flush();
        $queriesForFive = $this->countPortfolioQueries(page: 1, perPage: 5);

        // 10 sociétés DE PLUS (15 au total), page toujours de 5 : les 10 autres
        // sont hors page et ne doivent rien coûter.
        $this->seedPortfolioCompanies(10);
        Cache::flush();
        $queriesForFifteen = $this->countPortfolioQueries(page: 1, perPage: 5);

        $this->assertLessThan(30, $queriesForFive, 'Le portefeuille doit tenir en un nombre borné de requêtes.');
        $this->assertLessThan(30, $queriesForFifteen, 'Le portefeuille doit tenir en un nombre borné de requêtes.');

        // Le point clé : 10 sociétés hors page ne doivent rien coûter de plus
        // qu'une poignée de requêtes (avant #7302 : ~15 par société, soit +150).
        $this->assertLessThanOrEqual(
            $queriesForFive + 2,
            $queriesForFifteen,
            "Le coût d'une page redevient linéaire en nombre de sociétés hors page ({$queriesForFive} requêtes pour 5 sociétés, {$queriesForFifteen} pour 15 dont 10 hors page).",
        );

        Carbon::setTestNow();
    }

    /**
     * #7339 — `GET /platform/companies/health` doit PAGINER (`?page=&per_page=`).
     *
     * Avant ce lot, l'endpoint ne connaissait que `limit`, plafonné à 100 : un
     * portefeuille de plus de 100 sociétés n'était **pas atteignable**, et la
     * réponse n'exposait aucune métadonnée de pagination. Ce test verrouille le
     * contrat : découpage en pages disjointes, dernière page partielle, page
     * hors bornes vide (sans erreur), synthèse bornée à la page et `meta`.
     */
    public function test_portfolio_exposes_page_metadata_and_disjoint_pages(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-08 10:00:00', 'UTC'));

        DB::table('plans')->insert([
            'id' => 1,
            'name' => 'Pilot',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'max_employees' => 30,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $this->seedPortfolioCompanies(5);
        Cache::flush();

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        // Page 1 sur 3 (per_page = 2).
        $first = $this->getJson('/api/v1/platform/companies/health?page=1&per_page=2')->assertOk();
        $first->assertJsonPath('meta.current_page', 1);
        $first->assertJsonPath('meta.per_page', 2);
        $first->assertJsonPath('meta.total', 5);
        $first->assertJsonPath('meta.last_page', 3);
        $first->assertJsonPath('meta.from', 1);
        $first->assertJsonPath('meta.to', 2);
        $first->assertJsonCount(2, 'data.items');
        // La synthèse décrit la PAGE — même sémantique que l'ancien `limit`.
        $first->assertJsonPath('data.summary.companies', 2);
        $firstIds = $this->portfolioCompanyIds($first);

        // Page 2 : deux autres sociétés, aucune recouvrante.
        $second = $this->getJson('/api/v1/platform/companies/health?page=2&per_page=2')->assertOk();
        $second->assertJsonPath('meta.current_page', 2);
        $second->assertJsonPath('meta.from', 3);
        $second->assertJsonPath('meta.to', 4);
        $second->assertJsonCount(2, 'data.items');
        $secondIds = $this->portfolioCompanyIds($second);

        $this->assertSame([], array_values(array_intersect($firstIds, $secondIds)), 'Deux pages consécutives ne doivent jamais servir la même société.');

        // Dernière page : partielle.
        $third = $this->getJson('/api/v1/platform/companies/health?page=3&per_page=2')->assertOk();
        $third->assertJsonPath('meta.from', 5);
        $third->assertJsonPath('meta.to', 5);
        $third->assertJsonCount(1, 'data.items');
        $thirdIds = $this->portfolioCompanyIds($third);

        // Les trois pages couvrent le portefeuille, sans trou ni doublon.
        $this->assertCount(5, array_unique(array_merge($firstIds, $secondIds, $thirdIds)));

        // Page hors bornes : vide, sans erreur, et `from`/`to` nuls (contrat
        // Laravel) — un client qui pagine ne doit pas recevoir un 500.
        $beyond = $this->getJson('/api/v1/platform/companies/health?page=4&per_page=2')->assertOk();
        $beyond->assertJsonCount(0, 'data.items');
        $beyond->assertJsonPath('meta.from', null);
        $beyond->assertJsonPath('meta.to', null);
        $beyond->assertJsonPath('data.summary.companies', 0);

        Carbon::setTestNow();
    }

    /**
     * #7339 — le contrat historique reste servi tel quel.
     *
     * `GET /platform/companies/health` sans paramètre doit rendre EXACTEMENT la
     * page d'avant la pagination (page 1, 50 sociétés), et `limit` reste accepté
     * comme alias de `per_page` (c'est le paramètre du contrat #7302, encore
     * envoyé par des appels existants). `per_page` est plafonné.
     */
    public function test_portfolio_defaults_and_legacy_limit_param_stay_compatible(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-08 10:00:00', 'UTC'));

        DB::table('plans')->insert([
            'id' => 1,
            'name' => 'Pilot',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'max_employees' => 30,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $this->seedPortfolioCompanies(3);
        Cache::flush();

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        // Appel historique SANS paramètre : page 1, taille de page 50.
        $legacy = $this->getJson('/api/v1/platform/companies/health')->assertOk();
        $legacy->assertJsonPath('meta.current_page', 1);
        $legacy->assertJsonPath('meta.per_page', 50);
        $legacy->assertJsonPath('meta.total', 3);
        $legacy->assertJsonPath('meta.last_page', 1);
        $legacy->assertJsonCount(3, 'data.items');

        // `limit` reste un alias de `per_page` (ancien contrat #7302).
        $limit = $this->getJson('/api/v1/platform/companies/health?limit=2')->assertOk();
        $limit->assertJsonPath('meta.per_page', 2);
        $limit->assertJsonPath('meta.last_page', 2);
        $limit->assertJsonCount(2, 'data.items');

        // `per_page` est plafonné (PORTFOLIO_MAX_PER_PAGE) : pas de « tout le
        // portefeuille en un appel ».
        $clamped = $this->getJson('/api/v1/platform/companies/health?per_page=500')->assertOk();
        $clamped->assertJsonPath('meta.per_page', 100);

        Carbon::setTestNow();
    }

    /**
     * #7302 — portefeuille et fiche société doivent annoncer les MÊMES chiffres.
     *
     * Le portefeuille calcule désormais par requêtes groupées au lieu d'appeler
     * `build()` par société. Ce test verrouille l'équivalence : deux chemins de
     * calcul pour une même donnée sont exactement ce qui avait produit trois
     * progressions d'onboarding divergentes (#7300).
     */
    public function test_portfolio_and_company_detail_agree_on_shared_metrics(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-08 10:00:00', 'UTC'));

        DB::table('plans')->insert([
            'id' => 1,
            'name' => 'Pilot',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'max_employees' => 30,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $this->seedPortfolioCompanies(3);
        Cache::flush();

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        /** @var list<array<string, mixed>> $items */
        $items = $this->getJson('/api/v1/platform/companies/health?limit=10')->assertOk()->json('data.items');
        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $detail = $this->getJson("/api/v1/platform/companies/{$item['company']['id']}/health")
                ->assertOk()
                ->json('data');

            $this->assertSame($detail['adoption']['health_score'], $item['health_score']);
            $this->assertSame($detail['adoption']['risk_level'], $item['risk_level']);
            $this->assertSame($detail['adoption']['employees']['active'], $item['employees_active']);
            $this->assertSame($detail['adoption']['attendance']['logs_30d'], $item['attendance_logs_30d']);
            $this->assertSame($detail['adoption']['anomalies']['critical_30d'], $item['critical_anomalies_30d']);
            $this->assertSame($detail['subscription']['mrr'], $item['subscription']['mrr']);
            $this->assertSame($detail['plan']['name'], $item['plan']['name']);
            $this->assertSame($detail['next_actions'][0] ?? null, $item['next_action']);
        }

        Carbon::setTestNow();
    }

    /**
     * Crée `$count` sociétés avec un employé et un pointage récent.
     */
    private function seedPortfolioCompanies(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $company = Company::factory()->create([
                'plan_id' => 1,
                'timezone' => 'UTC',
            ]);

            app()->instance('current_company', $company);

            // `currentCompany()` est typé `Company` : la variable de fabrique
            // est un `Model` pour larastan, et le baseline PHPStan strict
            // compte les accès `Model::$id` par fichier.
            $current = currentCompany();

            /** @var Employee $employee */
            $employee = Employee::factory()->create([
                'company_id' => $current->id,
                'salary_base' => 173330,
            ]);

            AttendanceLog::factory()->create([
                'company_id' => $current->id,
                'employee_id' => $employee->id,
                'date' => '2026-05-08',
                'check_in' => Carbon::parse('2026-05-08 08:00:00', 'UTC'),
                'check_out' => Carbon::parse('2026-05-08 17:00:00', 'UTC'),
            ]);

            app()->forgetInstance('current_company');
        }
    }

    /**
     * Compte les requêtes émises par UN appel à `portfolio()` (#7302).
     *
     * Mesuré au niveau du service (et non de la route) pour ne pas compter les
     * requêtes d'authentification : ce qui est verrouillé ici est le coût du
     * calcul du portefeuille lui-même.
     *
     * #7339 — on passe par le journal de requêtes de la connexion
     * (`flushQueryLog()` + `enableQueryLog()`) et non par `DB::listen()`. Un
     * écouteur `DB::listen()` n'est jamais retiré : la DEUXIÈME mesure d'un
     * même test voyait chaque requête comptée deux fois, et la borne de coût
     * devenait un artefact de comptage (mesure faussée dans les deux sens).
     */
    private function countPortfolioQueries(int $page, int $perPage): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(PlatformCompanyHealthService::class)->portfolio(page: $page, perPage: $perPage);

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * Identifiants des sociétés d'une page de portefeuille (#7339).
     *
     * @param  TestResponse<JsonResponse>  $response
     * @return list<string>
     */
    private function portfolioCompanyIds(TestResponse $response): array
    {
        /** @var list<array{company: array{id: string}}> $items */
        $items = $response->json('data.items');

        // `$items` est déjà une `list` (annotation PHPDoc) : `array_map` sur une
        // list renvoie une list, `array_values` est donc redondant (PHPStan
        // strict, `arrayValues.list`).
        return array_map(
            static fn (array $item): string => (string) $item['company']['id'],
            $items,
        );
    }

    private function superAdmin(): SuperAdmin
    {
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        return $superAdmin;
    }

    /**
     * #7300 — amorce ET complète la checklist d'onboarding (source de vérité)
     * pour le tenant courant.
     *
     * On passe par `currentCompany()` (retour typé `Company`) plutôt que par la
     * variable de test : `Company::factory()->create()` est typé `Model` pour
     * larastan et le baseline PHPStan strict compte les accès `Model::$id` par
     * fichier — un accès non typé de plus ferait échouer le check requis
     * `PHPStan — Strict` (`ignore.count`). Le contexte tenant est posé par
     * l'appelant.
     */
    private function seedOnboardingSteps(): void
    {
        $company = currentCompany();
        app(SeedDefaultSteps::class)->execute($company->id);
        OnboardingStep::query()
            ->where('company_id', $company->id)
            ->update(['status' => 'completed']);
    }
}
