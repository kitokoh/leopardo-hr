<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Contracts\SmsCodeSenderContract;
use App\Core\Auth\Domain\Models\User;
use App\Core\Auth\Infrastructure\Services\UserAuthService;
use App\Http\Controllers\Controller;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * COULOIR 16 — M2 (#9104) · Connexion par code (OTP) **par telephone**.
 *
 * ## Pourquoi un controleur distinct de `LoginCodeController`
 *
 * `LoginCodeController` (#7490) sert un parcours **employeur** : il n'accepte
 * que l'e-mail, et seulement pour une ligne `trial_provisionings` `ready` sans
 * mot de passe. Le parcours du canal public est different : le client saisit un
 * **numero de telephone**, il n'a pas forcement d'e-mail, et il peut deja
 * exister dans `users` (pont d'identite L4, #9094). Melanger les deux aurait
 * fait porter a un controleur deux politiques d'eligibilite incompatibles.
 *
 * ## Ce que ce controleur garantit
 *
 *  - **Anti-enumeration** : `request` rend **204 dans tous les cas**, meme si le
 *    numero est inconnu. Un 404 revelerait quels numeros ont un compte.
 *  - **Un code, un canal** : le code part par SMS ; si le SMS n'est pas
 *    configurable, on ne fabrique pas de faux succes — on trace et on rend 204
 *    (le client reessaiera), jamais un code fantome.
 *  - **Code hache au repos** : `Hash::make`, jamais de comparaison en clair,
 *    TTL 10 min, usage unique, verrou apres 5 echecs — meme politique que
 *    `LoginCodeController`.
 *  - **Anti-fraude** : 3 codes / 15 min par numero, 10 codes / heure par IP,
 *    backoff exponentiel apres 3 echecs de verification.
 *  - **Aucune PII dans les traces** : ni le code, ni le numero complet.
 *
 * ## Ce que ce controleur ne fait PAS
 *
 * Il ne cree **jamais** de compte : un numero inconnu ne produit rien. La
 * creation de compte est le role du pont d'identite (L4) et de l'inscription.
 * Un OTP ne doit pas etre un chemin de creation d'identite silencieux.
 */
class PublicPhoneOtpController extends Controller
{
    private const CODE_TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    /** 3 codes / 15 min par numero (cout SMS + harcelement). */
    private const MAX_PER_PHONE = 3;

    private const PHONE_WINDOW_SECONDS = 900;

    /** 10 codes / heure par IP (robots). */
    private const MAX_PER_IP = 10;

    private const IP_WINDOW_SECONDS = 3600;

    /** Backoff apres 3 echecs de verification : 1 min, 5 min, 15 min. */
    private const BACKOFF_SECONDS = [60, 300, 900];

    public function __construct(
        private readonly UserAuthService $userAuthService,
        private readonly SmsCodeSenderContract $smsSender,
    ) {}

    /**
     * POST /api/v1/user/login-code/phone/request
     *
     * Rend **toujours 204** : le corps ne dit jamais si le numero a un compte.
     */
    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_e164' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $phone = PhoneNumber::toE164(
            (string) $validated['phone_e164'],
            isset($validated['country']) ? (string) $validated['country'] : null,
        );

        // Numero non normalisable : on ne devine pas un pays, et on ne dit pas
        // non plus que le format est mauvais (ce serait un oracle). 204.
        if ($phone === null) {
            Log::info('auth.public_phone_otp_rejected', ['reason' => 'invalid_e164']);

            return $this->accepted();
        }

        if (! $this->allowSend($phone, $request->ip())) {
            Log::warning('auth.public_phone_otp_rate_limited', ['phone' => $this->mask($phone)]);

            return $this->accepted();
        }

        /** @var User|null $user */
        $user = User::query()->where('phone_e164', $phone)->first();

        // Numero inconnu : rien n'est envoye, rien n'est revele.
        if ($user === null) {
            Log::info('auth.public_phone_otp_unknown_phone', ['phone' => $this->mask($phone)]);

            return $this->accepted();
        }

        if ($user->status !== 'active') {
            // Compte suspendu : aucun code emis (fail-closed, #2618).
            Log::warning('auth.public_phone_otp_inactive_account', ['user_id' => $user->id]);

            return $this->accepted();
        }

        $locale = $this->resolveLocale($user);

        // Le canal doit pouvoir delivrer AVANT qu'un code soit genere : un code
        // genere puis perdu laisserait l'utilisateur bloque sans explication.
        if (! $this->smsSender->isConfigured()) {
            Log::warning('auth.public_phone_otp_sms_unavailable', ['phone' => $this->mask($phone)]);

            return $this->accepted();
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $traceId = $this->smsSender->send($phone, $code, $locale);

        if ($traceId === null) {
            // Rien n'est parti : on ne stocke AUCUN code (un nouvel essai doit
            // produire un nouveau code, pas rejouer un code que personne n'a recu).
            Log::warning('auth.public_phone_otp_delivery_failed', ['phone' => $this->mask($phone)]);

            return $this->accepted();
        }

        Cache::put($this->cacheKey($phone), [
            'hash' => Hash::make($code),
            'attempts' => 0,
            'backoff_until' => null,
        ], now()->addMinutes(self::CODE_TTL_MINUTES));

        Log::info('auth.public_phone_otp_dispatched', [
            'trace_id' => $traceId,
            'phone' => $this->mask($phone),
            'locale' => $locale,
        ]);

        return $this->accepted();
    }

    /**
     * POST /api/v1/user/login-code/phone/verify
     *
     * Succes : `{data:{user}, token, token_type, token_expires_at}` — meme
     * contrat que `POST /user/login`, donc le client mobile n'a qu'un seul
     * format de reponse a gerer.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_e164' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'code' => ['required', 'string', 'size:6'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $phone = PhoneNumber::toE164(
            (string) $validated['phone_e164'],
            isset($validated['country']) ? (string) $validated['country'] : null,
        );

        if ($phone === null) {
            return $this->invalidCode();
        }

        $key = $this->cacheKey($phone);

        /** @var array{hash?: string, attempts?: int, backoff_until?: int|null}|null $entry */
        $entry = Cache::get($key);

        if (! is_array($entry) || ! isset($entry['hash'])) {
            return $this->expiredCode();
        }

        $backoffUntil = $entry['backoff_until'] ?? null;

        if (is_int($backoffUntil) && $backoffUntil > now()->timestamp) {
            return $this->tooManyAttempts();
        }

        $attempts = (int) ($entry['attempts'] ?? 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return $this->tooManyAttempts();
        }

        if (! Hash::check((string) $validated['code'], (string) $entry['hash'])) {
            $attempts++;
            $entry['attempts'] = $attempts;

            // Backoff exponentiel : 3e echec -> 1 min, 4e -> 5 min, 5e -> 15 min.
            if ($attempts >= 3) {
                $step = min($attempts - 3, count(self::BACKOFF_SECONDS) - 1);
                $entry['backoff_until'] = (int) now()->timestamp + (int) self::BACKOFF_SECONDS[$step];
            }

            Cache::put($key, $entry, now()->addMinutes(self::CODE_TTL_MINUTES));

            Log::warning('auth.public_phone_otp_invalid_code', [
                'phone' => $this->mask($phone),
                'attempts' => $attempts,
            ]);

            return $this->invalidCode();
        }

        // Usage unique : le code est consomme AVANT l'ouverture de session.
        Cache::forget($key);

        /** @var User|null $user */
        $user = User::query()->where('phone_e164', $phone)->first();

        if ($user === null || $user->status !== 'active') {
            return $this->invalidCode();
        }

        // Le telephone vient d'etre prouve : on horodate la verification. C'est
        // ce que le pont d'identite (L4) exige avant d'ecrire `user_id`.
        if ($user->phone_verified_at === null) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        $result = $this->userAuthService->loginViaPhone($phone, $validated['device_name'] ?? null);

        return new JsonResponse([
            'data' => $this->formatUser($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'token_expires_at' => $result['token_expires_at'],
        ]);
    }

    /**
     * Consomme une unite des deux quotas (numero + IP).
     *
     * Les deux sont verifies AVANT d'en consommer un : sinon un numero bloque
     * par son quota IP verrait quand meme son compteur numero avance.
     */
    private function allowSend(string $phone, ?string $ip): bool
    {
        $phoneKey = 'auth:public_phone_otp_send:'.hash('sha256', $phone);
        $ipKey = 'auth:public_phone_otp_ip:'.hash('sha256', (string) $ip);

        if (RateLimiter::tooManyAttempts($phoneKey, self::MAX_PER_PHONE)) {
            return false;
        }

        if (RateLimiter::tooManyAttempts($ipKey, self::MAX_PER_IP)) {
            return false;
        }

        RateLimiter::hit($phoneKey, self::PHONE_WINDOW_SECONDS);
        RateLimiter::hit($ipKey, self::IP_WINDOW_SECONDS);

        return true;
    }

    private function resolveLocale(User $user): string
    {
        $language = $user->preferred_language;

        return in_array($language, ['fr', 'en', 'ar', 'tr'], true) ? $language : 'fr';
    }

    private function cacheKey(string $phone): string
    {
        return 'auth:public_phone_otp:'.hash('sha256', $phone);
    }

    /**
     * Masque un E.164 pour les traces : indicatif + 3 derniers chiffres.
     */
    private function mask(string $e164): string
    {
        $digits = ltrim($e164, '+');

        if (strlen($digits) <= 7) {
            return str_repeat('*', strlen($digits));
        }

        return '+'.substr($digits, 0, 4).str_repeat('*', 4).substr($digits, -3);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone_e164' => $user->phone_e164,
            'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
            'personal_statuses' => $user->personal_statuses,
        ];
    }

    private function accepted(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }

    private function invalidCode(): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'error' => 'INVALID_CODE',
            'localized_message' => __('auth.public_phone_otp_invalid'),
        ], 401);
    }

    private function expiredCode(): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'error' => 'CODE_EXPIRED',
            'localized_message' => __('auth.public_phone_otp_expired'),
        ], 410);
    }

    private function tooManyAttempts(): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'error' => 'TOO_MANY_ATTEMPTS',
            'localized_message' => __('auth.public_phone_otp_too_many_attempts'),
        ], 429);
    }
}
