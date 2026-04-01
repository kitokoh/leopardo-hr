# JU-01 — Initialisation Flutter + Auth avec mock
# Agent : Jules
# Durée : 4-6 heures
# Prérequis : Aucun côté backend (développement en parallèle avec mock)

---

## RÈGLE FONDAMENTALE DE CE PROMPT

Tu développes sur mock UNIQUEMENT pour les semaines 1-2.
Le mock simule l'API réelle. Quand le backend sera prêt (CC-02 vert),
la connexion se fera uniquement en changeant une variable d'environnement.
Zéro refactoring si tu respectes les interfaces dès maintenant.

---

## PRÉREQUIS

```bash
flutter --version   # 3.x stable
dart --version      # 3.x
```

---

## ÉTAPE 1 — Créer le projet

```bash
flutter create --org com.leopardo --platforms=android,ios leopardo_rh_mobile
cd leopardo_rh_mobile
flutter pub add flutter_riverpod riverpod_annotation go_router dio flutter_secure_storage
flutter pub add firebase_core firebase_messaging mobile_scanner local_auth geolocator
flutter pub add intl shared_preferences cached_network_image
flutter pub add --dev riverpod_generator build_runner mockito flutter_lints
```

---

## ÉTAPE 2 — Structure de dossiers (créer vides)

```
lib/
├── app/
│   ├── router/app_router.dart
│   └── theme/app_theme.dart
├── core/
│   ├── config/
│   │   ├── app_config.dart         ← USE_MOCK = true/false
│   │   └── api_endpoints.dart      ← tous les chemins URL centralisés
│   ├── network/
│   │   ├── dio_client.dart
│   │   ├── api_client.dart
│   │   └── mock_client.dart        ← implémente la même interface
│   └── errors/
│       ├── app_exception.dart
│       └── error_handler.dart
├── features/
│   ├── auth/
│   │   ├── data/
│   │   │   ├── auth_repository.dart
│   │   │   └── auth_repository_impl.dart
│   │   ├── domain/
│   │   │   └── auth_provider.dart
│   │   └── presentation/
│   │       ├── login_screen.dart
│   │       └── login_controller.dart
│   ├── attendance/
│   │   ├── data/
│   │   ├── domain/
│   │   └── presentation/
│   │       ├── attendance_home_screen.dart  ← écran principal
│   │       ├── attendance_history_screen.dart
│   │       └── checkin_button_widget.dart
│   ├── absences/
│   ├── tasks/
│   ├── payroll/
│   └── notifications/
├── shared/
│   ├── models/              ← copier depuis docs/MODELES_DART_COMPLET.md
│   ├── widgets/
│   └── utils/
└── l10n/
    ├── app_fr.arb
    ├── app_ar.arb
    ├── app_tr.arb
    └── app_en.arb
```

---

## ÉTAPE 3 — AppConfig (interrupteur mock/API)

```dart
// lib/core/config/app_config.dart
class AppConfig {
  // Changer à false quand le backend CC-02 est validé
  static const bool useMock = bool.fromEnvironment('USE_MOCK', defaultValue: true);
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://localhost:8000/api/v1',
  );
}
```

```dart
// lib/core/network/api_client.dart
// Interface abstraite — le mock et le vrai client l'implémentent tous les deux

abstract class ApiClient {
  Future<Map<String, dynamic>> login(String email, String password, String deviceName);
  Future<Map<String, dynamic>> getProfile();
  Future<Map<String, dynamic>?> getTodayAttendance();
  Future<Map<String, dynamic>> checkIn({double? lat, double? lng});
  Future<Map<String, dynamic>> checkOut({double? lat, double? lng});
  Future<List<Map<String, dynamic>>> getAbsences();
  Future<Map<String, dynamic>> requestAbsence(Map<String, dynamic> data);
  Future<List<Map<String, dynamic>>> getPayslips();
  Future<List<Map<String, dynamic>>> getTasks();
  Future<List<Map<String, dynamic>>> getNotifications();
}
```

