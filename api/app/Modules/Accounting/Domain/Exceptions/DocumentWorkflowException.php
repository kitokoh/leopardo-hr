<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use RuntimeException;

/**
 * #5223 — Erreur métier du workflow des documents comptables (cycle de vie,
 * numérotation, règles de transition). Mappée en 422 par le contrôleur.
 */
class DocumentWorkflowException extends RuntimeException {}
