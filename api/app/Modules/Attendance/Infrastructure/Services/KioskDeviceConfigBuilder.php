<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Modules\Attendance\Domain\Models\AttendanceKiosk;

/**
 * #7962 (sous-tache) — cas d'usage : construction du payload de configuration
 * publie a un kiosque (`GET /kiosks/{deviceCode}/config` et
 * `GET /kiosks/{deviceCode}/sync-status`).
 *
 * Extrait de `KioskController::config()`, `doAnnouncements()`-voisin et
 * `syncStatus()`. Le point cle de l'extraction est la POLITIQUE OFFLINE
 * (BIO-007, #6772), qui etait dupliquee dans le controleur sous forme de
 * `offlinePolicy()` : elle est desormais definie a un seul endroit, avec sa
 * borne documentee.
 *
 * Cette classe ne lit pas la requete et n'ecrit pas de reponse : elle rend
 * des tableaux serialisables. Le controleur reste seul juge du code HTTP et
 * de l'enveloppe `{data: ...}`.
 *
 * #6570 — placee sous `Infrastructure/Services/` et non sous
 * `Application/Actions/` : ce n'est pas un cas d'usage (elle ne porte aucune
 * decision metier, aucun effet de bord, aucune transaction) mais un
 * assembleur de payload. La garde `check-actions-convention.sh` exige
 * « 1 action = 1 execute() » ; plutot que d'inventer un `execute()` qui ne
 * ferait que deleguer a trois methodes de lecture, la classe est rangee la ou
 * sa nature la place. Le nom suit la convention des autres builders du
 * module (`*Builder`).
 */
class KioskDeviceConfigBuilder
{
    /**
     * Politique offline publiee au kiosque (BIO-007 #6772).
     *
     * Le mode offline est BORNE, jamais illimite : `max_age_days` est la
     * fenetre d'anciennete au-dela de laquelle un evenement synchronise est
     * isole (EVENT_EXPIRED), `max_events_per_batch` borne la taille d'un
     * batch de synchro. Valeurs lues en configuration (`config/attendance.php`),
     * jamais codees en dur.
     *
     * @return array{max_age_days: int, max_events_per_batch: int}
     */
    public function offlinePolicy(): array
    {
        return [
            'max_age_days' => (int) config('attendance.kiosk.offline.max_age_days', 14),
            'max_events_per_batch' => (int) config('attendance.kiosk.offline.max_events_per_batch', 500),
        ];
    }

    /**
     * Bloc d'etat de synchronisation expose au kiosque.
     *
     * @return array{last_seen_at: string|null, last_sync_at: string|null, acked_event_counter: int}
     */
    public function syncStatus(AttendanceKiosk $kiosk): array
    {
        return [
            'last_seen_at' => $kiosk->last_seen_at?->toIso8601String(),
            'last_sync_at' => $kiosk->last_sync_at?->toIso8601String(),
            'acked_event_counter' => (int) $kiosk->acked_event_counter,
        ];
    }

    /**
     * Payload complet de configuration d'un kiosque authentifie.
     *
     * `punch_methods` est resolu cote serveur (BIO-006, #6767) : l'interface
     * ne propose que les methodes reellement activees (BIO-009, #6774).
     *
     * @return array<string, mixed>
     */
    public function forKiosk(AttendanceKiosk $kiosk, string $deviceCode): array
    {
        return [
            'device_code' => $deviceCode,
            'company_id' => $kiosk->company_id,
            'name' => $kiosk->name,
            'status' => $kiosk->status,
            'biometric_mode' => $kiosk->biometric_mode,
            'site_id' => $kiosk->site_id,
            'punch_methods' => $kiosk->resolvedPunchMethods(),
            'server_time' => now()->toIso8601String(),
            'offline_policy' => $this->offlinePolicy(),
            'sync_status' => $this->syncStatus($kiosk),
        ];
    }

    /**
     * Payload de `sync-status` : configuration reduite a l'etat de synchro
     * (l'appareil a deja ete authentifie par le middleware `kiosk.device`,
     * il n'a pas besoin de re-lire toute sa configuration).
     *
     * @return array<string, mixed>
     */
    public function syncStatusPayload(AttendanceKiosk $kiosk, string $deviceCode): array
    {
        return [
            'device_code' => $deviceCode,
            'status' => $kiosk->status,
            'company_id' => $kiosk->company_id,
            'server_time' => now()->toIso8601String(),
            'offline_policy' => $this->offlinePolicy(),
        ] + $this->syncStatus($kiosk);
    }
}
