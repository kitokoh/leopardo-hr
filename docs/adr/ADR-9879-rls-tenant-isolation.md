# ADR-9879 — Activer PostgreSQL RLS comme filet d'isolation tenant

- **Statut** : Accepté (déploiement progressif)
- **Date** : 2026-11-24
- **Contexte de décision** : revue d'architecture (#9879, constat R8)

## Contexte

L'isolation multi-tenant de Leopardo repose sur **deux mécanismes applicatifs** :

1. `search_path` PostgreSQL positionné par `TenantManager` (`shared_tenants,public`) ;
2. un scope Eloquent global avec `TenantContextMissingException` (fail-closed).

Mesure : `grep -rl "ENABLE ROW LEVEL SECURITY\|CREATE POLICY" database/migrations` → **0 fichier**. Aucune barrière au niveau base.

Conséquence : toute requête qui contourne Eloquent — `DB::table()`, SQL brut, un `withoutGlobalScopes()` oublié, un job qui n'a pas posé le contexte — **lit les données de tous les tenants**. Le dépôt a déjà des gardes CI statiques contre les `withoutGlobalScopes()` non justifiés, mais une garde statique ne voit pas le SQL construit dynamiquement.

## Décision

Activer RLS **progressivement**, en commençant par `bulk_payment_claims` :

```sql
ALTER TABLE bulk_payment_claims ENABLE ROW LEVEL SECURITY;
ALTER TABLE bulk_payment_claims FORCE ROW LEVEL SECURITY;

CREATE POLICY bulk_payment_claims_tenant_isolation
ON bulk_payment_claims
USING  (company_id::text = current_setting('app.current_company_id', true))
WITH CHECK (company_id::text = current_setting('app.current_company_id', true));
```

Le `true` de `current_setting(..., true)` (missing_ok) est **essentiel** : si la variable n'est pas positionnée, la comparaison vaut `NULL`, donc **aucune ligne n'est visible**. Oublier de positionner le tenant ne fuit rien — c'est fail-closed, cohérent avec le `TenantContextMissingException` existant.

`FORCE ROW LEVEL SECURITY` est nécessaire car sans lui le **propriétaire** de la table (souvent le rôle applicatif) contourne la politique.

## ⚠️ Prérequis de déploiement — mesuré dans ce dépôt

**Le rôle applicatif `leopardo` est SUPERUSER** (`\du` → `Superuser`). PostgreSQL fait **toujours** bypasser RLS aux superusers, **même avec `FORCE`**.

Tant que l'application se connecte en superuser, la politique existe mais **ne protège pas**. Le test `RlsTenantIsolationTest` en tient compte : il crée un rôle `rls_isolation_probe` non-superuser, exécute la politique, et vérifie depuis ce rôle. Un test qui compterait les lignes en tant qu'utilisateur applicatif passerait au vert sans rien prouver.

**Action requise avant de considérer RLS comme actif en production** : créer un rôle applicatif dédié, `NOSUPERUSER NOBYPASSRLS`, propriétaire distinct des tables (ou `FORCE` + rôle non-propriétaire), et l'utiliser dans `DB_USERNAME`.

## Preuve

`tests/Feature/Security/RlsTenantIsolationTest` :

| Scénario | Attendu | Obtenu |
|---|---|---|
| tenant A interroge la table | 1 ligne (la sienne) | ✅ |
| tenant B interroge la table | 1 ligne (la sienne) | ✅ |
| aucun tenant positionné | 0 ligne (fail-closed) | ✅ |
| tenant B modifie une ligne de A | 0 ligne affectée | ✅ |
| rôle superuser applicatif | 2 lignes (RLS bypassé) | ✅ — documente la limite |

## Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| **Schéma par tenant** (`search_path` seul) | Déjà en place, mais ne protège pas du SQL brut dans le schéma partagé |
| **Base par tenant** | Coût opérationnel et migrations prohibitifs à 42 modules |
| **Base par tenant + RLS** | Redondant : si chaque tenant a sa base, RLS n'apporte rien |
| **Gardes CI uniquement** | Statique, contournable par du SQL dynamique — ne voit pas l'exécution |

## Plan de déploiement

| Étape | Périmètre | Statut |
|---|---|---|
| 1 | `bulk_payment_claims` (table neuve, aucun accès inter-tenant légitime) | ✅ fait |
| 2 | Rôle applicatif non-superuser (`NOSUPERUSER NOBYPASSRLS`) | ⬜ prérequis bloquant |
| 3 | `pay_slips`, `payroll_runs` (données de paie) | ⬜ après inventaire des accès |
| 4 | `employees` | ⬜ après inventaire des accès (jointures cross-schema) |

L'étape 2 est **bloquante** : sans elle, les étapes 3 et 4 donneraient une fausse impression de sécurité.

## Conséquences

**Positives** : filet indépendant du code applicatif ; un `DB::table()` oublié ne fuit plus ; fail-closed par défaut.

**Négatives** : coût de planification sur les tables volumineuses ; nécessite que chaque connexion positionne `app.current_company_id` (sinon **plus rien** n'est lisible) ; impose la séparation rôle-propriétaire / rôle-applicatif.
