<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ApplicantResource;
use App\Modules\Recruitment\Application\Services\ApplicantStatusJournal;
use App\Modules\Recruitment\Application\Services\AtsApplicantFilter;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\AtsSavedFilter;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * ATS — vue de travail du recruteur.
 *
 * Trois responsabilités, volontairement séparées de `RecruitmentController`
 * (qui gère le CRUD) :
 *
 * 1. **Pipeline filtrable** — la liste transverse des candidatures d'une
 *    entreprise, filtrable par statut, note, source, ancienneté, état du CV et
 *    recherche plein texte, avec les compteurs par étape.
 * 2. **Dossier détaillé** — une candidature avec son historique de statuts et
 *    son score de correspondance à l'offre.
 * 3. **Filtres sauvegardés** — les vues de travail nommées, rejouables.
 *
 * Toutes les routes sont protégées par le middleware tenant + Sanctum ; chaque
 * requête est en plus bornée à `company_id` de l'acteur. Un identifiant d'une
 * autre entreprise répond 404, jamais 403 : on ne confirme pas l'existence
 * d'un dossier qu'on n'a pas le droit de voir.
 */
class AtsController extends Controller
{
    public function __construct(
        private readonly AtsApplicantFilter $filter,
        private readonly ApplicantStatusJournal $journal,
    ) {}

    /**
     * GET /api/v1/recruitment/ats/applicants
     *
     * Pipeline transverse, tous postes confondus.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $criteria = $this->filter->normalize($request->all());

        $base = Applicant::query()
            ->where('company_id', $actor->company_id)
            ->with('jobPosting:id,title');

        $counts = $this->filter->counts(clone $base);

        $query = $this->filter->apply(clone $base, $criteria);
        $query = $this->filter->sort($query, $criteria);

        $perPage = max(1, min(100, $request->integer('per_page', 20)));
        $page = $query->paginate($perPage);

        return ApplicantResource::collection($page)
            ->additional([
                'meta' => [
                    'counts' => $counts,
                    'criteria' => $criteria,
                    'statuses' => AtsApplicantFilter::STATUSES,
                ],
            ])
            ->response();
    }

    /**
     * GET /api/v1/recruitment/ats/jobs/{jobPosting}/applicants
     *
     * Pipeline d'une offre précise — c'est l'écran qu'ouvre un recruteur
     * depuis une offre publiée.
     */
    public function jobPipeline(Request $request, int $jobPosting): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $job = JobPosting::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($jobPosting);

        $criteria = $this->filter->normalize($request->all());

        $base = Applicant::query()
            ->where('company_id', $actor->company_id)
            ->where('job_posting_id', $job->id);

        $counts = $this->filter->counts(clone $base);

        $query = $this->filter->apply(clone $base, $criteria);
        $query = $this->filter->sort($query, $criteria);

        $perPage = max(1, min(100, $request->integer('per_page', 20)));
        $page = $query->paginate($perPage);

