<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Logo de station (P1-e, audit 2026-10-17).
 *
 * Reutilise le mecanisme de stockage du branding societe
 * (CompanyBrandingController) : disque `public`, chemin par tenant. Aucun
 * stockage parallele n'est introduit (exigence explicite de l'issue #8583).
 *
 * Le chemin est TOUJOURS prefixe par `company_id` : deux tenants ne peuvent
 * pas se marcher dessus, et un logo d'un autre tenant n'est jamais servi.
 */
final class FuelStationLogoService
{
    public const DISK = 'public';

    /**
     * Stocke le logo et met a jour la station. Retourne la station rafraichie.
     */
    public function store(FuelStation $station, UploadedFile $file): FuelStation
    {
        $this->delete($station);

        $stored = $file->store("fuel-station-logos/{$station->company_id}", self::DISK);

        if (! is_string($stored) || $stored === '') {
            throw new \RuntimeException('FUEL_STATION_LOGO_STORE_FAILED');
        }

        $path = $stored;

        $station->update([
            'logo_path' => $path,
            'logo_disk' => self::DISK,
            'logo_url' => Storage::disk(self::DISK)->url($path),
        ]);

        return $station->refresh();
    }

    /**
     * Supprime le logo physique et remet les colonnes a null.
     */
    public function delete(FuelStation $station): FuelStation
    {
        $path = $station->logo_path;
        $disk = is_string($station->logo_disk) && $station->logo_disk !== ''
            ? $station->logo_disk
            : self::DISK;

        if (is_string($path) && $path !== '' && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }

        $station->update([
            'logo_path' => null,
            'logo_disk' => null,
            'logo_url' => null,
        ]);

        return $station->refresh();
    }
}
