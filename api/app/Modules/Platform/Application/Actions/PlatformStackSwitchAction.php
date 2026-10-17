<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Platform\Infrastructure\Services\StackRegistryService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * #8673 (épic #8670) — déclenche la bascule d'un tier vers une autre stack.
 *
 * Le bouton superadmin ne bascule PAS lui-même : il déclenche l'événement
 * GitHub `stack-switch`, que `.github/workflows/stack-switch-dispatch.yml`
 * route vers `.github/workflows/stack-switch.yml`. Raisons de ce détour :
 *
 *  - le workflow détient les secrets Render/Vercel nécessaires et exécute
 *    `scripts/stack-switch.sh`, déjà testé (garde-fous de données prod : même
 *    base, jamais de reseed) ;
 *  - la bascule devient traçable dans l'onglet Actions (qui a lancé quoi, avec
 *    quel résultat) au lieu d'un état caché côté API ;
 *  - l'API n'a pas besoin des clés Render pour changer le pointeur de stack :
 *    elle n'a qu'un jeton GitHub à portée minimale.
 *
 * Sécurité : jeton absent → refus explicite (fail-closed), jamais d'appel
 * anonyme. Le jeton et la réponse GitHub ne sont JAMAIS journalisés.
 */
class PlatformStackSwitchAction
{
    /** Événement `repository_dispatch` attendu par stack-switch-dispatch.yml. */
    public const DISPATCH_EVENT = 'stack-switch';

    public function __construct(
        private readonly StackRegistryService $registry,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     reason: string,
     *     tier: string,
     *     stack: string,
     *     previous: string,
     *     dispatch_id: int|null,
     *     message: string
     * }
     *
     * @throws RuntimeException quand le jeton GitHub est absent (fail-closed).
     */
    public function execute(string $tier, string $stack, ?int $actorId = null): array
    {
        $validation = $this->registry->validateSwitch($tier, $stack);

        if (! $validation['ok']) {
            return [
                'ok' => false,
                'reason' => $validation['reason'],
                'tier' => $tier,
                'stack' => $stack,
                'previous' => $validation['active'],
                'dispatch_id' => null,
                'message' => $this->messageFor($validation['reason']),
            ];
        }

        $token = (string) config('services.github_stack_switch.token', '');
        $repository = (string) config('services.github_stack_switch.repository', 'kitokoh/leopardo-hr');

        if ($token === '') {
            // Fail-closed : sans jeton, on n'appelle rien. Cette exception est
            // traduite en 503 par le contrôleur — jamais en bascule silencieuse.
            throw new RuntimeException('GITHUB_STACK_SWITCH_TOKEN absent : bascule indisponible.');
        }

        $response = Http::withToken($token)
            ->accept('application/json')
            ->timeout(15)
            ->post("https://api.github.com/repos/{$repository}/dispatches", [
                'event_type' => self::DISPATCH_EVENT,
                'client_payload' => [
                    'tier' => $tier,
                    'stack' => $stack,
                    'source' => 'superadmin',
                    'requested_by' => $actorId,
                    'requested_at' => now()->toIso8601String(),
                ],
            ]);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'reason' => 'dispatch_rejete',
                'tier' => $tier,
                'stack' => $stack,
                'previous' => $validation['active'],
                // Le corps d'erreur GitHub est volontairement réduit : il peut
                // contenir des indices sur le compte et n'apporte rien à l'opérateur.
                'dispatch_id' => null,
                'message' => __('platform.infra_dispatch_rejected', ['status' => $response->status()]),
            ];
        }

        // 204 attendu (repository_dispatch répond No Content) ; on renvoie
        // quand même l'identifiant de corrélation pour que l'écran puisse
        // pointer vers l'onglet Actions.
        AuditLog::record(
            module: 'platform',
            action: 'infra.stack_switch.dispatched',
            subject: null,
            actor: null,
            oldValues: ['active_stack' => $validation['active']],
            newValues: ['tier' => $tier, 'stack' => $stack, 'dispatch_event' => self::DISPATCH_EVENT],
            metadata: [
                'requested_by' => $actorId,
                'repository' => $repository,
            ],
        );

        return [
            'ok' => true,
            'reason' => '',
            'tier' => $tier,
            'stack' => $stack,
            'previous' => $validation['active'],
            'dispatch_id' => $response->status() === 204 ? null : $response->json('id'),
            'message' => __('platform.infra_switch_dispatched'),
        ];
    }

    private function messageFor(string $reason): string
    {
        return match ($reason) {
            'deja_active' => __('platform.infra_stack_already_active'),
            'non_basculable' => __('platform.infra_stack_not_switchable'),
            'stack_inconnue' => __('platform.infra_stack_unknown'),
            'tier_inconnu' => __('platform.infra_tier_unknown'),
            default => __('platform.infra_switch_refused'),
        };
    }
}
