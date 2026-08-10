<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services\SSO;

use RuntimeException;

/**
 * Audit #1694 — la validation des assertions/tokens SSO n'est pas encore
 * implémentée. Les callbacks doivent échouer explicitement (501) plutôt que
 * de renvoyer un succès vide qui laisserait croire qu'une session SSO a été
 * établie.
 */
class SSOValidationNotImplementedException extends RuntimeException {}
