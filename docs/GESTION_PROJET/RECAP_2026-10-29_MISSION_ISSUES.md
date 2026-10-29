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

---

# Session 2 (même journée) — reprise après la première vague

## Point de départ vérifié, pas supposé

`git fetch` + lecture de l'état réel : **12 issues ouvertes**, dont **9 actions
propriétaire** déjà documentées nominativement en session 1. Le travail restant
était donc **3 issues de fond** (#7962, #8210, #8448) plus la vérification que
les volets code de #7994 / #7967 / #8191 étaient bien sur `main`.

Vérification faite : **#7994 §3/§4/§5 et #7967 §2 sont déjà livrés** sur `main`
(`marketplace-deploy.yml` existe, le skip prod pousse un `::error`, la garde
`fronts-vercel` de `deploy-drift-guard.yml` lit les GitHub Deployments,
`APP_KEY: sync: false` dans les deux blueprints). Ce qui reste sur ces deux
issues est **strictement propriétaire** (secrets Vercel, moyen de paiement
Render). #8191 est intégralement propriétaire : l'outillage
`audit:scheduler-duplicates` est livré, il faut une copie des données de prod.

## Ce qui a été livré

| PR | Issue | Contenu |
|---|---|---|
| **#9017** | #8210 | Transport unique des webhooks sortants (BC-14) |
| **#9024** | #8448 | KDS Edge offline-first + endpoint idempotent |
| **#9025** | #7962 | Gardes d'accès nommées dans `PayrollRunController` |

### #8210 — le transport, pas seulement la signature

La session 1 avait unifié la **primitive de signature**. Restait la **livraison
HTTP**, recopiée dans deux producteurs — et les deux copies n'étaient pas
équivalentes :

- `DispatchWebhook` (Billing) : garde anti-SSRF inline, TLS vérifié ;
- `TravelWebhookDispatcher` (Travel) : **`Http::withoutVerifying()`**, aucune
  garde d'hôte.

La seconde est le vrai défaut que ce lot corrige. `withoutVerifying()` désactive
la vérification du certificat : un abonné Travel pouvait enregistrer une URL
`https://` vers un hôte interne (le `StoreTravelWebhookSubscriptionRequest` ne
validait que `url`, pas `NotPrivateUrl`) et recevoir le payload signé — avec, en
prime, un certificat non vérifié, donc un serveur imposteur accepté.

`OutboundWebhookTransport` impose désormais, pour **tous** les producteurs :
`https://` obligatoire, re-résolution DNS juste avant l'appel (TOCTOU), TLS
vérifié, timeout borné. Ce qui reste chez le producteur est ce qui fait partie
de son contrat public : la chaîne signée (Svix vs payload canonique), les
en-têtes de signature, le journal de livraison et la politique de retry.

### #8448 — la propriété qui rend l'offline sûr

Le KDS hors ligne rejoue ses gestes. Un rejeu est inévitable (réponse perdue,
retry, deux tablettes) et ne doit jamais faire avancer une commande deux fois.
L'idempotence est portée par la **contrainte d'unicité** de
`restaurant_kitchen_actions` (`company_id` + `client_action_id`), **pas** par un
`SELECT` préalable : deux rejeux concurrents liraient tous les deux « absent »
avant que l'un des deux n'écrive. Un rejeu répond **200**, pas 409 — c'est le
fonctionnement normal d'un client hors ligne, pas une erreur à montrer au
cuisinier.

### #7962 — la tranche qui ne collisionne pas

`PayrollRunController` recopiait un préambule tenant+manager dans 11 méthodes et
un `catch` métier dans 5. La répétition cachait une incohérence réelle :
`lock`/`unlock` exigeaient `principal`/`comptable` alors que
`cancel`/`regularize` se contentaient de `isManager()`. Deux noms disent
maintenant deux règles (`authorizeManager` / `authorizeApprover`).

## Décision de coordination — la plus importante de cette session

En poussant la tranche #7962, j'ai constaté que **#9019 (autre agent) travaille
déjà sur `AuthController` et `KioskController`**, #9021 sur
`AttendanceController`, #9016 sur `SelfServiceTrialController`.

Ma PR initiale (#9022) embarquait `AuthController` **et** `PayrollRunController`.
**Deux PR qui réécrivent le même fichier ne peuvent pas être relues.** Elle a
donc été **fermée**, avec un commentaire qui conserve les deux constats utiles
pour la revue de #9019 (tronc commun des six gardes Google ; les deux 503
`GOOGLE_OAUTH_NOT_CONFIGURED` / `GOOGLE_OAUTH_UNAVAILABLE` restent distincts,
#5170). La tranche Payroll, sans recouvrement, est repartie seule en **#9025**.

C'est la règle « une PR empilée se remplace, elle ne se rebase pas
indéfiniment » appliquée à une collision entre agents, pas à un empilement.

### Correction : la tranche Auth n'est PAS livrée par #9019

J'avais d'abord justifié la fermeture en écrivant que `AuthController` était
« couvert par #9019 ». **C'était faux**, et `PROTOCOLE_LOTS_MULTI_AGENTS.md`
§4bis (#7581) interdit exactement ça : une clôture « couvert par le lot » est une
**affirmation de contenu**, et quand elle est fausse le correctif disparaît
pendant que le backlog devient vert à tort.

Mesure, pas supposition :

```
$ bash dev-hub/tools/check-pr-subset-of-lot.sh \
    --lot origin/refactor/7962-auth-google-sso \
    --pr refactor/7962-payroll-auth-actions

NON PROUVE : n'est PAS un sous-ensemble du lot.
Fichiers absents du lot (10) : les 4 Actions Google, les 3 services
d'infrastructure, le DTO GoogleSsoResult, PayrollRunAccess et son test.
```

#9019 livre **une seule** action (`AuthenticateGoogleIdentityAction`) et réécrit
`AuthController`. Elle ne contient **ni** les 4 Actions de flux, **ni** les 3
services, **ni** le DTO.

| Élément | État réel |
|---|---|
| `PayrollRunController` + `PayrollRunAccess` + test | **livré en #9025** |
| `AuthController` (le fichier) | **traité par #9019** — bonne PR pour ce fichier |
| 4 Actions Google + 3 services + DTO | **NON livrés** — préservés sur `refactor/7962-payroll-auth-actions` |

**Conséquence : #7962 reste ouverte.** Fermer cette PR reste la bonne décision
(une PR par fichier), mais la tranche Auth doit être reprise **après** fusion de
#9019, par-dessus `AuthController` déjà refactorisé.

## État des issues à la fin de la session 2

| Issue | État | Preuve / blocage |
|---|---|---|
| #8210 | **PR #9017** | transport unique + 5 tests anti-SSRF/TLS |
| #8448 | **PR #9024** | 30 tests front + 5 tests d'idempotence |
| #7962 | **PR #9025** (Payroll) — **reste ouverte** : tranche Auth non livrée | 23 tests Payroll ; mesure §4bis ci-dessus |
| #7994 | **propriétaire** | §3/§4/§5 livrés ; reste les secrets Vercel |
| #7967 | **propriétaire** | §2 livré ; reste le moyen de paiement Render |
| #8191 | **propriétaire** | outillage livré ; reste la copie de prod |
| #8285, #8232, #7924, #7941, #8880, #8727 | **propriétaire / produit** | hors dépôt |

## Ce que cette session n'a pas fait, et pourquoi

Aucune issue n'a été fermée par cette session. Trois raisons, dans l'ordre :

1. **Une PR ouverte n'est pas une issue livrée.** Les trois PR attendent la
   revue et la fusion ; fermer l'issue maintenant rendrait le suivi faux.
2. **Les 9 issues restantes sont propriétaire par nature.** Elles demandent un
   secret, un paiement, une signature ou une donnée de production. Les fermer
   sans l'action reviendrait à effacer le rappel du risque.
3. **#8727 est un brouillon produit** bloqué sur un inventaire de données de
   production, pas sur du code.

## Vérifications exécutées localement

`phpstan-strict.neon` 0 erreur · `pint --test` PASS · `check-layer-purity.sh`
0 violation nouvelle · `check-bounded-context-dependencies.sh` 151 arêtes /
0 import hors contrat · gardes migrations (tenant-schema, basenames,
destructive-down, alter-before-create, sql-quotes) toutes OK · `vitest run`
46 passed · `tsc --noEmit` 0 erreur · `eslint` 0 erreur · `next build` OK.

**Échecs préexistants, vérifiés par `git stash` + `git checkout main`, jamais
par supposition** : `GoogleSsoTwoFactorSchemaTest` (2, fixtures périmées),
`TravelWebhookTest` (4, `name` requis absent des fixtures),
`tests/page.test.tsx` (Node 20 vs `undici` Node 22).
