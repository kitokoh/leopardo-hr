# AGENTS.md - Guide de travail Leopardo RH

Derniere mise a jour : 2026-05-07

Ce fichier doit etre lu au debut de chaque nouvelle session agent. Il doit aussi etre mis a jour a chaque push ou merge vers `main`, comme le `CHANGELOG.md`, des qu'une lecon operationnelle peut eviter de perdre du temps plus tard.

## Regles obligatoires

- Avant de travailler sur une branche existante, faire `git fetch origin main` puis comparer avec `origin/main`.
- `main` distant est la source de verite. Le local doit rester aligne sur `origin/main` apres chaque intervention terminee.
- Ne pas pousser directement sur `main` si la branche est protegee. Creer un PR, attendre les checks GitHub Actions, puis merger et supprimer la branche.
- Apres un merge dans `main`, supprimer la branche distante et nettoyer les branches locales devenues inutiles.
- Ne jamais perdre les stashes existants. Verifier `git stash list` avant toute operation destructive.
- Chaque changement de comportement, migration, CI ou procedure doit avoir une entree `CHANGELOG.md`.
- Chaque connaissance utile pour les prochains agents doit etre ajoutee ici.

## Strategie CI rapide

Depuis la session du 2026-05-06, la meilleure strategie est d'utiliser GitHub Actions comme source de verite au lieu d'insister sur les checks locaux Windows.

- Preferer `gh pr checks <numero>` pour voir l'etat global.
- Preferer `gh run view <run-id> --log-failed` pour lire uniquement les erreurs rouges.
- Corriger l'erreur exacte, push, puis repeter.
- Eviter les longues commandes locales si elles bloquent sur Windows : `dart format`, `jest`, `npm run build`, `flutter analyze` peuvent etre lents ou produire du bruit localement.
- `npx tsc --strict --noEmit` est acceptable localement quand il faut verifier vite une erreur TypeScript evidente.
- Les checks GitHub Actions qui ont permis de merger le PR #268 : backend, backend quality, mobile, build, lint, type-check, test Node 20, CodeQL, governance.
- Les workflows web `build.yml`, `lint.yml` et `test.yml` doivent rester limites aux changements `web/**` ou a leur propre fichier workflow pour eviter les CI inutiles sur des PR backend/docs.
- Si une simplification CI/CD est envisagee, prioriser d'abord les gains de signal (Playwright dedie, coverage backend visible, tests critiques, secret scan) avant la fusion cosmetique des fichiers YAML.
- Si les workflows web sont fusionnes plus tard, conserver absolument les filtres `paths:` qui ont reduit le bruit CI a partir du 2026-05-06.
- Pour Composer en CI, preferer un cache base sur `composer.lock` ou le cache officiel plutot qu'un cache brut de `vendor/`.
- Pour la coverage backend, mesurer puis activer un seuil progressif ; ne pas imposer `60%` d'un coup sans baseline reelle.
- Le runbook backup existe deja dans `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md` ; en cas de plan CI/CD, penser mise a jour/allegement avant creation d'une nouvelle doc.
- Le frontend versionne dans ce depot est `admin-dashboard/`, pas `web/`. Si un workflow pointe encore vers `web/**`, il est probablement obsolete ou desynchronise.
- Le depot contient deja beaucoup de tests backend critiques (auth, guardrails, RBAC, absences, attendance, contrats mobile). Avant d'ajouter de nouveaux tests, verifier d'abord si le manque reel n'est pas plutot la visibilite CI (coverage, artifacts, reporting).

## Pieges connus

### Render et migrations PostgreSQL

Render peut rejouer des migrations dans un environnement ou certaines tables existent deja. Les migrations publiques doivent donc etre idempotentes.

Exemples resolus le 2026-05-06 :

