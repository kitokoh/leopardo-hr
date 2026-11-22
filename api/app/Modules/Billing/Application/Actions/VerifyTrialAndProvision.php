<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\AuthService;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Application\Services\TrialEmailHash;
use Illuminate\Contracts\Translation\Translator;
use Psr\Log\LoggerInterface;

/**
 * Verification OTP et provisioning de l'essai (#9732).
 *
 * Cas d'usage extrait du `SelfServiceTrialController::verify()` : la methode
 * faisait la validation, le provisioning, l'auto-connexion et la serialisation
 * dans 125 lignes.
 *
 * ## Regles conservees
 *
 * 1. **#2903** : le parcours d'essai guide ne doit JAMAIS exposer un 500 brut
 *    (c'etait le cas en prod v4.23.5 sur le verify) — toute exception
 *    inattendue du provisioning est convertie en resultat `unavailable`.
 * 2. **Auto-connexion** (retour fondateur 2026-09-13) : l'utilisateur vient de
 *    prouver la possession de sa boite mail et n'a JAMAIS choisi de mot de
 *    passe. On reutilise le chemin de connexion EPROUVE (`AuthService::login`)
 *    plutot que de forger un jeton : il construit les abilities multi-tenant
 *    sans lesquelles le dashboard est inaccessible, applique la purge de quota
 *    de jetons (#7009) et positionne `last_login_at`. Toute erreur est
 *    absorbee : le compte EST provisionne, on degrade vers le parcours manuel.
 */
final class VerifyTrialAndProvision
{
    public function __construct(
        private readonly VerifyTrialSignup $verifyTrialSignup,
        private readonly AuthService $authService,
        private readonly Translator $translator,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array{success: true, company: Company, manager_email: string, first_name: string, last_name: string, token: string|null}
     *                                                                                                                                  |array{success: false, error: string, message: string, status: int}
     */
    public function execute(string $email, string $code): array
    {
        $email = strtolower(trim($email));

        try {
            /** @var array{success: true, company: Company, manager: Employee, manager_email: string, first_name: string, last_name: string, temp_password: string}|array{success: false, error: string, message: string, status: int} $result */
            $result = $this->verifyTrialSignup->execute($email, $code);
        } catch (\Throwable $e) {
            $this->logger->error('trial.verify.unexpected', [
                'email_hash' => TrialEmailHash::of($email),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'TRIAL_VERIFY_UNAVAILABLE',
                'message' => $this->translator->get('errors.VERIFICATION_TEMPORARILY_UNAVAILABLE'),
                'status' => 503,
            ];
        }

        if ($result['success'] === false) {
            return $result;
        }

        // Auto-connexion : toute erreur est absorbee, le compte EST provisionne.
        $autoLoginToken = null;

        try {
            /** @var array{token: string} $authResult */
            $authResult = $this->authService->login($email, $result['temp_password'], 'trial-self-service');
            $autoLoginToken = $authResult['token'];
        } catch (\Throwable $e) {
            $this->logger->warning('trial.verify.autologin_failed', [
                'email_hash' => TrialEmailHash::of($email),
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'success' => true,
            'company' => $result['company'],
            'manager_email' => $result['manager_email'],
            'first_name' => $result['first_name'],
            'last_name' => $result['last_name'],
            'token' => $autoLoginToken,
        ];
    }
}
