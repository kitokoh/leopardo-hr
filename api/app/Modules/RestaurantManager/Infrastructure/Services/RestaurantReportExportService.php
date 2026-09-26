<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Permissions\RestaurantPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RESTO-702 (#6215) — Export CSV idempotent + URL signée éphémère.
 *
 * `POST /restaurant/reports/export` : génère le CSV (colonnes allowlistées,
 * contenu déterministe) et le stocke sous
 * `restaurant/exports/{company}/restaurant_{type}_{from}_{to}_{branch}.csv`
 * — rejouer les mêmes filtres produit le même nom de fichier et réutilise
 * le même fichier (idempotence). La réponse porte une URL **signée
 * éphémère** (10 min, middleware `signed`) ; le paramètre `company` de
 * l'URL est couvert par la signature (intégrité, pas de traversée tenant).
 *
 * #8180 — forme `{filename, download_url}` et chemin de stockage réalignés
 * sur le contrat initial (RestaurantReportExportTest), perdu lors de la
 * fusion de dette « PM round 7 ».
 */
final class RestaurantReportExportService
{
    /** Durée de validité de l'URL signée (minutes). */
    public const SIGNED_URL_TTL_MINUTES = 10;

    private const ALLOWED_TYPES = ['sales', 'products', 'cogs', 'pos'];

    /**
     * @return array{filename: string, download_url: string, reused: bool}
     */
    public function export(Employee $actor, string $reportType, Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        if (! in_array($reportType, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException('Type de rapport inconnu (sales|products|cogs|pos).');
        }

        $filename = sprintf(
            'restaurant_%s_%s_%s_%s.csv',
            $reportType,
            $from->toDateString(),
            $to->toDateString(),
            $branchId ?? 'all',
        );

        $relative = sprintf('restaurant/exports/%s/%s', $actor->company_id, $filename);
        $disk = Storage::disk('local');
        $reused = $disk->exists($relative);

        if (! $reused) {
            $csv = app(RestaurantReportService::class)->toCsv(
                $actor->company_id,
                $reportType,
                $from,
                $to,
                $branchId,
            );

            $disk->put($relative, $csv);
        }

        $downloadUrl = URL::temporarySignedRoute(
            'restaurant.reports.export.download',
            now()->addMinutes(self::SIGNED_URL_TTL_MINUTES),
            ['export' => $filename, 'company' => $actor->company_id],
        );

        return [
            'filename' => $filename,
            'download_url' => $downloadUrl,
            'reused' => $reused,
        ];
    }

    /**
     * Téléchargement du fichier (route signée, hors groupe auth — la
     * signature couvre `{export}` ET `company`, prouvée par le middleware
     * `signed` en amont).
     */
    public function download(string $companyId, string $export): StreamedResponse|JsonResponse
    {
        $filename = basename($export);
        $relative = sprintf('restaurant/exports/%s/%s', $companyId, $filename);
        $disk = Storage::disk('local');

        if ($filename === '' || ! $disk->exists($relative)) {
            return response()->json(['message' => 'Export introuvable ou expiré.'], 404);
        }

        return $disk->download($relative, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Prouve la permission `restaurant.reports` pour la fermeture de la policy.
     *
     * #7599 — les rapports sont un geste de gestion : comportement historique
     * (`principal`/`rh`) tant qu'aucune assignation `restaurant_branch`
     * n'existe, puis niveau `manage` sur au moins une succursale assignée.
     *
     * #8180 — délègue à l'implémentation canonique unique
     * ({@see RestaurantPermissions::canViewReports()}) : la copie locale est
     * retirée pour éviter la dérive entre les 3 call sites HTTP.
     */
    public static function authorize(Employee $actor): bool
    {
        return (new RestaurantPermissions)->canViewReports($actor);
    }
}
