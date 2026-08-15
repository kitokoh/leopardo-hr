# Feature Specification: Audit expert Mobile — 2026-08-15

**Feature Branch**: `qa-audit-expert-mobile-2026-08-15`

**Created**: 2026-08-15

**Status**: Draft

**Input**: Mission de test expert de la plateforme (session 2026-08-15) — audit des applications mobiles Flutter (`front/mobile_apps` : leopardo_core, employee, manager, hr, platform_admin, marketing) : endpoints, onboarding, authentification, i18n, logique, config plateforme. Base : `main` (`8a57dbf8`). Suite de l'audit expert mobile initié dans la même session (issues #2594-#2597, T001-T004).

## User Stories & Testing

### User Story M1 — L'onboarding mobile complète réellement les étapes (Priority: P1)

Les boutons « Terminer »/« Passer » des apps employee/manager/hr appellent `POST /onboarding-setup/{idNumérique}/complete|skip` — le backend n'enregistre que `PATCH /onboarding-setup/{stepKey}/complete|skip` (route par `step_key` **chaîne** : `company_info`, `first_department`, …) → **405** (verbe) et **404** (id vs clé). Le test de contrat `leopardo_hr/test/repositories/repository_contract_test.dart:181-182` codifie même le mauvais POST.

**Pourquoi P1** : le parcours d'onboarding employeur (étapes obligatoires) ne peut jamais se terminer depuis le mobile.

**Test indépendant** : `flutter test` des repos + smoke : `PATCH /onboarding-setup/company_info/complete` → 200 ; vérification du contrat `validate-mobile-workflow-contracts.ps1`.

**Acceptance Scenarios**:
1. **Given** un employeur sur l'écran d'onboarding, **When** il termine une étape, **Then** la requête est `PATCH /onboarding-setup/{step.key}/complete` et la progression augmente.
2. **Given** le modèle `OnboardingStep`, **When** il parse le checklist, **Then** il expose `stepKey` (champ serveur `step_key`) en plus de l'id.
3. **Given** le test de contrat, **When** il s'exécute, **Then** il valide le PATCH (pas le POST).

### User Story M2 — La navigation cabinet fonctionne dans l'app Manager (Priority: P1)

`leopardo_manager/lib/app.dart:179` pousse `'/cabinet/folder/${folder.id}'` mais le routeur manager ne déclare que `'/cabinet/:folderId'` (un seul segment) — les apps employee/HR utilisent correctement `/cabinet/folder/:folderId` → **GoRouter no-match**, l'exploration des dossiers du cabinet est morte dans l'app Manager.

**Test indépendant** : `check-mobile-manifest-routes.sh` + test de navigation (pousser `/cabinet/folder/1` → route trouvée).

**Acceptance Scenarios**:
1. **Given** l'app Manager, **When** on tape un dossier du cabinet, **Then** le routeur résout `/cabinet/folder/:folderId` (fini le no-match).
2. **Given** les 3 apps, **When** on compare les routeurs, **Then** le même pattern de route cabinet est utilisé partout.

### User Story M3 — La session mobile survit aux pannes réseau (Priority: P2)

`checkAuth()` (`auth_repository.dart:142,165`) **supprime le token à la moindre erreur**, y compris timeout/réseau : lancer l'app hors-ligne détruit la session et renvoie au `/welcome` — ce qui casse le pointage hors-ligne (#1290) qui exige un état authentifié. Par ailleurs l'intercepteur 401 (`api_client.dart:44-52`) appelle `onUnauthorized` mais **aucune app ne le branche** → après révocation de session, l'app reste dans un état « authentifié » où toutes les requêtes échouent.

**Test indépendant** : lancement hors-ligne → le token est conservé ; révocation serveur → l'app se déconnecte (pas d'UI fantôme).

**Acceptance Scenarios**:
1. **Given** un téléphone hors-ligne, **When** on ouvre l'app, **Then** le token est conservé et l'utilisateur reste connecté (mode hors-ligne).
2. **Given** une session révoquée (mot de passe changé), **When** une requête répond 401, **Then** l'app déconnecte proprement (logout local).

### User Story M4 — Un seul système d'authentification, sans écrasement (Priority: P2)

Deux flux d'auth (employé sanctum + `auth:user_api` régulier) partagent la **même clé** `auth_token` dans SecureStorage → se connecter via `/user-login` écrase silencieusement la session employé (et vice-versa) ; un token utilisateur périmé fait échouer `/auth/me` et peut tout effacer.

**Test indépendant** : connexion user puis employee → les deux sessions coexistent (clés distinctes) ou un flux unique est documenté.

**Acceptance Scenarios**:
1. **Given** un utilisateur connecté via `/user-login`, **When** il se connecte aussi en employé, **Then** les deux jetons sont stockés séparément (ou le flux user est explicitement séparé).

### User Story M5 — Les textes affichés sont encodés et localisés (Priority: P2)

- Chaînes **mojibake** rendues littéralement : « Aucune Ã©valuation », « PÃ©riode: », « Sessions rÃ©centes », « â€” » (evaluations, expenses, personal_space, smart_attendance + copies manager/hr).
- ~1 300 chaînes françaises codées en dur dans employee/manager/hr qui contournent le système ARB 4-locales (les écrans AR/TU/EN affichent du français).
- Labels mixtes dans un même écran (« Employe », « min », « Francais » vs « Français »).

**Test indépendant** : `rg` des motifs mojibake (`Ã©`, `â€™`, `â€”`) → 0 dans `lib/` ; échantillon d'écrans via `context.l10n`.