```dart
// lib/core/network/mock_client.dart
// Lit les fichiers JSON depuis assets/mock/
// Simule un délai réseau de 300-600ms

class MockApiClient implements ApiClient {
  Future<T> _simulateNetwork<T>(Future<T> Function() action) async {
    await Future.delayed(Duration(milliseconds: 300 + Random().nextInt(300)));
    return action();
  }

  @override
  Future<Map<String, dynamic>> login(String email, String password, String deviceName) async {
    return _simulateNetwork(() async {
      final data = await rootBundle.loadString('assets/mock/mock_auth_login.json');
      return json.decode(data) as Map<String, dynamic>;
    });
  }

  // Implémenter toutes les méthodes en lisant les fichiers mock correspondants
  // mock_attendance_today_A_not_checked.json ou mock_attendance_today_B_checked_in.json
  // Simuler le state : si checkIn a été appelé, retourner B au prochain getTodayAttendance
}
```

---

## ÉTAPE 4 — Copier les modèles Dart

Copier TOUTES les classes depuis `docs/dossierdeConception/16_MODELES_DART/20_MODELES_DART_COMPLET.md`
dans `lib/shared/models/`.

Vérifier que chaque modèle a :
- `fromJson()` factory constructor
- `toJson()` method
- `copyWith()` method
- Les enums correspondants

---

## ÉTAPE 5 — Copier les fichiers mock

```bash
mkdir -p assets/mock
# Copier depuis docs/PROMPTS_EXECUTION/MOCK_JSON/ tous les fichiers .json
cp ../../docs/PROMPTS_EXECUTION/MOCK_JSON/*.json assets/mock/
```

Déclarer dans `pubspec.yaml` :
```yaml
flutter:
  assets:
    - assets/mock/
```

---

## ÉTAPE 6 — AuthProvider avec Riverpod

```dart
// lib/features/auth/domain/auth_provider.dart

@riverpod
class AuthNotifier extends _$AuthNotifier {
  @override
  AuthState build() => const AuthState.unauthenticated();

  Future<void> login(String email, String password) async {
    state = const AuthState.loading();
    try {
      final client = ref.read(apiClientProvider);
      final response = await client.login(email, password, await _getDeviceName());

      final token = response['data']['token'] as String;
      final user = Employee.fromJson(response['data']['user']);
      final company = Company.fromJson(response['data']['company']);

      // Stocker le token de façon sécurisée
      await ref.read(secureStorageProvider).write(key: 'auth_token', value: token);

      state = AuthState.authenticated(user: user, company: company, token: token);
    } catch (e) {
      state = AuthState.error(message: e.toString());
    }
  }

  Future<void> logout() async {
    await ref.read(secureStorageProvider).delete(key: 'auth_token');
    state = const AuthState.unauthenticated();
  }
}
```

---

## ÉTAPE 7 — GoRouter avec redirections

```dart
// lib/app/router/app_router.dart

final routerProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authNotifierProvider);

  return GoRouter(
    initialLocation: '/login',
    redirect: (context, state) {
      final isLoggedIn = authState is AuthStateAuthenticated;
      final isOnLogin = state.matchedLocation == '/login';

      if (!isLoggedIn && !isOnLogin) return '/login';
      if (isLoggedIn && isOnLogin) return '/home';
      return null;
    },
    routes: [
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      GoRoute(
        path: '/home',
        builder: (_, __) => const AttendanceHomeScreen(), // Écran principal = pointage
        routes: [
          GoRoute(path: 'history', builder: (_, __) => const AttendanceHistoryScreen()),
        ],
      ),
      GoRoute(path: '/absences', builder: (_, __) => const AbsenceListScreen()),
      GoRoute(path: '/absences/new', builder: (_, __) => const AbsenceCreateScreen()),
      GoRoute(path: '/tasks', builder: (_, __) => const TaskListScreen()),
      GoRoute(path: '/payroll', builder: (_, __) => const PayrollListScreen()),
      GoRoute(path: '/notifications', builder: (_, __) => const NotificationScreen()),
      GoRoute(path: '/profile', builder: (_, __) => const ProfileScreen()),
    ],
  );
});
```

---

## ÉTAPE 8 — LoginScreen (fonctionnel avec mock)

