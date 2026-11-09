<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\Antivirus;

use App\Modules\Recruitment\Domain\Contracts\ResumeScanner;
use App\Modules\Recruitment\Domain\ValueObjects\ResumeScanVerdict;

/**
 * BC-10 (#9217) — scanner inactif, utilisé quand aucun moteur n'est configuré.
 *
 * Ce n'est PAS un « tout est sain ». Il rend explicitement `unavailable`, et
 * c'est délibéré : un déploiement sans ClamAV doit produire des candidatures
 * marquées « non analysé », visibles comme telles dans l'écran recruteur.
 *
 * L'alternative — renvoyer `clean` — serait le pire des choix : le recruteur
 * verrait un CV « vérifié » qui ne l'a jamais été, et personne ne saurait
 * qu'il faut configurer un scanner. Un faux positif de sécurité est plus
 * dangereux qu'une absence de contrôle assumée.
 *
 * La politique (`reject` / `quarantine`) décide ensuite si un CV non analysé
 * est accepté ou refusé — c'est une décision d'exploitation, pas de code.
 */
final class NullResumeScanner implements ResumeScanner
{
    public function name(): string
    {
        return 'none';
    }

    public function scan(string $absolutePath, string $originalName): ResumeScanVerdict
    {
        return ResumeScanVerdict::unavailable(
            $this->name(),
            'aucun moteur antivirus configuré (RECRUITMENT_AV_DRIVER)',
        );
    }
}
