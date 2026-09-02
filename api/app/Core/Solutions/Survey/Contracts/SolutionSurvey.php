<?php

declare(strict_types=1);

namespace App\Core\Solutions\Survey\Contracts;

use App\Core\Solutions\Survey\SolutionSurveyEngine;

/**
 * Questionnaire de pré-qualification d'une solution sectorielle.
 *
 * Une solution (FuelStation, Restaurant, EduManager…) expose :
 *  - des questions (affichées par la vitrine web) ;
 *  - un catalogue de « packages » suggérables (module, app mobile,
 *    dispositif, Edge…) ;
 *  - des règles de suggestion : à partir des réponses, le moteur
 *    {@see SolutionSurveyEngine} détermine
 *    les packages pertinents, chacun avec une raison lisible.
 *
 * Le moteur est volontairement DÉTERMINISTE (règles pures en PHP, aucun
 * appel réseau, aucune IA) : gratuit, testable, et suffisant pour une v1.
 * Un modèle IA (Ollama self-host) pourrait plus tard affiner le tri des
 * packages sans changer le contrat.
 *
 * @see docs/architecture/RESTAURANT_SOLUTION_SURVEY.md
 */
interface SolutionSurvey
{
    /** Code de solution — DOIT correspondre au code du SolutionManifest (ex. `restaurant`). */
    public function code(): string;

    /** Nom lisible de la solution (affiché dans le PDF/la vitrine). */
    public function name(): string;

    /**
     * Questions du questionnaire.
     *
     * @return list<array{
     *   key: string,
     *   type: 'select'|'bool'|'multi',
     *   label_key: string,
     *   options?: list<array{value: string, label_key: string}>,
     *   default?: string|bool
     * }>
     */
    public function questions(): array;

    /**
     * Catalogue des packages suggérables par cette solution.
     *
     * @return array<string, array{
     *   key: string,
     *   type: 'mobile'|'module'|'device'|'edge',
     *   label_key: string,
     *   app?: string,
     *   download?: 'apk'|'edge_install'|'guide'|null,
     *   required?: bool
     * }>
     */
    public function packages(): array;

    /**
     * Règles de suggestion — évaluées dans l'ordre, première règle gagnante
     * par package (les règles de même package sont fusionnées).
     *
     * @return list<array{
     *   package: string,
     *   priority: int,
     *   when: callable(array<string, mixed>): bool,
     *   reason_key: string
     * }>
     */
    public function rules(): array;
}
