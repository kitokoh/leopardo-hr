# Audit d'architecture — Leopardo (`kitokoh/leopardo-hr`)

**Date** : 2026-11-26 · **Branche** : `fix/audit-architecture-2026-11` · **PR** : #9984
**Périmètre** : dépôt complet (API Laravel, 5 surfaces front, 89 workflows CI, 1 918 documents)
**Question posée** : les bases sont-elles solides pour une solution sécurisée, propre, capable de supporter une évolution, et qui répond aux objectifs fixés ainsi qu'à des besoins réels du marché ?

---

## 1. Verdict

**Oui, avec deux réserves sérieuses.**

Ce dépôt n'est pas un prototype qui a grossi. C'est un système avec une **discipline d'ingénierie rare** : 42 bounded contexts déclarés et vérifiés par une matrice de dépendances versionnée, 1 533 fichiers de tests, 89 workflows dont 6 checks requis au merge, des ADR numérotés, des gardes de parité doc↔code, et une culture explicite du post-mortem (les commentaires de code citent les issues qui ont motivé chaque décision).

Les deux réserves :

1. **L'isolation multi-tenant est annoncée mais pas effective.** Les politiques RLS existent sur 8 tables, mais le rôle applicatif est superuser — PostgreSQL fait bypasser RLS aux superusers même avec `FORCE`. Sur 523 tables portant `company_id`, l'isolation réelle repose sur une seule couche applicative, contournable par 246 appels `withoutGlobalScope()`.
2. **La fiabilité opérationnelle n'est pas au niveau du produit vendu.** Le worker de queue et le scheduler tournent dans le conteneur web d'un plan gratuit qui s'endort. Sur un produit qui vend de la paie et de la facturation, c'est un défaut fonctionnel silencieux.

Le reste est de la dette **gérable et déjà instrumentée** : couplage inter-modules élevé, baseline PHPStan de 1 716 erreurs, saturation CI, documentation pléthorique.

**Note par axe** (sur 5) :

| Axe | Note | Justification |
|---|---|---|
| Sécurité | **2,5** | Excellente discipline (secrets, CORS, redaction PII, 2FA, audit) mais RLS inerte et revue humaine absente |
| Architecture | **3,5** | DDD réel et vérifié, mais 155 arêtes inter-contextes et fichiers de 30-40 Ko |
| Testabilité | **4** | 1 533 fichiers, coverage gate à 65 %, tests de non-régression par issue |
| Observabilité | **3** | Sentry + request_id + redaction, mais aucun SLO mesuré |
| CI/CD | **3** | 6 checks requis, gardes nombreuses, mais 89 workflows et saturation documentée |
| Maintenabilité | **3** | Conventions fortes, mais 1 918 docs et baseline PHPStan permanente |
| Adéquation au besoin | **4** | Le positionnement « suite métier pour entreprises de terrain » est cohérent avec le code |

---

## 2. Le besoin réel du produit

### 2.1 Ce que le produit est

