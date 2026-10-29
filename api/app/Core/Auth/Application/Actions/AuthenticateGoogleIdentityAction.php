<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Domain\Exceptions\TwoFactorException;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\TwoFactorAuthService;
use App\Exceptions\AccountSuspendedException;
use Psr\Log\LoggerInterface;

/**
 * Use Case : Authentifier une identité Google DÉJÀ résolue vers un employé.
 *
 * Issue #7962 — les deux flux SSO (`handleGoogleCallback` et
 * `handleGoogleToken`) portaient la MÊME séquence de quatre gardes, recopiée
 * mot pour mot. Ce n'était pas seulement de la longueur de contrôleur : deux
 * copies d'une règle de sécurité divergent à la première correction appliquée
 * d'un seul côté. La classe de défaut est documentée dans l'historique du
 * fichier (#6531 liaison du sub, #5579 garde 2FA, #6540 tenant_schema, #2630
 * statut) — chaque fois, la même garde a dû être posée deux fois.
 *
 * La séquence, désormais unique :
 *   1. liaison `google_id` (sub) à la première connexion ;
 *   2. refus si le sub présenté diffère de celui déjà lié (réattribution
 *      d'email Workspace, compte Google compromis) ;
 *   3. refus d'un employé suspendu, d'une société suspendue ou expirée ;
 *   4. challenge TOTP si la 2FA est enrôlée, sinon blocage par politique
 *      tenant (`requiresMfa`).
 *
 * Le cas d'usage ne émet AUCUN token : il rend la décision. Le contrôleur
 * reste maître de la sérialisation HTTP (et de la façon dont il présente le
 * challenge selon le support — cookie pour le web, JSON pour le mobile).
 *
 * @phpstan-type SsoOutcome array{
 *     status: 'authenticated'|'identity_mismatch'|'mfa_challenge',
 *     employee: Employee,
 *     token_name: string,
 *     challenge: array{token: string, expires_in: int}|null,
 * }
 */
final class AuthenticateGoogleIdentityAction
{
    public function __construct(
        private readonly TwoFactorAuthService $twoFactorService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param  array{employee: Employee, tenant_schema: ?string}|null  $resolved
     *                                                                            Résolution BORNÉE au tenant produite par
     *                                                                            `AuthService::resolveEmployeeByEmail()` (#6531) — jamais une
     *                                                                            recherche cross-tenant par email seul. `null` quand l'employé
     *                                                                            vient d'être créé par le tunnel de démo (aucune résolution).
     * @param  string|null  $rememberCookie  Cookie signé `mfa_remember_{id}`
     *                                       quand le support en dispose (web) ; `null` pour le mobile, où le
     *                                       challenge est alors systématique.
     * @param  string|null  $challengeDeviceName  Nom d'appareil porté par le
     *                                            challenge (le mobile transmet `device_name`, le web passe `null`).
     * @return array{
     *     status: 'authenticated'|'identity_mismatch'|'mfa_challenge',
     *     employee: Employee,
     *     token_name: string,
     *     challenge: array{token: string, expires_in: int}|null,
     * }
     *
     * @throws AccountSuspendedException employé ou société suspendue/expirée (#2630)
     * @throws TwoFactorException rôle sensible sans 2FA (politique tenant)
     */
    public function execute(
        Employee $employee,
        string $googleSub,
        ?array $resolved,
        string $tokenName,
        ?string $rememberCookie = null,
        ?string $challengeDeviceName = null,
    ): array {
        // audit(securite) #6531 — liaison du sub à la première connexion ;
        // connexion suivante avec un sub différent : REFUS.
        if ($employee->google_id === null || $employee->google_id === '') {
            $employee->forceFill(['google_id' => $googleSub])->save();
            $this->logger->info('auth.google.linked', [
                'employee_id' => $employee->id,
                'email' => $employee->email,
            ]);
        } elseif (! hash_equals($employee->google_id, $googleSub)) {
            $this->logger->warning('auth.google.identity_mismatch', [
                'employee_id' => $employee->id,
                'email' => $employee->email,
            ]);

            return [
                'status' => 'identity_mismatch',
                'employee' => $employee,
                'token_name' => $tokenName,
                'challenge' => null,
            ];
        }

        // Sécurité #2630 — ni un employé suspendu ni une société
        // suspendue/expirée ne peuvent s'authentifier, y compris via Google.
        if ($employee->status !== 'active') {
            throw new AccountSuspendedException;
        }
        if ($employee->company_id) {
            $company = $employee->company;
            if ($company && in_array($company->status, ['suspended', 'expired'], true)) {
                throw new AccountSuspendedException;
            }
        }

        // Issue #5579 — garde 2FA sur les flux SSO : sans elle, un employé
        // ayant activé la TOTP contournait le challenge via Google.
        if ($employee->two_fa_enabled_at !== null) {
            $trusted = $this->isTrustedDevice($employee, $rememberCookie);
            if (! $trusted) {
                $challenge = $this->twoFactorService->issueChallenge([
                    'employee_id' => $employee->id,
                    'company_id' => (string) $employee->company_id,
                    // audit(securite) #6540 — tenant_schema résolu : en mode
                    // schéma-par-tenant, un challenge à null ne peut pas
                    // re-poser le search_path à la vérification (2FA cassée).
                    'tenant_schema' => $resolved['tenant_schema'] ?? null,
                    'email' => (string) $employee->email,
                    'device_name' => $challengeDeviceName,
                ]);

                return [
                    'status' => 'mfa_challenge',
                    'employee' => $employee,
                    'token_name' => $tokenName,
                    'challenge' => $challenge,
                ];
            }
        }

        // Politique tenant : rôle sensible + 2FA non activée → blocage.
        if ($this->twoFactorService->requiresMfa($employee)) {
            throw TwoFactorException::required();
        }

        return [
            'status' => 'authenticated',
            'employee' => $employee,
            'token_name' => $tokenName,
            'challenge' => null,
        ];
    }

    /**
     * Appareil de confiance : cookie signé `mfa_remember_{id}` (web seulement).
     * Le mobile ne porte pas de cookie — le challenge y est donc systématique,
     * comportement volontairement inchangé (#5579).
     */
    private function isTrustedDevice(Employee $employee, ?string $rememberCookie): bool
    {
        if ($rememberCookie === null) {
            return false;
        }

        $expected = $this->twoFactorService->rememberCookieValue($employee);

        return hash_equals($expected, $rememberCookie);
    }
}
