<?php

declare(strict_types=1);

namespace App\Core\Solutions\Infrastructure\Services;

use App\Core\Solutions\Survey\Contracts\SolutionSurvey;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Génération du « pack de démarrage » PDF d'une solution sectorielle.
 *
 * Document A4 simple, localisé via les fichiers lang Laravel
 * (`solutions.*` — même vocabulaire de clés que le wizard web).
 * Aucune dépendance payante : dompdf est déjà en deps (composer.json).
 *
 * @see docs/architecture/RESTAURANT_SOLUTION_SURVEY.md
 */
final class SolutionPackPdfGenerator
{
    /**
     * @param  list<string>  $packageKeys  clés du catalogue de packages
     * @param  string  $locale  code langue du document ('fr'|'en')
     */
    public function generate(SolutionSurvey $survey, array $packageKeys, string $locale = 'fr'): DomPdf
    {
        $catalog = $survey->packages();
        $packages = [];

        foreach ($packageKeys as $key) {
            if (isset($catalog[$key])) {
                $packages[] = $catalog[$key];
            }
        }

        $pdf = Pdf::loadView('pdf.solution_pack', [
            'solutionCode' => $survey->code(),
            'solutionName' => $survey->name(),
            'packages' => $packages,
            'locale' => $locale,
        ]);
        $pdf->setPaper('A4', 'portrait');

        return $pdf;
    }
}
