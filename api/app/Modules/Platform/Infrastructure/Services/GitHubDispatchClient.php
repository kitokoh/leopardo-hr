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

    /*
     * Émet un `repository_dispatch` (204 attendu, No Content).
     *
     * $payload : tableau libre transmis tel quel dans client_payload.
     * Retour : ['status' => int, 'ok' => bool, 'id' => int|null].
     * Lève RuntimeException si le jeton est absent (fail-closed).
     */
    public function dispatch(string $eventType, array $payload): array
    {
        $token = (string) config('services.github_stack_switch.token', '');
        $repository = (string) config('services.github_stack_switch.repository', 'kitokoh/leopardo-hr');

        if ($token === '') {
            throw new RuntimeException('GITHUB_STACK_SWITCH_TOKEN absent.');
        }

        $response = Http::withToken($token)
            ->accept('application/json')
            ->timeout(15)
            ->post("https://api.github.com/repos/{$repository}/dispatches", [
                'event_type' => $eventType,
                'client_payload' => $payload,
            ]);

        $ok = $response->successful();
        $id = null;

        if ($ok) {
            // 204 sans corps ; certains proxys renvoient 200 avec un id.
            $json = $response->json();
            $id = is_array($json) && isset($json['id']) ? (int) $json['id'] : null;
        }

        return ['status' => $response->status(), 'ok' => $ok, 'id' => $id];
    }
}
