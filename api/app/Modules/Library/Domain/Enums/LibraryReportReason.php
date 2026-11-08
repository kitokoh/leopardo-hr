<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Enums;

/**
 * Motif d'un signalement de contenu (procédure de retrait, droits d'auteur).
 */
enum LibraryReportReason: string
{
    case Copyright = 'copyright';
    case Inappropriate = 'inappropriate';
    case Broken = 'broken';
    case Other = 'other';
}
