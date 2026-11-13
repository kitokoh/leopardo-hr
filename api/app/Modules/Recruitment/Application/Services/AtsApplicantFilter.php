<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Services;

use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Moteur de filtrage ATS.
 *
 * Le besoin réel d'un recruteur n'est pas « lister les candidatures » (la table
 * le fait déjà) mais « retrouver les 12 dossiers qui méritent mon attention
 * aujourd'hui ». Ce service traduit des critères DÉCLARATIFS en requête, et
 * expose en plus un score de correspondance calculé à partir des compétences
 * demandées par l'offre.
 *
 * Deux règles de conception :
 *
 * 1. **Liste blanche stricte.** Chaque critère est lu par une méthode dédiée ;
 *    aucun nom de colonne ne vient de l'extérieur. Un filtre sauvegardé rejoué
 *    plus tard ne peut donc pas devenir une injection SQL.
 * 2. **Aucune donnée inventée.** Le score ne s'applique qu'aux compétences
 *    réellement renseignées sur l'offre ET réellement présentes dans le texte
 *    du candidat (CV/lettre). Sans compétence déclarée sur l'offre, le score
 *    est `null` — pas 0, pas 50 : « non calculable » n'est pas « mauvais ».
 */
final class AtsApplicantFilter
{
    /** Statuts du pipeline, dans l'ordre du parcours de recrutement. */
    public const STATUSES = ['new', 'screening', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'];

    /**
     * Applique les critères à une requête de candidatures.
     *
     * @param  Builder<Applicant>  $query
     * @param  array<string, mixed>  $criteria
     * @return Builder<Applicant>
     */
    public function apply(Builder $query, array $criteria): Builder
    {
        $this->applyStatuses($query, $criteria);
        $this->applyRating($query, $criteria);
        $this->applySource($query, $criteria);
        $this->applySearch($query, $criteria);
        $this->applyAge($query, $criteria);
        $this->applyResumeScan($query, $criteria);

        return $query;
    }

    /**
     * Normalise des critères bruts (query string ou filtre sauvegardé) en un
     * tableau sûr, prêt à être persisté ou rejoué.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normalize(array $raw): array
    {
        $criteria = [];

        $statuses = $this->stringList($raw['statuses'] ?? $raw['status'] ?? null);
        $statuses = array_values(array_intersect($statuses, self::STATUSES));
        if ($statuses !== []) {
            $criteria['statuses'] = $statuses;
        }

        if (isset($raw['min_rating']) && is_numeric($raw['min_rating'])) {
            $rating = (int) $raw['min_rating'];
            if ($rating >= 1 && $rating <= 5) {
                $criteria['min_rating'] = $rating;
            }
        }

        $source = is_string($raw['source'] ?? null) ? trim($raw['source']) : '';
        if ($source !== '' && in_array($source, ['website', 'referral', 'linkedin', 'agency', 'other'], true)) {
            $criteria['source'] = $source;
        }

        $q = is_string($raw['q'] ?? null) ? trim($raw['q']) : '';
        if ($q !== '') {
            // Bornage : un terme de 500 caractères n'est pas une recherche,
            // c'est une tentative de déni de service sur le LIKE.
            $criteria['q'] = mb_substr($q, 0, 120);
        }

        if (isset($raw['older_than_days']) && is_numeric($raw['older_than_days'])) {
            $days = (int) $raw['older_than_days'];
            if ($days > 0 && $days <= 3650) {
                $criteria['older_than_days'] = $days;
            }
        }

        if (isset($raw['newer_than_days']) && is_numeric($raw['newer_than_days'])) {
            $days = (int) $raw['newer_than_days'];
            if ($days > 0 && $days <= 3650) {
                $criteria['newer_than_days'] = $days;
            }
        }

        $scan = is_string($raw['resume_scan_status'] ?? null) ? $raw['resume_scan_status'] : '';
        if (in_array($scan, ['clean', 'infected', 'unavailable', 'unscanned'], true)) {
            $criteria['resume_scan_status'] = $scan;
        }

        $sort = is_string($raw['sort'] ?? null) ? $raw['sort'] : '';
        if (in_array($sort, ['recent', 'oldest', 'rating', 'name'], true)) {
            $criteria['sort'] = $sort;
        }

        return $criteria;
    }

    /**
     * Tri de la liste. Le tri par défaut est « plus récent d'abord » : c'est
     * l'ordre dans lequel un recruteur veut voir arriver les dossiers.
     *
     * @param  Builder<Applicant>  $query
     * @return Builder<Applicant>
     */
    public function sort(Builder $query, array $criteria): Builder
    {
        return match ($criteria['sort'] ?? 'recent') {
            'oldest' => $query->orderBy('applied_at'),
            'rating' => $query->orderByDesc('rating')->orderByDesc('applied_at'),
            'name' => $query->orderBy('last_name')->orderBy('first_name'),
            default => $query->orderByDesc('applied_at'),
        };
    }

    /**
     * Score de correspondance 0-100 entre un candidat et les compétences
     * demandées par l'offre.
     *
     * Retourne `null` quand l'offre ne déclare aucune compétence : il n'y a
     * alors rien à comparer, et afficher 0 laisserait croire à un mauvais
     * profil.
     *
     * @return array{score: int|null, matched: list<string>, missing: list<string>}
     */
    public function score(Applicant $applicant, ?JobPosting $job): array
    {
        $required = $job?->skills_required;
        if (! is_array($required) || $required === []) {
            return ['score' => null, 'matched' => [], 'missing' => []];
        }

        $required = array_values(array_filter(
            array_map(static fn ($s): string => is_string($s) ? trim($s) : '', $required),
            static fn (string $s): bool => $s !== '',
        ));

        if ($required === []) {
            return ['score' => null, 'matched' => [], 'missing' => []];
        }

        // Le corpus est le texte que le candidat a réellement fourni. On ne
        // devine pas ses compétences à partir de son intitulé de poste.
        $haystack = mb_strtolower(trim(implode(' ', array_filter([
            (string) $applicant->cover_letter,
            (string) $applicant->notes,
        ]))));

        $matched = [];
        $missing = [];

        foreach ($required as $skill) {
            if ($haystack !== '' && str_contains($haystack, mb_strtolower($skill))) {
                $matched[] = $skill;
            } else {
                $missing[] = $skill;
            }
        }

        return [
            'score' => (int) round(count($matched) / count($required) * 100),
            'matched' => $matched,
            'missing' => $missing,
        ];
    }

    /**
     * Agrégats du pipeline pour l'en-tête de l'écran ATS.
     *
     * @param  Builder<Applicant>  $query
     * @return array<string, int>
     */
    public function counts(Builder $query): array
    {
        $rows = (clone $query)
            ->reorder()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = [];
        foreach (self::STATUSES as $status) {
            $counts[$status] = (int) ($rows[$status] ?? 0);
        }
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    /** @param Builder<Applicant> $query */
    private function applyStatuses(Builder $query, array $criteria): void
    {
        $statuses = $criteria['statuses'] ?? null;
        if (is_array($statuses) && $statuses !== []) {
            $query->whereIn('status', array_values(array_intersect($statuses, self::STATUSES)));
        }
    }

    /** @param Builder<Applicant> $query */
    private function applyRating(Builder $query, array $criteria): void
    {
        if (isset($criteria['min_rating'])) {
            $query->where('rating', '>=', (int) $criteria['min_rating']);
        }
    }

    /** @param Builder<Applicant> $query */
    private function applySource(Builder $query, array $criteria): void
    {
        if (isset($criteria['source'])) {
            $query->where('source', (string) $criteria['source']);
        }
    }

    /** @param Builder<Applicant> $query */
    private function applySearch(Builder $query, array $criteria): void
    {
        $term = $criteria['q'] ?? null;
        if (! is_string($term) || $term === '') {
            return;
        }

        // `%` et `_` sont des jokers LIKE : un candidat nommé « 100% » ne doit
        // pas faire correspondre tout le monde.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
        $like = '%'.$escaped.'%';

        $query->where(function (Builder $q) use ($like): void {
            $q->whereRaw("first_name ILIKE ? ESCAPE '\\'", [$like])
                ->orWhereRaw("last_name ILIKE ? ESCAPE '\\'", [$like])
                ->orWhereRaw("email ILIKE ? ESCAPE '\\'", [$like])
                ->orWhereRaw("COALESCE(cover_letter, '') ILIKE ? ESCAPE '\\'", [$like]);
        });
    }

    /** @param Builder<Applicant> $query */
    private function applyAge(Builder $query, array $criteria): void
    {
        if (isset($criteria['older_than_days'])) {
            $query->where('applied_at', '<=', Carbon::now()->subDays((int) $criteria['older_than_days']));
        }

        if (isset($criteria['newer_than_days'])) {
            $query->where('applied_at', '>=', Carbon::now()->subDays((int) $criteria['newer_than_days']));
        }
    }

    /** @param Builder<Applicant> $query */
    private function applyResumeScan(Builder $query, array $criteria): void
    {
        $scan = $criteria['resume_scan_status'] ?? null;
        if (! is_string($scan) || $scan === '') {
            return;
        }

        if ($scan === 'unscanned') {
            $query->whereNull('resume_scan_status');

            return;
        }

        $query->where('resume_scan_status', $scan);
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($v): string => is_string($v) ? trim($v) : '', $value),
            static fn (string $v): bool => $v !== '',
        ));
    }
}
