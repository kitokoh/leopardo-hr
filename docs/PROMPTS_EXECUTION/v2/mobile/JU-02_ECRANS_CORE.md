# JU-02 — Écrans Pointage + Absences + Tâches (avec mock)
# Agent : Jules
# Durée : 6-8 heures
# Prérequis : JU-01 vert (login fonctionne avec mock)

---

## RÈGLE MOCK → API RÉELLE

Tous ces écrans fonctionnent d'abord avec le mock.
La connexion à l'API réelle se fait dans JU-03 en changeant `AppConfig.useMock = false`.
Aucun refactoring nécessaire si tu respectes l'interface `ApiClient`.

---

## PARTIE A — ÉCRAN POINTAGE (écran principal de l'app)

### AttendanceHomeScreen

Cet écran est le cœur de l'application — l'employé le voit en premier chaque jour.

**Ce qu'il doit afficher :**
- Heure actuelle (mise à jour chaque seconde)
- Bouton principal : "Pointer l'arrivée" ou "Pointer le départ" selon l'état du jour
- Si pointé : heure d'arrivée + temps écoulé depuis le check-in
- Statut du jour : À l'heure / En retard / Incomplet
- Mini-historique : 3 derniers jours de pointage

```dart
// lib/features/attendance/domain/attendance_provider.dart

@riverpod
class AttendanceNotifier extends _$AttendanceNotifier {
  @override
  Future<AttendanceState> build() async {
    final client = ref.read(apiClientProvider);
    final todayData = await client.getTodayAttendance();
    if (todayData == null) {
      return const AttendanceState.notCheckedIn();
    }
    final log = AttendanceLog.fromJson(todayData['data']);
    if (log.checkOut != null) {
      return AttendanceState.completed(log: log);
    }
    return AttendanceState.checkedIn(log: log);
  }

  Future<void> checkIn({double? lat, double? lng}) async {
    state = const AsyncLoading();
    final client = ref.read(apiClientProvider);
    try {
      final response = await client.checkIn(lat: lat, lng: lng);
      final log = AttendanceLog.fromJson(response['data']);
      state = AsyncData(AttendanceState.checkedIn(log: log));
    } catch (e) {
      state = AsyncError(e, StackTrace.current);
    }
  }

  Future<void> checkOut({double? lat, double? lng}) async {
    state = const AsyncLoading();
    final client = ref.read(apiClientProvider);
    try {
      final response = await client.checkOut(lat: lat, lng: lng);
      final log = AttendanceLog.fromJson(response['data']);
      state = AsyncData(AttendanceState.completed(log: log));
    } catch (e) {
      state = AsyncError(e, StackTrace.current);
    }
  }
}
```

**UI du bouton principal :**

```dart
// lib/features/attendance/presentation/checkin_button_widget.dart

class CheckInButton extends ConsumerWidget {
  const CheckInButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final attendanceAsync = ref.watch(attendanceNotifierProvider);

    return attendanceAsync.when(
      loading: () => const CircularProgressIndicator(),
      error: (e, _) => Text('Erreur : $e'),
      data: (state) {
        return switch (state) {
          AttendanceStateNotCheckedIn() => _buildCheckInButton(ref, context),
          AttendanceStateCheckedIn(log: final log) => _buildCheckOutButton(ref, context, log),
          AttendanceStateCompleted() => _buildCompletedState(context, state),
        };
      },
    );
  }

  Widget _buildCheckInButton(WidgetRef ref, BuildContext context) {
    return ElevatedButton.icon(
      icon: const Icon(Icons.login, size: 28),
      label: Text(
        context.l10n.checkInButton,
        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
      ),
      style: ElevatedButton.styleFrom(
        backgroundColor: Theme.of(context).colorScheme.primary,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 40, vertical: 20),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      ),
      onPressed: () async {
        final position = await _getGpsPosition();
        await ref.read(attendanceNotifierProvider.notifier).checkIn(
          lat: position?.latitude,
          lng: position?.longitude,
        );
      },
    );
  }

  // _buildCheckOutButton : même style, couleur orange, icône logout
  // _buildCompletedState : résumé de la journée avec heures travaillées
}
```

**Tests de cet écran :**

