<?php

declare(strict_types=1);

namespace App\Core\Outbox\Domain\Exceptions;

use RuntimeException;

/**
 * MAT-008 (#5866) — Erreur permanente d'un consommateur d'outbox.
 *
 * Provoque une dead-letter immédiate (status `failed`) : aucune retry,
 * l'événement reste rejouable manuellement via `outbox:replay`.
 */
class PermanentOutboxException extends RuntimeException {}
