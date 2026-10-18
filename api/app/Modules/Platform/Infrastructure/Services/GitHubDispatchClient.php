<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * #8673 (épic #8670) — client GitHub minimal pour le bouton de bascule.
 *
 * Vit dans Infrastructure parce qu'il parle HTTP : la couche Application ne
 * doit pas importer de facade Laravel (garde de pureté de couches, issue
 * #6568). `PlatformStackSwitchAction` décide, ce client exécute.
 *
 * Portée du jeton attendue : fine-grained, limité à ce dépôt, `Contents: read`
 * + `Actions: write`. Aucun accès aux secrets du dépôt n'est nécessaire.
 */
class GitHubDispatchClient
{
    public const EVENT_STACK_SWITCH = 'stack-switch';

    /**
     * Émet l'événement `stack-switch` avec un payload construit ici même.
     *
     * Le contrat de l'événement appartient au client : les appelants passent
     * des scalaires, jamais un tableau libre. Cela évite les tableaux à forme
     * ouverte (PHPStan niveau 8) tout en gardant un seul endroit à changer si
     * le workflow attend un champ de plus.
     *
     * Lève RuntimeException si le jeton est absent (fail-closed) : le
     * contrôleur le traduit en 503, jamais en bascule silencieuse.
     */
    public function dispatchStackSwitch(
        string $tier,
        string $stack,
        ?int $requestedBy,
        string $requestedAt,
    ): DispatchOutcome {
        $token = (string) config('services.github_stack_switch.token', '');
        $repository = (string) config('services.github_stack_switch.repository', 'kitokoh/leopardo-hr');

        if ($token === '') {
            throw new RuntimeException('GITHUB_STACK_SWITCH_TOKEN absent.');
        }

        $response = Http::withToken($token)
            ->accept('application/json')
            ->timeout(15)
            ->post("https://api.github.com/repos/{$repository}/dispatches", [
                'event_type' => self::EVENT_STACK_SWITCH,
                'client_payload' => [
                    'tier' => $tier,
                    'stack' => $stack,
                    'source' => 'superadmin',
                    'requested_by' => $requestedBy,
                    'requested_at' => $requestedAt,
                ],
            ]);

        if (! $response->successful()) {
            return new DispatchOutcome(status: $response->status(), ok: false);
        }

        // 204 attendu (No Content) ; certains proxys renvoient 200 avec un id.
        $body = $response->json();

        return new DispatchOutcome(
            status: $response->status(),
            ok: true,
            id: is_array($body) && isset($body['id']) ? (int) $body['id'] : null,
        );
    }
}
