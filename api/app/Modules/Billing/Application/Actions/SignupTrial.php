<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Modules\Billing\Application\Services\TrialEmailHash;
use App\Modules\Billing\Infrastructure\Services\TrialProvisioningRowService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

/**
 * Inscription a l'essai : orchestration du parcours (#9732).
 *
 * Cas d'usage extrait du `SelfServiceTrialController::handleSignup()` — 270
 * lignes melangeant validation, normalisation, anti-enumeration, choix du
 * parcours et serialisation. Le controleur ne garde que le HTTP (idempotence
 * de l'en-tete, garde de session, serialisation de la reponse).
 *
 * ## Regles metier conservees a l'identique
 *
 * 1. **BOS-009 (#8227)** : `solution` (singulier) etait valide puis JETE. Le
 *    wizard vitrine envoie `solution: "travelagency"` et seul `solutions`
 *    (pluriel) partait au provisioning — le tenant naissait sans sa verticale,
 *    sans aucune erreur. La normalisation se fait ici, en un seul point, pour
 *    que les DEUX parcours et le `signup_payload` persiste voient la meme liste.
 * 2. **#7853** : nom d'entreprise resolu UNE fois pour les deux parcours.
 * 3. **Anti-enumeration (#3945)** : la reponse est uniforme que l'email ait
 *    deja un compte manager ou non ; l'existence est seulement journalisee.
 * 4. **#4866 / #4949** : un echec d'ecriture n'est pas un 422 mais ne doit
 *    JAMAIS devenir un 500 brut — on rend un resultat `unavailable`.
 * 5. **#9305 / #9323** : une panne du transport e-mail bascule sur l'activation
 *    directe, synchrone, sans dependre d'un worker.
 */
final class SignupTrial
{
    public function __construct(
        private readonly RequestTrialSignup $requestTrialSignup,
        private readonly ProvisionTrialSandbox $provisionTrialSandbox,
        private readonly ActivateTrialInRequest $activateTrialInRequest,
        private readonly TrialProvisioningRowService $provisioningRows,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array{outcome: string, data?: array<string, mixed>}
     *                                                             outcome ∈ {sandbox, direct_activation, otp_sent, unavailable}
     */
    public function execute(array $validated): array
    {
        $email = strtolower(trim((string) $validated['email']));

        // BOS-009 (#8227) — normalisation `solution` + `solutions` en un point.
        $requestedSolutions = $validated['solutions'] ?? [];
        if (($validated['solution'] ?? null) !== null) {
            $requestedSolutions[] = $validated['solution'];
        }
        $validated['solutions'] = array_values(array_unique(array_map(
            static fn (string $code): string => strtolower(trim($code)),
            $requestedSolutions
        )));

        // #7853 — nom d'entreprise resolu UNE fois pour les deux parcours.
        $companyName = $this->requestTrialSignup->resolveCompanyName($validated, $email);
        $validated['company'] = $companyName;

        // Anti-enumeration (#3945) : l'existence d'un manager est purement
        // informative ici, la reponse reste uniforme. Issue #4949 : une erreur
        // DB/schema ne doit jamais produire un 500 brut.
        $existingManager = null;
        try {
            $existingManager = $this->requestTrialSignup->findExistingManager($email);
            if ($existingManager) {
                // BOS-002 (#8144) — jamais d'email en clair dans les logs.
                $this->logger->info('trial.signup_duplicate_email_uniform_response', [
                    'email_hash' => TrialEmailHash::of($email),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('trial.signup_duplicate_check_failed', [
                'email_hash' => TrialEmailHash::of($email),
                'error' => $e->getMessage(),
            ]);
        }

        if (($validated['requestedWorkflow'] ?? '') === 'guided_trial') {
            return [
                'outcome' => 'sandbox',
                'data' => $this->provisionTrialSandbox->execute($email, $companyName, $validated, $existingManager),
            ];
        }

        // Issue #4866 : le chemin legacy (sans requestedWorkflow ou
        // self_service) echouait en 500 INTERNAL_ERROR quand la creation de la
        // CompanyRequest echouait — un echec d'ecriture n'est pas une erreur
        // client a 422, mais il ne doit JAMAIS devenir un 500 brut.
        try {
            $sent = $this->requestTrialSignup->execute($validated);
        } catch (\Throwable $e) {
            $this->logger->error('trial.signup_legacy_failed', [
                'email_hash' => TrialEmailHash::of($email),
                'error' => $e->getMessage(),
            ]);

            return ['outcome' => 'unavailable'];
        }

        // Issue #3057 / #4949 : `execute()` retourne `false` quand l'envoi de
        // l'email OTP a echoue (mailer KO) — on ne repond JAMAIS « code envoye »
        // pour un code jamais parti.
        if (! $sent) {
            // #9305 / #9323 — bascule serveur sur l'activation directe,
            // synchrone, sans dependre du mailer ni d'un worker.
            $this->logger->warning('trial.signup_otp_unavailable_direct_activation', [
                'email_hash' => TrialEmailHash::of($email),
                'requested_workflow' => $validated['requestedWorkflow'] ?? null,
                'password_provided' => filled($validated['password'] ?? null),
            ]);

            return [
                'outcome' => 'direct_activation',
                'data' => $this->activateTrialInRequest->execute($email, $companyName, $validated, $existingManager),
            ];
        }

        // Issue #2437 (parite guide) : le parcours self-service doit lui aussi
        // emettre un `provisioning_token`, sinon /trial/status et
        // /trial/set-password repondent 404 pour TOUT prospect self-service.
        try {
            $provisioningToken = $this->provisioningRows->createPending(
                $email,
                (string) $validated['company'],
                (string) $validated['country'],
                withLockTimeout: true,
            );
        } catch (QueryException $e) {
            // Un POST concurrent a deja cree la ligne pending pour cet email
            // (index partiel unique #3951) : on reutilise la ligne gagnante.
            if ($e->getCode() === '23505') {
                $winnerToken = $this->provisioningRows->findPendingToken($email);

                if ($winnerToken === null) {
                    $this->logger->warning('trial.self_service.provisioning_row_race_unresolved', [
                        'email_hash' => TrialEmailHash::of($email),
                    ]);
                }

                $provisioningToken = $winnerToken ?? Str::random(64);
            } else {
                // Jamais bloquant pour l'essai : l'OTP reste le chemin nominal.
                $this->logger->error('trial.self_service.provisioning_row_failed', [
                    'email_hash' => TrialEmailHash::of($email),
                    'error' => $e->getMessage(),
                ]);
                $provisioningToken = Str::random(64);
            }
        }

        return [
            'outcome' => 'otp_sent',
            'data' => [
                'email' => $email,
                'status' => 'pending_verification',
                'workflow' => 'self_service',
                'otp_sent' => true,
                'provisioning_token' => $provisioningToken,
            ],
        ];
    }
}
