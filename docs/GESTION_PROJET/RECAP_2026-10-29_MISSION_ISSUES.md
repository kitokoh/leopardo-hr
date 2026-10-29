# Récapitulatif — mission « implémenter toutes les issues ouvertes » (2026-10-29)

Session du 2026-10-29. Dépôt `Kitokoh/leopardo-hr`. Décisions détaillées :
`docs/GESTION_PROJET/DECISIONS_2026-10-29_MISSION_ISSUES.md`.

## Point de départ

28 éléments ouverts : **16 issues** + **12 pull requests**.

Le diagnostic central, établi avant toute écriture de code : **la majorité des issues
ouvertes n'étaient pas bloquées par du code manquant, mais par des PR vertes non
fusionnées et par des actions humaines hors dépôt.** Traiter le code en premier aurait
été travailler sur des fichiers déjà corrects.

## Ce qui a été fait

### 1. Déblocage collectif — 13 PR fusionnées

Les PR se bloquaient mutuellement. Trois causes racines, traitées dans cet ordre :

| PR | Rôle | Effet |
|---|---|---|
| **#9006** | bump `symfony/mime` v7.4.22 (CVE-2026-45070) | débloque *Backend Security* sur **toutes** les PR |
| **#8999** | garde OpenAPI remis au vert (4 routes Travel + 11 drifts de noms) | débloque **toutes** les fusions |
| **#9008** | `PageModerationAudit` sans `BelongsToCompany` | débloque *Hygiene Guards* sur `main` et 7 PR |
| #9011, #9007, #9009, #9001, #9000, #8993 | contenu (Marketing, Travel, Absence, Attendance) | — |
| **#9010** | Marketing SQL defaults — **rebasé + conflit résolu** | 24/24 tests |
| **#9003** | Travel consentement — **rebasé + PHPStan corrigé** | 13/13 tests |
| **#9012** | registre de décisions (cette session) | — |
| **#9013** | Fuel : 2 tests périmés — **cette session** | 68/68 tests |

**#8997 fermée comme superseded** par #9003 : elle portait 36 fichiers et des commits
déjà mergés ailleurs. Un conflit sur un fichier déjà corrigé sur `main` n'est pas un
conflit de contenu, c'est un empilement.

### 2. Code écrit dans cette session

**#9013 — Fuel : 2 tests périmés (68/68 vert).**
Deux tests inséraient dans des tables que les endpoints ne lisent plus :
`fuel_pumps` au lieu de `fuel_incidents` (FUEL-010), et `fuel_cash_sessions` avec un
statut `pending_review` qui n'existe pas dans `fuel_reconciliation_runs` (FUEL-009).
Le code était correct ; les tests décrivaient un contrat abandonné.

**#9014 — `SelfServiceTrialController` 859 → 470 lignes (fermée pour collision).**
Extraction de `handleSignup` (374 lignes) vers `SubmitTrialSignup`. Fermée parce qu'un
autre agent travaillait sur le **même fichier** (#9016) : deux PR qui réécrivent le même
fichier ne peuvent pas être relues. Le diff est conservé sur la branche et documenté
dans #9014 pour reprise après #9016.

### 3. Issues fermées sur preuve

| Issue | Preuve |
|---|---|
| **#8199** (BOS-012) | `modules:consolidate` + `--apply` + `--json` livrés, 3 suites de tests vertes, `features` jamais réécrit (parité `hasFeature` structurelle) |
| **#7959** (isolation modules) | gardes exécutées : 46/46 et 24/24 arêtes sous budget, 0 nouvel import croisé, `Absence` tranché en façade, routes unifiées |

### 4. Issues documentées comme décidées (non fermées, à dessein)

**9 issues « actions propriétaire »** — #7657, #7994, #7967, #8285, #8191, #8232,
#7924, #7941, #8880. Le volet code est sur `main` ; ce qui bloque est hors dépôt
(secret, paiement, dashboard, signature humaine). Chacune a reçu un commentaire de
décision nominatif avec l'action attendue et le risque associé.

**4 issues de fond** — #8396 (campagne tests), #7962 (controllers obèses), #8210
(webhooks, livré par #9015), #8727 (migration Showcase, brouillon produit), #8448
(KDS offline, découpé en 3 tranches).

## Décisions structurantes

1. **Ne jamais désactiver une garde fail-loud pour « faire vert ».** Couper
   `database-backup.yml` déplacerait la panne du CI vers le jour de la restauration de
   données — le pire moment pour la découvrir.
2. **Un budget, pas une allowlist.** Une allowlist d'imports sans plafond est un stock
   qui ne descend jamais. Le garde de budget force à baisser le plafond quand une arête
   est résorbée.
3. **Unifier la primitive de signature, pas la chaîne signée.** Billing signe
   `{timestamp}.{body}` (Svix), Travel signe le payload canonique. Unifier la chaîne
   aurait cassé tous les consommateurs.
4. **Une PR empilée se remplace, elle ne se rebase pas indéfiniment.**
5. **« Livré dans le code » ≠ « livré en prod ».** Une issue ne se ferme que sur une
   preuve d'exécution.
6. **Vérifier « préexistant » par `git stash`, jamais par supposition.** Deux échecs
   `TrialRestaurantSolutionActivationTest` ont été attribués à `main` après vérification,
   pas avant.

## Points restants

### Actions propriétaire (bloquantes, hors dépôt)

| # | Action | Risque si non fait |
|---|---|---|
| 1 | Bucket S3 + IAM écriture, secrets `BACKUP_S3_*` | **aucune sauvegarde de la prod** |
| 2 | PAT *Administration : Read-only*, secret `BRANCH_PROTECTION_TOKEN` | garde de protection aveugle |
| 3 | Déconnecter le Worker Cloudflare `gestionemploye` du dépôt | check rouge récurrent |
| 4 | Vérifier branche prod + `Ignored Build Step` sur les 2 projets Vercel | correctif #7963 non servi |
| 5 | Moyen de paiement Render + worker starter (~7 $/mois) | jobs paie perdus au spin-down |
| 6 | Snapshot prod → `audit:scheduler-duplicates` | doublons non mesurés |
| 7 | Expert-comptable par marché + signature `VALIDATION_EXPERTE.md` | aucune promesse de conformité permise |
| 8 | Responsable terrain Dakar + tableur externe | pilote non démarrable |
| 9 | Tenants de test + panel bêta Léa | décision GA non fondée |

### Travail de fond (non bloquant)

- **#8396** : suites Attendance / Billing complet / Payroll non encore mesurées.
- **#7962** : `KioskController` (777), `PayrollRunController` (637), `AuthController` (605).
- **#7959** : résorption des 70 arêtes restantes, en commençant par HR↔Payroll.
- **#8448** : tranche 1 (PWA installable) est la prochaine étape naturelle.
- **#8727** : bloquée sur l'inventaire données de production.
- **Nouveau** : `TrialRestaurantSolutionActivationTest` — 2 échecs préexistants
  (200 attendu / 201 reçu sur `/trial/signup` sans solution), à tracer en issue dédiée.

## État de `main`

`main` a reçu 13 fusions squash. Les 4 checks requis (PHPStan Strict, Module Structure
Validator, Frontend ESLint+TS, actionlint) sont la référence de vert ; aucun échec
introduit par cette session n'a été observé. Les gardes d'architecture exécutées
localement sont vertes (isolation modules, budget d'isolation).
