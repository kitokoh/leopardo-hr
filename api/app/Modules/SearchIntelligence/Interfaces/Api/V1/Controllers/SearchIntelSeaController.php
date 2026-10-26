<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\SearchIntelligence\Domain\Enums\CampaignStatus;
use App\Modules\SearchIntelligence\Domain\Enums\MetricSource;
use App\Modules\SearchIntelligence\Domain\Models\Campaign;
use App\Modules\SearchIntelligence\Domain\Models\CampaignMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * GSI-3 (#8514) — campagnes SEA, relevés de performance, comparaison.
 *
 * Deux principes portent ce contrôleur :
 *
 *  1. **Aucun chiffre inventé.** Un relevé vient d'une saisie manuelle
 *     (`source=manual`) ou d'une intégration (`source=integration`, qui exige
 *     alors `integration_id`). Tant qu'aucune intégration ads n'est connectée,
 *     la réponse porte `tracking_mode: "manual"` — le front affiche « suivi
 *     manuel — non synchronisé » à partir de ce champ, sans le déduire.
 *
 *  2. **Aucune dépense sans validation humaine.** `draft → active` est absent
 *     de la machine à états ; le passage par `awaiting_validation` puis
 *     `POST /validate` est le seul chemin.
 */
final class SearchIntelSeaController
{
    /** Seuil d'alerte de consommation budgétaire. */
    private const BUDGET_ALERT_RATIO = 0.9;

