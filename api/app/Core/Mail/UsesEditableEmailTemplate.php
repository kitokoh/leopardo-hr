<?php

declare(strict_types=1);

namespace App\Core\Mail;

/**
 * #7347 — accès pratique, depuis un Mailable, au contenu éditable d'un e-mail.
 *
 * Permet notamment de rendre le SUJET modifiable depuis l'admin : la valeur
 * résolue remplace la clé de traduction utilisée jusqu'ici dans `envelope()`.
 */
trait UsesEditableEmailTemplate
{
    /**
     * @param  array<string, string>  $variables
     */
    protected function editableEmailTemplate(string $key, ?string $locale = null, array $variables = []): ResolvedEmailTemplate
    {
        return app(EmailTemplateResolver::class)->resolve($key, $locale, $variables);
    }
}
