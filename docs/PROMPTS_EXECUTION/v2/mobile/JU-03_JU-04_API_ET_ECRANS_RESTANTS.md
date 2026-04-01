# JU-03 — Connexion à l'API réelle + QR Code + Notifications push
# Agent : Jules
# Durée : 4-6 heures
# Prérequis : CC-02 vert (backend Auth déployé) + JU-02 vert

---

## MISSION

Basculer du mock vers l'API réelle.
Zéro refactoring de logique — seulement le client réseau change.

---

## ÉTAPE 1 — Implémenter DioApiClient

```dart
// lib/core/network/dio_client.dart

class DioApiClient implements ApiClient {
  late final Dio _dio;

  DioApiClient(String token) {
    _dio = Dio(BaseOptions(
      baseUrl: AppConfig.apiBaseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ));

    // Intercepteur : refresh token si 401
    _dio.interceptors.add(InterceptorsWrapper(
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          // Token expiré → déconnecter proprement
          // (ne pas tenter de refresh — Sanctum n'a pas de refresh token)
          handler.next(error);
          return;
        }
        handler.next(error);
      },
    ));

    // Intercepteur : logger en mode debug
    if (kDebugMode) {
      _dio.interceptors.add(LogInterceptor(
        requestBody: true,
        responseBody: true,
        logPrint: (obj) => debugPrint(obj.toString()),
      ));
    }
  }

  @override
  Future<Map<String, dynamic>> login(String email, String password, String deviceName) async {
    try {
      final response = await Dio().post(
        '${AppConfig.apiBaseUrl}/auth/login',
        data: {'email': email, 'password': password, 'device_name': deviceName},
      );
      return response.data as Map<String, dynamic>;
    } on DioException catch (e) {
      throw _handleDioError(e);
    }
  }

  @override
  Future<Map<String, dynamic>?> getTodayAttendance() async {
    try {
      final response = await _dio.get('/attendance/today');
      return response.data as Map<String, dynamic>;
    } on DioException catch (e) {
      throw _handleDioError(e);
    }
  }

  @override
  Future<Map<String, dynamic>> checkIn({double? lat, double? lng}) async {
    try {
      final response = await _dio.post('/attendance/check-in', data: {
        if (lat != null) 'gps_lat': lat,
        if (lng != null) 'gps_lng': lng,
      });
      return response.data as Map<String, dynamic>;
    } on DioException catch (e) {
      throw _handleDioError(e);
    }
  }

  AppException _handleDioError(DioException e) {
    if (e.response != null) {
      final data = e.response!.data as Map<String, dynamic>?;
      final code = data?['error'] as String? ?? 'UNKNOWN_ERROR';
      final message = data?['message'] as String? ?? 'Erreur inconnue';
      return ApiException(code: code, message: message, statusCode: e.response!.statusCode);
    }
    if (e.type == DioExceptionType.connectionTimeout) {
      return const NetworkException(message: 'Délai de connexion dépassé');
    }
    return const NetworkException(message: 'Erreur réseau');
  }

  // Implémenter toutes les autres méthodes de l'interface ApiClient
}
```

---

## ÉTAPE 2 — Basculer vers l'API réelle

```dart
// lib/core/network/api_client_provider.dart
final apiClientProvider = Provider<ApiClient>((ref) {
  final token = ref.watch(authTokenProvider);
  if (AppConfig.useMock || token == null) {
    return MockApiClient();
  }
  return DioApiClient(token);
});
```

Pour basculer vers la prod :
```bash
flutter run --dart-define=USE_MOCK=false --dart-define=API_BASE_URL=https://api.leopardo-rh.com/api/v1
```

---

## ÉTAPE 3 — Gestion des erreurs API dans l'UI

Créer un widget `ErrorHandler` qui affiche les erreurs API de façon cohérente :

