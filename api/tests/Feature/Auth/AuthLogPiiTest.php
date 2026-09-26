<?php

namespace Tests\Feature\Auth;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8164 — les points de log d'AuthService ne doivent JAMAIS contenir l'email
 * en clair (PII) : on journalise `email_hash` (pseudonyme corrélable,
 * {@see \App\Support\EmailLogId}), même en cas d'erreur de résolution.
 */
class AuthLogPiiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_login_resolution_failure_logs_email_hash_not_plain_email(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'schema_name' => 'pii_ghost_schema',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        // Schéma tenant RÉEL avec une table employees partiellement migrée :
        // la requête de résolution lève une QueryException (colonne absente)
        // — c'est le chemin qui journalise auth.login_employee_resolution_failed.
        // (DDL transactionnel sous pgsql : nettoyé au rollback du test.)
        DB::statement('CREATE SCHEMA pii_ghost_schema');
        DB::statement('CREATE TABLE pii_ghost_schema.employees (id bigint PRIMARY KEY)');

        $email = 'pii-target@missing-schema.dz';

        DB::table('public.user_lookups')->insert([
            'email' => $email,
            'company_id' => $company->id,
            'schema_name' => 'pii_ghost_schema',
            'employee_id' => 424242,
            'role' => 'employee',
        ]);

        /** @var list<MessageLogged> $records */
        $records = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$records): void {
            $records[] = $event;
        });

        // La QueryException (table partiellement migrée) est attrapée par
        // AuthService en production, où aucune transaction externe n'est
        // ouverte. Ici, le test enveloppe tout dans une transaction : une fois
        // l'erreur levée, pgsql la met en état « aborted ». On borne donc la
        // requête dans un savepoint et on y revient pour les assertions.
        DB::statement('SAVEPOINT before_pii_login');

        $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ]);

        DB::statement('ROLLBACK TO SAVEPOINT before_pii_login');

        $authLogs = collect($records)->filter(
            fn (MessageLogged $e): bool => str_starts_with($e->message, 'auth.')
        );

        $this->assertNotEmpty($authLogs->all(), 'la résolution fantôme doit produire au moins un log auth.* structuré');

        $sawEmailHash = false;

        foreach ($authLogs as $log) {
            $serialized = json_encode($log->context, JSON_UNESCAPED_UNICODE) ?: '';

            $this->assertStringNotContainsString(
                $email,
                $serialized,
                "le contexte du log « {$log->message} » contient l'email en clair"
            );

            $emailHash = $log->context['email_hash'] ?? null;

            if (is_string($emailHash)) {
                $sawEmailHash = true;
                $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $emailHash);
            }
        }

        $this->assertTrue($sawEmailHash, 'au moins un log auth.* doit porter le pseudonyme email_hash');
    }
}