```dart
// lib/features/auth/presentation/login_screen.dart

class LoginScreen extends ConsumerWidget {
  const LoginScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authNotifierProvider);
    final emailController = TextEditingController();
    final passwordController = TextEditingController();

    ref.listen<AuthState>(authNotifierProvider, (_, next) {
      if (next is AuthStateError) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(next.message), backgroundColor: Colors.red),
        );
      }
    });

    return Scaffold(
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // Logo Leopardo RH
            Image.asset('assets/images/logo.png', height: 80),
            const SizedBox(height: 40),

            TextFormField(
              controller: emailController,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(labelText: 'Email'),
            ),
            const SizedBox(height: 16),

            TextFormField(
              controller: passwordController,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Mot de passe'),
            ),
            const SizedBox(height: 24),

            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: authState is AuthStateLoading
                    ? null
                    : () => ref.read(authNotifierProvider.notifier).login(
                          emailController.text.trim(),
                          passwordController.text,
                        ),
                child: authState is AuthStateLoading
                    ? const CircularProgressIndicator(color: Colors.white)
                    : const Text('Se connecter'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
```

---

## ÉTAPE 9 — i18n (4 langues)

```json
// lib/l10n/app_fr.arb
{
  "@@locale": "fr",
  "loginTitle": "Connexion",
  "loginButton": "Se connecter",
  "loginEmailLabel": "Adresse e-mail",
  "loginPasswordLabel": "Mot de passe",
  "checkInButton": "Pointer l'arrivée",
  "checkOutButton": "Pointer le départ",
  "attendanceStatusOntime": "À l'heure",
  "attendanceStatusLate": "En retard",
  "attendanceStatusAbsent": "Absent",
  "absencesTitle": "Mes congés",
  "newAbsenceButton": "Nouvelle demande",
  "payrollTitle": "Bulletins de paie",
  "tasksTitle": "Mes tâches",
  "notificationsTitle": "Notifications",
  "profileTitle": "Mon profil",
  "errorInvalidCredentials": "Email ou mot de passe incorrect.",
  "errorNetworkTimeout": "Erreur réseau. Vérifiez votre connexion.",
  "errorGeneric": "Une erreur inattendue s'est produite."
}
```

Créer les équivalents `app_ar.arb`, `app_tr.arb`, `app_en.arb`.
Configurer `flutter_localizations` dans `pubspec.yaml` et `MaterialApp`.

---

## TESTS À ÉCRIRE

```dart
// test/features/auth/auth_provider_test.dart

void main() {
  group('AuthNotifier', () {
    test('login with mock client returns authenticated state', () async {
      final container = ProviderContainer(overrides: [
        apiClientProvider.overrideWithValue(MockApiClient()),
      ]);

      await container.read(authNotifierProvider.notifier).login('test@test.com', 'pass123');

      final state = container.read(authNotifierProvider);
      expect(state, isA<AuthStateAuthenticated>());
      expect((state as AuthStateAuthenticated).user.email, isNotEmpty);
    });

    test('token is stored in secure storage after login', () async {
      final mockStorage = MockSecureStorage();
      final container = ProviderContainer(overrides: [
        apiClientProvider.overrideWithValue(MockApiClient()),
        secureStorageProvider.overrideWithValue(mockStorage),
      ]);

      await container.read(authNotifierProvider.notifier).login('test@test.com', 'pass');

      verify(mockStorage.write(key: 'auth_token', value: anyNamed('value'))).called(1);
    });

    test('logout clears token from storage', () async {
      final mockStorage = MockSecureStorage();
      final container = ProviderContainer(overrides: [
        secureStorageProvider.overrideWithValue(mockStorage),
      ]);

      await container.read(authNotifierProvider.notifier).logout();

      final state = container.read(authNotifierProvider);
      expect(state, isA<AuthStateUnauthenticated>());
    });
  });
}
```

---

## CRITÈRES DE VALIDATION — PORTE VERTE VERS JU-02

```
[ ] flutter run → app démarre sans erreur
[ ] LoginScreen → login avec email quelconque → navigation vers HomeScreen
[ ] Le token est stocké dans flutter_secure_storage
[ ] GoRouter → redirect fonctionne (non connecté → /login, connecté → /home)
[ ] flutter test → 0 failure
[ ] Toutes les routes de base naviguent sans crash
[ ] i18n : les 4 fichiers ARB sont créés et chargés (tester en changeant locale)
[ ] AppConfig.useMock = true → lecture du fichier mock JSON
```

---

## COMMIT

```
feat: initialize Flutter project with Riverpod, GoRouter, mock API client
feat: add LoginScreen with mock authentication and secure token storage
feat: add 4-language i18n with French, Arabic, Turkish, English
feat: add all Dart models from conception documents
test: add auth provider tests with mock client
```
