# SCÉNARIOS DE TEST MOBILE FLUTTER (COUVERTURE COMPLÈTE)

## Objectif

Fournir une couverture de test mobile exhaustive par rôle utilisateur et par fonctionnalité clé, avec exécution automatisable dans GitHub Actions.

## Rôles couverts

1. **Super Admin** (global)
2. **Owner / Company Admin**
3. **HR Manager**
4. **Manager d’équipe**
5. **Employee**
6. **Comptable / Finance** (si activé côté tenant)
7. **Utilisateur inactif / bloqué** (cas sécurité)

## Comptes de référence (recette)

- Super Admin: `admin@leopardo-rh.com` / `admin`
- Créer 1 compte de test par rôle tenant dans un tenant dédié QA.
- Prévoir au moins 1 utilisateur multi-rôles (ex: Manager + HR) pour tester la fusion des permissions.

## Pré-requis techniques

- Flutter stable + `flutter test` + `flutter test integration_test`
- API disponible via `API_BASE_URL`
- Jeu de données seedé (plans, langues, modèle RH, utilisateurs QA, congés, présences, paie)
- Notifications push/mock configurées si la feature existe
- Environnement isolé (pas la prod)

## Stratégie de test

1. **Widget tests**: composants UI + validation locale
2. **Integration tests**: parcours métier bout-en-bout
3. **Smoke tests device**: login + dashboard + action critique par rôle
4. **Non-régression API-mobile**: vérification des contrats JSON consommés par l’app

## Matrice des scénarios par domaine

### 1) Authentification et session (tous rôles)

- Login succès, login échec, mot de passe incorrect
- Compte inactif/bloqué refusé proprement
- Expiration token (401) => retour login sans crash
- Refresh token/session restaurée au redémarrage
- Logout volontaire => token supprimé et écrans protégés inaccessibles

### 2) Autorisations et navigation par rôle

- Super Admin voit uniquement les écrans globaux autorisés
- Owner/Admin voit administration tenant, pas la zone super admin
- Manager voit seulement son périmètre d’équipe
- Employee ne voit que ses données personnelles
- Tentative d’accès deep link à un écran interdit => blocage + message clair

### 3) Employés (CRUD + consultation)

- Liste employés (recherche, filtre, tri, pagination/scroll)
- Détail employé (identité, contrat, statut)
- Création employé (champs requis, validations métier)
- Mise à jour employé (cas normal + conflit de validation)
- Désactivation/réactivation employé

### 4) Présence et pointage

- Check-in / check-out nominal
- Double check-in interdit
- Historique journalier/hebdo/mensuel cohérent
- Retards/absences correctement marqués
- Cas fuseau horaire (Europe/Istanbul) cohérent entre UI et API
- Le bouton pointage affiche un état d'envoi strictement lié à l'action (`isPunching`), confirme le succès/échec par message utilisateur et ne dépend pas du chargement historique.
- Les appels pointage mobile consomment le backend Render par défaut, sauf `API_BASE_URL` explicite ou `USE_LOCAL_API=true`, et acceptent les payloads Laravel `data` ou `data.item`.
- Le menu haut de la page pointage employee ne doit pas proposer `Modifier` ; il ouvre les taches du jour, l'historique, les preferences et les parametres. Les demandes de modification restent accessibles par les menus des lignes de jour.

### 5) Congés et absences

- Employee crée une demande de congé
- Manager/HR approuve et refuse
- Solde congés mis à jour correctement
- Conflit de période (chevauchement) correctement refusé
- Statuts visibles et cohérents sur toutes les vues

### 6) Paie / compensation (si module activé)

- Consultation bulletins par Employee via **`GET /api/v1/me/pay-slips`** (Modules RH > bulletins ; mapping vers `PayrollRecord`)
- Managers : liste / actions legacy **`/payrolls`** (creation, validation) conserve le comportement historique
- Vue synthèse paie pour Finance/HR
- Détail composantes (brut, retenues, net) affiché sans erreur
- Blocage d’accès paie pour rôles non autorisés

### 7) Planning / tâches / RH opérationnel (si activé)

- Création/assignation tâche/planning par manager
- Employee voit ses tâches affectées
- Changement d’état (à faire/en cours/terminé)
- Filtre par période/équipe

### 8) Notifications et événements

