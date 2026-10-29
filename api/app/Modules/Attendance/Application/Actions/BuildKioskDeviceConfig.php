<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

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
 * Convention #6570 — « 1 action = 1 execute() ». Les trois payloads publies
 * au kiosque etaient trois methodes publiques de la meme classe, ce qui est
 * exactement la forme que la garde `check-actions-convention.sh` refuse : une
 * Action porte un cas d'usage, pas un catalogue de reponses. Ils deviennent
 * les trois variantes de `execute()` via `KioskConfigView`, qui rend en plus
 * explicite ce que le controleur decidait implicitement en choisissant la
 * methode. Les variantes partagent la meme politique offline : c'est
 * justement cette unicite que l'extraction devait garantir, et trois classes
 * separees l'auraient re-dupliquee.
 */
class BuildKioskDeviceConfig
{
    /** Configuration complete d'un kiosque authentifie. */
    public const VIEW_CONFIG = 'config';

    /**
     * Etat de synchro seul : l'appareil a deja ete authentifie par le
     * middleware `kiosk.device`, il n'a pas besoin de relire sa configuration.
     */
    public const VIEW_SYNC_STATUS = 'sync_status';

    /**
     * Politique offline seule (reponse de `announcements`, qui n'a pas de
     * kiosque a decrire).
     */
    public const VIEW_OFFLINE_POLICY = 'offline_policy';

    /**
     * @param  self::VIEW_*  $view
     * @return array<string, mixed>
     */
    public function execute(string $view, ?AttendanceKiosk $kiosk = null, ?string $deviceCode = null): array
    {
        return match ($view) {
            self::VIEW_OFFLINE_POLICY => ['offline_policy' => $this->offlinePolicy()],
            self::VIEW_SYNC_STATUS => $this->syncStatusPayload($this->requireKiosk($kiosk), $this->requireDeviceCode($deviceCode)),
            default => $this->forKiosk($this->requireKiosk($kiosk), $this->requireDeviceCode($deviceCode)),
        };
    }

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
     * Payload complet de configuration d'un kiosque authentifie.
     *
     * `punch_methods` est resolu cote serveur (BIO-006, #6767) : l'interface
     * ne propose que les methodes reellement activees (BIO-009, #6774).
     *
     * @return array<string, mixed>
     */
    private function forKiosk(AttendanceKiosk $kiosk, string $deviceCode): array
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
     * Bloc d'etat de synchronisation expose au kiosque.
     *
     * @return array{last_seen_at: string|null, last_sync_at: string|null, acked_event_counter: int}
     */
    private function syncStatus(AttendanceKiosk $kiosk): array
    {
        return [
            'last_seen_at' => $kiosk->last_seen_at?->toIso8601String(),
            'last_sync_at' => $kiosk->last_sync_at?->toIso8601String(),
            'acked_event_counter' => (int) $kiosk->acked_event_counter,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncStatusPayload(AttendanceKiosk $kiosk, string $deviceCode): array
    {
        return [
            'device_code' => $deviceCode,
            'status' => $kiosk->status,
            'company_id' => $kiosk->company_id,
            'server_time' => now()->toIso8601String(),
            'offline_policy' => $this->offlinePolicy(),
        ] + $this->syncStatus($kiosk);
    }

    /**
     * Les variantes `config` et `sync_status` n'ont pas de sens sans kiosque.
     * Echouer explicitement vaut mieux que rendre un payload incomplet que le
     * kiosque appliquerait silencieusement.
     */
    private function requireKiosk(?AttendanceKiosk $kiosk): AttendanceKiosk
    {
        if ($kiosk === null) {
            throw new \InvalidArgumentException('BuildKioskDeviceConfig: une vue kiosque exige le kiosque.');
        }

        return $kiosk;
    }

    private function requireDeviceCode(?string $deviceCode): string
    {
        if ($deviceCode === null || $deviceCode === '') {
            throw new \InvalidArgumentException('BuildKioskDeviceConfig: une vue kiosque exige le device code.');
        }

        return $deviceCode;
    }
}
