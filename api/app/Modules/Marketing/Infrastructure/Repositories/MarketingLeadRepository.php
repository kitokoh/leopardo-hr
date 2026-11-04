<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Repositories;

use App\Modules\Marketing\Domain\Contracts\MarketingLeadRepositoryInterface;
use App\Modules\Marketing\Domain\DTOs\CreateMarketingLeadDTO;
use App\Modules\Marketing\Domain\Models\MarketingLead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class MarketingLeadRepository implements MarketingLeadRepositoryInterface
{
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
     * Pattern try/finally (#2689, #3368) : le search_path est TOUJOURS rendu à
     * son état d'entrée, y compris si le callback lève. No-op hors PostgreSQL.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function onPublicSchema(callable $callback): mixed
    {
        $previous = $this->currentSearchPath();

        if ($previous === null) {
            return $callback();
        }

        DB::statement('SET search_path TO public');

        try {
            return $callback();
        } finally {
            DB::statement('SET search_path TO '.$previous);
        }
    }

    /**
     * `search_path` courant de la session, ou null si indisponible (driver
     * non PostgreSQL, connexion absente) — dans ce cas on ne touche à rien.
     */
    private function currentSearchPath(): ?string
    {
        if (DB::getDriverName() !== 'pgsql') {
            return null;
        }

        try {
            $row = DB::selectOne('SHOW search_path');
        } catch (Throwable) {
            return null;
        }

        if (! is_object($row) || ! property_exists($row, 'search_path')) {
            return null;
        }

        $value = (string) $row->search_path;

        return $value === '' ? null : $value;
    }
}
