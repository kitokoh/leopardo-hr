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

### 5) Congés et absences

- Employee crée une demande de congé
- Manager/HR approuve et refuse
- Solde congés mis à jour correctement
- Conflit de période (chevauchement) correctement refusé
- Statuts visibles et cohérents sur toutes les vues

### 6) Paie / compensation (si module activé)

- Consultation bulletins par Employee
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

- `mobile/test/features/auth/*`
- `mobile/test/features/attendance/*`
- `mobile/test/features/leave/*`
- `mobile/test/features/employees/*`
- `mobile/test/features/payroll/*` (si module actif)

### Integration tests (PR sur mobile)

- `mobile/integration_test/auth_role_matrix_test.dart`
- `mobile/integration_test/employee_crud_flow_test.dart`
- `mobile/integration_test/attendance_and_leave_flow_test.dart`
- `mobile/integration_test/payroll_access_control_test.dart`
- `mobile/integration_test/offline_timeout_error_flow_test.dart`

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
