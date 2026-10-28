<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Modules\Attendance\Domain\Models\AttendanceKiosk;

/**
 * #7962 (suite de #8944) — projections de lecture d'un kiosque.
 *
 * Extrait de `KioskController::serializeKiosk()`, `::offlinePolicy()`,
 * `::config()` et `::syncStatus()`. Ces quatre méthodes produisaient des
 * tableaux que le contrôleur assemblait lui-même : le format d'un kiosque
 * existait donc à quatre endroits, et une clé ajoutée à l'un n'apparaissait pas
 * dans les autres — exactement ce qui s'était produit avec `punch_methods` puis
 * `offline_policy`.
 *
 * ## Pourquoi `serialize()` et `config()` sont séparés
 *
 * `serialize()` décrit le kiosque au **gestionnaire** (surface authentifiée
 * tenant) ; `config()` décrit au **terminal** ce qu'il doit appliquer
 * (surface appareil). Les deux ne s'adressent pas au même lecteur et n'ont pas
 * la même durée de vie de contrat : les fusionner obligerait à exposer au
 * terminal des champs d'administration (`revoked_at`, `trusted_device_label`)
 * dont il n'a aucun usage. La séparation est donc volontaire, pas un oubli.
 */
final class KioskViewBuilder
{
    /**
     * Vue gestionnaire d'un kiosque (création, révocation, rotation).
     *
     * `device_code` est rendu sous sa forme stockée (hash) : le clair n'est
     * restitué qu'à la création et à la rotation, par l'appelant qui le détient.
     *
     * @return array<string, mixed>
     */
    public function serialize(AttendanceKiosk $kiosk): array
    {
        return [
            'id' => $kiosk->id,
            'name' => $kiosk->name,
            'location_label' => $kiosk->location_label,
            'device_code' => $kiosk->device_code,
            'status' => $kiosk->status,
            'biometric_mode' => $kiosk->biometric_mode,
            'site_id' => $kiosk->site_id,
            'punch_methods' => $kiosk->resolvedPunchMethods(),
            'trusted_device_label' => $kiosk->trusted_device_label,
            'revoked_at' => $kiosk->revoked_at?->toIso8601String(),
        ];
    }

    /**
     * Politique offline publiée au terminal (BIO-007 #6772).
     *
     * Bornée par configuration : un mode offline « illimité » n'existe pas —
     * un appareil hors réseau pendant trois semaines ne doit pas pouvoir
     * injecter trois semaines de pointages non vérifiables.
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
     * Configuration appliquée par le terminal (BIO-006 / BIO-007 / BIO-009).
     *
     * @return array<string, mixed>
     */
    public function config(AttendanceKiosk $kiosk, string $deviceCode): array
    {
        return [
            'device_code' => $deviceCode,
            'company_id' => $kiosk->company_id,
            'name' => $kiosk->name,
            'status' => $kiosk->status,
            'biometric_mode' => $kiosk->biometric_mode,
            'site_id' => $kiosk->site_id,
            // BIO-006 (#6767) : matrice serveur — l'interface ne propose que
            // les méthodes réellement activées (BIO-009 #6774).
            'punch_methods' => $kiosk->resolvedPunchMethods(),
            'server_time' => now()->toIso8601String(),
            'offline_policy' => $this->offlinePolicy(),
            'sync_status' => $this->syncStatus($kiosk),
        ];
    }

    /**
     * État de synchronisation visible de l'appareil.
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
}
