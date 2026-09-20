<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Services;

use App\Core\Tenant\Domain\Models\Company;

/**
 * #7493 — moteur de mapping réponses d'entretien → plan d'activation.
 *
 * Miroir tenant-scoped du pattern `SolutionSurveyEngine` (pré-inscription) :
 * déterministe, sans effet de bord, testable en unité pure. Les réponses de
 * l'entretien de préparation (`/setup-interview`) sont traduites en un plan
 * `{solutions, tools}` que `CompleteSetupInterview` exécute ensuite via
 * `SolutionActivator` (verticales) et `Company::activateHorizontalTool`
 * (outils horizontaux).
 *
 * Kill switch / fail-closed :
 *  - une réponse inconnue est IGNORÉE (jamais d'activation hors allowlist) ;
 *  - les outils retournés appartiennent tous à `Company::HORIZONTAL_TOOLS` ;
 *  - les solutions retournées sont des codes candidats : l'exécuteur vérifie
 *    `SolutionCatalogue::has()` avant toute activation ;
 *  - un profil `solo` ne reçoit JAMAIS d'outil d'équipe hors plancher
 *    (`Company::SOLO_FLOOR_TOOLS`) — même garde que `CompanyModuleController`.
 */
final class SetupInterviewPlanner
{
    /**
     * Allowlist des questions et de leurs réponses possibles. `null` reste
     * accepté partout (question sautée). `priorities` est multi-choix.
     * `company_name` (#7853) est en TEXTE LIBRE (liste d'options vide,
     * voir FREE_TEXT) : c'est la première question de l'entretien depuis que
     * le nom d'entreprise n'est plus demandé à l'inscription.
     *
     * @var array<string, list<string>>
     */
    public const QUESTIONS = [
        'company_name' => [],
        'company_type' => ['solo', 'team'],
        'team_size' => ['1-10', '11-50', '51-200', '201-500', '500+'],
        'sector' => ['restaurant', 'fuel_station', 'education', 'commerce', 'services', 'travel', 'other'],
        'premises' => ['single', 'multiple', 'mobile', 'none'],
        'priorities' => ['attendance', 'payroll', 'accounting', 'crm', 'cameras', 'showcase'],
        'scheduled_hours' => ['yes', 'no'],
    ];

    /** @var list<string> */
    public const MULTI_CHOICE = ['priorities'];

    /**
     * #7853 — questions à réponse en texte libre (optionnelles/zappables).
     * L'allowlist reste fail-closed : la CLÉ doit exister dans QUESTIONS et
     * la valeur doit être une chaîne bornée (2..120 après trim) — aucune
     * valeur libre ne produit d'activation (plan() ne lit jamais ces clés).
     *
     * @var list<string>
     */
    public const FREE_TEXT = ['company_name'];

    /** Longueur maximale d'une réponse en texte libre (miroir signup 2..120). */
    public const FREE_TEXT_MAX = 120;

    /**
     * Secteur déclaré → code de solution verticale (`SolutionCatalogue`).
     * `commerce`/`services`/`other` n'ont pas de verticale : outils
     * horizontaux uniquement.
     *
     * @var array<string, string>
     */
    private const SECTOR_SOLUTIONS = [
        'restaurant' => 'restaurant',
        'fuel_station' => 'fuel_station',
        'education' => 'edumanager',
        'travel' => 'travelagency',
    ];

    /**
     * Priorité exprimée → outil horizontal (`Company::HORIZONTAL_TOOLS`).
     *
     * @var array<string, string>
     */
    private const PRIORITY_TOOLS = [
        'attendance' => 'attendance',
        'payroll' => 'payroll',
        'accounting' => 'accounting',
        'crm' => 'crm',
        'cameras' => 'cameras',
        'showcase' => 'showcase',
    ];

