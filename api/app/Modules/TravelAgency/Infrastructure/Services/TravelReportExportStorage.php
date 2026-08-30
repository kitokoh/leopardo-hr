<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelReportExport;
use Illuminate\Support\Facades\Storage;

/**
 * TRAVEL-505 (#6075) — Stockage des exports CSV de rapports.
 *
 * Fallback disque tenant (`storage/app/travel/exports/…`) avec URL signée
 * temporaire (30 min) — jamais de lien public permanent. Le contrat
 * documents BC-20 pourra remplacer le fallback sans changer l'interface.
 */
final class TravelReportExportStorage
{
    public const DISK = 'local';

    public const PREFIX = 'travel/exports';

    public function store(TravelReportExport $export, string $csvBinary): string
    {
        $path = self::PREFIX.'/'.$export->company_id.'/'.$export->request_hash.'.csv';

        Storage::disk(self::DISK)->put($path, $csvBinary);

        return $path;
    }

    /**
     * URL signée temporaire (30 min) — jamais un lien public permanent.
     */
    public function signedUrl(string $path): string
    {
        return Storage::disk(self::DISK)->temporaryUrl($path, now()->addMinutes(30));
    }

    public function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete($path);
    }
}
