<?php

declare(strict_types=1);

namespace App\Core\Privacy\Domain\Enums;

/**
 * MAT-011 (#5869) — Niveau de sensibilité PII.
 */
enum PiiSensitivity: string
{
    case Low = 'low';

    case Medium = 'medium';

    case High = 'high';
}
