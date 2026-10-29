<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Domain\Exceptions\TwoFactorException;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\AuthService;
use App\Core\Auth\Infrastructure\Services\DemoEmployeeProvisioner;
use App\Core\Auth\Infrastructure\Services\GoogleSsoAuditLogger;
use App\Core\Auth\Infrastructure\Services\TwoFactorAuthService;
use App\Exceptions\AccountSuspendedException;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * #7962 — le tronc commun des deux flux Google (callback web et token mobile).
 *
 * Les deux flux faisaient, dans le contrôleur, la même séquence de six gardes
 * recopiée deux fois : email vérifié, résolution bornée au tenant, liaison
 * `google_id` avec refus en cas de mismatch, statut employé + société, 2FA,
 * politique MFA. Deux copies d'une séquence de sécurité divergent toujours —
 * et ici la divergence serait un contournement d'authentification.
 *
 * Cette Action porte la séquence UNE fois. Les deux flux ne diffèrent plus que
 * par ce qui est réellement différent : le web honore le cookie « appareil de
 * confiance » et peut pré-remplir une inscription, le mobile ne le peut pas.
 *
 * Elle ne délivre pas de token : c'est le rôle de l'appelant, qui seul sait
 * quel nom de token et quel statut HTTP conviennent.
 */
final class ResolveGoogleIdentityAction
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly TwoFactorAuthService $twoFactorService,
        private readonly GoogleSsoAuditLogger $auditLogger,
        private readonly DemoEmployeeProvisioner $demoProvisioner,
    ) {}

    /**
     * @param  string  $flow  `callback` ou `token` — uniquement pour les clés de log.
     * @param  array<int, string>  $rememberCookies  Valeurs des cookies « appareil de confiance » présentés (web seulement).
     * @param  string|null  $deviceName  Nom du token demandé (mobile seulement).
     * @param  bool  $allowDemoProvisioning  Autorise l'auto-création en mode démo (web seulement).
     * @return array{
     *     employee: Employee|null,
     *     tenant_schema: string|null,
     *     mfa_challenge: array{token: string, expires_in: int}|null,
     *     email_not_verified: bool,
     *     identity_mismatch: bool,
     *     created: bool
     * }
     *
     * @throws AccountSuspendedException employé ou société suspendue/expirée
     * @throws TwoFactorException rôle sensible sans 2FA activée
     */
    public function execute(
        SocialiteUser $googleUser,
        string $flow,
        array $rememberCookies = [],
        ?string $deviceName = null,
        bool $allowDemoProvisioning = false,
    ): array {
        $empty = [
            'employee' => null,
            'tenant_schema' => null,
            'mfa_challenge' => null,
            'email_not_verified' => false,
            'identity_mismatch' => false,
            'created' => false,
        ];

        // Issue #5580 — exiger email_verified=true : un compte Google non
        // vérifié (ou un IdP qui n'atteste pas la possession) pourrait usurper
        // n'importe quel employé partageant le même email.
        $rawGoogleUser = $googleUser->getRaw();
        if (empty($rawGoogleUser['email_verified'])) {
            $this->auditLogger->emailNotVerified((string) $googleUser->getEmail(), $flow);

            return ['email_not_verified' => true] + $empty;
        }

        // audit(securite) #6531 — résolution BORNÉE au tenant (user_lookups →
        // schéma → employé) au lieu d'une recherche cross-tenant par email seul.
        $resolved = $this->authService->resolveEmployeeByEmail((string) $googleUser->getEmail());
        /** @var Employee|null $employee */
        $employee = $resolved['employee'] ?? null;
        $tenantSchema = $resolved['tenant_schema'] ?? null;

        if (! $employee) {
            // Issue #3724 — pas d'auto-provisionnement silencieux. L'auto-
            // création reste possible uniquement sur les environnements de
            // démo explicitement configurés, en parité avec le 401 mobile.
            if (! $allowDemoProvisioning || ! config('app.demo_mode_enabled')) {
                return ['tenant_schema' => $tenantSchema] + $empty;
            }

            $employee = $this->demoProvisioner->provision($googleUser);
        }

        $created = $employee->wasRecentlyCreated;

        // audit(securite) #6531 — liaison google_id (sub) : première connexion
        // → liaison ; connexion suivante avec un sub ≠ google_id stocké → REFUS
        // (réattribution d'email Google Workspace / compte compromis).
        $googleSub = (string) $googleUser->getId();
        if ($employee->google_id === null || $employee->google_id === '') {
            $employee->forceFill(['google_id' => $googleSub])->save();
            $this->auditLogger->linked((string) $employee->id, (string) $employee->email);
        } elseif (! hash_equals($employee->google_id, $googleSub)) {
            $this->auditLogger->identityMismatch((string) $employee->id, (string) $employee->email);

            return ['tenant_schema' => $tenantSchema, 'identity_mismatch' => true] + $empty;
        }

        // Sécurité #2630 — un employé suspendu (ou société suspendue/expirée)
        // ne peut pas s'authentifier, y compris via Google.
        if ($employee->status !== 'active') {
            throw new AccountSuspendedException;
        }
        if ($employee->company_id) {
            $company = $employee->company;
            if ($company && in_array($company->status, ['suspended', 'expired'], true)) {
                throw new AccountSuspendedException;
            }
        }

        // Issue #5579 — garde 2FA sur les flux SSO : sans cette vérification un
        // employé ayant activé la TOTP pouvait contourner le challenge via Google.
        if ($employee->two_fa_enabled_at !== null) {
            // Le nom du cookie dépend de l'id employé : il n'est donc connu
            // qu'APRÈS résolution. Le contrôleur transmet les valeurs des
            // cookies `mfa_remember_*` présentés, et la décision reste ici —
            // un seul endroit décide si un appareil est de confiance. Le hash
            // inclut l'id employé, donc une valeur d'un AUTRE employé ne peut
            // pas correspondre : comparer toutes les valeurs est sûr.
            $trusted = false;
            $expected = $this->twoFactorService->rememberCookieValue($employee);

            foreach ($rememberCookies as $provided) {
                if (hash_equals($expected, $provided)) {
                    $trusted = true;
                    break;
                }
            }

            if (! $trusted) {
                $challenge = $this->twoFactorService->issueChallenge([
                    'employee_id' => $employee->id,
                    'company_id' => (string) $employee->company_id,
                    // audit(securite) #6540 — tenant_schema résolu (user_lookups) :
                    // en mode schéma-par-tenant, un challenge avec null ne peut
                    // pas re-poser search_path à la vérification (2FA cassée).
                    'tenant_schema' => $tenantSchema,
                    'email' => (string) $employee->email,
                    'device_name' => $deviceName,
                ]);

                return [
                    'employee' => $employee,
                    'tenant_schema' => $tenantSchema,
                    'mfa_challenge' => $challenge,
                    'email_not_verified' => false,
                    'identity_mismatch' => false,
                    'created' => $created,
                ];
            }
        }

        // Politique tenant : rôle sensible + 2FA non activée → blocage.
        if ($this->twoFactorService->requiresMfa($employee)) {
            throw TwoFactorException::required();
        }

        return [
            'employee' => $employee,
            'tenant_schema' => $tenantSchema,
            'mfa_challenge' => null,
            'email_not_verified' => false,
            'identity_mismatch' => false,
            'created' => $created,
        ];
    }
}
