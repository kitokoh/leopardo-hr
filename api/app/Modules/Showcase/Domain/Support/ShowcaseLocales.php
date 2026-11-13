<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Support;

use Illuminate\Http\Request;

/**
 * Locales supportées par la vitrine publique (BC-27 SHOWCASE, V-I18N #6874).
 *
 * Les 4 langues produit du projet : fr (défaut), en, ar, tr — alignées sur
 * les catalogues `api/lang` et `shared/i18n`. La langue du rendu public est
 * résolue, dans l'ordre :
 *   1. `?lang=xx` explicite (lien partagé / sélecteur) si supportée ;
 *   2. en-tête `Accept-Language` (première langue supportée) ;
 *   3. défaut `fr`.
 *
 * Le contenu de section est résolu par surcharge (`content_i18n[locale]`)
 * avec repli sur `content` (langue par défaut du tenant).
 */
final class ShowcaseLocales
{
    public const DEFAULT = 'fr';

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        return ['fr', 'en', 'ar', 'tr'];
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::supported(), true);
    }

    /**
     * Résout la locale d'une requête publique.
     */
    public static function resolve(Request $request): string
    {
        $explicit = $request->query('lang');

        if (is_string($explicit) && trim($explicit) !== '') {
            $normalized = strtolower(substr(trim($explicit), 0, 2));

            if (self::isSupported($normalized)) {
                return $normalized;
            }

            // #6874 — une locale EXPLICITEMENT demandée mais non supportée ne
            // doit jamais retomber sur une autre langue via `Accept-Language` :
            // le visiteur a exprimé un choix, on sert le défaut du produit
            // (`fr`) plutôt qu'une langue qu'il n'a pas demandée.
            return self::DEFAULT;
        }

        $header = $request->header('Accept-Language');

        if (is_string($header) && $header !== '') {
            // Ex. "ar-DZ,ar;q=0.9,fr;q=0.8" → premier préfixe supporté.
            foreach (explode(',', $header) as $part) {
                $candidate = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));

                if (self::isSupported($candidate)) {
                    return $candidate;
                }
            }
        }

        return self::DEFAULT;
    }

    /**
     * Contenu de section résolu pour une locale : surcharge `content_i18n`
     * sinon `content` (défaut tenant).
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>|null  $contentI18n
     * @return array<string, mixed>
     */
    public static function resolveContent(array $content, ?array $contentI18n, string $locale): array
    {
        if (is_array($contentI18n) && isset($contentI18n[$locale]) && is_array($contentI18n[$locale])) {
            /** @var array<string, mixed> $localized */
            $localized = $contentI18n[$locale];

            return $localized;
        }

        return $content;
    }

    /**
     * Locales effectivement traduites d'une section (pour l'UI d'édition et
     * les balises hreflang).
     *
     * @param  array<string, mixed>|null  $contentI18n
     * @return list<string>
     */
    public static function translatedLocales(?array $contentI18n): array
    {
        if (! is_array($contentI18n)) {
            return [];
        }

        $locales = [];

        foreach (self::supported() as $locale) {
            if (isset($contentI18n[$locale]) && is_array($contentI18n[$locale])) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }
}
