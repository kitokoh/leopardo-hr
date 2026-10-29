<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Support;

use App\Modules\Showcase\Domain\Enums\PageProjectionStatus;

/**
 * #8727 — Politique de DOUBLE LECTURE `company_showcases` ↔ `pages`.
 *
 * ## Ce que ce service décide, et ce qu'il ne décide pas
 *
 * Il ne lit rien et n'écrit rien : il répond à trois questions pures, ce qui
 * rend la politique testable sans base.
 *
 * 1. **Faut-il doubler cette vitrine ?** (`shouldSample`)
 * 2. **Les deux lectures sont-elles d'accord ?** (`compare`)
 * 3. **Que vaut-il la peine de signaler ?** (`summary`)
 *
 * ## Pourquoi un échantillonnage déterministe par slug
 *
 * Un tirage aléatoire par requête aurait deux défauts rédhibitoires en recette
 * comme en production : une même vitrine serait parfois doublée, parfois non
 * (les divergences deviennent irreproductibles), et monter le pourcentage
 * pourrait *retirer* des vitrines du panel, ce qui rendrait la progression du
 * rollout non monotone. Le hash du slug est stable : monter
 * `rollout_percent` ne fait qu'**ajouter** des vitrines.
 *
 * ## Pourquoi la comparaison ne modifie jamais la réponse
 *
 * Une double lecture qui changerait la réponse publique rendrait impossible
 * d'attribuer une divergence au legacy ou à la projection : on ne saurait plus
 * ce que l'utilisateur a vu. Ici la réponse reste **toujours** celle du legacy
 * — la source de vérité — et l'écart est seulement observé. C'est aussi ce qui
 * permet d'activer la double lecture en production sans risque produit.
 *
 * ## Ce qui est comparé, et pourquoi seulement cela
 *
 * Les trois axes qui, s'ils divergent, changent ce que voit un visiteur :
 * le **statut** (une vitrine publiée servie comme brouillon, ou l'inverse),
 * le **slug** (l'URL canonique) et le **nombre de sections** (contenu
 * manquant ou dupliqué). Les identifiants techniques (`id`, `page_id`) ne
 * sont volontairement pas comparés : ils diffèrent par construction.
 */
final class ShowcaseDoubleReadPolicy
{
    /**
     * Une vitrine entre-t-elle dans le panel de double lecture ?
     *
     * @param  int  $rolloutPercent  pourcentage demandé (0–100) ; 0 désactive
     */
    public static function shouldSample(string $slug, int $rolloutPercent): bool
    {
        if ($rolloutPercent <= 0) {
            return false;
        }

        if ($rolloutPercent >= 100) {
            return true;
        }

        // `crc32b` est stable entre langages/hôtes et suffisant : on ne défend
        // rien contre un adversaire, on répartit des vitrines.
        $bucket = (int) hexdec(substr(hash('crc32b', $slug), 0, 4)) % 100;

        return $bucket < $rolloutPercent;
    }

    /**
     * Compare la lecture legacy (ce qui est servi) et la lecture Pages.
     *
     * @param  array{status: string, slug: string, sections: int}  $legacy
     * @param  array{status: string, slug: string, sections: int}|null  $projection  null = aucune Page
     * @return list<string> liste vide = parité
     */
    public static function compare(array $legacy, ?array $projection): array
    {
        if ($projection === null) {
            // Cas normal et attendu : vitrine non encore backfillée. Ce n'est
            // PAS une divergence — sinon la double lecture signalerait du bruit
            // sur la totalité du parc avant le premier backfill.
            return [];
        }

        $divergences = [];

        if ($legacy['status'] !== $projection['status']) {
            $divergences[] = sprintf(
                'status: legacy=%s pages=%s',
                $legacy['status'],
                $projection['status'],
            );
        }

        if ($legacy['slug'] !== $projection['slug']) {
            $divergences[] = sprintf(
                'slug: legacy=%s pages=%s',
                $legacy['slug'],
                $projection['slug'],
            );
        }

        if ($legacy['sections'] !== $projection['sections']) {
            $divergences[] = sprintf(
                'sections: legacy=%d pages=%d',
                $legacy['sections'],
                $projection['sections'],
            );
        }

        return $divergences;
    }

    /**
     * Statut de Page attendu pour un statut de vitrine.
     *
     * Le Domain de Showcase ne connaît **pas** `Modules\Pages` (garde #5584) :
     * c'est `PageProjectionStatus` qui porte les deux valeurs, avec les mêmes
     * chaînes que `PageStatus`. La traduction vers l'enum de Pages se fait en
     * Infrastructure, là où l'écriture existe.
     */
    public static function expectedPageStatus(string $showcaseStatus): string
    {
        return $showcaseStatus === 'published'
            ? PageProjectionStatus::Published->value
            : PageProjectionStatus::Draft->value;
    }

    /**
     * Résumé lisible d'un échantillon, pour un log ou une ligne de rapport.
     *
     * @param  list<string>  $divergences
     */
    public static function summary(string $slug, array $divergences): string
    {
        if ($divergences === []) {
            return sprintf('showcase:%s double-read=parity', $slug);
        }

        return sprintf(
            'showcase:%s double-read=DIVERGENCE (%s)',
            $slug,
            implode('; ', $divergences),
        );
    }
}
