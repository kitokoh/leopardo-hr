<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Events\PublicContactRequestReceived;
use App\Modules\CRM\Domain\Enums\CrmLeadStatus;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * #8760 — Consomme `public.contact_request_received`.
 *
 * Deux effets seulement, tous deux dans les BC propriétaires :
 *  1. **CRM (BC-11)** — matérialise le prospect `source = public_gateway` dans
 *     le tenant destinataire (même geste que `CreateCrmLeadFromCatalogInquiry`
 *     sur le canal catalogue) ;
 *  2. **Notification (BC-13)** — notifie les managers du tenant via
 *     `CommunicationService` (préférences, heures calmes, quotas et audit
 *     respectés par le service — rien n'est ré-implémenté ici).
 *
 * ⚠️ Isolation multi-tenant : le tenant est résolu depuis
 * `PublicContactRequest::resolved_company_id` (écrit après résolution
 * fail-closed côté serveur), puis TOUTES les écritures s'exécutent dans
 * `TenantManager::withinTenant()` — le `search_path` PostgreSQL est basculé sur
 * le schéma du tenant et restauré en `finally`. Aucune ligne ne peut donc
 * atterrir dans le mauvais tenant, même si l'événement est rejoué.
 *
 * ⚠️ Idempotence : un rejeu (file d'attente, retry) ne doit pas dupliquer la
 * notification. La demande elle-même est unique (`idempotency_key`), et la
 * notification porte la référence de la demande.
 *
 * ⚠️ Séparation Léa : ce listener n'écrit rien dans `ai_*` et n'appelle aucun
 * service IA. Un message humain vers un professionnel n'est jamais de la
 * mémoire conversationnelle d'assistante.
 */
class CreateCrmLeadFromPublicContact
{
    public function __construct(
        private readonly CommunicationService $communicationService,
        private readonly TenantManager $tenants,
    ) {}

    public function handle(PublicContactRequestReceived $event): void
    {
        $request = $event->contactRequest;
        $companyId = $request->resolved_company_id;

        // Fail-closed : sans tenant résolu, aucune écriture n'est tentée. Un
        // profil non publiable n'a jamais produit d'événement, ce cas ne peut
        // venir que d'une donnée altérée.
        if (! is_string($companyId) || $companyId === '') {
            Log::warning('public.contact_request_received sans tenant résolu', [
                'contact_request_id' => $request->id,
            ]);

            return;
        }

        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            return;
        }

        // Anti-doublon de traitement : le conteneur peut résoudre le même
        // listener deux fois pour un seul `event()` (comportement déjà observé
        // sur `CatalogInquiryReceived`), ce qui créerait DEUX leads et DEUX
        // notifications pour une seule demande. `Cache::add()` est atomique :
        // seul le premier passage exécute les effets de bord.
        $onceKey = 'public-gateway.contact-request.processed.'.$request->id;

        if (! Cache::add($onceKey, 1, 3600)) {
            return;
        }

        $this->tenants->withinTenant($company, function () use ($company, $request): void {
            $lead = $this->persistCrmLead($company, $request);

            $this->notifyTenantManagers($company, $request, $lead);
        });
    }

    private function persistCrmLead(Company $company, \App\Modules\Platform\Domain\Models\PublicContactRequest $request): CrmLead
    {
        $notes = sprintf(
            'Passerelle publique — profil « %s » (%s: %s)',
            (string) $request->target_slug,
            (string) $request->target_type,
            (string) $request->target_slug
        );

        if (is_string($request->message) && trim($request->message) !== '') {
            $notes .= ' — '.trim($request->message);
        }

        /** @var CrmLead $lead */
        $lead = CrmLead::query()->create([
            'company_id' => $company->id,
            'first_name' => (string) $request->sender_name,
            'company_name' => null,
            'email' => (string) $request->sender_email,
            'phone' => $request->sender_phone,
            'source' => 'public_gateway',
            'status' => CrmLeadStatus::New->value,
            'notes' => $notes,
        ]);

        return $lead;
    }

    private function notifyTenantManagers(
        Company $company,
        \App\Modules\Platform\Domain\Models\PublicContactRequest $request,
        CrmLead $lead
    ): void {
        $managers = Employee::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->where('role', 'manager')
            ->whereIn('manager_role', ['principal', 'rh', 'manager'])
            ->limit(20)
            ->get();

        foreach ($managers as $manager) {
            $this->communicationService->notifyEmployee(
                $manager,
                'public.contact_request_received',
                [
                    'category' => 'crm',
                    // Contexte À PLAT : `CommunicationService` filtre les
                    // métadonnées par allowlist (`public_metadata_keys`) et
                    // résout les variables du gabarit (`vars`) au même niveau.
                    // Refermer ces clés dans un sous-tableau `data` les
                    // rendrait invisibles aux deux mécanismes.
                    'contact_request_id' => $request->id,
                    'lead_id' => $lead->id,
                    'sender_name' => (string) $request->sender_name,
                    'target_type' => (string) $request->target_type,
                    'target_slug' => (string) $request->target_slug,
                ],
                ['app']
            );
        }
    }
}
