<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Exceptions\KioskProvisioningMismatchException;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * #7962 (sous-tache) — ecriture du kiosque et sonde de resolvabilite.
 *
 * Vit en Infrastructure et non en Application : `check-layer-purity.sh`
 * (#6568) interdit les facades Laravel (`DB`, `Hash`, `Log`) depuis
 * `Application/`. L'Action `ProvisionKiosk` porte la regle metier, ce service
 * porte l'acces.
 */
class KioskProvisioningWriter
{
    /**
     * Cree le kiosque et rend les credentials en clair. Le code et le token ne
     * sont stockes hashes ; ils ne sont rendus qu'ici, une seule fois.
     *
     * @param  array{name: string, location_label?: string|null, biometric_mode?: string|null, trusted_device_label?: string|null, punch_methods?: list<string>|null, site_id?: int|null}  $attributes
     * @return array{kiosk: AttendanceKiosk, device_code: string, sync_token: string}
     */
    public function create(Company $company, array $attributes): array
    {
        $plainDeviceCode = strtoupper(Str::random(10));
        $plainSyncToken = Str::random(48);

        $kiosk = DB::transaction(fn (): AttendanceKiosk => AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => $attributes['name'],
            'location_label' => $attributes['location_label'] ?? null,
            'biometric_mode' => $attributes['biometric_mode'] ?? 'fingerprint',
            'trusted_device_label' => $attributes['trusted_device_label'] ?? null,
            'site_id' => $attributes['site_id'] ?? null,
            'punch_methods' => $attributes['punch_methods'] ?? null,
            // Issue #5588 : le device_code n'est plus stocke en clair (hash
            // deterministe sha256, lookup par egalite).
            'device_code' => AttendanceKiosk::hashDeviceCode($plainDeviceCode),
            'sync_token_hash' => Hash::make($plainSyncToken),
            'status' => 'active',
        ]));

        return [
            'kiosk' => $kiosk,
            'device_code' => $plainDeviceCode,
            'sync_token' => $plainSyncToken,
        ];
    }

    /**
     * Sonde : le kiosque est-il resolvable par les routes publiques ?
     */
    public function isResolvableInPublicContext(AttendanceKiosk $kiosk): bool
    {
        return AttendanceKiosk::query()
            ->where('device_code', $kiosk->device_code)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * #6678 — supprime la ligne orpheline et trace l'audit avant de laisser
     * remonter l'echec. Une ligne non resolvable ne doit jamais rester : elle
     * apparaitrait dans la console manager sans jamais pouvoir pointer.
     */
    public function discardOrphan(AttendanceKiosk $kiosk): void
    {
        $kiosk->delete();

        Log::channel('audit')->error('kiosk.provisioning.mismatch', [
            'kiosk_id' => $kiosk->id,
            'company_id' => $kiosk->company_id,
            'device_code_hash' => $kiosk->device_code,
        ]);

        throw new KioskProvisioningMismatchException;
    }
}
