<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Actions;

use App\Core\Solutions\SolutionActivator;
use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Onboarding\Domain\Services\SetupInterviewPlanner;
use App\Modules\Onboarding\Infrastructure\Services\CompanyActivationSurfaceWriter;

/**
 * Use Case : clôture de l'entretien de préparation (#7493) — traduit les
 * réponses en activations réelles, puis persiste l'état `completed`.
 *
 * Propriétés :
 *  - **idempotent** : un entretien déjà `completed` est un no-op strict
 *    (aucune nouvelle activation, aucune écriture) — le récapitulatif
 *    d'origine est rejoué tel quel ;
 *  - **fail-safe** : l'échec d'activation d'une solution n'est PAS bloquant
 *    pour l'entretien — la solution est tracée dans `failed` (remontée
 *    support), l'espace reste utilisable ;
 *  - **allowlisté** : le plan vient de `SetupInterviewPlanner` (outils bornés
 *    à `Company::HORIZONTAL_TOOLS`) et chaque solution est vérifiée contre
 *    `SolutionCatalogue::has()` avant activation (kill switch serveur) ;
 *  - les verticales passent par `SolutionActivator::activateWithDependencies`
 *    (idempotent, audité `solution.activated`) — le chemin canonique #6693.
 *
 * L'idempotence repose sur l'état PERSISTÉ (lecture fraîche), pas sur
 * l'instance reçue — même leçon que `AcknowledgeWelcomeScreen`.
 */
final class CompleteSetupInterview
{
    public function __construct(
        private readonly SetupInterviewPlanner $planner,
        private readonly SolutionActivator $activator,
        private readonly SolutionCatalogue $catalogue,
        private readonly CompanyActivationSurfaceWriter $writer,
        private readonly SeedDefaultSteps $seedSteps,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     already_completed: bool,
     *     completed_at: string,
     *     activated: array{solutions: list<string>, tools: list<string>, failed: list<string>}
     * }
     */
    public function execute(Company $company, ?int $actorId = null): array
    {
        // Lecture FRAÎCHE : l'instance du conteneur peut être antérieure à un
        // premier `complete` (piège documenté sur `AcknowledgeWelcomeScreen`).
        $fresh = Company::query()->find($company->id);
        $source = $fresh instanceof Company ? $fresh : $company;

        $metadata = $source->metadata ?? [];
        $interview = is_array($metadata['setup_interview'] ?? null) ? $metadata['setup_interview'] : [];

        if (($interview['status'] ?? null) === 'completed') {
            $activated = is_array($interview['activated'] ?? null) ? $interview['activated'] : [];

            return [
                'status' => 'completed',
                'already_completed' => true,
                'completed_at' => (string) ($interview['completed_at'] ?? ''),
                'activated' => [
                    'solutions' => array_values((array) ($activated['solutions'] ?? [])),
                    'tools' => array_values((array) ($activated['tools'] ?? [])),
                    'failed' => array_values((array) ($activated['failed'] ?? [])),
                ],
            ];
        }

        $answers = is_array($interview['answers'] ?? null) ? $interview['answers'] : [];
        $plan = $this->planner->plan($answers);

        // Outils horizontaux — idempotents (`activateHorizontalTool` retourne
        // false si déjà actif) ; la persistance est faite en une fois à la fin.
        $tools = [];
        foreach ($plan['tools'] as $tool) {
            if ($source->activateHorizontalTool($tool)) {
                $tools[] = $tool;
            }
        }

        // Solutions verticales — chemin canonique, non bloquant en cas d'échec.
        $solutions = [];
        $failed = [];
        foreach ($plan['solutions'] as $code) {
            if (! $this->catalogue->has($code)) {
                continue; // fail-closed : code hors catalogue, jamais activé
            }

            try {
                $result = $this->activator->activateWithDependencies($source, $code, $actorId);
                if (in_array($result['status'] ?? null, ['activated', 'already_active'], true)) {
                    $solutions[] = $code;
                } else {
                    $failed[] = $code;
                }
            } catch (\Throwable) {
                // Non bloquant pour l'entretien : tracé pour le support.
                $failed[] = $code;
            }
        }

        $completedAt = now()->toIso8601String();
        $activated = ['solutions' => $solutions, 'tools' => $tools, 'failed' => $failed];

        $metadata = $source->metadata ?? []; // relu : activateHorizontalTool a muté metadata.modules
        $interview['status'] = 'completed';
        $interview['answers'] = $answers;
        $interview['completed_at'] = $completedAt;
        $interview['activated'] = $activated;
        $metadata['setup_interview'] = $interview;

        // Écriture QUALIFIÉE unique des deux sources de vérité (metadata +
        // features) — autoritaire même si le save interne de l'activator a
        // ciblé un autre schéma sous `search_path` tenant.
        $this->writer->persist((string) $source->id, $metadata, $source->features ?? []);

        // #7494 — la checklist d'onboarding est resynchronisée sur le profil
        // issu de l'entretien : les étapes génériques encore `pending` sans
        // rapport avec les modules actifs sont retirées, les étapes du profil
        // sont ajoutées (les actions déjà faites/sautées sont conservées).
        $this->seedSteps->execute((string) $source->id);

        return [
            'status' => 'completed',
            'already_completed' => false,
            'completed_at' => $completedAt,
            'activated' => $activated,
        ];
    }
}
