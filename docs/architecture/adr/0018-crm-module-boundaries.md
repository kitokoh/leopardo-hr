# ADR — Frontières de dépendances du module CRM client (gardes CI)

- **Statut :** ratifié — implémentation (issue #5745, CRM PRE)
- **Date :** 2026-08-28
- **Périmètre :** API Laravel — module `App\Modules\CRM` (CRM client tenant) vs
  `Platform`/`Marketing` (CRM commercial), `Payroll`, `Accounting`
- **ADR parent :** `docs/architecture/refactoring/ADR-CRM-DUAL-CONTEXTS.md`
  (ADR-CRM-001/002)
- **Issue de référence :** #5745 — « Ajouter les gardes de dépendances entre modules CRM »
- **Garde CI :** `dev-hub/tools/check-crm-boundary-imports.sh`

---

## Contexte

Le CRM client (`App\Modules\CRM`) est un bounded context strictement séparé du
CRM commercial Leopardo (`Platform` + `Marketing`), de la paie (`Payroll`) et
de la comptabilité (`Accounting`). La règle non négociable du programme CRM
(issue #5745) : **aucun accès direct du CRM client aux agrégats du CRM
commercial, de Payroll ou d'Accounting** — les échanges passent par des
contrats (App\Shared) ou des événements, jamais par un import direct des
modèles internes d'un autre contexte.

La garde existante issue #5584 (`check-module-isolation.sh` /
`check-cross-module-imports.sh`) bloque déjà **tout nouvel import croisé**
entre modules distincts (y compris vers le CRM) depuis l'audit 2026-08-26.
Elle ne distingue pas les contextes « voisins » : c'est une garde symétrique.
La présente ADR ajoute une garde **orientée** spécifique au CRM client.

## Décisions

### ADR-CRM-010 — HARD BLOCK : imports interdits depuis `Modules/CRM`

Les quatre cibles suivantes sont **interdites en toutes circonstances** dans
`api/app/Modules/CRM/**` — aucune exemption, aucune allowlist :

| Cible | Raison |
|---|---|
| `App\Modules\Platform\` | Agrégats du CRM commercial Leopardo (pipeline plateforme) — ADR-CRM-001 |
| `App\Modules\Marketing\` | CRM commercial / acquisition (marketing_leads) — ADR-CRM-001 |
| `App\Modules\Payroll\` | Agrégats de paie (interdiction issue #5745) |
| `App\Modules\Accounting\` | Agrégats de comptabilité (interdiction issue #5745) |

### ADR-CRM-011 — Autres imports inter-modules : exemption justifiée obligatoire

Tout autre `use App\Modules\<X>\` depuis `Modules/CRM` (ex. `Notification`,
`Billing`) doit être listé dans `dev-hub/tools/crm-boundary-allowlist.txt`
avec une justification. En l'absence d'entrée, la CI échoue. La préférence
reste un contrat partagé (`App\Shared`) ou un événement.

### ADR-CRM-012 — Imports toujours autorisés

- `App\Core\*` (socle transversal : Tenant, Auth, Feature)
- `App\Shared\*` (kernel partagé : DTOs, Enums, Events, Exceptions, Traits)
- intra-module `App\Modules\CRM\*`

### ADR-CRM-013 — Contrôleurs routés et CODEOWNERS

- Tout contrôleur du module CRM doit être routé dans
  `api/routes/modules/crm.php` (couvert par `check-unrouted-controllers.sh`).
- CODEOWNERS : `/api/app/Modules/CRM/` est couvert (documentaire, la revue
  croisée reste la règle volontaire #1730).

## Conséquences

1. La garde `check-crm-boundary-imports.sh` tourne dans `architecture-check.yml`
   (job module-structure-check) et sur chaque PR touchant le backend.
2. Tant que `api/app/Modules/CRM` n'existe pas sur `main`, la garde est en
   veille (non bloquante) et s'active automatiquement à l'arrivée du module.
3. Toute violation est une erreur CI actionnable, avec le fichier fautif et la
   règle enfreinte (HARD BLOCK vs exemption manquante).

## Options écartées

- **Deptrac/PHPStan custom rules** : dépendance supplémentaire, non nécessaire
  — le scan statique des `use` couvre le contrat (même méthode que #5584).
- **Garde symétrique dédiée** : déjà couverte par #5584 ; inutile de dupliquer.