- `2026_05_02_000003_create_company_requests_table.php` doit verifier `Schema::hasTable('company_requests')` avant `Schema::create`.
- `2026_05_02_100001_create_users_and_company_requests_tables.php` doit verifier l'existence de `users`, `company_requests` et `user_employee_links`.
- Si une migration touche une table tenant comme `employees`, verifier le `search_path` PostgreSQL et proteger avec `Schema::hasTable`.

### Vercel

Le statut externe `Vercel` peut echouer immediatement vers une page de configuration projet. Lors du PR #268 et du hotfix #299, tous les GitHub Actions etaient verts et le merge restait possible malgre ce statut externe. Ne pas perdre du temps a corriger le code si Vercel echoue sans logs de build applicatif.

Le workflow GitHub `Build & Deploiement` a aussi porte une integration `vercel/action@v4` introuvable cote Actions. Si ce workflow redevient rouge pour `Unable to resolve action vercel/action`, conserver seulement le job de build jusqu'a ce qu'une integration Vercel valide soit configuree.

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

## Historique utile

### 2026-05-07 - Mobile i18n

- Avant d'estimer un chantier i18n mobile, verifier l'etat reel sur `origin/main` : `flutter_localizations`, `intl`, locale et RTL peuvent etre branches sans que `gen-l10n`, `l10n.yaml`, les `.arb` et `context.l10n` existent deja.
- Ne pas migrer 500+ cles d'un coup. La sequence la plus sure est : fondation `gen-l10n`, un ecran prioritaire, CI mobile, puis extension par lots verticaux.
- Pour l'arabe, tester explicitement les petits viewports : les textes traduits peuvent casser les `Column` avec `Spacer`; preferer des zones scrollables bornees ou des layouts qui degradent proprement.
- Les plans i18n doivent distinguer les cles reellement utilisees dans le code des cles "catalogue" prevues plus tard, sinon la progression annoncee devient trompeuse.

### 2026-05-06 - PR #268 Feature/vitrine restructure

- Le PR #268 a ete merge dans `main` avec le commit `08d4316a2b9baaf2e95b2d40ffa8dd69bdc40af5`.
- Approche gagnante : boucle rapide GitHub Actions, pas de tatonnement local.
- Corrections majeures : TypeScript vitrine, exports ambigus, Zod v4, Playwright hors Jest, migrations PostgreSQL, mobile pubspec, gates CI instables.

### 2026-05-06 - PR #299 Hotfix Render company_requests

- Render echouait avec `SQLSTATE[42P07]: Duplicate table: relation "company_requests" already exists`.
- Hotfix merge dans `main` avec le commit `53f1d20892353e7012612822ff43eb0709e56202`.
- Le correctif rend la migration `company_requests` idempotente.

### 2026-05-07 - CI/CD incremental

- Les anciens workflows web pointaient encore vers `web/**` alors que le frontend reel du depot est `admin-dashboard/`.
- La bonne simplification n'est pas de fusionner des YAML a l'aveugle, mais de realigner d'abord la CI sur l'arborescence reelle puis d'ajouter un smoke E2E Playwright dedie.
- La coverage backend doit etre publiee en artifact et resume CI avant de devenir une gate stricte ; un seuil configurable via variable GitHub est preferable a une valeur codee en dur trop ambitieuse.
- Attention au schema public `company_requests` : la migration historique `2026_05_02_000003_*` cree l'ancienne forme basee sur `employee_id`, alors que les controllers et le modele `User` attendent la forme moderne basee sur `user_id`. La migration `2026_05_02_100001_*` doit donc aussi mettre a niveau une table existante, pas seulement la creer.
- Dans `tests.yml`, un `continue-on-error` sur Unit/Feature ou coverage peut masquer un vrai rouge applicatif si aucun step final ne re-propage l'echec. Toujours ajouter un step final explicite qui fait echouer le job quand la suite de tests a casse.
- Quand des assertions FR cassent avec `EmployÃ©` ou `RÃ©cupÃ¨re`, verifier tout de suite un probleme d'encodage UTF-8/mojibake dans les tests ou les messages de validation avant de soupconner la logique metier.

