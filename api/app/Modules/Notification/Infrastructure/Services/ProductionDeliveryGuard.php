<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

/**
 * Production delivery configuration guard (issue #7014, part of #6919).
 *
 * A production deployment must never deliver transactional messages through
 * a sandbox or a no-op transport while reporting success:
 *
 * - `mail.default = log|array` writes messages to a file/array and reports
 *   `queued` — no real email is ever sent;
 * - an SMTP host or a Mailgun domain containing `sandbox` is a provider
 *   test environment (Mailtrap, Mailgun sandbox domain, ...) that absorbs
 *   or refuses production traffic;
 * - a configured WhatsApp provider without both Meta Cloud API secrets
 *   silently falls back to the audit-only provider (PA2-COMM-008) — on
 *   production this means confirmations "go out" without ever reaching
 *   Meta.
 *
 * The guard is intentionally read-only and side-effect free: it only
 * reports issues. The platform health endpoint surfaces them as a
 * non-blocking `degraded` check (`checks.delivery`) so operators and
 * probes can see the misconfiguration without the deploy gates (which
 * only depend on the database check) being affected. Outside the
 * `production` environment the guard is not applicable and reports
 * nothing, keeping the silent audit fallback on dev/staging unchanged.
 */
class ProductionDeliveryGuard
{
    public const ISSUE_MAILER_LOG = 'mailer_log_no_real_delivery';

    public const ISSUE_SMTP_SANDBOX_HOST = 'mailer_smtp_sandbox_host';

    public const ISSUE_MAILGUN_SANDBOX_DOMAIN = 'mailer_mailgun_sandbox_domain';

    public const ISSUE_WHATSAPP_MISSING_SECRETS = 'whatsapp_provider_missing_secrets';

    /**
     * Whether the guard applies to the current environment.
     *
     * The environment is read from `app.env` configuration rather than from
     * `app()->environment()`: the latter returns the value cached on the
     * application instance at bootstrap, so it ignores a configuration change
     * (`config(['app.env' => 'production'])`) made at runtime. This guard is a
     * *configuration* guard — it reports on the configuration it is given, and
     * an operator (or a test) overriding that configuration must see the
     * verdict change. In a real deployment both values come from the same
     * `APP_ENV` variable, so the behaviour is unchanged in production.
     */
    public function isApplicable(): bool
    {
        $configured = config('app.env');

        if (is_string($configured) && $configured !== '') {
            return $configured === 'production';
        }

        return app()->environment('production');
    }

    /**
     * Machine-readable issue slugs detected for the current configuration.
     *
     * @return list<string>
     */
    public function issues(): array
    {
        if (! $this->isApplicable()) {
            return [];
        }

        $issues = [];

        if ($this->mailerIsNoOp()) {
            $issues[] = self::ISSUE_MAILER_LOG;
        }

        $smtpIssue = $this->smtpSandboxIssue();
        if ($smtpIssue !== null) {
            $issues[] = $smtpIssue;
        }

        $mailgunIssue = $this->mailgunSandboxIssue();
        if ($mailgunIssue !== null) {
            $issues[] = $mailgunIssue;
        }

        if ($this->whatsappMissingSecrets()) {
            $issues[] = self::ISSUE_WHATSAPP_MISSING_SECRETS;
        }

        return $issues;
    }

    private function mailerIsNoOp(): bool
    {
        $mailer = (string) config('mail.default', '');

        return in_array($mailer, ['log', 'array'], true);
    }

    private function smtpSandboxIssue(): ?string
    {
        $mailer = (string) config('mail.default', '');
        $host = (string) config('mail.mailers.smtp.host', '');

        if ($mailer !== 'smtp' || $host === '') {
            return null;
        }

        return str_contains(strtolower($host), 'sandbox')
            ? self::ISSUE_SMTP_SANDBOX_HOST
            : null;
    }

    private function mailgunSandboxIssue(): ?string
    {
        $mailer = (string) config('mail.default', '');
        $domain = (string) config('mail.mailers.mailgun.domain', '');

        if ($mailer !== 'mailgun' || $domain === '') {
            return null;
        }

        $normalized = strtolower(ltrim($domain, '.'));

        // Mailgun sandbox domains look like `sandbox1234abcd.mailgun.org`.
        if (str_starts_with($normalized, 'sandbox') || str_contains($normalized, '.sandbox')) {
            return self::ISSUE_MAILGUN_SANDBOX_DOMAIN;
        }

        return null;
    }

    /**
     * WhatsApp is "configured" (real provider requested) but at least one of
     * the two Meta Cloud API secrets is missing: dispatch silently falls
     * back to the audit-only provider (PA2-COMM-008).
     */
    private function whatsappMissingSecrets(): bool
    {
        $configured = (string) config('communication.providers.whatsapp', 'audit');

        if ($configured === 'audit') {
            return false;
        }

        $phoneNumberId = (string) config('services.whatsapp.phone_number_id', '');
        $accessToken = (string) config('services.whatsapp.access_token', '');

        return $phoneNumberId === '' || $accessToken === '';
    }
}
