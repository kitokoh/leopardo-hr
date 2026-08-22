<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Services;

use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class JobRecommendationService
{
    public function __construct(private readonly TenantManager $tenantManager) {}

    /** @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>} */
    public function recommend(User $user, ?string $query = null, int $limit = 20): array
    {
        $preferences = is_array($user->job_search_preferences) ? $user->job_search_preferences : [];
        $companies = Company::query()->where('status', 'active')->get();
        $jobs = [];

        foreach ($companies as $company) {
            $tenantJobs = $this->tenantManager->withinTenant($company, function () use ($company, $query): array {
                return JobPosting::query()
                    ->where('company_id', $company->id)
                    ->published()
                    ->when($query, fn ($builder) => $builder->where(function ($q) use ($query): void {
                        $q->where('title', 'ilike', "%{$query}%")
                            ->orWhere('description', 'ilike', "%{$query}%")
                            ->orWhere('location', 'ilike', "%{$query}%");
                    }))
                    ->where(function ($q): void {
                        $q->whereNull('closes_at')->orWhere('closes_at', '>=', now());
                    })
                    ->with('department:id,name')
                    ->orderByDesc('published_at')
                    ->limit(100)
                    ->get()
                    ->map(fn (JobPosting $job): array => $this->serializeCandidate($job, $company))
                    ->all();
            });
            $jobs = [...$jobs, ...$tenantJobs];
        }

        foreach ($jobs as &$job) {
            [$score, $reasons] = $this->score($job, $preferences);
            $job['match_score'] = $score;
            $job['match_reasons'] = $reasons;
        }
        unset($job);

        usort($jobs, fn (array $a, array $b): int => $b['match_score'] <=> $a['match_score']);
        $jobs = array_slice($jobs, 0, max(1, min(50, $limit)));
        $ai = $this->aiRerank($user, $preferences, $jobs);

        return [
            'data' => $ai['data'],
            'meta' => [
                'source' => $ai['source'],
                'model' => $ai['model'],
                'generated_at' => now()->toIso8601String(),
                'profile_updated_at' => $user->job_search_profile_updated_at?->toIso8601String(),
            ],
        ];
    }

    /** @return array{0: int, 1: array<int, string>} */
    private function score(array $job, array $preferences): array
    {
        $score = 0;
        $reasons = [];
        $skills = $this->normalizeList($preferences['skills'] ?? []);
        $requiredSkills = $this->normalizeList($job['skills_required'] ?? []);
        $skillMatches = array_values(array_intersect($skills, $requiredSkills));
        if ($requiredSkills !== []) {
            $skillScore = (int) round(45 * count($skillMatches) / count($requiredSkills));
            $score += $skillScore;
            if ($skillMatches !== []) $reasons[] = 'Compétences correspondantes : '.implode(', ', $skillMatches);
        }

        $titles = $this->normalizeList($preferences['target_titles'] ?? []);
        $title = Str::lower((string) ($job['title'] ?? ''));
        if ($titles !== [] && collect($titles)->contains(fn (string $item): bool => Str::contains($title, $item))) {
            $score += 25;
            $reasons[] = 'Le titre correspond à votre recherche';
        }

        $locations = $this->normalizeList($preferences['locations'] ?? []);
        $location = Str::lower((string) ($job['location'] ?? ''));
        if ($locations !== [] && collect($locations)->contains(fn (string $item): bool => Str::contains($location, $item))) {
            $score += 15;
            $reasons[] = 'Localisation compatible';
        }

        $contracts = $this->normalizeList($preferences['contract_types'] ?? []);
        if ($contracts !== [] && in_array(Str::lower((string) $job['contract_type']), $contracts, true)) {
            $score += 10;
            $reasons[] = 'Type de contrat recherché';
        }

        if (($preferences['remote_only'] ?? false) && in_array($job['remote_policy'], ['remote', 'hybrid'], true)) {
            $score += 5;
            $reasons[] = 'Possibilité de travail à distance';
        }

        if ($reasons === []) $reasons[] = 'Offre publiée correspondant à votre profil de recherche';
        return [min(100, $score), $reasons];
    }

    /** @return array{data: array<int, array<string, mixed>>, source: string, model: string|null} */
    private function aiRerank(User $user, array $preferences, array $jobs): array
    {
        $enabled = filter_var(env('JOB_RECOMMENDATION_AI_ENABLED', true), FILTER_VALIDATE_BOOL);
        $base = trim((string) env('OPENAI_API_BASE', ''));
        $key = trim((string) env('OPENAI_API_KEY', ''));
        if (! $enabled || $base === '' || $key === '' || $jobs === []) {
            return ['data' => $jobs, 'source' => 'rules', 'model' => null];
        }

        try {
            $candidates = collect($jobs)->map(fn (array $job): array => [
                'id' => $job['id'],
                'title' => $job['title'],
                'company' => $job['company']['name'] ?? null,
                'location' => $job['location'],
                'contract_type' => $job['contract_type'],
                'skills_required' => $job['skills_required'],
                'match_score' => $job['match_score'],
            ])->values()->all();

            $response = Http::timeout(8)->withToken($key)->post(rtrim($base, '/').'/chat/completions', [
                'model' => (string) env('JOB_RECOMMENDATION_AI_MODEL', 'gpt-5-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => 'Tu recommandes des offres d’emploi de façon objective. Ne déduis jamais l’âge, le genre, l’origine, la santé ou toute autre caractéristique protégée. Retourne uniquement le JSON demandé.'],
                    ['role' => 'user', 'content' => json_encode([
                        'profil' => $preferences,
                        'offres' => $candidates,
                        'instruction' => 'Classe les offres par pertinence et donne une raison courte et vérifiable pour chaque offre.',
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'job_recommendations',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'ranked' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                                    'id' => ['type' => 'integer'],
                                    'reason' => ['type' => 'string'],
                                ], 'required' => ['id', 'reason'], 'additionalProperties' => false]],
                            ],
                            'required' => ['ranked'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'max_completion_tokens' => 1200,
            ]);
            $content = $response->json('choices.0.message.content');
            $ranked = is_string($content) ? json_decode($content, true, 512, JSON_THROW_ON_ERROR)['ranked'] ?? [] : [];
            $byId = collect($jobs)->keyBy('id');
            $ordered = [];
            foreach ($ranked as $item) {
                $job = $byId->get((int) ($item['id'] ?? 0));
                if ($job) {
                    $job['ai_reason'] = Str::limit((string) ($item['reason'] ?? ''), 240);
                    $ordered[] = $job;
                    $byId->forget($job['id']);
                }
            }
            return ['data' => [...$ordered, ...$byId->values()->all()], 'source' => 'hybrid', 'model' => (string) env('JOB_RECOMMENDATION_AI_MODEL', 'gpt-5-mini')];
        } catch (\Throwable) {
            return ['data' => $jobs, 'source' => 'rules_fallback', 'model' => null];
        }
    }

    private function normalizeList(mixed $value): array
    {
        if (! is_array($value)) return [];
        return array_values(array_unique(array_filter(array_map(fn ($item): string => Str::lower(trim((string) $item)), $value))));
    }

    /** @return array<string, mixed> */
    private function serializeCandidate(JobPosting $job, Company $company): array
    {
        return [
            'id' => (int) $job->id,
            'title' => $job->title,
            'description' => Str::limit(strip_tags((string) $job->description), 500),
            'location' => $job->location,
            'remote_policy' => $job->remote_policy,
            'contract_type' => $job->contract_type,
            'salary_range_min' => $job->salary_range_min,
            'salary_range_max' => $job->salary_range_max,
            'currency' => $job->currency,
            'skills_required' => is_array($job->skills_required) ? $job->skills_required : [],
            'closes_at' => $job->closes_at?->toDateString(),
            'company' => ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug],
            'public_url' => rtrim((string) config('app.frontend_url', config('app.url')), '/')."/careers/{$company->slug}/jobs/{$job->id}",
        ];
    }
}
