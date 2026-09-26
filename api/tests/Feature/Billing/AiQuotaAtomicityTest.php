<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Middleware\AI\AIRateLimiter;
use App\Modules\Billing\Domain\Models\AiCreditLedger;
use App\Modules\Billing\Domain\Models\Subscription;
use App\Modules\Billing\Infrastructure\Services\AiCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-008 (#8143) — la décision de quota IA est ATOMIQUE.
 *
 * Défaut d'origine : `AIRateLimiter` lisait l'usage courant, le comparait à la
 * limite, PUIS incrémentait (« check-then-increment ») — deux requêtes
 * concurrentes sous le quota passaient toutes les deux. Le test reproduit
 * l'interleaving de façon DÉTERMINISTE (aucune dépendance à un ordonnanceur) :
 * toute lecture d'usage renvoie la valeur PÉRIMÉE 0, comme le ferait un lecteur
 * concurrent qui n'a pas encore vu les incréments des autres requêtes. Seule
 * une décision prise sur la valeur atomique post-incrément peut tenir le quota.
 */
class AiQuotaAtomicityTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // L'interleaving simulé remplace le service par un lecteur périmé :
        // les crédits achetés ne doivent donc PAS couvrir le dépassement.
        config(['ai.quotas.pilot' => 3]);
    }

    public function test_quota_holds_even_when_usage_reads_are_stale(): void
    {
        [$company, $manager] = $this->tenantWithPlan('pilot');
        $companyId = strval($company->id);
        $period = now()->format('Y-m');

        $this->app->instance(AiCreditService::class, new StaleReadAiCreditService);

        $passed = 0;
        $refused = 0;
        $refusalBody = null;

        foreach (range(1, 20) as $attempt) {
            $response = $this->rateLimit($manager);
            if ($response->getStatusCode() === 200) {
                $passed++;

                continue;
            }

            $refused++;
            $refusalBody ??= (array) json_decode(strval($response->getContent()), true);
            $this->assertSame(422, $response->getStatusCode(), 'les requêtes hors quota reçoivent le refus existant');
        }

        $this->assertSame(3, $passed, 'exactement la limite du plan passe, même sous lecture périmée');
        $this->assertSame(17, $refused, 'toutes les requêtes au-delà de la limite sont refusées');
        $this->assertSame('AI_CREDITS_EXHAUSTED', $refusalBody['error'] ?? null);
        $this->assertSame(3, $refusalBody['quota'] ?? null);
        $this->assertSame(0, AiCreditLedger::query()->count(), 'aucun débit de crédits (solde nul)');

        // Compteur final EXACT : ni sur-incrément, ni dépassement.
        $this->assertDatabaseHas('ai_usage_counters', [
            'company_id' => $companyId,
            'period' => $period,
            'used' => 3,
        ]);
    }

    public function test_refused_requests_do_not_increment_the_counter(): void
    {
        [$company, $manager] = $this->tenantWithPlan('pilot');
        $companyId = strval($company->id);
        $period = now()->format('Y-m');

        $this->assertSame(200, $this->rateLimit($manager)->getStatusCode());
        $this->assertSame(200, $this->rateLimit($manager)->getStatusCode());
        $this->assertSame(200, $this->rateLimit($manager)->getStatusCode());

        // Quota épuisé, aucun crédit acheté → refus fail-closed.
        $this->assertSame(422, $this->rateLimit($manager)->getStatusCode());
        $this->assertSame(422, $this->rateLimit($manager)->getStatusCode());

        $this->assertDatabaseHas('ai_usage_counters', [
            'company_id' => $companyId,
            'period' => $period,
            'used' => 3,
        ]);
        $this->assertSame(3, app(AiCreditService::class)->monthlyUsage($companyId, $period));
        $this->assertSame(0, AiCreditLedger::query()->count(), 'un refus ne débite jamais de crédits');
    }

    public function test_atomic_consume_never_exceeds_the_limit_at_storage_level(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $companyId = strval($company->id);
        $period = now()->format('Y-m');
        $service = app(AiCreditService::class);

        $granted = 0;
        $denied = 0;

        foreach (range(1, 8) as $attempt) {
            if ($service->incrementMonthlyUsageIfUnderLimit($companyId, $period, 3) !== null) {
                $granted++;

                continue;
            }

            $denied++;
        }

        $this->assertSame(3, $granted);
        $this->assertSame(5, $denied);
        $this->assertSame(3, $service->monthlyUsage($companyId, $period));
    }

    public function test_unlimited_plan_still_passes_and_counts(): void
    {
        config(['ai.quotas.pilot' => null]);
        [$company, $manager] = $this->tenantWithPlan('pilot');
        $companyId = strval($company->id);

        foreach (range(1, 5) as $attempt) {
            $this->assertSame(200, $this->rateLimit($manager)->getStatusCode());
        }

        $this->assertSame(5, app(AiCreditService::class)->monthlyUsage($companyId, now()->format('Y-m')));
        $this->assertSame(0, AiCreditLedger::query()->count(), 'aucun débit sur un plan illimité');
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function tenantWithPlan(string $plan): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        Subscription::create([
            'company_id' => $company->id,
            'plan' => $plan,
            'status' => 'active',
            'payment_method' => 'stripe',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return [$company, $manager];
    }

    private function rateLimit(Employee $employee): Response
    {
        $request = Request::create('/api/v1/ai/chat', 'POST');
        $request->setUserResolver(static fn (): Employee => $employee);

        /** @var AIRateLimiter $middleware */
        $middleware = app(AIRateLimiter::class);

        return $middleware->handle($request, static fn (): JsonResponse => new JsonResponse(['ok' => true]));
    }
}

/**
 * Lecteur PÉRIMÉ : « l'autre requête concurrente n'a pas encore vu mes
 * incréments ». L'ancien check-then-increment décidait sur cette valeur et
 * laissait donc tout passer ; la décision atomique ne la consulte plus.
 */
final class StaleReadAiCreditService extends AiCreditService
{
    public function monthlyUsage(string $companyId, string $period): int
    {
        return 0;
    }
}
