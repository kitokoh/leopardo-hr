<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\AuthService;
use App\Core\Http\Idempotency\IdempotencyStore;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Billing\Application\Actions\SubmitTrialSignup;
use App\Modules\Billing\Application\Actions\VerifyTrialSignup;
use App\Shared\Rules\PasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Self-service trial provisioning endpoint.
 *
 * Transforms the guided trial signup into an instant self-service flow:
 * email + company name → tenant created → credentials returned → access in <30s.
 */
class SelfServiceTrialController extends Controller
{
    public function __construct(
        private readonly SubmitTrialSignup $submitTrialSignup,
        private readonly VerifyTrialSignup $verifyTrialSignup,
        private readonly AuthService $authService,
        private readonly IdempotencyStore $idempotencyStore,
    ) {}

    /**
     * POST /api/v1/trial/signup
     *
     * Creates a trial tenant with a manager account immediately.
     * Returns the credentials so the prospect can log in right away.
     */
    public function signup(Request $request): JsonResponse
    {
        // BOS-O-05 / #8626 — idempotence de l'inscription PUBLIQUE.
        //
        // `/trial/signup` est une écriture SANS session : le middleware
        // `IdempotencyMiddleware` (#5277) ne la couvre pas (sa clé de cache est
        // scopée par le token `Authorization`). Un double clic ou un retry
        // réseau créait donc deux demandes et deux OTP pour la même intention.
        //
        // Quand le client envoie `Idempotency-Key`, la première réponse 2xx est
        // mémorisée puis rejouée à l'identique (header `Idempotent-Replayed`).
        // Sans clé, le comportement historique est strictement préservé.
        $idempotencyKey = $request->headers->get('Idempotency-Key');

        if (! is_string($idempotencyKey) || $idempotencyKey === '') {
            return $this->handleSignup($request);
        }

        if (preg_match('/^[A-Za-z0-9._:-]{8,255}$/', $idempotencyKey) !== 1) {
            return new JsonResponse([
                'success' => false,
                'error' => 'INVALID_IDEMPOTENCY_KEY',
                'message' => __('errors.INVALID_IDEMPOTENCY_KEY'),
            ], 422);
        }

        $scope = 'trial.signup';
        $requestHash = hash('sha256', (string) $request->getContent());
        $begin = $this->idempotencyStore->begin($scope, $idempotencyKey, $requestHash);

        if ($begin['state'] === 'conflict') {
            return new JsonResponse([
                'success' => false,
                'error' => 'IDEMPOTENCY_KEY_REUSED',
                'message' => __('errors.IDEMPOTENCY_KEY_REUSED'),
            ], 422);
        }

        if ($begin['state'] === 'in_progress') {
            return new JsonResponse([
                'success' => false,
                'error' => 'IDEMPOTENCY_IN_PROGRESS',
                'message' => __('errors.IDEMPOTENCY_IN_PROGRESS'),
            ], 409);
        }

        if ($begin['state'] === 'replay') {
            $replayed = new JsonResponse($begin['body'], $begin['status']);
            $replayed->headers->set('Idempotent-Replayed', 'true');

            return $replayed;
        }

        try {
            $response = $this->handleSignup($request);

            if ($response->isSuccessful()) {
                /** @var array<string, mixed> $body */
                $body = json_decode((string) $response->getContent(), true) ?? [];
                $this->idempotencyStore->complete($begin['id'], $response->getStatusCode(), $body);
            } else {
                // Un échec n'est pas une intention aboutie : la clé est libérée
                // pour qu'un retry légitime puisse réessayer.
                $this->idempotencyStore->release($begin['id']);
            }

            return $response;
        } catch (\Throwable $e) {
            $this->idempotencyStore->release($begin['id']);

            throw $e;
        }
    }

    /**
     * Corps de l'inscription — delegue a `SubmitTrialSignup` (#7962).
     *
     * Le bloc de 374 lignes (validation, resolution du nom, deux parcours,
     * creation de `trial_provisionings`) vivait ici. Il est deplace a
     * l'identique dans une Action : le controller ne garde que le transport
     * HTTP (idempotence, delegation, reponse).
     */
    private function handleSignup(Request $request): JsonResponse
    {
        return $this->submitTrialSignup->execute($request);
    }

