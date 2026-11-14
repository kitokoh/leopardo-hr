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
     * @param  array<string, mixed>  $criteria
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
     * Le corpus est le texte que le candidat a RÉELLEMENT fourni : le CV
     * extrait (`resume_text`), la lettre de motivation et les notes internes.
     * Avant #9461, seul le texte libre était lu — un candidat sans lettre
     * obtenait 0 % malgré un CV parfaitement aligné, ce qui rendait le score
     * trompeur au point d'être inutilisable.
     *
     * Retourne `null` quand l'offre ne déclare aucune compétence : il n'y a
     * alors rien à comparer, et afficher 0 laisserait croire à un mauvais
     * profil.
     *
     * @return array{score: int|null, matched: list<string>, missing: list<string>, sources: list<string>}
     */
    public function score(Applicant $applicant, ?JobPosting $job): array
    {
        $required = $job?->skills_required;
        if (! is_array($required) || $required === []) {
            return ['score' => null, 'matched' => [], 'missing' => [], 'sources' => []];
        }

        $required = array_values(array_filter(
            array_map(static fn ($s): string => is_string($s) ? trim($s) : '', $required),
            static fn (string $s): bool => $s !== '',
        ));

        if ($required === []) {
            return ['score' => null, 'matched' => [], 'missing' => [], 'sources' => []];
        }

        // Le corpus est le texte que le candidat a réellement fourni. On ne
        // devine pas ses compétences à partir de son intitulé de poste.
        //
        // `sources` dit au recruteur SUR QUOI le score a été calculé : un
        // score de 40 % obtenu sans CV lisible n'a pas la même valeur qu'un
        // score de 40 % sur un CV complet, et l'écran doit pouvoir le dire.
        $sources = [];
        $parts = [];

        if (is_string($applicant->resume_text) && trim($applicant->resume_text) !== '') {
            $parts[] = $applicant->resume_text;
            $sources[] = 'resume';
        }

        if (is_string($applicant->cover_letter) && trim($applicant->cover_letter) !== '') {
            $parts[] = $applicant->cover_letter;
            $sources[] = 'cover_letter';
        }

        if (is_string($applicant->notes) && trim($applicant->notes) !== '') {
            $parts[] = $applicant->notes;
            $sources[] = 'notes';
        }

        $haystack = mb_strtolower(trim(implode("\n", $parts)));

        $matched = [];
        $missing = [];

        foreach ($required as $skill) {
            if ($haystack !== '' && $this->containsSkill($haystack, $skill)) {
                $matched[] = $skill;
            } else {
                $missing[] = $skill;
            }
        }

        return [
            'score' => (int) round(count($matched) / count($required) * 100),
            'matched' => $matched,
            'missing' => $missing,
            'sources' => $sources,
        ];
    }

    /**
     * Présence d'une compétence dans le corpus, avec frontières de mot.
     *
     * Une simple sous-chaîne produit des faux positifs coûteux : « R » est
     * contenu dans « Recrutement », « Go » dans « Google », « C » partout. On
     * exige donc que la compétence ne soit pas collée à une lettre ou un
     * chiffre. Les compétences à plusieurs mots (« React Native ») restent
     * cherchées telles quelles, espaces normalisés.
     */
    private function containsSkill(string $haystack, string $skill): bool
    {
        $needle = mb_strtolower(trim($skill));
        if ($needle === '') {
            return false;
        }

        // Les espaces du corpus ont été normalisés à l'extraction, mais pas
        // forcément ceux de la lettre : on tolère plusieurs espaces.
        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'(?![\p{L}\p{N}])/u';

        return preg_match($pattern, $haystack) === 1;
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

    /**
     * @param  Builder<Applicant>  $query
     * @param  array<string, mixed>  $criteria
     */
    private function applyStatuses(Builder $query, array $criteria): void
    {
        $statuses = $criteria['statuses'] ?? null;
        if (is_array($statuses) && $statuses !== []) {
            $query->whereIn('status', array_values(array_intersect($statuses, self::STATUSES)));
        }
    }

    /**
     * @param  Builder<Applicant>  $query
     * @param  array<string, mixed>  $criteria
     */
    private function applyRating(Builder $query, array $criteria): void
    {
        if (isset($criteria['min_rating'])) {
            $query->where('rating', '>=', (int) $criteria['min_rating']);
        }
    }

    /**
     * @param  Builder<Applicant>  $query
     * @param  array<string, mixed>  $criteria
     */
    private function applySource(Builder $query, array $criteria): void
    {
        if (isset($criteria['source'])) {
            $query->where('source', (string) $criteria['source']);
        }
    }

    /**
     * @param  Builder<Applicant>  $query
     * @param  array<string, mixed>  $criteria
     */
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
                ->orWhereRaw("COALESCE(cover_letter, '') ILIKE ? ESCAPE '\\'", [$like])
                // ATS (#9461) — chercher « Kubernetes » doit trouver le
                // candidat qui l'a écrit dans son CV, pas seulement dans sa
                // lettre. Sans cette branche, la recherche plein texte
                // ignorait le document principal du dossier.
                ->orWhereRaw("COALESCE(resume_text, '') ILIKE ? ESCAPE '\\'", [$like]);
        });
    }

    /**
     * @param  Builder<Applicant>  $query
     * @param  array<string, mixed>  $criteria
     */
    private function applyAge(Builder $query, array $criteria): void
    {
        if (isset($criteria['older_than_days'])) {
            $query->where('applied_at', '<=', Carbon::now()->subDays((int) $criteria['older_than_days']));
        }

        if (isset($criteria['newer_than_days'])) {
            $query->where('applied_at', '>=', Carbon::now()->subDays((int) $criteria['newer_than_days']));
        }
    }

    /**
     * @param  Builder<Applicant>  $query
     * @param  array<string, mixed>  $criteria
     */
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
