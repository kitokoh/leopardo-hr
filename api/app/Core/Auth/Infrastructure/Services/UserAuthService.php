<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use App\Core\Auth\Domain\Models\User;
use App\Core\Auth\Infrastructure\Services\SSO\OidcIdTokenValidator;
use App\Exceptions\AccountLockedException;
use App\Exceptions\InvalidCredentialsException;
use Illuminate\Support\Facades\Hash;

readonly class UserAuthService
{
    public function __construct(
        private readonly GoogleIdentityVerifier $googleIdentityVerifier = new GoogleIdentityVerifier(new OidcIdTokenValidator),
    ) {}

    /**
     * @return array{user: User, token: string, token_type: string, token_expires_at: ?string}
     */
    public function register(string $firstName, string $lastName, string $email, string $password, ?string $phone = null): array
    {
        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'provider' => 'email',
        ]);
        // #4695 : password_hash hors $fillable — assignation explicite.
        $user->forceFill(['password_hash' => Hash::make($password)])->save();

        return $this->issueToken($user, 'Mobile App');
    }

    /**
     * @return array{user: User, token: string, token_type: string, token_expires_at: ?string}
     */
    public function login(string $email, string $password, ?string $deviceName = null): array
    {
        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        if (! $user || ! $user->password_hash) {
            throw new InvalidCredentialsException;
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            throw new AccountLockedException($user->locked_until);
        }

        if ($user->status !== 'active') {
            // Issue #2618 : compte suspendu = aucun token émis (fail-closed).
            throw new \App\Exceptions\AccountSuspendedException;
        }

        if (! Hash::check($password, $user->password_hash)) {
            $user->increment('failed_login_attempts');
            if ($user->failed_login_attempts >= 5) {
                $user->locked_until = now()->addMinutes(15);
                $user->save();
            }
            throw new InvalidCredentialsException;
        }

        if ($user->failed_login_attempts > 0 || $user->locked_until) {
            $user->failed_login_attempts = 0;
            $user->locked_until = null;
            $user->save();
        }

        return $this->issueToken($user, $deviceName ?? 'api');
    }

    /**
     * COULOIR 16 — M2 (#9104) : ouverture de session après vérification d'un
     * code OTP **par téléphone**.
     *
     * Le numéro est déjà normalisé en E.164 et **déjà prouvé** par l'appelant
     * (`PublicPhoneOtpController::verify()` a consommé le code) : ce service ne
     * re-vérifie donc pas le code, il ouvre la session. La séparation est
     * volontaire — la preuve d'identité vit dans le contrôleur, l'émission du
     * jeton vit ici, comme pour les autres flux.
     *
     * Aucun compte n'est créé : un numéro inconnu lève
     * `InvalidCredentialsException`. Un OTP ne doit jamais être un chemin de
     * création d'identité silencieux.
     *
     * @return array{user: User, token: string, token_type: string, token_expires_at: ?string}
     */
    public function loginViaPhone(string $phoneE164, ?string $deviceName = null): array
    {
        /** @var User|null $user */
        $user = User::where('phone_e164', $phoneE164)->first();

        if (! $user) {
            throw new InvalidCredentialsException;
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            throw new AccountLockedException($user->locked_until);
        }

        if ($user->status !== 'active') {
            // Issue #2618 : compte suspendu = aucun token émis (fail-closed).
            throw new \App\Exceptions\AccountSuspendedException;
        }

        return $this->issueToken($user, $deviceName ?? 'Mobile App');
    }

    /**
     * Google Sign-In vérifié côté serveur (issue #3941).
     *
     * L'identité (google_id, email, nom, avatar) est dérivée des claims du
     * ID token vérifié par {@see GoogleIdentityVerifier} (signature Google
     * RS256/JWKS, iss, aud, exp, email_verified). Les valeurs fournies par
     * le client ne sont plus jamais utilisées — un attaquant ne peut plus
     * forger une identité pour prendre le contrôle d'un compte.
     *
     * @return array{user: User, token: string, token_type: string, is_new: bool}
     */
    public function googleSignIn(string $idToken, ?string $deviceName = null): array
    {
        $identity = $this->googleIdentityVerifier->verify($idToken);

        $googleId = $identity['google_id'];
        $email = $identity['email'];
        $firstName = $identity['first_name'];
        $lastName = $identity['last_name'];
        $avatarUrl = $identity['avatar_url'];

        $isNew = false;
        /** @var User|null $user */
        $user = User::where('google_id', $googleId)->first();

        if (! $user) {
            /** @var User|null $user */
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            if ($user->status !== 'active') {
                // Issue #2618 : compte suspendu = aucun token émis (fail-closed).
                throw new \App\Exceptions\AccountSuspendedException;
            }

            $user->update([
                'google_id' => $googleId,
                'avatar_url' => $avatarUrl ?? $user->avatar_url,
                'provider' => $user->provider === 'email' ? 'email' : 'google',
            ]);
        } else {
            $isNew = true;
            /** @var User $user */
            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'google_id' => $googleId,
                'avatar_url' => $avatarUrl,
                'provider' => 'google',
            ]);
            // Issue #3597 : email_verified_at non mass-assignable — assignation explicite.
            $user->email_verified_at = now();
            $user->save();
        }

        $result = $this->issueToken($user, $deviceName ?? 'Google Sign-In');
        $result['is_new'] = $isNew;

        return $result;
    }

    /**
     * @return array{user: User, token: string, token_type: string, token_expires_at: ?string}
     */
    private function issueToken(User $user, string $deviceName): array
    {
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        // #9093 — tout jeton porte une expiration. Avant, ce chemin (comme les
        // deux flux Google) créait un jeton SANS échéance : un jeton volé
        // restait valable indéfiniment. #9344 — la durée vient désormais de
        // `TokenExpiryService` (12 h par défaut, 30 j pour un appareil enrôlé).
        $expiresAt = app(TokenExpiryService::class)->forNewToken($deviceName);

        $token = $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
            'token_expires_at' => $expiresAt?->toIso8601String(),
        ];
    }
}
