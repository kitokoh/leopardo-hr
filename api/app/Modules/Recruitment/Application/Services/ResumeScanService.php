<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Services;

use App\Modules\Recruitment\Domain\Contracts\ResumeScanner;
use App\Modules\Recruitment\Domain\Exceptions\ResumeRejectedException;
use App\Modules\Recruitment\Domain\ValueObjects\ResumeScanVerdict;
use Illuminate\Support\Facades\Log;

/**
 * BC-10 (#9217) — applique la politique de sécurité à un verdict de scan.
 *
 * Sépare deux décisions qui n'ont rien à voir :
 *
 *  - le SCANNER dit ce qu'il a trouvé (`ResumeScanVerdict`) ;
 *  - la POLITIQUE dit ce qu'on en fait (`reject` / `quarantine`).
 *
 * Les mélanger dans le contrôleur rendrait impossible de changer de politique
 * sans toucher au flux de candidature, et impossible de tester l'un sans
 * l'autre.
 *
 * Politiques :
 *
 *  - `reject` (défaut) — un CV infecté est refusé (422). Le fichier est
 *    supprimé immédiatement : le conserver « au cas où » reviendrait à garder
 *    un document malveillant sur le disque.
 *  - `quarantine` — le CV infecté est conservé mais marqué, pour analyse
 *    manuelle par l'équipe sécurité. La candidature est acceptée.
 *
 * Dans les deux cas, un scanner INDISPONIBLE n'est pas un motif de rejet par
 * défaut : une panne d'infrastructure ne doit pas empêcher un candidat de
 * postuler. Le verdict est persisté, donc auditable, et le recruteur voit que
 * le fichier n'a pas été analysé.
 */
final class ResumeScanService
{
    public const POLICY_REJECT = 'reject';

    public const POLICY_QUARANTINE = 'quarantine';

    public function __construct(
        private readonly ResumeScanner $scanner,
        private readonly string $policy,
    ) {}

    /**
     * Analyse le fichier et applique la politique.
     *
     * @return ResumeScanVerdict le verdict, à persister sur la candidature
     *
     * @throws ResumeRejectedException si la politique refuse le fichier
     */
    public function inspect(string $absolutePath, string $originalName): ResumeScanVerdict
    {
        $verdict = $this->scanner->scan($absolutePath, $originalName);

        Log::info('recruitment.resume_scan.verdict', [
            'original_name' => $originalName,
            'status' => $verdict->status,
            'engine' => $verdict->engine,
            'signature' => $verdict->signature,
            'reason' => $verdict->reason,
            'policy' => $this->policy,
        ]);

        if ($verdict->isInfected() && $this->policy === self::POLICY_REJECT) {
            throw new ResumeRejectedException($verdict);
        }

        return $verdict;
    }

    public function policy(): string
    {
        return $this->policy;
    }
}
