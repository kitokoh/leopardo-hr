<?php

declare(strict_types=1);

namespace App\Core\Mail;

use Closure;

/**
 * #9317 — diagnostic du transport e-mail RÉELLEMENT résolu.
 *
 * Le 2026-11-10, `POST /api/v1/trial/signup` répondait 503
 * `TRIAL_OTP_SEND_FAILED` en production pendant que `GET /health/ready`
 * rapportait `mail: {ok: true, status: "skipped", mailer: "resend"}`.
 *
 * La cause n'était pas le transport lui-même mais le DIAGNOSTIC : chaque
 * sonde ne connaissait qu'une liste fermée de transports (`smtp` pour
 * `HealthController::checkMail()`, `log`/`array`/`smtp`/`mailgun` pour
 * `ProductionDeliveryGuard`, `mailgun`/`smtp`/`ses` pour `mail:check-config`).
 * Tout transport hors liste — dont `resend`, celui de la production — était
 * classé « rien à sonder » et rapporté `ok`. Un mailer cassé produisait donc
 * un faux vert, exactement le trou que #7385 prétendait avoir comblé.
 *
 * Cette classe est la source UNIQUE de la résolution transport → credentials
 * attendus, partagée par les trois consommateurs, pour qu'un transport
 * nouvellement adopté ne puisse plus être silencieusement ignoré.
 *
 * Elle n'expose JAMAIS de secret : uniquement des présences et des slugs.
 */
final class MailTransportDiagnostics
{
    /**
     * Transports sans dépendance externe : rien à sonder, `skipped` est
     * l'état honnête (même logique que `checkRedis()` quand Redis n'est pas
     * voulu).
     *
     * @var list<string>
     */
    public const LOCAL_TRANSPORTS = ['log', 'array', 'null'];

    /**
     * Transports HTTP-API dont le domaine d'envoi est porté par l'adresse
     * expéditrice (`MAIL_FROM_ADDRESS`) et doit être vérifié chez le
     * fournisseur. `mailgun` en est volontairement ABSENT : son domaine
     * d'envoi vit dans `mail.mailers.mailgun.domain`, pas dans l'expéditeur —
     * sonder l'expéditeur y produirait un faux positif.
     *
     * @var list<string>
     */
    public const FROM_DOMAIN_TRANSPORTS = ['resend'];

    /**
     * @param  Closure(string): bool|null  $resolver  Résolveur DNS injectable
     *                                                (tests) ; par défaut le
     *                                                résolveur système.
     */
    public function __construct(
        private readonly ?Closure $resolver = null,
    ) {}

    /** Mailer résolu (`MAIL_MAILER`), jamais vide. */
    public function resolvedMailer(): string
    {
        $mailer = config('mail.default', 'log');

        return is_scalar($mailer) && (string) $mailer !== '' ? (string) $mailer : 'log';
    }

    /**
     * Transport effectif du mailer résolu. `mail.mailers.<mailer>.transport`
     * prime ; à défaut le nom du mailer fait office de transport (convention
     * Laravel pour `log`, `array`, `resend`…).
     */
    public function resolvedTransport(): string
    {
        $mailer = $this->resolvedMailer();
        $config = config("mail.mailers.{$mailer}", []);
        $transport = is_array($config) ? ($config['transport'] ?? $mailer) : $mailer;

        return is_scalar($transport) && (string) $transport !== '' ? (string) $transport : $mailer;
    }

    public function isLocalTransport(): bool
    {
        return in_array($this->resolvedTransport(), self::LOCAL_TRANSPORTS, true);
    }

    /** Le domaine d'envoi de ce transport est-il porté par l'expéditeur ? */
    public function requiresVerifiedSendingDomain(): bool
    {
        return in_array($this->resolvedTransport(), self::FROM_DOMAIN_TRANSPORTS, true);
    }

