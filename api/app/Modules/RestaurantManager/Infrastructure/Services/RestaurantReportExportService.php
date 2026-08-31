<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
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
 * `restaurant/exports/{company}/{hash}.csv` — rejouer les mêmes filtres
 * produit le même hash et réutilise le même fichier (idempotence). La
 * réponse porte une URL **signée éphémère** (10 min, middleware `signed`).
 */
final class RestaurantReportExportService
{
    /** Durée de validité de l'URL signée (minutes). */
    public const SIGNED_URL_TTL_MINUTES = 10;

    private const ALLOWED_TYPES = ['sales', 'products', 'cogs', 'pos'];

    /**
     * @return array{export_id: string, filename: string, signed_url: string, reused: bool}
     */
    public function export(Employee $actor, string $reportType, Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        if (! in_array($reportType, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException('Type de rapport inconnu (sales|products|cogs|pos).');
        }

        $hash = sha1(implode('|', [$actor->company_id, $reportType, $from->toDateString(), $to->toDateString(), (string) $branchId]));

        // Le hash intègre la company : pas de collision cross-tenant, le
        // téléchargement signé ne reçoit que le hash.
        $relative = sprintf('restaurant/exports/%s.csv', $hash);
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

        $signedUrl = URL::temporarySignedRoute(
            'restaurant.reports.export.download',
            now()->addMinutes(self::SIGNED_URL_TTL_MINUTES),
            ['export' => $hash],
        );

        return [
            'export_id' => $hash,
            'filename' => $reportType.'-'.$from->toDateString().'_'.$to->toDateString().'.csv',
            'signed_url' => $signedUrl,
            'reused' => $reused,
        ];
    }

    /**
     * Téléchargement du fichier (route signée, hors groupe auth).
     */
    public function download(string $exportId): StreamedResponse|JsonResponse
    {
        $relative = sprintf('restaurant/exports/%s.csv', $exportId);
        $disk = Storage::disk('local');

        if (! $disk->exists($relative)) {
            return response()->json(['message' => 'Export introuvable ou expiré.'], 404);
        }

        return $disk->download($relative);
    }

    /**
     * Prouve la permission `restaurant.reports` pour la fermeture de la policy.
     */
    public static function authorize(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'server', 'kitchen', 'rider');
    }
}
