<?php

declare(strict_types=1);

namespace App\Core\Mail;

/**
 * #7346 — valeurs techniques de la charte e-mail.
 *
 * Elles vivent en PHP (et non dans le layout) pour deux raisons :
 *   - ce sont des constantes techniques (préfixe `mailto:`, viewport), pas du
 *     contenu localisé : les écrire dans un template ferait apparaître un
 *     littéral à la garde i18n, qui surveille `resources/views/emails` ;
 *   - un seul endroit à changer si la marque évolue.
 *
 * Aucun texte destiné au destinataire ici : tout le contenu passe par le
 * catalogue (`api/lang/*`).
 */
final class MailBrand
{
    public static function name(): string
    {
        return (string) config('mail.brand.name');
    }

    public static function fontStack(): string
    {
        return (string) config('mail.brand.font_stack');
    }

    public static function supportHref(): string
    {
        return 'mailto:'.config('mail.brand.support_address');
    }

    public static function viewport(): string
    {
        return 'width=device-width,initial-scale=1';
    }
}
