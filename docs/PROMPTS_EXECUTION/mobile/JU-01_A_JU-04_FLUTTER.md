# PROMPT JU-01 — Initialisation Flutter
# Agent : JULES (Google AI Code Assistant)
# Phase : Sprint 0 — Semaine 1
# Durée estimée : 4-6 heures

---

## CONTEXTE

Tu es le développeur mobile principal de **Leopardo RH**.
Tu initialises l'application Flutter qui est la pièce centrale du produit.

---

## DOCUMENTS DE RÉFÉRENCE (lire avant de commencer)

- `06_PROMPT_MASTER_JULES_FLUTTER.md` → règles absolues Flutter
- `02_MODELES_DART/20_MODELES_DART_COMPLET.md` → toutes les classes Dart
- `03_MOCK_JSON/` → fichiers JSON pour développer sans API réelle
- `03_MOCK_JSON/README_INTEGRATION_FLUTTER.md` → comment intégrer les mocks

---

## STACK OBLIGATOIRE (non négociable)

```
Flutter       : 3.x (stable channel)
État          : Riverpod 2.x UNIQUEMENT (pas BLoC, pas Provider legacy)
Navigation    : GoRouter 13.x
HTTP          : Dio 5.x avec intercepteurs
Auth          : flutter_secure_storage
i18n          : flutter_localizations + intl (ARB files, 4 langues)
Push          : firebase_messaging
QR            : mobile_scanner
Biométrie     : local_auth
GPS           : geolocator
```

---

## TÂCHES DANS L'ORDRE

### Étape 1 — Créer le projet
```bash
flutter create --org com.leopardo leopardo_rh_mobile
cd leopardo_rh_mobile
```

### Étape 2 — pubspec.yaml
```yaml
dependencies:
  flutter:
    sdk: flutter
  flutter_riverpod: ^2.5.1
  riverpod_annotation: ^2.3.5
  go_router: ^13.2.0
  dio: ^5.4.3
  flutter_secure_storage: ^9.0.0
  firebase_core: ^2.29.0
  firebase_messaging: ^14.8.4
  mobile_scanner: ^5.0.0
  local_auth: ^2.2.0
  geolocator: ^12.0.0
  intl: ^0.19.0
  shared_preferences: ^2.2.3
  cached_network_image: ^3.3.1

dev_dependencies:
  flutter_test:
    sdk: flutter
  riverpod_generator: ^2.4.0
  build_runner: ^2.4.9
  mockito: ^5.4.4
  flutter_lints: ^3.0.0

flutter:
  generate: true
  assets:
    - assets/mock/
    - assets/images/
  fonts:
    - family: Inter
      fonts:
        - asset: assets/fonts/Inter-Regular.ttf
        - asset: assets/fonts/Inter-Bold.ttf
          weight: 700
```

### Étape 3 — Structure de dossiers
```
lib/
├── app/
│   ├── router/         → GoRouter routes
│   └── theme/          → ThemeData
├── features/
│   ├── auth/           → login screen, auth provider
│   ├── attendance/     → check-in/out, history
│   ├── absences/       → list, create, detail
│   ├── tasks/          → list, detail, comments
│   ├── payroll/        → list, detail, PDF viewer
│   └── notifications/  → list, badge
├── shared/
│   ├── models/         → toutes les classes Dart (depuis 20_MODELES_DART_COMPLET.md)
│   ├── services/       → DioService, MockDataService, NotificationService
│   ├── widgets/        → composants réutilisables
│   └── utils/          → formatters, validators
└── l10n/               → fichiers ARB (fr, ar, en, tr)
```

### Étape 4 — Copier les modèles Dart
Copier toutes les classes depuis `02_MODELES_DART/20_MODELES_DART_COMPLET.md` dans `lib/shared/models/`.

### Étape 5 — Copier les fichiers mock
Copier tous les fichiers JSON depuis `03_MOCK_JSON/` dans `assets/mock/`.

### Étape 6 — Implémenter AuthScreen avec mock
L'écran de login doit fonctionner en mode mock dès maintenant.
Flux : email + password → `MockDataService.load('mock_auth_login.json')` → stocker le token → naviguer vers HomeScreen.