    /**
     * Clé API Resend. Deux emplacements légitimes : `services.resend.key`
     * (convention Laravel du dépôt) et `resend.api_key` (config publiée par
     * `resend/resend-laravel`). Le package lit les deux — on fait de même,
     * sinon une clé posée au bon endroit serait rapportée absente.
     */
    public function resendKey(): string
    {
        foreach (['services.resend.key', 'resend.api_key'] as $key) {
            $value = config($key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    public function fromAddress(): string
    {
        $value = config('mail.from.address', '');

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** Domaine de l'expéditeur, en minuscules (`''` si l'adresse est inexploitable). */
    public function fromDomain(): string
    {
        $address = $this->fromAddress();

        if ($address === '' || ! str_contains($address, '@')) {
            return '';
        }

        $domain = substr($address, (int) strrpos($address, '@') + 1);

        return strtolower(trim($domain));
    }

    /**
     * Variables requises par transport (clé env => clé de config
     * `mail.mailers.<transport>`). `resend` n'y figure pas : sa clé vit dans
     * `services.resend.key`, traitée à part par {@see missingRequiredEnv()}.
     *
     * @return array<string, array<string, string>>
     */
    public function requiredEnvByTransport(): array
    {
        return [
            'mailgun' => ['MAILGUN_DOMAIN' => 'domain', 'MAILGUN_SECRET' => 'secret'],
            'smtp' => ['MAIL_HOST' => 'host', 'MAIL_PORT' => 'port', 'MAIL_USERNAME' => 'username'],
            'ses' => ['AWS_ACCESS_KEY_ID' => 'key', 'AWS_SECRET_ACCESS_KEY' => 'secret'],
        ];
    }

    /**
     * Credentials manquants pour le transport RÉELLEMENT résolu.
     *
     * @return list<string> messages lisibles, jamais de secret
     */
    public function missingRequiredEnv(): array
    {
        $transport = $this->resolvedTransport();
        $issues = [];

        if ($transport === 'resend' && $this->resendKey() === '') {
            $issues[] = "RESEND_KEY absent/vide — le transport 'resend' échouera à l'envoi (vérifier l'env du service).";
        }

        $required = $this->requiredEnvByTransport();

        if (isset($required[$transport])) {
            $config = config("mail.mailers.{$transport}", []);
            $config = is_array($config) ? $config : [];

            foreach ($required[$transport] as $envKey => $cfgKey) {
                if (! filled($config[$cfgKey] ?? null)) {
                    $issues[] = "{$envKey} absent/vide — le transport '{$transport}' échouera à l'envoi (vérifier l'env du service).";
                }
            }
        }

        return $issues;
    }

    /**
     * Slug machine du premier credential manquant, pour `checks.mail.error`.
     * Un slug stable est exploitable par une sonde ; un message localisé ne
     * l'est pas.
     */
    public function missingCredentialSlug(): ?string
    {
        $transport = $this->resolvedTransport();

        if ($transport === 'resend' && $this->resendKey() === '') {
            return 'RESEND_KEY_MISSING';
        }

        $required = $this->requiredEnvByTransport();

        if (isset($required[$transport])) {
            $config = config("mail.mailers.{$transport}", []);
            $config = is_array($config) ? $config : [];

            foreach ($required[$transport] as $envKey => $cfgKey) {
                if (! filled($config[$cfgKey] ?? null)) {
                    return $envKey.'_MISSING';
                }
            }
        }

        return null;
    }

    /**
     * Le domaine d'envoi existe-t-il dans le DNS ?
     *
     * Un domaine inexistant (NXDOMAIN) ne peut pas être vérifié chez le
     * fournisseur : aucun envoi ne partira, quelle que soit la validité de la
     * clé API. C'est le cas mesuré en production — `MAIL_FROM_ADDRESS` pointait
     * sur `leopardo-rh.com`, NXDOMAIN (cf. #8062).
     *
     * `checkdnsrr()` interroge le résolveur système (borné par sa propre
     * configuration, et mis en cache) : on teste `A` puis `MX`, car un domaine
     * d'envoi peut n'avoir qu'un enregistrement de messagerie.
     */
    public function domainResolves(string $domain): bool
    {
        if ($domain === '') {
            return false;
        }

        if ($this->resolver !== null) {
            return (bool) ($this->resolver)($domain);
        }

        return checkdnsrr($domain, 'A') || checkdnsrr($domain, 'MX');
    }
}