    /**
     * Filtre les réponses reçues sur l'allowlist (fail-closed) : question
     * inconnue rejetée, valeur inconnue rejetée, `null` = sautée.
     *
     * @param  array<array-key, mixed>  $raw
     * @return array{answers: array<string, mixed>, rejected: list<string>}
     */
    public function sanitize(array $raw): array
    {
        $answers = [];
        $rejected = [];

        foreach ($raw as $question => $value) {
            if (! is_string($question) || ! array_key_exists($question, self::QUESTIONS)) {
                $rejected[] = is_string($question) ? $question : '(non-string)';

                continue;
            }

            if ($value === null) {
                $answers[$question] = null; // question explicitement sautée

                continue;
            }

            // #7853 — texte libre borné (fail-closed : non-chaîne, trop court
            // ou trop long → rejeté, aucune écriture partielle).
            if (in_array($question, self::FREE_TEXT, true)) {
                if (! is_string($value)) {
                    $rejected[] = $question;

                    continue;
                }

                $trimmed = trim($value);
                if (mb_strlen($trimmed) < 2 || mb_strlen($trimmed) > self::FREE_TEXT_MAX) {
                    $rejected[] = $question;

                    continue;
                }

                $answers[$question] = $trimmed;

                continue;
            }

            if (in_array($question, self::MULTI_CHOICE, true)) {
                if (! is_array($value)) {
                    $rejected[] = $question;

                    continue;
                }

                $clean = array_values(array_unique(array_filter(
                    $value,
                    fn ($item): bool => is_string($item) && in_array($item, self::QUESTIONS[$question], true)
                )));
                $answers[$question] = $clean;

                continue;
            }

            if (! is_string($value) || ! in_array($value, self::QUESTIONS[$question], true)) {
                $rejected[] = $question;

                continue;
            }

            $answers[$question] = $value;
        }

        return ['answers' => $answers, 'rejected' => $rejected];
    }

    /**
     * Réponses → plan d'activation déterministe.
     *
     * @param  array<string, mixed>  $answers
     * @return array{solutions: list<string>, tools: list<string>}
     */
    public function plan(array $answers): array
    {
        $solutions = [];
        $tools = [];

        $solo = ($answers['company_type'] ?? null) === 'solo';
        $team = ! $solo && (
            ($answers['company_type'] ?? null) === 'team'
            || is_string($answers['team_size'] ?? null)
        );

        // Verticale sectorielle (restaurant, station-service, éducation, voyage).
        $sector = $answers['sector'] ?? null;
        if (is_string($sector) && isset(self::SECTOR_SOLUTIONS[$sector])) {
            $solutions[] = self::SECTOR_SOLUTIONS[$sector];
        }

        // Une équipe à faire pointer → gestion des employés + présence.
        if ($team) {
            $tools[] = 'employees';
            $tools[] = 'attendance';
        }

        // Horaires planifiés → la présence est indispensable (le module
        // Planning vit derrière `attendance` côté catalogue client).
        if ($team && ($answers['scheduled_hours'] ?? null) === 'yes') {
            $tools[] = 'attendance';
        }

        // Priorités déclarées (multi-choix).
        $priorities = $answers['priorities'] ?? [];
        if (is_array($priorities)) {
            foreach ($priorities as $priority) {
                if (is_string($priority) && isset(self::PRIORITY_TOOLS[$priority])) {
                    $tools[] = self::PRIORITY_TOOLS[$priority];
                }
            }
        }

        // Plancher solo (#7423) : jamais d'outil d'ÉQUIPE hors plancher pour
        // un indépendant — même règle que `CompanyModuleController::activate`.
        if ($solo) {
            $tools = array_values(array_filter(
                $tools,
                static fn (string $tool): bool => ! in_array($tool, Company::TEAM_TOOLS, true)
                    || Company::isSoloFloorTool($tool)
            ));
        }

        // Ceinture et bretelles : ne sortent que des clés de l'allowlist
        // (intersection avec `Company::HORIZONTAL_TOOLS` — no-op tant que les
        // sources ci-dessus restent des sous-ensembles de l'allowlist, mais
        // garde runtime si une future clé en sortait).
        $tools = array_values(array_intersect(array_unique($tools), Company::HORIZONTAL_TOOLS));

        return [
            'solutions' => array_values(array_unique($solutions)),
            'tools' => $tools,
        ];
    }
}
