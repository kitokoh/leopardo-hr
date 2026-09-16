<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Notifications\Contracts\InAppNotifier;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7481 — UNE SEULE source de vérité pour la notification in-app.
 *
 * Ce test verrouille la règle, pas seulement le code : tout ce qui publie une
 * notification in-app — le port transversal `InAppNotifier` (utilisé par les
 * modules métier sans import cross-BC), `SendNotification`, le dispatcher —
 * doit atterrir dans **`notifications`**, le store que sert
 * `GET /api/v1/notifications` (donc le web, le mobile et l'assistant).
 *
 * Avant #7481, le dispatcher écrivait `app_notifications` : la notification
 * n'apparaissait NULLE PART dans la boîte de réception de l'utilisateur, et
 * elle échappait aux préférences, aux heures calmes, aux quotas et à l'audit.
 */
class NotificationSingleStoreTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_a_dispatched_notification_is_visible_in_the_api_inbox_and_can_be_marked_read(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        // Chemin réel des modules métier : le port transversal, jamais le
        // modèle directement (garde cross-module #5584).
        app(InAppNotifier::class)->dispatch(
            userId: $employee->id,
            type: 'camera_alert',
            title: 'Mouvement détecté',
            body: 'Caméra entrée — 03:12',
            data: ['camera_id' => 3],
            actionUrl: '/cameras/3',
        );

        $list = $this->getJson('/api/v1/notifications')->assertOk();
        $list->assertJsonPath('data.0.type', 'camera_alert');
        $list->assertJsonPath('data.0.title', 'Mouvement détecté');
        $list->assertJsonPath('data.0.is_read', false);
        // `action_url` n'a pas de colonne dédiée : elle doit rester lisible.
        $list->assertJsonPath('data.0.data.action_url', '/cameras/3');

        $id = (int) $list->json('data.0.id');

        // Elle compte bien comme non lue sur la surface dédiée.
        $this->getJson('/api/v1/notifications/unread')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->patchJson("/api/v1/notifications/{$id}/read")->assertOk();

        $this->assertSame(
            0,
            Notification::query()->where('employee_id', $employee->id)->where('is_read', false)->count(),
            'Marquer comme lue doit viser le store canonique (sinon la boîte de réception reste éternellement non lue).'
        );

        // Et AUCUNE écriture parallèle dans le store déprécié : une double
        // écriture recréerait deux vérités à diverger.
        $this->assertDatabaseCount('app_notifications', 0);
    }

    public function test_the_assistant_reads_the_same_store_as_the_inbox(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        app(InAppNotifier::class)->dispatch(
            userId: $employee->id,
            type: 'payroll_ready',
            title: 'Bulletin disponible',
            body: 'Votre bulletin est prêt.',
        );

        // Le même store que la boîte de réception : l'assistant ne doit pas
        // annoncer des notifications que l'utilisateur ne voit pas (ni
        // l'inverse).
        $this->assertSame(1, Notification::query()
            ->where('employee_id', $employee->id)
            ->where('is_read', false)
            ->count());

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'payroll_ready');
    }
}
