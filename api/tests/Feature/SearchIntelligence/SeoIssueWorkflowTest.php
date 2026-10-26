<?php

declare(strict_types=1);

namespace Tests\Feature\SearchIntelligence;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\SearchIntelligence\Application\Services\SeoIssueWorkflow;
use App\Modules\SearchIntelligence\Domain\Enums\SeoIssueSeverity;
use App\Modules\SearchIntelligence\Domain\Enums\SeoIssueStatus;
use App\Modules\SearchIntelligence\Domain\Exceptions\InvalidTransitionException;
use App\Modules\SearchIntelligence\Domain\Models\SeoIssue;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GSI-2 (#8513) — workflow de traitement des anomalies SEO.
 *
 * Le test couvre les trois exigences de la spec :
 *  - les transitions valides avancent, les invalides sont refusées ;
 *  - `dismissed` sans motif → 422 (et refus aussi au niveau du domaine) ;
 *  - chaque transition écrit une ligne `AuditLog` — c'est la preuve qu'aucune
 *    correction n'est appliquée sans approbation humaine.
 */
final class SeoIssueWorkflowTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_happy_path_reaches_verified(): void
    {
        $issue = $this->makeIssue();
        $workflow = $this->app->make(SeoIssueWorkflow::class);

        $workflow->transition($issue, SeoIssueStatus::Recommended, 1);
        $workflow->transition($issue, SeoIssueStatus::Approved, 1);
        $workflow->transition($issue, SeoIssueStatus::Applied, 1);
        $workflow->transition($issue, SeoIssueStatus::Verified, 1);

        $issue->refresh();
        self::assertSame(SeoIssueStatus::Verified, $issue->status);
        self::assertNotNull($issue->applied_at);
        self::assertNotNull($issue->verified_at);
    }

    public function test_detected_cannot_jump_straight_to_applied(): void
    {
        $issue = $this->makeIssue();
        $workflow = $this->app->make(SeoIssueWorkflow::class);

        $this->expectException(InvalidTransitionException::class);

        // Sauter l'approbation humaine est exactement ce que la spec interdit.
        $workflow->transition($issue, SeoIssueStatus::Applied, 1);
    }

    public function test_dismiss_requires_a_reason_in_the_domain(): void
    {
        $issue = $this->makeIssue();
        $workflow = $this->app->make(SeoIssueWorkflow::class);

        $this->expectException(InvalidTransitionException::class);

        $workflow->transition($issue, SeoIssueStatus::Dismissed, 1);
    }

    public function test_dismiss_with_reason_is_persisted(): void
    {
        $issue = $this->makeIssue();
        $workflow = $this->app->make(SeoIssueWorkflow::class);

        $workflow->transition($issue, SeoIssueStatus::Dismissed, 1, 'Page décommissionnée.');

        $issue->refresh();
        self::assertSame(SeoIssueStatus::Dismissed, $issue->status);
        self::assertSame('Page décommissionnée.', $issue->dismissed_reason);
    }

    public function test_every_transition_writes_an_audit_log(): void
    {
        $issue = $this->makeIssue();
        $workflow = $this->app->make(SeoIssueWorkflow::class);

        $workflow->transition($issue, SeoIssueStatus::Recommended, 7);
        $workflow->transition($issue, SeoIssueStatus::Approved, 7);

        $logs = AuditLog::query()
            ->where('auditable_type', SeoIssue::class)
            ->where('auditable_id', $issue->id)
            ->orderBy('id')
            ->get();

        self::assertCount(2, $logs);
        self::assertSame('search_intel.seo_issue.recommended', $logs[0]->action);
        self::assertSame('search_intel.seo_issue.approved', $logs[1]->action);
        self::assertSame(['status' => 'detected'], $logs[0]->old_values);
        self::assertSame(7, $logs[0]->metadata['actor_super_admin_id']);
    }

    public function test_api_transition_returns_422_with_allowed_transitions(): void
    {
        $issue = $this->makeIssue();
        $marketing = $this->superAdminWithRole(PlatformRole::Marketing);

        Sanctum::actingAs($marketing, ['*'], 'super_admin_api');

        // `detected → applied` : refusé, et la réponse dit quoi faire.
        $this->patchJson("/api/v1/platform/search-intel/seo/issues/{$issue->id}/transition", [
            'status' => 'applied',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_transition')
            ->assertJsonPath('error.current_status', 'detected')
            ->assertJsonPath('error.allowed_transitions', ['recommended', 'dismissed']);
    }

    public function test_api_dismiss_without_reason_returns_422(): void
    {
        $issue = $this->makeIssue();
        $marketing = $this->superAdminWithRole(PlatformRole::Marketing);

        Sanctum::actingAs($marketing, ['*'], 'super_admin_api');

        $this->patchJson("/api/v1/platform/search-intel/seo/issues/{$issue->id}/transition", [
            'status' => 'dismissed',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_transition');
    }

    public function test_api_transition_succeeds_and_is_audited(): void
    {
        $issue = $this->makeIssue();
        $marketing = $this->superAdminWithRole(PlatformRole::Marketing);

        Sanctum::actingAs($marketing, ['*'], 'super_admin_api');

        $this->patchJson("/api/v1/platform/search-intel/seo/issues/{$issue->id}/transition", [
            'status' => 'recommended',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'recommended');

        self::assertSame(
            1,
            AuditLog::query()->where('auditable_type', SeoIssue::class)->where('auditable_id', $issue->id)->count(),
        );
    }

    private function makeIssue(): SeoIssue
    {
        return SeoIssue::query()->create([
            'type' => 'title_missing',
            'severity' => SeoIssueSeverity::High->value,
            'status' => SeoIssueStatus::Detected->value,
            'title' => 'Titre manquant',
            'recommendation' => 'Ajouter une balise <title>.',
            'detected_at' => now(),
        ]);
    }

    private function superAdminWithRole(PlatformRole $role): SuperAdmin
    {
        $admin = new SuperAdmin([
            'name' => 'Test '.$role->value,
            'email' => $role->value.'-'.uniqid().'@leopardo.test',
        ]);

        $admin->forceFill([
            'password_hash' => Hash::make('irrelevant-for-this-test'),
            'platform_role' => $role->value,
        ])->save();

        return $admin->refresh();
    }
}
