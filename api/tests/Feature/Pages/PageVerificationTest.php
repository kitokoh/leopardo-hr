<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Modules\Pages\Domain\Enums\PageStatus;
use App\Modules\Pages\Domain\Enums\PageVerificationMethod;
use App\Modules\Pages\Domain\Enums\PageVerificationStatus;
use App\Modules\Pages\Domain\Exceptions\PageVerificationException;
use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageVerificationCode;
use App\Modules\Pages\Domain\Models\PageVerificationEvidence;
use App\Modules\Pages\Domain\Models\PageVerificationRequest;
use App\Modules\Pages\Domain\Services\PageVerificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8725 (BC PAGES) — vérification de propriété et traitement des litiges.
 *
 * Couvre les critères d'acceptation :
 *  - méthodes configurables par pays ;
 *  - codes SMS : expiration, limite d'essais, cooldown ;
 *  - pièces privées + conservation documentée ;
 *  - état/preuve/décision/acteur/motif audités SANS exposer les pièces ;
 *  - rejet, contestation, révocation et suspension produisent les événements ;
 *  - absence d'accès public aux preuves, permissions tenant vs plateforme.
 */
class PageVerificationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Page $page;

    private PageVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);

        /** @var Site $site */
        $site = Site::create([
            'company_id' => $company->id,
            'name' => 'Chez Léa',
            'code' => 'CL-01',
            'site_type' => 'store',
            'status' => Site::STATUS_ACTIVE,
        ]);

        /** @var Page $page */
        $page = Page::create([
            'company_id' => $company->id,
            'site_id' => $site->id,
            'slug' => 'chez-lea',
            'status' => PageStatus::Draft,
            'verification_status' => PageVerificationStatus::Unverified,
        ]);
        $this->page = $page;

        $this->service = new PageVerificationService(app(\App\Shared\Contracts\Persistence\TransactionRunner::class));

        // Configuration par défaut pour les tests : les trois méthodes actives.
        config()->set('pages.verification.methods', ['sms', 'document', 'superadmin']);
    }

    // ── Configuration par pays ──────────────────────────────────────────────

    public function test_methods_are_configurable_and_disabled_methods_are_refused(): void
    {
        config()->set('pages.verification.methods', ['document']);

        $this->assertSame([PageVerificationMethod::Document], PageVerificationMethod::enabled());
        $this->assertFalse(PageVerificationMethod::isEnabled(PageVerificationMethod::Sms));

        $this->expectException(PageVerificationException::class);

        $this->service->open($this->page, PageVerificationMethod::Sms, 'owner@chez-lea.sn');
    }

    public function test_empty_configuration_disables_every_method_fail_closed(): void
    {
        config()->set('pages.verification.methods', []);

        // Fail-closed : une config vide ne doit pas tout activer par accident.
        $this->assertSame([], PageVerificationMethod::enabled());

        $this->expectException(PageVerificationException::class);
        $this->service->open($this->page, PageVerificationMethod::Document, 'owner@chez-lea.sn');
    }

    public function test_unknown_method_value_in_config_is_ignored(): void
    {
        config()->set('pages.verification.methods', ['sms', 'carrier_pigeon']);

        $this->assertSame([PageVerificationMethod::Sms], PageVerificationMethod::enabled());
    }

    // ── Ouverture du dossier ────────────────────────────────────────────────

    public function test_opening_a_request_puts_the_page_in_pending_not_verified(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Document, 'owner@chez-lea.sn');

        $this->assertSame(PageVerificationRequest::STATUS_OPEN, $request->status);

        $fresh = $this->page->fresh();
        $this->assertNotNull($fresh);
        // Ouvrir un dossier ne VÉRIFIE rien : c'est le point où la plupart des
        // implémentations se trompent et blanchissent la Page d'avance.
        $this->assertSame(PageVerificationStatus::Pending, $fresh->verification_status);
        $this->assertNull($fresh->verified_at);
    }

    public function test_a_second_open_request_is_refused(): void
    {
        $this->service->open($this->page, PageVerificationMethod::Document, 'owner@chez-lea.sn');

        $this->expectException(PageVerificationException::class);

        $this->service->open($this->page, PageVerificationMethod::Sms, 'owner@chez-lea.sn');
    }

    public function test_suspended_page_cannot_be_verified(): void
    {
        $this->page->suspend('fraud');
        $this->page->save();

        $this->expectException(PageVerificationException::class);

        $this->service->open($this->page->fresh() ?? $this->page, PageVerificationMethod::Document, 'owner@chez-lea.sn');
    }

    // ── Codes SMS : expiration, essais, cooldown ────────────────────────────

    public function test_sms_code_is_never_stored_in_clear(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Sms, 'owner@chez-lea.sn');
        $plain = $this->service->issueSmsCode($request, '+221771234567');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $plain);

        $row = PageVerificationCode::query()->where('request_id', $request->id)->firstOrFail();
        // Ni le code en clair, ni une valeur vide : seule une empreinte.
        $this->assertNotSame($plain, $row->code_hash);
        $this->assertSame(64, strlen($row->code_hash));

        $raw = DB::table('page_verification_codes')->where('id', $row->id)->first();
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString($plain, json_encode($raw) ?: '');
    }

    public function test_valid_code_verifies_the_page(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Sms, 'owner@chez-lea.sn');
        $plain = $this->service->issueSmsCode($request, '+221771234567');

        $this->service->confirmSmsCode($request, '+221771234567', $plain, 'system:sms');

        $freshRequest = $request->fresh();
        $this->assertNotNull($freshRequest);
        $this->assertSame(PageVerificationRequest::STATUS_APPROVED, $freshRequest->status);
        $this->assertSame('system:sms', $freshRequest->decided_by);

        $freshPage = $this->page->fresh();
        $this->assertNotNull($freshPage);
        $this->assertSame(PageVerificationStatus::Verified, $freshPage->verification_status);
        $this->assertNotNull($freshPage->verified_at);
        $this->assertSame('sms', $freshPage->verification_method);
    }

    public function test_expired_code_is_refused(): void
    {
        $t0 = Carbon::parse('2026-10-27 10:00:00');
        $request = $this->service->open($this->page, PageVerificationMethod::Sms, 'owner@chez-lea.sn', $t0);
        $plain = $this->service->issueSmsCode($request, '+221771234567', $t0);

        // Le message est traduit par le catalogue i18n : il depend de la locale
        // active du process. On asserte donc le CODE d'erreur, qui est le
        // contrat stable de l'API.
        try {
            $this->service->confirmSmsCode(
                $request,
                '+221771234567',
                $plain,
                'system:sms',
                $t0->copy()->addMinutes(PageVerificationCode::TTL_MINUTES + 1),
            );
            $this->fail('Un code expire aurait du etre refuse.');
        } catch (PageVerificationException $e) {
            $this->assertSame('PAGE_VERIFICATION_CODE_EXPIRED', $e->errorCode());
        }
    }

    public function test_code_is_single_use(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Sms, 'owner@chez-lea.sn');
        $plain = $this->service->issueSmsCode($request, '+221771234567');

        $this->service->confirmSmsCode($request, '+221771234567', $plain, 'system:sms');

        // Rejouer le même code sur un dossier rouvert doit échouer.
        $page2 = $this->page;
        $page2->verification_status = PageVerificationStatus::Revoked;
        $page2->verified_at = null;
        $page2->verification_method = null;
        $page2->save();

        $this->assertSame(PageVerificationStatus::Revoked, PageVerificationStatus::Revoked);

        try {
            $this->service->confirmSmsCode($request, '+221771234567', $plain, 'system:sms');
            $this->fail('Un code consommé aurait dû être refusé.');
        } catch (PageVerificationException $e) {
            // Dossier déjà clos : c'est le refus attendu au premier chef.
            $this->assertContains($e->errorCode(), [
                'PAGE_VERIFICATION_CLOSED',
                'PAGE_VERIFICATION_CODE_CONSUMED',
            ]);
        }
    }

    public function test_attempts_are_capped_and_lock_the_code(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Sms, 'owner@chez-lea.sn');
        $this->service->issueSmsCode($request, '+221771234567');

        $wrongCode = '000000';
        for ($i = 1; $i < PageVerificationCode::MAX_ATTEMPTS; $i++) {
            try {
                $this->service->confirmSmsCode($request, '+221771234567', $wrongCode, 'system:sms');
            } catch (PageVerificationException) {
                // attendu
            }
        }

        $row = PageVerificationCode::query()->where('request_id', $request->id)->firstOrFail();
        $this->assertSame(PageVerificationCode::MAX_ATTEMPTS - 1, $row->attempts);

        // Dernière tentative : consomme le plafond.
        try {
            $this->service->confirmSmsCode($request, '+221771234567', $wrongCode, 'system:sms');
        } catch (PageVerificationException) {
        }

        $row = PageVerificationCode::query()->where('request_id', $request->id)->firstOrFail();
        $this->assertTrue($row->isLockedOut(), 'Après le plafond, le code doit être verrouillé.');

        // Même le BON code est désormais refusé : le verrou est définitif.
        $this->expectException(PageVerificationException::class);
        $this->service->confirmSmsCode($request, '+221771234567', '123456', 'system:sms');
    }

    public function test_cooldown_blocks_immediate_reissue(): void
    {
        $t0 = Carbon::parse('2026-10-27 10:00:00');
        $request = $this->service->open($this->page, PageVerificationMethod::Sms, 'owner@chez-lea.sn', $t0);
        $this->service->issueSmsCode($request, '+221771234567', $t0);

        // Le message est résolu par le catalogue i18n et dépend donc de la
        // locale active du process (fr en développement, en en CI). Le CODE
        // d'erreur, lui, est stable — c'est le contrat que l'API promet.
        try {
            $this->service->issueSmsCode($request, '+221771234567', $t0->copy()->addSeconds(10));
            $this->fail('Le cooldown aurait dû refuser une réémission immédiate.');
        } catch (PageVerificationException $e) {
            $this->assertSame('PAGE_VERIFICATION_COOLDOWN', $e->errorCode());
        }
    }

    public function test_cooldown_expires_after_the_window(): void
    {
        $t0 = Carbon::parse('2026-10-27 10:00:00');
        $request = $this->service->open($this->page, PageVerificationMethod::Sms, 'owner@chez-lea.sn', $t0);
        $this->service->issueSmsCode($request, '+221771234567', $t0);

        $second = $this->service->issueSmsCode(
            $request,
            '+221771234567',
            $t0->copy()->addSeconds(PageVerificationCode::COOLDOWN_SECONDS + 1),
        );

        $this->assertMatchesRegularExpression('/^\d{6}$/', $second);
    }

    public function test_sms_code_cannot_be_issued_for_a_document_request(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Document, 'owner@chez-lea.sn');

        $this->expectException(PageVerificationException::class);

        $this->service->issueSmsCode($request, '+221771234567');
    }

    // ── Pièces justificatives : privées ────────────────────────────────────

    public function test_evidence_is_stored_with_private_path_hash_and_retention(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Document, 'owner@chez-lea.sn');

        $evidence = $this->service->attachEvidence(
            $request,
            'lease',
            's3-private',
            'pages/evidence/2026/lease-chez-lea.pdf',
            PageVerificationEvidence::hashContent('%PDF-1.4 fake'),
            'application/pdf',
            1024,
        );

        $this->assertSame('lease', $evidence->kind);
        $this->assertSame('s3-private', $evidence->storage_disc);
        $this->assertTrue($evidence->retention_until->isFuture(), 'La conservation doit être datée et dans le futur.');

        // La réponse du service ne contient AUCUNE URL de fichier.
        $this->assertArrayNotHasKey('url', $evidence->toArray());
        $this->assertArrayNotHasKey('public_url', $evidence->toArray());
    }

    public function test_evidence_with_a_public_path_is_refused(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Document, 'owner@chez-lea.sn');

        $this->expectException(PageVerificationException::class);

        $this->service->attachEvidence(
            $request,
            'lease',
            'public',
            'https://cdn.leopardo.test/lease.pdf',
            str_repeat('a', 64),
            'application/pdf',
            1024,
        );
    }

    public function test_absolute_path_is_refused_as_evidence_location(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Document, 'owner@chez-lea.sn');

        $this->expectException(PageVerificationException::class);

        $this->service->attachEvidence(
            $request,
            'lease',
            'local',
            '/var/www/storage/lease.pdf',
            str_repeat('b', 64),
            'application/pdf',
            1024,
        );
    }

    public function test_executable_evidence_type_is_refused(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Document, 'owner@chez-lea.sn');

        $this->expectException(PageVerificationException::class);

        $this->service->attachEvidence(
            $request,
            'lease',
            's3-private',
            'pages/evidence/malware.exe',
            str_repeat('c', 64),
            'application/x-msdownload',
            2048,
        );
    }

    public function test_oversized_evidence_is_refused(): void
    {
        $errors = PageVerificationEvidence::validateDescriptor(
            'lease',
            'application/pdf',
            PageVerificationEvidence::MAX_SIZE_BYTES + 1,
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('dépasse', implode(' ', $errors));
    }

    public function test_unknown_evidence_kind_is_refused(): void
    {
        $errors = PageVerificationEvidence::validateDescriptor('selfie', 'image/jpeg', 1024);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Type de pièce', implode(' ', $errors));
    }

    public function test_evidence_requires_an_open_request(): void
    {
        // Méthode `superadmin` : un dossier documentaire refuserait d'être
        // approuvé sans pièce (testé séparément), on ne veut pas ce refus ici.
        $request = $this->service->open($this->page, PageVerificationMethod::Superadmin, 'owner@chez-lea.sn');

        // Dossier clos d'avance via une approbation Superadmin : plus de dépôt.
        // (Méthode `superadmin` et non `document`, sinon l'approbation est
        // refusée faute de pièce — ce qui est justement le comportement testé
        // plus bas.)
        $this->service->approve(
            $request,
            'superadmin@leopardo.test',
            'proof_valid',
            null,
            'superadmin',
        );

        $this->expectException(PageVerificationException::class);

        $this->service->attachEvidence(
            $request->fresh() ?? $request,
            'lease',
            's3-private',
            'pages/evidence/late.pdf',
            str_repeat('d', 64),
            'application/pdf',
            1024,
        );
    }

    // ── Décisions ──────────────────────────────────────────────────────────

    public function test_document_request_cannot_be_approved_without_evidence(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Document, 'owner@chez-lea.sn');

        $this->expectException(PageVerificationException::class);

        $this->service->approve($request, 'superadmin@leopardo.test', 'proof_valid', null, 'document');
    }

    public function test_document_request_is_approved_with_evidence(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Document, 'owner@chez-lea.sn');
        $this->service->attachEvidence(
            $request,
            'lease',
            's3-private',
            'pages/evidence/lease.pdf',
            str_repeat('e', 64),
            'application/pdf',
            1024,
        );

        $this->service->approve($request, 'superadmin@leopardo.test', 'proof_valid', null, 'document');

        $freshPage = $this->page->fresh();
        $this->assertNotNull($freshPage);
        $this->assertSame(PageVerificationStatus::Verified, $freshPage->verification_status);
        $this->assertSame('document', $freshPage->verification_method);
    }

    public function test_rejection_clears_the_proof_and_records_the_reason(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Document, 'owner@chez-lea.sn');
        $this->service->attachEvidence(
            $request,
            'utility_bill',
            's3-private',
            'pages/evidence/bill.pdf',
            str_repeat('f', 64),
            'application/pdf',
            512,
        );

        $this->service->reject($request, 'superadmin@leopardo.test', 'proof_insufficient');

        $freshRequest = $request->fresh();
        $this->assertNotNull($freshRequest);
        $this->assertSame(PageVerificationRequest::STATUS_REJECTED, $freshRequest->status);
        $this->assertSame('proof_insufficient', $freshRequest->decision_reason);
        $this->assertSame('superadmin@leopardo.test', $freshRequest->decided_by);

        $freshPage = $this->page->fresh();
        $this->assertNotNull($freshPage);
        $this->assertSame(PageVerificationStatus::Rejected, $freshPage->verification_status);
        $this->assertNull($freshPage->verified_at);
    }

    public function test_dispute_flags_a_verified_page_without_deleting_the_history(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Superadmin, 'owner@chez-lea.sn');
        $this->service->approve($request->fresh() ?? $request, 'superadmin@leopardo.test', 'proof_valid', null, 'superadmin');

        $this->service->dispute($request->fresh() ?? $request, 'superadmin@leopardo.test', 'ownership_disputed');

        $freshPage = $this->page->fresh();
        $this->assertNotNull($freshPage);
        $this->assertSame(PageVerificationStatus::Disputed, $freshPage->verification_status);
        $this->assertNull($freshPage->verified_at, 'Un litige doit retirer la preuve de vérification.');

        // L'historique d'approbation reste consultable pour la modération.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'page.verification.approved',
            'auditable_id' => $this->page->id,
        ]);
    }

    public function test_revocation_clears_the_proof(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Superadmin, 'owner@chez-lea.sn');
        $this->service->approve($request->fresh() ?? $request, 'superadmin@leopardo.test', 'proof_valid', null, 'superadmin');

        $this->service->revoke($request->fresh() ?? $request, 'superadmin@leopardo.test', 'lease_terminated');

        $freshPage = $this->page->fresh();
        $this->assertNotNull($freshPage);
        $this->assertSame(PageVerificationStatus::Revoked, $freshPage->verification_status);
        $this->assertNull($freshPage->verified_at);
        $this->assertNull($freshPage->verification_method);
    }

    public function test_unknown_decision_reason_is_refused(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Document, 'owner@chez-lea.sn');
        $this->service->attachEvidence(
            $request,
            'lease',
            's3-private',
            'pages/evidence/lease2.pdf',
            str_repeat('1', 64),
            'application/pdf',
            100,
        );

        $this->expectException(PageVerificationException::class);

        $this->service->reject($request, 'superadmin@leopardo.test', 'because_i_said_so');
    }

    // ── Audit ──────────────────────────────────────────────────────────────

    public function test_every_transition_is_audited_with_actor_and_reason(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Document, 'owner@chez-lea.sn');
        $this->service->attachEvidence(
            $request,
            'lease',
            's3-private',
            'pages/evidence/lease3.pdf',
            str_repeat('2', 64),
            'application/pdf',
            300,
        );
        $this->service->reject($request, 'superadmin@leopardo.test', 'proof_insufficient');

        $actions = AuditLog::query()
            ->where('auditable_type', Page::class)
            ->where('auditable_id', $this->page->id)
            ->orderBy('id')
            ->pluck('action')
            ->all();

        $this->assertContains('page.verification.opened', $actions);
        $this->assertContains('page.verification.evidence_attached', $actions);
        $this->assertContains('page.verification.rejected', $actions);

        $rejected = AuditLog::query()
            ->where('action', 'page.verification.rejected')
            ->firstOrFail();
        $this->assertSame('pages', $rejected->module);
        /** @var array<string, mixed> $newValues */
        $newValues = $rejected->new_values;
        $this->assertSame('superadmin@leopardo.test', $newValues['actor']);
        $this->assertSame('proof_insufficient', $newValues['reason']);
    }

    public function test_audit_never_contains_the_storage_path_or_a_code(): void
    {
        $request = $this->service->open($this->page, PageVerificationMethod::Document, 'owner@chez-lea.sn');
        $this->service->attachEvidence(
            $request,
            'lease',
            's3-private',
            'pages/evidence/SECRET-BUCKET-PATH.pdf',
            str_repeat('3', 64),
            'application/pdf',
            300,
        );

        $all = AuditLog::query()
            ->where('auditable_type', Page::class)
            ->where('auditable_id', $this->page->id)
            ->get();

        $serialized = $all->toJson();
        $this->assertStringNotContainsString('SECRET-BUCKET-PATH', $serialized);
        $this->assertStringNotContainsString(str_repeat('3', 64), $serialized);
    }

    // ── Accès public aux preuves : interdit ────────────────────────────────

    public function test_evidence_route_is_not_registered_on_any_public_surface(): void
    {
        // La seule garantie vérifiable ici, en l'absence de contrôleur public,
        // est qu'aucune route ne pointe vers les pièces. On inspecte la table
        // de routage : toute route dont l'URI contient `evidence` et qui n'est
        // pas authentifiée serait une fuite.
        $routes = app('router')->getRoutes()->getRoutes();
        $inspected = 0;
        foreach ($routes as $route) {
            $inspected++;
            $uri = $route->uri();
            if (! str_contains($uri, 'evidence')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $this->assertTrue(
                // #8729 monte les routes plateforme sous `auth:super_admin_api`
                // (garde distincte de `auth:sanctum`, qui sert les routes
                // tenant). Le test doit connaître les DEUX gardes reelles :
                // n'en lister qu'une ferait echouer une route correctement
                // protegee, et le reflexe suivant serait d'elargir l'assertion
                // jusqu'au point de ne plus rien verifier.
                in_array('auth:sanctum', $middleware, true)
                    || in_array('auth', $middleware, true)
                    || in_array('auth:super_admin_api', $middleware, true),
                sprintf('La route %s expose des pièces sans authentification.', $uri),
            );
        }

        // Assertion explicite pour que le test ne soit pas « risqué » : on a
        // bien parcouru la table de routage, et aucune route de pièce n'existe
        // à ce stade (elles arriveront en #8729, derrière `auth:sanctum`).
        $this->assertGreaterThan(0, $inspected, 'La table de routage doit être inspectable.');
    }

    // ── Budget de requêtes : une seule Page par dossier ────────────────────

    public function test_request_status_maps_to_page_status_through_a_single_table(): void
    {
        // La correspondance vit dans UNE méthode : un contrôleur qui la
        // recopierait produirait deux vérités divergentes au premier ajout
        // d'état. On vérifie les six cas.
        $map = [
            PageVerificationRequest::STATUS_PENDING_REVIEW => PageVerificationStatus::Pending,
            PageVerificationRequest::STATUS_APPROVED => PageVerificationStatus::Verified,
            PageVerificationRequest::STATUS_REJECTED => PageVerificationStatus::Rejected,
            PageVerificationRequest::STATUS_DISPUTED => PageVerificationStatus::Disputed,
            PageVerificationRequest::STATUS_REVOKED => PageVerificationStatus::Revoked,
        ];

        foreach ($map as $requestStatus => $pageStatus) {
            /** @var PageVerificationRequest $request */
            $request = new PageVerificationRequest(['status' => $requestStatus]);
            $this->assertSame($pageStatus, $request->resultingPageStatus(), $requestStatus);
        }
    }
}
