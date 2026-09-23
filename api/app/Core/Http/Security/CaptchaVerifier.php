<?php

declare(strict_types=1);

namespace App\Core\Http\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * #8054 — Vérification CAPTCHA côté serveur (Cloudflare Turnstile /
 * reCAPTCHA — même contrat `siteverify`).
 *
 * Avant ce correctif, les middlewares des boutiques publiques (Travel,
 * Restaurant) n'exigeaient qu'un header `X-Captcha-Token` NON VIDE : le
 * secret configuré (`*.public_shop.captcha_secret`) n'était JAMAIS utilisé —
 * `curl -H "X-Captcha-Token: x"` bypassait toute la « protection anti-bot »
 * (fausse assurance de sécurité).
 *
 * Désormais : POST {verify_url} avec `secret` + `response` (+ `remoteip`),
 * timeout court, FAIL-CLOSED — toute erreur réseau/HTTP/payload est un
 * refus, jamais un laissez-passer. Vérificateur unique partagé par les deux
 * verticales (le code était dupliqué à l'identique).
 */
final class CaptchaVerifier
{
    /**
     * Endpoint `siteverify` par défaut (Cloudflare Turnstile — même contrat
     * que reCAPTCHA : POST form secret/response/remoteip → {success: bool}).
     */
    public const DEFAULT_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private const TIMEOUT_SECONDS = 5;

    /**
     * @return bool true UNIQUEMENT si le fournisseur confirme le jeton.
     */
    public function verify(string $secret, string $token, ?string $remoteIp = null, ?string $verifyUrl = null): bool
    {
        if ($secret === '' || $token === '') {
            return false;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->asForm()
                ->post($verifyUrl !== null && $verifyUrl !== '' ? $verifyUrl : self::DEFAULT_VERIFY_URL, array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ], static fn (?string $value): bool => $value !== null && $value !== ''));
        } catch (Throwable $e) {
            // Fail-closed : un fournisseur injoignable ne désactive jamais la
            // protection (sinon couper l'accès sortant = bypass total).
            Log::warning('captcha.verify_unreachable', ['error' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('captcha.verify_http_error', ['status' => $response->status()]);

            return false;
        }

        return $response->json('success') === true;
    }
}
