<?php

namespace App\Services;

use App\Exceptions\AccountLockedException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserAuthService
{
    /**
     * @return array{user: User, token: string, token_type: string}
     */
    public function register(string $firstName, string $lastName, string $email, string $password, ?string $phone = null): array
    {
        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => Hash::make($password),
            'provider' => 'email',
        ]);

        return $this->issueToken($user, 'Mobile App');
    }

    /**
     * @return array{user: User, token: string, token_type: string}
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

        if (! Hash::check($password, $user->password_hash)) {
            $user->increment('failed_login_attempts');
            if ($user->failed_login_attempts >= 5) {
                $user->update(['locked_until' => now()->addMinutes(15)]);
            }
            throw new InvalidCredentialsException;
        }

        if ($user->failed_login_attempts > 0 || $user->locked_until) {
            $user->update([
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]);
        }

        return $this->issueToken($user, $deviceName ?? 'api');
    }

    /**
     * @return array{user: User, token: string, token_type: string, is_new: bool}
     */
    public function googleSignIn(string $googleId, string $email, string $firstName, string $lastName, ?string $avatarUrl = null): array
    {
        $isNew = false;
        /** @var User|null $user */
        $user = User::where('google_id', $googleId)->first();

        if (! $user) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            $user->update([
                'google_id' => $googleId,
                'avatar_url' => $avatarUrl ?? $user->avatar_url,
                'provider' => $user->provider === 'email' ? 'email' : 'google',
            ]);
        } else {
            $isNew = true;
            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'google_id' => $googleId,
                'avatar_url' => $avatarUrl,
                'provider' => 'google',
                'email_verified_at' => now(),
            ]);
        }

        $result = $this->issueToken($user, 'Google Sign-In');
        $result['is_new'] = $isNew;

        return $result;
    }

    /**
     * @return array{user: User, token: string, token_type: string}
     */
    private function issueToken(User $user, string $deviceName): array
    {
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }
}
