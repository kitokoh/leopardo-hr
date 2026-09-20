# AGENTS.md - Guide de travail Leopardo

Derniere mise a jour : 2026-09-05 (audit PM architecture — liste des apps mobiles alignée sur le dépôt)

Ce fichier doit etre lu au debut de chaque nouvelle session agent. Il doit aussi etre mis a jour a chaque push ou merge vers `main`, comme le `CHANGELOG.md`, des qu'une lecon operationnelle peut eviter de perdre du temps plus tard.

> **Leçons historiques : voir `docs/GESTION_PROJET/LECONS_AGENTS.md`** (leçons datées,
> post-mortems et historiques d'incidents extraits de ce guide — #7843).
> Bibliothèque transversale des pièges connus (vue rapide) : `docs/GESTION_PROJET/BIBLIOTHEQUE_ERREURS.md`.
> Toute nouvelle leçon opérationnelle = mise à jour AGENTS.md **et**, si c'est un piège
> rejouable, une ligne dans la bibliothèque des erreurs. Flux pas-à-pas :
> `docs/GOUVERNANCE/RETEX_FLUX.md` (constat → issue [LECON] → leçon, protocole P04).

> **NOUVEL AGENT ? Commence par lire `dev-hub/prompts/00_AGENT_QUICK_CARD.md` (2 min) pour une carte de reference rapide. Ce fichier AGENTS.md est le guide complet.**

> **Cadre de protocoles opposables (P01-P07) : `docs/PROTOCOLES/README.md`** — validation marché
> (P01), onboarding (P02), vitrine & présentation (P03), issues/tâches par expérience (P04),
> design harmonisé (P05), desktop Windows/macOS par BC (P06), architecture dev/prod (P07).
> État, propriétaires et travaux ouverts : `docs/PROTOCOLES/REGISTRE_PROTOCOLES.md`.
> **Revue mensuelle** le dernier jour ouvré (gabarit `.github/ISSUE_TEMPLATE/revue_mensuelle.md`) ;
> moisson des leçons le 1er du mois (`docs/GESTION_PROJET/MOISSON_LECONS.md`).
> En cas de conflit de règle : `.specify/constitution.md` > `AGENTS.md` > `docs/PROTOCOLES/` >
> `docs/GOUVERNANCE/` > `docs/ops/`.

## ⚡ Spec-Driven Development — Spec Kit (NOUVEAU 2026-08-14)

Leopardo utilise desormais **GitHub Spec Kit** pour structurer tout travail significatif.
Lire `.specify/constitution.md` — c'est la loi fondamentale du projet.

### Commandes disponibles (GitHub Copilot skills)

| Commande | Role |
|----------|------|
| `/speckit-constitution` | Principes directeurs du projet |
| `/speckit-specify` | Creer une spec avant de coder |
| `/speckit-clarify` | Clarifier les ambiguites (avant plan) |
| `/speckit-plan` | Plan technique et architecture |
| `/speckit-tasks` | Generer les taches actionnables |
| `/speckit-analyze` | Verifier coherence avant implementation |
| `/speckit-implement` | Executer les taches |
| `/speckit-converge` | Detecter le travail restant |

### Presets actifs (injectes automatiquement dans toute spec)

| Preset | Se declenche sur |
|--------|-----------------|
| `leopardo-payroll` | Toute spec touchant `api/app/Modules/Payroll/` |
| `leopardo-multitenancy` | Toute spec ajoutant une table ou un endpoint API |
| `leopardo-dz` | Specs paie algeriennes (IRG/CNAS) |
| `leopardo-cemac` | Specs paie zone CEMAC (CM/GA/CG/XAF) |
| `leopardo-cedeao` | Specs paie zone CEDEAO (CI/SN/BF/ML/XOF) |

### Workflow standard (remplace la creation manuelle d'issues)

```
1. /speckit-specify  "description de ce que tu veux construire"
2. /speckit-clarify  (si ambiguite)
3. /speckit-plan     (architecture)
4. /speckit-analyze  (verifier dependances manquantes)
5. /speckit-tasks    (generer les taches)
6. /speckit-implement (coder)
```

### Regle anti-doublon (CRITIQUE — protocole durci issue #2400)

L'auto-assignation seule ne protege pas la fenetre implementation : plusieurs
agents peuvent travailler en parallele sur la meme issue (constate le
2026-08-15 : #2333 ×3 PRs, #2329 ×2 PRs, #2264 ×2, #2326 ×2 branches...).
Protocol OBLIGATOIRE avant de commencer a coder :

1. **Verifier TOUTES les branches, pas seulement les PRs** — le nom de
   branche EST le lock :
   ```bash
   gh api repos/kitokoh/leopardo-hr/branches --paginate | grep -i "<issue>"
   gh pr list --state open --json number,title,headRefName
   ```
   Une branche `fix/<issue>-*` existante = l'issue est prise → contribuer
   dessus ou s'arreter (constitution §I « deux agents ne peuvent pas
   implementer la meme spec »).
2. **Marker branch immediat** : des le self-assign
   (`gh issue edit <N> --add-assignee @me`), pousser une branche
   `fix/<issue>-<slug>` avec un commit vide de claim (message
   « claim marker #N »). Le premier-arrive conserve sa branche ; tout agent
   qui voit la branche pour la meme issue contribue dessus ou s'arrete.
3. **Nommage de branche UNIQUE par issue** : un seul `fix/<issue>-*` par
   issue. Pas de suffixes multiples (`fix/2333-a`, `fix/2333-b`...).
4. **Fermeture des doublons** : toute PR dupliquee sur une meme issue est
   fermee avec un commentaire de renvoi vers la PR canonique (1 PR = 1 issue).

### Affectation par Bounded Context (labels BC — registre #5859)

Le registre automatise des bounded contexts (`dev-hub/governance/
bounded-context-registry.json`, BC-01 PLATFORM .. BC-26 DELIVERY) est la carte
canonique. Toute issue ouverte est etiquettee avec son BC (`BC-01 PLATFORM`,
`BC-02 TENANT`, `BC-11 CRM`, `BC-15 FUEL`, `BC-16 EDU`, ...).

1. **Affectation par BC, jamais par surface vague** : le fondateur/chef de
   projet confie le travail par BC — ex. « travaille sur les issues
   etiquetees BC-15 FUEL » ou « issues du BC-01 PLATFORM ». Un agent
   selectionne UNIQUEMENT des issues du BC qui lui a ete confie.
2. **Un seul agent par BC a la fois** : deux agents peuvent travailler en
   parallele sur des BC DIFFERENTS, jamais sur le meme BC. Le verrou
   reste la regle anti-doublon ci-dessus (`fix/<issue>-<slug>` + claim
   marker) : si une branche existe deja pour une issue du BC, contribuer
   dessus ou s'arreter.
2bis. **Lot d'issues d'un meme BC : une branche par lot, pas une par issue**
   (`docs/GOUVERNANCE/BC_BATCH_BRANCH_PROTOCOL.md`, 2026-08-29) — quand
   plusieurs issues du meme BC sont confiees a un agent, il ouvre UNE branche
   `bc/<code-bc>-<slug>` et y accumule un commit par issue livree, puis ferme
   toutes les issues du lot dans une seule PR (`Closes #N` repete par issue).
   Objectif : reduire le nombre de branches/PRs/runs CI par lot (constat
   `docs/infra/02_alignement/CI_SATURATION.md`). Le protocole `fix/<issue>-*`
   (une branche par issue) reste la norme pour une issue isolee hors lot, un
   hotfix, ou le programme CRM qui garde son protocole dedie plus strict
   (`CRM_BRANCH_PROTOCOL.md`, une issue = une branche = une PR).
3. **Nouvelle issue sans label BC** : verifier le registre
   `bounded-context-registry.json` (chemin racine -> BC) et ajouter le
   label avant de commencer.
4. **Une branche reste dans son BC** : pas d'import cross-BC (garde Module
   Structure Validator), pas de migration tenant sans `company_id` (garde
   Hygiene Guards).

### Garde migrations AVANT push (issue #1962 — 3 occurrences le 2026-08-24)

Toute PR ajoutant ou renommant une migration (`api/database/migrations/*`)
doit verifier AVANT push l'absence de collision de prefixe de sequence
(`YYYY_MM_DD_0000NN`) — Laravel indexe les migrations par basename, une
collision rend `main` ROUGE pour TOUTES les PRs (garde Hygiene Guards) :

```bash
bash dev-hub/tools/check-migration-basename-collisions.sh
```

Un prefixe deja pris = renumero ter (ex. `000006` -> `000007`) en gardant
l'ordre chronologique (la migration la plus ancienne conserve son prefixe).
Le commit precedent `fix/1962-*` est l'exemple canonique.

## Garde « une table, une migration » (#7452 / tranche #7455)

Deux migrations qui font `Schema::create('<table>')` sont **une seule et même
déclaration** : la première exécutée gagne (`if (! schemaTableExists())`) et les
suivantes sont ignorées **silencieusement**. Quand leurs colonnes divergent, tout
le code écrit contre la dernière génération casse en `column "x" does not exist`
— souvent masqué par une cascade `25P02` (223 échecs de `tests/Feature/Travel`).

- **Ne jamais** créer une table par un second `Schema::create` : pour rattraper
  une colonne, une migration `Schema::table` **idempotente** (`schemaHasColumn`).
- Vérifier localement avant push :
  ```bash
  python3 dev-hub/tools/check-duplicate-schema-create.py --base origin/main --strict --fail-on-duplicate
  ```
  (garde CI `.github/workflows/migration-duplication-guard.yml`). **Depuis l'audit de
  clôture #7452 (2026-09-18), la dette est SOLDÉE à l'échelle du dépôt (0 table
  dupliquée, 0 divergente — tranches Travel #7467 et EDU #7571) et la garde tourne
  en zéro tolérance par défaut** : toute PR qui redéclare une table existante est
  rouge, même sans divergence de colonnes. L'inventaire historique reste dans
  `docs/audits/MIGRATIONS_DUPLIQUEES_TENANT.md` (`--write` régénère le document).
- La résorption s'est faite **module par module** (#7452, #7417, #7410) — terminée.
  Historique de la tranche Travel et recette détaillée de consolidation d'un module :
  voir `docs/GESTION_PROJET/LECONS_AGENTS.md`.

## Garde post-merge `Closes #` (issue #2512)

Une PR qui **mentionne** une issue (`#1234`) sans mot-clé `Closes #` (ou
Fixes/Resolves) dans le **body** ne ferme JAMAIS l'issue au merge : elle
reste ouverte même une fois le correctif livré. Règles :

- Le mot-clé `Closes #N` doit être dans le **body** de la PR (le titre seul
  est fragile : GitHub ne le traite pas toujours).
- Les mentions entre parenthèses `(#1234)` ou dans le contexte ne ferment
  rien — si l'issue doit être close, ajouter `Closes #1234` dans le body.
- Garde de détection : `dev-hub/tools/check-issues-left-open-by-merged-prs.sh
  <owner/repo>` liste les issues référencées par des PRs mergées mais restées
  ouvertes sans PR en cours (rapport non bloquant — fermeture manuelle avec
  preuve code : commentaire + état closed).
- Fallback : fermeture manuelle avec vérification du code sur main.

## Garde anti « ghost close » (issue #4816)

Une issue ne doit être **clôturée que lorsqu'un correctif est réellement
mergé** (auto-close par `Closes #N` sur main) **ou** avec un commentaire
motivé (`wontfix` / `superseded` / renvoi vers le ticket canonique).
Clôturer « pour faire propre » sans code casse le backlog : le visuel devient
vert alors que le correctif n'existe pas (vague du 2026-08-17 : #4690/#4687/
#4688/#4305/#4410 fermées non résolues, vérifié sur main).

- Règle : **jamais de `gh issue close` sans commit de merge associé OU sans
  commentaire de motivation explicite.**
- Garde de détection : `dev-hub/tools/check-issues-closed-without-merge.sh
  <owner/repo>` liste les issues clôturées sans commit de fermeture ET sans
  PR mergée les référençant (rapport non bloquant — ré-ouverture/correction
  manuelle avec preuve code, même esprit que #2512).
- Si une issue a été clôturée à tort : la ré-ouvrir, créer le ticket de
  correctif dédié, et référencer la vérification code dans un commentaire.

## SLA bugs pilotes (issue #5155)

- **Promesse** : un bug **bloquant** pilote (paie / pointage / login impossible
  en prod) se regle en **moins de 24 h** (deploiement prod inclus).
- **Canal** : issue avec le template `PILOT_BLOCKER`
  (`.github/ISSUE_TEMPLATE/pilot_blocker.yml`) + label `pilot-blocker` — voir
  `docs/ops/SLA_PILOTES.md`.
- **Triage** : bloquant = paie/pointage/login impossible ; tout le reste est P2.
- **Hotfix** : branche `hotfix/<issue>-<slug>`, CI minimale (tests paie + E2E
  funnel), deploy prod < 24 h, post-mortem court en cas de recidive.
- **Metrique** : delai moyen de resolution des bloquants, tableau hebdo dans
  le bilan du vendredi.

## Regles obligatoires

- **Drain de crise (#7562, 2026-09-16)** — quand plusieurs agents ont livré le **même sujet** sur des branches concurrentes, qu'aucune PR n'est mergeable (protection `strict` : chaque merge invalide les autres) et que la file CI est le facteur limitant : appliquer **`docs/GOUVERNANCE/PROTOCOLE_LOTS_MULTI_AGENTS.md` §6** (une PR de lot, un sujet = une implémentation canonique, pilotage de la file, outillage local, critères de sortie). **Ce n'est pas un mode de travail** : les 4 checks requis gardent leur rôle, aucune PR ne se merge avec un check requis rouge, et le drain suivant se fait PR par PR. Un drain se termine quand il ne reste QUE `main`, propre et verte — jamais « presque ».

- **Protocole branches CRM (#5746)** : pour toute issue du programme CRM (#5705→#5731, #5735→#5746), suivre `docs/GOUVERNANCE/CRM_BRANCH_PROTOCOL.md` — une issue = une branche = une PR ; marker branch immédiat après claim ; base `main` à jour ; migrations avec réf issue dans le nom ; jamais d'auto-merge d'une PR rouge ; arrêt si `main` rouge. Le garde `dev-hub/tools/check-crm-branch-protocol.sh` (workflow `crm-branch-protocol.yml`) signale doublons de branches, PRs sans `Closes #N` et PRs trop grosses.

- Les leçons datées historiques (garde mock #4164, quota Vercel #4868, rafale de
  pushes swe-qa-360…) sont dans `docs/GESTION_PROJET/LECONS_AGENTS.md`.

- **Lecon 2026-09-08 (#6590)** : les fichiers generes mobile NE SONT PLUS
  COMMITES (`.gitignore` racine : `*.g.dart`, `*.freezed.dart`,
  `**/lib/l10n/generated/`) — regeneres en CI. Tout job qui analyse, teste ou
  build une app consommant `leopardo_core` doit d'abord regenerer dans
  `front/mobile_apps/leopardo_core` : `flutter pub get && flutter gen-l10n &&
  dart run build_runner build --delete-conflicting-outputs` (le codegen drift
  + json_serializable exige les dev_dependencies du package core resolues —
  un `pub get` dans le dossier core est requis, celui de l'app ne suffit
  pas). La garde ARB l10n (#4762) tourne desormais APRES `flutter gen-l10n`
  dans le job `flutter-analyze` (projet leopardo_core) — plus dans le job
  guard (checkout brut sans generes). Tout ajout de cle ARB sans regenerer
  reste capture a la compile (#4762) ; ne jamais re-commiter un genere.

- **REGLE D'OR POUR LES NOUVEAUX MODULES** : Avant de commencer a coder un nouveau module ou de generer des tickets (GitHub Issues) pour celui-ci, un agent DOIT OBLIGATOIREMENT creer un fichier Markdown de specification dans le dossier `docs/specifications/` (ex: `docs/specifications/MODULE_RECRUTEMENT.md`). Ce n'est qu'apres validation explicite de ce document par le proprietaire que les issues GitHub peuvent etre creees.

- Avant de travailler sur une branche existante, faire `git fetch origin main` puis comparer avec `origin/main`.
- `main` distant est la source de verite. Le local doit rester aligne sur `origin/main` apres chaque intervention terminee.
- Ne pas pousser directement sur `main` si la branche est protegee. Creer un PR, attendre les checks GitHub Actions, puis merger et supprimer la branche.
- Apres un merge dans `main`, supprimer la branche distante et nettoyer les branches locales devenues inutiles.
- Ne jamais perdre les stashes existants. Verifier `git stash list` avant toute operation destructive.
- Chaque changement de comportement, migration, CI ou procedure doit avoir une entree `CHANGELOG.md`.
- **CHANGELOG.md (issue #2417)** : toute PR ajoute son entree sous `## [Unreleased]` avec la categorie adaptee (`### Added` / `### Changed` / `### Fixed` / `### Removed`) — Keep a Changelog. Les sections versionnees (`## [x.y.z] - date`) sont creees a la release ; l'historique integral vit dans les notes de release GitHub et dans l'historique git (`CHANGELOG_ARCHIVE.md` a ete sorti du depot — #7654, derniere version : blob `6a3819a`).
- Chaque connaissance utile pour les prochains agents doit etre ajoutee ici.

## 🗺️ Cartographie de l'Ecosysteme Leopardo (A respecter strictement)

Le projet est une **Suite d'Applications** (1 App = 1 Metier). Voici les roles definis "noir sur blanc" :

### Les 8 Applications Mobiles Flutter (`front/mobile_apps/`)
- **`leopardo_employee`** : Application employee (self-service) — pointage GPS, absences, soldes, notifications.
- **`leopardo_manager`** : Application dediee a la gestion du tenant (entreprise). Vue globale, affectation des roles, evolution.
- **`leopardo_hr`** : Application dediee aux Ressources Humaines. Suivi des employes, presences/absences, taches, et gestion du recrutement (ATS).
- **`leopardo_marketing`** : Application dediee aux marketeurs. Planification et publication en "1-clic" sur les differents reseaux sociaux.
- **`leopardo_platform_admin`** : Application ultra-securisee pour le Super-Admin (proprietaire du SaaS) pour gerer les abonnements et l'infrastructure.
- **`leopardo_accounting`** : Application dediee a la comptabilite (facturation, suivi des impayes). Integree a melos et a la CI mobile (voir `front/mobile_apps/README.md`).
- **`leopardo_travel_agent`** : Application dediee aux agents/vendeurs de la verticale TravelAgency — vente guichet multi-passagers, encaissement cash, check-in QR, manifeste, caisse PDV (TRAVEL-701 #6088 / TRAVEL-810 #6100).
- **`leopardo_cameras`** : Application dediee a la surveillance video « Leopardo Cameras » (BC-19 DEVICE, #7426) — mur des cameras du tenant, visionnage direct via stream-token (chaine video MediaMTX #7424), evenements et alertes (#7427). Reservee aux responsables (module `cameras` + `api.manager`) ; integree a melos et a la CI mobile.

> `leopardo_core` est le package partage (design system, API client, modeles, l10n) consomme par les 8 apps.
> La liste canonique des apps mobiles est `front/mobile_apps/README.md` (a jour avec melos.yaml).
> Le **kiosk/biometrie n'est PAS une app Flutter** : c'est une web app offline-first (`front/zkteco-kiosk`,
> pointage local `/local/punch` + bridge ZKTeco) — cf. `front/zkteco-kiosk/README.md`.

### L'Ecosysteme Web (`front/`)
- **La Web App Client (`front/web` et admin-dashboard)** : Le portail web client est **unique**. Un employe, un RH ou un Manager se connecte au meme portail, mais l'interface s'adapte dynamiquement et change completement en fonction du role (RBAC).
- **La Web App Super-Admin** : Interface web reservee exclusivement a l'administration de la plateforme Leopardo (SaaS).

### Freeze scope 60 jours (issue #5147, plan 60 jours)
Toute feature hors du périmètre autorisé de `docs/GOUVERNANCE/FREEZE_SCOPE_60J.md` est **refusée en revue** avec renvoi vers ce document. Les exceptions passent par une issue `[FREEZE-EXCEPTION]` décidée par le fondateur — jamais par l'agent lui-même.

## ⚠️ NOUVELLE METHODE DE GESTION DE PROJET (Juillet 2026)

**ATTENTION AGENTS** : Les anciens dossiers `docs/PLAN_ACTION/` et `docs/PLAN_ACTION2/` sont **obsoletes et archives**. Il est **strictement interdit** de lire ces dossiers pour chercher du travail ou d'y creer de nouveaux fichiers Markdown de planification.

La gestion du projet Leopardo se fait desormais **exclusivement via GitHub Issues et GitHub Projects**.

### Regles de selection d'une tache (GitHub Issues)

1. **Lister les tickets ouverts** : `gh issue list --limit 50 --state open --json number,title,labels,assignees`.
2. **Filtrer** : Ne choisissez **que** les issues qui n'ont pas d'assignes (`assignees` vide) ET qui possedent des criteres d'acceptation clairs dans leur description (`gh issue view <number>`). Idealement, cherchez le label `Agent-Ready` ou `good first issue`.
3. **S'assigner** : Avant de coder, vous DEVEZ vous assigner l'issue, ou annoncer que vous la prenez pour eviter que deux agents ne fassent la meme chose.
4. **Fermeture automatique (CRITIQUE)** : Votre Pull Request (PR) **doit obligatoirement** contenir `Closes #<numero_issue>` dans sa description pour fermer l'issue automatiquement au merge.

### Comment demander une review

- Une fois le travail complet et verifie localement (tests pertinents, `shellcheck`/lint si applicable), passez la PR draft en "Ready for review" : `gh pr ready <numero>`.
- Ne jamais merger sa propre PR sans que les checks CI obligatoires (`gh pr checks <numero>`) soient verts.
- Assurez-vous que la description de la PR indique clairement quelle issue P0/P1 est resolue.

### Bibliotheque de prompts operationnels

Le dossier `dev-hub/prompts/` contient des prompts numerotes prets a l'emploi pour piloter les agents. Chaque prompt est un fichier Markdown autonome avec des instructions executables.

- **Carte rapide** : `dev-hub/prompts/00_AGENT_QUICK_CARD.md` — resume des regles vitales (2 min)
- **Vider le backlog** : `dev-hub/prompts/01_DRAIN_BACKLOG.md` — traiter tous les tickets
- **Audits** : prompts 02, 05-09 — auditer chaque surface du projet
- **CI/Merge** : prompts 03, 12 — reparer la CI, merger les branches
- **Anti-regression** : `dev-hub/prompts/13_REGRESSION_GUARD.md` — traquer les patterns interdits
- Voir `dev-hub/prompts/README.md` pour l'index complet

## Prérequis Git LFS (#4124)

`assets/**` (design, screenshots) et les icônes mobiles sont trackés en **vrai
Git LFS** (pointeurs) — les médias vitrine (`front/web/public/**`) sont des
binaires réels hors LFS. Un agent clonant sans git-lfs verra des fichiers
pointeurs (~130 o) pour les assets LFS : installer git-lfs (`git lfs install`)
pour les résoudre au checkout.

## Strategie CI rapide

> La strategie CI rapide (workflows, fast-path, saturation du pipeline, relances) a ete
> deplacee vers `docs/ops/STRATEGIE_CI_RAPIDE.md` (issue #6698 — desengorgement d'AGENTS.md).

## Pieges connus

> Les pièges datés (drain de crise 2026-09-16, merge lane 2026-09-08, dérive dev
> Render 2026-09-15, audits 2026-05-13/14, incidents Vercel…) sont consignés dans
> `docs/GESTION_PROJET/LECONS_AGENTS.md`.

### Frontieres routes modules

- `routes/modules/rh.php` porte le socle RH transverse (employes, contrats, absences, rapports courants) alors que `routes/modules/hr_extended.php` porte les extensions post-MVP. Avant de deplacer une route, verifier le controller et le scenario de test associe.
- Les routes IA experimentales voice/agent restent sous feature AI + rate limit ; toute exposition plus large doit passer par une feature flag explicite et une couverture RBAC.
- Dans les extensions RH (`RecruitmentController`, `TrainingController`, `EmployeeLoanController`, `ExpenseClaimController`), les index doivent toujours demarrer par `where('company_id', $actor->company_id)` et les references employees/departments/positions/trainers/interviewers doivent etre validees dans le tenant courant.

### Paie multi-pays et exports bancaires

- Les tables `tax_slabs` et `social_contributions` sont creees par les migrations tenant. Le seeder `PayrollCountryConfigSeeder` doit etre lance dans le schema tenant courant, pas depuis un contexte public qui n'a pas ces tables.
- Les exports bancaires doivent utiliser les colonnes reelles de `employees` : `iban` et `bank_account`. Ne pas reintroduire `rib` ou `bank_name` sans migration correspondante.
- Les declarations sociales CNAS/CNSS/DSN doivent lire les salaries via le modele `Employee`, pas via `DB::table('employees')`, afin de respecter les casts `encrypted` (`national_id`). Les identifiants entreprise viennent de `companies.metadata` (`nis`, `affiliate_number`, `siret`, `tax_id`) ; ne pas reintroduire `companies.tax_id` ni `employees.hire_date`.
- Pour les barèmes fiscaux de paie, les tranches documentees sont inclusives (`0-5000`, `5001-20000`). Utiliser le helper progressif de `AbstractCountryRules` pour eviter les erreurs d'unite aux bornes.
- Pour tester `PayrollRunController` sans rendre la suite fragile face aux baremes/salary structures, binder un faux `PayrollCalculator` dans le container et verifier le contrat controller : run calcule, pay slip cree, validation/cancel et isolation tenant.

### Render et migrations PostgreSQL

Render peut rejouer des migrations dans un environnement ou certaines tables existent deja. Les migrations publiques doivent donc etre idempotentes.

Exemples resolus le 2026-05-06 :

- `2026_05_02_000003_create_company_requests_table.php` doit verifier `Schema::hasTable('company_requests')` avant `Schema::create`.
- `2026_05_02_100001_create_users_and_company_requests_tables.php` doit verifier l'existence de `users`, `company_requests` et `user_employee_links`.
- Si une migration touche une table tenant comme `employees`, verifier le `search_path` PostgreSQL et proteger avec `Schema::hasTable`.

Lecons 2026-09-06 (issues #6916/#6924, pre-flight migrations dev/prod) :

- **Les migrations du boot passent par l'hôte DIRECT de la base, jamais le
  pooler Neon** (`api/docker-entrypoint.sh` : `DB_MIGRATE_URL` si posée, sinon
  derivee de `DB_URL` en retirant `-pooler`) — le pooler en mode transaction
  aborte toute migration DDL en `SQLSTATE 25P02` (deploy `update_failed`).
- **Le search_path des migrations tenant au boot est `shared_tenants,public`**
  (aligné sur le runtime), pas `shared_tenants` strict : des tables billing
  héritées (`invoices`...) vivent dans `public` alors que leurs migrations sont
  dans le dossier tenant. Toute migration tenant qui ALTÈRE une table héritée
  doit rester non qualifiée + gardée (`schemaTableExists`/`schemaHasColumn`,
  #1613) ; ne pas réintroduire de search_path strict pour le dossier tenant.
- Garde migrations #1962 avant push sur toute PR touchant
  `api/database/migrations/*` : `bash dev-hub/tools/check-migration-basename-collisions.sh`.

### Main local divergent

Le poste local peut avoir un `main` divergent (`ahead`/`behind`). Dans ce cas :

- Ne pas tenter de fast-forward aveugle.
- Travailler depuis `origin/main` via une branche propre.
- Une fois les travaux merges, remettre le local en phase avec `origin/main` seulement apres avoir confirme qu'aucun changement local utile ne sera perdu.

## Procedure PR et merge

1. Creer une branche courte depuis `origin/main`.
2. Faire le changement minimal.
3. Ajouter `CHANGELOG.md` et `AGENTS.md` si une connaissance doit etre conservee.
4. Push la branche et creer un PR.
5. Observer avec `gh pr checks <numero>`.
6. Corriger uniquement les rouges.
7. Quand les GitHub Actions requis sont verts, merger avec `gh pr merge <numero> --merge --delete-branch`.
8. Verifier que le PR est `MERGED` avec `gh pr view <numero> --json state,mergedAt,mergeCommit`.
9. Verifier que la branche distante est supprimee avec `git ls-remote --heads origin <branche>`.

## Nettoyage branches

Objectif demande le 2026-05-06 : en local, ne garder que `main` aligne sur `origin/main`.

Procedure recommandee :

- Verifier `git status --short --branch`.
- Verifier les stashes avec `git stash list`.
- Supprimer les branches locales non `main` apres merge ou abandon explicite.
- Pour les branches distantes, commencer par les PR ouverts. Merger uniquement si les changements apportent une nouveaute utile a `main`, puis supprimer la branche.
- Ne pas supprimer une branche distante non analysee si elle contient du travail non merge ou non remplace.

## Federation de branches

- Pour les vieilles branches mobiles ou mixtes tres en retard sur `main`, ne pas merger la branche complete si le diff embarque des centaines de suppressions hors sujet.
- Preferer recuperer uniquement les fichiers utiles avec `git checkout <branche> -- <fichier>` dans une branche federatrice propre creee depuis `origin/main`.
- Cette approche a ete confirmee utile le 2026-05-06 pour reutiliser seulement les apports de `#269`, `#275` et `#298` sans reintroduire le bruit historique de branches anciennes.

## Historique utile & lecons

> Le journal « Historique utile » et les lecons associees ont ete archives dans
> `docs/archive/AGENTS_HISTORIQUE_UTILE.md` (issue #6698). Les leçons datées et
> post-mortems extraits de ce guide vivent dans `docs/GESTION_PROJET/LECONS_AGENTS.md`
> (issue #7843). Les regles actives de ce fichier font foi ; l'historique reste
> disponible pour tracabilite.

