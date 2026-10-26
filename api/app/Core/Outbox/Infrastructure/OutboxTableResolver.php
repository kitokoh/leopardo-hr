<?php

declare(strict_types=1);

namespace App\Core\Outbox\Infrastructure;

use Illuminate\Support\Facades\DB;

/**
 * #8209 — Resolution du schema reel d'une table d'outbox (garde F-17).
 *
 * Pourquoi ce service existe : la version `PlatformOutboxPublisher` resolvait
 * sa table via `information_schema` codee en dur sur `platform_outbox_events`.
 * Generaliser cette mecanique sans la factoriser aurait produit six copies de
 * la meme requete, chacune avec son propre nom de table en dur — exactement le
 * schema de divergence que cette issue corrige.
 *
 * Le nom de table n'est PAS porte par ce service : le core ne connait aucun
 * modele de module (une telle dependance inverserait le sens des couches et
 * casserait la garde d'isolation). L'appelant fournit le nom, que
 * `DatabaseOutboxPublisher` lit directement sur le modele (`getTable()`).
 */
final class OutboxTableResolver
{
    /**
     * Table qualifiee par le schema reel, ou nom nu si introuvable.
     *
     * On interroge `information_schema` plutot que `resolveTableSchema()`
     * seul : quand l'appelant a positionne `SET search_path TO public` (parcours
     * d'essai self-service), la table — qui vit dans `shared_tenants` — n'est
     * pas dans le search_path et la resolution renvoie null.
     */
    public function qualified(string $table): string
    {
        $schema = $this->schemaOf($table);

        return $schema !== null ? $schema.'.'.$table : $table;
    }

    private function schemaOf(string $table): ?string
    {
        $resolved = resolveTableSchema($table);

        if ($resolved !== null) {
            return $resolved;
        }

        // Priorite aux schemas canoniques, `shared_tenants` d'abord (toutes
        // les outbox y vivent ; `public` n'est qu'un repli de developpement).
        $row = DB::selectOne(
            "SELECT table_schema FROM information_schema.tables
              WHERE table_name = ?
                AND table_schema IN ('shared_tenants', 'public')
              ORDER BY CASE table_schema WHEN 'shared_tenants' THEN 0 ELSE 1 END
              LIMIT 1",
            [$table],
        );

        if ($row === null) {
            return null;
        }

        /** @var array<string, mixed> $record */
        $record = (array) $row;
        $schema = $record['table_schema'] ?? null;

        return is_string($schema) ? $schema : null;
    }
}
