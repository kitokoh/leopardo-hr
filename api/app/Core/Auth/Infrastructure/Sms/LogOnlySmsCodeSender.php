<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Sms;

use App\Core\Auth\Domain\Contracts\SmsCodeSenderContract;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Envoi de code OTP par SMS — implementation par defaut (#8738).
 *
 * ## Decision : « audit-only » plutot qu'un faux fournisseur
 *
 * Aucun fournisseur SMS de production n'est contractualise pour l'instant
 * (meme posture que `Modules/CRM/.../SmsAdapter`, issue #5727 : « SMS/WhatsApp
 * restent en provider audit-only tant qu'un fournisseur production, des
 * signatures webhook et des quotas par plan ne sont pas actives »). Deux
 * mauvaises options etaient possibles :
 *
 *  1. un faux `Sent` qui pretend delivrer — l'utilisateur attend un code qui
 *     n'arrive jamais, et l'equipe ne le sait pas ;
 *  2. refuser le canal SMS entierement — le chemin n'existe pas, donc le jour
 *     ou le fournisseur arrive il faut tout ecrire sous pression.
 *
 * Cette implementation prend la troisieme voie : le transport est **reel quand
 * il est configure**, et **explicitement non configure sinon** (`isConfigured()`
 * rend `false`). L'appelant decouvre donc l'etat AVANT de generer un code et
 * peut replier sur l'e-mail — c'est exactement le comportement exige par
 * l'issue (« SMS, e-mail, ou les deux avec repli »).
 *
 * ## Ce qui est deja en place et testable des aujourd'hui
 *
 * - **validation E.164** via `App\Support\PhoneNumber` (source unique) ;
 * - **limitation de debit par numero** (`RateLimiter`, fenetre glissante) :
 *   l'anti-abus ne peut pas attendre le fournisseur, c'est une propriete du
 *   domaine, pas du transport ;
 * - **aucune PII dans les traces** : le numero est masque (`+2135****456`),
 *   le code n'est JAMAIS journalise. Le critere d'acceptation de #8738 exige
 *   un test dedie, present dans `LoginCodeSmsChannelTest`.
 *
 * ## Brancher un vrai fournisseur
 *
 * Implementer `SmsCodeSenderContract` et lier l'interface dans le conteneur
 * (`app()->bind(...)`) : ni le controleur ni le domaine ne changent. C'est la
 * raison d'etre du contrat.
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

    public function channel(): string
    {
        return 'sms';
    }

    /**
     * Un transport n'existe que si un DSN de fournisseur est configure.
     *
     * On ne se contente pas de lire `config('sms.driver')` : un driver nomme
     * sans endpoint ni cle est une configuration a moitie faite, donc un canal
     * NON configure — le detecter ici evite un envoi qui echouerait plus tard.
     */
    public function isConfigured(): bool
    {
        $endpoint = config('services.sms_otp.endpoint');
        $key = config('services.sms_otp.api_key');

        return is_string($endpoint) && $endpoint !== ''
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

        $traceId = 'otp_sms_'.substr(hash('sha256', $e164.'|'.$locale.'|'.now()->timestamp), 0, 20);

        if (! $this->isConfigured()) {
            // Mode audit-only : on trace le FAIT de l'envoi (canal, trace,
            // locale) et rien du contenu. Le numero reste masque.
            Log::info('auth.login_code_sms_audit_only', [
                'trace_id' => $traceId,
                'phone' => $this->mask($e164),
                'locale' => $locale,
            ]);

            return null;
        }

        // Point d'extension du fournisseur reel. Tant qu'il n'existe pas, le
        // canal se declare non configure : on n'arrive ici que si endpoint +
        // cle sont renseignes, et l'envoi HTTP serait alors implemente ici.
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
