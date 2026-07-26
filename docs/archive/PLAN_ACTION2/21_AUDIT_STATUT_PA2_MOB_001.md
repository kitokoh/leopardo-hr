# Audit et cloture PA2-MOB-001 — 2026-07-25

Statut: complete
Auteur: audit interne KiloClaw (agent)
Perimetre: ticket `PA2-MOB-001` de `02_BACKLOG_ATOMIQUE.md` / `03_GITHUB_PROJECT_IMPORT.csv` / GitHub Issue #971, verifie contre le code reel (`front/mobile_apps/leopardo_core/lib/core/widgets/startup_gate.dart`, `front/mobile_apps/*/android/app/build.gradle.kts`, `.github/workflows/mobile-distribute.yml`).

## Criteres d'acceptation du ticket

> Premier ecran visible sans await bloquant ; StartupGate degrade ; noms APK personnalises.

## Constat par critere

1. **Premier ecran visible sans await bloquant** — Deja FAIT. `StartupGate` (`leopardo_core`) lance son initialisation dans `addPostFrameCallback` (donc apres le premier frame, jamais avant) et affiche immediatement un ecran de chargement local (logo + message), pas un ecran noir. Utilise par les 4 apps mobiles (`main.dart` de `leopardo_employee`/`leopardo_manager`/`leopardo_hr`/`leopardo_platform_admin`).
2. **StartupGate degrade** — Deja FAIT. En cas de timeout (`criticalTimeout` 6s par defaut) ou d'erreur durant l'initialisation critique, un message degrade s'affiche ("Demarrage en mode securise" / "Initialisation partielle") avec un bouton "Continuer" et un auto-continue apres 1.2s — l'utilisateur n'est jamais bloque indefiniment. Deja sous test (`leopardo_core/test/core/widgets/startup_gate_test.dart`).
3. **Noms APK personnalises** — **Gap reel trouve et corrige par ce ticket.** Les 4 apps produisaient toutes un binaire nomme generiquement `app-release.apk` (ou `app-release.aab` en prod) par Flutter, ne se distinguant que par le chemin du dossier projet. Une fois telecharge depuis les artefacts CI (`actions/upload-artifact`) ou Firebase App Distribution, ce nom generique rendait le fichier difficile a identifier une fois sorti de son dossier d'origine.

## Correction apportee

`.github/workflows/mobile-distribute.yml` : nouvelle etape `Rename build output with a per-app identifiable filename`, executee juste apres la verification d'existence du build et avant l'upload (artefact CI + Firebase App Distribution). Renomme (pas copie, pour ne pas dupliquer l'espace disque du runner) le binaire en `<artifact_name>-<build_name>.<extension>` (ex: `leopardo-employee-employee-main-42.apk`), puis repointe `BUILD_FILE` vers le nouveau chemin pour que les etapes suivantes (upload artefact, distribution Firebase) continuent de fonctionner sans modification. Chaque app a deja un `artifact_name` distinct dans la matrice du workflow (`leopardo-employee`, `leopardo-manager`, `leopardo-platform-admin`, `leopardo-hr`), reutilise ici plutot que duplique.

**Alternative envisagee et ecartee** : renommer via `androidComponents.onVariants { ... }.outputFileName` cote Gradle Kotlin DSL (`build.gradle.kts` de chaque app). Ecartee car (a) necessiterait de dupliquer la logique de nommage dans 4 fichiers Gradle distincts, un par app, avec un risque de syntaxe Kotlin DSL non verifiable dans cet environnement (pas de Flutter/Gradle installe pour compiler un `./gradlew assembleRelease` de verification), alors que (b) le renommage cote workflow CI est un simple `mv` bash, verifiable par lecture directe et par validation YAML (`js-yaml`), et suffit au critere d'acceptation (le nom du fichier distribue/telecharge est personnalise) sans toucher a la configuration de build Android elle-meme.

## Verification

- Lecture directe de `startup_gate.dart` et de son test existant, confirmant les criteres 1 et 2 deja satisfaits.
- Recherche croisee (`grep -rn archivesBaseName|outputFileName` sur les 4 `build.gradle.kts`) confirmant l'absence de personnalisation avant ce ticket.
- Validation YAML de `.github/workflows/mobile-distribute.yml` apres modification (`js-yaml` via `node -e`) : fichier valide, cle `jobs.build-and-distribute` presente.
- Flutter/Gradle non disponibles dans cet environnement d'audit — la nouvelle etape est un `mv` bash pur (pas de dependance Gradle/Flutter), donc verifiable sans ces outils ; un build CI reel constituera la verification d'execution.
