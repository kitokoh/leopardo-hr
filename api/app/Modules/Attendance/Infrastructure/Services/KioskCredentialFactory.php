<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * #7962 (suite de #8944) — génération et hachage des secrets d'un kiosque.
 *
 * Extrait de `KioskController::doRegister()` et `::rotateToken()`. Vit en
 * Infrastructure parce que `Hash` est une facade Laravel, et que
 * `check-layer-purity` (#6568) la réserve à Interfaces/Infrastructure.
 *
 * ## Pourquoi ce service existe au lieu d'appeler `Hash` sur place
 *
 * Deux chemins produisent un `sync_token` (provisioning et rotation). Écrits
 * séparément, ils peuvent diverger : un jour l'un hashe avec `bcrypt`, l'autre
 * oublie de hasher et stocke le clair. Le `sync_token_hash` est ensuite vérifié
 * par `Hash::check()` dans `resolveAuthorizedKiosk()` — un token stocké en clair
 * ne matcherait jamais, et le terminal serait silencieusement mort.
 *
 * Le `device_code` suit une autre règle : hash **déterministe** (sha256, cf.
 * `AttendanceKiosk::hashDeviceCode`) parce qu'il sert de clé de recherche, donc
 * il doit être calculable à partir de la valeur présentée par l'appareil.
 */
final class KioskCredentialFactory
{
    /** Longueur du device_code affiché à l'opérateur (majuscules). */
    private const DEVICE_CODE_LENGTH = 10;

    /** Longueur du secret de synchronisation. */
    private const SYNC_TOKEN_LENGTH = 48;

    /**
     * Code appareil en clair, prêt à être montré une seule fois.
     */
    public function newDeviceCode(): string
    {
        return strtoupper(Str::random(self::DEVICE_CODE_LENGTH));
    }

    /**
     * Secret de synchronisation en clair, prêt à être montré une seule fois.
     */
    public function newSyncToken(): string
    {
        return Str::random(self::SYNC_TOKEN_LENGTH);
    }

    /**
     * Forme stockée du device_code : hash déterministe, utilisable en `WHERE`.
     */
    public function storeDeviceCode(string $plainDeviceCode): string
    {
        return AttendanceKiosk::hashDeviceCode($plainDeviceCode);
    }

    /**
     * Forme stockée du sync_token : hash lent, non réversible.
     */
    public function storeSyncToken(string $plainSyncToken): string
    {
        return Hash::make($plainSyncToken);
    }
}
