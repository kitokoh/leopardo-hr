<?php

declare(strict_types=1);

namespace App\Core\Solutions\Survey;

use App\Core\Solutions\Survey\Contracts\SolutionSurvey;

/**
 * Moteur de suggestion de packages — déterministe et sans effet de bord.
 *
 * Applique les règles du survey aux réponses du prospect et retourne les
 * packages suggérés, triés par priorité (la plus haute d'abord), chacun
 * avec sa raison lisible (clé i18n, localisée par le front).
 *
 * Aucune dépendance externe : testable en unité pure.
 */
final class SolutionSurveyEngine
{
    /**
     * @param  array<string, mixed>  $answers  réponses brutes du questionnaire
     * @return array{
     *   packages: list<array{
     *     key: string,
     *     type: string,
     *     label_key: string,
     *     reason_key: string,
     *     app?: string,
     *     download?: string|null,
     *     required?: bool,
     *     priority: int
     *   }>,
     *   total: int
     * }
     */
    public function suggest(SolutionSurvey $survey, array $answers): array
    {
        $matched = [];

        foreach ($survey->rules() as $rule) {
            if (! ($rule['when'])($answers)) {
                continue;
            }

            $package = $survey->packages()[$rule['package']] ?? null;
            if ($package === null) {
                continue; // règle orpheline : jamais bloquant
            }

            // Fusion par package : on garde la priorité la plus haute et la
            // première raison rencontrée (ordre de déclaration = ordre logique).
            $key = $package['key'];
            if (! isset($matched[$key])) {
                $matched[$key] = [
                    ...$package,
                    'reason_key' => $rule['reason_key'],
                    'priority' => $rule['priority'],
                ];
            } else {
                $matched[$key]['priority'] = max($matched[$key]['priority'], $rule['priority']);
            }
        }

        $packages = array_values($matched);
        usort(
            $packages,
            static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']
        );

        return [
            'packages' => $packages,
            'total' => count($packages),
        ];
    }
}
