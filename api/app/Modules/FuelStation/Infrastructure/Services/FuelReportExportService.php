<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FUEL-018 (#5812) — Export CSV contrôlé + URL signée éphémère.
 *
 * `POST /fuel-station/reports/export` : génère le CSV (contenu déterministe)
 * et le stocke sous `fuel/exports/{hash}.csv` — mêmes filtres → même fichier
 * (idempotent). Réponse avec URL signée (TTL 10 min, middleware `signed`).
 */
final class FuelReportExportService
{
    public const SIGNED_URL_TTL_MINUTES = 10;

    private const ALLOWED_TYPES = ['sales', 'shifts', 'cash-sessions'];

    /**
     * @return array{export_id: string, filename: string, signed_url: string, reused: bool}
     */
    public function export(Employee $actor, string $type, Carbon $from, Carbon $to, ?int $stationId = null): array
    {
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException('Type de rapport inconnu (sales|shifts|cash-sessions).');
        }

        $hash = sha1(implode('|', [$actor->company_id, $type, $from->toDateString(), $to->toDateString(), (string) $stationId]));
        $relative = sprintf('fuel/exports/%s.csv', $hash);
        $disk = Storage::disk('local');
        $reused = $disk->exists($relative);

        if (! $reused) {
            $csv = app(FuelReportService::class)->toCsv($actor->company_id, $type, $from, $to, $stationId);
            $disk->put($relative, $csv);
        }

        $signedUrl = URL::temporarySignedRoute(
            'fuel.reports.export.download',
            now()->addMinutes(self::SIGNED_URL_TTL_MINUTES),
            ['export' => $hash],
        );

        return [
            'export_id' => $hash,
            'filename' => $type.'-'.$from->toDateString().'_'.$to->toDateString().'.csv',
            'signed_url' => $signedUrl,
            'reused' => $reused,
        ];
    }

    /**
     * Téléchargement signé (hors groupe auth — la signature EST l'auth).
     */
    public function download(string $exportId): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $relative = sprintf('fuel/exports/%s.csv', $exportId);
        $disk = Storage::disk('local');

        if (! $disk->exists($relative)) {
            return response()->json(['message' => 'Export introuvable ou expiré.'], 404);
        }

        return $disk->download($relative);
    }
}
