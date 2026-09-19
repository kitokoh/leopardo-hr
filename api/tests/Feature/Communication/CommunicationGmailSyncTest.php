<?php

declare(strict_types=1);

namespace Tests\Feature\Communication;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Notifications\Contracts\InAppNotifier;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Communication\Domain\Exceptions\GmailRateLimitedException;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Modules\Communication\Domain\Models\CommunicationThread;
use App\Modules\Communication\Domain\Support\CommunicationFeatures;
use App\Modules\Communication\Infrastructure\Jobs\SyncGmailMailboxJob;
use App\Modules\Communication\Infrastructure\Services\GoogleGmailOAuthService;
use App\Modules\Communication\Infrastructure\Services\GoogleGmailSyncService;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-29 COMMUNICATION (R2, #7687) — sync Gmail incrementale : full sync
 * bornee aux 50 derniers fils, incremental via historyId (fallback full
 * sync sur 404), idempotence (resync sans doublons), MINIMISATION (headers
 * utiles + snippet + references message-id, corps text/plain CHIFFRE au
 * repos, pieces jointes NON stockees — references seulement), backoff 429,
 * token mort -> status error + notification, purge complete a la
 * revocation, endpoints de consultation (boite personnelle, policies).
 *
 * Tous les appels Gmail/Google sont mockes via Http::fake — AUCUN appel
 * reseau reel (exigence issue).
 */
class CommunicationGmailSyncTest extends TestCase
{
    use RefreshTenantDatabase;

    private const GMAIL = GoogleGmailSyncService::GMAIL_API_BASE;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        // AUCUN appel reseau reel : toute requete non stubbee explose.
        Http::preventStrayRequests();

        config()->set('services.google.client_id', 'test-client-id.apps.googleusercontent.com');
        config()->set('services.google.client_secret', 'test-client-secret');
        config()->set(
            'services.google.communication_redirect',
            'https://api.leopardo.test/api/v1/communication/integrations/google/callback'
        );

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $company->setFeature(CommunicationFeatures::COMMUNICATION, true);
        $company->save();
        $this->company = $company;

        $this->employee = $this->makeEmployee($this->company);
    }

    private function makeEmployee(Company $company, string $role = 'employee', ?string $managerRole = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => $role,
            'manager_role' => $managerRole,
        ]);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeIntegration(Employee $employee, array $attributes = []): CommunicationIntegration
    {
        $integration = new CommunicationIntegration;

        $integration->forceFill(array_merge([
            'company_id' => (string) $employee->company_id,
            'employee_id' => $employee->id,
            'provider' => CommunicationIntegration::PROVIDER_GOOGLE,
            'email' => 'mailbox-'.$employee->id.'@gmail.com',
            'scopes' => GoogleGmailOAuthService::DEFAULT_SCOPES,
            'access_token' => 'plain-access-token-'.$employee->id,
            'refresh_token' => 'plain-refresh-token-'.$employee->id,
            'expires_at' => now()->addHour(),
            'status' => CommunicationIntegration::STATUS_ACTIVE,
            'connected_at' => now(),
        ], $attributes));

        $integration->save();

        return $integration;
    }

    private static function base64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * Payload Gmail d'un message `format=full` (multipart : text/plain +
     * piece jointe PDF — le contenu de la piece jointe ne doit JAMAIS
     * arriver en base).
     *
     * @return array<string, mixed>
     */
    private function gmailMessage(
        string $id,
        string $threadId,
        string $body = 'Bonjour, voici le corps du message.',
        string $internalDate = '1758200000000',
    ): array {
        return [
            'id' => $id,
            'threadId' => $threadId,
            'labelIds' => ['INBOX', 'UNREAD'],
            'snippet' => 'Extrait Gmail du message '.$id,
            'internalDate' => $internalDate,
            'historyId' => '900',
            'payload' => [
                'mimeType' => 'multipart/mixed',
                'headers' => [
                    ['name' => 'From', 'value' => 'Alice Dupont <alice@example.com>'],
                    ['name' => 'To', 'value' => 'Moi <mailbox-'.$this->employee->id.'@gmail.com>'],
                    ['name' => 'Cc', 'value' => 'bob@example.com'],
                    ['name' => 'Subject', 'value' => 'Demande de devis'],
                    ['name' => 'Message-ID', 'value' => '<'.$id.'@mail.example.com>'],
                    ['name' => 'In-Reply-To', 'value' => '<parent@mail.example.com>'],
                    // Header NON conserve (minimisation) :
                    ['name' => 'Received', 'value' => 'from smtp.example.com (10.0.0.1)'],
                ],
                'parts' => [
                    [
                        'mimeType' => 'text/plain',
                        'body' => ['data' => self::base64url($body)],
                    ],
                    [
                        'mimeType' => 'text/html',
                        'body' => ['data' => self::base64url('<p>'.$body.'</p>')],
                    ],
                    [
                        'mimeType' => 'application/pdf',
                        'filename' => 'devis.pdf',
                        'body' => ['attachmentId' => 'att-'.$id, 'size' => 12345, 'data' => self::base64url('PDFBYTES')],
                    ],
                ],
            ],
        ];
    }

    /**
     * Fakes Http d'une FULL SYNC : profil (historyId), liste de fils,
     * deux fils complets.
     */
    private function fakeFullSync(): void
    {
        Http::fake([
            self::GMAIL.'/profile*' => Http::response(['emailAddress' => 'user@gmail.com', 'historyId' => '1000']),
            self::GMAIL.'/threads/thread-1*' => Http::response([
                'id' => 'thread-1',
                'messages' => [
                    $this->gmailMessage('msg-1', 'thread-1', 'Premier message.', '1758100000000'),
                    $this->gmailMessage('msg-2', 'thread-1', 'Reponse au premier message.', '1758200000000'),
                ],
            ]),
            self::GMAIL.'/threads/thread-2*' => Http::response([
                'id' => 'thread-2',
                'messages' => [
                    $this->gmailMessage('msg-3', 'thread-2', 'Autre fil.', '1758300000000'),
                ],
            ]),
            self::GMAIL.'/threads*' => Http::response([
                'threads' => [['id' => 'thread-1'], ['id' => 'thread-2']],
            ]),
        ]);
    }

    // ── Full sync (premiere connexion) ───────────────────────────────────

    public function test_initial_full_sync_ingests_recent_threads_with_minimized_metadata(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->fakeFullSync();

        app(GoogleGmailSyncService::class)->sync($integration);

        $integration->refresh();
        // Curseur incremental pose depuis le profil, full sync terminee.
        $this->assertSame('1000', $integration->sync_history_id);
        $this->assertNull($integration->sync_page_token);
        $this->assertNotNull($integration->last_synced_at);

        $threads = CommunicationThread::query()->withoutGlobalScope('company')->get();
        $this->assertCount(2, $threads);

        /** @var CommunicationThread $thread */
        $thread = $threads->firstWhere('gmail_thread_id', 'thread-1');
        $this->assertSame('Demande de devis', $thread->subject);
        $this->assertSame(2, $thread->message_count);
        $this->assertNotNull($thread->last_message_at);

        /** @var CommunicationMessage $message */
        $message = CommunicationMessage::query()
            ->withoutGlobalScope('company')
            ->where('gmail_message_id', 'msg-1')
            ->firstOrFail();

        // Metadonnees UTILES : adresses seules (pas de display name), sujet,
        // labels, snippet, references RFC 5322.
        $this->assertSame('alice@example.com', $message->from_email);
        $this->assertContains('bob@example.com', $message->cc_emails ?? []);
        $this->assertSame('Demande de devis', $message->subject);
        $this->assertSame(['INBOX', 'UNREAD'], $message->labels);
        $this->assertSame('<msg-1@mail.example.com>', $message->internet_message_id);
        $this->assertSame('<parent@mail.example.com>', $message->in_reply_to);
        // Corps text/plain dechiffre a la lecture par le cast.
        $this->assertSame('Premier message.', $message->body);

        // Pieces jointes : REFERENCE Gmail seulement, jamais le contenu.
        $refs = $message->attachment_refs ?? [];
        $this->assertCount(1, $refs);
        $this->assertSame('att-msg-1', $refs[0]['attachment_id']);
        $this->assertSame('devis.pdf', $refs[0]['filename']);
        $this->assertArrayNotHasKey('data', $refs[0]);
    }

    public function test_message_bodies_are_encrypted_at_rest_and_raw_headers_are_not_stored(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->fakeFullSync();

        app(GoogleGmailSyncService::class)->sync($integration);

        $raw = DB::table('communication_messages')->where('gmail_message_id', 'msg-1')->first();
        $this->assertNotNull($raw);

        // Corps CHIFFRE au repos : la colonne brute ne contient jamais le clair.
        $this->assertIsString($raw->body);
        $this->assertStringNotContainsString('Premier message.', $raw->body);

        // Minimisation : aucun header non liste (Received…) ni contenu de
        // piece jointe nulle part sur la ligne brute.
        $serialized = json_encode($raw);
        $this->assertIsString($serialized);
        $this->assertStringNotContainsString('smtp.example.com', $serialized);
        $this->assertStringNotContainsString(self::base64url('PDFBYTES'), $serialized);
    }

    public function test_resync_is_idempotent_and_never_duplicates(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->fakeFullSync();

        $service = app(GoogleGmailSyncService::class);
        $service->sync($integration);

        // Curseur efface -> le resync repart en FULL sync sur les memes fils.
        $integration->refresh();
        $integration->forceFill(['sync_history_id' => null])->save();
        $service->sync($integration);

        $this->assertSame(2, CommunicationThread::query()->withoutGlobalScope('company')->count());
        $this->assertSame(3, CommunicationMessage::query()->withoutGlobalScope('company')->count());
    }

    // ── Sync incrementale (historyId) ────────────────────────────────────

    public function test_incremental_sync_uses_history_id_and_only_reingests_touched_threads(): void
    {
        $integration = $this->makeIntegration($this->employee);

        // Tous les stubs poses d'emblee (Http::fake ne re-stubbe pas un
        // pattern deja enregistre) : thread-1 renvoie 2 messages pendant la
        // full sync, 3 messages pendant la passe incrementale.
        $threadOneCalls = 0;
        Http::fake([
            self::GMAIL.'/profile*' => Http::response(['emailAddress' => 'user@gmail.com', 'historyId' => '1000']),
            self::GMAIL.'/history*' => Http::response([
                'historyId' => '1100',
                'history' => [
                    ['messagesAdded' => [['message' => ['id' => 'msg-4', 'threadId' => 'thread-1']]]],
                ],
            ]),
            self::GMAIL.'/threads/thread-1*' => function () use (&$threadOneCalls) {
                $threadOneCalls++;

                $messages = [
                    $this->gmailMessage('msg-1', 'thread-1', 'Premier message.', '1758100000000'),
                    $this->gmailMessage('msg-2', 'thread-1', 'Reponse au premier message.', '1758200000000'),
                ];

                if ($threadOneCalls > 1) {
                    $messages[] = $this->gmailMessage('msg-4', 'thread-1', 'Message arrive depuis la derniere passe.', '1758400000000');
                }

                return Http::response(['id' => 'thread-1', 'messages' => $messages]);
            },
            self::GMAIL.'/threads/thread-2*' => Http::response([
                'id' => 'thread-2',
                'messages' => [$this->gmailMessage('msg-3', 'thread-2', 'Autre fil.', '1758300000000')],
            ]),
            self::GMAIL.'/threads*' => Http::response([
                'threads' => [['id' => 'thread-1'], ['id' => 'thread-2']],
            ]),
        ]);

        // Passe 1 : full sync (premiere connexion).
        app(GoogleGmailSyncService::class)->sync($integration);
        $integration->refresh();
        $this->assertSame('1000', $integration->sync_history_id);

        // Passe 2 : incrementale.
        app(GoogleGmailSyncService::class)->sync($integration);

        // La passe incrementale part bien du curseur persiste…
        Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/history')
            && str_contains($request->url(), 'startHistoryId=1000'));
        // …sans re-derouler une full sync (une seule lecture du profil,
        // celle de la passe initiale).
        $this->assertSame(1, Http::recorded(
            fn (ClientRequest $request): bool => str_contains($request->url(), '/profile')
        )->count());

        $integration->refresh();
        $this->assertSame('1100', $integration->sync_history_id);

        // Pas de doublons : thread-1 re-ingere en upsert, msg-4 ajoute.
        $this->assertSame(2, CommunicationThread::query()->withoutGlobalScope('company')->count());
        $this->assertSame(4, CommunicationMessage::query()->withoutGlobalScope('company')->count());

        /** @var CommunicationThread $thread */
        $thread = CommunicationThread::query()
            ->withoutGlobalScope('company')
            ->where('gmail_thread_id', 'thread-1')
            ->firstOrFail();
        $this->assertSame(3, $thread->message_count);
    }

    public function test_expired_history_id_falls_back_to_full_sync_without_duplicates(): void
    {
        $integration = $this->makeIntegration($this->employee);

        // Stubs poses d'emblee : le profil rend un historyId different a
        // chaque passe ; /history repond TOUJOURS 404 (curseur expire).
        $profileCalls = 0;
        Http::fake([
            self::GMAIL.'/profile*' => function () use (&$profileCalls) {
                $profileCalls++;

                return Http::response([
                    'emailAddress' => 'user@gmail.com',
                    'historyId' => $profileCalls === 1 ? '1000' : '2000',
                ]);
            },
            self::GMAIL.'/history*' => Http::response(['error' => ['code' => 404]], 404),
            self::GMAIL.'/threads/thread-1*' => Http::response([
                'id' => 'thread-1',
                'messages' => [
                    $this->gmailMessage('msg-1', 'thread-1', 'Premier message.', '1758100000000'),
                    $this->gmailMessage('msg-2', 'thread-1', 'Reponse au premier message.', '1758200000000'),
                ],
            ]),
            self::GMAIL.'/threads/thread-2*' => Http::response([
                'id' => 'thread-2',
                'messages' => [$this->gmailMessage('msg-3', 'thread-2', 'Autre fil.', '1758300000000')],
            ]),
            self::GMAIL.'/threads*' => Http::response([
                'threads' => [['id' => 'thread-1'], ['id' => 'thread-2']],
            ]),
        ]);

        // Passe 1 : full sync -> curseur 1000.
        app(GoogleGmailSyncService::class)->sync($integration);
        $integration->refresh();
        $this->assertSame('1000', $integration->sync_history_id);

        // Passe 2 : Google repond 404 -> fallback FULL sync.
        app(GoogleGmailSyncService::class)->sync($integration);

        $integration->refresh();
        // Nouveau curseur pris sur le profil, resync SANS doublons.
        $this->assertSame('2000', $integration->sync_history_id);
        $this->assertSame(2, CommunicationThread::query()->withoutGlobalScope('company')->count());
        $this->assertSame(3, CommunicationMessage::query()->withoutGlobalScope('company')->count());
    }

    // ── Erreurs : quota (429) et token mort ──────────────────────────────

    public function test_rate_limited_sync_raises_backoff_exception_with_retry_after(): void
    {
        $integration = $this->makeIntegration($this->employee);

        Http::fake([
            self::GMAIL.'/profile*' => Http::response(['error' => ['code' => 429]], 429, ['Retry-After' => '120']),
        ]);

        try {
            app(GoogleGmailSyncService::class)->sync($integration);
            $this->fail('GmailRateLimitedException attendue.');
        } catch (GmailRateLimitedException $exception) {
            $this->assertSame(120, $exception->retryAfterSeconds);
        }

        // Le quota n'est PAS une erreur de la boite : elle reste active.
        $this->assertSame(CommunicationIntegration::STATUS_ACTIVE, $integration->refresh()->status);
    }

    public function test_rate_limited_job_releases_itself_with_backoff(): void
    {
        $integration = $this->makeIntegration($this->employee);

        Http::fake([
            self::GMAIL.'/profile*' => Http::response(['error' => ['code' => 429]], 429, ['Retry-After' => '90']),
        ]);

        $job = (new SyncGmailMailboxJob((string) $this->company->id, $integration->id))
            ->withFakeQueueInteractions();

        $job->handle(app(GoogleGmailSyncService::class), app(InAppNotifier::class));

        $job->assertReleased(90);
    }

    public function test_dead_token_marks_integration_error_and_notifies_owner(): void
    {
        // Access token expire + refresh refuse (invalid_grant) : la boite
        // passe en `error` et le proprietaire est notifie (exigence issue).
        $integration = $this->makeIntegration($this->employee, [
            'expires_at' => now()->subMinute(),
        ]);

        Http::fake([
            GoogleGmailOAuthService::TOKEN_ENDPOINT => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $notifier = $this->mock(InAppNotifier::class);
        $notifier->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn (int $userId, string $type): bool => $userId === (int) $this->employee->id
                && $type === 'communication_sync_error');

        (new SyncGmailMailboxJob((string) $this->company->id, $integration->id))
            ->handle(app(GoogleGmailSyncService::class), app(InAppNotifier::class));

        $this->assertSame(CommunicationIntegration::STATUS_ERROR, $integration->refresh()->status);
    }

    public function test_job_skips_revoked_integrations_without_calling_gmail(): void
    {
        $integration = $this->makeIntegration($this->employee, [
            'status' => CommunicationIntegration::STATUS_REVOKED,
            'access_token' => null,
            'refresh_token' => null,
        ]);

        Http::fake();

        (new SyncGmailMailboxJob((string) $this->company->id, $integration->id))
            ->handle(app(GoogleGmailSyncService::class), app(InAppNotifier::class));

        Http::assertNothingSent();
    }

    // ── Commande schedulee (polling 5 min) ───────────────────────────────

    public function test_scheduled_command_dispatches_one_job_per_active_google_mailbox(): void
    {
        Queue::fake();

        $active = $this->makeIntegration($this->employee);
        // Boite revoquee : jamais re-syncee.
        $this->makeIntegration($this->makeEmployee($this->company), [
            'status' => CommunicationIntegration::STATUS_REVOKED,
        ]);

        // Tenant SANS le module : ignore (kill switch).
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->makeIntegration($this->makeEmployee($other));

        $this->artisan('communication:sync-mailboxes')
            ->assertExitCode(0);

        Queue::assertPushed(SyncGmailMailboxJob::class, 1);
        Queue::assertPushedOn('communication', SyncGmailMailboxJob::class);
        Queue::assertPushed(
            SyncGmailMailboxJob::class,
            fn (SyncGmailMailboxJob $job): bool => $job->tenantCompanyId() === (string) $this->company->id
        );

        // Idempotence du polling : rejouer la commande ne fait que re-dispatcher
        // des jobs upsert (le WithoutOverlapping par boite protege l'execution).
        $this->artisan('communication:sync-mailboxes')->assertExitCode(0);
        Queue::assertPushed(SyncGmailMailboxJob::class, 2);
        $this->assertNotNull($active->refresh());
    }

    // ── Endpoints de consultation (boite personnelle, policies) ──────────

    public function test_threads_index_returns_only_own_threads_most_recent_first(): void
    {
        $mine = $this->makeIntegration($this->employee);
        $colleague = $this->makeEmployee($this->company);
        $other = $this->makeIntegration($colleague);

        $this->seedThread($mine, 'thread-old', 'Ancien fil', now()->subDays(2));
        $this->seedThread($mine, 'thread-new', 'Fil recent', now()->subHour());
        $this->seedThread($other, 'thread-colleague', 'Fil du collegue', now());

        Sanctum::actingAs($this->employee);

        $response = $this->getJson('/api/v1/communication/threads')->assertStatus(200);

        $subjects = array_column($response->json('data'), 'subject');
        // Tri du plus recent au plus ancien, et JAMAIS le fil du collegue.
        $this->assertSame(['Fil recent', 'Ancien fil'], $subjects);
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_thread_messages_expose_decrypted_body_to_owner_only(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $thread = $this->seedThread($integration, 'thread-1', 'Demande de devis', now());
        $this->seedMessage($integration, $thread, 'msg-1', 'Corps confidentiel du message.');

        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/communication/threads/'.$thread->id.'/messages')
            ->assertStatus(200)
            ->assertJsonPath('data.thread.gmail_thread_id', 'thread-1')
            ->assertJsonPath('data.messages.0.gmail_message_id', 'msg-1')
            ->assertJsonPath('data.messages.0.body', 'Corps confidentiel du message.');
    }

    public function test_mailbox_content_is_never_visible_to_colleagues_even_managers(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $thread = $this->seedThread($integration, 'thread-1', 'Prive', now());

        // Collegue simple : 403.
        Sanctum::actingAs($this->makeEmployee($this->company));
        $this->getJson('/api/v1/communication/threads/'.$thread->id.'/messages')
            ->assertStatus(403);

        // MEME principal/rh : le contenu d'une boite reste personnel (ils
        // peuvent revoquer la boite, pas la lire).
        Sanctum::actingAs($this->makeEmployee($this->company, 'manager', 'principal'));
        $this->getJson('/api/v1/communication/threads/'.$thread->id.'/messages')
            ->assertStatus(403);
    }

    public function test_cross_tenant_thread_is_a_404(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $thread = $this->seedThread($integration, 'thread-1', 'Prive', now());

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $other->setFeature(CommunicationFeatures::COMMUNICATION, true);
        $other->save();

        Sanctum::actingAs($this->makeEmployee($other));

        $this->getJson('/api/v1/communication/threads/'.$thread->id.'/messages')
            ->assertStatus(404);
    }

    // ── Purge a la deconnexion ───────────────────────────────────────────

    public function test_revocation_purges_all_synced_threads_and_messages(): void
    {
        $integration = $this->makeIntegration($this->employee, [
            'sync_history_id' => '1000',
            'last_synced_at' => now(),
        ]);
        $thread = $this->seedThread($integration, 'thread-1', 'Demande de devis', now());
        $this->seedMessage($integration, $thread, 'msg-1', 'Corps confidentiel.');

        Http::fake([
            GoogleGmailOAuthService::REVOKE_ENDPOINT => Http::response([], 200),
        ]);

        Sanctum::actingAs($this->employee);

        $this->deleteJson('/api/v1/communication/integrations/'.$integration->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', CommunicationIntegration::STATUS_REVOKED);

        // Plus AUCUN fil, message ni corps en base (droit a l'effacement) ;
        // curseurs remis a zero -> une reconnexion repart d'une full sync.
        $this->assertSame(0, CommunicationThread::query()->withoutGlobalScope('company')->count());
        $this->assertSame(0, CommunicationMessage::query()->withoutGlobalScope('company')->count());
        $this->assertSame(0, DB::table('communication_messages')->count());

        $integration->refresh();
        $this->assertNull($integration->sync_history_id);
        $this->assertNull($integration->last_synced_at);
        $this->assertNull($integration->access_token);
        $this->assertNull($integration->refresh_token);
    }

    // ── Helpers de seed ──────────────────────────────────────────────────

    private function seedThread(
        CommunicationIntegration $integration,
        string $gmailThreadId,
        string $subject,
        \Illuminate\Support\Carbon $lastMessageAt,
    ): CommunicationThread {
        $thread = new CommunicationThread;

        $thread->forceFill([
            'company_id' => $integration->company_id,
            'integration_id' => $integration->id,
            'gmail_thread_id' => $gmailThreadId,
            'subject' => $subject,
            'snippet' => 'Extrait de '.$subject,
            'message_count' => 1,
            'last_message_at' => $lastMessageAt,
        ])->save();

        return $thread;
    }

    private function seedMessage(
        CommunicationIntegration $integration,
        CommunicationThread $thread,
        string $gmailMessageId,
        string $body,
    ): CommunicationMessage {
        $message = new CommunicationMessage;

        $message->forceFill([
            'company_id' => $integration->company_id,
            'thread_id' => $thread->id,
            'integration_id' => $integration->id,
            'gmail_message_id' => $gmailMessageId,
            'from_email' => 'alice@example.com',
            'to_emails' => ['mailbox@gmail.com'],
            'subject' => $thread->subject,
            'snippet' => 'Extrait',
            'body' => $body,
            'labels' => ['INBOX'],
            'sent_at' => now(),
        ])->save();

        return $message;
    }
}
