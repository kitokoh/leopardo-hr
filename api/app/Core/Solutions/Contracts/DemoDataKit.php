<?php

declare(strict_types=1);

namespace App\Core\Solutions\Contracts;

use App\Core\Tenant\Domain\Models\Company;

/**
 * Kit de données de démonstration d'une solution sectorielle (#7865).
 *
 * Un kit installe un jeu de données synthétiques NON SENSIBLES pour un
 * tenant (référentiel, enregistrements d'exemple) afin que le client
 * découvre la verticale avec un espace « vivant » plutôt que vide.
 *
 * Contrat exigé des implémentations (mêmes garanties que les seeders
 * existants RESTO-107 / TRAVEL-107) :
 *  - idempotence : rejouer `seed()` ne crée jamais de doublon ;
 *  - tenant-scoped : aucune écriture hors du tenant reçu en argument.
 */
interface DemoDataKit
{
    /** Installe le jeu de démonstration pour la société donnée (idempotent). */
    public function seed(Company $company): void;
}
