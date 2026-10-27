<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * #8723 — une Page ne peut être rattachée qu'à un établissement du MÊME tenant.
 *
 * Deux cas distincts, deux codes :
 *  - `PAGE_SITE_NOT_FOUND` : `site_id` inexistant (le site a été supprimé ou
 *    l'identifiant est forgé) — 422, la requête est compréhensible mais la
 *    référence est invalide ;
 *  - `PAGE_SITE_CROSS_TENANT` : le site existe mais appartient à une autre
 *    société — 403, c'est une tentative d'accès cross-tenant, pas une faute de
 *    saisie.
 *
 * Le second cas est celui qui compte : sans cette garde, le scope global
 * `BelongsToCompany` (qui filtre `pages.company_id`) laisserait passer une
 * Page du tenant A pointant l'établissement du tenant B, et la surface
 * publique afficherait les données de B sous le nom de A.
 */
final class PageTenantMismatchException extends DomainException
{
    public const CODE_MISSING_SITE = 'PAGE_SITE_NOT_FOUND';

    public const CODE_CROSS_TENANT = 'PAGE_SITE_CROSS_TENANT';

    public static function missingSite(int $siteId): self
    {
        return new self(
            sprintf('Établissement introuvable pour la Page (site_id=%d).', $siteId),
            422,
            self::CODE_MISSING_SITE,
        );
    }

    public static function crossTenant(string $companyId, int $siteId): self
    {
        return new self(
            sprintf(
                'L\'établissement %d n\'appartient pas à la société %s : une Page ne peut pas être rattachée au lieu d\'un autre tenant.',
                $siteId,
                $companyId,
            ),
            403,
            self::CODE_CROSS_TENANT,
        );
    }
}
