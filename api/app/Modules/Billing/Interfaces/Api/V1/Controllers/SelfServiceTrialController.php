<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1\Controllers;

use App\Core\Http\Idempotency\IdempotencyStore;
use App\Http\Controllers\Controller;
use App\Modules\Billing\Application\Actions\GetTrialStatus;
use App\Modules\Billing\Application\Actions\SetTrialPassword;
use App\Modules\Billing\Application\Actions\SignupTrial;
use App\Modules\Billing\Application\Actions\VerifyTrialAndProvision;
use App\Modules\Billing\Interfaces\Api\V1\Requests\SetTrialPasswordRequest;
use App\Modules\Billing\Interfaces\Api\V1\Requests\SignupTrialRequest;
use App\Modules\Billing\Interfaces\Api\V1\Requests\VerifyTrialRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Self-service trial provisioning endpoint.
 *
 * Transforms the guided trial signup into an instant self-service flow:
 * email + company name -> tenant created -> credentials returned -> access in <30s.
 *
 * ## #9732 — decoupage par cas d'usage
 *
 * Ce controleur pesait 955 lignes et GROSSISSAIT a chaque lot (le seul de
 * l'audit structurel dans ce cas). Il ne fait plus que du HTTP : lire les
 * en-tetes, valider la forme (FormRequest), appeler l'Action du cas d'usage,
 * serialiser. Chaque cas d'usage est une classe nommee, exercable par un test
 * qui ne passe PAS par le controleur — c'est la condition de la regle D4 du
 * registre (« pas d'extraction sans test ») : un test qui passe par le
 * controleur ne survit pas a la prochaine refactorisation du controleur.
 *
 * | Cas d'usage | Action |
 * |---|---|
 * | inscription (3 parcours) | `SignupTrial` |
 * | provisioning sandbox | `ProvisionTrialSandbox` |
 * | activation directe | `ActivateTrialInRequest` |
 * | statut de provisioning | `GetTrialStatus` |
 * | verification OTP | `VerifyTrialAndProvision` |
 * | choix du mot de passe | `SetTrialPassword` (deja extraite, #7962) |
 */
class SelfServiceTrialController extends Controller
{
    public function __construct(
        private readonly SignupTrial $signupTrial,
        private readonly GetTrialStatus $getTrialStatus,
        private readonly VerifyTrialAndProvision $verifyTrialAndProvision,
        private readonly SetTrialPassword $setTrialPassword,
        private readonly IdempotencyStore $idempotencyStore,
    ) {}

    /**
     * POST /api/v1/trial/signup
     *
     * Creates a trial tenant with a manager account immediately.
     * Returns the credentials so the prospect can log in right away.
     */
    public function signup(SignupTrialRequest $request): JsonResponse
    {
        // BOS-O-05 / #8626 — idempotence de l'inscription PUBLIQUE.
        //
        // `/trial/signup` est une ecriture SANS session : le middleware
        // `IdempotencyMiddleware` (#5277) ne la couvre pas (sa cle de cache est
        // scopee par le token `Authorization`). Un double clic ou un retry
        // reseau creait donc deux demandes et deux OTP pour la meme intention.
        //
        // Quand le client envoie `Idempotency-Key`, la premiere reponse 2xx est
        // memorisee puis rejouee a l'identique (header `Idempotent-Replayed`).
        // Sans cle, le comportement historique est strictement preserve.
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
                // Un echec n'est pas une intention aboutie : la cle est liberee
                // pour qu'un retry legitime puisse reessayer.
                $this->idempotencyStore->release($begin['id']);
            }

            return $response;
        } catch (\Throwable $e) {
            $this->idempotencyStore->release($begin['id']);

            throw $e;
        }
    }

    /**
     * Corps de l'inscription — appele directement sans cle d'idempotence, ou
     * via le wrapper idempotent ci-dessus.
     */
    private function handleSignup(SignupTrialRequest $request): JsonResponse
    {
        // Audit 2026-09-13 — un navigateur DEJA connecte ne doit pas pouvoir
        // creer un second espace : `/signup` restait accessible et cet endpoint
        // repondait 201 avec une session Sanctum active, provisionnant un
        // second tenant pour le meme utilisateur.
        // Le garde est pose ICI (et non seulement dans le middleware Next, qui
        // n'est qu'un filtre cosmetique) : les clients directs — mobile, cURL,
        // SDK — ne traversent pas le middleware web.
        if ($request->user('sanctum') !== null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'SESSION_ALREADY_ACTIVE',
                'message' => __('errors.SESSION_ALREADY_ACTIVE'),
            ], 409);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $result = $this->signupTrial->execute($validated);

        return match ($result['outcome']) {
            'unavailable' => new JsonResponse([
                'success' => false,
                'error' => 'TRIAL_SIGNUP_UNAVAILABLE',
                'message' => __('errors.TRIAL_SIGNUP_UNAVAILABLE'),
                'localized_message' => __('errors.TRIAL_SIGNUP_UNAVAILABLE'),
            ], 503),
            'direct_activation' => $this->directActivationResponse($result['data'] ?? []),
            'sandbox' => new JsonResponse([
                'success' => true,
                'message' => __('billing.trial_signup_received'),
                'data' => $result['data'] ?? [],
            ], 200),
            default => new JsonResponse([
                'success' => true,
                'message' => __('errors.VERIFICATION_CODE_SENT'),
                'data' => $result['data'] ?? [],
            ], 200),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function directActivationResponse(array $data): JsonResponse
    {
        if (($data['success'] ?? true) === false) {
            return new JsonResponse([
                'success' => false,
                'error' => 'TRIAL_ACTIVATION_UNAVAILABLE',
                'message' => __('errors.TRIAL_SIGNUP_UNAVAILABLE'),
                'localized_message' => __('errors.TRIAL_SIGNUP_UNAVAILABLE'),
            ], 503);
        }

        $payload = $data['data'] ?? $data;

        return new JsonResponse([
            'success' => true,
            'message' => ($payload['password_set'] ?? false)
                ? __('billing.trial_direct_activation_ready')
                : __('billing.trial_direct_activation_password_required'),
            'data' => $payload,
        ], 200);
    }

    /**
     * GET /api/v1/trial/status?token=...
     *
     * Statut du provisioning d'un essai guide (#2437). Le prospect recoit un
     * provisioning_token au signup et peut poller ce endpoint sans exposer son
     * email brut : pending -> ready (login_url emis) / failed (raison
     * generique). Le token est verifie exactement (pas de lookup par email).
     */
    public function status(Request $request): JsonResponse
    {
        // #4931 : le provisioning_token ne doit plus voyager en query string.
        // Header X-Token privilegie (la vitrine passe par le proxy same-origin
        // /api/forms/trial-status qui le positionne) ; ?token= reste accepte en
        // fallback pour les clients deja deployes (depreciation).
        $token = trim((string) $request->header('X-Token', ''));
        if ($token === '') {
            $token = trim((string) $request->query('token', ''));
        }

        $result = $this->getTrialStatus->execute($token);

        if ($result['success'] === false) {
            return new JsonResponse([
                'success' => false,
                'error' => $result['error'],
                'message' => __('billing.trial_status_token_invalid'),
            ], 404);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $result['data'],
        ], 200);
    }

    /**
     * POST /api/v1/trial/verify
     *
     * Verifies the OTP and provisions the trial tenant immediately.
     */
    public function verify(VerifyTrialRequest $request): JsonResponse
    {
        /** @var array{email: string, code: string} $validated */
        $validated = $request->validated();

        $result = $this->verifyTrialAndProvision->execute($validated['email'], $validated['code']);

        if ($result['success'] === false) {
            return new JsonResponse([
                'success' => false,
                'error' => $result['error'],
                'message' => $result['message'],
            ], $result['status']);
        }

        // La locale HTTP peut rester sur la valeur par defaut (souvent `en`)
        // puisque `/trial/verify` ne recoit pas le pays. Le provisioning a
        // toutefois resolu et persiste la langue depuis le pays du signup ;
        // utiliser cette langue pour le message de succes garantit un contrat
        // coherent avec la societe creee.
        $company = $result['company'];
        $fallbackLocale = config('app.locale', 'en');
        App::setLocale($company->language ?: (\is_string($fallbackLocale) ? $fallbackLocale : 'en'));

        return new JsonResponse([
            'success' => true,
            'message' => __('errors.TRIAL_SPACE_READY'),
            'data' => [
                // Jeton de session a poser en cookie httpOnly par le client web
                // (/api/forms/verify). `null` = auto-connexion indisponible ->
                // le client retombe sur l'ecran de connexion classique.
                'token' => $result['token'],
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
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
     * Onboarding **sans dependance au mailer**. Le guided trial creait le
     * manager avec un mot de passe aleatoire jamais communique et un
     * `login_url` pointant sur une page a mot de passe ; l'email d'acces etant
     * best-effort, un client pouvait se retrouver provisionne sans aucun moyen
     * d'entrer (mailer non configure en dev comme en prod).
     *
     * Le prospect detient deja un secret : le `provisioning_token` renvoye par
     * /trial/signup et polle via /trial/status. Il lui suffit de choisir son
     * mot de passe avec ce token pour acceder au portail — l'email redevient un
     * simple bonus.
     *
     * Le token est a usage unique pour cette operation : un second appel repond
     * 409, un token egare ne peut donc pas reecrire un mot de passe deja choisi.
     */
    public function setPassword(SetTrialPasswordRequest $request): JsonResponse
    {
        // Meme convention que /trial/status (#4931) : le token vit dans
        // l'en-tete X-Token, jamais dans l'URL.
        $token = trim((string) $request->header('X-Token', ''));
        if ($token === '') {
            $token = trim((string) $request->input('token', ''));
        }

        /** @var array{password: string} $validated */
        $validated = $request->validated();

        // #7962 — les regles metier (token a usage unique, expiration 72 h,
        // revocation du magic link) vivent dans l'Action, pas ici.
        $result = $this->setTrialPassword->execute($token, $validated['password']);

        if ($result['success'] === false) {
            return new JsonResponse([
                'success' => false,
                'error' => $result['error'],
                'localized_message' => $result['message'],
            ], $result['status']);
        }

        return new JsonResponse([
            'success' => true,
            'message' => $result['message'],
            'localized_message' => $result['message'],
            'data' => $result['data'],
        ]);
    }
}
