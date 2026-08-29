<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Contracts;

use App\Modules\CRM\Domain\DTOs\EmailDeliveryResult;
use App\Modules\CRM\Domain\DTOs\EmailMessage;

/**
 * Fournisseur email interchangeable (canal CRM) — Issue #5726.
 *
 * Implémentations : `LogEmailProvider` (défaut, aucun envoi réel — CI/tests)
 * et `MailEmailProvider` (Laravel Mail). Le provider est choisi par
 * `config('crm.email.provider')` (CRM_EMAIL_PROVIDER).
 */
interface EmailProviderInterface
{
    public function send(EmailMessage $message): EmailDeliveryResult;

    /** Identifiant stable du fournisseur (log, mail, sendgrid…). */
    public function providerName(): string;
}
