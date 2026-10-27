<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Establishments;

use App\Core\Tenant\Domain\Models\Company;
use App\Shared\Establishments\EstablishmentItem;

/**
 * Hub \u00ab Mes \u00e9tablissements \u00bb (#7910) \u2014 contrat PARTAG\u00c9 d'agr\u00e9gation.
 *
 * M\u00eame patron que {@see \App\Shared\Contracts\Discovery\PublicDiscoverableProvider} :
 * chaque verticale D\u00c9CLARE ses propres \u00e9tablissements dans SON module et tague
 * son impl\u00e9mentation `establishments.provider` depuis son ServiceProvider.
 * L'agr\u00e9gateur {@see \App\Shared\Establishments\EstablishmentAggregationService}
 * ne conna\u00eet alors AUCUNE verticale et n'importe jamais `App\Modules\*`
 * (garde d'isolation #5584).
 *
 * D\u00e9cision de conception (issue #7910, commentaire propri\u00e9taire du 2026-10-26) :
 * le point dur est ce REGISTRE PAR MODULE \u2014 un `switch` central par verticale
 * grossirait \u00e0 chaque nouveau m\u00e9tier. Ajouter une verticale au hub ne doit
 * demander AUCUNE modification du socle : un provider tagu\u00e9 suffit.
 *
 * Invariants (non n\u00e9gociables) :
 *  - isolation tenant : l'impl\u00e9mentation ne lit QUE les lignes de
 *    `$company->id`, jamais une autre soci\u00e9t\u00e9 \u2014 le `Company` est pass\u00e9
 *    explicitement pour que la contrainte soit visible dans la requ\u00eate ;
 *  - fail-closed : une verticale non activ\u00e9e (`hasFeature`) ne d\u00e9clare RIEN ;
 *  - lecture seule : aucune \u00e9criture, aucune nouvelle table.
 */
interface EstablishmentProvider
{
    /**
     * Identifiant canonique de la verticale (`restaurant`, `fuel`, \u2026).
     *
     * Sert de discriminant `vertical` sur chaque item et de cl\u00e9 de
     * regroupement c\u00f4t\u00e9 frontend. Doit \u00eatre stable : c'est un contrat d'API.
     */
    public function vertical(): string;

    /**
     * La verticale est-elle activ\u00e9e pour cette soci\u00e9t\u00e9 ?
     *
     * Fail-closed : une impl\u00e9mentation renvoie `false` par d\u00e9faut si elle ne
     * sait pas r\u00e9pondre. L'agr\u00e9gateur n'interroge `establishments()` que si
     * cette m\u00e9thode retourne `true` \u2014 un module d\u00e9sactiv\u00e9 n'appara\u00eet donc
     * jamais, m\u00eame si ses tables contiennent encore des lignes.
     */
    public function enabledFor(Company $company): bool;

    /**
     * \u00c9tablissements de cette verticale pour la soci\u00e9t\u00e9 donn\u00e9e.
     *
     * @return list<EstablishmentItem>
     */
    public function establishments(Company $company): array;
}
