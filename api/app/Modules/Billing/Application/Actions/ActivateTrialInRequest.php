<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Billing\Application\Services\TrialEmailHash;
use App\Modules\Billing\Infrastructure\Services\TrialProvisioningRowService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

/**
 * Ouvre l'espace d'essai DANS la requete, sans e-mail ni worker (#9732).
 *
 * Cas d'usage extrait du `SelfServiceTrialController::activateDirectly()`.
 * C'est le seul chemin qui garantit qu'un responsable obtient un espace meme
 * quand le transport e-mail est hors service ET qu'aucun worker ne consomme la
 * file (#9323). Le provisioning est synchrone : soit l'espace existe et la
 * reponse le prouve, soit on repond 503 — jamais de faux succes (lecon #9308).
 *
 * ## Politique d'erreur, conservee telle quelle
 *
 * L'Action ne fabrique pas de reponse HTTP : elle rend un resultat structure
 * et laisse l'appelant decider du statut. C'est la regle du depot — une Action
 * ne connait pas HTTP.
 */
final class ActivateTrialInRequest
{
    public function __construct(
        private readonly ActivateTrialDirectly $activateTrialDirectly,
        private readonly TrialProvisioningRowService $provisioningRows,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array{success: true, data: array<string, mixed>}|array{success: false, error: string}
     */
    public function execute(
        string $email,
        string $companyName,
        array $validated,
        ?Employee $existingManager,
    ): array {
        // Anti-enumeration (#3945) : un e-mail deja manager recoit la meme
        // reponse uniforme qu'un nouveau prospect, sans second provisioning.
        if ($existingManager) {
            return [
                'success' => true,
                'data' => [
                    'email' => $email,
                    'status' => 'provisioning_sandbox',
                    'workflow' => 'direct_activation',
                    'otp_sent' => false,
                    'provisioning_token' => Str::random(64),
                ],
            ];
        }

        // Idempotence (#3951) : un double POST reutilise la ligne pending
        // existante au lieu de creer un second tenant.
        $provisioningToken = $this->provisioningRows->findPendingToken($email);

        if ($provisioningToken === null) {
            try {
                $provisioningToken = $this->provisioningRows->createPending(
                    $email,
                    (string) $validated['company'],
                    (string) $validated['country'],
                );
            } catch (QueryException $e) {
                // Course entre le check et l'insert (index partiel unique
                // #3951) : on reutilise la ligne gagnante, jamais de 500.
                if ($e->getCode() !== '23505') {
                    $this->logger->error('trial.direct_activation_row_failed', [
                        'email_hash' => TrialEmailHash::of($email),
                        'error' => $e->getMessage(),
                    ]);

                    return ['success' => false, 'error' => 'TRIAL_ACTIVATION_UNAVAILABLE'];
                }

                $provisioningToken = $this->provisioningRows->findPendingToken($email);

                if ($provisioningToken === null) {
                    return ['success' => false, 'error' => 'TRIAL_ACTIVATION_UNAVAILABLE'];
                }
            }
        }

        try {
            $result = $this->activateTrialDirectly->execute(
                $email,
                $companyName,
                (string) $validated['country'],
                $provisioningToken,
                $validated['solutions'],
                $validated['company_type'] ?? null,
                $validated['modules'] ?? [],
                $validated['locale'] ?? null,
                $validated['market_type'] ?? null,
                $validated['trade_profile'] ?? null,
                isset($validated['password']) && is_string($validated['password'])
                    ? $validated['password']
                    : null,
            );
        } catch (\Throwable $e) {
            // Aucun faux succes : l'espace n'existe pas, on le dit. La ligne
            // reste `pending` pour que le balayage (#3600) puisse reprendre.
            $this->logger->error('trial.direct_activation_failed', [
                'email_hash' => TrialEmailHash::of($email),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'TRIAL_ACTIVATION_UNAVAILABLE'];
        }

        return [
            'success' => true,
            'data' => [
                'email' => $email,
                'status' => 'ready',
                'workflow' => 'direct_activation',
                'otp_sent' => false,
                'provisioning_token' => $provisioningToken,
                'login_url' => $result['login_url'],
                'password_set' => $result['password_set'],
                // Sans mot de passe choisi, le prospect en definit un via ce
                // lien (meme token) — jamais d'impasse.
                'activation_url' => $result['password_set'] ? null : $result['activation_url'],
            ],
        ];
    }
}
