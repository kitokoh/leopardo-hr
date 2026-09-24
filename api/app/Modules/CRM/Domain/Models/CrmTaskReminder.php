<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Issue #5720 — Journal d'idempotence des relances de tâches CRM en retard.
 *
 * Une ligne par (tâche, jour) — contrainte UNIQUE (task_id, remind_date)
 * portée par la migration `2026_08_28_000400_5720_create_crm_task_reminders_table`.
 *
 * EXCEPTION TENANT-SCOPE — journal cross-tenant, exception canonique référencée dans
 * dev-hub/governance/tenant-scope-exceptions.json (#7999).
 *
 * Modèle volontairement minimal : écriture uniquement, depuis le scheduler
 * cross-tenant (`CrmOverdueReminderService`), via `insertOrIgnore` — jamais
 * de lecture sur la surface API. Pas de trait `BelongsToCompany` : le
 * scheduler itère tous les tenants hors contexte tenant (même régime que
 * `crossTenantForSystemTask`, issue #7960) ; `company_id` est renseigné
 * explicitement à l'insert.
 *
 * @property int $id
 * @property string $company_id
 * @property int $task_id
 * @property string $remind_date Date locale entreprise du rappel (Y-m-d, fuseau tenant).
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class CrmTaskReminder extends Model
{
    protected $table = 'crm_task_reminders';

    /**
     * La table ne porte que `created_at` (timestampTz useCurrent) — pas d'`updated_at`.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Insertions en masse contrôlées (payload fixe côté service).
     *
     * @var list<string>
     */
    protected $guarded = [];
}
