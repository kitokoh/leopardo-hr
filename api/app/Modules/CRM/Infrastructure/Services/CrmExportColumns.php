<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Enums\CrmExportEntity;

/**
 * Manifeste des colonnes exportables par entité (issue #5729).
 *
 * Whitelist stricte : l'export ne sérialise JAMAIS une colonne hors liste,
 * et les colonnes PII sensibles (notes privées, etc.) sont exclues par
 * défaut. `label` est l'en-tête CSV lisible.
 */
final class CrmExportColumns
{
    /**
     * @return array<string, array<string, string>>  entity → {colonnes autorisées → label}
     */
    public static function manifest(): array
    {
        return [
            CrmExportEntity::ACCOUNTS => [
                'id' => 'id',
                'name' => 'name',
                'status' => 'status',
                'owner_id' => 'owner_id',
                'industry' => 'industry',
                'website' => 'website',
                'created_at' => 'created_at',
                'updated_at' => 'updated_at',
            ],
            CrmExportEntity::CONTACTS => [
                'id' => 'id',
                'account_id' => 'account_id',
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'email' => 'email',
                'phone' => 'phone',
                'is_primary' => 'is_primary',
                'created_at' => 'created_at',
            ],
            CrmExportEntity::LEADS => [
                'id' => 'id',
                'name' => 'name',
                'source' => 'source',
                'status' => 'status',
                'owner_id' => 'owner_id',
                'created_at' => 'created_at',
            ],
            CrmExportEntity::OPPORTUNITIES => [
                'id' => 'id',
                'account_id' => 'account_id',
                'pipeline_id' => 'pipeline_id',
                'stage' => 'stage',
                'amount' => 'amount',
                'expected_close_at' => 'expected_close_at',
                'created_at' => 'created_at',
            ],
            CrmExportEntity::ACTIVITIES => [
                'id' => 'id',
                'type' => 'type',
                'done_at' => 'done_at',
                'created_at' => 'created_at',
            ],
            CrmExportEntity::TASKS => [
                'id' => 'id',
                'title' => 'title',
                'due_at' => 'due_at',
                'done' => 'done',
                'created_at' => 'created_at',
            ],
        ];
    }

    /**
     * Colonnes autorisées pour une entité (valeurs).
     *
     * @return array<int, string>
     */
    public static function allowedFor(string $entity): array
    {
        return array_keys(self::manifest()[$entity] ?? []);
    }

    public static function isValidColumn(string $entity, string $column): bool
    {
        return in_array($column, self::allowedFor($entity), true);
    }
}