| Code d'erreur | Message affiché | Action |
|---|---|---|
| `GPS_OUTSIDE_ZONE` | "Vous n'êtes pas dans la zone autorisée" | SnackBar rouge |
| `ALREADY_CHECKED_IN` | "Vous avez déjà pointé l'arrivée" | SnackBar orange |
| `MISSING_CHECK_IN` | "Pointez d'abord l'arrivée" | SnackBar orange |
| `INSUFFICIENT_LEAVE_BALANCE` | "Solde insuffisant : X jour(s) disponible(s)" | Dialog |
| `COMPANY_SUSPENDED` | "Compte suspendu. Contactez votre administrateur." | Page dédiée |
| `SUBSCRIPTION_EXPIRED` | "Abonnement expiré." | Page dédiée |
| `NETWORK_ERROR` | "Vérifiez votre connexion internet" | SnackBar + retry |

---

## ÉTAPE 4 — QR Code pour le pointage

```dart
// lib/features/attendance/presentation/qr_scanner_screen.dart
// Utilise le package mobile_scanner

class QrScannerScreen extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      body: MobileScanner(
        onDetect: (capture) async {
          final barcode = capture.barcodes.first;
          if (barcode.rawValue == null) return;

          // Le QR code contient le token du site ou de l'appareil
          final qrToken = barcode.rawValue!;

          await ref.read(attendanceNotifierProvider.notifier)
              .checkInWithQr(qrToken: qrToken);

          if (context.mounted) context.pop();
        },
      ),
    );
  }
}
```

---

## ÉTAPE 5 — Push Notifications (FCM)

```dart
// lib/core/notifications/fcm_service.dart

class FcmService {
  static Future<void> initialize() async {
    await Firebase.initializeApp();
    final messaging = FirebaseMessaging.instance;

    await messaging.requestPermission(alert: true, badge: true, sound: true);

    // Enregistrer le token FCM auprès du backend
    final token = await messaging.getToken();
    if (token != null) {
      await _registerTokenWithBackend(token);
    }

    // Token se rafraîchit → re-enregistrer
    messaging.onTokenRefresh.listen(_registerTokenWithBackend);

    // Notification en foreground
    FirebaseMessaging.onMessage.listen((message) {
      _showLocalNotification(message);
    });

    // Tap sur notification → naviguer vers la bonne page
    FirebaseMessaging.onMessageOpenedApp.listen((message) {
      _handleNotificationTap(message);
    });
  }

  static void _handleNotificationTap(RemoteMessage message) {
    final type = message.data['type'] as String?;
    switch (type) {
      case 'absence_approved':
      case 'absence_rejected':
        // Naviguer vers /absences
        break;
      case 'payslip_available':
        // Naviguer vers /payroll
        break;
      case 'task.overdue':
        // Naviguer vers /tasks
        break;
    }
  }
}
```

---

## TESTS CRITIQUES JU-03

```dart
test('DioApiClient sends Authorization header', () async {
  final mockServer = MockServer(); // http_mock_adapter
  final client = DioApiClient('test_token_123');
  client.dio.httpClientAdapter = mockServer;

  mockServer.onGet('/attendance/today').reply(200, {'data': null});
  await client.getTodayAttendance();

  expect(mockServer.lastRequest?.headers['Authorization'], contains('Bearer test_token_123'));
});

test('GPS_OUTSIDE_ZONE error is properly converted to ApiException', () async {
  final client = DioApiClient('token');
  // Mock 422 response avec error: GPS_OUTSIDE_ZONE
  expect(
    () => client.checkIn(lat: 0, lng: 0),
    throwsA(isA<ApiException>().having((e) => e.code, 'code', 'GPS_OUTSIDE_ZONE')),
  );
});
```

---

## CRITÈRES DE VALIDATION — PORTE VERTE VERS JU-04

