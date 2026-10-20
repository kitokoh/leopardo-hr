<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Onboarding\Infrastructure\Services\CompanyOnboardingStateWriter;
use InvalidArgumentException;

/**
 * BOS-O-06 / #8627 — reprise du parcours d'onboarding après interruption.
 *
 * Deux notions DISTINCTES, et c'est tout l'objet de cette action :
 *
 *  - `current_step`  : où l'utilisateur se trouve MAINTENANT. Il peut RECULER
 *                      (revenir corriger une étape précédente) ;
 *  - `furthest_step` : le plus haut point ATTEINT. Il est **monotone** : il ne
 *                      décrémente jamais. C'est cette valeur — et non la
 *                      position courante — qui garantit qu'une étape déjà
 *                      validée n'est pas rejouée au retour.
 *
 * L'état est lu côté SERVEUR (`public.companies.onboarding_state`) : le front
 * reprend à l'étape confirmée par le serveur, jamais à celle mémorisée par le
 * navigateur — un `localStorage` falsifié ou simplement périmé (autre appareil)
 * ne peut donc ni ouvrir une étape non autorisée, ni faire reculer la
 * progression persistée.
 *
 * Portée assumée (ADR-05) : l'état est POST-tenant. Aucune reprise n'existe
 * avant la vérification de l'OTP — il n'y a pas de brouillon pré-tenant, donc
 * aucune donnée personnelle n'est conservée hors société. Le contrat le dit
 * explicitement au client (`not_before_verification`) plutôt que de laisser
 * croire à une reprise possible dès la première page d'inscription.
 */
final class ResumeOnboardingStep
{
    public function __construct(
        private readonly CompanyOnboardingStateWriter $writer,
    ) {}

    /**
     * État normalisé exposé au client.
     *
     * @param  list<array{step_key: string, order: int, status: string}>  $steps
     * @return array{
     *     current_step: string|null,
     *     current_step_order: int|null,
     *     furthest_step: string|null,
     *     furthest_step_order: int|null,
     *     total_steps: int,
     *     completed_steps: int,
     *     next_step: string|null,
     *     resumed: bool,
     *     updated_at: string|null,
     *     not_before_verification: bool
     * }
     */
    public function stateOf(Company $company, array $steps): array
    {
        $state = $this->normalize($company);

        $currentOrder = $state['current_step_order'];

        $nextStep = null;
        $completed = 0;
        foreach ($steps as $step) {
            if ($step['status'] !== 'pending') {
                $completed++;

                continue;
            }

            if ($nextStep === null) {
                $nextStep = $step['step_key'];
            }
        }

        // `resumed` = l'état serveur désigne une étape qui n'est pas la
        // première : l'utilisateur revient sur un parcours interrompu, et le
        // front doit reprendre là — pas à zéro.
        $resumed = $currentOrder !== null && $currentOrder > 1;

        return [
            'current_step' => $state['current_step'],
            'current_step_order' => $currentOrder,
            'furthest_step' => $state['furthest_step'],
            'furthest_step_order' => $state['furthest_step_order'],
            'total_steps' => count($steps),
            'completed_steps' => $completed,
            'next_step' => $nextStep,
            'resumed' => $resumed,
            'updated_at' => $state['updated_at'],
            'not_before_verification' => true,
        ];
    }

    /**
     * Positionne l'étape courante. Idempotent : rejouer la même étape renvoie
     * exactement le même état sans écriture (`changed: false`).
     *
     * Le retour en arrière est autorisé sur `current_step` mais `furthest_step`
     * ne recule jamais — c'est la différence entre « je corrige l'étape 2 » et
     * « j'ai oublié que j'avais déjà atteint l'étape 4 ».
     *
     * @param  list<array{step_key: string, order: int, status: string}>  $steps
     * @return array{changed: bool, state: array<string, mixed>}
     */
    public function execute(Company $company, string $stepKey, array $steps): array
    {
        $target = null;
        foreach ($steps as $step) {
            if ($step['step_key'] === $stepKey) {
                $target = $step;
                break;
            }
        }

        // L'appelant a déjà validé la clé ; la garde reste ici pour que
        // l'action soit sûre même réutilisée ailleurs (fail-closed).
        if ($target === null) {
            throw new InvalidArgumentException('unknown_step: '.$stepKey);
        }

        $previous = $this->normalize($company);
        $targetOrder = $target['order'];

        $furthestOrder = $previous['furthest_step_order'];
        $furthestStep = $previous['furthest_step'];

        if ($furthestOrder === null || $targetOrder > $furthestOrder) {
            $furthestOrder = $targetOrder;
            $furthestStep = $target['step_key'];
        }

        $unchanged = $previous['current_step'] === $target['step_key']
            && $previous['furthest_step'] === $furthestStep;

        $state = [
            'current_step' => $target['step_key'],
            'current_step_order' => $targetOrder,
            'furthest_step' => $furthestStep,
            'furthest_step_order' => $furthestOrder,
        ];

        if (! $unchanged) {
            // L'horodatage est posé par l'écrivain d'Infrastructure : la couche
            // Application ne touche pas aux helpers de framework.
            $state = $this->writer->persist((string) $company->id, $state);
        }

        return [
            'changed' => ! $unchanged,
            'state' => $this->stateOf($this->withState($company, $state), $steps),
        ];
    }

    /**
     * Lit l'état persisté. Toute forme invalide (valeur non scalaire, ordre
     * non entier) est ramenée à `null` — on ne répare jamais silencieusement un
     * état corrompu en inventant une progression.
     *
     * @return array{current_step: string|null, current_step_order: int|null, furthest_step: string|null, furthest_step_order: int|null, updated_at: string|null}
     */
    private function normalize(Company $company): array
    {
        $raw = $company->onboarding_state;
        $state = is_array($raw) ? $raw : [];

        return [
            'current_step' => $this->stringOrNull($state['current_step'] ?? null),
            'current_step_order' => $this->intOrNull($state['current_step_order'] ?? null),
            'furthest_step' => $this->stringOrNull($state['furthest_step'] ?? null),
            'furthest_step_order' => $this->intOrNull($state['furthest_step_order'] ?? null),
            'updated_at' => $this->stringOrNull($state['updated_at'] ?? null),
        ];
    }

    /**
     * Instance portant l'état qui vient d'être écrit, pour que `read()` renvoie
     * la valeur persistée sans relire la base dans la même requête.
     *
     * @param  array<string, mixed>  $state
     */
    private function withState(Company $company, array $state): Company
    {
        $company->setAttribute('onboarding_state', $state);

        return $company;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
