# P06 — Clients desktop Windows/macOS : extraction, tests & distribution par BC vertical

> **Statut :** ratifié v1.0 (2026-09-11) — **Dernière revue :** 2026-09-11 — revue mensuelle (dernier jour ouvré)
> **Propriétaire :** gardien technique mobile/desktop + PM (décision de sortie)
> **Portée :** production de clients desktop (Windows `.exe`/MSIX, macOS `.app`/`.dmg`) **extraits
> des apps Flutter existantes**, BC vertical par BC vertical. Hors champ : le web (PWA/edge), le
> kiosk ZKTeco HTML (sauf mention) et les apps mobiles Android/iOS (déjà distribuées — Firebase).
> **État réel au 2026-09-09 (vérifié dans le dépôt) :** aucun client desktop n'est distribué ni
> annoncé (README product map : desktop « Planned — only justified desktop workflows such as
> intensive accounting or kiosk operation » ; vitrine corrigée pour ne plus promettre de
> téléchargement public — #3257). Les scaffolds `windows/` + `macos/` existent déjà dans 5 apps
> (`leopardo_employee`, `leopardo_hr`, `leopardo_manager`, `leopardo_marketing`,
> `leopardo_platform_admin`) ; `leopardo_accounting`, `leopardo_travel_agent`, `leopardo_core`
> n'en ont pas. `melos.yaml` ne définit que `build:android` et `build:ios`.

## 1. Objet

Définir **quand, comment et sous quelles conditions** on sort un client desktop depuis le code
Flutter existant — pour qu'un BC vertical puisse livrer un `.exe` Windows ou une app macOS sans
réinventer le processus, sans casser l'harmonisation (P05) ni promettre en vitrine (P03).

Le desktop n'est **jamais** la copie du mobile : c'est une surface dédiée à des **postes de
travail fixes** (saisie intensive, caisse, kiosque, supervision multi-écrans, offline bureau)
dont la justification est tranchée BC par BC (voir §2).

## 2. Décision d'opportunité — « un desktop est-il justifié pour ce BC ? »

Une demande de client desktop (issue) passe la **checklist de justification** avant toute spec :

1. **Besoin de poste fixe** : rôle utilisateur assis (comptable, gestionnaire de caisse, admin
   site, superviseur) — pas un besoin terrain mobile.
2. **Valeur ajoutée desktop réelle** vs web/PWA : accès offline local robuste, périphériques
   (imprimante, lecteur, caisse), multi-fenêtres, ressources locales, kiosque verrouillé.
3. **BC et app source identifiés** : on n'extrait que depuis une app Flutter existante du BC
   (ou on justifie une nouvelle app melos). Exemples candidats documentés dans le dépôt :
   comptabilité intensive (README desktop), caisse/point de vente d'une verticale,
   supervision/kiosque — jamais « pour avoir un .exe ».
4. **Coût accepté** : signature (certificat Windows, Developer ID macOS + notarisation),
   maintenance de la surface, tests par plateforme, runners CI macOS payants.
5. **Promesse vitrine maîtrisée** : la sortie pilote n'annonce **pas** de téléchargement public
   (#3257) — canal pilote fermé tant que le palier GA n'est pas décidé (P01).

> Décision : issue `desktop:<bc>` ouverte par le PM avec la checklist remplie → spec Spec Kit
> (`.specify/features/`) → protocole d'exécution ci-dessous.

## 3. Extraction technique (app Flutter → client desktop)

1. **Cible = une app melos par BC** (`front/mobile_apps/leopardo_<bc>`) ; le partage de code reste
   dans `leopardo_core` (L.03/L.08 : le core n'importe aucun module ; pas d'import cross-app).
2. **Génération des scaffolds** manquants (ex. `leopardo_accounting`, `leopardo_travel_agent`) :
   ```bash
   cd front/mobile_apps/leopardo_<app>
   flutter create --platforms=windows,macos --project-name leopardo_<app> .   # ajoute windows/ + macos/
   ```
   Puis harmoniser : nom produit, identifiants (`com.leopardo.<app>` — cf. #4087 pour la
   cohérence iOS/desktop), icônes (assets de marque), fenêtre (titre, taille min, single-instance).
3. **Adaptations desktop dans la même PR que le scaffold** : responsive fenêtre redimensionnable,
   sortie d'app (pas de bouton retour système), gestion de l'absence de `FirebaseMessaging` /
   push (desktop n'a pas les mêmes capacités — pattern `PushUnavailable` existant #3932),
   offline/sync identique au mobile (pattern `requestWithRetry`, `sync_service.dart`).
4. **Fingerprint d'environnement** : le build desktop cible l'API du **même volet** que l'app
   mobile associée (dev ↔ API dev, prod ↔ API prod) via variable d'environnement de build
   (`API_BASE_URL`), avec garde anti-API-prod en build dev (pattern #4524).
5. **Versioning** : version desktop = version de l'app (pubspec) ; extension de la matrice
   `release-compat-matrix.json` avec `current` + `min_api` par desktop, vérifiée par
   `check-release-compat.sh` (même règle que mobile — pas de desktop sous son plancher d'API).

## 4. Scripts & CI (melos + GitHub Actions)

1. **Étendre `melos.yaml`** (issue) :
   ```yaml
   build:windows:  run: flutter build windows --release   # + package (.zip/.msix)
   build:macos:    run: flutter build macos --release     # + package (.dmg/.app)
   ```
   avec `packageFilters.ignore: [leopardo_core]` et sélection par app.
2. **Workflow `desktop-distribute.yml`** (issue, calqué sur `mobile-distribute.yml`) :
   - `workflow_dispatch` avec inputs : app, plateforme (windows/macos), volet (dev/pilot) ;
   - runners : `windows-latest` et `macos-latest` (coût macOS assumé au §2.4) ;
   - jobs : analyze + tests (réutilise `mobile-apps-ci.yml` pour analyze sur PRs touchant
     `front/mobile_apps/**`) → build → **tests desktop** (§5) → signature → artefact ;
   - artefact publié en **GitHub Release** (canal pilote privé) ou sur un canal pilote choisi —
   jamais de lien public avant décision PM (P01 palier + P03 vitrine).
3. **Signature** : secrets CI dédiés (certificat Windows + mot de passe ; chaîne
   Developer ID/notarisation macOS) — jamais dans le dépôt (constitution §V, secret scan).
   Sans signature, un build interne non notarié est marqué `unsigned` et réservé au pilote
   technique.

## 5. Tests desktop (matrice)

| Niveau | Contenu | Où |
|---|---|---|
| Unitaire/widget | Réutilise `melos test` (tests partagés mobile/desktop — même code, mêmes tests) | CI PR |
| Analyse/qualité | `dart analyze`, format, gardes mobile existants (apps split, color tokens, l10n, manifests) | CI PR (`mobile-apps-ci.yml`) |
| Smoke desktop | Démarrage, fenêtre, login, parcours critique du BC contre API dev (ou mock), sortie propre | Workflow desktop (job `desktop-smoke`) |
| Installation | Installer/désinstaller sur VM propre Windows + macOS (runners), vérifier icônes/identité | Workflow desktop (job `install-test`) |
| Contrat API | Même contrat que mobile : OpenAPI + compat matrix ; un desktop ancien reçoit blocage explicite si API plus récente | `check-release-compat.sh` |
| Recette verticale | Golden journeys du BC sur desktop (mêmes scénarios que `docs/architecture/GOLDEN_JOURNEYS.md`) | Pilote (UAT, P01) |

Règle : **pas de build desktop sans les tests desktop verts** — un `.exe` non testé ne sort pas
du workflow (même discipline que mobile-distribute : gate `PROD_API_BASE_URL`, checks bloquants).

## 6. Sortie & communication (alignement P01/P03)

1. Palier pilote : artefact signé, liste de pilotes nommés, runbook d'installation
   (`docs/GESTION_PROJET/RUNBOOK_*`), canal de feedback, **aucune promesse publique de
   téléchargement** (page `/download` = demande d'accès pilote, #3257).
2. Palier GA (si décidé) : vitrine et stores mis à jour **après** la sortie réelle (P03 §3.3),
   page `/download` avec installateurs publics, numéros de version, notes de version.
3. Chaque sortie desktop alimente `CHANGELOG.md` et la revue mensuelle (coût, incidents,
   enseignements → P04 §6).

## 7. Gardes & indicateurs

- **Existant :** `mobile-apps-ci.yml` (analyze/tests/gardes sur toutes les apps),
  `check-release-compat.sh`, `check-app-version-sync.sh`, secret scan, honnêteté vitrine #3257.
- **À créer (issues) :** scaffolds desktop manquants ; scripts melos `build:windows`/`build:macos` ;
  workflow `desktop-distribute.yml` ; extension `release-compat-matrix.json` (desktop) ;
  garde « pas de lien de téléchargement public sans Release GA ».
- **Indicateurs :** builds desktop verts par BC ; délai spec→pilote ; incidents pilote desktop ;
  nombre de promesses vitrine desktop (cible 0 avant GA).

## 8. Revue mensuelle — questions spécifiques

- [ ] Un BC a-t-il demandé un desktop ce mois-ci ? La checklist §2 a-t-elle été remplie ?
- [ ] Les coûts (signature, runners macOS) sont-ils toujours acceptés ?
- [ ] Les tests desktop couvrent-ils les parcours réellement utilisés en pilote ?

## 9. Historique

| Version | Date | Changement |
|---|---|---|
| v0.1 | 2026-09-09 | Création — état réel constaté (#3257, scaffolds 5/8 apps) + processus d'extraction desktop |
| v1.0 | 2026-09-11 | Ratification — audit de l'état réel du dépôt (registre `REGISTRE_PROTOCOLES.md`) |