```
[ ] flutter run --dart-define=USE_MOCK=false → app démarre
[ ] Login avec vrai compte sur staging → token reçu et stocké
[ ] Check-in via l'API réelle → log créé en base, visible dans le backend
[ ] QR code → scan → check-in déclenché
[ ] FCM token envoyé au backend après login
[ ] Notification push reçue quand absence approuvée (tester manuellement)
[ ] Erreur GPS_OUTSIDE_ZONE affichée correctement dans l'UI
[ ] flutter test → 0 failure
```

---

# JU-04 — Bulletins de paie + Notifications + Profil + Polissage final
# Prérequis : JU-03 vert + CC-06 vert (paie backend OK)

---

## PARTIE A — BULLETINS DE PAIE

```dart
// PayrollListScreen
// - Liste des bulletins par mois (le plus récent en premier)
// - Chaque bulletin : mois/année + net à payer + badge statut
// - Tap → PayrollDetailScreen

// PayrollDetailScreen
// - Détail complet : brut, cotisations, IR, avances, pénalités, net
// - Bouton "Télécharger le PDF" → télécharge et ouvre avec le lecteur PDF natif
// - Affiche "Génération en cours..." si status = processing
```

```dart
// Téléchargement PDF
Future<void> downloadPayslipPdf(int payrollId) async {
  final response = await _dio.get(
    '/payroll/$payrollId/pdf',
    options: Options(responseType: ResponseType.bytes),
  );

  final dir = await getApplicationDocumentsDirectory();
  final file = File('${dir.path}/bulletin_$payrollId.pdf');
  await file.writeAsBytes(response.data);

  // Ouvrir avec le lecteur PDF natif (package open_filex)
  await OpenFilex.open(file.path);
}
```

---

## PARTIE B — ÉCRAN NOTIFICATIONS

```dart
// NotificationScreen
// - Liste toutes les notifications (lues + non lues)
// - Non lues en haut, badge bleu
// - Tap → marquer comme lue + naviguer vers le contenu correspondant
// - Bouton "Tout marquer comme lu"
// - Badge rouge sur l'icône de l'appbar (mis à jour en temps réel via SSE)
```

---

## PARTIE C — ÉCRAN PROFIL

```dart
// ProfileScreen
// - Photo de profil (avec possibilité d'en choisir une depuis la galerie)
// - Champs modifiables : téléphone
// - Champs non modifiables (affichés mais grisés) : nom, email, poste, département, salaire
// - Bouton "Changer le mot de passe" → bottomSheet avec ancien + nouveau + confirmation
// - Section "Mon solde de congés" avec barre de progression
// - Bouton déconnexion en bas
```

---

## PARTIE D — POLISSAGE FINAL

- Animations de transition entre écrans (GoRouter transitions)
- Pull-to-refresh sur toutes les listes
- État vide sur toutes les listes (illustration + message)
- Loading skeleton sur toutes les listes (shimmer effect)
- Mode hors-ligne : afficher les données en cache, message d'avertissement
- Thème sombre automatique selon le thème du téléphone

---

## CRITÈRES DE VALIDATION FINALE FLUTTER

```
[ ] flutter test → 0 failure
[ ] flutter analyze → 0 warning
[ ] Bulletin de paie : PDF téléchargeable et lisible
[ ] Notifications : badge mis à jour en temps réel
[ ] Profil : photo uploadée visible immédiatement
[ ] Mode hors-ligne : app fonctionne avec données en cache
[ ] RTL arabe : tous les écrans correctement mirrorés
[ ] Android release build : flutter build apk --release → APK valide
[ ] iOS release build : flutter build ios --release → archive valide
```

---

## COMMIT JU-03

```
feat: add DioApiClient connecting to real API backend
feat: add QR code scanner for attendance check-in
feat: add Firebase Cloud Messaging with push notification routing
test: add DioApiClient integration tests with mock HTTP server
```

## COMMIT JU-04

```
feat: add payroll list and detail screens with PDF download
feat: add notifications screen with real-time badge update
feat: add profile screen with photo upload and password change
feat: add pull-to-refresh, empty states, loading skeletons on all lists
feat: add offline mode with cached data and warning banner
```