- Réception d’une notification métier (congé approuvé, retard, etc.)
- Ouverture notification => redirection écran correct
- Notification non lue/lue synchronisée

### 9) Résilience réseau et UX

- Offline au lancement => état offline lisible
- Timeout API => message actionnable + retry
- Erreur 5xx => message générique non bloquant
- Loading states: skeleton/spinner visibles sans blocage UI
- Les actions critiques comme check-in/check-out utilisent des retries courts pour éviter un spinner long sans retour, puis exposent un message clair si Render ou le réseau ne répond pas.

### 10) Sécurité mobile

- Token absent/corrompu géré proprement
- Données sensibles non affichées après logout
- Pas d’escalade de privilèges côté UI (menus basés claims/roles)
- Protection contre double tap (actions critiques idempotentes côté UX)

## Scénarios end-to-end minimaux obligatoires par rôle

### Super Admin

1. Login
2. Consultation tenants
3. Consultation état global (plans/abonnements)
4. Logout
5. Garde CI `validate-mobile-workflow-contracts.ps1` : toute action `leopardo_platform_admin` doit rester limitee aux routes/endpoints `/platform/*`, sans route tenant employee/manager.

### Owner / Company Admin

1. Login
2. Création/modification employé
3. Validation d’une demande
4. Consultation reporting tenant

### HR Manager

1. Login
2. Gestion dossier employé
3. Approbation congé
4. Vérification impact présence/solde

### Manager

1. Login
2. Consultation équipe
3. Validation absence/pointage
4. Suivi des retards

### Employee

1. Login
2. Check-in + check-out
3. Soumission congé
4. Consultation historique et profil

### Finance (si activé)

1. Login
2. Consultation éléments paie
3. Vérification accès restreint aux zones RH sensibles

## Mapping CI GitHub Actions recommandé

### Widget tests (PR)

- `front/mobile/test/features/auth/*`
- `front/mobile/test/features/attendance/*`
- `front/mobile/test/features/mobile_surface_smoke_test.dart` couvre le rendu sans backend des surfaces principales: welcome, login, register, home, hub modules, absences, paie, notifications, equipe, settings, historique et resume mensuel.
- `front/mobile/test/features/leave/*`
- `front/mobile/test/features/employees/*`
- `front/mobile/test/features/payroll/*` (si module actif)
- `front/mobile/test/navigation/go_router_guard_test.dart` couvre les redirections GoRouter public/protege.
- `front/mobile/test/repositories/repository_contract_test.dart` couvre les contrats endpoints des repositories mobiles avec `ApiClient` intercepte sans reseau.
- `front/mobile/test/golden/critical_component_golden_test.dart` maintient des baselines structurelles pour paie mobile et conges tant que les goldens image ne sont pas generes localement.

### Integration tests (PR sur mobile)

- `front/mobile/integration_test/auth_role_matrix_test.dart`
- `front/mobile/integration_test/employee_crud_flow_test.dart`
- `front/mobile/integration_test/attendance_and_leave_flow_test.dart`
- `front/mobile/integration_test/payroll_access_control_test.dart`
- `front/mobile/integration_test/offline_timeout_error_flow_test.dart`

### Smoke build (toujours)

- `flutter analyze`
- `flutter test --coverage`
- `flutter build apk --debug --dart-define=API_BASE_URL=...`

## Rapport attendu CI (à archiver en artifact)

- Résumé par suite: passed/failed/skipped
- Couverture globale et par module
- Liste des scénarios KO avec stacktrace court
- Recommandation automatique: `GO` / `NO GO`

## Critères de validation finale

- 100% des scénarios critiques rôles passent
- 0 crash sur auth, présence, congé, employés
- Aucun accès non autorisé observé
- Backend tests verts + mobile tests verts + smoke build vert

## Extension i18n enterprise

### 11) Locales, variantes et fallback distant

- L'application accepte les variantes r-FR, r-BE, r-CA, r-SA, r-MA, 	r-TR, en-US, en-GB sans crash.
- Une variante est resolue vers une langue supportee quand aucun catalogue specifique n'existe encore.
- La direction RTL reste correcte pour l'arabe quel que soit le couple langue/pays.
- Un echec reseau sur le catalogue distant laisse l'application utilisable grace au catalogue embarque ou au dernier cache valide.
- Un 304 Not Modified reutilise bien le checksum et le cache local sans retelechargement inutile.
