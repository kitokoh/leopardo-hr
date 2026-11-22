<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Jobs\ProvisionDemoTenantJob;
use App\Modules\Billing\Application\Services\TrialEmailHash;
use App\Modules\Billing\Infrastructure\Services\TrialProvisioningRowService;
use Illuminate\Database\QueryException;
use Psr\Log\LoggerInterface;

/**
 * Provisionne un espace d'essai SANS dependre du transport e-mail (#9732).
 *
 * Cas d'usage extrait du `SelfServiceTrialController::provisionSandbox()` :
 * parcours `guided_trial`, et repli automatique du `self_service` quand l'OTP
 * ne peut pas partir (#9305).
 *
 * ## Pourquoi une Action et pas une methode privee
 *
 * La methode portait trois regles metier non testables sans monter une requete
 * HTTP complete : l'anti-enumeration (#3945), l'idempotence sur l'index partiel
 * unique (#3951) et la parite de colonnes de la ligne de suivi (#9305). Le
 * controleur ne fait plus que serialiser le resultat.
 *
 * ## Garanties conservees a l'identique
 *
 * 1. **Anti-enumeration** : un e-mail deja manager ne provisionne rien de plus
 *    et recoit une reponse uniforme (token aleatoire, aucun row/job).
 * 2. **Idempotence** : un double POST reutilise la ligne `pending` existante
 *    au lieu de creer un second tenant.
 * 3. **Course sur l'insert** : `23505` (unique_violation) reutilise la ligne
 *    gagnante — jamais de 500.
 */
final class ProvisionTrialSandbox
{
    public function __construct(
        private readonly TrialProvisioningRowService $provisioningRows,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array{email: string, status: string, workflow: string, otp_sent: bool, provisioning_token: string}
     */
    public function execute(
        string $email,
        string $companyName,
        array $validated,
        ?Employee $existingManager,
    ): array {
        if ($existingManager) {
            // Issue #3945 : pas de double provisioning pour un email deja
            // enregistre : reponse uniforme uniquement (token aleatoire,
            // aucun row/job) — l'anti-enumeration prime.
            return $this->payload($email, 'guided_trial', \Illuminate\Support\Str::random(64));
        }

        // Issue #3951 : un double POST (retry reseau, double clic, onglet
        // duplique) ne doit PAS creer 2 lignes pending + 2 jobs -> 2 tenants.
        $existingToken = $this->provisioningRows->findPendingToken($email);

        if ($existingToken !== null) {
            $this->logger->info('trial.signup_existing_pending_reused', [
                'email_hash' => TrialEmailHash::of($email),
            ]);

            return $this->payload($email, 'guided_trial', $existingToken);
        }

        // MULTI-PAYS (#1950) : le pays valide du signup est transmis au job
        // (plus de fallback silencieux DZ — invariant 10 de la spec).
        // #2437 : un provisioning_token est cree pour permettre au prospect de
        // poller GET /trial/status sans exposer l'email brut.
        try {
            // #9305 — parite avec le parcours verifie : la ligne de suivi porte
            // le nom et le pays declares, sinon l'ecran de statut ne peut rien
            // afficher d'utile au responsable.
            $provisioningToken = $this->provisioningRows->createPending(
                $email,
                (string) $validated['company'],
                (string) $validated['country'],
            );
        } catch (QueryException $e) {
            // Issue #3951 : course entre le check ci-dessus et l'insert — un
            // POST concurrent a gagne (index partiel unique
            // trial_provisionings_pending_email_unique). 23505 = SQLSTATE
            // unique_violation : on recupere la ligne gagnante et on repond son
            // token — jamais de 500.
            if ($e->getCode() === '23505') {
                $this->logger->warning(
                    'Trial provisioning race on email_hash '.TrialEmailHash::of($email).' — reusing winner row.'
                );

                $winnerToken = $this->provisioningRows->findPendingToken($email);

                if ($winnerToken === null) {
                    throw $e;
                }

                return $this->payload($email, 'guided_trial', $winnerToken);
            }

            throw $e;
        }

        ProvisionDemoTenantJob::dispatch(
            $email,
            $companyName,
            $validated['country'],
            $provisioningToken,
            // BOS-009 (#8227) — normalise en amont : la cle existe toujours
            // (liste vide si aucune solution demandee).
            $validated['solutions'],
            // #7235 — profil + outils horizontaux choisis (null/[] = inscription
            // rapide : aucun verrouillage retroactif).
            $validated['company_type'] ?? null,
            $validated['modules'] ?? [],
            // QA onboarding 2026-09-14 : le choix de langue de l'utilisateur
            // etait valide puis jete ici — le tenant naissait dans la langue du
            // pays, pas la sienne.
            $validated['locale'] ?? null,
            // BOS-O-01 / #8625 — l'intention Trade ne doit plus mourir dans le
            // sessionStorage du navigateur.
            $validated['market_type'] ?? null,
            $validated['trade_profile'] ?? null,
        );

        return $this->payload($email, 'guided_trial', $provisioningToken);
    }

    /**
     * @return array{email: string, status: string, workflow: string, otp_sent: bool, provisioning_token: string}
     */
    private function payload(string $email, string $workflow, string $token): array
    {
        return [
            'email' => $email,
            'status' => 'provisioning_sandbox',
            'workflow' => $workflow,
            'otp_sent' => false,
            'provisioning_token' => $token,
        ];
    }
}
