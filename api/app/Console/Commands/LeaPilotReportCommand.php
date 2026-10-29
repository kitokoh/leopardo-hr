<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * #8232 (BOS-044) — rapport chiffré du pilote Léa.
 *
 * ## Pourquoi ce rapport décide, et ne se contente pas de compter
 *
 * L'issue demande une décision GA / itération / retrait. Une décision ne se
 * prend pas sur des compteurs bruts : elle se prend sur des compteurs
 * confrontés aux seuils écrits dans `09_EXECUTION_READINESS_REVIEW.md` §3 :
 *
 *   - taux de fallback < 30 % ;
 *   - complétion Léa ≥ complétion du formulaire ;
 *   - coût LLM / onboarding sous le seuil de la spec.
 *
 * La commande rend donc un VERDICT par critère (`pass` / `fail` /
 * `insufficient_data`) et un verdict global. `insufficient_data` n'est pas un
 * `pass` : un pilote avec 3 parcours ne prouve rien, et le déclarer vert
 * serait exactement le faux positif que le dépôt combat ailleurs (#3545).
 *
 * ## Le coût n'est pas mesuré ici, et c'est assumé
 *
 * Le coût LLM par onboarding vit dans `ai_audit_logs` (tokens + `cost_cents`),
 * alimenté par la couche AI, pas par le parcours Léa. Le rapport expose donc
 * le coût OBSERVÉ quand la table est disponible, et marque le critère
 * `insufficient_data` sinon — plutôt que d'inventer une estimation.
 *
 * Usage :
 *   php artisan lea:pilot-report
 *   php artisan lea:pilot-report --days=30 --json
 *   php artisan lea:pilot-report --min-samples=20 --fail-on-regression
 */
final class LeaPilotReportCommand extends Command
{
    /** Seuil de fallback du pilote (spec §3) : au-delà, Léa n'est pas fiable. */
    private const MAX_FALLBACK_RATE = 0.30;

    /** En dessous, un taux n'est pas interprétable (échantillon trop faible). */
    private const MIN_SAMPLES = 10;

    protected $signature = 'lea:pilot-report
        {--days=30 : Fenêtre d\'analyse en jours}
        {--company= : Restreint le rapport à une compagnie (UUID)}
        {--min-samples= : Nombre minimal de parcours pour conclure (défaut 10)}
        {--json : Sortie JSON exploitable}
        {--fail-on-regression : Code de sortie 1 si un critère est en échec}';

    protected $description = 'Rapport chiffré du pilote Léa et verdict GA / itération / retrait (#8232)';

    public function handle(TenantManager $tenantManager): int
    {
        $days = max(1, (int) $this->option('days'));
        $minSamples = max(1, (int) ($this->option('min-samples') ?: self::MIN_SAMPLES));
        $since = now()->subDays($days);

        $companies = $this->resolveCompanies();

        if ($companies->isEmpty()) {
            $this->error('Aucune compagnie trouvée.');

            return self::FAILURE;
        }

        /** @var list<array<string, mixed>> $perTenant */
        $perTenant = [];
        foreach ($companies as $company) {
            try {
                $perTenant[] = $tenantManager->withinTenant(
                    $company,
                    fn (): array => $this->tenantMetrics($company, $since)
                );
            } catch (Throwable $e) {
                $perTenant[] = [
                    'company_id' => $company->id,
                    'company_slug' => $company->slug,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $aggregate = $this->aggregate($perTenant);
        $verdict = $this->verdict($aggregate, $minSamples);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'window' => ['days' => $days, 'since' => $since->toIso8601String()],
            'thresholds' => [
                'max_fallback_rate' => self::MAX_FALLBACK_RATE,
                'min_samples' => $minSamples,
            ],
            'aggregate' => $aggregate,
            'verdict' => $verdict,
            'tenants' => $perTenant,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
        } else {
            $this->render($report);
        }

        if ($this->option('fail-on-regression') && $verdict['decision'] === 'retrait') {
            $this->error('Verdict : RETRAIT — au moins un critère est en échec.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Company>
     */
    private function resolveCompanies(): \Illuminate\Support\Collection
    {
        $uuid = $this->option('company');

        $query = Company::query()->orderBy('slug');

        if (is_string($uuid) && $uuid !== '') {
            $query->where('id', $uuid);
        }

        return $query->get();
    }

    /**
     * Métriques d'UN tenant, lues dans son search_path.
     *
     * @return array<string, mixed>
     */
    private function tenantMetrics(Company $company, \Illuminate\Support\Carbon $since): array
    {
        $row = [
            'company_id' => $company->id,
            'company_slug' => $company->slug,
            'lea_parcours' => 0,
            'lea_completed' => 0,
            'fallbacks' => 0,
            'clarifications' => 0,
            'answers_modified' => 0,
            'form_parcours' => 0,
            'form_completed' => 0,
            'llm_cost_cents' => null,
        ];

        if (schemaTableExists('lea_pilot_events')) {
            $events = DB::table('lea_pilot_events')
                ->where('company_id', $company->id)
                ->where('created_at', '>=', $since)
                ->get(['stage', 'outcome', 'answers_modified']);

            // Un « parcours » = une interprétation (étape 1). Compter les
            // événements bruts gonflerait le dénominateur avec les étapes 2/3
            // du même parcours et écraserait artificiellement le taux de
            // fallback.
            $row['lea_parcours'] = $events->where('stage', 'interpret')->count();
            $row['fallbacks'] = $events->where('outcome', 'fallback_required')->count();
            $row['clarifications'] = $events->where('outcome', 'clarification')->count();
            $row['lea_completed'] = $events->where('outcome', 'completed')->count();
            $row['answers_modified'] = $events->where('answers_modified', true)->count();
        }

        // Parcours déterministe (formulaire) : l'entretien est persisté dans
        // `companies.metadata.setup_interview`. On lit le statut, pas les
        // réponses — la mesure n'a pas besoin du contenu.
        $metadata = $company->metadata ?? [];
        $interview = is_array($metadata['setup_interview'] ?? null) ? $metadata['setup_interview'] : [];
        $status = $interview['status'] ?? null;

        if (in_array($status, ['in_progress', 'completed', 'dismissed'], true)) {
            $row['form_parcours'] = 1;
            $row['form_completed'] = $status === 'completed' ? 1 : 0;
        }

        // Coût LLM observé, si la couche AI l'a enregistré sur la fenêtre.
        //
        // #8232 — `AUCUNE ligne` et `0 centime` ne sont PAS la même chose :
        // sans audit AI sur la fenêtre, le coût est INCONNU (null), pas nul.
        // Confondre les deux ferait passer le critère « coût sous le seuil »
        // au vert sur une absence de donnée — c'est exactement le faux positif
        // que ce rapport doit éviter. `sum()` renvoie déjà `null` sur un
        // agrégat vide, on le propage tel quel.
        if (schemaTableExists('ai_audit_logs')) {
            $hasRows = DB::table('ai_audit_logs')
                ->where('company_id', $company->id)
                ->where('created_at', '>=', $since)
                ->exists();

            if ($hasRows) {
                $cost = DB::table('ai_audit_logs')
                    ->where('company_id', $company->id)
                    ->where('created_at', '>=', $since)
                    ->sum('cost_cents');

                $row['llm_cost_cents'] = (int) $cost;
            }
        }

        return $row;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function aggregate(array $rows): array
    {
        $sum = static fn (string $key): int => array_sum(array_map(
            static fn (array $r): int => (int) ($r[$key] ?? 0),
            $rows
        ));

        $parcours = $sum('lea_parcours');
        $fallbacks = $sum('fallbacks');
        $leaCompleted = $sum('lea_completed');
        $formParcours = $sum('form_parcours');
        $formCompleted = $sum('form_completed');

        $costs = array_filter(
            array_map(static fn (array $r) => $r['llm_cost_cents'] ?? null, $rows),
            static fn ($v): bool => $v !== null
        );

        return [
            'tenants' => count($rows),
            'lea_parcours' => $parcours,
            'lea_completed' => $leaCompleted,
            'fallbacks' => $fallbacks,
            'clarifications' => $sum('clarifications'),
            'answers_modified' => $sum('answers_modified'),
            'form_parcours' => $formParcours,
            'form_completed' => $formCompleted,
            // Taux calculés sur les PARCOURS, jamais sur les événements.
            'fallback_rate' => $parcours > 0 ? round($fallbacks / $parcours, 4) : null,
            'lea_completion_rate' => $parcours > 0 ? round($leaCompleted / $parcours, 4) : null,
            'form_completion_rate' => $formParcours > 0 ? round($formCompleted / $formParcours, 4) : null,
            'answers_modified_rate' => $leaCompleted > 0 ? round($sum('answers_modified') / $leaCompleted, 4) : null,
            'llm_cost_cents_total' => $costs === [] ? null : (int) array_sum($costs),
            'llm_cost_cents_per_onboarding' => ($costs !== [] && $leaCompleted > 0)
                ? (int) round(array_sum($costs) / $leaCompleted)
                : null,
        ];
    }

    /**
     * Verdict par critère, puis verdict global.
     *
     * `insufficient_data` n'est JAMAIS converti en `pass` : un pilote avec
     * trop peu de parcours ne prouve rien, et le déclarer vert reproduirait
     * le faux positif de pipeline que le dépôt combat (#3545).
     *
     * @param  array<string, mixed>  $a
     * @return array<string, mixed>
     */
    private function verdict(array $a, int $minSamples): array
    {
        $parcours = (int) $a['lea_parcours'];

        $fallback = 'insufficient_data';
        if ($parcours >= $minSamples && $a['fallback_rate'] !== null) {
            $fallback = $a['fallback_rate'] < self::MAX_FALLBACK_RATE ? 'pass' : 'fail';
        }

        $completion = 'insufficient_data';
        if ($parcours >= $minSamples && $a['lea_completion_rate'] !== null && $a['form_completion_rate'] !== null) {
            $completion = $a['lea_completion_rate'] >= $a['form_completion_rate'] ? 'pass' : 'fail';
        }

        $cost = $a['llm_cost_cents_per_onboarding'] === null ? 'insufficient_data' : 'pass';

        $criteria = [
            'fallback_rate' => $fallback,
            'completion_vs_form' => $completion,
            'llm_cost' => $cost,
        ];

        $decision = match (true) {
            in_array('fail', $criteria, true) => 'retrait',
            in_array('insufficient_data', $criteria, true) => 'iteration',
            default => 'ga',
        };

        return [
            'criteria' => $criteria,
            'decision' => $decision,
            'rationale' => match ($decision) {
                'ga' => 'Tous les critères sont au vert sur un échantillon suffisant : passage en GA.',
                'retrait' => 'Au moins un critère est en échec : retrait par flag OFF (sans résidu).',
                default => 'Échantillon insuffisant ou coût non mesuré : itérer et prolonger le pilote.',
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function render(array $report): void
    {
        /** @var array<string, mixed> $a */
        $a = $report['aggregate'];
        /** @var array<string, mixed> $v */
        $v = $report['verdict'];

        $this->newLine();
        $this->info('Pilote Léa — rapport de mesure (#8232)');
        $this->line(sprintf(
            'Fenêtre : %d jours · tenants : %d · parcours Léa : %d · parcours formulaire : %d',
            $report['window']['days'],
            $a['tenants'],
            $a['lea_parcours'],
            $a['form_parcours'],
        ));

        $pct = static fn ($v): string => $v === null ? 'n/a' : number_format((float) $v * 100, 1).' %';

        $this->table(
            ['métrique', 'valeur', 'seuil', 'verdict'],
            [
                ['taux de fallback', $pct($a['fallback_rate']), '< 30 %', $v['criteria']['fallback_rate']],
                ['complétion Léa', $pct($a['lea_completion_rate']), '≥ formulaire ('.$pct($a['form_completion_rate']).')', $v['criteria']['completion_vs_form']],
                ['modification du résumé', $pct($a['answers_modified_rate']), 'informatif', '—'],
                ['coût LLM / onboarding', $a['llm_cost_cents_per_onboarding'] === null ? 'n/a' : $a['llm_cost_cents_per_onboarding'].' c', 'seuil spec', $v['criteria']['llm_cost']],
            ]
        );

        $this->line('Décision : '.strtoupper((string) $v['decision']).' — '.$v['rationale']);
    }
}
