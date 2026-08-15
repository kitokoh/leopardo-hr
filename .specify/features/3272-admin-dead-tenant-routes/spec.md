# Spec — #3272 Admin dashboard : routes tenant mortes vs endpoints super-admin réels

## Contexte

`front/admin-dashboard` est la console **super-admin** (`auth:super_admin_api`).
Le guard `requiresTenant` (#2272) redirige les vues tenant vers `/` car un token
super-admin reçoit 401 sur les endpoints tenant. Constat d'audit : `/exports`
et `/fleet` appelaient des endpoints super-admin **réels** mais restaient
bloqués (déjà corrigé sur main), et 10 routes tenant restaient guardées alors
que 3 d'entre elles disposent depuis #2634 d'équivalents `/api/v1/admin/*`.

## Analyse des endpoints (vérifié sur `api/routes/api.php`, groupe `admin`)

| Route vue | Endpoints appelés | Verdict |
|-----------|-------------------|---------|
| `/training` | `/admin/training/courses|sessions|enrollments` | **vivante** → un-gater |
| `/chat` | `/admin/ai/chat`, `/v1/admin/ai/conversations` | **vivante** → un-gater |
| `/webhooks` | `/admin/webhooks`, `/platform/companies` | **vivante** → un-gater |
| `/payroll` | `/v1/payroll-runs`, `/v1/pay-slips`, ... | morte (tenant only) |
| `/leaves` | `/v1/absences`, `/v1/leave-balances`, ... | morte |
| `/contracts` | `/v1/contracts`, `/v1/export/contracts` | morte |
| `/recruitment` | `/v1/recruitment/jobs`, ... | morte |
| `/reports` | `/v1/reports/*` | morte |
| `/predictions` | `/v1/predictions/*` | morte |
| `/audit` | `/v1/audit-logs*` | morte |

## Comportement cible

1. `/training`, `/chat`, `/webhooks` accessibles directement (plus de
   redirection toast) : leurs endpoints super-admin existent.
2. Les 7 vues mortes (routes + entrées Sidebar + CommandPalette + raccourci
   Alt+R + fichiers vue) sont **supprimées** : elles ne pouvaient rendre que
   des écrans vides en 401 — la console cesse de promettre des
   fonctionnalités inexistantes pour un super-admin.
3. Le guard `requiresTenant` est conservé (filet pour futures vues tenant).

## Hors périmètre

- Les routes `/settings/payroll/*` (cotisations, barèmes, taux) ne sont pas
  citées par l'issue et restent inchangées.
- Pas de suppression du guard lui-même.