### Étape 7 — GoRouter configuration de base
```dart
// lib/app/router/app_router.dart
final router = GoRouter(
  initialLocation: '/login',
  redirect: (context, state) {
    final isLoggedIn = ref.read(authProvider).isAuthenticated;
    final isOnLogin = state.matchedLocation == '/login';
    if (!isLoggedIn && !isOnLogin) return '/login';
    if (isLoggedIn && isOnLogin) return '/home';
    return null;
  },
  routes: [
    GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
    GoRoute(path: '/home', builder: (_, __) => const HomeScreen()),
    GoRoute(path: '/attendance/history', builder: (_, __) => const AttendanceHistoryScreen()),
    GoRoute(path: '/absences', builder: (_, __) => const AbsenceListScreen()),
    GoRoute(path: '/tasks', builder: (_, __) => const TaskListScreen()),
    GoRoute(path: '/payroll', builder: (_, __) => const PayrollListScreen()),
    GoRoute(path: '/notifications', builder: (_, __) => const NotificationScreen()),
    GoRoute(path: '/profile', builder: (_, __) => const ProfileScreen()),
  ],
);
```

---

## RÈGLES ABSOLUES (rappel)

1. Jamais de String hardcodée dans l'UI → `context.l10n.xxxKey`
2. Jamais de Dio direct dans un Widget → toujours via Repository
3. Jamais de `Navigator.push` direct → `context.go('/route')`
4. Jamais `DateTime.now()` pour afficher l'heure de pointage → utiliser l'heure serveur

---

## COMMIT ATTENDU

```
feat: initialize Flutter app with GoRouter, Riverpod, Dio, mock auth flow
```

## RÉSULTAT ATTENDU

- [ ] `flutter run` démarre sans erreur
- [ ] `flutter test` passe (tests de base)
- [ ] LoginScreen fonctionne avec mock → navigation vers HomeScreen
- [ ] Toutes les routes GoRouter configurées (même si les screens sont vides)

---
---

# PROMPT JU-02 — Écran de Pointage (PRIORITÉ ABSOLUE)
# Agent : JULES
# Phase : Semaine 3
# Prérequis : JU-01 terminé, CC-02 backend auth terminé

---

## MISSION

Développer l'écran de pointage — le cœur de l'application.
C'est le premier écran que les employés voient chaque matin.
Il doit être ultra-simple, ultra-rapide, zéro friction.

## FICHIERS À CRÉER

```
lib/features/attendance/
├── data/
│   ├── attendance_repository.dart
│   └── attendance_remote_data_source.dart
├── domain/
│   └── attendance_provider.dart   ← Riverpod provider
└── presentation/
    ├── home_screen.dart            ← écran principal avec grand bouton
    ├── attendance_confirm_screen.dart
    └── attendance_history_screen.dart
```

## FLUX EXACT — HomeScreen

```
1. Au démarrage → appeler getTodayAttendance()
   → Source : mock_attendance_today_A_not_checked.json OU mock_attendance_today_B_checked_in.json

2. Si data == null :
   → Afficher GRAND bouton "Pointer mon arrivée" (couleur verte)
   → Afficher heure prévue d'arrivée (context.expectedStart)

3. Si data != null ET check_out == null :
   → Afficher heure de check_in (celle du SERVEUR — pas DateTime.now())
   → Afficher GRAND bouton "Pointer mon départ" (couleur orange)
   → Afficher durée depuis check_in (calculée en temps réel)

4. Si data != null ET check_out != null :
   → Afficher résumé du jour (check_in, check_out, heures travaillées, statut)
   → Pas de bouton de pointage

5. Tap sur bouton check-in :
   → Demander permission GPS (geolocator)
   → Si permission refusée → afficher message explicatif
   → Appeler checkIn(lat, lng) via repository
   → Si GPS_OUTSIDE_ZONE → afficher "Vous n'êtes pas dans la zone de votre site"
   → Si succès → naviguer vers AttendanceConfirmScreen avec les données du log retourné
```

## AttendanceConfirmScreen

```
- Animation de succès (tick vert animé)
- Afficher : "Arrivée enregistrée à HH:MM" (heure du SERVEUR)
- Afficher statut : "À l'heure ✅" ou "En retard ⚠️"
- Bouton "Retour" → context.go('/home')
- Auto-retour après 3 secondes
```

## AttendanceHistoryScreen

```
- Calendrier mensuel avec couleurs par statut :
  · Vert (#4CAF50)   → ontime
  · Orange (#FF9800) → late
  · Rouge (#F44336)  → absent
  · Gris (#9E9E9E)   → holiday/leave
  · Blanc            → weekend
- Tap sur un jour → BottomSheet avec détail (check_in, check_out, heures, statut)
- Switcher mois (prev/next)
- Source : mock_attendance_history.json
```

## MODÈLE DART À UTILISER

`AttendanceLog` et `AttendanceTodayContext` depuis `20_MODELES_DART_COMPLET.md`.

