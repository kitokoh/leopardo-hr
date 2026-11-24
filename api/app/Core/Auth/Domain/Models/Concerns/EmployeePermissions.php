<?php

declare(strict_types=1);

namespace App\Core\Auth\Domain\Models\Concerns;

use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Roles, perimetres de visibilite et acces ressource-scopes d'un employe —
 * extrait de `Employee` par #9888.
 *
 * ## Pourquoi ce cluster, et pas les relations
 *
 * `Employee` faisait 833 lignes et melangeait trois choses : les relations
 * Eloquent vers 11 modules, la synchronisation de `user_lookups`, et TOUTE la
 * logique d'autorisation. Seul le troisieme bloc est cohesif et sans
 * dependance vers `Modules` : il ne parle que de `manager_role`, de
 * `department_id`, de `manager_id` et de `EmployeeResourceAssignment`
 * (Core/Tenant).
 *
 * Les relations, elles, restent dans le modele : les deplacer dans un trait
 * n'aurait fait que DEPLACER les aretes `Core -> Modules` vers un nouveau
 * fichier, en faisant grossir la baseline de
 * `IdentityLayerBoundariesArchitectureTest` — c'est-a-dire en deguisant la
 * dette au lieu de la reduire. Le ratchet de ce test ne peut que retrecir ;
 * une extraction qui l'allonge n'est pas un progres.
 *
 * ## La regle qui gouverne tout ce fichier : fail-closed
 *
 * Chaque methode qui repond « puis-je voir / agir sur X ? » retourne `false`
 * quand l'information manque (pas de departement, pas de grant, niveau
 * inconnu). Un manager mal configure voit RIEN, jamais tout. C'est la meme
 * decision que `TenantContextMissingException` cote tenant : l'absence de
 * contexte n'ouvre pas les droits, elle les ferme.
 *
 * ## Ce qui n'a pas change (#9888)
 *
 * Deplacement mecanique. Aucune signature, aucun retour, aucune comparaison
 * n'a ete modifie. Les commentaires d'origine qui portaient une decision
 * (pourquoi `-1` plutot que `null`, pourquoi `rh` n'a que `view`) sont
 * conserves : ils expliquent le comportement, pas la syntaxe.
 */
trait EmployeePermissions
{
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    public function hasManagerRole(string ...$roles): bool
    {
        if (! $this->isManager()) {
            return false;
        }

        if ($roles === []) {
            return true;
        }

        return in_array($this->manager_role, $roles, true);
    }

    /**
     * PA2-COMM-007 - True once a mail provider bounce webhook has recorded
     * a hard bounce for this employee's `email`. `MailMessageProvider`
     * checks this before every send so a known-bad address stops being
     * retried on every future communication.
     */
    public function hasBouncedEmail(): bool
    {
        return $this->email_bounced_at !== null;
    }

    public function isPrincipal(): bool
    {
        return $this->hasManagerRole('principal');
    }

    public function isHr(): bool
    {
        return $this->hasManagerRole('rh');
    }

    public function isMarketing(): bool
    {
        return $this->hasManagerRole('marketing');
    }

    public function isComptable(): bool
    {
        return $this->hasManagerRole('comptable');
    }

    public function isDept(): bool
    {
        return $this->hasManagerRole('dept');
    }

    public function isSuperviseur(): bool
    {
        return $this->hasManagerRole('superviseur');
    }

    /**
     * Whether this actor's manager scope is limited to a single department
     * (currently only `manager_role = 'dept'`). Company-wide roles
     * (principal, rh, comptable, marketing) and self-service employees
     * are not department-scoped.
     */
    public function isDepartmentScoped(): bool
    {
        return $this->isDept();
    }

    /**
     * Whether this actor's manager scope is limited to their own directly
     * assigned team (`manager_role = 'superviseur'`). Per RBAC_SYSTEM.md,
     * a superviseur only ever sees "son equipe assignee", never the whole
     * company (PA2-SEC-003).
     */
    public function isSupervisorScoped(): bool
    {
        return $this->isSuperviseur();
    }

    /**
     * Whether $target sits within this department-scoped actor's own
     * department. Returns false when the actor has no department
     * assigned (fail closed) so a misconfigured dept manager sees
     * nothing rather than everything.
     */
    public function managesDepartmentOf(self $target): bool
    {
        if ($this->department_id === null) {
            return false;
        }

        return $this->department_id === $target->department_id;
    }

    /**
     * Whether $target is directly assigned to this supervisor via
     * `manager_id` (the existing hierarchy FK; see PA2-SEC-003). A
     * superviseur's "equipe assignee" is defined as employees whose
     * manager_id points back to them. Self is always included so a
     * superviseur can act on their own records.
     */
    public function managesEmployeeDirectly(self $target): bool
    {
        if ($this->id === $target->id) {
            return true;
        }

        return $target->manager_id !== null && $target->manager_id === $this->id;
    }

    /**
     * Whether this actor's visibility is limited to an explicit subset of
     * employees (department for `dept`, direct reports for `superviseur`)
     * rather than the whole company. Company-wide roles (principal, rh,
     * comptable, marketing) and self-service employees are not team-scoped.
     */
    public function isTeamScoped(): bool
    {
        return $this->isDept() || $this->isSuperviseur();
    }

    /**
     * Dispatches to the correct "manages" check for whichever team-scoped
     * role the actor holds. Callers should guard with isTeamScoped() (or
     * accept that non-team-scoped actors always return true here).
     */
    public function managesTeamMemberOf(self $target): bool
    {
        if ($this->isDept()) {
            return $this->managesDepartmentOf($target);
        }

        if ($this->isSuperviseur()) {
            return $this->managesEmployeeDirectly($target);
        }

        return true;
    }

    /**
     * Constrains an Employee query builder to the employees this actor is
     * allowed to see when they hold a team-scoped manager_role. No-op for
     * company-wide roles. Fails closed: a dept manager without a
     * department, or a superviseur (who by definition has no direct
     * reports until manager_id is set on someone), sees nobody rather
     * than everybody.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleToManager(Builder $query, self $actor): Builder
    {
        if ($actor->isDept()) {
            return $query->where('department_id', $actor->department_id ?? -1);
        }

        if ($actor->isSuperviseur()) {
            return $query->where(function ($scope) use ($actor): void {
                $scope->where('manager_id', $actor->id)->orWhere('id', $actor->id);
            });
        }

        return $query;
    }

    /**
     * Resolves who should be alerted about this employee (e.g. an
     * out-of-geofence punch): their direct `manager_id` when one is
     * assigned and still active, otherwise every active company-wide
     * manager (`manager_role` principal/rh) so an alert is never silently
     * dropped just because no direct hierarchy has been configured yet.
     * Never includes the employee themself.
     *
     * @return Collection<int, static>
     */
    public function resolveAlertRecipients(): Collection
    {
        if ($this->manager_id !== null) {
            $directManager = static::query()
                ->where('company_id', $this->company_id)
                ->where('status', 'active')
                ->find($this->manager_id);

            if ($directManager !== null) {
                return collect([$directManager]);
            }
        }

        return static::query()
            ->where('company_id', $this->company_id)
            ->where('role', 'manager')
            ->whereIn('manager_role', ['principal', 'rh'])
            ->where('status', 'active')
            ->where('id', '!=', $this->id)
            ->get();
    }

    /**
     * Route d'accueil suggeree selon le role/sous-role de l'employe.
     */
    public function homeRoute(): string
    {
        if (! $this->isManager()) {
            return 'me.dashboard';
        }

        return match ($this->manager_role) {
            'principal' => 'dashboard',
            'rh' => 'dashboard',
            'comptable' => 'dashboard',
            'marketing' => 'dashboard',
            'dept' => 'dashboard',
            default => 'dashboard',
        };
    }

    /**
     * Ce collaborateur a-t-il un grant explicite pour ce module ?
     *
     * Le `principal` a implicitement TOUT (spec §3.1) — sans ligne en base.
     * Toute autre reponse vient d'un grant explicite du meme tenant
     * (fail-closed : pas de grant → false, le RBAC historique decide seul).
     */
    public function hasModuleGrant(string $moduleKey): bool
    {
        if ($this->isPrincipal()) {
            return true;
        }

        if ($moduleKey === '' || $this->company_id === null) {
            return false;
        }

        return $this->moduleGrants()
            ->where('company_id', $this->company_id)
            ->where('module_key', $moduleKey)
            ->exists();
    }

    /**
     * Cles de modules explicitement accordees a ce collaborateur (contrat de
     * session `/auth/me` — le front compose menu et refus par defaut avec).
     *
     * @return list<string>
     */
    public function grantedModuleKeys(): array
    {
        if ($this->company_id === null) {
            return [];
        }

        /** @var list<string> $keys */
        $keys = $this->moduleGrants()
            ->where('company_id', $this->company_id)
            ->orderBy('module_key')
            ->pluck('module_key')
            ->all();

        return $keys;
    }

    /**
     * Le collaborateur peut-il agir sur CETTE ressource au niveau `$min` ?
     *
     * Regles (issue #7598) :
     *  - `principal` : tout, sans assignation ;
     *  - `rh` : lecture (`view`) seule, sans assignation ;
     *  - assigne : niveau ≥ `$min` ;
     *  - non assigne : refuse **si et seulement si** ce type de ressource est
     *    deja assigne dans l'entreprise. C'est la regle de progressivite :
     *    avant la premiere assignation, le comportement historique (meme
     *    `company_id` + role) est conserve ; des qu'une assignation existe, le
     *    scoping est actif et fail-closed pour les non-assignes du type.
     */
    public function hasResourceAccess(
        string $resourceType,
        int $resourceId,
        string $min = EmployeeResourceAssignment::LEVEL_VIEW,
    ): bool {
        // Exigence inconnue → refus (fail-closed), jamais un « view » implicite.
        if (EmployeeResourceAssignment::levelRank($min) < 0) {
            return false;
        }

        if ($this->isPrincipal()) {
            return true;
        }

        if ($this->isHr() && $min === EmployeeResourceAssignment::LEVEL_VIEW) {
            return true;
        }

        $level = $this->resourceAssignmentLevel($resourceType, $resourceId);
        if ($level !== null) {
            return EmployeeResourceAssignment::satisfies($level, $min);
        }

        return ! $this->isResourceTypeScoped($resourceType);
    }

    /** Niveau assigne a ce collaborateur pour cette ressource, ou null. */
    public function resourceAssignmentLevel(string $resourceType, int $resourceId): ?string
    {
        $assignment = $this->resourceAssignments()
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->first();

        return $assignment?->access_level;
    }

    /**
     * Ce type de ressource est-il deja assigne dans l'entreprise ? Si oui, le
     * scoping est actif : les non-assignes du type sont refuses (fail-closed).
     */
    public function isResourceTypeScoped(string $resourceType): bool
    {
        return EmployeeResourceAssignment::query()
            ->where('company_id', $this->company_id)
            ->where('resource_type', $resourceType)
            ->exists();
    }

    /**
     * Identifiants des ressources de ce type accessibles au niveau `$min`.
     *
     * `null` signifie « aucune restriction » (principal, ou lecture `rh`, ou
     * type pas encore assigne → comportement historique) : c'est different
     * d'une liste VIDE, qui veut dire « scoping actif, acces a rien ». Les
     * appelants (listings, policies R2+) doivent donc distinguer les deux.
     *
     * @return list<int>|null
     */
    public function accessibleResourceIds(
        string $resourceType,
        string $min = EmployeeResourceAssignment::LEVEL_VIEW,
    ): ?array {
        if (EmployeeResourceAssignment::levelRank($min) < 0) {
            return [];
        }

        if ($this->isPrincipal()) {
            return null;
        }

        if ($this->isHr() && $min === EmployeeResourceAssignment::LEVEL_VIEW) {
            return null;
        }

        if (! $this->isResourceTypeScoped($resourceType)) {
            return null;
        }

        $allowed = [];
        foreach ($this->resourceAssignments()->where('resource_type', $resourceType)->get() as $assignment) {
            if (EmployeeResourceAssignment::satisfies($assignment->access_level, $min)) {
                $allowed[] = (int) $assignment->resource_id;
            }
        }

        return $allowed;
    }
}
