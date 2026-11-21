<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\Services;

use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agrégations du tableau de bord recrutement (#9546).
 *
 * Extrait de `RecruitmentController::stats()` (126 lignes) : la couche HTTP ne
 * doit porter que le contrat (RBAC, parsing de la période, forme de la
 * réponse), pas les règles de calcul. Le taux de conversion du funnel et le
 * délai moyen d'embauche sont des règles métier : ils doivent être testables
 * sans monter une requête HTTP complète.
 *
 * Le périmètre est TOUJOURS borné par `company_id` : l'appelant fournit
 * l'identifiant de la société de l'acteur authentifié, il n'y a pas de tenant
 * implicite ici.
 */
class RecruitmentStatsService
{
    /**
     * KPIs de recrutement sur une période optionnelle.
     *
     * @return array{
     *     period: array{from: string|null, to: string|null},
     *     job_postings: array{total: int, active: int, filled: int, closed: int},
     *     applications: array{total: int, new: int, screening: int, interview: int, offer: int, hired: int, rejected: int},
     *     funnel: array{screening_rate: float, interview_rate: float, offer_rate: float, conversion_rate: float},
     *     avg_time_to_hire_days: float|null,
     *     top_sources: list<array{source: string|null, count: int}>
     * }
     */
    public function overview(string $companyId, ?Carbon $from, ?Carbon $to): array
    {
        $jobStats = $this->jobPostingCounts($companyId, $from, $to);
        $appStats = $this->applicationCounts($companyId, $from, $to);

        $total = $appStats['total'];
        $screening = $appStats['screening'];
        $interview = $appStats['interview'];
        $offer = $appStats['offer'];
        $hired = $appStats['hired'];

        return [
            'period' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'job_postings' => $jobStats,
            'applications' => $appStats,
            'funnel' => [
                'screening_rate' => $this->rate($screening, $total),
                'interview_rate' => $this->rate($interview, $screening),
                'offer_rate' => $this->rate($offer, $interview),
                'conversion_rate' => $this->rate($hired, $total),
            ],
            'avg_time_to_hire_days' => $this->averageTimeToHireDays($companyId),
            'top_sources' => $this->topSources($companyId, $from, $to),
        ];
    }

    /**
     * @return array{total: int, active: int, filled: int, closed: int}
     */
    private function jobPostingCounts(string $companyId, ?Carbon $from, ?Carbon $to): array
    {
        $query = JobPosting::query()->where('company_id', $companyId);
        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }

        $row = $query->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as filled,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as closed
        ', ['published', 'closed', 'archived'])->first();

        return [
            'total' => $this->intProp($row, 'total'),
            'active' => $this->intProp($row, 'active'),
            'filled' => $this->intProp($row, 'filled'),
            'closed' => $this->intProp($row, 'closed'),
        ];
    }

    /**
     * @return array{total: int, new: int, screening: int, interview: int, offer: int, hired: int, rejected: int}
     */
    private function applicationCounts(string $companyId, ?Carbon $from, ?Carbon $to): array
    {
        $query = Applicant::query()->where('company_id', $companyId);
        if ($from !== null) {
            $query->where('applied_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('applied_at', '<=', $to);
        }

        $row = $query->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as new,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as screening,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as interview,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as offer,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as hired,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected
        ', ['new', 'screening', 'interview', 'offer', 'hired', 'rejected'])->first();

        return [
            'total' => $this->intProp($row, 'total'),
            'new' => $this->intProp($row, 'new'),
            'screening' => $this->intProp($row, 'screening'),
            'interview' => $this->intProp($row, 'interview'),
            'offer' => $this->intProp($row, 'offer'),
            'hired' => $this->intProp($row, 'hired'),
            'rejected' => $this->intProp($row, 'rejected'),
        ];
    }

    /**
     * Délai moyen d'embauche, en jours.
     *
     * #9677 — `applicants` n'a pas de colonne `hired_at` : le délai est mesuré
     * depuis `applied_at` jusqu'à la dernière mise à jour de la candidature,
     * qui correspond au passage au statut `hired`.
     */
    private function averageTimeToHireDays(string $companyId): ?float
    {
        $avg = Applicant::query()
            ->where('company_id', $companyId)
            ->where('status', 'hired')
            ->whereNotNull('applied_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (updated_at - applied_at)) / 86400) as avg_days')
            ->value('avg_days');

        return $avg === null ? null : round((float) $avg, 1);
    }

    /**
     * @return list<array{source: string|null, count: int}>
     */
    private function topSources(string $companyId, ?Carbon $from, ?Carbon $to): array
    {
        $rows = Applicant::query()
            ->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->where('applied_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('applied_at', '<=', $to))
            ->whereNotNull('source')
            ->select('source', DB::raw('COUNT(*) as count'))
            ->groupBy('source')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        /** @var list<array{source: string|null, count: int}> $sources */
        $sources = array_values($rows->map(function ($row): array {
            $source = $row->getAttribute('source');

            return [
                'source' => is_scalar($source) ? (string) $source : null,
                'count' => (int) $row->getAttribute('count'),
            ];
        })->all());

        return $sources;
    }

    /**
     * Taux de conversion, arrondi à 4 décimales.
     *
     * Un dénominateur nul renvoie 0.0 plutôt que de lever une division par
     * zéro : une période sans candidature est un cas normal, pas une erreur.
     */
    private function rate(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round($numerator / $denominator, 4) : 0.0;
    }

    /**
     * Lecture d'une propriété entière sur une ligne de requête brute.
     *
     * `selectRaw(...)->first()` renvoie `object|null` : accéder directement à
     * la propriété ferait échouer PHPStan level 8 et masquerait le cas
     * « aucune ligne ». On passe par `get_object_vars` pour rester typé.
     */
    private function intProp(?object $row, string $prop): int
    {
        if ($row === null) {
            return 0;
        }

        $vars = get_object_vars($row);
        $value = $vars[$prop] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }
}
