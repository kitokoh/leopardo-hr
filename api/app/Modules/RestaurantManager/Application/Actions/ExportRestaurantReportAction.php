<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Modules\RestaurantManager\Application\Services\RestaurantReportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

/**
 * RESTO-702 (#6215) — Export CSV idempotent d'un rapport restaurant.
 *
 * - Colonnes allowlistées par type de rapport (RestaurantReportService) ;
 * - fichier déterministe : même jeu de paramètres → même fichier (le rejeu
 *   réutilise le fichier existant, critère « export rejouable = même fichier ») ;
 * - URL signée éphémère (15 min) via `restaurant.reports.export.download`
 *   (pattern VoiceController — URLs signées Laravel) ;
 * - fichiers rangés sous `storage/app/restaurant/exports/{company_id}/`.
 */
final class ExportRestaurantReportAction
{
    private const SIGNED_URL_TTL_MINUTES = 15;

    public function __construct(
        private readonly RestaurantReportService $reports,
    ) {
    }

    /**
     * @return array{filename: string, download_url: string}
     */
    public function export(
        string $reportType,
        string $companyId,
        ?Carbon $from,
        ?Carbon $to,
        ?int $branchId,
    ): array {
        $filename = $this->filename($reportType, $from, $to, $branchId);
        $directory = 'restaurant/exports/'.$companyId;

        if (! Storage::disk('local')->exists($directory.'/'.$filename)) {
            $lines = $this->reports->exportCsv($reportType, $companyId, $from, $to, $branchId);
            $content = implode("\n", $lines)."\n";

            Storage::disk('local')->put($directory.'/'.$filename, $content);
        }

        $downloadUrl = URL::temporarySignedRoute(
            'restaurant.reports.export.download',
            now()->addMinutes(self::SIGNED_URL_TTL_MINUTES),
            ['export' => $filename],
        );

        return ['filename' => $filename, 'download_url' => $downloadUrl];
    }

    private function filename(string $reportType, ?Carbon $from, ?Carbon $to, ?int $branchId): string
    {
        $stamp = Str::slug(sprintf(
            '%s_%s_%s_%s',
            $reportType,
            $from?->toDateString() ?? 'all',
            $to?->toDateString() ?? 'all',
            $branchId ?? 'all',
        ));

        return 'restaurant_'.$stamp.'.csv';
    }
}
