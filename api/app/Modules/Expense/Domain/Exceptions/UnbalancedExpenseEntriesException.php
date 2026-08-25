<?php

declare(strict_types=1);

namespace App\Modules\Expense\Domain\Exceptions;

use RuntimeException;

/**
 * Issue #5235 — un journal d'écritures Expense dont le total des débits ne
 * vaut pas le total des crédits ne doit JAMAIS être persisté (partie double,
 * équilibre garanti par construction — exception défensive).
 */
class UnbalancedExpenseEntriesException extends RuntimeException {}
