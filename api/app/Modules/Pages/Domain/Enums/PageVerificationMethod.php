<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Enums;

/**
 * Méthodes de vérification de propriété d'une Page (BC PAGES, #8725).
 *
 * La disponibilité par pays est **configurable** (`pages.verification.methods`),
 * parce qu'un SMS n'a pas la même valeur de preuve partout et que les pièces
 * acceptées diffèrent (bail notarié au Sénégal, `Handelsregister` en Allemagne).
 * L'enum ne décide donc pas de ce qui est *disponible* : il décide de ce qui
 * est *implémenté*. La configuration restreint l'ensemble.
 */
enum PageVerificationMethod: string
{
    /** Code à 6 chiffres envoyé au téléphone déclaré de l'établissement. */
    case Sms = 'sms';

    /** Pièces justificatives (bail, facture, registre) relues par la plateforme. */
    case Document = 'document';

    /** Décision manuelle d'un Superadmin (cas particuliers, grand compte). */
    case Superadmin = 'superadmin';

    /**
     * Cette méthode exige-t-elle des pièces ?
     */
    public function requiresEvidence(): bool
    {
        return $this === self::Document;
    }

    /**
     * Cette méthode produit-elle un code à usage unique ?
     */
    public function usesOneTimeCode(): bool
    {
        return $this === self::Sms;
    }

    /**
     * Méthodes activées par configuration.
     *
     * @return list<self>
     */
    public static function enabled(): array
    {
        /** @var mixed $configured */
        $configured = config('pages.verification.methods', ['sms', 'document', 'superadmin']);

        if (! is_array($configured)) {
            return self::cases();
        }

        $enabled = [];
        foreach ($configured as $value) {
            if (! is_string($value)) {
                continue;
            }

            $method = self::tryFrom($value);
            if ($method instanceof self) {
                $enabled[] = $method;
            }
        }

        // Fail-closed : une configuration vide ou entièrement invalide ne doit
        // pas activer toutes les méthodes par accident — elle n'en active aucune,
        // et l'absence de méthode se voit immédiatement en recette.
        return $enabled;
    }

    public static function isEnabled(self $method): bool
    {
        return in_array($method, self::enabled(), true);
    }
}
