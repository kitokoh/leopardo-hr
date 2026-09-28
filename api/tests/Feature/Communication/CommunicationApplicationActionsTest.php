<?php

declare(strict_types=1);

namespace Tests\Feature\Communication;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Communication\Application\Actions\ApprovePendingReplyAction;
use App\Modules\Communication\Application\Actions\CancelFollowUpAction;
use App\Modules\Communication\Application\Actions\CompleteGoogleConnectionAction;
use App\Modules\Communication\Application\Actions\DecideContactProposalAction;
use App\Modules\Communication\Application\Actions\RevokeIntegrationAction;
use App\Modules\Communication\Application\Actions\SaveFollowUpRuleAction;
use App\Modules\Communication\Application\Actions\StartGoogleConnectionAction;
use App\Modules\Communication\Application\Actions\UpsertReplyPolicyAction;
use App\Modules\Communication\Domain\Models\CommunicationContactProposal;
use App\Modules\Communication\Domain\Models\CommunicationFollowUp;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpLog;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpRule;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Modules\Communication\Domain\Models\CommunicationPendingReply;
use App\Modules\Communication\Domain\Models\CommunicationReplyLog;
use App\Modules\Communication\Domain\Models\CommunicationReplyPolicy;
use App\Modules\Communication\Domain\Models\CommunicationThread;
use App\Modules\Communication\Domain\Support\CommunicationFeatures;
use App\Modules\Communication\Infrastructure\Services\GoogleGmailOAuthService;
use App\Modules\Communication\Infrastructure\Services\GoogleGmailReplySender;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Épreuve directe des Actions de la couche Application du module
 * Communication (BOS-024e, #8216 — extraction des controllers, gabarit
 * BOS-024b #8235).
 *
 * Chaque Action est résolue via le conteneur et éprouvée sur SES
 * responsabilités : orchestration du cas d'usage, invariants métier
 * (409/404/422/429/502 + codes stables du contrat API), transactionnalité,
 * audit. Les Policies, la validation HTTP et la présentation restent au
 * niveau interface (couvertes par les suites de contrat existantes —
 * CommunicationReplyTest, CommunicationFollowUpTest,
 * CommunicationClassificationTest, CommunicationIntegrationTest — qui
 * doivent rester vertes sans modification).
 *
 * Tous les appels Google sont mockés via `Http::fake` +
 * `Http::preventStrayRequests()` — AUCUN appel réseau réel.
 */
class CommunicationApplicationActionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private const SEND = GoogleGmailReplySender::SEND_ENDPOINT;

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

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $company->setFeature(CommunicationFeatures::COMMUNICATION, true);
        $company->save();
        $this->company = $company;

        $this->employee = $this->makeEmployee($this->company);
    }

    // ── StartGoogleConnectionAction ─────────────────────────────────────

    public function test_start_action_fails_closed_503_when_google_not_configured(): void
    {
        config()->set('services.google.client_secret', null);

        $this->assertActionError(503, 'GOOGLE_OAUTH_UNAVAILABLE', function (): void {
            app(StartGoogleConnectionAction::class)->execute($this->employee, false);
        }, 'error');
    }

    public function test_start_action_persists_single_use_state_and_scoped_authorization_url(): void
    {
        $payload = app(StartGoogleConnectionAction::class)->execute($this->employee, true);

        $this->assertSame(600, $payload['expires_in']);
        $this->assertStringContainsString(
            GoogleGmailOAuthService::AUTHORIZATION_ENDPOINT,
            $payload['authorization_url']
        );

        $state = null;
        parse_str((string) parse_url($payload['authorization_url'], PHP_URL_QUERY), $query);
        $state = $query['state'] ?? null;
        $this->assertIsString($state);
        $this->assertSame(40, mb_strlen($state));

        // Le state porte l'identité du demandeur (re-vérifiée au callback).
        /** @var array{employee_id: int, company_id: string}|null $context */
        $context = Cache::get(StartGoogleConnectionAction::STATE_CACHE_PREFIX.$state);
        $this->assertSame((int) $this->employee->id, (int) ($context['employee_id'] ?? 0));
        $this->assertSame((string) $this->company->id, (string) ($context['company_id'] ?? ''));

        // with_send=true → scopes gmail.send + gmail.compose demandés.
        $this->assertStringContainsString(rawurlencode(GoogleGmailOAuthService::GMAIL_SEND_SCOPE), $payload['authorization_url']);
    }

    // ── CompleteGoogleConnectionAction ──────────────────────────────────

    public function test_complete_action_rejects_unknown_state_without_calling_google(): void
    {
        Http::fake();

        $this->assertActionError(400, 'OAUTH_STATE_INVALID', function (): void {
            app(CompleteGoogleConnectionAction::class)->execute('unknown-state', null, 'auth-code');
        }, 'error');

        Http::assertNothingSent();
    }

    public function test_complete_action_consumes_state_once_then_rejects_replay(): void
    {
        $state = $this->issueState();

        // Premier passage : le refus de consentement consomme le state.
        $this->assertActionError(400, 'OAUTH_CONSENT_DENIED', function () use ($state): void {
            app(CompleteGoogleConnectionAction::class)->execute($state, 'access_denied', null);
        }, 'error');

        // Rejeu du MÊME state : déjà consommé → OAUTH_STATE_INVALID.
        $this->assertActionError(400, 'OAUTH_STATE_INVALID', function () use ($state): void {
            app(CompleteGoogleConnectionAction::class)->execute($state, null, 'auth-code');
        }, 'error');
    }

    public function test_complete_action_is_fail_closed_when_module_disabled(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $state = $this->issueState($this->makeEmployee($other));

        $this->assertActionError(403, 'FEATURE_NOT_ENABLED', function () use ($state): void {
            app(CompleteGoogleConnectionAction::class)->execute($state, null, 'auth-code');
        }, 'error');
    }

    public function test_complete_action_returns_502_when_code_exchange_fails(): void
    {
        Http::fake([
            GoogleGmailOAuthService::TOKEN_ENDPOINT => Http::response(['error' => 'invalid_grant'], 500),
        ]);
        $state = $this->issueState();

        $this->assertActionError(502, 'OAUTH_EXCHANGE_FAILED', function () use ($state): void {
            app(CompleteGoogleConnectionAction::class)->execute($state, null, 'bad-code');
        }, 'error');
    }

    public function test_complete_action_stores_tokens_encrypted_and_returns_identity(): void
    {
        Http::fake([
            GoogleGmailOAuthService::TOKEN_ENDPOINT => Http::response([
                'access_token' => 'google-access-token',
                'refresh_token' => 'google-refresh-token',
                'expires_in' => 3600,
                'scope' => 'openid email',
                'token_type' => 'Bearer',
            ]),
            GoogleGmailOAuthService::USERINFO_ENDPOINT => Http::response([
                'email' => 'user@gmail.com',
                'email_verified' => true,
            ]),
        ]);
        $state = $this->issueState();

        $payload = app(CompleteGoogleConnectionAction::class)->execute($state, null, 'auth-code');

        $this->assertSame(CommunicationIntegration::STATUS_ACTIVE, $payload['status']);
        $this->assertSame(CommunicationIntegration::PROVIDER_GOOGLE, $payload['provider']);
        $this->assertSame('user@gmail.com', $payload['email']);

        /** @var CommunicationIntegration $integration */
        $integration = CommunicationIntegration::query()
            ->withoutGlobalScope('company')
            ->where('employee_id', $this->employee->id)
            ->firstOrFail();

        $this->assertSame((string) $this->company->id, (string) $integration->company_id);
        // Casts `encrypted` : déchiffré à la lecture, chiffré au repos.
        $this->assertSame('google-access-token', $integration->access_token);
        $this->assertSame('google-refresh-token', $integration->refresh_token);
    }

    // ── RevokeIntegrationAction ─────────────────────────────────────────

    public function test_revoke_action_revokes_tokens_and_marks_integration_revoked(): void
    {
        Http::fake([
            GoogleGmailOAuthService::REVOKE_ENDPOINT => Http::response([], 200),
        ]);
        $integration = $this->makeIntegration($this->employee);

        $integration = app(RevokeIntegrationAction::class)->execute($integration);

        $this->assertSame(CommunicationIntegration::STATUS_REVOKED, $integration->status);
        $this->assertNull($integration->access_token);
        $this->assertNull($integration->refresh_token);
        $this->assertNotNull($integration->revoked_at);
        Http::assertSentCount(1);
    }

    // ── ApprovePendingReplyAction ───────────────────────────────────────

    public function test_approve_action_sends_and_audits_the_decision(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->makePolicy($integration, 'prospect', CommunicationReplyPolicy::POLICY_CONFIRM);
        Http::fake([self::SEND => Http::response(['id' => 'sent-abc'], 200)]);
        $reply = $this->makePendingReply($integration);

        $reply = app(ApprovePendingReplyAction::class)->execute($reply, $this->employee);

        $this->assertSame(CommunicationPendingReply::STATUS_SENT, $reply->status);
        $this->assertSame('sent-abc', $reply->sent_gmail_message_id);
        $this->assertSame((int) $this->employee->id, $reply->decided_by);
        $this->assertNotNull($reply->sent_at);

        foreach ([CommunicationReplyLog::ACTION_APPROVED, CommunicationReplyLog::ACTION_SENT] as $action) {
            $this->assertDatabaseHas('communication_reply_logs', [
                'pending_reply_id' => $reply->id,
                'action' => $action,
                'actor_id' => $this->employee->id,
            ]);
        }
    }

    public function test_approve_action_refuses_non_pending_reply_409(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $reply = $this->makePendingReply($integration, [
            'status' => CommunicationPendingReply::STATUS_SENT,
        ]);

        $this->assertActionError(409, 'PENDING_REPLY_NOT_PENDING', function () use ($reply): void {
            app(ApprovePendingReplyAction::class)->execute($reply, $this->employee);
        });
    }

    public function test_approve_action_blocks_422_when_send_scope_missing(): void
    {
        $integration = $this->makeIntegration($this->employee, [
            'scopes' => GoogleGmailOAuthService::DEFAULT_SCOPES,
        ]);
        $this->makePolicy($integration, 'prospect', CommunicationReplyPolicy::POLICY_CONFIRM);
        $reply = $this->makePendingReply($integration);

        $this->assertActionError(422, 'GMAIL_SEND_SCOPE_REQUIRED', function () use ($reply): void {
            app(ApprovePendingReplyAction::class)->execute($reply, $this->employee);
        });

        // La proposition reste pending : aucun effet d'un envoi refusé.
        $this->assertTrue($reply->refresh()->isPending());
    }

    public function test_approve_action_returns_429_on_gmail_rate_limit_and_stays_pending(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->makePolicy($integration, 'prospect', CommunicationReplyPolicy::POLICY_CONFIRM);
        Http::fake([
            self::SEND => Http::response(['error' => 'quota exceeded'], 429, ['Retry-After' => '30']),
        ]);
        $reply = $this->makePendingReply($integration);

        $this->assertActionError(429, 'GMAIL_RATE_LIMITED', function () use ($reply): void {
            app(ApprovePendingReplyAction::class)->execute($reply, $this->employee);
        });

        $this->assertTrue($reply->refresh()->isPending());
    }

    public function test_approve_action_marks_failed_and_returns_502_when_send_fails(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $this->makePolicy($integration, 'prospect', CommunicationReplyPolicy::POLICY_CONFIRM);
        Http::fake([self::SEND => Http::response(['error' => 'backend'], 500)]);
        $reply = $this->makePendingReply($integration);

        $this->assertActionError(502, 'REPLY_SEND_FAILED', function () use ($reply): void {
            app(ApprovePendingReplyAction::class)->execute($reply, $this->employee);
        });

        $reply->refresh();
        $this->assertSame(CommunicationPendingReply::STATUS_FAILED, $reply->status);
        $this->assertSame('gmail_send_failed', $reply->skip_reason);
        $this->assertDatabaseHas('communication_reply_logs', [
            'pending_reply_id' => $reply->id,
            'action' => CommunicationReplyLog::ACTION_FAILED,
        ]);
    }

    // ── DecideContactProposalAction ─────────────────────────────────────

    public function test_decide_action_accept_creates_contact_and_links_messages(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $proposal = $this->makeProposal($integration);
        $this->makeMessage($integration, ['from_email' => 'ALICE@example.com', 'sent_at' => now()->subHours(2)]);
        $latest = $this->makeMessage($integration, ['from_email' => 'alice@example.com', 'sent_at' => now()->subHour()]);

        $proposal = app(DecideContactProposalAction::class)->execute(
            $proposal,
            $this->employee,
            DecideContactProposalAction::DECISION_ACCEPT,
            'Alice Martin',
        );

        $this->assertSame(CommunicationContactProposal::STATUS_ACCEPTED, $proposal->status);
        $this->assertNotNull($proposal->crm_contact_id);
        $this->assertSame((int) $this->employee->id, $proposal->decided_by);

        // Contact CRM créé via le contrat partagé (BC-11).
        $this->assertDatabaseHas('crm_contacts', [
            'id' => $proposal->crm_contact_id,
            'email' => 'alice@example.com',
        ]);

        // Liaison rétroactive — casse de l'expéditeur ignorée.
        foreach (CommunicationMessage::query()->where('integration_id', $integration->id)->get() as $message) {
            $this->assertSame($proposal->crm_contact_id, $message->crm_contact_id);
            $this->assertSame(CommunicationMessage::CONTACT_LINK_LINKED, $message->contact_link_status);
        }
        $this->assertNotNull($latest->refresh()->crm_contact_id);
    }

    public function test_decide_action_dismiss_then_409_on_second_decision(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $proposal = $this->makeProposal($integration);

        $proposal = app(DecideContactProposalAction::class)->execute(
            $proposal,
            $this->employee,
            DecideContactProposalAction::DECISION_DISMISS,
        );

        $this->assertSame(CommunicationContactProposal::STATUS_DISMISSED, $proposal->status);

        $this->assertActionError(409, 'PROPOSAL_ALREADY_DECIDED', function () use ($proposal): void {
            app(DecideContactProposalAction::class)->execute(
                $proposal,
                $this->employee,
                DecideContactProposalAction::DECISION_ACCEPT,
            );
        });
    }

    // ── SaveFollowUpRuleAction ──────────────────────────────────────────

    public function test_save_action_creates_rule_with_ordered_steps(): void
    {
        $integration = $this->makeIntegration($this->employee);

        $rule = app(SaveFollowUpRuleAction::class)->execute($this->employee, [
            'integration_id' => $integration->id,
            'name' => 'Relance devis',
            'steps' => [
                ['delay_days' => 3],
                ['delay_days' => 7],
                ['delay_days' => 14, 'template_key' => 'communication_follow_up'],
            ],
        ]);

        $this->assertSame((string) $this->company->id, (string) $rule->company_id);
        $this->assertTrue($rule->active);
        $this->assertCount(3, $rule->steps);
        $this->assertSame([1, 2, 3], $rule->steps->pluck('position')->all());
        $this->assertSame([3, 7, 14], $rule->steps->pluck('delay_days')->all());
        // template_key par défaut appliqué quand absent.
        $this->assertSame('communication_follow_up', $rule->steps->first()?->template_key);
    }

    public function test_save_action_hides_foreign_mailbox_behind_404(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $foreign = $this->makeIntegration($this->makeEmployee($other));

        $this->assertActionError(404, 'MAILBOX_NOT_FOUND', function () use ($foreign): void {
            app(SaveFollowUpRuleAction::class)->execute($this->employee, [
                'integration_id' => $foreign->id,
                'name' => 'Règle interdite',
                'steps' => [['delay_days' => 3]],
            ]);
        });
    }

    public function test_save_action_requires_send_scope_only_when_activating(): void
    {
        $integration = $this->makeIntegration($this->employee, [
            'scopes' => GoogleGmailOAuthService::DEFAULT_SCOPES,
        ]);

        $this->assertActionError(422, 'GMAIL_SEND_SCOPE_REQUIRED', function () use ($integration): void {
            app(SaveFollowUpRuleAction::class)->execute($this->employee, [
                'integration_id' => $integration->id,
                'name' => 'Relance active',
                'active' => true,
                'steps' => [['delay_days' => 3]],
            ]);
        });

        // La même règle INACTIVE est acceptée (scope demandé à l'activation).
        $rule = app(SaveFollowUpRuleAction::class)->execute($this->employee, [
            'integration_id' => $integration->id,
            'name' => 'Relance inactive',
            'active' => false,
            'steps' => [['delay_days' => 3]],
        ]);
        $this->assertFalse($rule->active);
    }

    public function test_save_action_update_rewrites_steps_transactionally(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $rule = app(SaveFollowUpRuleAction::class)->execute($this->employee, [
            'integration_id' => $integration->id,
            'name' => 'V1',
            'steps' => [['delay_days' => 3], ['delay_days' => 7]],
        ]);
        $oldStepIds = $rule->steps->pluck('id')->all();

        $rule = app(SaveFollowUpRuleAction::class)->execute($this->employee, [
            'active' => false,
            'steps' => [['delay_days' => 1], ['delay_days' => 2], ['delay_days' => 4]],
        ], $rule->refresh());

        $this->assertFalse($rule->active);
        $this->assertSame('V1', $rule->name); // name absent → inchangé
        $this->assertCount(3, $rule->steps);
        $this->assertSame([1, 2, 4], $rule->steps->pluck('delay_days')->all());
        foreach ($oldStepIds as $oldId) {
            $this->assertDatabaseMissing('communication_follow_up_steps', ['id' => $oldId]);
        }
    }

    // ── UpsertReplyPolicyAction ─────────────────────────────────────────

    public function test_upsert_action_creates_then_updates_the_same_row(): void
    {
        $integration = $this->makeIntegration($this->employee);

        ['policy' => $policy, 'created' => $created] = app(UpsertReplyPolicyAction::class)->execute($this->employee, [
            'integration_id' => $integration->id,
            'category_key' => 'client',
            'policy' => CommunicationReplyPolicy::POLICY_CONFIRM,
        ]);
        $this->assertTrue($created);
        $this->assertSame(CommunicationReplyPolicy::POLICY_CONFIRM, $policy->policy);

        ['policy' => $again, 'created' => $createdAgain] = app(UpsertReplyPolicyAction::class)->execute($this->employee, [
            'integration_id' => $integration->id,
            'category_key' => 'client',
            'policy' => CommunicationReplyPolicy::POLICY_OFF,
        ]);
        $this->assertFalse($createdAgain);
        $this->assertSame($policy->id, $again->id);
        $this->assertSame(CommunicationReplyPolicy::POLICY_OFF, $again->policy);
        $this->assertSame(1, CommunicationReplyPolicy::query()->count());
    }

    public function test_upsert_action_rejects_unknown_category_422(): void
    {
        $integration = $this->makeIntegration($this->employee);

        $this->assertActionError(422, 'REPLY_CATEGORY_UNKNOWN', function () use ($integration): void {
            app(UpsertReplyPolicyAction::class)->execute($this->employee, [
                'integration_id' => $integration->id,
                'category_key' => 'not-a-category',
                'policy' => CommunicationReplyPolicy::POLICY_CONFIRM,
            ]);
        });
    }

    public function test_upsert_action_never_allows_auto_on_blocked_category(): void
    {
        $integration = $this->makeIntegration($this->employee);

        // `invoice` est une catégorie système active (taxonomie matérialisée)
        // ET dans la liste bloquée EN DUR — jamais contournable.
        $this->assertActionError(422, 'REPLY_AUTO_CATEGORY_BLOCKED', function () use ($integration): void {
            app(UpsertReplyPolicyAction::class)->execute($this->employee, [
                'integration_id' => $integration->id,
                'category_key' => 'invoice',
                'policy' => CommunicationReplyPolicy::POLICY_AUTO,
            ]);
        });
    }

    public function test_upsert_action_requires_compose_scope_for_draft(): void
    {
        $integration = $this->makeIntegration($this->employee, [
            'scopes' => GoogleGmailOAuthService::DEFAULT_SCOPES,
        ]);

        $this->assertActionError(422, 'GMAIL_COMPOSE_SCOPE_REQUIRED', function () use ($integration): void {
            app(UpsertReplyPolicyAction::class)->execute($this->employee, [
                'integration_id' => $integration->id,
                'category_key' => 'client',
                'policy' => CommunicationPendingReply::MODE_DRAFT,
            ]);
        });
    }

    // ── CancelFollowUpAction ────────────────────────────────────────────

    public function test_cancel_action_cancels_pending_follow_up_with_audit(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $rule = $this->makeRule($integration);
        $followUp = $this->makeFollowUp($rule);

        $followUp = app(CancelFollowUpAction::class)->execute($followUp);

        $this->assertSame(CommunicationFollowUp::STATUS_CANCELLED, $followUp->status);
        $this->assertSame('cancelled_by_owner', $followUp->skip_reason);
        $this->assertDatabaseHas('communication_follow_up_logs', [
            'follow_up_id' => $followUp->id,
            'action' => CommunicationFollowUpLog::ACTION_CANCELLED,
        ]);
    }

    public function test_cancel_action_refuses_non_pending_409(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $rule = $this->makeRule($integration);
        $followUp = $this->makeFollowUp($rule, [
            'status' => CommunicationFollowUp::STATUS_SENT,
        ]);

        $this->assertActionError(409, 'FOLLOW_UP_NOT_CANCELLABLE', function () use ($followUp): void {
            app(CancelFollowUpAction::class)->execute($followUp);
        });
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * Asserte qu'un appel d'Action lève `HttpResponseException` avec le
     * statut et le code machine EXACTS du contrat API du module (la clé
     * portant le code est `code` pour les erreurs métier, `error` pour le
     * flux OAuth public).
     */
    private function assertActionError(int $status, string $code, callable $fn, string $key = 'code'): void
    {
        try {
            $fn();
            $this->fail(sprintf('HttpResponseException attendue (%d %s), aucune levée.', $status, $code));
        } catch (HttpResponseException $exception) {
            $response = $exception->getResponse();
            $this->assertSame($status, $response->getStatusCode());
            /** @var array<string, mixed> $payload */
            $payload = (array) json_decode((string) $response->getContent(), true);
            $this->assertSame($code, $payload[$key] ?? null);
        }
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
            'scopes' => array_merge(
                GoogleGmailOAuthService::DEFAULT_SCOPES,
                [GoogleGmailOAuthService::GMAIL_SEND_SCOPE, GoogleGmailOAuthService::GMAIL_COMPOSE_SCOPE],
            ),
            'access_token' => 'plain-access-token-'.$employee->id,
            'refresh_token' => 'plain-refresh-token-'.$employee->id,
            'expires_at' => now()->addYear(),
            'status' => CommunicationIntegration::STATUS_ACTIVE,
            'connected_at' => now(),
        ], $attributes));

        $integration->save();

        return $integration;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePendingReply(CommunicationIntegration $integration, array $attributes = []): CommunicationPendingReply
    {
        $thread = $this->makeThread($integration);
        $message = $this->makeMessage($integration, ['thread_id' => $thread->id]);

        $reply = new CommunicationPendingReply;
        $reply->forceFill(array_merge([
            'company_id' => (string) $integration->company_id,
            'integration_id' => $integration->id,
            'thread_id' => $thread->id,
            'message_id' => $message->id,
            'category_key' => 'prospect',
            'mode' => CommunicationPendingReply::MODE_CONFIRM,
            'to_email' => 'alice@example.com',
            'subject' => 'Re: Demande de devis',
            'body' => 'Bonjour, merci pour votre message. Voici notre devis.',
            'ai_language' => 'fr',
            'ai_confidence' => 90,
            'status' => CommunicationPendingReply::STATUS_PENDING,
        ], $attributes));
        $reply->save();

        return $reply;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeProposal(CommunicationIntegration $integration, array $attributes = []): CommunicationContactProposal
    {
        $proposal = new CommunicationContactProposal;
        $proposal->forceFill(array_merge([
            'company_id' => (string) $integration->company_id,
            'integration_id' => $integration->id,
            'email' => 'alice@example.com',
            'suggested_name' => 'Alice Martin',
            'status' => CommunicationContactProposal::STATUS_PROPOSED,
            'message_count' => 3,
        ], $attributes));
        $proposal->save();

        return $proposal;
    }

    private function makeThread(CommunicationIntegration $integration): CommunicationThread
    {
        $thread = new CommunicationThread;
        $thread->forceFill([
            'company_id' => (string) $integration->company_id,
            'integration_id' => $integration->id,
            'gmail_thread_id' => 'thread-'.fake()->unique()->numerify('######'),
            'subject' => 'Demande de devis',
            'snippet' => 'Bonjour, pouvez-vous m envoyer un devis ?',
            'message_count' => 1,
            'last_message_at' => now(),
        ]);
        $thread->save();

        return $thread;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeMessage(CommunicationIntegration $integration, array $attributes = []): CommunicationMessage
    {
        $message = new CommunicationMessage;
        $message->forceFill(array_merge([
            'company_id' => (string) $integration->company_id,
            'thread_id' => $this->makeThread($integration)->id,
            'integration_id' => $integration->id,
            'gmail_message_id' => 'msg-'.fake()->unique()->numerify('######'),
            'internet_message_id' => '<'.fake()->unique()->numerify('######').'@mail.example.com>',
            'from_email' => 'alice@example.com',
            'to_emails' => ['mailbox-'.$integration->employee_id.'@gmail.com'],
            'subject' => 'Demande de devis',
            'snippet' => 'Bonjour, pouvez-vous m envoyer un devis ?',
            'body' => 'Bonjour, pouvez-vous m envoyer un devis detaille ? Merci.',
            'sent_at' => now()->subHour(),
        ], $attributes));
        $message->save();

        return $message;
    }

    private function makeRule(CommunicationIntegration $integration): CommunicationFollowUpRule
    {
        $rule = new CommunicationFollowUpRule;
        $rule->forceFill([
            'company_id' => (string) $integration->company_id,
            'integration_id' => $integration->id,
            'name' => 'Relance standard',
            'active' => true,
        ]);
        $rule->save();

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeFollowUp(CommunicationFollowUpRule $rule, array $attributes = []): CommunicationFollowUp
    {
        /** @var CommunicationIntegration $integration */
        $integration = CommunicationIntegration::query()->findOrFail($rule->integration_id);
        $thread = $this->makeThread($integration);

        $followUp = new CommunicationFollowUp;
        $followUp->forceFill(array_merge([
            'company_id' => (string) $rule->company_id,
            'rule_id' => $rule->id,
            'integration_id' => $rule->integration_id,
            'thread_id' => $thread->id,
            'step_position' => 1,
            'contact_email' => 'alice@example.com',
            'scheduled_for' => now()->addDay(),
            'status' => CommunicationFollowUp::STATUS_PENDING,
        ], $attributes));
        $followUp->save();

        return $followUp;
    }

    private function makePolicy(
        CommunicationIntegration $integration,
        string $category,
        string $policy,
    ): CommunicationReplyPolicy {
        $row = new CommunicationReplyPolicy;
        $row->forceFill([
            'company_id' => (string) $integration->company_id,
            'integration_id' => $integration->id,
            'category_key' => $category,
            'policy' => $policy,
        ]);
        $row->save();

        return $row;
    }

    private function issueState(?Employee $employee = null): string
    {
        $employee ??= $this->employee;
        $state = str_repeat('ab', 20);

        Cache::put(
            StartGoogleConnectionAction::STATE_CACHE_PREFIX.$state,
            [
                'employee_id' => $employee->id,
                'company_id' => (string) $employee->company_id,
            ],
            now()->addMinutes(10)
        );

        return $state;
    }
}
