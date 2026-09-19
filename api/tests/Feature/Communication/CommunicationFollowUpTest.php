<?php

declare(strict_types=1);

namespace Tests\Feature\Communication;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Communication\Domain\Models\CommunicationFollowUp;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpLog;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpOptOut;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpRule;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpStep;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Modules\Communication\Domain\Models\CommunicationThread;
use App\Modules\Communication\Domain\Support\CommunicationFeatures;
use App\Modules\Communication\Infrastructure\Services\GoogleGmailFollowUpSender;
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
 * BC-29 COMMUNICATION (R4, #7689) — moteur de relances automatiques :
 * regles/sequences par utilisateur (max 3 etapes, delais configurables),
 * commande schedulee IDEMPOTENTE `communication:send-follow-ups` (table de
 * deduplication : une relance par echeance), garde-fous stricts (arret sur
 * reponse detectee via la sync R2, auto-repondeurs RFC 3834 / listes de
 * diffusion, opt-out local, consentement CRM + unsubscribe via le contrat
 * partage `EmailFollowUpConsentGate`, quiet hours, plafonds journaliers par
 * boite et par destinataire, scope `gmail.send` requis), envoi via le Gmail
 * du proprietaire (tokens chiffres R1, threading RFC 5322), journal d'audit
 * append-only, endpoints CRUD + RBAC + isolation tenant.
 *
 * `Http::fake` + `Http::preventStrayRequests()` — AUCUN appel reseau reel.
 */
class CommunicationFollowUpTest extends TestCase
{
    use RefreshTenantDatabase;

    private const SEND = GoogleGmailFollowUpSender::SEND_ENDPOINT;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config()->set('services.google.client_id', 'test-client-id.apps.googleusercontent.com');
        config()->set('services.google.client_secret', 'test-client-secret');
        config()->set(
            'services.google.communication_redirect',
            'https://api.leopardo.test/api/v1/communication/integrations/google/callback'
        );

        // Fenetre calme desactivee par defaut dans les tests (start == end) ;
        // chaque test de quiet hours la re-active explicitement.
        config()->set('communication.follow_ups.quiet_hours', [
            'start' => 0,
            'end' => 0,
            'timezone' => 'UTC',
        ]);

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $company->setFeature(CommunicationFeatures::COMMUNICATION, true);
        $company->save();
        $this->company = $company;

        $this->employee = $this->makeEmployee($this->company);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

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
            'scopes' => array_merge(
                GoogleGmailOAuthService::DEFAULT_SCOPES,
                [GoogleGmailOAuthService::GMAIL_SEND_SCOPE],
            ),
            'access_token' => 'plain-access-token-'.$employee->id,
            'refresh_token' => 'plain-refresh-token-'.$employee->id,
            'expires_at' => now()->addHour(),
            'status' => CommunicationIntegration::STATUS_ACTIVE,
            'connected_at' => now(),
        ], $attributes));

        $integration->save();

        return $integration;
    }

    /**
     * @param  list<array{delay_days: int}>  $steps
     */
    private function makeRule(CommunicationIntegration $integration, array $steps = [['delay_days' => 3]], bool $active = true): CommunicationFollowUpRule
    {
        $rule = new CommunicationFollowUpRule;
        $rule->forceFill([
            'company_id' => (string) $integration->company_id,
            'integration_id' => $integration->id,
            'name' => 'Relance devis',
            'active' => $active,
        ]);
        $rule->save();

        foreach (array_values($steps) as $index => $payload) {
            $step = new CommunicationFollowUpStep;
            $step->forceFill([
                'company_id' => (string) $integration->company_id,
                'rule_id' => $rule->id,
                'position' => $index + 1,
                'delay_days' => $payload['delay_days'],
                'template_key' => 'communication_follow_up',
            ]);
            $step->save();
        }

        return $rule;
    }

    private function makeThread(CommunicationIntegration $integration, string $subject = 'Proposition commerciale'): CommunicationThread
    {
        $thread = new CommunicationThread;
        $thread->forceFill([
            'company_id' => (string) $integration->company_id,
            'integration_id' => $integration->id,
            'gmail_thread_id' => 'thread-'.fake()->unique()->numerify('######'),
            'subject' => $subject,
            'snippet' => 'Bonjour, voici notre proposition.',
            'message_count' => 1,
            'last_message_at' => now(),
        ]);
        $thread->save();

        return $thread;
    }

    /**
     * Message SORTANT (envoye par la boite) — l'ancre d'une relance.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function makeOutbound(
        CommunicationIntegration $integration,
        CommunicationThread $thread,
        string $to = 'prospect@example.com',
        ?string $sentAt = null,
        array $attributes = [],
    ): CommunicationMessage {
        return $this->makeMessage($integration, $thread, array_merge([
            'from_email' => $integration->email,
            'to_emails' => [$to],
            'sent_at' => $sentAt ?? now()->subDays(5),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeMessage(
        CommunicationIntegration $integration,
        CommunicationThread $thread,
        array $attributes = [],
    ): CommunicationMessage {
        $message = new CommunicationMessage;
        $message->forceFill(array_merge([
            'company_id' => (string) $integration->company_id,
            'thread_id' => $thread->id,
            'integration_id' => $integration->id,
            'gmail_message_id' => 'msg-'.fake()->unique()->numerify('########'),
            'internet_message_id' => '<'.fake()->unique()->numerify('########').'@mail.example.com>',
            'from_email' => 'prospect@example.com',
            'to_emails' => [(string) $integration->email],
            'subject' => $thread->subject,
            'snippet' => 'Extrait du message',
            'body' => 'Corps du message.',
            'sent_at' => now()->subDay(),
            'classification_status' => CommunicationMessage::CLASSIFICATION_CLASSIFIED,
        ], $attributes));
        $message->save();

        return $message;
    }

    private function fakeSendOk(string $gmailId = 'sent-gmail-1'): void
    {
        Http::fake([self::SEND.'*' => Http::response(['id' => $gmailId, 'threadId' => 'thread-x'])]);
    }

    private function runCommand(): void
    {
        $this->artisan('communication:send-follow-ups')->assertExitCode(0);
    }

    // ── CRUD regles / sequences ──────────────────────────────────────────

    public function test_owner_creates_rule_with_sequence_and_index_is_owner_scoped(): void
    {
        $integration = $this->makeIntegration($this->employee);

        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/communication/follow-up-rules', [
            'integration_id' => $integration->id,
            'name' => 'Relance devis',
            'steps' => [
                ['delay_days' => 3],
                ['delay_days' => 4],
                ['delay_days' => 7],
            ],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Relance devis')
            ->assertJsonPath('data.active', true)
            ->assertJsonCount(3, 'data.steps')
            ->assertJsonPath('data.steps.0.position', 1)
            ->assertJsonPath('data.steps.2.delay_days', 7);

        // 4 etapes = au-dela du max 3 (spec §3.4) -> 422 de validation.
        $this->postJson('/api/v1/communication/follow-up-rules', [
            'integration_id' => $integration->id,
            'name' => 'Trop d etapes',
            'steps' => [
                ['delay_days' => 1], ['delay_days' => 2], ['delay_days' => 3], ['delay_days' => 4],
            ],
        ])->assertStatus(422);

        // L'index d'un collegue ne montre RIEN (regles personnelles).
        Sanctum::actingAs($this->makeEmployee($this->company));
        $this->getJson('/api/v1/communication/follow-up-rules')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_rule_activation_requires_gmail_send_scope(): void
    {
        // Boite connectee SANS le scope d'envoi (R1 par defaut).
        $integration = $this->makeIntegration($this->employee, [
            'scopes' => GoogleGmailOAuthService::DEFAULT_SCOPES,
        ]);

        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/communication/follow-up-rules', [
            'integration_id' => $integration->id,
            'name' => 'Relance',
            'steps' => [['delay_days' => 3]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'GMAIL_SEND_SCOPE_REQUIRED');

        // Inactive : acceptee (sera activable apres reconnexion avec le scope).
        $this->postJson('/api/v1/communication/follow-up-rules', [
            'integration_id' => $integration->id,
            'name' => 'Relance',
            'active' => false,
            'steps' => [['delay_days' => 3]],
        ])->assertStatus(201);

        // La reconnexion `with_send` demande bien le scope gmail.send.
        $response = $this->postJson('/api/v1/communication/integrations/google', ['with_send' => true])
            ->assertStatus(200);
        $this->assertStringContainsString(
            urlencode(GoogleGmailOAuthService::GMAIL_SEND_SCOPE),
            (string) $response->json('data.authorization_url')
        );
    }

    public function test_rule_is_owner_only_for_update_and_delete(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $rule = $this->makeRule($integration);

        // Collegue : 403 (meme tenant, pas sa boite).
        Sanctum::actingAs($this->makeEmployee($this->company));
        $this->patchJson('/api/v1/communication/follow-up-rules/'.$rule->id, ['name' => 'X'])
            ->assertStatus(403);

        // Meme principal : 403 (la boite est personnelle).
        Sanctum::actingAs($this->makeEmployee($this->company, 'manager', 'principal'));
        $this->deleteJson('/api/v1/communication/follow-up-rules/'.$rule->id)
            ->assertStatus(403);

        // Cross-tenant : 404 (existence non revelee).
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $other->setFeature(CommunicationFeatures::COMMUNICATION, true);
        $other->save();
        Sanctum::actingAs($this->makeEmployee($other));
        $this->patchJson('/api/v1/communication/follow-up-rules/'.$rule->id, ['name' => 'X'])
            ->assertStatus(404);

        // Proprietaire : OK.
        Sanctum::actingAs($this->employee);
        $this->patchJson('/api/v1/communication/follow-up-rules/'.$rule->id, [
            'name' => 'Relance mise a jour',
            'steps' => [['delay_days' => 2]],
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Relance mise a jour')
            ->assertJsonCount(1, 'data.steps');
        $this->deleteJson('/api/v1/communication/follow-up-rules/'.$rule->id)->assertStatus(204);
    }

    // ── Envoi + idempotence ──────────────────────────────────────────────

    public function test_due_follow_up_is_sent_once_via_owner_gmail_and_audited(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->makeRule($integration, [['delay_days' => 3]]);
        $thread = $this->makeThread($integration);
        $anchor = $this->makeOutbound($integration, $thread, 'prospect@example.com', (string) now()->subDays(5));

        $this->fakeSendOk('sent-gmail-42');

        $this->runCommand();

        /** @var CommunicationFollowUp $followUp */
        $followUp = CommunicationFollowUp::query()->withoutGlobalScopes()->sole();
        $this->assertSame(CommunicationFollowUp::STATUS_SENT, $followUp->status);
        $this->assertSame('prospect@example.com', $followUp->contact_email);
        $this->assertSame(1, $followUp->step_position);
        $this->assertSame('sent-gmail-42', $followUp->sent_gmail_message_id);

        // L'envoi part DANS LE FIL Gmail d'origine, en repondant au
        // Message-ID du message sortant ancre (threading RFC 5322).
        Http::assertSent(function (ClientRequest $request) use ($thread, $anchor): bool {
            if (! str_contains($request->url(), '/messages/send')) {
                return false;
            }

            $raw = base64_decode(strtr((string) $request['raw'], '-_', '+/'));

            return $request['threadId'] === $thread->gmail_thread_id
                && is_string($raw)
                && str_contains($raw, 'To: prospect@example.com')
                && str_contains($raw, 'In-Reply-To: '.(string) $anchor->internet_message_id);
        });

        // Journal d'audit (« tout envoi audite »).
        $log = CommunicationFollowUpLog::query()->withoutGlobalScopes()->sole();
        $this->assertSame(CommunicationFollowUpLog::ACTION_SENT, $log->action);
        $this->assertSame($followUp->id, $log->follow_up_id);

        // REJOUER la commande : deduplication -> aucune nouvelle ligne,
        // aucun nouvel envoi (« relance part une seule fois par echeance »).
        $this->runCommand();
        Http::assertSentCount(1);
        $this->assertSame(1, CommunicationFollowUp::query()->withoutGlobalScopes()->count());
    }

    public function test_sequence_progresses_step_by_step_after_each_send(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->makeRule($integration, [['delay_days' => 3], ['delay_days' => 4]]);
        $thread = $this->makeThread($integration);
        $this->makeOutbound($integration, $thread, 'prospect@example.com', (string) now()->subDays(5));

        $this->fakeSendOk();

        // Passe 1 : etape 1 due (J+3 < J+5), etape 2 pas encore.
        $this->runCommand();
        $this->assertSame(1, CommunicationFollowUp::query()->withoutGlobalScopes()->count());
        Http::assertSentCount(1);

        // 4 jours plus tard : etape 2 due (delai depuis l'envoi de l'etape 1).
        $this->travel(4)->days();
        $this->runCommand();

        $positions = CommunicationFollowUp::query()
            ->withoutGlobalScopes()
            ->orderBy('step_position')
            ->pluck('status', 'step_position');
        $this->assertSame(CommunicationFollowUp::STATUS_SENT, $positions[1]);
        $this->assertSame(CommunicationFollowUp::STATUS_SENT, $positions[2]);
        Http::assertSentCount(2);
    }

    // ── Garde-fous ───────────────────────────────────────────────────────

    public function test_reply_detected_via_sync_stops_the_sequence(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->makeRule($integration, [['delay_days' => 3]]);
        $thread = $this->makeThread($integration);
        $this->makeOutbound($integration, $thread, 'prospect@example.com', (string) now()->subDays(5));
        // Le prospect a REPONDU (message entrant ingere par la sync R2).
        $this->makeMessage($integration, $thread, [
            'from_email' => 'prospect@example.com',
            'sent_at' => now()->subDays(2),
        ]);

        Http::fake(); // rien ne doit partir

        $this->runCommand();

        Http::assertNothingSent();
        $this->assertSame(0, CommunicationFollowUp::query()->withoutGlobalScopes()->count());
    }

    public function test_reply_arriving_after_materialization_cancels_pending_follow_up(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->makeRule($integration, [['delay_days' => 3]]);
        $thread = $this->makeThread($integration);
        $anchor = $this->makeOutbound($integration, $thread, 'prospect@example.com', (string) now()->subDays(5));

        // Quiet hours couvrent TOUTE la journee : la passe 1 materialise
        // l'echeance mais ne l'envoie pas (defer).
        config()->set('communication.follow_ups.quiet_hours', ['start' => 0, 'end' => 24, 'timezone' => 'UTC']);
        Http::fake();
        $this->runCommand();

        /** @var CommunicationFollowUp $pending */
        $pending = CommunicationFollowUp::query()->withoutGlobalScopes()->sole();
        $this->assertSame(CommunicationFollowUp::STATUS_PENDING, $pending->status);
        Http::assertNothingSent();

        // La reponse arrive (sync R2), la fenetre calme se leve : l'echeance
        // doit etre ANNULEE, pas envoyee.
        $this->makeMessage($integration, $thread, [
            'from_email' => 'prospect@example.com',
            'sent_at' => now(),
        ]);
        config()->set('communication.follow_ups.quiet_hours', ['start' => 0, 'end' => 0, 'timezone' => 'UTC']);

        $this->runCommand();

        Http::assertNothingSent();
        $pending->refresh();
        $this->assertSame(CommunicationFollowUp::STATUS_CANCELLED, $pending->status);
        $this->assertSame('replied', $pending->skip_reason);
        $this->assertSame(
            1,
            CommunicationFollowUpLog::query()
                ->withoutGlobalScopes()
                ->where('action', CommunicationFollowUpLog::ACTION_CANCELLED)
                ->where('reason', 'replied')
                ->count()
        );
        $this->assertNotNull($anchor->refresh());
    }

    public function test_auto_reply_rfc3834_freezes_sequence_without_counting_as_reply(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->makeRule($integration, [['delay_days' => 3]]);
        $thread = $this->makeThread($integration);
        $this->makeOutbound($integration, $thread, 'prospect@example.com', (string) now()->subDays(5));
        // Reponse AUTOMATIQUE (out-of-office, Auto-Submitted != no).
        $this->makeMessage($integration, $thread, [
            'from_email' => 'prospect@example.com',
            'sent_at' => now()->subDays(4),
            'is_auto_reply' => true,
        ]);

        Http::fake();

        $this->runCommand();

        Http::assertNothingSent();
        // L'auto-reponse ne compte pas comme le dernier mot du destinataire
        // (l'ancre reste le sortant) mais gele la sequence : skipped.
        /** @var CommunicationFollowUp $followUp */
        $followUp = CommunicationFollowUp::query()->withoutGlobalScopes()->sole();
        $this->assertSame(CommunicationFollowUp::STATUS_SKIPPED, $followUp->status);
        $this->assertSame('auto_reply', $followUp->skip_reason);
    }

    public function test_mailing_list_thread_is_never_followed_up(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->makeRule($integration, [['delay_days' => 3]]);
        $thread = $this->makeThread($integration);
        // Dernier message du fil : message de LISTE (List-Id) sortant de la
        // boite (post a une liste) -> jamais candidat.
        $this->makeOutbound($integration, $thread, 'liste@example.com', (string) now()->subDays(5), [
            'is_list_message' => true,
        ]);

        Http::fake();

        $this->runCommand();

        Http::assertNothingSent();
        $this->assertSame(0, CommunicationFollowUp::query()->withoutGlobalScopes()->count());
    }

    public function test_opt_out_blocks_follow_up(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->makeRule($integration, [['delay_days' => 3]]);
        $thread = $this->makeThread($integration);
        $this->makeOutbound($integration, $thread, 'prospect@example.com', (string) now()->subDays(5));

        $optOut = new CommunicationFollowUpOptOut;
        $optOut->forceFill([
            'company_id' => (string) $this->company->id,
            'email' => 'prospect@example.com',
            'source' => CommunicationFollowUpOptOut::SOURCE_MANUAL,
        ]);
        $optOut->save();

        Http::fake();

        $this->runCommand();

        Http::assertNothingSent();
        /** @var CommunicationFollowUp $followUp */
        $followUp = CommunicationFollowUp::query()->withoutGlobalScopes()->sole();
        $this->assertSame(CommunicationFollowUp::STATUS_SKIPPED, $followUp->status);
        $this->assertSame('opted_out', $followUp->skip_reason);
    }

    public function test_crm_suppression_and_withdrawn_consent_block_follow_up(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->makeRule($integration, [['delay_days' => 3]]);
        $thread = $this->makeThread($integration);
        $this->makeOutbound($integration, $thread, 'unsubscribed@example.com', (string) now()->subDays(5));

        // Adresse presente dans la liste de suppression CRM (#5726 :
        // unsubscribe / bounce / plainte, hash sha256 normalise).
        DB::table('crm_email_suppressions')->insert([
            'company_id' => (string) $this->company->id,
            'email_hash' => hash('sha256', 'unsubscribed@example.com'),
            'reason' => 'unsubscribe',
            'source' => 'email_link',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake();

        $this->runCommand();

        Http::assertNothingSent();
        /** @var CommunicationFollowUp $followUp */
        $followUp = CommunicationFollowUp::query()->withoutGlobalScopes()->sole();
        $this->assertSame(CommunicationFollowUp::STATUS_SKIPPED, $followUp->status);
        $this->assertSame('consent_blocked', $followUp->skip_reason);
    }

    public function test_quiet_hours_defer_sending_without_terminating(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->makeRule($integration, [['delay_days' => 3]]);
        $thread = $this->makeThread($integration);
        $this->makeOutbound($integration, $thread, 'prospect@example.com', (string) now()->subDays(5));

        // Fenetre calme couvrant TOUTE la journee : rien ne part.
        config()->set('communication.follow_ups.quiet_hours', ['start' => 0, 'end' => 24, 'timezone' => 'UTC']);
        Http::fake([self::SEND.'*' => Http::response(['id' => 'sent-1'])]);
        $this->runCommand();

        Http::assertNothingSent();
        $this->assertSame(
            CommunicationFollowUp::STATUS_PENDING,
            CommunicationFollowUp::query()->withoutGlobalScopes()->sole()->status
        );

        // Fenetre levee : la MEME echeance part a la passe suivante.
        config()->set('communication.follow_ups.quiet_hours', ['start' => 0, 'end' => 0, 'timezone' => 'UTC']);
        $this->runCommand();

        Http::assertSentCount(1);
        $this->assertSame(
            CommunicationFollowUp::STATUS_SENT,
            CommunicationFollowUp::query()->withoutGlobalScopes()->sole()->status
        );
    }

    public function test_daily_cap_per_mailbox_defers_extra_sends(): void
    {
        config()->set('communication.follow_ups.daily_cap_per_user', 1);

        $integration = $this->makeIntegration($this->employee);
        $this->makeRule($integration, [['delay_days' => 3]]);
        $threadA = $this->makeThread($integration, 'Fil A');
        $this->makeOutbound($integration, $threadA, 'a@example.com', (string) now()->subDays(5));
        $threadB = $this->makeThread($integration, 'Fil B');
        $this->makeOutbound($integration, $threadB, 'b@example.com', (string) now()->subDays(5));

        $this->fakeSendOk();

        $this->runCommand();

        // Plafond boite = 1 : un seul envoi, l'autre echeance reste pending.
        Http::assertSentCount(1);
        $statuses = CommunicationFollowUp::query()->withoutGlobalScopes()->pluck('status');
        $this->assertEqualsCanonicalizing(
            [CommunicationFollowUp::STATUS_SENT, CommunicationFollowUp::STATUS_PENDING],
            $statuses->all()
        );

        // Le lendemain, le compteur repart : la deuxieme echeance part.
        $this->travel(1)->days();
        $this->runCommand();
        Http::assertSentCount(2);
    }

    public function test_contact_daily_cap_defers_second_follow_up_to_same_recipient(): void
    {
        config()->set('communication.follow_ups.contact_daily_cap', 1);

        $integration = $this->makeIntegration($this->employee);
        $this->makeRule($integration, [['delay_days' => 3]]);
        // Deux fils differents vers LE MEME destinataire.
        $threadA = $this->makeThread($integration, 'Fil A');
        $this->makeOutbound($integration, $threadA, 'same@example.com', (string) now()->subDays(5));
        $threadB = $this->makeThread($integration, 'Fil B');
        $this->makeOutbound($integration, $threadB, 'same@example.com', (string) now()->subDays(5));

        $this->fakeSendOk();

        $this->runCommand();

        // Plafond destinataire = 1/jour : un seul envoi aujourd'hui.
        Http::assertSentCount(1);
        $this->assertSame(
            1,
            CommunicationFollowUp::query()
                ->withoutGlobalScopes()
                ->where('status', CommunicationFollowUp::STATUS_PENDING)
                ->count()
        );
    }

    public function test_missing_send_scope_skips_with_machine_code(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $rule = $this->makeRule($integration, [['delay_days' => 3]]);
        $thread = $this->makeThread($integration);
        $this->makeOutbound($integration, $thread, 'prospect@example.com', (string) now()->subDays(5));

        // Le scope d'envoi a disparu (reconnexion sans with_send) APRES la
        // creation de la regle : fail-closed a l'envoi.
        $integration->forceFill(['scopes' => GoogleGmailOAuthService::DEFAULT_SCOPES])->save();

        Http::fake();

        $this->runCommand();

        Http::assertNothingSent();
        /** @var CommunicationFollowUp $followUp */
        $followUp = CommunicationFollowUp::query()->withoutGlobalScopes()->sole();
        $this->assertSame(CommunicationFollowUp::STATUS_SKIPPED, $followUp->status);
        $this->assertSame('missing_send_scope', $followUp->skip_reason);
        $this->assertNotNull($rule->refresh());
    }

    public function test_gmail_401_marks_follow_up_failed_and_integration_error(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->makeRule($integration, [['delay_days' => 3]]);
        $thread = $this->makeThread($integration);
        $this->makeOutbound($integration, $thread, 'prospect@example.com', (string) now()->subDays(5));

        Http::fake([self::SEND.'*' => Http::response(['error' => ['code' => 401]], 401)]);

        $this->runCommand();

        /** @var CommunicationFollowUp $followUp */
        $followUp = CommunicationFollowUp::query()->withoutGlobalScopes()->sole();
        $this->assertSame(CommunicationFollowUp::STATUS_FAILED, $followUp->status);
        $this->assertSame(CommunicationIntegration::STATUS_ERROR, $integration->refresh()->status);
        $this->assertSame(
            1,
            CommunicationFollowUpLog::query()
                ->withoutGlobalScopes()
                ->where('action', CommunicationFollowUpLog::ACTION_FAILED)
                ->count()
        );
    }

    public function test_command_ignores_tenant_without_module(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->makeRule($integration, [['delay_days' => 3]]);
        $thread = $this->makeThread($integration);
        $this->makeOutbound($integration, $thread, 'prospect@example.com', (string) now()->subDays(5));

        // Kill switch : module coupe -> la passe ne touche pas ce tenant.
        $this->company->setFeature(CommunicationFeatures::COMMUNICATION, false);
        $this->company->save();

        Http::fake();

        $this->runCommand();

        Http::assertNothingSent();
        $this->assertSame(0, CommunicationFollowUp::query()->withoutGlobalScopes()->count());
    }

    // ── File d'attente : consultation + annulation ───────────────────────

    public function test_follow_up_queue_is_owner_scoped_and_cancellable(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->makeRule($integration, [['delay_days' => 3]]);
        $thread = $this->makeThread($integration);
        $this->makeOutbound($integration, $thread, 'prospect@example.com', (string) now()->subDays(5));

        // Materialise sans envoyer (fenetre calme totale).
        config()->set('communication.follow_ups.quiet_hours', ['start' => 0, 'end' => 24, 'timezone' => 'UTC']);
        Http::fake();
        $this->runCommand();

        /** @var CommunicationFollowUp $followUp */
        $followUp = CommunicationFollowUp::query()->withoutGlobalScopes()->sole();

        // Index proprietaire (filtre ?status=).
        Sanctum::actingAs($this->employee);
        $this->getJson('/api/v1/communication/follow-ups?status=pending')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $followUp->id)
            ->assertJsonPath('data.0.contact_email', 'prospect@example.com');

        // Collegue : liste vide ; annulation 403.
        $colleague = $this->makeEmployee($this->company);
        Sanctum::actingAs($colleague);
        $this->getJson('/api/v1/communication/follow-ups')->assertStatus(200)->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/communication/follow-ups/'.$followUp->id.'/cancel')->assertStatus(403);

        // Cross-tenant : 404.
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $other->setFeature(CommunicationFeatures::COMMUNICATION, true);
        $other->save();
        Sanctum::actingAs($this->makeEmployee($other));
        $this->postJson('/api/v1/communication/follow-ups/'.$followUp->id.'/cancel')->assertStatus(404);

        // Proprietaire : annulation OK + audit ; rejeu = 409.
        Sanctum::actingAs($this->employee);
        $this->postJson('/api/v1/communication/follow-ups/'.$followUp->id.'/cancel')
            ->assertStatus(200)
            ->assertJsonPath('data.status', CommunicationFollowUp::STATUS_CANCELLED);
        $this->postJson('/api/v1/communication/follow-ups/'.$followUp->id.'/cancel')
            ->assertStatus(409)
            ->assertJsonPath('code', 'FOLLOW_UP_NOT_CANCELLABLE');

        $this->assertSame(
            1,
            CommunicationFollowUpLog::query()
                ->withoutGlobalScopes()
                ->where('action', CommunicationFollowUpLog::ACTION_CANCELLED)
                ->where('reason', 'cancelled_by_owner')
                ->count()
        );

        // Une echeance annulee ne repart JAMAIS (deduplication).
        config()->set('communication.follow_ups.quiet_hours', ['start' => 0, 'end' => 0, 'timezone' => 'UTC']);
        $this->runCommand();
        Http::assertNothingSent();
    }

    // -- Sync R2 : drapeaux auto-repondeur / liste ------------------------

    public function test_sync_flags_auto_submitted_and_list_id_as_booleans_only(): void
    {
        // La classification R3 ne doit pas s'executer inline pendant la sync.
        Queue::fake();

        $integration = $this->makeIntegration($this->employee);

        Http::fake([
            GoogleGmailSyncService::GMAIL_API_BASE.'/profile*' => Http::response(['emailAddress' => 'user@gmail.com', 'historyId' => '1000']),
            GoogleGmailSyncService::GMAIL_API_BASE.'/threads/thread-1*' => Http::response([
                'id' => 'thread-1',
                'messages' => [
                    [
                        'id' => 'msg-auto',
                        'threadId' => 'thread-1',
                        'labelIds' => ['INBOX'],
                        'snippet' => 'Je suis absent du bureau.',
                        'internalDate' => '1758200000000',
                        'payload' => [
                            'mimeType' => 'text/plain',
                            'headers' => [
                                ['name' => 'From', 'value' => 'prospect@example.com'],
                                ['name' => 'To', 'value' => 'mailbox-'.$this->employee->id.'@gmail.com'],
                                ['name' => 'Subject', 'value' => 'Absence du bureau'],
                                ['name' => 'Auto-Submitted', 'value' => 'auto-replied'],
                                ['name' => 'List-Id', 'value' => '<newsletter.example.com>'],
                            ],
                            'body' => ['data' => rtrim(strtr(base64_encode('Je suis absent.'), '+/', '-_'), '=')],
                        ],
                    ],
                ],
            ]),
            GoogleGmailSyncService::GMAIL_API_BASE.'/threads*' => Http::response([
                'threads' => [['id' => 'thread-1']],
            ]),
        ]);

        app(GoogleGmailSyncService::class)->sync($integration);

        /** @var CommunicationMessage $message */
        $message = CommunicationMessage::query()
            ->withoutGlobalScopes()
            ->where('gmail_message_id', 'msg-auto')
            ->firstOrFail();

        // Seuls des BOOLEENS sont persistes -- jamais les headers eux-memes
        // (minimisation R2 conservee).
        $this->assertTrue($message->is_auto_reply);
        $this->assertTrue($message->is_list_message);
    }

    // ── Opt-outs ─────────────────────────────────────────────────────────

    public function test_opt_out_endpoints_rbac_and_idempotence(): void
    {
        Sanctum::actingAs($this->employee);

        // Tout employe peut AJOUTER une exclusion (garde-fou protecteur).
        $created = $this->postJson('/api/v1/communication/follow-up-opt-outs', [
            'email' => 'Client@Example.com',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.email', 'client@example.com');

        // Idempotent : rejeu = 200 sur la meme ligne.
        $this->postJson('/api/v1/communication/follow-up-opt-outs', ['email' => 'client@example.com'])
            ->assertStatus(200)
            ->assertJsonPath('data.id', (string) $created->json('data.id'));

        $optOutId = (string) $created->json('data.id');

        // Suppression (re-autorise les relances) : employe = 403.
        $this->deleteJson('/api/v1/communication/follow-up-opt-outs/'.$optOutId)->assertStatus(403);

        // Cross-tenant : 404.
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $other->setFeature(CommunicationFeatures::COMMUNICATION, true);
        $other->save();
        Sanctum::actingAs($this->makeEmployee($other, 'manager', 'principal'));
        $this->deleteJson('/api/v1/communication/follow-up-opt-outs/'.$optOutId)->assertStatus(404);

        // Principal du tenant : 204.
        Sanctum::actingAs($this->makeEmployee($this->company, 'manager', 'principal'));
        $this->deleteJson('/api/v1/communication/follow-up-opt-outs/'.$optOutId)->assertStatus(204);
    }
}
