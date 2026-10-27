<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Blocks;

/**
 * Contrat qu'un BC vertical implémente pour fournir SES blocs à Pages
 * (BC PAGES, #8724).
 *
 * ## Le problème que ce port résout
 *
 * Showcase (BC-27) valide son contenu contre un **registre fermé**
 * (`ShowcaseSectionSchemaRegistry::schemas()`) : types connus à la compilation,
 * un `match`/`enum` central, et pour ajouter un type il faut modifier le module
 * Showcase lui-même. C'est acceptable pour une vitrine unique par tenant, mais
 * impossible pour Pages : chaque vertical (restaurant, retail, fuel, travel)
 * doit pouvoir publier ses propres blocs (menu, catalogue, horaires de pompe)
 * **sans que Pages importe ses tables ni son code**.
 *
 * Ce port inverse la dépendance : Pages possède le contenant, le validateur et
 * l'orchestrateur ; le vertical possède son schéma et l'enregistre au boot
 * (tag de conteneur `pages.block_providers`). Pages ne connaît jamais la classe
 * concrète du fournisseur.
 *
 * ## Ce que le contrat impose (et pourquoi)
 *
 * Un identifiant **qualifié** (`<bc>.<nom>`, ex. `core.hero`, `restaurant.menu`)
 * est obligatoire : un type non qualifié (`hero`) garantit à terme une
 * collision entre deux verticaux, et une collision de type signifie qu'un
 * contenu est interprété par le mauvais schéma — donc potentiellement rendu
 * avec un renderer qui n'attend pas cette forme.
 *
 * Le `schemaVersion` est porté par le fournisseur ET par chaque bloc stocké :
 * un bloc écrit sous la v1 doit rester lisible quand le contrat passe en v2,
 * ou être explicitement rapporté comme non pris en charge. Jamais silencieusement
 * réinterprété.
 */
interface PageBlockProvider
{
    /**
     * Le contexte borné propriétaire (ex. `core`, `restaurant`, `retail`).
     *
     * Sert au diagnostic et à l'allowlist d'activation par vertical : une
     * plateforme qui n'a pas activé le BC Travel ne doit pas rendre ses blocs.
     */
    public function ownerBc(): string;

    /**
     * Les types de blocs fournis, indexés par identifiant qualifié.
     *
     * La clé DOIT être préfixée par `ownerBc()` + `.` — le registre le vérifie
     * et refuse un fournisseur qui déborde de son espace de noms.
     *
     * @return array<string, PageBlockTypeDefinition>
     */
    public function blockTypes(): array;
}
