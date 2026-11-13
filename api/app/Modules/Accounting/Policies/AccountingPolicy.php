<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingFixedAsset;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Domain\Models\AccountingTaxRate;

/**
 * Autorisations du module Comptabilité (issue #9379).
 *
 * Le défaut audité : le module n'avait **aucune Policy**. Toute l'autorisation
 * reposait sur le middleware de route `api.manager:comptable,principal`, qui
 * répond à une seule question — « ce rôle a-t-il accès au module ? » — et jamais
 * aux questions qui comptent réellement :
 *
 *   - un comptable peut-il **annuler** une facture déjà envoyée, ou seulement
 *     la consulter ?
 *   - peut-il **clôturer** une période, ou est-ce réservé au principal ?
 *   - peut-il **supprimer** une écriture manuelle après validation ?
 *   - peut-il **modifier le référentiel de TVA** (paramétrage structurant) ?
 *
 * Ces règles sont ici, en un seul endroit, et non dispersées dans les
 * contrôleurs. Le middleware reste le premier filtre (accès au module) ; la
 * Policy tranche l'action.
 *
 * Matrice retenue (rôles de gestion du tenant) :
 *
 * | Action                          | principal | comptable | autres |
 * |---------------------------------|-----------|-----------|--------|
 * | Consulter documents/écritures   |    oui    |    oui    |  non   |
 * | Créer / envoyer un document     |    oui    |    oui    |  non   |
 * | Annuler un document envoyé      |    oui    |    oui    |  non   |
 * | Annuler un document **payé**    |    non    |    non    |  non   |
 * | Saisir une écriture manuelle    |    oui    |    oui    |  non   |
 * | Supprimer une écriture manuelle |    oui    |    oui    |  non   |
 * | Clôturer une période / exercice |    oui    |    non    |  non   |
 * | Gérer le référentiel de TVA     |    oui    |    non    |  non   |
 * | Gérer les immobilisations       |    oui    |    oui    |  non   |
 * | Supprimer une immobilisation    |    oui    |    non    |  non   |
 *
 * La clôture et le paramétrage fiscal sont réservés au principal : ce sont des
 * actes irréversibles ou structurants, qu'un comptable ne doit pas pouvoir
 * déclencher seul (séparation des tâches).
 */
class AccountingPolicy
{
    /** Rôles autorisés à lire le module. */
    public const READ_ROLES = ['principal', 'comptable'];

    /** Rôles autorisés à saisir des pièces. */
    public const WRITE_ROLES = ['principal', 'comptable'];

    /** Rôles autorisés aux actes irréversibles/structurants. */
    public const ADMIN_ROLES = ['principal'];

    public function viewAny(Employee $actor): bool
    {
        return $this->hasAnyRole($actor, self::READ_ROLES);
    }

    public function view(Employee $actor, mixed $subject = null): bool
    {
        if (! $this->hasAnyRole($actor, self::READ_ROLES)) {
            return false;
        }

        return $this->sameTenant($actor, $subject);
    }

    public function create(Employee $actor): bool
    {
        return $this->hasAnyRole($actor, self::WRITE_ROLES);
    }

    public function update(Employee $actor, mixed $subject = null): bool
    {
        if (! $this->hasAnyRole($actor, self::WRITE_ROLES)) {
            return false;
        }

        return $this->sameTenant($actor, $subject);
    }

    /**
     * Annulation d'un document : autorisée au comptable, **sauf** sur un
     * document déjà payé — l'annulation d'une pièce réglée exige un avoir, pas
     * une suppression d'écriture.
     */
    public function cancel(Employee $actor, ?AccountingDocument $document = null): bool
    {
        if (! $this->hasAnyRole($actor, self::WRITE_ROLES)) {
            return false;
        }

        if ($document === null) {
            return true;
        }

        if (! $this->sameTenant($actor, $document)) {
            return false;
        }

        // #9379 — « paye » signifie INTEGRALEMENT regle. Un document
        // partiellement regle reste annulable : DocumentWorkflowService
        // autorise explicitement Draft/Sent/PartiallyPaid/Overdue, et la
        // matrice ci-dessus ne reserve l'avoir qu'aux pieces soldees. La garde
        // precedente (`paid_amount <= 0`) refusait toute piece ayant recu le
        // moindre acompte, en contradiction avec le service.
        $paid = (float) $document->paid_amount;
        $total = (float) $document->total_ttc;

        return $paid <= 0.0 || $paid < $total;
    }

    /**
     * Écriture manuelle : saisie et correction ouvertes au comptable.
     */
    public function manageManualEntry(Employee $actor, ?AccountingJournalEntry $entry = null): bool
    {
        if (! $this->hasAnyRole($actor, self::WRITE_ROLES)) {
            return false;
        }

        return $entry === null || $this->sameTenant($actor, $entry);
    }

    /**
     * Clôture de période / d'exercice : principal uniquement (acte
     * irréversible, séparation des tâches).
     */
    public function closePeriod(Employee $actor): bool
    {
        return $this->hasAnyRole($actor, self::ADMIN_ROLES);
    }

    /**
     * Référentiel de TVA : lecture large, écriture réservée au principal
     * (paramétrage fiscal structurant).
     */
    public function manageTaxRates(Employee $actor, ?AccountingTaxRate $taxRate = null): bool
    {
        if (! $this->hasAnyRole($actor, self::ADMIN_ROLES)) {
            return false;
        }

        return $taxRate === null || $this->sameTenant($actor, $taxRate);
    }

    /**
     * Immobilisations : création/modification au comptable, suppression au
     * principal (une suppression efface l'historique des dotations).
     */
    public function manageFixedAssets(Employee $actor, ?AccountingFixedAsset $asset = null): bool
    {
        if (! $this->hasAnyRole($actor, self::WRITE_ROLES)) {
            return false;
        }

        return $asset === null || $this->sameTenant($actor, $asset);
    }

    public function deleteFixedAsset(Employee $actor, ?AccountingFixedAsset $asset = null): bool
    {
        if (! $this->hasAnyRole($actor, self::ADMIN_ROLES)) {
            return false;
        }

        return $asset === null || $this->sameTenant($actor, $asset);
    }

    /**
     * Export comptable (FEC, CSV, PDF) : lecture suffit, mais jamais hors
     * tenant.
     */
    public function export(Employee $actor): bool
    {
        return $this->hasAnyRole($actor, self::READ_ROLES);
    }

    /**
     * @param  list<string>  $roles
     */
    private function hasAnyRole(Employee $actor, array $roles): bool
    {
        return $actor->hasManagerRole(...$roles);
    }

    /**
     * Isolation tenant : un acteur ne voit jamais la pièce d'une autre
     * entreprise, quel que soit son rôle.
     */
    private function sameTenant(Employee $actor, mixed $subject): bool
    {
        if ($subject === null) {
            return true;
        }

        $companyId = $subject->company_id ?? null;

        return $companyId === null || (string) $companyId === (string) $actor->company_id;
    }
}
