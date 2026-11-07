<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Sms;

use App\Core\Auth\Domain\Contracts\SmsCodeSenderContract;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Envoi de code OTP par SMS — implementation par defaut (#8738, #9091).
 *
 * ## Transport reel quand il est configure, audit-only sinon
 *
 * Le canal n'a de sens que s'il peut REELLEMENT delivrer. Deux etats, jamais
 * d'entre-deux :
 *
 *  - **configure** (`driver = http` + endpoint + cle) : `send()` emet un appel
 *    HTTP via `HttpSmsTransport` et ne rend un `traceId` QUE si l'appel a
 *    abouti (2xx). Un echec rend `null`, donc l'appelant replit sur l'e-mail.
 *  - **non configure** : `isConfigured()` rend `false` AVANT tout envoi, et
 *    `send()` se contente de tracer le FAIT de la demande (audit-only) en
 *    rendant `null`.
 *
 * ## Ce que #9091 corrige
 *
 * Avant, `send()` rendait un `traceId` des que endpoint + cle etaient non
 * vides, **sans emettre aucun appel** : le controleur croyait l'envoi reussi
 * et sautait l'e-mail — le code ne partait nulle part, silencieusement.
 * Desormais un `traceId` est la preuve d'un appel HTTP abouti.
 *
 * ## Ce qui reste vrai
 *
 * - **validation E.164** via `App\Support\PhoneNumber` (source unique) ;
 * - **limitation de debit par numero** (`RateLimiter`, fenetre glissante) :
 *   l'anti-abus est une propriete du domaine, pas du transport ;
 * - **aucune PII dans les traces** : le numero est masque (`+2135****456`),
 *   le code n'est JAMAIS journalise.
 */
final class LogOnlySmsCodeSender implements SmsCodeSenderContract
{
    /**
     * Budget d'envoi par numero et par fenetre.
     *
     * 3 codes / 15 min : un utilisateur legitime qui s'est trompe de numero ou
     * dont le SMS a disparu corrige sa saisie, alors qu'un enumerateur qui
     * tape des numeros au hasard s'arrete au troisieme. La fenetre est
     * glissante (RateLimiter), pas un compteur fixe.
     */
    private const MAX_PER_WINDOW = 3;

    private const WINDOW_SECONDS = 900;

    public function __construct(
        private readonly HttpSmsTransport $transport = new HttpSmsTransport,
    ) {}

    public function channel(): string
    {
        return 'sms';
    }

    /**
     * Fail-closed : un canal n'est configure que si un transport CAPABLE est
     * branche (`driver = http`) ET que l'endpoint et la cle sont renseignes.
     *
     * Un driver `log` (defaut) ne peut pas delivrer : le declarer configure
     * ferait sauter l'e-mail pour un code qui n'arrive jamais (#9091).
     */
    public function isConfigured(): bool
    {
        $driver = config('services.sms_otp.driver');
        $endpoint = config('services.sms_otp.endpoint');
        $key = config('services.sms_otp.api_key');

        return $driver === 'http'
            && is_string($endpoint) && $endpoint !== ''
            && is_string($key) && $key !== '';
    }

    public function send(string $e164, string $code, string $locale): ?string
    {
        // Le contrat prend un E.164 : on refuse tout ce qui n'en est pas un
        // plutot que d'envoyer a un numero approximatif. `null` = non envoye.
        if (! PhoneNumber::isValidE164($e164)) {
            Log::warning('auth.login_code_sms_rejected', [
                'reason' => 'invalid_e164',
            ]);

            return null;
        }

        if (! $this->allowFor($e164)) {
            Log::warning('auth.login_code_sms_rate_limited', [
                'phone' => $this->mask($e164),
            ]);

            return null;
        }

        if (! $this->isConfigured()) {
            // Mode audit-only : on trace le FAIT de la demande (canal, locale)
            // et rien du contenu. Le numero reste masque.
            Log::info('auth.login_code_sms_audit_only', [
                'phone' => $this->mask($e164),
                'locale' => $locale,
            ]);

            return null;
        }

        $delivered = $this->transport->deliver(
            (string) config('services.sms_otp.endpoint'),
            (string) config('services.sms_otp.api_key'),
            (string) config('services.sms_otp.from'),
            $e164,
            $code,
            $locale,
        );

        if (! $delivered) {
            // L'appel a echoue : RIEN n'est parti. On rend `null` pour que
            // l'appelant replit sur l'e-mail — jamais de "code fantome".
            Log::warning('auth.login_code_sms_delivery_failed', [
                'phone' => $this->mask($e164),
            ]);

            return null;
        }

        $traceId = 'otp_sms_'.substr(hash('sha256', $e164.'|'.$locale.'|'.now()->timestamp), 0, 20);

        Log::info('auth.login_code_sms_dispatched', [
            'trace_id' => $traceId,
            'phone' => $this->mask($e164),
            'locale' => $locale,
        ]);

        return $traceId;
    }

    /**
     * Consomme une unite du quota pour ce numero.
     *
     * Cle derivee du numero normalise (donc stable entre deux ecritures) et
     * **hashee** : la cle de cache ne doit pas porter un numero en clair.
     */
    private function allowFor(string $e164): bool
    {
        $key = 'auth:login_code_sms:'.hash('sha256', $e164);

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_WINDOW)) {
            return false;
        }

        RateLimiter::hit($key, self::WINDOW_SECONDS);

        return true;
    }

    /**
     * Masque un E.164 pour les traces : on garde l'indicatif et les 3 derniers
     * chiffres, assez pour reconnaitre un numero dans une investigation, pas
     * assez pour le recomposer.
     */
    private function mask(string $e164): string
    {
        $digits = ltrim($e164, '+');

        if (strlen($digits) <= 7) {
            return str_repeat('*', strlen($digits));
        }

        return '+'.substr($digits, 0, 4).str_repeat('*', 4).substr($digits, -3);
    }
}