    /**
     * GET /api/v1/trial/status?token=...
     *
     * Statut du provisioning d'un essai guidé (#2437). Le prospect reçoit un
     * provisioning_token au signup et peut poller ce endpoint sans exposer
     * son email brut : pending → ready (login_url émis) / failed (raison
     * générique). Le token est vérifié exactement (pas de lookup par email).
     */
    public function status(Request $request): JsonResponse
    {
        // #4931 : le provisioning_token ne doit plus voyager en query string.
        // Header X-Token privilégié (la vitrine passe par le proxy same-origin
        // /api/forms/trial-status qui le positionne) ; ?token= reste accepté
        // en fallback pour les clients déjà déployés (dépréciation).
        $token = trim((string) $request->header('X-Token', ''));
        if ($token === '') {
            $token = trim((string) $request->query('token', ''));
        }

        if ($token === '' || strlen($token) !== 64) {
            return new JsonResponse([
                'success' => false,
                'error' => 'PROVISIONING_TOKEN_INVALID',
                'message' => __('billing.trial_status_token_invalid'),
            ], 404);
        }

        $row = DB::table('trial_provisionings')
            ->where('provisioning_token', $token)
            ->first();

        if ($row === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'PROVISIONING_TOKEN_INVALID',
                'message' => __('billing.trial_status_token_invalid'),
            ], 404);
        }

        // #2903 : provisioned_at est stocké en string (insert DB::table) —
        // ne JAMAIS appeler ->toIso8601String() sur une string (500).
        $provisionedAt = is_scalar($row->provisioned_at) && (string) $row->provisioned_at !== ''
            ? Carbon::parse((string) $row->provisioned_at)->toIso8601String()
            : null;

        $payload = [
            'success' => true,
            'data' => [
                'status' => $row->status,
                'provisioned_at' => $provisionedAt,
            ],
        ];

        // Le lien d'accès n'est exposé qu'une fois le sandbox prêt (jamais
        // dans l'état pending — le prospect n'a rien à ouvrir avant).
        if ($row->status === 'ready' && is_string($row->login_url) && $row->login_url !== '') {
            $payload['data']['login_url'] = $row->login_url;
        }
        if ($row->status === 'failed') {
            $payload['data']['message'] = __('billing.trial_status_failed');
        }

        // Onboarding sans mailer : le front doit savoir si un mot de passe a
        // déjà été choisi (sinon il propose le formulaire) et si l'email
        // d'accès est réellement parti (sinon il ne promet pas un lien).
        $payload['data']['password_set'] = is_scalar($row->password_set_at)
            && (string) $row->password_set_at !== '';
        $payload['data']['access_sent'] = is_scalar($row->access_sent_at ?? null)
            && (string) ($row->access_sent_at ?? '') !== '';

        return new JsonResponse($payload, 200);
    }

    /**
     * POST /api/v1/trial/verify
     *
     * Verifies the OTP and provisions the trial tenant immediately.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        /** @var array{email: string, code: string} $validated */
        $email = strtolower(trim($validated['email']));

        // Issue #2903 : le parcours d'essai guidé ne doit JAMAIS exposer un
        // 500 brut « Server Error » (c'était le cas en prod v4.23.5 sur le
        // verify) : toute exception inattendue du provisioning est convertie
        // en réponse structurée réessayable.
        try {
            /** @var array{success: true, company: Company, manager: Employee, manager_email: string, first_name: string, last_name: string, temp_password: string}|array{success: false, error: string, message: string, status: int} $result */
            $result = $this->verifyTrialSignup->execute($email, $validated['code']);
        } catch (\Throwable $e) {
            Log::channel('structured')->error('trial.verify.unexpected', [
                'email_hash' => $this->emailLogId($email),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'TRIAL_VERIFY_UNAVAILABLE',
                'message' => __('errors.VERIFICATION_TEMPORARILY_UNAVAILABLE'),
            ], 503);
        }

        if ($result['success'] === false) {
            return new JsonResponse([
                'success' => false,
                'error' => $result['error'],
                'message' => $result['message'],
            ], $result['status']);
        }

        // La locale HTTP peut rester sur la valeur par défaut (souvent `en`)
        // puisque `/trial/verify` ne reçoit pas le pays. Le provisioning a
        // toutefois résolu et persisté la langue depuis le pays du signup;
        // utiliser cette langue pour le message de succès garantit un contrat
        // cohérent avec la société créée.
        /** @var Company $company */
        $company = $result['company'];
        $fallbackLocale = config('app.locale', 'en');
        App::setLocale($company->language ?: (\is_string($fallbackLocale) ? $fallbackLocale : 'en'));

        // Auto-connexion (retour fondateur 2026-09-13). L'utilisateur vient de
        // prouver la possession de sa boîte mail par le code à 6 chiffres, et
        // n'a JAMAIS choisi de mot de passe : le renvoyer vers un écran de
        // connexion était un cul-de-sac (« il n'a qu'à cliquer sur se
        // connecter » alors qu'aucun mot de passe n'existe).
        //
        // On réutilise le chemin de connexion ÉPROUVÉ (`AuthService::login`)
        // plutôt que de forger un jeton ici : il construit les abilities
        // multi-tenant (`tenant_schema:…`, `tenant_company:…`) sans lesquelles
        // le dashboard est inaccessible, applique la purge de quota de jetons
        // (#7009) et positionne `last_login_at`.
        //
        // Toute erreur est absorbée : le compte EST provisionné, on dégrade
        // vers le parcours manuel au lieu de casser la vérification.
        $autoLoginToken = null;

        try {
            /** @var array{token: string} $authResult */
            $authResult = $this->authService->login($email, $result['temp_password'], 'trial-self-service');
            $autoLoginToken = $authResult['token'];
        } catch (\Throwable $e) {
            Log::warning('trial.verify.autologin_failed', [
                'email_hash' => $this->emailLogId($email),
                'error' => $e->getMessage(),
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => __('errors.TRIAL_SPACE_READY'),
            'data' => [
                // Jeton de session à poser en cookie httpOnly par le client web
                // (/api/forms/verify). `null` = auto-connexion indisponible →
                // le client retombe sur l'écran de connexion classique.
                'token' => $autoLoginToken,
                'company' => [
                    'id' => $result['company']->id,
                    'name' => $result['company']->name,
                    'slug' => $result['company']->slug,
                ],
                'manager' => [
                    'email' => $result['manager_email'],
                    'first_name' => $result['first_name'],
                    'last_name' => $result['last_name'],
                ],
                'trial' => [
                    'days' => 14,
                    'ends_at' => now()->addDays(14)->toIso8601String(),
                ],
                'next_steps' => [
                    'login' => __('errors.TRIAL_STEP_LOGIN'),
                    'change_password' => __('errors.TRIAL_STEP_CHANGE_PASSWORD'),
                    'add_employees' => __('errors.TRIAL_STEP_ADD_EMPLOYEES'),
                ],
            ],
        ], 201);
    }

    /**
     * POST /api/v1/trial/set-password
     *
     * Onboarding **sans dépendance au mailer**. Le guided trial créait le
     * manager avec un mot de passe aléatoire jamais communiqué et un
     * `login_url` pointant sur une page à mot de passe ; l'email d'accès étant
     * best-effort, un client pouvait se retrouver provisionné sans aucun moyen
     * d'entrer (mailer non configuré en dev comme en prod).
     *
     * Le prospect détient déjà un secret : le `provisioning_token` renvoyé par
     * /trial/signup et pollé via /trial/status. Il lui suffit de choisir son
     * mot de passe avec ce token pour accéder au portail — l'email redevient un
     * simple bonus.
     *
     * Le token est à usage unique pour cette opération : un second appel
     * répond 409, un token égaré ne peut donc pas réécrire un mot de passe déjà
     * choisi.
     */
    public function setPassword(Request $request): JsonResponse
    {
        // Même convention que /trial/status (#4931) : le token vit dans
        // l'en-tête X-Token, jamais dans l'URL.
        $token = trim((string) $request->header('X-Token', ''));
        if ($token === '') {
            $token = trim((string) $request->input('token', ''));
        }

        if ($token === '' || strlen($token) !== 64) {
            return new JsonResponse([
                'success' => false,
                'error' => 'PROVISIONING_TOKEN_INVALID',
                'localized_message' => __('billing.trial_status_token_invalid'),
            ], 404);
        }

        // Même politique que la réinitialisation de mot de passe (#5620,
        // helper unique #8021).
        $validated = $request->validate([
            'password' => PasswordPolicy::required(),
        ]);

        $row = DB::table('trial_provisionings')
            ->where('provisioning_token', $token)
            ->first();

        if ($row === null || $row->status !== 'ready' || $row->company_id === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'PROVISIONING_NOT_READY',
                'localized_message' => __('billing.trial_password_not_ready'),
            ], 409);
        }

        if (is_scalar($row->password_set_at) && (string) $row->password_set_at !== '') {
            return new JsonResponse([
                'success' => false,
                'error' => 'TRIAL_PASSWORD_ALREADY_SET',
                'localized_message' => __('billing.trial_password_already_set'),
            ], 409);
        }

        // #7490 : le lien magique de l'e-mail de bienvenue porte ce token — il
        // doit expirer. TTL 72 h depuis le provisioning ; passé ce délai,
        // l'utilisateur passe par « Recevoir un code de connexion » puis
        // définit son mot de passe depuis l'espace.
        $provisionedAtRaw = $row->provisioned_at ?? null;
        if (is_scalar($provisionedAtRaw) && (string) $provisionedAtRaw !== ''
            && Carbon::parse((string) $provisionedAtRaw)->addHours(72)->isPast()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'TRIAL_PASSWORD_LINK_EXPIRED',
                'localized_message' => __('billing.trial_password_link_expired'),
            ], 410);
        }

        $manager = $this->findTrialManager((string) $row->email, (string) $row->company_id);

        if ($manager === null) {
            Log::error('trial.set_password_manager_not_found', [
                'company_id' => $row->company_id,
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'TRIAL_MANAGER_NOT_FOUND',
                'localized_message' => __('billing.trial_password_manager_missing'),
            ], 404);
        }

        $manager->forceFill(['password_hash' => Hash::make($validated['password'])])->save();

        // Le magic link d'accès n'a plus lieu d'être : on révoque son hash pour
        // qu'un email égaré ne puisse plus ouvrir de session.
        $extraData = $manager->extra_data;
        unset($extraData['demo_access_token_hash'], $extraData['demo_access_token_expires_at']);
        $manager->forceFill(['extra_data' => $extraData])->save();

        DB::table('trial_provisionings')
            ->where('id', $row->id)
            ->update(['password_set_at' => now(), 'updated_at' => now()]);

        Log::info('trial.set_password_success', ['company_id' => $row->company_id]);

        return new JsonResponse([
            'success' => true,
            'message' => __('billing.trial_password_set'),
            'localized_message' => __('billing.trial_password_set'),
            'data' => [
                'login_url' => is_string($row->login_url) && $row->login_url !== ''
                    ? $row->login_url
                    : '/auth/login',
            ],
        ]);
    }

    /**
     * BOS-002 (#8144) — identifiant de journalisation d'un email (pseudonyme).
     *
     * Les logs applicatifs du parcours trial ne doivent JAMAIS porter l'email
     * du prospect en clair (PII) : on journalise un hachage tronqué, qui
     * conserve la corrélation entre événements d'un même email sans exposer
     * la donnée. Maille volontairement courte (16 hex) : suffisante pour
     * corréler, insuffisante pour être réversible par force brute à l'échelle
     * des volumes de logs.
     */
    private function emailLogId(string $email): string
    {
        return substr(hash('sha256', mb_strtolower(trim($email))), 0, 16);
    }


    /**
     * Retrouve le manager du sandbox d'essai.
     *
     * Requête non authentifiée, exactement comme `DemoLoginController` (le
     * magic link) : les sandbox vivent dans le schéma `shared_tenants`, inclus
     * dans le search_path par défaut du service.
     */
    private function findTrialManager(string $email, string $companyId): ?Employee
    {
        /** @var Employee|null $manager */
        $manager = Employee::query()
            ->where('email', $email)
            ->where('company_id', $companyId)
            ->where('role', 'manager')
            ->orderBy('id')
            ->first();

        return $manager;
    }
}
