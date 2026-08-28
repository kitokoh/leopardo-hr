<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Contracts\CrmLeadRepositoryInterface;
use App\Modules\CRM\Domain\Enums\CrmOpportunityStage;
use App\Modules\CRM\Domain\Exceptions\CrmLeadException;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmContact;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use Illuminate\Support\Facades\DB;

/**
 * #5717 — Conversion guidée d'un lead en account + contact + opportunity.
 *
 * Transactionnelle et idempotente :
 *  - un lead déjà converti répond 409 (claim conditionnel atomique) ;
 *  - compte réutilisé si un compte du tenant porte le même nom
 *    (déduplication naïve — la fusion avancée arrive avec #5718) ;
 *  - contact primaire créé si le compte n'en a pas encore ;
 *  - opportunité créée avec l'étape par défaut (whitelist) ;
 *  - audit `crm.lead.converted`.
 *
 * Note schéma (#5709 en cours) : les colonnes `pipeline_id` / `lead_id` de
 * `crm_opportunities` sont typées uuid dans la migration du swarm alors que
 * les PK sont bigint — elles restent NULL ici pour éviter toute erreur de
 * typage PostgreSQL ; le câblage fin arrive avec #5712.
 */
final class ConvertLeadAction
{
    public function __construct(private readonly CrmLeadRepositoryInterface $leads)
    {
    }

    /**
     * @param  array{amount?: numeric-string, currency?: string, expected_close_date?: string, stage?: string}  $data
     */
    public function handle(int $leadId, Employee $actor, array $data): CrmLead
    {
        $companyId = $this->companyId($actor);

        $lead = $this->leads->findForCompany($leadId, $companyId)
            ?? throw CrmLeadException::notFound();

        $stage = isset($data['stage'])
            ? CrmOpportunityStage::from($data['stage'])
            : CrmOpportunityStage::Prospecting;

        DB::transaction(function () use ($lead, $actor, $data, $stage): void {
            // ── 1. Compte (réutilisé si même nom) ────────────────────────────
            $accountName = $lead->company_name !== null && trim($lead->company_name) !== ''
                ? $lead->company_name
                : trim($lead->first_name.' '.$lead->last_name);

            $account = CrmAccount::query()->where('name', $accountName)->first();

            if (! $account instanceof CrmAccount) {
                $account = CrmAccount::query()->create([
                    'name' => $accountName,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'status' => 'active',
                ]);
            }

            // ── 2. Contact (primaire si le compte n'en a pas) ───────────────
            $hasPrimary = CrmContact::query()
                ->where('account_id', $account->id)
                ->where('is_primary', true)
                ->exists();

            $contact = CrmContact::query()->create([
                'account_id' => $account->id,
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'title' => $lead->title,
                'is_primary' => ! $hasPrimary,
                'notes' => $lead->notes,
            ]);

            // ── 3. Opportunité ───────────────────────────────────────────────
            CrmOpportunity::query()->create([
                'name' => $accountName,
                'stage' => $stage->value,
                'amount' => $data['amount'] ?? null,
                'currency' => $data['currency'] ?? null,
                'expected_close_date' => $data['expected_close_date'] ?? null,
                'status' => 'open',
                'notes' => $lead->notes,
            ]);

            // ── 4. Lead → converti (claim atomique, 409 si déjà fait) ───────
            if (! $this->leads->markConverted($lead, now()->toDateTimeString())) {
                throw CrmLeadException::alreadyConverted();
            }

            AuditLog::create([
                'company_id' => $actor->company_id,
                'user_id' => $actor->id,
                'action' => 'crm.lead.converted',
                'module' => 'crm',
                'auditable_type' => $lead->getMorphClass(),
                'auditable_id' => $lead->id,
                'new_values' => [
                    'account_id' => $account->id,
                    'contact_id' => $contact->id,
                ],
            ]);
        });

        // Re-lecture après transaction (expression volontairement non identique
        // à la 1re requête : le `?? throw` étend son narrowing PHPStan).
        $fresh = $this->leads->findForCompany($leadId, $this->companyId($actor));

        return $fresh ?? $lead;
    }

    /**
     * Identifiant du tenant obligatoire (routes scopées) — 404 sûr.
     */
    private function companyId(Employee $actor): string
    {
        return $actor->company_id ?? throw CrmLeadException::notFound();
    }
}