    public function index(Request $request): JsonResponse
    {
        $query = Campaign::query()->orderByDesc('id');

        if (is_string($status = $request->query('status')) && $status !== '') {
            $query->where('status', $status);
        }

        if (is_string($channel = $request->query('channel')) && $channel !== '') {
            $query->where('channel', $channel);
        }

        $campaigns = $query->get();

        return new JsonResponse([
            'data' => $campaigns->map(fn (Campaign $c): array => $this->present($c))->all(),
            'meta' => [
                'tracking_mode' => $this->trackingMode(),
                'count' => $campaigns->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'in:google_ads,ms_ads,meta,other'],
            'objective' => ['nullable', 'in:traffic,leads,signups,sales,awareness'],
            'daily_budget_cents' => ['nullable', 'integer', 'min:0'],
            'total_budget_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'audience' => ['nullable', 'array'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'landing_page' => ['nullable', 'string', 'max:512'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $campaign = new Campaign;
        $campaign->fill($validator->validated());
        // Une campagne naît TOUJOURS en brouillon : `status` n'est pas
        // mass-assignable, et on le pose explicitement pour que l'instance
        // sérialise l'état réel plutôt que `null`.
        $campaign->status = CampaignStatus::Draft;
        $campaign->currency = (string) ($campaign->currency ?: 'EUR');
        $campaign->save();

        return new JsonResponse(['data' => $this->present($campaign)], 201);
    }

    public function show(int $id): JsonResponse
    {
        $campaign = Campaign::query()->find($id);

        if ($campaign === null) {
            return $this->notFound();
        }

        $metrics = $campaign->metrics()->orderBy('measured_on')->get();

        return new JsonResponse([
            'data' => $this->present($campaign),
            'metrics' => $metrics->map(fn (CampaignMetric $m): array => $this->presentMetric($m))->all(),
            'totals' => $this->totals($campaign),
            'meta' => ['tracking_mode' => $this->trackingMode()],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::query()->find($id);

        if ($campaign === null) {
            return $this->notFound();
        }

        // Une campagne passée en validation ou lancée n'est plus un brouillon :
        // la modifier changerait ce qui a été validé. On refuse plutôt que de
        // laisser dériver silencieusement le périmètre d'une dépense engagée.
        if (! in_array($campaign->status, [CampaignStatus::Draft], true)) {
            return new JsonResponse([
                'error' => [
                    'code' => 'campaign_not_editable',
                    'message' => 'Seul un brouillon est éditable. Repassez la campagne en brouillon pour la corriger.',
                    'status' => $campaign->status->value,
                ],
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', 'in:google_ads,ms_ads,meta,other'],
            'objective' => ['nullable', 'in:traffic,leads,signups,sales,awareness'],
            'daily_budget_cents' => ['nullable', 'integer', 'min:0'],
            'total_budget_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'audience' => ['nullable', 'array'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'landing_page' => ['nullable', 'string', 'max:512'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $campaign->fill($validator->validated());
        $campaign->save();

        return new JsonResponse(['data' => $this->present($campaign)]);
    }

    /**
     * Transition de statut. `validate` est un cas particulier : il ne change
     * pas le statut mais pose les preuves de validation.
     */
    public function transition(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::query()->find($id);

        if ($campaign === null) {
            return $this->notFound();
        }

        $target = CampaignStatus::tryFrom((string) $request->input('status', ''));

        if ($target === null) {
            return $this->validationError(['status' => ['Statut cible inconnu.']]);
        }

        $from = $campaign->status;

        if (! $from->canTransitionTo($target)) {
            return new JsonResponse([
                'error' => [
                    'code' => 'invalid_transition',
                    'message' => "Transition {$from->value} → {$target->value} interdite.",
                    'allowed_transitions' => array_map(
                        static fn (CampaignStatus $s): string => $s->value,
                        $from->allowedTransitions(),
                    ),
                ],
            ], 422);
        }

        // Le passage à `active` exige d'avoir été validé — c'est la traduction
        // applicative du CHECK `campaigns_active_requires_validation`.
        if ($target === CampaignStatus::Active && $campaign->validated_at === null) {
            return new JsonResponse([
                'error' => [
                    'code' => 'validation_required',
                    'message' => 'Une campagne doit être validée avant activation.',
                ],
            ], 422);
        }

        $campaign->status = $target;
        $campaign->save();

        $this->audit($campaign, $target->value, ['status' => $from->value]);

        return new JsonResponse(['data' => $this->present($campaign)]);
    }

    /** Validation humaine : c'est elle qui autorise l'activation. */
    public function validateCampaign(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::query()->find($id);

        if ($campaign === null) {
            return $this->notFound();
        }

        if (! $campaign->status->canBeValidated()) {
            return new JsonResponse([
                'error' => [
                    'code' => 'not_awaiting_validation',
                    'message' => 'Seule une campagne en attente de validation peut être validée.',
                    'status' => $campaign->status->value,
                ],
            ], 422);
        }

        $campaign->validated_at = Carbon::now();
        $campaign->validated_by = null; // pas d'utilisateur authentifié côté plateforme
        $campaign->save();

        $this->audit($campaign, 'validated', []);

        return new JsonResponse(['data' => $this->present($campaign)]);
    }

    /**
     * Relevés : upsert par (campagne, date).
     *
     * Un second relevé sur la même date est une CORRECTION explicite, pas une
     * ligne concurrente — l'API met à jour au lieu de créer un doublon, ce que
     * la contrainte d'unicité imposerait de toute façon.
     */
    public function storeMetric(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::query()->find($id);

        if ($campaign === null) {
            return $this->notFound();
        }

        $validator = Validator::make($request->all(), [
            'measured_on' => ['required', 'date'],
            'source' => ['nullable', 'in:manual,integration'],
            'integration_id' => ['nullable', 'integer', 'exists:integrations,id'],
            'spend_cents' => ['required', 'integer', 'min:0'],
            'impressions' => ['required', 'integer', 'min:0'],
            'clicks' => ['required', 'integer', 'min:0'],
            'conversions' => ['nullable', 'integer', 'min:0'],
            'revenue_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $source = MetricSource::tryFrom((string) ($data['source'] ?? 'manual')) ?? MetricSource::Manual;

        // Un relevé « integration » sans intégration rattachée serait un
        // chiffre sans provenance. Refusé ici, et par un CHECK en base.
        if ($source === MetricSource::Integration && empty($data['integration_id'])) {
            return new JsonResponse([
                'error' => [
                    'code' => 'integration_required',
                    'message' => 'Un relevé de source « integration » doit référencer une intégration.',
                ],
            ], 422);
        }

        if ($source === MetricSource::Manual) {
            $data['integration_id'] = null;
        }

        $metric = CampaignMetric::query()->updateOrCreate(
            [
                'campaign_id' => $campaign->id,
                'measured_on' => Carbon::parse($data['measured_on'])->toDateString(),
            ],
            [
                'source' => $source,
                'integration_id' => $data['integration_id'] ?? null,
                'spend_cents' => $data['spend_cents'],
                'impressions' => $data['impressions'],
                'clicks' => $data['clicks'],
                'conversions' => $data['conversions'] ?? 0,
                'revenue_cents' => $data['revenue_cents'] ?? 0,
            ],
        );

        $this->audit($campaign, 'metric_recorded', ['measured_on' => $metric->measured_on?->toDateString()]);

        return new JsonResponse([
            'data' => $this->presentMetric($metric),
            'totals' => $this->totals($campaign),
            'budget_alert' => $this->budgetAlert($campaign),
        ], 201);
    }

    /** Comparaison inter-campagnes, sur la même base de calcul. */
    public function compare(Request $request): JsonResponse
    {
        $query = Campaign::query();

        if (is_string($ids = $request->query('ids')) && $ids !== '') {
            $query->whereIn('id', array_map('intval', explode(',', $ids)));
        }

        $campaigns = $query->orderByDesc('id')->get();

        $rows = $campaigns->map(function (Campaign $campaign): array {
            $totals = $this->totals($campaign);

            return [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'channel' => $campaign->channel,
                'status' => $campaign->status->value,
                'objective' => $campaign->objective,
                'spent_cents' => $totals['spend_cents'],
                'clicks' => $totals['clicks'],
                'impressions' => $totals['impressions'],
                'conversions' => $totals['conversions'],
                'cost_per_conversion_cents' => $totals['cost_per_conversion_cents'],
                'click_through_rate' => $totals['click_through_rate'],
                'budget_consumption_ratio' => $campaign->budgetConsumptionRatio(),
            ];
        })->all();

        return new JsonResponse([
            'data' => $rows,
            'meta' => [
                'tracking_mode' => $this->trackingMode(),
                // Le classement n'est proposé que s'il y a de quoi comparer
                // honnêtement : au moins deux campagnes avec des conversions.
                'rankable' => count(array_filter(
                    $rows,
                    static fn (array $r): bool => $r['conversions'] > 0,
                )) >= 2,
            ],
        ]);
    }

    /**
     * @return array<string, int|float|null>
     */
    private function totals(Campaign $campaign): array
    {
        $metrics = $campaign->metrics()->get();

        $spend = (int) $metrics->sum('spend_cents');
        $clicks = (int) $metrics->sum('clicks');
        $impressions = (int) $metrics->sum('impressions');
        $conversions = (int) $metrics->sum('conversions');

        return [
            'spend_cents' => $spend,
            'clicks' => $clicks,
            'impressions' => $impressions,
            'conversions' => $conversions,
            // Pas de division par zéro : on renvoie `null` (« pas encore de
            // conversion ») plutôt qu'un CPO infini ou un 0 trompeur.
            'cost_per_conversion_cents' => $conversions > 0 ? (int) round($spend / $conversions) : null,
            'click_through_rate' => $impressions > 0 ? $clicks / $impressions : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function budgetAlert(Campaign $campaign): ?array
    {
        $ratio = $campaign->budgetConsumptionRatio();

        if ($ratio === null || $ratio < self::BUDGET_ALERT_RATIO) {
            return null;
        }

        $this->audit($campaign, 'budget_exceeded', [
            'consumption_ratio' => round($ratio, 4),
            'spent_cents' => $campaign->spentCents(),
        ]);

        return [
            'code' => 'budget_alert',
            'consumption_ratio' => round($ratio, 4),
            'threshold' => self::BUDGET_ALERT_RATIO,
            'spent_cents' => $campaign->spentCents(),
            'budget_cents' => $campaign->total_budget_cents ?? $campaign->daily_budget_cents,
        ];
    }

    /**
     * Mode de suivi réel : `integration` seulement si au moins une intégration
     * ads est connectée. Sinon `manual` — c'est ce que le front affiche en
     * bandeau, sans avoir à deviner.
     */
    private function trackingMode(): string
    {
        $connected = \App\Modules\SearchIntelligence\Domain\Models\Integration::query()
            ->whereIn('provider', ['google_ads', 'ms_ads'])
            ->where('status', 'connected')
            ->exists();

        return $connected ? 'integration' : 'manual';
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function audit(Campaign $campaign, string $action, array $extra): void
    {
        AuditLog::query()->create([
            'company_id' => null,
            'user_id' => null,
            'action' => 'search_intel.campaign.'.$action,
            'module' => 'search_intel',
            'auditable_type' => Campaign::class,
            'auditable_id' => $campaign->id,
            'old_values' => null,
            'new_values' => $extra === [] ? null : $extra,
            'metadata' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'channel' => $campaign->channel,
            'objective' => $campaign->objective,
            'status' => $campaign->status->value,
            'daily_budget_cents' => $campaign->daily_budget_cents,
            'total_budget_cents' => $campaign->total_budget_cents,
            'currency' => $campaign->currency,
            'audience' => $campaign->audience,
            'utm_campaign' => $campaign->utm_campaign,
            'landing_page' => $campaign->landing_page,
            'starts_on' => $campaign->starts_on?->toDateString(),
            'ends_on' => $campaign->ends_on?->toDateString(),
            'validated_at' => $campaign->validated_at?->toIso8601String(),
            'is_spending' => $campaign->status->isSpending(),
            'spent_cents' => $campaign->spentCents(),
            'budget_consumption_ratio' => $campaign->budgetConsumptionRatio(),
            'allowed_transitions' => array_map(
                static fn (CampaignStatus $s): string => $s->value,
                $campaign->status->allowedTransitions(),
            ),
            'tracking_mode' => $this->trackingMode(),
            'measured_days' => $campaign->measuredDays(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMetric(CampaignMetric $metric): array
    {
        return [
            'id' => $metric->id,
            'campaign_id' => $metric->campaign_id,
            'measured_on' => $metric->measured_on?->toDateString(),
            'source' => $metric->source->value,
            'integration_id' => $metric->integration_id,
            'spend_cents' => $metric->spend_cents,
            'impressions' => $metric->impressions,
            'clicks' => $metric->clicks,
            'conversions' => $metric->conversions,
            'revenue_cents' => $metric->revenue_cents,
            'cost_per_conversion_cents' => $metric->costPerConversionCents(),
            'click_through_rate' => $metric->clickThroughRate(),
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function validationError(array $errors): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => 'validation_failed',
                'message' => 'Données invalides.',
                'fields' => $errors,
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse([
            'error' => ['code' => 'not_found', 'message' => 'Campagne introuvable.'],
        ], 404);
    }
}