### 2026-05-07 - Cap 10 clients payants

- Le produit a maintenant ses 10 premiers clients payants.
- Priorite produit immediate : prouver la valeur mesurable du pointage et du controle terrain avant d'ajouter des modules RH generiques.
- Premier chantier lance : `GET /api/v1/attendance/anomalies` pour exposer aux managers les retards, sorties manquantes, corrections manuelles, heures supplementaires elevees et pointages rapproches sur un meme appareil.
- Meme lot backend : `GET /api/v1/attendance/monthly-report` fournit le rapport mensuel en JSON/CSV/PDF ; `GET /api/v1/onboarding/checklist` donne la progression d'installation client ; `GET/PATCH /api/v1/platform/companies/{company}/features` rend les feature flags exploitables par API super-admin.
- Les anomalies avancees utilisent `company.metadata.attendance_geofence` avec `{lat,lng,radius_meters}` pour detecter les pointages hors zone, et signalent aussi les pointages a heure trop repetitive.
- Pour les prochaines PR, privilegier les features qui donnent un ROI client visible : reduction fraude/erreurs, temps admin economise, exports comptables, alertes manager simples.

### 2026-05-07 - Dossier Go-To-Market racine

- Le dossier racine de strategie commerciale s'appelle `GOTO_MARKET/`, pas `marketing/`, sur demande explicite.
- Le PDF inspirant `Leopardo_RH_Production_Creative.pdf` doit etre conserve dans `GOTO_MARKET/00_inspiration/` et sert de base creative IA-first.
- Les prochaines actions GTM doivent rester connectees au wedge produit prioritaire : pointage, anomalies, rapport mensuel, onboarding et ROI client mesurable.
- `GOTO_MARKET/` est aussi le centre de reflexion sur la viabilite globale : utiliser la tech pour repondre a un besoin actuel, gagner de l'argent, et ne pas hesiter a repositionner ou moderniser le produit/offre quand le marche l'exige.
- Les fichiers destines a presenter Leopardo RH au public doivent aller dans `GOTO_MARKET/public/` avec un sous-dossier par canal : `social/`, `landing/`, `video/`, `press/`, `partners/`, `ads/`, `content_calendar/`, `metrics/`.
- Le pack de lancement acquisition vit dans `GOTO_MARKET/12_PACK_LANCEMENT_ACQUISITION.md` et les supports associes (`public/email`, `public/lead_magnets`, `public/owned_channels`, etc.).
- La vision "Leopardo RH peut aussi aider une entreprise a gerer et automatiser son marketing" est documentee dans `GOTO_MARKET/product_marketing_automation/`, mais elle n'est pas implementee dans le depot et ne doit pas distraire du wedge pointage tant qu'il n'est pas solidement vendu.

### 2026-05-07 - Federation de PR ouvertes

- Quand plusieurs PR ouvertes sont propres mais en retard sur `main`, il est souvent plus rapide de creer une branche federatrice depuis `origin/main`, puis de `cherry-pick` uniquement les commits utiles des PR au lieu de tenter des merges historiques un par un.
- Les PR purement documentaires ou de synchronisation de version (`docs/AUDITS/*`, simple bump `PILOTAGE.md` / `api/config/app.php`) ne doivent pas bloquer un lot fonctionnel plus utile ; elles peuvent etre fermees comme supersedees apres integration du vrai contenu produit.
- Pour les lots mixtes backend/mobile, les conflits les plus probables sont `CHANGELOG.md`, `PILOTAGE.md` et `api/config/app.php`. Les absorber une seule fois en fin de federation fait gagner beaucoup de temps.
- Avant de conserver un fichier cache bot ou journal interne (`.jules/*`), verifier qu'il apporte une connaissance de projet exploitable par les humains ; sinon le retirer du lot merge.
