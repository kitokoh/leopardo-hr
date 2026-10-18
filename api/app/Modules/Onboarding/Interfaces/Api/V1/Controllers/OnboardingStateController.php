<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Modules\Onboarding\Application\Actions\ResumeOnboardingStep;
use App\Modules\Onboarding\Application\Actions\SeedDefaultSteps;
use App\Modules\Onboarding\Interfaces\Api\V1\Requests\ResumeOnboardingStepRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BOS-O-06 / #8627 — reprise du parcours d'onboarding après interruption.
 *
 * Contrat :
 *   GET   /onboarding-setup/state          → où le serveur dit que l'on en est ;
 *   PATCH /onboarding-setup/state          → l'utilisateur déclare son étape
 *                                            courante (peut RECULER).
 *
 * Pourquoi ce n'est pas redondant avec `checklist` : la checklist décrit la
 * COMPLÉTION (`pending`/`completed`/`skipped`) ; elle ne sait pas dire à quelle
 * étape un utilisateur s'est arrêté, ni distinguer « il a reculé pour corriger
 * l'étape 2 » de « il n'a jamais dépassé l'étape 2 ». `onboarding_state` porte
 * cette information en deux valeurs — `current_step` (mobile) et
 * `furthest_step` (monotone).
 *
 * Isolation : toutes les lectures/écritures sont scopées au tenant courant
 * (`currentCompany()` + `company_id` de l'acteur). Une clé d'étape inconnue de
 * CE tenant renvoie 404 (jamais 403, jamais un état d'un autre espace).
 *
 * RBAC : lecture ouverte à tout utilisateur authentifié du tenant (comme la
 * checklist) ; l'ÉCRITURE de l'étape courante est réservée aux gestionnaires
 * (`api.manager`), comme les autres mutations d'état d'onboarding (#3430).
 */
class OnboardingStateController extends Controller
{
    private const STATUSES = ['not_started', 'in_progress', 'completed', 'skipped'];

    public function __construct(
        private readonly ResumeOnboardingStep $resume,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $this->requireEmployee($request);

        return response()->json(['data' => $this->state(includeSteps: true)]);
    }

    public function update(ResumeOnboardingStepRequest $request): JsonResponse
    {
        $this->authorizeManager($request);

        $company = $this->freshCompany();
        $steps = $this->steps((string) $company->id);
        $stepKey = (string) $request->validated('step_key');

        $known = array_map(static fn (array $step): string => $step['step_key'], $steps);
        if (! in_array($stepKey, $known, true)) {
            // Clé inconnue de CE tenant : 404 — on ne révèle pas l'existence
            // d'une étape appartenant à un autre espace.
            return $this->errorResponse('ONBOARDING_STEP_NOT_FOUND', 404);
        }

        $result = $this->resume->moveTo($company, $stepKey, $steps);
        $data = $result['state'];
        $data['changed'] = $result['changed'];

        return response()->json(['data' => $data]);
    }

    /**
     * @return array<string, mixed>
     */
    private function state(bool $includeSteps = false): array
    {
        $company = $this->freshCompany();
        $companyId = (string) $company->id;
        $steps = $this->steps($companyId);

        $state = $this->resume->read($company, $steps);

        if ($includeSteps) {
            $state['steps'] = $steps;
        }

        return $state;
    }

    /**
     * Étapes réelles du tenant, triées. Seed paresseux identique aux autres
     * endpoints d'onboarding : une société provisionnée avant le correctif
     * n'a aucune ligne et renverrait sinon un état vide trompeur.
     *
     * @return list<array{step_key: string, order: int, status: string}>
     */
    private function steps(string $companyId): array
    {
        $hasSteps = OnboardingStep::where('company_id', $companyId)->exists();
        if (! $hasSteps) {
            app(SeedDefaultSteps::class)->execute($companyId);
        }

        $rows = OnboardingStep::where('company_id', $companyId)
            ->orderBy('order')
            ->get(['step_key', 'order', 'status']);

        $steps = [];
        foreach ($rows as $row) {
            $status = (string) $row->status;
            $steps[] = [
                'step_key' => (string) $row->step_key,
                'order' => (int) $row->order,
                'status' => in_array($status, self::STATUSES, true) ? $status : 'not_started',
            ];
        }

        return $steps;
    }

    private function requireEmployee(Request $request): Employee
    {
        /** @var Employee|null $actor */
        $actor = $request->user();

        abort_if(! $actor instanceof Employee, 401);

        return $actor;
    }

    private function authorizeManager(Request $request): Employee
    {
        $actor = $this->requireEmployee($request);

        abort_unless($actor->hasManagerRole('principal', 'rh'), 403);

        return $actor;
    }

    /**
     * Relit la société depuis la table QUALIFIÉE — le modèle résolu par
     * `search_path` (contexte tenant) pointerait vers le mauvais schéma
     * (piège documenté #7322 / `SetupInterviewController`).
     */
    private function freshCompany(): Company
    {
        /** @var Company $company */
        $company = Company::query()
            ->from($this->companiesTable())
            ->where('id', currentCompany()->id)
            ->firstOrFail();

        return $company;
    }

    private function companiesTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
    }

    /**
     * Réponse d'erreur localisée du module (code stable + message traduit par
     * le catalogue `errors.*` dans la langue résolue par SetLocale).
     */
    private function errorResponse(string $code, int $status): JsonResponse
    {
        $translated = __("errors.{$code}");

        return new JsonResponse([
            'error' => $code,
            'message' => $code,
            'localized_message' => is_string($translated) ? $translated : $code,
        ], $status);
    }
}