Le dépôt a tranché la question lui-même (`docs/REFERENTIEL_PRODUIT/POSITIONNEMENT_SUITE_METIER.md`, décision P03 / #7428) :

> « Leopardo est la suite métier des entreprises de terrain — RH & paie, pointage, absences, CRM, comptabilité et opérations, sur web, mobile et bornes. »

Ce n'est **pas** un SaaS RH. C'est une suite qui adresse un besoin précis : les entreprises qui coordonnent des équipes **sur plusieurs sites physiques**, avec des contraintes que les SaaS RH classiques ne couvrent pas.

### 2.2 Le besoin réel, déduit du code

En lisant les modules et non le marketing, le besoin se formule ainsi :

| Besoin réel | Preuve dans le code | Criticité |
|---|---|---|
| **Pointage fiable hors bureau** | `Attendance` + `KioskController` (30 Ko) + intégration ZKTeco + `EdgeSync` (mode hors-ligne) | Critique |
| **Paie multi-pays correcte** | `Payroll` + `tax_slabs` partagés + `PayrollRunController` (23 Ko) + règles par pays | Critique |
| **Isolation stricte entre clients** | 523 tables `company_id`, `BelongsToCompany`, ADR-9879 RLS | Critique |
| **Traçabilité opposable** | `audit_logs`, `module_request_id`, journalisation des mouvements de stock | Critique |
| **Fonctionnement en connectivité dégradée** | `web-offline`, `EdgeSync`, kiosque | Majeur |
| **Extension verticale sans fork** | `Core/Solutions`, feature flags, 42 modules activables | Majeur |
| **Conformité RGPD** | `PiiRedactionProcessor`, `inventory-company-id-tables`, purge `accounting:purge-expired-shares` | Majeur |

### 2.3 Les objectifs que l'architecture doit servir

Trois objectifs se déduisent de ce besoin, et c'est contre eux que l'architecture doit être jugée :

- **O1 — Confiance** : un client doit pouvoir confier sa paie et ses données de santé sans risque de fuite inter-tenant ni de calcul faux.
- **O2 — Évolution** : ajouter un vertical (restaurant, école, pharmacie) ne doit pas toucher au cœur.
- **O3 — Disponibilité opérationnelle** : les traitements planifiés (paie, facturation, relances) doivent s'exécuter, même sans trafic.

---

## 3. Écarts entre l'architecture et le besoin

| Objectif | État réel | Écart |
|---|---|---|
| **O1 Confiance** | RLS posé sur 8/523 tables, **bypassé** par le rôle superuser ; isolation réelle = 1 couche applicative ; 0 revue obligatoire au merge | **Écart critique** : la promesse « Secure tenant isolation » du README n'est pas tenue au niveau base de données |
| **O2 Évolution** | 42 modules, matrice de 155 arêtes vérifiée, `Module Structure Validator` requis | **Écart modéré** : la frontière est une convention de dossiers, pas une frontière d'exécution |
| **O3 Disponibilité** | Queue + scheduler dans le conteneur web, plan `free`, spin-down documenté | **Écart majeur** : les traitements planifiés s'arrêtent |

---

## 4. Points forts (à ne pas casser)

Ces éléments sont **au-dessus de la moyenne de l'industrie** et l'audit les protège explicitement :

1. **La matrice de dépendances inter-contextes est versionnée et vérifiée.** `check-bounded-context-dependencies.sh` valide 155 arêtes contre `dev-hub/governance/`. La plupart des monolithes modulaires n'ont même pas cette mesure.
2. **Chaque garde a un `--self-test`.** Une garde cassée ne peut pas passer pour un vert. C'est une pratique que peu d'équipes appliquent.
3. **Les commentaires de code citent l'issue qui a motivé la décision.** On peut reconstituer le *pourquoi*, pas seulement le *quoi*. C'est la différence entre un dépôt maintenable et un dépôt archéologique.
4. **La sécurité applicative est sérieuse** : `cors_env_origin()` rejette wildcard/http/vide ; `PiiRedactionProcessor` couvre les tableaux imbriqués et les motifs `clé=valeur` ; tokens Sanctum à durée de vie différenciée (12 h session / 30 j appareil enrôlé) ; 2FA ; `Secret Scan` et `CodeQL` en CI.
5. **La documentation produit est décidée, pas subie.** Le positionnement P03 est un document de décision avec ses alternatives rejetées et ses conséquences assumées.
6. **Le dépôt documente ses propres anti-patterns.** `#7480` (« un check rouge permanent ne signale plus rien »), `#3545`, `#2011` — la leçon est écrite et réutilisée.
7. **La couverture est mesurée et gardée** : 1 533 fichiers de tests, `coverage-gate.yml` à 65 %, avec un fast-path documenté pour ne pas affamer les checks requis.

---

## 5. Faiblesses classées par criticité

### 5.1 CRITIQUE

#### C1 — RLS posé mais inerte : le rôle applicatif est superuser

**Constat.** L'ADR-9879 a posé `ENABLE` + `FORCE ROW LEVEL SECURITY` et une politique sur 8 tables. Mais PostgreSQL fait **toujours** bypasser RLS aux superusers, `FORCE` compris. Tant que `DB_USERNAME` est le rôle propriétaire, ces politiques ne filtrent rien. Le dépôt le dit lui-même dans `TenantRlsStatusCommand` : « RLS est annoncé mais BYPASSÉ — c'est l'état actuel du dépôt ».

**Aggravant.** `php artisan tenant:rls-status` sait rendre ce verdict (code 1 = bypassé) et **n'est branché dans aucun workflow ni dans `docker-entrypoint.sh`**. La seule sonde capable de dire la vérité n'est jamais exécutée.

**Pourquoi critique.** Une politique « posée mais inopérante » est le pire état : elle donne l'impression d'une isolation qui n'existe pas. L'isolation réelle repose entièrement sur le global scope applicatif — une seule couche, sans filet.

**Issue** : #9974

#### C2 — 523 tables portent `company_id`, 8 sont couvertes par RLS

**Constat.** `inventory-company-id-tables.sh` mesure 523 tables `shared_tenants` avec `company_id`. L'ADR-9879 en couvre 8. Les 515 restantes dépendent du seul `BelongsToCompany`, contournable par 246 `withoutGlobalScope()` (baseline #7960) et 11 modèles encore `legacy_pending_review`.

**Pourquoi critique.** Le produit est vendu sur « Secure tenant isolation ». Une fuite sur des données de paie ou d'absence est un incident RGPD et un incident de confiance irréversible.

**Issue** : #9975

### 5.2 MAJEUR

#### M1 — Queue et scheduler dans le conteneur web, perdus au spin-down

`render.yaml` documente honnêtement : worker commenté (billing), « la queue ET le scheduler tournent dans le conteneur web — perdus à chaque spin-down free-tier ». Un job de paie mis en queue avant l'endormissement ne tourne pas. Une facture mensuelle ne part pas. **Issue** : #9976

#### M2 — 42 modules, 452k lignes, 155 arêtes inter-contextes

La discipline est réelle (matrice vérifiée, validator requis). Mais 155 arêtes pour 42 contextes, c'est ~3,7 dépendances sortantes par module : le bounded context est une convention de dossiers plus qu'une frontière d'exécution. Plusieurs fichiers dépassent 30 Ko (`ConversationService` 39 Ko, `PlatformCompanyHealthService` 36 Ko, `AuthService` 31 Ko). **Issue** : #9977

#### M3 — 1 716 erreurs PHPStan en baseline

1 072 en mode strict, 644 en standard, 6 modules. `PHPStan — Strict (level 8)` est requis au merge, mais valider contre 1 716 erreurs ne garantit qu'une chose : aucune nouvelle erreur. Sur du code de paie et de comptabilité, c'est du typage non vérifié là où une erreur coûte de l'argent. **Issue** : #9978

#### M4 — 89 workflows : la saturation CI est un risque de disponibilité

Le dépôt documente lui-même : « 179 runs créés/h pour 76 terminés/h ; file `queued` 99 → 140 en 30 min », et un incident où les checks requis étaient **absents** (pas rouges) parce que le job amont n'obtenait pas de runner — avec `enforce_admins: true`, aucune PR ne pouvait être mergée. **Issue** : #9979

#### M5 — Aucune revue obligatoire au merge

`required_approving_review_count: 0`. `enforce_admins: true` et 6 checks protègent du merge accidentel, pas du merge délibéré. Sur un produit qui manipule paie, santé et facturation, l'absence de seconde paire d'yeux est un risque de sécurité. Le token de cette session (accès `admin` complet) en est la démonstration. **Issue** : #9980

#### M6 — 1 918 fichiers Markdown pour 452k lignes de code

Une partie est excellente (ADR, référentiel produit, runbooks, gardes de parité). Mais le volume crée une **source de vérité concurrente** : l'audit a trouvé la preuve — `BRANCH_PROTECTION_REQUIRED.md` et le canonique déclaraient 4 checks requis quand la réalité en avait 6, et la garde était rouge en permanence. La doc n'était pas fausse par négligence : elle l'était parce qu'il y a trop de documents pour qu'un humain les tienne tous à jour. **Issue** : #9981

### 5.3 MINEUR

#### m1 — 44 variables `.env.example` orphelines

Dont cinq `RATE_LIMIT_PLAN_*` documentant des plans **qui n'existent pas** dans `PlanCode` (`free`, `pilot`, `operations`, `enterprise` seulement), `CORS_ALLOWED_ORIGINS` (le code lit `CORS_EXTRA_ORIGIN`), et `CATALOG_CURRENCIES`/`CATALOG_UNITS` dont la clé de config n'existait nulle part — la sur-enrichissement de la spec §8 était morte. **Issue** : #9982 — **corrigé dans cette branche**

#### m2 — Aucun SLO défini

L'observabilité est réelle (Sentry, `request_id`, redaction PII, `monitor:slow-queries`, sondes) mais aucun engagement mesuré. On ne distingue pas un système sain d'un système vivant. **Issue** : #9983 — **corrigé dans cette branche**

---

## 6. Risques de sécurité

| Risque | Vecteur | Impact | Mitigation actuelle | État |
|---|---|---|---|---|
| **Fuite inter-tenant** | `withoutGlobalScope()` oublié, RLS bypassé | Divulgation de paie/santé, incident RGPD | Global scope applicatif seul | ❌ Insuffisant |
| **Merge malveillant** | Compte compromis, 0 revue requise | Backdoor passant les checks | `enforce_admins` + 6 checks | ⚠️ Partiel |
| **Seeding de démo en prod** | `env()` après `config:cache` | Comptes de démo avec mots de passe connus | Gardes en `env()` — **inopérantes** | ✅ **Corrigé** |
| **Élargissement CORS** | Valeur d'env erronée (`*`, `http://`) | Vol de session cross-origin | `cors_env_origin()` | ✅ Bon |
| **Fuite de PII dans les logs** | Log d'un objet complet | RGPD | `PiiRedactionProcessor` | ✅ Bon |
| **Token à privilège long** | Token `admin` de session | Compromission totale | Aucune limite de durée visible | ⚠️ À traiter |

**Le risque le plus grave n'est pas technique, il est de gouvernance** : un seul humain peut fusionner n'importe quoi, et l'isolation multi-tenant n'a pas de filet base de données.

---

## 7. Risques de scalabilité

| Risque | Seuil de rupture | Signal actuel |
|---|---|---|
| **Scheduler/queue non fiable** | Immédiat (spin-down) | Documenté dans `render.yaml` |
| **CPU partagé web + jobs** | Pic de jobs | `WEB_QUEUE_DRAIN` dans le conteneur web |
| **Requêtes sans index sur 523 tables** | Volume tenant élevé | Index posés au cas par cas, pas de revue systématique |
| **Couplage inter-modules** | Chaque évolution | 155 arêtes, rayon d'impact non borné |
| **Saturation CI** | Nombre de PR | 89 workflows, file `queued` documentée |

**Point positif** : le schéma est multi-tenant par `company_id` (shared schema), ce qui scale horizontalement en lecture avec des réplicas. Le choix est cohérent avec le marché visé (PME multi-sites, pas des milliers de tenants).

---

## 8. Risques de maintenabilité

| Risque | Mesure | Seuil d'alerte |
|---|---|---|
| **Baseline PHPStan permanente** | 1 716 erreurs | Ne décroît pas |
| **Documentation pléthorique** | 1 918 `.md` | 1 doc / 235 lignes de code |
| **Fichiers trop gros** | 5 fichiers > 25 Ko | Responsabilités mélangées |
| **Ratio fix/feat** | Suivi par `fix-feat-ratio-guard.yml` | Cible ≤ 2,5 |
| **17 155 commits** | Historique très dense | Coût de `git blame` élevé |

**Point positif majeur** : les conventions sont écrites et gardées (`CONVENTIONS.md`, `Module Structure Validator`, `check-docs-hygiene.sh`). Un nouveau développeur a un chemin balisé — c'est rare et précieux.

---

## 9. Recommandations priorisées

### P0 — Avant toute nouvelle fonctionnalité

1. **Rendre RLS effectif** (#9974) : créer le rôle `leopardo_app` (`NOSUPERUSER NOBYPASSRLS`), basculer `DB_USERNAME`, brancher `tenant:rls-status --strict` dans le déploiement avec échec bloquant en prod.
2. **Couvrir les 50 tables les plus sensibles** (#9975) : paie, santé, identité, documents. Suivre le modèle de #9924 (inventaire des accès légitimes AVANT la politique).
3. **Sortir queue et scheduler du conteneur web** (#9976) : worker dédié, puis `WEB_QUEUE_DRAIN=false` et `WEB_SCHEDULER_LOOP=false`.

### P1 — Dans le trimestre

4. **Revue obligatoire sur les chemins sensibles** (#9980) : ruleset GitHub sur `Core/Auth`, `Payroll`, `Billing`, `migrations`, `.github/workflows` + `CODEOWNERS`.
5. **Budget PHPStan décroissant** (#9978) : rendre `phpstan-baseline-budget.json` bloquant, priorité paie/compta/facturation.
6. **Réduire la matrice de dépendances de 20 %** (#9977) : identifier les 5 modules les plus importés, remplacer les imports directs par des contrats/événements.
7. **Consolider les workflows CI** (#9979) : regrouper les mono-jobs par surface comme `guards-fast.yml` l'a fait (-71 % sur dix gardes).

### P2 — Continu

8. **Assainir la documentation** (#9981) : classer les 1 918 `.md` en vivant/archivé/obsolète, générer les listes machine-vérifiables depuis le code.
9. **Mesurer les SLO** (#9983) : job d'agrégation des logs structurés → table `slo_snapshots` → tableau de bord.
10. **Maintenir les gardes ajoutées** : les 4 nouvelles gardes de cette branche doivent rester vertes ; toute dérive est désormais détectée au merge.

---

## 10. Ce qui a été livré dans cette branche

| Correction | Type | Vérification |
|---|---|---|
| Gardes de seeding lisant `config()` au lieu de `env()` | Critique | 11 appels détectés sur `main`, 0 sur la branche |
| `api/config/deployment.php` (tier, verrous, super-admin) | Critique | `php -l` OK, 5 seeders migrés |
| Clés de config dupliquées supprimées | Majeur | `check-config-duplicate-keys.py` vert |
| 44 variables `.env.example` orphelines traitées | Mineur | `check-env-example-parity.py` vert |
| `config/catalog.php` : devises/unités branchées | Mineur | testé avec et sans env |
| `RestaurantStockService` : clé inexistante corrigée | Mineur | `php -l` OK, marqué déprécié |
| `docs/architecture/SLO.md` : 3 SLO + budget d'erreur | Mineur | état de mesure documenté honnêtement |
| 4 gardes CI avec `--self-test` | Majeur | chacune échoue sur dérive injectée |

**Non livré, volontairement** : les corrections P0 (RLS, worker) demandent un accès infrastructure (rôle PostgreSQL, plan Render) qui n'est pas dans le périmètre d'une branche de code. Elles sont spécifiées en issues avec critères d'acceptation.

---

## 11. Conclusion

Les bases sont **solides sur la discipline** et **fragiles sur deux garanties précises**.

La discipline est réelle et au-dessus de la moyenne : matrice de dépendances vérifiée, gardes auto-testées, ADR, post-mortems cités dans le code, couverture mesurée. Ce dépôt sait se gouverner.

Les deux fragilités sont nommables et corrigeables :

- **l'isolation multi-tenant est une promesse sans filet** — RLS posé mais bypassé, 515 tables non couvertes, une seule couche applicative ;
- **la fiabilité opérationnelle n'est pas au niveau du produit** — paie et facturation dépendent d'un scheduler qui s'endort.

Aucune des deux ne demande une réécriture. Les deux demandent une **décision d'infrastructure** (rôle PostgreSQL, worker dédié) et une **décision de gouvernance** (revue obligatoire). Une fois ces quatre décisions prises, l'architecture actuelle supporte l'évolution visée : les 42 modules, la matrice de dépendances et les feature flags sont déjà le bon outil pour ajouter des verticaux sans toucher au cœur.

Le risque principal n'est donc pas technique. C'est de **croire que la sécurité est faite parce qu'elle est écrite** — l'ADR-9879 existe, les politiques existent, la sonde existe. Il manque l'exécution de la sonde et le rôle qui rend les politiques effectives.
