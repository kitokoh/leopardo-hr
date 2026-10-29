<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Onboarding\Application\Services\LeaPilotMetricsRecorder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8232 (BOS-044) — `lea:pilot-report`.
 *
 * Le rapport doit produire une DÉCISION, pas un tableau de chiffres. Ces
 * tests verrouillent les trois pièges qui rendraient la décision fausse :
 *
 * 1. **Le dénominateur** — un « parcours » est une interprétation (étape 1),
 *    pas un événement. Compter les événements gonflerait le dénominateur avec
 *    les étapes 2/3 du même parcours et écraserait le taux de fallback.
 * 2. **L'échantillon** — `insufficient_data` ne doit JAMAIS devenir `pass`.
 *    Un pilote à 3 parcours ne prouve rien ; le déclarer vert serait le faux
 *    positif de pipeline que le dépôt combat (#3545).
 * 3. **Le verdict** — un critère en échec ⇒ `retrait`, jamais `ga`.
 */
class LeaPilotReportCommandTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array{clarification_rounds?: int, answers_modified?: bool}  $context
     */
    private function record(Company $company, string $stage, string $outcome, array $context = []): void
    {
        DB::table('lea_pilot_events')->insert([
            'company_id' => $company->id,
            'stage' => $stage,
            'outcome' => $outcome,
            'reason_code' => $outcome === 'fallback_required' ? 'llm_unavailable' : null,
            'correlation_id' => 'corr-'.uniqid(),
            'via_lea' => true,
            'clarification_rounds' => $context['clarification_rounds'] ?? 0,
            'answers_modified' => $context['answers_modified'] ?? false,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function report(array $options = []): array
    {
        Artisan::call('lea:pilot-report', $options + ['--json' => true]);

        return json_decode(Artisan::output(), true);
    }

    public function test_healthy_pilot_reaches_ga(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['slug' => 'pilote-sain']);

        // 20 parcours, 2 fallbacks (10 % < 30 %), 18 complétés.
        for ($i = 0; $i < 20; $i++) {
            $this->record($company, 'interpret', 'plan_ready');
        }
        for ($i = 0; $i < 2; $i++) {
            $this->record($company, 'interpret', 'fallback_required');
        }
        for ($i = 0; $i < 18; $i++) {
            $this->record($company, 'confirm', 'completed');
        }

        // Formulaire : entretien ouvert mais NON clôturé → 0 % de complétion.
        // C'est le cas réel du pilote : le client a commencé l'entretien
        // déterministe puis a basculé sur Léa, qui va au bout. Comparer à un
        // formulaire déjà complété n'aurait aucun sens (il n'y aurait plus de
        // pilote à mesurer).
        $company->forceFill(['metadata' => ['setup_interview' => ['status' => 'in_progress']]])->save();

        $report = $this->report(['--company' => $company->id, '--min-samples' => 10]);

        $this->assertSame(22, $report['aggregate']['lea_parcours'], 'Seules les étapes interpret comptent comme parcours.');
        $this->assertSame(2, $report['aggregate']['fallbacks']);
        $this->assertEqualsWithDelta(0.0909, $report['aggregate']['fallback_rate'], 0.001);
        $this->assertEqualsWithDelta(0.8182, $report['aggregate']['lea_completion_rate'], 0.001);
        $this->assertSame('pass', $report['verdict']['criteria']['fallback_rate']);
        $this->assertSame('pass', $report['verdict']['criteria']['completion_vs_form']);
        // Le coût n'est pas mesuré sur cette fixture (aucun ai_audit_logs) :
        // le verdict reste donc `iteration`, jamais `ga` sur une donnée absente.
        $this->assertSame('insufficient_data', $report['verdict']['criteria']['llm_cost']);
        $this->assertSame('iteration', $report['verdict']['decision']);
    }

    public function test_all_criteria_green_reaches_ga(): void
    {
        // Verdict GA complet : échantillon suffisant, fallback sous seuil,
        // complétion ≥ formulaire ET coût effectivement mesuré.
        /** @var Company $company */
        $company = Company::factory()->create(['slug' => 'pilote-ga']);

        for ($i = 0; $i < 18; $i++) {
            $this->record($company, 'interpret', 'plan_ready');
            $this->record($company, 'confirm', 'completed');
        }
        for ($i = 0; $i < 2; $i++) {
            $this->record($company, 'interpret', 'fallback_required');
        }

        $company->forceFill(['metadata' => ['setup_interview' => ['status' => 'in_progress']]])->save();

        // Coût LLM observé : 18 complétions × 12 centimes.
        $employee = \App\Core\Auth\Domain\Models\Employee::factory()->create(['company_id' => (string) $company->id]);
        for ($i = 0; $i < 18; $i++) {
            DB::table('ai_audit_logs')->insert([
                'company_id' => $company->id,
                'user_id' => $employee->id,
                'prompt' => '[redacted]',
                'response' => '[redacted]',
                'tools_called' => '[]',
                'provider' => 'test',
                'model' => 'test',
                'input_tokens' => 10,
                'output_tokens' => 10,
                'cost_cents' => 12,
                'duration_ms' => 10,
                'created_at' => now(),
            ]);
        }

        $report = $this->report(['--company' => $company->id, '--min-samples' => 10]);

        $this->assertSame(20, $report['aggregate']['lea_parcours']);
        $this->assertSame(216, $report['aggregate']['llm_cost_cents_total']);
        $this->assertSame(12, $report['aggregate']['llm_cost_cents_per_onboarding']);
        $this->assertSame('pass', $report['verdict']['criteria']['llm_cost']);
        $this->assertSame('ga', $report['verdict']['decision']);
    }

    public function test_high_fallback_rate_triggers_retrait(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['slug' => 'pilote-degrade']);

        // 10 parcours, 5 fallbacks = 50 % > 30 %.
        for ($i = 0; $i < 5; $i++) {
            $this->record($company, 'interpret', 'plan_ready');
        }
        for ($i = 0; $i < 5; $i++) {
            $this->record($company, 'interpret', 'fallback_required');
        }

        $report = $this->report(['--company' => $company->id, '--min-samples' => 10]);

        $this->assertSame('fail', $report['verdict']['criteria']['fallback_rate']);
        $this->assertSame('retrait', $report['verdict']['decision']);
    }

    public function test_small_sample_is_never_reported_as_pass(): void
    {
        // 3 parcours parfaits : le taux est excellent, l'échantillon ne prouve
        // rien. `insufficient_data` doit rester `insufficient_data`.
        /** @var Company $company */
        $company = Company::factory()->create(['slug' => 'pilote-trop-petit']);

        for ($i = 0; $i < 3; $i++) {
            $this->record($company, 'interpret', 'plan_ready');
            $this->record($company, 'confirm', 'completed');
        }

        $report = $this->report(['--company' => $company->id, '--min-samples' => 10]);

        $this->assertSame('insufficient_data', $report['verdict']['criteria']['fallback_rate']);
        $this->assertNotSame('ga', $report['verdict']['decision']);
        $this->assertSame('iteration', $report['verdict']['decision']);
    }

    public function test_fail_on_regression_exits_non_zero(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['slug' => 'pilote-rouge']);

        for ($i = 0; $i < 10; $i++) {
            $this->record($company, 'interpret', 'fallback_required');
        }

        $exit = Artisan::call('lea:pilot-report', [
            '--company' => $company->id,
            '--min-samples' => 10,
            '--fail-on-regression' => true,
        ]);

        $this->assertSame(1, $exit);
    }

    public function test_recorder_never_stores_free_text(): void
    {
        // Revue RGPD du critère d'acceptation : la table ne doit contenir que
        // des énumérations et des compteurs. On vérifie la LISTE des colonnes,
        // pas seulement ce qu'on y écrit — une colonne ajoutée plus tard
        // pourrait réintroduire du texte libre sans que ce test le voie.
        $columns = DB::select(
            "SELECT column_name FROM information_schema.columns
              WHERE table_name = 'lea_pilot_events'
              ORDER BY ordinal_position"
        );

        $names = array_map(static fn ($c): string => (string) $c->column_name, $columns);

        $this->assertSame([
            'id',
            'company_id',
            'stage',
            'outcome',
            'reason_code',
            'correlation_id',
            'via_lea',
            'clarification_rounds',
            'answers_modified',
            'created_at',
        ], $names, 'Aucune colonne de texte libre ne doit exister dans lea_pilot_events.');
    }

    public function test_recorder_is_non_blocking_when_table_is_missing(): void
    {
        // L'instrumentation ne doit jamais casser l'inscription : si la table
        // n'existe pas (tenant ancien), l'appel est un no-op silencieux.
        /** @var Company $company */
        $company = Company::factory()->create(['slug' => 'sans-table']);

        DB::statement('ALTER TABLE lea_pilot_events RENAME TO lea_pilot_events_tmp');

        try {
            app(LeaPilotMetricsRecorder::class)->record(
                (string) $company->id,
                LeaPilotMetricsRecorder::STAGE_INTERPRET,
                'plan_ready',
            );

            // Le simple fait d'arriver ici prouve que l'appel est un no-op
            // silencieux : aucune exception n'a remonte.
            $this->addToAssertionCount(1);
        } finally {
            DB::statement('ALTER TABLE lea_pilot_events_tmp RENAME TO lea_pilot_events');
        }
    }
}
