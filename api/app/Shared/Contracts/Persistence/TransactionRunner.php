<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Persistence;

/**
 * Exécution d'un bloc dans une transaction atomique.
 *
 * ## Pourquoi un port et pas `DB::transaction()` directement
 *
 * La garde `check-layer-purity.sh` (#6568) interdit les facades Laravel dans
 * `Domain/` : le domaine décrit des règles métier, il ne doit pas savoir que
 * la persistance est Eloquent. Ce port renverse la dépendance — le service de
 * domaine reçoit une capacité abstraite, l'infrastructure en fournit
 * l'implémentation réelle.
 *
 * Bénéfice concret au-delà de la conformité : un test unitaire peut brancher
 * une implémentation immédiate qui exécute le bloc tel quel, sans base de
 * données ni rollback — ce qui rend la règle métier testable seule.
 */
interface TransactionRunner
{
    /**
     * Exécute `$callback` de façon atomique et renvoie sa valeur de retour.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}
