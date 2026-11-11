<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\CRM\Domain\Models\CrmTaskReminder;
use Tests\TestCase;

/**
 * #9346 (audit sécurité 2026-11-10, F4) — assignation de masse.
 *
 * #9350 (F8) — l'invariant sur les identifiants SQL géospatiaux est
 * verrouillé par un test unitaire dédié
 * (`tests/Unit/Geo/EloquentNearestSearchIdentifierTest.php`), qui n'a pas
 * besoin de la base ni du conteneur.
 */
class MassAssignmentTest extends TestCase
{
    public function test_reminder_does_not_allow_mass_assignment_of_the_id(): void
    {
        $model = new CrmTaskReminder;

        // Le modèle déclarait `$guarded = []` : tout était assignable, y
        // compris `id` et `company_id` — et il ne porte PAS le trait
        // `BelongsToCompany`, donc aucune réécriture défensive ne s'appliquait.
        $this->assertFalse(
            $model->isFillable('id'),
            'CrmTaskReminder ne doit pas rendre `id` assignable en masse (#9346).',
        );
    }

    public function test_reminder_allows_the_three_columns_its_service_writes(): void
    {
        $model = new CrmTaskReminder;

        foreach (['company_id', 'task_id', 'remind_date'] as $column) {
            $this->assertTrue(
                $model->isFillable($column),
                "CrmTaskReminder doit garder `{$column}` assignable (consommateur : CrmOverdueReminderService).",
            );
        }
    }

    public function test_reminder_rejects_a_forged_primary_key(): void
    {
        $reminder = new CrmTaskReminder;
        // Date relative à now() : une date calendaire figée deviendrait passée
        // et déclencherait le garde #8396 (check-tests-no-frozen-dates).
        $reminder->fill([
            'id' => 999999,
            'company_id' => 1,
            'task_id' => 2,
            'remind_date' => now()->addDay()->toDateString(),
        ]);

        $this->assertNotSame(999999, $reminder->id, 'Un `id` fourni par le client doit être ignoré.');
    }
}
