<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Jobs\DispatchCommunicationJob;
use App\Modules\Cameras\Domain\Models\Camera;
use App\Modules\Cameras\Domain\Models\CameraAlert;
use App\Modules\Cameras\Domain\Models\CameraEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Événements & alertes caméra (issue #7427, BC-19).
 *
 * Trois responsabilités, aucune invention de données :
 *  - `recordEvent()` : persiste un événement **réellement détecté** par la
 *    chaîne vidéo (mouvement/personne/véhicule) ;
 *  - `raiseAlert()` : crée l'alerte manager correspondante, **dédupliquée** par
 *    `alert_key` (une rafale de mouvement sur une caméra ne produit qu'une
 *    alerte — et un seul push — par fenêtre de regroupement) puis notifie ;
 *  - `acknowledge()` / `resolve()` : cycle de vie de l'alerte.
 *
 * Diffusion : `DispatchCommunicationJob` → `CommunicationService::notifyEmployee`
 * avec le gabarit `camera_security_alert` et la catégorie **`security`**
 * (`config/communication.php` → `quiet_hours.bypass_categories`) : une intrusion
 * n'est jamais muette pendant les heures calmes. L'audit de chaque envoi
 * (`CommunicationEvent`) et la visibilité des échecs sont assurés par
 * `CommunicationService` / `DispatchCommunicationJob` — rien n'est réimplémenté
 * ici, et aucun module n'est importé en croisé (isolation #5584).
 *
 * Aucune PII dans les payloads : identifiants, type, sévérité et horodatage
 * uniquement (jamais de visage, plaque ou image — voir
 * `docs/GESTION_PROJET/CAMERAS_ALERTES_RETENTION_RGPD.md`).
 */
final class CameraAlertService
{
    /**
     * Fenêtre de regroupement par défaut (minutes) : deux détections du même
     * type sur la même caméra dans la même fenêtre = une seule alerte.
     */
    public const DEDUP_WINDOW_MINUTES = 5;

    /** Nombre maximal de managers notifiés (borne anti-tempête). */
    private const MAX_MANAGERS = 10;

    /**
     * Enregistre un événement détecté par la chaîne vidéo.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordEvent(
        string $companyId,
        int $cameraId,
        string $type,
        string $severity,
        ?Carbon $detectedAt = null,
        ?string $snapshotPath = null,
        array $metadata = [],
    ): CameraEvent {
        /** @var CameraEvent $event */
        $event = CameraEvent::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'camera_id' => $cameraId,
            'type' => $type,
            'severity' => $severity,
            'detected_at' => $detectedAt ?? Carbon::now('UTC'),
            'snapshot_path' => $snapshotPath,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);

        return $event;
    }

    /**
     * Crée l'alerte dédupliquée associée à un événement, puis notifie les
     * managers. Un rejeu (re-ingestion du même événement dans la fenêtre de
     * regroupement) ne crée ni alerte ni notification supplémentaire.
     *
     * @return array{alert: CameraAlert, created: bool, notified: int}
     */
    public function raiseAlert(CameraEvent $event, int $windowMinutes = self::DEDUP_WINDOW_MINUTES): array
    {
        $companyId = (string) $event->company_id;
        $alertKey = $this->alertKey($event, $windowMinutes);

        $existing = CameraAlert::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('alert_key', $alertKey)
            ->first();

        if ($existing instanceof CameraAlert) {
            return ['alert' => $existing, 'created' => false, 'notified' => 0];
        }

        try {
            /** @var CameraAlert $alert */
            $alert = CameraAlert::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'camera_id' => $event->camera_id,
                'camera_event_id' => $event->id,
                'type' => $event->type,
                'severity' => $event->severity,
                'alert_key' => $alertKey,
                'payload' => $this->payload($event),
                'status' => CameraAlert::STATUS_OPEN,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Course entre deux ingestions : la contrainte unique a arbitré,
            // le perdant ne re-notifie pas.
            $alert = CameraAlert::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('alert_key', $alertKey)
                ->firstOrFail();

            return ['alert' => $alert, 'created' => false, 'notified' => 0];
        }

        return ['alert' => $alert, 'created' => true, 'notified' => $this->notifyManagers($alert)];
    }

    /**
     * Sévérité par défaut d'un type de détection, quand la chaîne vidéo ne
     * classe pas l'événement elle-même. Défaut **documenté** (pas de donnée
     * inventée : le payload d'ingestion peut toujours imposer la sévérité) :
     * une personne ou une caméra masquée est plus grave qu'un mouvement.
     */
    public function severityFor(string $type): string
    {
        return match ($type) {
            CameraEvent::TYPE_PERSON => CameraAlert::SEVERITY_HIGH,
            CameraEvent::TYPE_TAMPER => CameraAlert::SEVERITY_CRITICAL,
            CameraEvent::TYPE_VEHICLE => CameraAlert::SEVERITY_INFO,
            default => CameraAlert::SEVERITY_WARNING,
        };
    }

    /**
     * Clé de dédoublonnage déterministe : caméra + type + fenêtre de
     * regroupement (bucket temporel) — même motif que
     * `stock-alert:{branch}:{ingredient}:{day}` (#5813).
     */
    public function alertKey(CameraEvent $event, int $windowMinutes = self::DEDUP_WINDOW_MINUTES): string
    {
        $window = max(1, $windowMinutes) * 60;
        $detectedAt = $event->detected_at ?? $event->created_at ?? Carbon::now('UTC');
        $bucket = intdiv($detectedAt->getTimestamp(), $window);

        return 'camera-alert:'.$event->camera_id.':'.(string) $event->type.':'.$bucket;
    }

    public function acknowledge(CameraAlert $alert, Employee $actor): CameraAlert
    {
        if ($alert->status !== CameraAlert::STATUS_OPEN) {
            return $alert;
        }

        $alert->update([
            'status' => CameraAlert::STATUS_ACKNOWLEDGED,
            'acknowledged_by' => $actor->id,
            'acknowledged_at' => Carbon::now('UTC'),
        ]);

        return $alert->refresh();
    }

    public function resolve(CameraAlert $alert, Employee $actor): CameraAlert
    {
        if ($alert->status === CameraAlert::STATUS_RESOLVED) {
            return $alert;
        }

        $alert->update([
            'status' => CameraAlert::STATUS_RESOLVED,
            'resolved_by' => $actor->id,
            'resolved_at' => Carbon::now('UTC'),
            // Une alerte résolue sans acquittement explicite reste traçable :
            // l'acquittement implicite est horodaté, jamais effacé.
            'acknowledged_by' => $alert->acknowledged_by ?? $actor->id,
            'acknowledged_at' => $alert->acknowledged_at ?? Carbon::now('UTC'),
        ]);

        return $alert->refresh();
    }

    /**
     * Notifie les managers actifs du tenant (catégorie `security` : jamais
     * supprimée par les heures calmes). Retourne le nombre de managers notifiés.
     */
    public function notifyManagers(CameraAlert $alert): int
    {
        $companyId = (string) $alert->company_id;

        $managers = Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->limit(self::MAX_MANAGERS)
            ->get()
            ->filter(fn (Employee $employee): bool => $employee->isManager());

        if ($managers->isEmpty()) {
            // Trace structurée SANS PII : une alerte sans destinataire est un
            // trou opérationnel, pas un événement muet.
            Log::channel('structured')->warning('cameras.alert.no_manager', [
                'company_id' => $companyId,
                'alert_id' => $alert->id,
                'camera_id' => $alert->camera_id,
                'severity' => $alert->severity,
            ]);

            return 0;
        }

        $cameraName = $this->cameraName($alert);

        foreach ($managers as $manager) {
            DispatchCommunicationJob::dispatch(
                employeeId: (int) $manager->id,
                companyId: $companyId,
                templateKey: 'camera_security_alert',
                context: [
                    'category' => 'security',
                    'camera' => $cameraName,
                    'alert_id' => $alert->id,
                    'severity' => $alert->severity,
                ],
                channels: ['app', 'push'],
            );
        }

        return $managers->count();
    }

    /** @return array<string, mixed> */
    private function payload(CameraEvent $event): array
    {
        return [
            'event_id' => $event->id,
            'camera_id' => $event->camera_id,
            'type' => $event->type,
            'severity' => $event->severity,
            'detected_at' => ($event->detected_at ?? $event->created_at)?->toIso8601String(),
            'snapshot_path' => $event->snapshot_path,
        ];
    }

    private function cameraName(CameraAlert $alert): string
    {
        $camera = $alert->camera;

        if ($camera instanceof Camera && $camera->name !== '') {
            return $camera->name;
        }

        // Cas de bord (caméra supprimée après ingestion) : libellé localisé,
        // jamais un identifiant technique dans le message utilisateur.
        return (string) __('cameras.unknown_camera');
    }
}