## TESTS FLUTTER

```dart
testWidgets('shows check-in button when not checked in', (tester) async {
  // Mock getTodayAttendance() → return null
  await tester.pumpWidget(const ProviderScope(child: HomeScreen()));
  expect(find.text('Pointer mon arrivée'), findsOneWidget); // ou l10n key
});

testWidgets('shows check-out button when checked in', (tester) async {
  // Mock getTodayAttendance() → return AttendanceLog with checkIn, no checkOut
  expect(find.text('Pointer mon départ'), findsOneWidget);
});
```

## COMMIT ATTENDU
```
feat: add attendance check-in/out screens with GPS validation and server timestamp display
```

---
---

# PROMPT JU-03 — Connexion API Réelle (après backend prêt)
# Agent : JULES
# Phase : Après semaine 4 (quand CC-02 et CC-03 backend sont déployés)

---

## MISSION

Basculer du mode mock vers l'API réelle.

## ÉTAPES DANS L'ORDRE

### 1. Configurer l'URL de l'API
```dart
// lib/shared/services/dio_service.dart
const String apiBaseUrl = 'https://api.leopardo-rh.com/v1';
// En dev : 'http://10.0.2.2:8000/api/v1' (Android emulator) ou 'http://localhost:8000/api/v1'
```

### 2. Implémenter le DioInterceptor (auth)
```dart
class AuthInterceptor extends Interceptor {
  final FlutterSecureStorage _storage;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    final token = await _storage.read(key: 'auth_token');
    if (token != null) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401) {
      // Token expiré → déconnecter et rediriger vers /login
      await _storage.deleteAll();
      // router.go('/login'); // via un global navigatorKey
    }
    handler.next(err);
  }
}
```

### 3. Désactiver le mock
```dart
// lib/shared/services/mock_data_service.dart
static const bool useMock = false; // ← changer ici
```

### 4. Gestion des erreurs API standardisée
```dart
// 422 → afficher le premier message d'erreur du champ concerné
// 503 → afficher "Serveur indisponible, réessayez dans quelques instants"
// Network error → afficher "Vérifiez votre connexion internet"
```

### 5. Enregistrer le FCM token
```dart
// Au premier login réussi, appeler POST /auth/device/fcm
final fcmToken = await FirebaseMessaging.instance.getToken();
if (fcmToken != null) {
  await authRepository.registerFcmToken(fcmToken, Platform.operatingSystem);
}
```

### 6. Tester sur appareil physique Android
- Tester check-in avec vraie position GPS
- Vérifier que l'heure affichée est celle du serveur
- Vérifier que les push notifications arrivent

## COMMIT ATTENDU
```
feat: switch from mock data to live API with auth interceptor and error handling
```

---
---

# PROMPT JU-04 — Modules Absences, Tâches, Paie, Notifications
# Agent : JULES
# Phase : Semaines 5-10
# Prérequis : JU-03 terminé (API réelle connectée)

---

## MISSION

Développer les écrans secondaires de l'application.
Utiliser les mocks JSON pour développer en parallèle du backend.

## MODULE ABSENCES

```
AbsenceListScreen    → liste des absences + solde en grand (source: mock_absences.json)
AbsenceCreateScreen  → formulaire demande (type, dates, commentaire)
AbsenceDetailScreen  → détail + statut + raison de refus éventuelle
```

Règles UI :
- Solde de congés affiché en grand chiffre coloré en haut (vert si > 5j, orange si 2-5j, rouge si < 2j)
- Couleur du statut : pending=orange, approved=vert, rejected=rouge, cancelled=gris

## MODULE TÂCHES

```
TaskListScreen     → liste avec filtres (statut, priorité)
TaskDetailScreen   → détail + checklist interactive + commentaires
```

Règles UI :
- Badge urgente en rouge pour priority=urgent
- Barre de progression de la checklist
- Afficher "En retard" si due_date < maintenant ET status != done

## MODULE PAIE

```
PayrollListScreen   → 6 derniers bulletins (source: mock_payroll.json)
PayrollDetailScreen → détail avec déductions
PayrollPdfScreen    → viewer PDF intégré (flutter_pdfview ou url_launcher)
```

## MODULE NOTIFICATIONS

```
NotificationScreen  → liste (source: mock_notifications.json)
                    → marquer lu au tap
                    → badge sur l'icône cloche dans la nav
NotificationBadge   → GET /notifications/count toutes les 5 minutes (polling)
```

## COMMIT ATTENDU
```
feat: add absence, task, payroll and notification screens
```
