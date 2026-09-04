<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SafeErrorMessage;
use Illuminate\Database\QueryException;
use Illuminate\Database\SQLiteConnection;
use Tests\TestCase;

/**
 * #6559 (audit fiabilité) — les messages d'erreur persistés (`error_message`,
 * visibles dans l'UI) ne doivent pas fuiter de détails internes (SQLSTATE,
 * requêtes SQL, chemins absolus, DSN) tout en conservant le message métier.
 */
class SafeErrorMessageTest extends TestCase
{
    public function test_business_message_is_preserved(): void
    {
        $e = new \RuntimeException('Configuration bancaire entreprise manquante (MISSING_COMPANY_IBAN).');

        $summary = SafeErrorMessage::summarize($e);

        $this->assertStringContainsString('Configuration bancaire entreprise manquante', $summary);
        $this->assertStringContainsString('RuntimeException', $summary);
    }

    public function test_sqlstate_and_sql_fragments_are_reduced_to_generic_database_error(): void
    {
        $e = new \RuntimeException(
            'SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "payroll_runs_correlation_id_unique" SQL: insert into "payroll_runs" ...'
        );

        $summary = SafeErrorMessage::summarize($e);

        $this->assertSame('RuntimeException: database_error (détail technique en logs)', $summary);
        $this->assertStringNotContainsString('23505', $summary);
        $this->assertStringNotContainsString('duplicate key', $summary);
        $this->assertStringNotContainsString('insert into', $summary);
    }

    public function test_absolute_paths_and_dsn_are_scrubbed(): void
    {
        // URL sans pattern credential (user:password@) pour ne pas déclencher
        // TruffleHog sur le test lui-même.
        $e = new \RuntimeException(
            'Échec de connexion sortante https://api.exemple-invalide.test/health — détail dans /var/www/html/storage/logs/laravel.log'
        );

        $summary = SafeErrorMessage::summarize($e);

        $this->assertStringNotContainsString('exemple-invalide.test', $summary);
        $this->assertStringNotContainsString('/var/www/html', $summary);
    }

    public function test_empty_message_falls_back_to_exception_class(): void
    {
        $e = new \RuntimeException('   ');

        $this->assertSame('RuntimeException', SafeErrorMessage::summarize($e));
    }

    public function test_query_exception_is_reduced_to_generic_database_error(): void
    {
        $connection = new SQLiteConnection(new \PDO('sqlite::memory:'));
        try {
            $connection->select('select * from "secrets"');
            $this->fail('La requête invalide doit lever QueryException.');
        } catch (QueryException $e) {
            $summary = SafeErrorMessage::summarize($e);
        }

        $this->assertSame('QueryException: database_error (détail technique en logs)', $summary);
        $this->assertStringNotContainsString('secrets', $summary);
    }
}
