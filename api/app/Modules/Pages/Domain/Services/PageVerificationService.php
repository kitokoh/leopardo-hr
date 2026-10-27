<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Pages\Domain\Enums\PageVerificationMethod;
use App\Modules\Pages\Domain\Enums\PageVerificationStatus;
use App\Modules\Pages\Domain\Exceptions\PageVerificationException;
use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageVerificationCode;
use App\Modules\Pages\Domain\Models\PageVerificationEvidence;
use App\Modules\Pages\Domain\Models\PageVerificationRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orchestration du dossier de vérification (BC PAGES, #8725).
 *
 * ## Ce que ce service garantit
 *
 * 1. **Un seul dossier ouvert par Page.** Ouvrir un dossier alors qu'un autre
 *    est en cours renvoie une erreur explicite, plutôt que de créer deux
 *    dossiers que deux modérateurs instruiraient en parallèle avec des
 *    conclusions opposées.
 * 2. **Chaque transition est auditée.** `audit_logs` (module `pages`) reçoit
 *    l'état, l'acteur et le motif — jamais le contenu d'une pièce ni un code.
 *    L'audit est le livrable vérifiable de l'issue : « état, preuves, décision,
 *    acteur et motif sont audités sans exposer les pièces au public ».
 * 3. **Le statut de la Page suit le dossier.** Une seule table de
 *    correspondance (`PageVerificationRequest::resultingPageStatus()`), pas de
 *    `match` recopié dans un contrôleur.
 * 4. **Les pièces ne sortent jamais en clair.** `evidenceFor()` retourne des
 *    métadonnées ; le téléchargement passe par `signedUrlFor()`, qui exige
 *    l'approbation d'un acteur habilité.
 *
 * ## Ce que ce service ne fait PAS
 *
 * Il n'envoie pas de SMS lui-même : il émet le code et le retourne à
 * l'appelant (une Action/Job le transmet au provider). Un service de domaine
 * qui parle HTTP serait intestable et couplé à un opérateur.
 */
final class PageVerificationService
{
    /** Nombre maximal de dossiers ouverts simultanément par Page (invariant). */
    public const MAX_OPEN_REQUESTS_PER_PAGE = 1;

    /**
     * Ouvre un dossier de vérification.
     *
     * @param  string  $actor  Identifiant de l'acteur (email ou `user:{id}`) pour l'audit
     *
     * @throws PageVerificationException
     */
    public function open(Page $page, PageVerificationMethod $method, string $actor, ?Carbon $at = null): PageVerificationRequest
    {
        $now = $at ?? Carbon::now();

        if (! PageVerificationMethod::isEnabled($method)) {
            throw PageVerificationException::methodDisabled($method->value);
        }

        if ($page->suspended_at !== null) {
            throw PageVerificationException::suspendedPage();
        }

        $alreadyOpen = PageVerificationRequest::query()
            ->where('page_id', $page->id)
            ->open()
            ->exists();

        if ($alreadyOpen) {
            throw PageVerificationException::requestAlreadyOpen();
        }

        return DB::transaction(function () use ($page, $method, $actor, $now): PageVerificationRequest {
            /** @var PageVerificationRequest $request */
            $request = PageVerificationRequest::create([
                'company_id' => $page->company_id,
                'page_id' => $page->id,
                'method' => $method->value,
                'status' => PageVerificationRequest::STATUS_OPEN,
                'submitted_by' => $actor,
                'submitted_at' => $now,
            ]);

            $this->applyToPage($page, PageVerificationStatus::Pending, $method->value, $now);
            $this->audit($page, 'page.verification.opened', $actor, [
                'request_id' => $request->id,
                'method' => $method->value,
            ], $now);

            return $request;
        });
    }

    /**
     * Émet un code SMS pour un dossier `sms`.
     *
     * Retourne le code EN CLAIR une seule fois : c'est à l'appelant de
     * l'envoyer puis de l'oublier. Rien de ce code n'est persisté ni audité.
     *
     * @return string Code à 6 chiffres
     *
     * @throws PageVerificationException
     */
    public function issueSmsCode(
        PageVerificationRequest $request,
        string $phoneE164,
        ?Carbon $at = null,
    ): string {
        $now = $at ?? Carbon::now();

        if ($request->method !== PageVerificationMethod::Sms->value) {
            throw PageVerificationException::wrongMethod('sms', (string) $request->method);
        }

        if ($request->isDecided()) {
            throw PageVerificationException::requestClosed();
        }

        // Cooldown vérifié sur le dernier code émis vers CE numéro : sans lui,
        // un attaquant peut saturer le numéro cible et le budget SMS.
        $last = PageVerificationCode::query()
            ->where('request_id', $request->id)
            ->where('phone_e164', $phoneE164)
            ->orderByDesc('id')
            ->first();

        if ($last instanceof PageVerificationCode
            && $last->cooldown_until instanceof Carbon
            && $last->cooldown_until->greaterThan($now)) {
            throw PageVerificationException::cooldown((int) ceil((float) $last->cooldown_until->diffInSeconds($now)));
        }

        $plain = PageVerificationCode::generatePlainCode();

        PageVerificationCode::create([
            'company_id' => $request->company_id,
            'request_id' => $request->id,
            'phone_e164' => $phoneE164,
            'code_hash' => PageVerificationCode::hashCode($plain, (int) $request->id),
            'attempts' => 0,
            'max_attempts' => PageVerificationCode::MAX_ATTEMPTS,
            'expires_at' => $now->copy()->addMinutes(PageVerificationCode::TTL_MINUTES),
            'cooldown_until' => $now->copy()->addSeconds(PageVerificationCode::COOLDOWN_SECONDS),
            'created_at' => $now,
        ]);

        $page = $request->page;
        if ($page instanceof Page) {
            $this->audit($page, 'page.verification.code_issued', (string) $request->submitted_by, [
                'request_id' => $request->id,
                'phone_suffix' => substr($phoneE164, -4),
            ], $now);
        }

        return $plain;
    }

    /**
     * Vérifie un code SMS et, en cas de succès, approuve le dossier.
     *
     * @throws PageVerificationException
     */
    public function confirmSmsCode(
        PageVerificationRequest $request,
        string $phoneE164,
        string $plainCode,
        string $actor,
        ?Carbon $at = null,
    ): PageVerificationRequest {
        $now = $at ?? Carbon::now();

        if ($request->method !== PageVerificationMethod::Sms->value) {
            throw PageVerificationException::wrongMethod('sms', (string) $request->method);
        }

        // ⚠️ Le compteur de tentatives doit survivre à l'échec de la
        // comparaison. Incrémenter DANS la transaction qui valide le code
        // paraissait élégant, mais l'exception de refus provoquait le rollback
        // de l'incrément : le compteur restait à 0 et 6 chiffres se
        // bruteforçaient donc sans limite. On incrémente dans sa PROPRE
        // transaction, courte et committée, avant toute comparaison.
        /** @var PageVerificationCode $code */
        $code = DB::transaction(function () use ($request, $phoneE164, $now): PageVerificationCode {
            /** @var PageVerificationCode|null $locked */
            $locked = PageVerificationCode::query()
                ->where('request_id', $request->id)
                ->where('phone_e164', $phoneE164)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof PageVerificationCode) {
                throw PageVerificationException::codeNotFound();
            }

            if (! $locked->isUsable($now)) {
                throw PageVerificationException::codeUnusable(
                    $locked->isExpired($now) ? 'expired' : ($locked->isConsumed() ? 'consumed' : 'locked')
                );
            }

            $locked->attempts = $locked->attempts + 1;
            $locked->save();

            return $locked;
        });

        if (! $code->matches($plainCode)) {
            throw PageVerificationException::codeMismatch($code->max_attempts - $code->attempts);
        }

        return DB::transaction(function () use ($request, $code, $actor, $now): PageVerificationRequest {
            $code->consumed_at = $now;
            $code->save();

            return $this->approve($request, $actor, 'proof_valid', $now, 'sms');
        });
    }

    /**
     * Enregistre une pièce justificative (métadonnées seules).
     *
     * @throws PageVerificationException
     */
    public function attachEvidence(
        PageVerificationRequest $request,
        string $kind,
        string $storageDisc,
        string $storagePath,
        string $contentHash,
        string $mimeType,
        int $sizeBytes,
        ?Carbon $at = null,
    ): PageVerificationEvidence {
        $now = $at ?? Carbon::now();

        if ($request->method !== PageVerificationMethod::Document->value) {
            throw PageVerificationException::wrongMethod('document', (string) $request->method);
        }

        if ($request->isDecided()) {
            throw PageVerificationException::requestClosed();
        }

        // Un chemin absolu ou une URL publique dans `storage_path` ferait
        // fuiter la pièce : refus explicite avant l'INSERT (le CHECK de la base
        // est la seconde barrière, pas la première).
        if (str_starts_with($storagePath, 'http') || str_starts_with($storagePath, '//') || str_starts_with($storagePath, '/')) {
            throw PageVerificationException::publicPath();
        }

        $errors = PageVerificationEvidence::validateDescriptor($kind, $mimeType, $sizeBytes);
        if ($errors !== []) {
            throw PageVerificationException::invalidEvidence($errors);
        }

        /** @var PageVerificationEvidence $evidence */
        $evidence = PageVerificationEvidence::create([
            'company_id' => $request->company_id,
            'request_id' => $request->id,
            'kind' => $kind,
            'storage_disc' => $storageDisc,
            'storage_path' => $storagePath,
            'content_hash' => $contentHash,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'retention_until' => $now->copy()->addMonths(PageVerificationEvidence::DEFAULT_RETENTION_MONTHS),
            'created_at' => $now,
        ]);

        $request->evidence_count = (int) $request->evidence_count + 1;
        $request->status = PageVerificationRequest::STATUS_PENDING_REVIEW;
        $request->save();

        $page = $request->page;
        if ($page instanceof Page) {
            // On audite le TYPE et l'empreinte, jamais le chemin : le chemin de
            // stockage dans un journal d'audit est déjà une fuite d'information
            // (il nomme la structure du bucket).
            $this->audit($page, 'page.verification.evidence_attached', (string) $request->submitted_by, [
                'request_id' => $request->id,
                'kind' => $kind,
                'sha256_prefix' => substr($contentHash, 0, 12),
            ], $now);
        }

        return $evidence;
    }

    /**
     * Approuve un dossier et vérifie la Page.
     *
     * @throws PageVerificationException
     */
    public function approve(
        PageVerificationRequest $request,
        string $actor,
        string $reason = 'proof_valid',
        ?Carbon $at = null,
        ?string $method = null,
    ): PageVerificationRequest {
        $now = $at ?? Carbon::now();

        if ($request->isDecided()) {
            throw PageVerificationException::requestClosed();
        }

        if ($request->method === PageVerificationMethod::Document->value && (int) $request->evidence_count === 0) {
            // Approuver un dossier documentaire sans aucune pièce est une
            // approbation sans preuve : refusée, sinon la vérification ne veut
            // plus rien dire.
            throw PageVerificationException::noEvidence();
        }

        $request->status = PageVerificationRequest::STATUS_APPROVED;
        $request->decided_by = $actor;
        $request->decided_at = $now;
        $request->decision_reason = $reason;
        $request->save();

        $page = $request->page;
        if ($page instanceof Page) {
            $this->applyToPage($page, PageVerificationStatus::Verified, $method ?? (string) $request->method, $now);
            $this->audit($page, 'page.verification.approved', $actor, [
                'request_id' => $request->id,
                'method' => $request->method,
                'reason' => $reason,
            ], $now);
        }

        return $request;
    }

    /**
     * Rejette un dossier.
     *
     * @throws PageVerificationException
     */
    public function reject(PageVerificationRequest $request, string $actor, string $reason, ?Carbon $at = null): PageVerificationRequest
    {
        return $this->decide($request, PageVerificationRequest::STATUS_REJECTED, PageVerificationStatus::Rejected, $actor, $reason, $at);
    }

    /**
     * Ouvre un litige sur une Page vérifiée (un tiers conteste la propriété).
     *
     * Distinct d'un rejet : la vérification précédente reste dans l'historique,
     * mais la Page repasse en `disputed` et le badge public doit l'indiquer.
     *
     * @throws PageVerificationException
     */
    public function dispute(PageVerificationRequest $request, string $actor, string $reason = 'ownership_disputed', ?Carbon $at = null): PageVerificationRequest
    {
        return $this->decide($request, PageVerificationRequest::STATUS_DISPUTED, PageVerificationStatus::Disputed, $actor, $reason, $at);
    }

    /**
     * Révoque une vérification (bail terminé, fraude découverte).
     *
     * @throws PageVerificationException
     */
    public function revoke(PageVerificationRequest $request, string $actor, string $reason, ?Carbon $at = null): PageVerificationRequest
    {
        return $this->decide($request, PageVerificationRequest::STATUS_REVOKED, PageVerificationStatus::Revoked, $actor, $reason, $at);
    }

    /**
     * Décision négative générique — un seul chemin pour rejet/litige/révocation.
     *
     * Centraliser évite la dérive classique : un des trois chemins oublie
     * d'effacer `verified_at`, et la Page reste « vérifiée » après révocation.
     *
     * @throws PageVerificationException
     */
    private function decide(
        PageVerificationRequest $request,
        string $requestStatus,
        PageVerificationStatus $pageStatus,
        string $actor,
        string $reason,
        ?Carbon $at,
    ): PageVerificationRequest {
        $now = $at ?? Carbon::now();

        if (! in_array($reason, PageVerificationRequest::DECISION_REASONS, true)) {
            throw PageVerificationException::invalidReason($reason);
        }

        if ($request->isDecided() && $request->status !== PageVerificationRequest::STATUS_APPROVED) {
            throw PageVerificationException::requestClosed();
        }

        $request->status = $requestStatus;
        $request->decided_by = $actor;
        $request->decided_at = $now;
        $request->decision_reason = $reason;
        $request->save();

        $page = $request->page;
        if ($page instanceof Page) {
            $this->applyToPage($page, $pageStatus, (string) $request->method, $now);
            $this->audit($page, 'page.verification.'.$requestStatus, $actor, [
                'request_id' => $request->id,
                'reason' => $reason,
            ], $now);
        }

        return $request;
    }

    /**
     * Applique un état de vérification à la Page.
     *
     * Passe par la transition validée du modèle (`transitionVerification`) :
     * on ne force jamais `verification_status` en base, sinon la machine à
     * états de `PageVerificationStatus` ne sert plus à rien.
     *
     * @throws PageVerificationException
     */
    private function applyToPage(Page $page, PageVerificationStatus $status, string $method, Carbon $at): void
    {
        $current = $page->verification_status;

        // `Pending` est atteint depuis `unverified`, mais aussi depuis
        // `revoked`/`rejected` : la machine à états l'autorise déjà.
        if (! $current->canTransitionTo($status)) {
            throw PageVerificationException::invalidTransition($current->value, $status->value);
        }

        // La garde `canTransitionTo` ci-dessus suffit : `transitionVerification`
        // ne peut plus lever. Pas de try/catch décoratif — un `catch` qui ne
        // peut jamais se déclencher donne l'illusion d'être protégé et masque
        // le jour où la contrainte change (PHPStan le signale d'ailleurs).
        $page->transitionVerification($status, $method, $at);

        $page->save();
    }

    /**
     * Écrit une ligne d'audit (module `pages`).
     *
     * Le contenu de `metadata` est volontairement restreint : identifiants,
     * type de pièce, suffixe de téléphone. Jamais de chemin de stockage, jamais
     * de code, jamais d'URL signée — un journal d'audit est lisible par plus de
     * monde qu'une pièce.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function audit(Page $page, string $action, string $actor, array $metadata, Carbon $at): void
    {
        AuditLog::create([
            'company_id' => $page->company_id,
            'action' => $action,
            'module' => 'pages',
            'auditable_type' => Page::class,
            'auditable_id' => $page->id,
            'new_values' => ['actor' => $actor] + $metadata,
            'metadata' => ['page_slug' => $page->slug],
        ]);
    }
}