        return ApplicantResource::collection($page)
            ->additional([
                'meta' => [
                    'job' => [
                        'id' => $job->id,
                        'title' => $job->title,
                        'status' => $job->status,
                        'skills_required' => $job->skills_required ?? [],
                    ],
                    'counts' => $counts,
                    'criteria' => $criteria,
                    'statuses' => AtsApplicantFilter::STATUSES,
                ],
            ])
            ->response();
    }

    /**
     * GET /api/v1/recruitment/ats/applicants/{applicant}
     *
     * Dossier complet : historique, entretiens, score de correspondance.
     */
    public function show(Request $request, int $applicant): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $record = Applicant::query()
            ->where('company_id', $actor->company_id)
            ->with(['jobPosting:id,title,skills_required', 'interviews', 'statusEvents'])
            ->findOrFail($applicant);

        $score = $this->filter->score($record, $record->jobPosting);

        return (new ApplicantResource($record))
            ->additional([
                'meta' => [
                    'match' => $score,
                    'time_in_stage_hours' => $this->journal->timeInStage($record),
                ],
            ])
            ->response();
    }

    /**
     * PATCH /api/v1/recruitment/ats/applicants/{applicant}/status
     *
     * Déplacement dans le pipeline, avec justification facultative. Le
     * changement est journalisé (qui, quand, pourquoi).
     */
    public function updateStatus(Request $request, int $applicant): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(AtsApplicantFilter::STATUSES)],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $record = Applicant::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($applicant);

        $record = app(\App\Modules\Recruitment\Application\Actions\UpdateApplicantAction::class)
            ->execute(
                $record,
                [
                    'status' => $validated['status'],
                    'status_reason' => $validated['reason'] ?? null,
                ],
                ['id' => $actor->id, 'name' => trim($actor->first_name.' '.$actor->last_name)],
            );

        return (new ApplicantResource($record->load('statusEvents')))->response();
    }

    /**
     * GET /api/v1/recruitment/applicants/{applicant}/resume
     *
     * Sert le CV depuis le disque privé. Le chemin stocké n'est jamais exposé
     * au client : il est résolu ici, après vérification du tenant.
     */
    public function downloadResume(Request $request, int $applicant): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $record = Applicant::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($applicant);

        $path = (string) $record->resume_path;
        if ($path === '' || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $absolute = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf';
        $filename = sprintf(
            'cv-%s-%s.%s',
            \Illuminate\Support\Str::slug($record->full_name) ?: 'candidat',
            $record->id,
            $extension,
        );

        return response()->download($absolute, $filename);
    }

    /**
     * GET /api/v1/recruitment/ats/filters
     *
     * Filtres sauvegardés visibles par l'acteur : les siens et ceux partagés.
     */
    public function indexFilters(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $filters = AtsSavedFilter::query()
            ->where('company_id', $actor->company_id)
            ->where(function ($q) use ($actor): void {
                $q->where('created_by', $actor->id)->orWhere('is_shared', true);
            })
            ->orderBy('name')
            ->get();

        return new JsonResponse([
            'data' => $filters->map(fn (AtsSavedFilter $filter) => [
                'id' => $filter->id,
                'name' => $filter->name,
                'job_posting_id' => $filter->job_posting_id,
                'criteria' => $filter->criteria,
                'is_shared' => $filter->is_shared,
                'created_by' => $filter->created_by,
            ])->values(),
        ]);
    }

    /**
     * POST /api/v1/recruitment/ats/filters
     */
    public function storeFilter(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'job_posting_id' => [
                'nullable',
                'integer',
                Rule::exists('job_postings', 'id')->where('company_id', $actor->company_id),
            ],
            'criteria' => ['required', 'array'],
            'is_shared' => ['nullable', 'boolean'],
        ]);

        $filter = AtsSavedFilter::create([
            'company_id' => $actor->company_id,
            'job_posting_id' => $validated['job_posting_id'] ?? null,
            'name' => $validated['name'],
            // On persiste les critères NORMALISÉS : un filtre enregistré avec
            // un statut inconnu serait rejoué à vide sans que personne ne
            // comprenne pourquoi.
            'criteria' => $this->filter->normalize($validated['criteria']),
            'created_by' => $actor->id,
            'is_shared' => (bool) ($validated['is_shared'] ?? false),
        ]);

        return new JsonResponse([
            'data' => [
                'id' => $filter->id,
                'name' => $filter->name,
                'job_posting_id' => $filter->job_posting_id,
                'criteria' => $filter->criteria,
                'is_shared' => $filter->is_shared,
            ],
        ], 201);
    }

    /**
     * DELETE /api/v1/recruitment/ats/filters/{filter}
     */
    public function destroyFilter(Request $request, int $filter): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $saved = AtsSavedFilter::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($filter);

        // Un filtre partagé n'est supprimable que par son auteur : sinon
        // n'importe quel manager effacerait la vue de travail de l'équipe.
        if ($saved->is_shared && $saved->created_by !== $actor->id) {
            abort(403);
        }

        $saved->delete();

        return new JsonResponse(['message' => 'Filter deleted.']);
    }
}
