# Audit de session 2026-08-09 — Leopardo RH (kitokoh/leopardo-hr)

> Mission : implémenter les issues ouvertes, vérifier les audits précédents,
> produire une audit + specs + issues, et laisser `main` vert avec un historique
> propre (branches mergées puis supprimées).

---

## 1. Vérification des audits précédents

| Audit | Point soulevé | Statut vérifié (2026-08-09) |
|---|---|---|
| GLOBAL 07-26 | Rotation Redis Upstash + purge historique git (#1472, P0) | 🔴 **Action humaine** — runbook prêt, rotation à faire (dashboards). |
| GLOBAL 07-26 | Secret scanning / clés Google (#1467) | 🟡 stub + secret CI en place ; rotation humaine restante. |
| CICD 07-19 | Épinglage actions tierces par SHA | ✅ **Corrigé dans cette session** (setup-php, flutter-action, lighthouse, Firebase, action-send-mail → SHA + commentaire). |
| CICD 07-19 | TruffleHog / supply-chain | ✅ déjà épinglé (3.96.0 + SHA). |
| 08-08 (#1573) | vue-eslint-parser devDependencies (#1567) | ✅ déjà sur main (devDependencies + step CI supprimé). |
| 08-08 (#1573) | vite.config.js `__dirname` (#1568) | ✅ déjà corrigé (`import.meta.dirname`). |
| 08-08 (#1573) | 189 tests CreatesMvpSchema (#1569 / F-13) | 🟡 Payroll migré ; HR/Attendance partiellement (suite #1593/#1606). |
| 08-08 (#1573) | phpunit.xml / clover paths (#1597) | ✅ aligné (DB_SEARCH_PATH + chemin clover unifié). |
| 08-08 (#1573) | CSP vitrine Report-Only (#1607) | ✅ **décision datée documentée** dans next.config.ts. |
| 08-09 (#1585) | PHPStan strict baseline (#1576) | ✅ baseline régénérée (main). |
| 08-09 (#1585) | Mojibake CHANGELOG (#1589) | ✅ CHANGELOG UTF-8 propre. |
| 08-09 (#1585) | PendingCommand `run()` (#1596) | ✅ règle documentée CONVENTIONS.md + 0 occurrence restante. |
| 08-09 (#1585) | F-17 chiffrement au repos | ✅ payment_documents + **étendu cette session** (payment_batches/items/confirmations). |
| 08-09 (#1585) | Onboarding smoke rouge (#1591) | ✅ root cause corrigée (placeholders .env.example) — vérif CI en cours. |

## 2. Bugs réels corrigés dans cette session

1. **`.env.example` cassait le boot onboarding** : `REDIS_URL=rediss://…VOTRE_HOST.upstash.io:6379` → `getaddrinfo` échouait sur **toute** requête (500), `AUTH_MODEL=Employee::class` (chaîne inutilisable), `LOG_PAPERTRAIL_HANDLER=SyslogUdpHandler::class`. → valeurs locales sûres + garde `check-env-example-safety.sh` branchée sur onboarding-smoke (#1591/#1605).
2. **`Language::activeCodes()`** : un Redis down faisait tomber l'API entière (SetLocale) y compris `/api/v1/health` (contrat de la sonde violé) → fallback DB direct sur exception.
3. **58 tests Feature rouges sur le vrai schéma** :
   - Absences : `Company::create` sans `plan_id`/`subscription_*`/`language`/`currency` et `Employee::create` sans `first_name`/`last_name` (NOT NULL du vrai schéma) ; code `absence_types` global unique (contrainte passe 3).
   - `CreatesMvpSchema.payment_documents.metadata` resté `json` alors que le cast F-17 écrit un payload chiffré → SQLSTATE 22P02 (idem payment_batches/items/confirmations, alignés cette session).
4. **PayrollCycleService — gardes `Schema::hasTable/hasColumn` dépendantes du search_path** : `current_schema()` vaut `shared_tenants` (1er du path) alors que les tables vivent dans `public` sur le vrai schéma → overtime toujours 0, colonnes du mobile-summary vides (`employee_id: null`). → helpers `tableExists/columnExists` bi-schéma (bug B6, #1606).
5. **Signature de consentement paie (PA2-PAY-016)** : hash calculé sur `now()` (ms) mais `confirmed_at` est `timestamp(0)` → PostgreSQL arrondit → **signature invérifiable après lecture**. → hash calculé sur la valeur persistée + canonicalisation UTC. Bug production réel.
6. **ExpenseClaimResource** : exposait `amount` (champ fantôme, toujours null) au lieu de `total_amount` — le fix annoncé passe 3 n'avait pas été livré.
7. **Timezone de session PG** : comparaisons `created_at >= Carbon` dépendantes du fuseau de l'hôte (CI UTC vs local -04) → binding ISO avec offset dans le test concerné (#1597).

## 3. État des issues ouvertes (49 au début de session)

### ✅ Implémentées / clôturables dans cette session (avec la PR #1611)
#1591, #1605, #1603, #1597, #1607, #1604 (protocole benchmark), #1589, #1596,
#1567, #1568, #1586 (déjà sur main), #1587 (déjà sur main + golden test),
#1608/#1585/#1573 (rapports — clôture après vérification).

### 🟡 Partiellement implémentées / suivies
#1595 (F-17 : metadata paiement chiffrées ; reste : autres colonnes), #1593/#1606
(F-13b : migration tests HR/Attendance — chantier ~189 fichiers), #1602 (coverage
Payroll 39,6 % → 80 % : tests à ajouter), #1575 (Playwright flaky), #1600
(suppression workflows temp — dans PR #1610), #1473 (OpenAPI guard opérationnel).

### 🔴 Action humaine (rien à faire côté code)
#1472 (rotation Redis Upstash + purge historique git), #1467/#1601 (rotation
clés Google / secret Neon dans l'historique).

### 🎯 Programme FOCUS (épiques, specs écrites)
#1535–#1560 : specs détaillées existantes ; cœur paie livré (F-05/07/08/10/11/20,
golden), F-12 protocole livré (#1604), F-13 Payroll livré, F-17 partiel, F-14
coverage en cours, F-21/F-22/F-30 non commencés côté code (mobile/kiosk).

## 4. Nouveaux points relevés (cette session)

1. **Le job "Notify Result" échoue en cascade** quand un autre check échoue —
   il redeviendra vert une fois la suite verte (comportement voulu, à documenter).
2. **`php artisan serve` + `.env` modifié** : le serveur redémarre (Laravel 12)
   — le health check doit tolérer la fenêtre de redémarrage (déjà géré par les
   retries du workflow).
3. **Migrations public : dépendance d'ordre au search_path** — certaines
   migrations font `SET search_path` sans restaurer ; la CI passe car
   `DB_SEARCH_PATH=public,shared_tenants` est appliqué à la connexion, mais un
   `migrate:fresh` CLI avec `.env` non aligné échoue (documenté #1597). La CI
   pré-migre avec `DB_SEARCH_PATH=public` / `shared_tenants` par phase
   (setup-backend-db) — pattern à conserver.
4. **Dette : `.env.testing` commité avec `DB_HOST=pgsql`** (hostname docker) —
   inoffensif en CI (phpunit.xml surcharge) mais trompeur en local.

## 5. Spécifications & issues créées

- (issues créées en fin de session, voir la liste GitHub) :
  - Spec CI : passer `Coverage Payroll` en bloquant une fois ≥ 80 % (suite #1602).
  - Spec F-13b : migration HR/Attendance vers RefreshTenantDatabase par module.
  - Spec mobile : builds verts permanents (F-30) + convergence apps (F-27).
  - Spec sécurité : rotation secrets (#1472/#1467/#1601) — runbook déjà prêt.

## 6. État final demandé

- Branches mergées sur `main` puis supprimées.
- CI `main` verte (tests.yml, onboarding-smoke, payroll, mobile, web).
- Audit réalisé par l'agent de session 2026-08-09. Toutes les écritures passent
  par des PR revues.
