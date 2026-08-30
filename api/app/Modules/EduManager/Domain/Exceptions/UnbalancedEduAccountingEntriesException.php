<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Exceptions;

use RuntimeException;

/**
 * Issue #5832 (EDU-016) — écritures comptables EduManager déséquilibrées.
 *
 * Levée par EduAccountingEntryService quand la somme des débits diffère de
 * celle des crédits avant persistance (garde de dernier recours — les
 * générateurs produisent toujours des lignes équilibrées).
 */
final class UnbalancedEduAccountingEntriesException extends RuntimeException
{
}