```dart
// test/features/attendance/attendance_screen_test.dart

testWidgets('shows check-in button when not checked in today', (tester) async {
  final mockClient = MockApiClient();
  when(mockClient.getTodayAttendance()).thenAnswer((_) async => null); // pas de log

  await tester.pumpWidget(ProviderScope(
    overrides: [apiClientProvider.overrideWithValue(mockClient)],
    child: const MaterialApp(home: AttendanceHomeScreen()),
  ));
  await tester.pumpAndSettle();

  expect(find.text('Pointer l\'arrivée'), findsOneWidget);
  expect(find.text('Pointer le départ'), findsNothing);
});

testWidgets('shows check-out button after check-in', (tester) async {
  final mockClient = MockApiClient();
  when(mockClient.getTodayAttendance()).thenAnswer((_) async => {
    'data': {'id': 1, 'check_in': '2026-04-01T08:05:00Z', 'check_out': null, 'status': 'ontime'}
  });

  await tester.pumpWidget(ProviderScope(
    overrides: [apiClientProvider.overrideWithValue(mockClient)],
    child: const MaterialApp(home: AttendanceHomeScreen()),
  ));
  await tester.pumpAndSettle();

  expect(find.text('Pointer le départ'), findsOneWidget);
});

testWidgets('shows error snackbar on GPS outside zone', (tester) async {
  final mockClient = MockApiClient();
  when(mockClient.getTodayAttendance()).thenAnswer((_) async => null);
  when(mockClient.checkIn(lat: anyNamed('lat'), lng: anyNamed('lng')))
      .thenThrow(ApiException(code: 'GPS_OUTSIDE_ZONE', message: 'Position GPS hors zone'));

  await tester.pumpWidget(...);
  await tester.tap(find.text('Pointer l\'arrivée'));
  await tester.pumpAndSettle();

  expect(find.text('Position GPS hors zone'), findsOneWidget);
});
```

---

## PARTIE B — ÉCRANS ABSENCES

### AbsenceListScreen

```dart
// Affiche :
// - Solde de congés restant (en jours, bien visible)
// - Liste des demandes avec badges de statut colorés :
//   - pending → badge jaune "En attente"
//   - approved → badge vert "Approuvé"
//   - rejected → badge rouge "Refusé"
// - Bouton FAB "Nouvelle demande"
```

### AbsenceCreateScreen

```dart
// Formulaire avec :
// - Sélecteur de type de congé (dropdown depuis absence-types)
// - Date picker pour start_date (date > aujourd'hui)
// - Date picker pour end_date (date > start_date)
// - Calcul automatique du nombre de jours (en temps réel, sous les date pickers)
//   → "5 jours ouvrables — il vous restera X jours"
// - Champ raison (optionnel)
// - Bouton "Soumettre"
// - Affichage erreur si solde insuffisant ou chevauchement
```

**Tests :**

```dart
testWidgets('shows correct remaining balance after date selection', (tester) async {
  // Sélectionner 5 jours, solde = 12 → afficher "il vous restera 7 jours"
});

testWidgets('submit button disabled when end_date < start_date', (tester) async {});
```

---

## PARTIE C — ÉCRANS TÂCHES

### TaskListScreen

```dart
// Onglets : Mes tâches (assigned_to=me) | Toutes (visible manager seulement)
// Chaque tâche affiche :
// - Titre + projet (si lié)
// - Deadline avec badge rouge si dépassée
// - Statut avec couleur (pending=gris, in_progress=bleu, completed=vert, overdue=rouge)
// - Bouton "Changer le statut" accessible depuis la carte
```

---

## PARTIE D — BOTTOM NAVIGATION

```dart
// lib/shared/widgets/main_navigation.dart
// 5 onglets : Pointage | Absences | Tâches | Bulletins | Profil
// Badge rouge sur Notifications (intégré dans l'appbar)
// L'onglet actif est persisté entre sessions (SharedPreferences)
```

---

## CRITÈRES DE VALIDATION — PORTE VERTE VERS JU-03

```
[ ] flutter test → 0 failure
[ ] AttendanceHomeScreen : bouton check-in → mock → bouton check-out visible
[ ] AttendanceHomeScreen : erreur GPS affichée correctement
[ ] AbsenceListScreen : liste de congés affichée depuis mock
[ ] AbsenceCreateScreen : calcul jours temps réel fonctionne
[ ] TaskListScreen : liste affichée avec badges de statut corrects
[ ] Bottom navigation : 5 onglets, navigation sans perte d'état
[ ] Tous les textes passent par context.l10n (aucun hardcoded)
[ ] RTL correct pour l'arabe (tester avec locale 'ar')
```

---

## COMMIT

```
feat: add attendance home screen with check-in/out button and daily summary
feat: add absence list and create screens with real-time day calculation
feat: add task list screen with status badges and deadline alerts
feat: add bottom navigation with 5 tabs
test: add widget tests for attendance, absences, and tasks screens
```
