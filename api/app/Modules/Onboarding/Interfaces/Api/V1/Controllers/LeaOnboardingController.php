<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Onboarding\Domain\Services\LeaOnboardingOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #8230 (BOS-042) — point d'entree du parcours Lea.
 *
 * Trois etapes, dans cet ordre, et **jamais fusionnees** :
 *
 *  1. `POST /onboarding/lea/interpret` — description libre → plan propose.
 *     Rien n'est active : l'utilisateur n'a encore rien confirme.
 *  2. (facultatif) la meme route avec `answers` — leve une clarification.
 *  3. `POST /onboarding/lea/confirm` — execution du plan **avec** le jeton
 *     rendu par l'etape 1/2.
 *
 * Le RBAC est celui de l'entretien deterministe (`principal`/`rh`) : un
 * employe ne configure pas l'espace, quel que soit le canal qui y mene.
 *
 * Le flag `onboarding.lea.enabled` est verifie **cote serveur** : le front
 * peut masquer l'entree, mais une route desactivee doit repondre 404 et non
 * executer quand meme — un flag d'interface n'est pas un interrupteur.
 */
class LeaOnboardingController extends Controller
{
    public function __construct(
        private readonly LeaOnboardingOrchestrator $lea,
    ) {}

    public function interpret(Request $request): JsonResponse
    {
        $actor = $this->authorizeManager($request);
        $this->assertEnabled();

        $validated = $request->validate([
            'description' => ['required', 'string', 'min:12', 'max:2000'],
            // Reponses a une clarification precedente (facultatif).
            'answers' => ['sometimes', 'array'],
            'correlation_id' => ['sometimes', 'string', 'max:64'],
        ]);

        $company = $this->freshCompany($actor);
        $correlationId = $this->correlationId($validated, $company, $actor);

        // Si l'appelant leve une clarification, ses reponses explicites
        // priment : aucune nouvelle interpretation du texte n'est lancee, ce
        // qui eviterait de « redeviner » ce qu'il vient de trancher.
        $answers = $validated['answers'] ?? null;

        if (is_array($answers) && $answers !== []) {
            /** @var array<string, mixed> $extracted */
            $extracted = is_array($request->input('extracted')) ? $request->input('extracted') : [];
            $result = $this->lea->clarify($company, $extracted, $answers, $correlationId);

            return response()->json(['data' => $result]);
        }

        $result = $this->lea->interpret($company, (string) $validated['description'], $correlationId);

        return response()->json(['data' => $result]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $actor = $this->authorizeManager($request);
        $this->assertEnabled();

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'plan_token' => ['required', 'string', 'size:32'],
            'correlation_id' => ['sometimes', 'string', 'max:64'],
        ]);

        $company = $this->freshCompany($actor);
        $correlationId = $this->correlationId($validated, $company, $actor);

        /** @var array<string, mixed> $answers */
        $answers = $validated['answers'];

        $result = $this->lea->confirm(
            $company,
            $answers,
            (string) $validated['plan_token'],
            $actor->id,
            $correlationId,
        );

        // Un jeton perime arrive typiquement quand l'utilisateur a repondu a
        // une clarification pendant que l'onglet precedent etait ouvert : le
        // plan qu'il a vu n'est plus celui des reponses envoyees. 409 plutot
        // que 200 pour que le client redemande une confirmation sur le plan
        // a jour au lieu de croire l'activation faite.
        if ($result['outcome'] === LeaOnboardingOrchestrator::OUTCOME_FALLBACK) {
            return response()->json([
                'data' => $result,
                'message' => __('onboarding.lea_confirm_stale'),
            ], 409);
        }

        return response()->json(['data' => $result]);
    }

    private function assertEnabled(): void
    {
        abort_unless((bool) config('onboarding.lea.enabled', false), 404);
    }

    private function authorizeManager(Request $request): Employee
    {
        /** @var Employee|null $actor */
        $actor = $request->user();

        abort_if(! $actor instanceof Employee, 401);
        abort_unless($actor->hasManagerRole('principal', 'rh'), 403);

        return $actor;
    }

    private function freshCompany(Employee $actor): Company
    {
        $company = Company::query()->find($actor->company_id);

        abort_if(! $company instanceof Company, 404);

        return $company;
    }

    /**
     * Identifiant de correlation pour l'audit : fourni par le client quand il
     * suit un parcours, sinon derive du couple societe/acteur — jamais un
     * identifiant aleatoire, qui rendrait deux appels du meme parcours
     * impossibles a relier.
     *
     * @param  array<string, mixed>  $validated
     */
    private function correlationId(array $validated, Company $company, Employee $actor): string
    {
        $provided = $validated['correlation_id'] ?? null;

        if (is_string($provided) && $provided !== '') {
            return $provided;
        }

        return sprintf('lea:%s:%s', $company->id, $actor->id);
    }
}
