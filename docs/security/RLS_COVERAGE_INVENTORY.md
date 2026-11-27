# Inventaire RLS — couverture par lots (#9975)

> **État au 2026-11-27.** Mesuré sur le dépôt, pas estimé.
> Source de vérité machine : `api/app/Console/Commands/TenantRlsStatusCommand.php`
> (`EXPECTED_TABLES`) et `dev-hub/tools/check-rls-coverage-parity.py`.

## Le chiffre, et ce qu'il veut dire

| Mesure | Valeur |
|---|---|
| Tables `shared_tenants` portant `company_id` | **523** |
| Tables couvertes par RLS avant l'audit | **8** (ADR-9879) + 4 (#9924) = **12** |
| Tables couvertes après ce lot | **32** |
| Couverture | **6,1 %** |

**Ce que 6 % ne veut pas dire.** Ce n'est pas « 94 % des données sont
exposées ». L'isolation réelle repose sur deux filets : le scope Eloquent
`BelongsToCompany` (fail-closed sur la surface API tenant, #3727) et RLS. Le
premier couvre la quasi-totalité des modèles ; RLS est le **filet de dernier
recours**, celui qui attrape un `DB::table()` oublié, un job de console, une
requête brute.

**Ce que 6 % veut dire.** Sur 511 tables, si le scope applicatif est contourné
— un `withoutGlobalScopes()` mal justifié, un rapport SQL écrit à la main, un
job qui itère les tenants — **rien** ne rattrape la fuite. C'est exactement le
scénario que l'ADR-9879 existe pour couvrir.

## Pourquoi couvrir par lots et pas d'un coup

L'ADR exige un **inventaire des accès légitimes avant chaque politique**. Une
politique posée sans cet inventaire est pire que l'absence de politique : elle
donne l'impression d'une protection qui n'existe pas, et elle casse
silencieusement les accès cross-tenant légitimes (référentiels partagés,
surfaces plateforme).

Ajouter 500 politiques en une migration serait donc une faute, pas un
raccourci. Le critère de sélection d'un lot est unique : **une fuite
inter-tenant y est un incident RGPD ou financier, pas une gêne.**

## Lot 6 — livré (#9975)

20 tables, `2026_11_27_000100_9975_enable_rls_on_sensitive_tables.php`.

| Domaine | Tables | `company_id` | Accès cross-tenant légitime |
|---|---|---|---|
| **Paie** | `employee_loans`, `payment_batches`, `payment_confirmations` | NOT NULL | aucun |
| | `pay_slip_lines` | **nullable** → backfill depuis `pay_slips` | aucun |
| **Facturation** | `invoices`, `billing_collections` | NOT NULL | aucun |
| **Comptabilité** | `accounting_documents`, `accounting_document_lines`, `bank_statements`, `bank_statement_lines` | NOT NULL | `BankStatementImportService` : `withoutGlobalScopes()` **mais filtre explicitement par `company_id`** → politique satisfaite |
| **Santé** | `health_patients`, `health_consultations`, `health_prescriptions`, `health_prescription_items`, `health_invoices` | NOT NULL | aucun |
| **Identité / RH** | `employee_documents`, `employee_departures`, `contract_amendments`, `leave_accruals` | NOT NULL | aucun |
| | `leave_balance_logs` | **nullable** → backfill depuis `employees` | aucun |

**Les deux colonnes nullables.** Sous la politique, une ligne à `NULL` devient
**invisible** (`NULL = 'x'` vaut `NULL`, pas `TRUE`). La migration backfill
depuis la table parente **avant** de poser la politique, puis **échoue
bruyamment** s'il reste un `NULL`. Une panne au déploiement vaut mieux qu'une
disparition silencieuse en production — c'est la leçon de #9897 sur
`employees`.

**Vérification réelle.** Les 20 tables ont été créées dans un PostgreSQL 16,
peuplées sur deux tenants, la migration exécutée, puis interrogées sous un rôle
`NOSUPERUSER NOBYPASSRLS` : **20/20 renvoient 0 ligne sans tenant publié**, et
le backfill ramène 0 `NULL`. Ce n'est pas une relecture de code.

## Lots suivants — priorisés

| Lot | Périmètre | Pourquoi ce rang | Bloquant |
|---|---|---|---|
| **7** | `documents`, `document_shares`, `document_versions` | pièces jointes nominatives (contrats, bulletins) ; partage public = surface d'exfiltration | non |
| **8** | `expenses`, `expense_reports`, `expense_items` | notes de frais : montants + justificatifs | non |
| **9** | `recruitment_*` (candidats, CV, entretiens) | données personnelles de **non-salariés** — RGPD le plus strict | non |
| **10** | `crm_*` (hors `crm_automation_states`, voir ci-dessous) | contacts prospects, consentements | non |
| **11** | `travel_*`, `retail_*`, `restaurant_*` | verticales métier, volume élevé | non |
| **12** | reste des 491 tables | couverture complète | non |

**Ordre imposé par le risque, pas par la facilité.** Un lot ne démarre qu'après
inventaire des accès légitimes de ses tables — c'est la règle de l'ADR, et
c'est ce qui a évité de casser `BankStatementImportService` au lot 6.

## Cas particuliers qualifiés (ne pas couvrir naïvement)

Ces tables portent `company_id` mais une politique uniforme serait **fausse**.
Elles restent hors `EXPECTED_TABLES`, avec le motif ci-dessous.

| Table | Motif |
|---|---|
| `public_holidays` | `company_id = NULL` = jour férié **national**, lisible par tous les tenants. Politique à sémantique `IS NULL OR …` — décision séparée (déjà exclue par l'ADR). |
| `tax_rate_changes` | idem : lignes `NULL` = référentiel légal national. |
| `crm_automation_states` | `company_id` est la **clé primaire** ; le modèle n'a pas le trait et toutes les écritures passent par `currentCompany()->id`. Candidat au lot 10, mais la politique doit être testée contre `AutomationEngine` (écrit en `updateOrCreate` sur la PK). |
| `edge_nodes` | `company_id` **nullable** (NULL en mode schéma isolé) et les routes machine (`/edge-node/{id}/push|pull|heartbeat`) s'authentifient par `edge_token` **pré-tenant**, sans middleware tenant. Une politique uniforme casserait la synchronisation Edge. Nécessite une politique dédiée + publication du tenant depuis le token. |
| `platform_*`, `partners`, `partner_referrals`, `commissions`, `travel_customer_accounts` | schéma `public`, hors `shared_tenants` : RLS ne s'y applique pas de la même façon. Leur isolation est applicative (et vérifiée : `SupportTicketController` fait `ensureCompanyOwnsTicket`). |

## Ce qui reste faux tant que #9974 n'est pas basculé

**Toutes** ces politiques sont **inertes** en production tant que
`DB_USERNAME` est le rôle SUPERUSER : PostgreSQL fait toujours bypasser RLS
aux superusers, `FORCE` compris. Le lot 6 ne protège donc rien **aujourd'hui**.
Il rend la bascule possible sans nouvelle migration, et il est compté par
`tenant:rls-status`.

L'ordre correct est : **#9974 (bascule) puis #9975 (couverture)**. Le gate
`RLS_GATE_STRICT_COVERAGE` permet de séparer les deux : basculer d'abord, puis
exiger la couverture lot par lot.
