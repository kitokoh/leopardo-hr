<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * EduManager — Issue #5819 : alignement du NOM de la contrainte FK des classes.
 *
 * CONTEXTE : la table `edu_classes` porte la FK composite
 * `(academic_year_id, company_id) → edu_academic_years(id, company_id)` qui
 * rend une classe cross-tenant impossible. Deux générations de migrations
 * l'ont déclarée sous deux noms différents :
 *
 *   - `edu_classes_academic_year_company_fk` (migration canonique
 *     `2026_08_30_000202_5819_create_edu_classes_table`) — nom réellement
 *     appliqué, donc celui présent en base ;
 *   - `edu_classes_year_company_fk` (générations groupées `..._0007xx_`,
 *     `..._0015xx_`, `2026_08_31_000205_`) — nom attendu par les services et
 *     vérifié par les tests (`EduAcademicYearServiceTest::test_class_cannot_
 *     reference_another_tenant_year` assertait le nom dans le message
 *     d'erreur PostgreSQL).
 *
 * La garde d'idempotence fait gagner la PREMIÈRE migration : le nom en base
 * est celui de la génération canonique. Comme une base de production a déjà
 * enregistré cette migration, corriger le fichier ne suffit pas — d'où ce
 * renommage.
 *
 * DÉCISION : renommer la contrainte vers le nom attendu par le code (le
 * renommage d'une contrainte est non destructif : aucune donnée, aucune
 * ligne, aucune sémantique de vérification ne change). Les deux noms
 * désignent exactement les mêmes colonnes et la même table parente.
 *
 * Idempotente et sûre en production :
 *   - no-op si l'ancienne contrainte est absente (déjà renommée) ;
 *   - no-op si le nom cible est DÉJÀ pris (une migration ultérieure a gagné) ;
 *   - schéma résolu par le search_path (helpers F-17 #1593/#1613).
 */
return new class extends Migration
{
    private const TABLE = 'edu_classes';

    private const OLD_NAME = 'edu_classes_academic_year_company_fk';

    private const NEW_NAME = 'edu_classes_year_company_fk';

    public function up(): void
    {
        $this->renameIfNeeded(self::OLD_NAME, self::NEW_NAME);
    }

    public function down(): void
    {
        $this->renameIfNeeded(self::NEW_NAME, self::OLD_NAME);
    }

    private function renameIfNeeded(string $from, string $to): void
    {
        if (! schemaTableExists(self::TABLE)) {
            return;
        }

        if ($this->constraintExists($to)) {
            return; // nom cible déjà en place → no-op
        }

        if (! $this->constraintExists($from)) {
            return; // contrainte absente (base fraîche post-correctif) → no-op
        }

        $schema = resolveTableSchema(self::TABLE);
        $qualified = ($schema !== null ? $this->quote($schema).'.' : '').$this->quote(self::TABLE);

        DB::statement(sprintf(
            'ALTER TABLE %s RENAME CONSTRAINT %s TO %s',
            $qualified,
            $this->quote($from),
            $this->quote($to),
        ));
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne(
            'SELECT 1 AS present
               FROM pg_constraint
              WHERE conname = ?
                AND conrelid = (SELECT oid FROM pg_class WHERE relname = ? AND relkind = ?)
              LIMIT 1',
            [$name, self::TABLE, 'r']
        );

        return $row !== null;
    }

    private function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
