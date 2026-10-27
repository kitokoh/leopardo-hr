<?php

declare(strict_types=1);

namespace App\Shared\Support\Persistence;

use App\Shared\Contracts\Persistence\TransactionRunner;
use Illuminate\Support\Facades\DB;

/**
 * Implémentation réelle de {@see TransactionRunner} sur la connexion par défaut.
 *
 * Vit dans `Shared/Support` — pas dans un module : c'est le seul endroit qui a
 * le droit de connaître la facade `DB`, et tous les modules peuvent s'y
 * raccorder sans se connaître entre eux.
 */
final class LaravelTransactionRunner implements TransactionRunner
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed
    {
        // `DB::transaction()` attend une Closure recevant la Connection : on
        // l'enveloppe plutot que de dependre de la signature exacte du contrat
        // expose aux modules (`callable(): T`), qui doit rester neutre.
        return DB::transaction(
            /** @return mixed */
            static fn ($connection) => $callback(),
        );
    }
}
