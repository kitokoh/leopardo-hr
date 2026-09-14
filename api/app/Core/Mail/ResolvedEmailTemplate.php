<?php

declare(strict_types=1);

namespace App\Core\Mail;

/**
 * #7347 — contenu RÉSOLU d'un e-mail (surcharge admin si elle existe, sinon
 * valeur par défaut du catalogue de traduction).
 */
final class ResolvedEmailTemplate
{
    /**
     * @param  array<string, bool>  $overridden  champ => surchargé en base ?
     */
    public function __construct(
        public readonly string $key,
        public readonly string $locale,
        public readonly string $subject,
        public readonly string $heading,
        public readonly string $body,
        public readonly ?string $ctaLabel,
        public readonly array $overridden = [],
    ) {}

    /**
     * Corps prêt à insérer dans le layout : texte ÉCHAPPÉ, retours à la ligne
     * préservés. Une valeur saisie dans l'admin ne peut donc jamais injecter de
     * HTML — c'est du texte, pas un template.
     */
    public function bodyHtml(): string
    {
        return nl2br(e($this->body));
    }

    public function isOverridden(string $field): bool
    {
        return ($this->overridden[$field] ?? false) === true;
    }

    /**
     * Représentation pour l'API d'administration.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'locale' => $this->locale,
            'subject' => $this->subject,
            'heading' => $this->heading,
            'body' => $this->body,
            'cta_label' => $this->ctaLabel,
            'overridden' => $this->overridden,
        ];
    }
}
