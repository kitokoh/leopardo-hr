<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAdminAiConversationController;
use Illuminate\Database\QueryException;
use PDOException;
use Tests\TestCase;

/**
 * Issue #6690 — classification des erreurs de GET /admin/ai/conversations
 * (classifieur pur, sans base) :
 *  - table absente (42P01) → « feature indisponible » → 403 ;
 *  - vraie erreur serveur (42703, colonne manquante) → 500 ;
 *  - erreur non-DB → 500.
 */
class PlatformAdminAiConversationErrorMappingTest extends TestCase
{
    private PlatformAdminAiConversationController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new PlatformAdminAiConversationController;
    }

    public function test_missing_table_is_feature_unavailable(): void
    {
        $this->assertTrue($this->controller->isFeatureUnavailable(
            $this->queryException('SQLSTATE[42P01]: Undefined table: relation "ai_conversations" does not exist', '42P01'),
        ));
    }

    public function test_sqlite_missing_table_is_feature_unavailable(): void
    {
        $this->assertTrue($this->controller->isFeatureUnavailable(
            $this->queryException('SQLSTATE[HY000]: General error: 1 no such table: ai_conversations', 'HY000'),
        ));
    }

    public function test_mysql_missing_table_is_feature_unavailable(): void
    {
        $this->assertTrue($this->controller->isFeatureUnavailable(
            $this->queryException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'db.ai_conversations' doesn't exist", '42S02'),
        ));
    }

    public function test_missing_column_is_not_feature_unavailable(): void
    {
        // #6690 : « column X does not exist » ≠ table absente → vraie erreur
        // serveur (500), jamais masquée en « feature indisponible ».
        $this->assertFalse($this->controller->isFeatureUnavailable(
            $this->queryException('SQLSTATE[42703]: Undefined column: column "nope" does not exist', '42703'),
        ));
    }

    public function test_unrelated_exception_is_not_feature_unavailable(): void
    {
        $this->assertFalse($this->controller->isFeatureUnavailable(new \RuntimeException('boom')));
    }

    /**
     * PDOException::$code est protected — sous-classe pour poser le SQLSTATE
     * (PHP 8.4 : constructeur natif à code int, le SQLSTATE arrive via le driver).
     */
    private function queryException(string $message, string $sqlState): QueryException
    {
        $pdo = new class($message, $sqlState) extends PDOException
        {
            public function __construct(string $message, string $sqlState)
            {
                parent::__construct($message, 0);
                $this->code = $sqlState;
            }
        };

        return new QueryException('pgsql', 'select 1', [], $pdo);
    }
}
