<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Interfaces\Api\V1\Controllers\HealthController;
use Tests\TestCase;

/**
 * Issue #1768 — `/api/v1/health` rapportait Redis « unexpected » avec le
 * client Predis : `ping()` retourne un objet `Predis\Response\Status` dont
 * `__toString()` vaut « PONG », mais les comparaisons strictes du
 * HealthController échouaient toujours (faux négatif → sonde rouge).
 */
class HealthRedisPredisTest extends TestCase
{
    public function test_pong_response_variants_are_all_healthy(): void
    {
        $method = new \ReflectionMethod(HealthController::class, 'isPongResponse');

        // PhpRedis : true / 'PONG' / '+PONG'
        self::assertTrue($method->invoke(null, true));
        self::assertTrue($method->invoke(null, 'PONG'));
        self::assertTrue($method->invoke(null, '+PONG'));

        // Predis : objet Status dont __toString() = 'PONG' (le bug #1768)
        $predisStatus = new class
        {
            public function __toString(): string
            {
                return 'PONG';
            }
        };
        self::assertTrue($method->invoke(null, $predisStatus));

        // Faux négatifs
        self::assertFalse($method->invoke(null, 'NOPE'));
        self::assertFalse($method->invoke(null, ''));
        self::assertFalse($method->invoke(null, null));
    }
}
