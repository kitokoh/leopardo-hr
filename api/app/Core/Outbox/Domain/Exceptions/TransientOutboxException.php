<?php

declare(strict_types=1);

namespace App\Core\Outbox\Domain\Exceptions;

use RuntimeException;

/**
 * MAT-008 (#5866) — Erreur transitoire d'un consommateur d'outbox.
 *
 * Provoque un retry avec backoff exponentiel (l'événement reste dans la
 * file, `attempts` incrémenté, `available_at` repoussé).
 */
class TransientOutboxException extends RuntimeException {}
