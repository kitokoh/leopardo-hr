<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Enums\CrmExportEntity;
use App\Modules\CRM\Domain\Exceptions\CrmExportEntityUnavailableException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source de données des exports CRM (issue #5729).
 *
 * Résout table + query builder par entité, tenant-scopé par `company_id`.
 * Garde `schemaTableExists()` : tant que le socle V0 (migrations #5708/#5709/
 * #5710) n'est pas mergé sur l'environnement, l'entité lève
 * CrmExportEntityUnavailableException (l'export est marqué failed avec un
 * message explicite, jamais un 500 silencieux).
 */
final class CrmExportSource
{
    /** @var array<string, string> */
    private const TABLE_BY_ENTITY = [
        CrmExportEntity::ACCOUNTS => 'crm_accounts',
        CrmExportEntity::CONTACTS => 'crm_contacts',
        CrmExportEntity::LEADS => 'crm_leads',
        CrmExportEntity::OPPORTUNITIES => 'crm_opportunities',
        CrmExportEntity::ACTIVITIES => 'crm_activities',
        CrmExportEntity::TASKS => 'crm_tasks',
    ];

    public function tableFor(string $entity): string
    {
        return self::TABLE_BY_ENTITY[$entity] ?? throw new CrmExportEntityUnavailableException();
    }

    public function assertAvailable(string $entity): void
    {
        if (! isset(self::TABLE_BY_ENTITY[$entity]) || ! Schema::hasTable(self::TABLE_BY_ENTITY[$entity])) {
            throw new CrmExportEntityUnavailableException();
        }
    }

    /**
     * Query builder tenant-scopé pour une entité.
     *
     * @param  array<string, mixed>  $filters
     */
    public function query(string $entity, array $filters = []): Builder
    {
        $this->assertAvailable($entity);
        $table = $this->tableFor($entity);

        $query = DB::table($table)->where('company_id', currentCompany()->id);

        if (isset($filters['status']) && is_string($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['owner_id']) && is_string($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        return $query;
    }
}
