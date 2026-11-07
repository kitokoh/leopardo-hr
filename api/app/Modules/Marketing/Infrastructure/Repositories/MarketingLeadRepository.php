<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Repositories;

use App\Core\Tenant\TenantManager;
use App\Modules\Marketing\Domain\Contracts\MarketingLeadRepositoryInterface;
use App\Modules\Marketing\Domain\DTOs\CreateMarketingLeadDTO;
use App\Modules\Marketing\Domain\Models\MarketingLead;
use Closure;
use Illuminate\Support\Collection;

class MarketingLeadRepository implements MarketingLeadRepositoryInterface
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    public function create(CreateMarketingLeadDTO $dto): MarketingLead
    {
        return $this->onPublicSchema(fn (): MarketingLead => MarketingLead::query()->create([
            'external_id' => $dto->externalId,
            'type' => $dto->type,
            'email' => $dto->email,
            'locale' => $dto->locale,
            'country' => $dto->country,
            'page' => $dto->page,
            'source' => $dto->source,
            'campaign' => $dto->campaign,
            'ip' => $dto->ip,
            'referrer' => $dto->referrer,
            'payload' => $dto->payload,
            'status' => MarketingLead::STATUS_NEW,
            'crm_forwarded' => $dto->crmForwarded,
            'email_forwarded' => $dto->emailForwarded,
            'captured_at' => $dto->capturedAt,
        ]));
    }

    /**
     * @return Collection<int, MarketingLead>
     */
    public function all(): Collection
    {
        return $this->onPublicSchema(
            fn (): Collection => MarketingLead::query()->orderByDesc('created_at')->get(),
        );
    }

    /**
     * Exécute `$callback` avec le `search_path` pointé sur `public`, puis
     * RESTAURE l'état d'entrée de la session.
     *
     * Issue #9132 (même défaut que `PlatformCompanyLookup`, #9115) —
     * `SET search_path TO public` était exécuté sans restauration. Sur une
     * connexion persistante (worker de queue, pool, requête HTTP), la suite de
     * la requête héritait de `public` : toute table tenant-scoped
     * (`shared_tenants.employees`, `biometric_enrollments`…) devenait
     * introuvable → SQLSTATE 42P01 « relation ... does not exist », puis
     * 25P02 « current transaction is aborted » en cascade.
     *
     * La bascule elle-même n'est PAS réimplémentée ici : `withinSearchPath()`
     * est le seul point autorisé à émettre `SET search_path` (ADR-0027 /
     * BOS-019 #8204) — il valide la chaîne cible (`assertSafeSearchPath`) et
     * avale le 25P02 de restauration pour ne pas masquer l'exception d'origine.
     * Réécrire ce `try/finally` localement, c'était réintroduire une
     * interpolation de chaîne non validée et perdre cette seconde garantie.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function onPublicSchema(Closure $callback): mixed
    {
        return $this->tenantManager->withinSearchPath('public', $callback);
    }
}
