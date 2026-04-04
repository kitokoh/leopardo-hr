# MVP-06 — App Flutter (3 écrans)
# Agent : Jules / Claude Code
# Durée : 6-8 heures
# Prérequis : MVP-04 vert (API estimation fonctionnelle)
# Note : peut être fait en parallèle avec MVP-05

---

## CE QUE TU FAIS

Créer l'app Flutter minimale avec 3 écrans : Login, Pointage + gain du jour, Historique.
L'app se connecte à l'API Laravel (MVP-01 à MVP-04).

---

## AVANT DE COMMENCER

1. Lis `PILOTAGE.md` à la racine (5 min)
2. Vérifie que l'API est en ligne : `GET /api/v1/health → {"status":"ok"}`
3. Lis `docs/dossierdeConception/16_MODELES_DART/20_MODELES_DART_COMPLET.md` — structures Employee et AttendanceLog

---

## INIT FLUTTER

```bash
cd mobile/
flutter create . --project-name leopardo_rh --org com.leopardo
```

### Packages à installer (pubspec.yaml)

```yaml
dependencies:
  flutter_riverpod: ^2.4.0     # State management
  go_router: ^14.0.0            # Navigation
  dio: ^5.4.0                   # HTTP client
  flutter_secure_storage: ^9.0.0  # Stockage token
  intl: ^0.19.0                 # Formatage dates/montants

dev_dependencies:
  flutter_test:
    sdk: flutter
  mockito: ^5.4.0
```

> ⚠️ Ne PAS installer : firebase_messaging, geolocator, camera, flutter_bloc

---

## ÉCRANS À CRÉER

### Écran 1 — Login

```
Champs : Email + Password
Bouton : "Se connecter"
Action :
  1. POST /api/v1/auth/login → token + user + company
  2. Stocker token dans flutter_secure_storage
  3. Naviguer vers écran Pointage
Erreurs :
  - 401 → "Email ou mot de passe incorrect"
  - 403 → "Compte suspendu — contactez votre employeur"
  - Réseau → "Impossible de se connecter au serveur"
```

### Écran 2 — Pointage (écran principal)

```
Contenu :
  ┌─────────────────────────────┐
  │  Bonjour {prénom} 👋        │
  │  {Nom entreprise}           │
  ├─────────────────────────────┤
  │                             │
  │     [ CHECK IN ]            │  ← Gros bouton vert (ou CHECK OUT si déjà in)
  │     ou                      │
  │     [ CHECK OUT ]           │  ← Gros bouton rouge
  │                             │
  │  Arrivée : 08:02            │
  │  Heures : 4h30 (en cours)  │
  ├─────────────────────────────┤
  │  💰 Gain estimé aujourd'hui │
  │     ~3,200 DA               │
  │  ⏱  Heures sup : 0h        │
  │                             │
  │  "Estimation — net final    │
  │   calculé en fin de mois"   │
  ├─────────────────────────────┤
  │  [Voir historique]          │
  │  [Déconnexion]              │
  └─────────────────────────────┘

Logique :
  1. Au chargement : GET /attendance/today → déterminer état (in/out/pas commencé)
  2. GET /employees/{self}/daily-summary → gain estimé
  3. Check In : POST /attendance/check-in → rafraîchir l'affichage
  4. Check Out : POST /attendance/check-out → rafraîchir l'affichage
```

### Écran 3 — Historique

```
Contenu :
  ┌─────────────────────────────┐
  │  ← Retour | Historique      │
  ├─────────────────────────────┤
  │  Avril 2026                 │
  │  ─────────────────────────  │
  │  03/04  08:02 → 17:15  9h  │  ✅
  │  02/04  08:30 → 17:00  8h  │  🟡 Retard
  │  01/04  —                   │  🔴 Absent
  │  31/03  07:55 → 18:00  10h │  ✅ +2h sup
  │  ...                        │
  ├─────────────────────────────┤
  │  Total mois : 18 jours      │
  │  Total heures : 162h        │
  │  Heures sup : 8h            │
  └─────────────────────────────┘

Logique :
  1. GET /attendance?month=2026-04 → liste paginée
  2. Scroll infini pour charger plus
  3. Couleur par statut : vert=ontime, orange=late, rouge=absent
```

---

## ARCHITECTURE FLUTTER

```
lib/
├── main.dart
├── app.dart                     → MaterialApp + GoRouter + ProviderScope
├── core/
│   ├── api/
│   │   ├── api_client.dart      → Dio + intercepteur token + base URL
│   │   └── api_exceptions.dart
│   ├── storage/
│   │   └── secure_storage.dart  → Wrapper flutter_secure_storage
│   └── theme/
│       └── app_theme.dart       → Dark theme + couleurs + typo
├── features/
│   ├── auth/
│   │   ├── data/auth_repository.dart
│   │   ├── providers/auth_provider.dart
│   │   └── screens/login_screen.dart
│   ├── attendance/
│   │   ├── data/attendance_repository.dart
│   │   ├── providers/attendance_provider.dart
│   │   ├── screens/attendance_screen.dart
│   │   └── screens/history_screen.dart
│   └── estimation/
│       ├── data/estimation_repository.dart
│       └── providers/estimation_provider.dart
└── models/
    ├── employee.dart
    ├── company.dart
    ├── attendance_log.dart
    └── daily_summary.dart
```

---

## DESIGN

- **Theme :** Dark mode (#0f172a fond, #1e293b cartes, #10b981 accent vert)
- **Bouton Check-in :** Cercle animé, vert, pulsation douce
- **Bouton Check-out :** Cercle rouge
- **Typo :** Inter ou Roboto
- **Animations :** Micro-animations sur check-in/check-out (scale + fade)
- **Monnaie :** Formatage selon la devise company (ex: "3,200 DA")

---

## TESTS À ÉCRIRE

```dart
// test/features/auth/login_screen_test.dart
testWidgets('shows error on invalid credentials', ...);
testWidgets('navigates to attendance on success', ...);

// test/features/attendance/attendance_screen_test.dart
testWidgets('shows CHECK IN button when not checked in', ...);
testWidgets('shows CHECK OUT button when checked in', ...);
testWidgets('shows daily summary after check-in', ...);

// test/features/attendance/history_screen_test.dart
testWidgets('displays attendance list', ...);
testWidgets('shows correct status colors', ...);
```

---

## PORTE VERTE → Beta

```
[ ] flutter test → 0 failure
[ ] Login → token stocké, navigation vers pointage
[ ] Check-in → horodatage affiché, bouton bascule vers Check-out
[ ] Check-out → heures calculées, gain estimé affiché
[ ] Historique → liste des 30 derniers jours avec statuts colorés
[ ] Gestion erreurs : 401 → retour login, 403 → message clair, réseau → message clair
[ ] Dark theme cohérent sur les 3 écrans
```

---

## COMMIT

```
feat(mobile): Flutter app with login, attendance, daily summary
feat(mobile): history screen with status colors + monthly totals
test(mobile): widget tests for login, attendance, history
```