**Acceptance Scenarios**:
1. **Given** l'app employé, **When** on ouvre Évaluations, **Then** « Aucune évaluation » s'affiche correctement (UTF-8).
2. **Given** une locale AR/TU/EN, **When** on navigue, **Then** les libellés suivent la locale (pas de français codé en dur).

### User Story M6 — La monnaie et les nombres suivent le tenant (Priority: P2)

Fallback `'DZD'` codé en dur (`attendance_screen.dart:142,148,608`, `salary_advance_list_screen.dart:401`, manager `team_screen.dart:307,790`) : une entreprise FR/MA/SN (multi-pays activé côté backend) voit des DZD. Formatage `toStringAsFixed` sans séparateurs ni locale (`payroll_list_screen.dart:184,561`) ; `detail_schema.dart:208`/`list_schema.dart:167` codent `€` + « Oui »/« Non ».

**Test indépendant** : payload `employee.currency`/summary → symbole correct ; formatage `NumberFormat.currency(locale)`.

**Acceptance Scenarios**:
1. **Given** une entreprise marocaine, **When** on affiche une avance, **Then** le symbole est MAD (dérivé du payload tenant).
2. **Given** un montant 1234567.89, **When** on l'affiche, **Then** il est formaté selon la locale (séparateurs inclus).

### User Story M7 — Pas de doublons ni de fausses alertes (Priority: P2)

- `POST /salary-advances` hérite de 2 retries automatiques (timeout/502/503/504) → une requête réussie côté serveur mais timeout côté client crée une **demande d'avance en double** (la copie manager est correctement à 0 ; `ai_chat_repository` hérite aussi des retries pour un appel IA payant).
- Tout 403 → « Compte suspendu - contactez votre employeur » (mauvais diagnostic pour un simple défaut de permission) et le token est conservé → utilisateur bloqué dans une impasse.
- Détection hors-ligne incohérente : le repo manager ne matche que les messages contenant 'connexion'/'internet' alors que le repo employé teste les types `DioException` → le même pointage hors-ligne est mis en file dans une app et pas dans l'autre.

**Test indépendant** : `maxRetriesOverride: 0` sur les POST non idempotents ; payload 403 → message différencié ; test offline manager avec timeout Dio.

**Acceptance Scenarios**:
1. **Given** une avance soumise avec timeout, **When** la requête a réussi côté serveur, **Then** aucune seconde demande n'est créée.
2. **Given** un 403 pour permission manquante, **When** l'utilisateur agit, **Then** le message explique le défaut de permission (pas « compte suspendu »).

### User Story M8 — La config plateforme Android est correcte (Priority: P2)

`AndroidManifest.xml` (employee) déclare `FOREGROUND_SERVICE` sans `android:foregroundServiceType` ni `FOREGROUND_SERVICE_LOCATION` → crash Android 14+ pour le service de localisation smart-attendance ; le manifest **debug** n'autorise pas `cleartextTraffic` → l'URL de dev documentée `http://10.0.2.2:8000` échoue sur Android 9+. L'app platform_admin force `Locale('fr')` (locale utilisateur ignorée).

**Test indépendant** : `aapt dump` du manifest (ou lecture) — service type présent ; `Locale` résolu depuis les préférences.

**Acceptance Scenarios**:
1. **Given** Android 14, **When** le service de localisation démarre, **Then** aucun crash (type déclaré).
2. **Given** un build debug, **When** on pointe vers `http://10.0.2.2:8000`, **Then** le trafic clair est autorisé (debug uniquement).

### User Story M9 — Hygiène mobile (Priority: P3)

- `company_request_screen.dart:59-76` (écran mort) : poste `/company-requests` sans le champ `email` requis par le backend → 422 systématique s'il était branché.
- `main.dart:59` : Google web client ID codé en dur (non configurable par environnement) + identifiants démo `admin@leopardo-rh.com/password123` embarqués (bouton no-op en release).
- `main.dart:26` : `tracesSampleRate = 1.0` Sentry → 100 % des traces (PII potentielle) → 0.1-0.3 + scrub.
- `attendance_repository.dart:552,554` : `DateTime.parse(...)` sans garde null → crash sur données malformées.
- Méthodes mortes : `getMyPayrolls()` → `/payrolls` (manager-scope, inutilisé), `getDailySummary(employeeId)` jamais appelées — risque de dérive de contrat.
- `leopardo_marketing/stats_dashboard_screen.dart` : écran `/stats` = TODO stub avec données placeholder (déjà T002).
- `detail_schema.dart`/`list_schema.dart` : €/français codés en dur (code mort via Feature schema).

**Test indépendant** : `rg` des références mortes ; lecture manifest/main.dart.

**Acceptance Scenarios**:
1. **Given** la release, **When** on inspecte le bundle, **Then** aucun identifiant démo ni client ID d'environnement dev.
2. **Given** un événement Sentry, **When** il est envoyé, **Then** l'échantillonnage est borné et les PII exclusions actives.

## Edge Cases

- Onboarding : garder `GET /onboarding-setup/checklist` intact ; le modèle doit supporter id + step_key (champ additif).
- Cabinet : la route manager doit accepter `:folderId` quel que soit le type (string ou int).
- Offline : ne supprimer le token que sur 401 explicite ; les autres erreurs → « mode hors-ligne ».
- 403 : si le payload contient `suspended: true`, garder le message de suspension ; sinon permission.
- Android 14 : `FOREGROUND_SERVICE_LOCATION` + type `location` — ne pas autoriser le cleartext en release.
