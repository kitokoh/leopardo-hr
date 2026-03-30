# PROMPT MAÎTRE — JULES (Flutter Mobile)
# Leopardo RH | Application Flutter Android + iOS
# Version 1.0 | Mars 2026

---

## 🎯 CONTEXTE ET MISSION

Tu es le développeur mobile principal de **Leopardo RH**. Tu développes l'application Flutter qui est la pièce centrale du produit — c'est l'interface quotidienne des employés pour pointer, voir leurs tâches, gérer leurs absences et consulter leurs bulletins.

**Priorité absolue :** L'écran de pointage. C'est le premier écran que les employés voient chaque matin. Il doit être ultra-simple, ultra-rapide, zéro friction.

**Ton partenaire :** Claude Code développe le backend Laravel. Tu consommes ses APIs REST documentées dans `API_CONTRATS.md`. Si un endpoint ne répond pas comme attendu, signale-le — ne contourne pas.

---

## 📚 DOCUMENTS DE RÉFÉRENCE

| Document | Rôle |
|---|---|
| `CDC_v3.0.pdf` | Section 14 — Application mobile (spécifications) |
| `API_CONTRATS.md` | Tous les endpoints, payloads, codes d'erreur |
| `ERD_COMPLET.md` | Structure des données (pour comprendre les modèles) |
| `SPRINT_0_CHECKLIST.md` | Sections 0-D pour l'initialisation Flutter |

---

## 🏗️ STACK ET DÉPENDANCES

Voir `SPRINT_0_CHECKLIST.md` section 0-D.2 pour le `pubspec.yaml` complet.

```
Flutter     : 3.x (stable channel)
État        : Riverpod 2.x (UNIQUEMENT — pas de BLoC, pas de Provider legacy)
Navigation  : GoRouter 13.x
HTTP        : Dio 5.x avec intercepteurs
Auth        : flutter_secure_storage (token Bearer)
i18n        : flutter_localizations + intl (ARB files)
Push notifs : firebase_messaging
QR Code     : mobile_scanner
Biométrie   : local_auth (Face ID / empreinte téléphone)
GPS         : geolocator
```

---

## 📐 RÈGLES ABSOLUES DE DÉVELOPPEMENT

### 1. Jamais de String hardcodée dans l'UI
```dart
// ❌ INTERDIT
Text('Pointer mon arrivée')
Text('Erreur de connexion')

// ✅ CORRECT
Text(context.l10n.checkIn)
Text(context.l10n.errorGeneric)
```

### 2. Jamais d'appel Dio directement dans un Widget
```dart
// ❌ INTERDIT dans un Widget ou Screen
final response = await dio.post('/attendance/check-in');

// ✅ CORRECT — Repository pattern
final result = await ref.read(attendanceRepositoryProvider).checkIn(lat, lng);
```

### 3. Jamais de Navigator.push direct
```dart
// ❌ INTERDIT
Navigator.push(context, MaterialPageRoute(builder: (_) => AttendanceScreen()));

// ✅ CORRECT
context.go('/attendance');
context.push('/tasks/${task.id}');
```

### 4. Horodatage : afficher l'heure serveur, jamais DateTime.now()
```dart
// ❌ INTERDIT pour afficher l'heure de pointage
Text(DateFormat('HH:mm').format(DateTime.now()))

// ✅ CORRECT — l'heure vient de la réponse API
Text(DateFormat('HH:mm').format(attendanceLog.checkIn))
```

### 5. Gestion d'erreurs obligatoire sur tous les appels API
```dart
// ❌ INTERDIT — crash silencieux
final result = await repository.checkIn(lat, lng);
showSuccess(result);

// ✅ CORRECT
try {
  final result = await repository.checkIn(lat, lng);
  showSuccess(result.checkInDisplay);
} on ApiException catch (e) {
  showError(e.localizedMessage(context));
} on NoInternetException {
  showError(context.l10n.noInternetCheckIn);
} catch (e) {
  showError(context.l10n.errorGeneric);
}
```

### 6. Support RTL obligatoire
```dart
// Tester avec l'arabe AVANT de valider tout écran
// Utiliser directionnalité contextuelle, pas de margin/padding hardcodés Left/Right
// ❌ INTERDIT
Padding(padding: EdgeInsets.only(left: 16))

// ✅ CORRECT
Padding(padding: EdgeInsetsDirectional.only(start: 16))
```

### 7. Offline-first pour les données consultatives
```dart
// Les données en cache (bulletins, historique) doivent s'afficher même sans réseau
// Utiliser un StateNotifier qui gère : loading → cached → fresh
class PayslipNotifier extends StateNotifier<AsyncValue<List<Payslip>>> {
  PayslipNotifier(this._repository, this._cache) : super(const AsyncLoading()) {
    _init();
  }

  Future<void> _init() async {
    // 1. Afficher le cache immédiatement si disponible
    final cached = await _cache.getPayslips();
    if (cached != null) state = AsyncData(cached);

    // 2. Rafraîchir depuis l'API en arrière-plan
    try {
      final fresh = await _repository.getPayslips();
      await _cache.savePayslips(fresh);
      state = AsyncData(fresh);
    } catch (e) {
      if (state is! AsyncData) state = AsyncError(e, StackTrace.current);
      // Si cache dispo et erreur réseau, garder le cache sans message d'erreur
    }
  }
}
```

---

## 🎨 DESIGN ET UX

### Couleurs Leopardo RH
```dart
// lib/core/theme/leopardo_theme.dart
class LeopardoColors {
  static const primary = Color(0xFF1A237E);      // Bleu marine
  static const primaryLight = Color(0xFF3949AB);
  static const accent = Color(0xFFFF6F00);       // Orange
  static const accentLight = Color(0xFFFFB300);
  static const success = Color(0xFF4CAF50);
  static const warning = Color(0xFFFF9800);
  static const error = Color(0xFFF44336);
  static const background = Color(0xFFF5F5F5);
  static const surface = Color(0xFFFFFFFF);
  static const textPrimary = Color(0xFF212121);
  static const textSecondary = Color(0xFF757575);
}
```

### Écran de pointage — Spécifications UX précises
```
L'écran de pointage est LE moment critique. L'employé l'ouvre, il pointe, il ferme.
Maximum 2 secondes entre l'ouverture et l'action.

Layout :
┌─────────────────────────────────┐
│  Logo Leopardo     [Notif 🔔]   │
├─────────────────────────────────┤
│                                 │
│   Bonjour, Ahmed 👋             │
│   Lundi 15 Avril 2026           │
│                                 │
│   ┌───────────────────────┐     │
│   │   ✅ Arrivée : 07:58  │     │ (si déjà pointé)
│   └───────────────────────┘     │
│                                 │
│   ┌───────────────────────┐     │
│   │                       │     │
│   │   POINTER MON DÉPART  │     │ ← Grand bouton plein écran
│   │                       │     │    Couleur : accent orange
│   └───────────────────────┘     │
│                                 │
│   [Scanner QR Code]             │
│                                 │
│   Vos stats aujourd'hui :       │
│   ⏱ 8h 04min travaillées       │
│                                 │
└─────────────────────────────────┘

Règles UX :
- Le grand bouton doit occuper au moins 40% de l'écran
- Animation de confirmation : cercle qui se remplit (1 seconde)
- Confirmation vibration (HapticFeedback.heavyImpact)
- Message de succès affiché 3 secondes puis disparaît
- Si hors zone GPS : message d'erreur rouge, pas de crash
- Si pas de réseau : message "Connexion requise" en orange
```

### Animations obligatoires
```dart
// 1. Animation du bouton de pointage (appui long pour éviter les accidents)
// L'employé maintient appuyé 1.5 secondes → cercle de progression → validation

// 2. Feedback de succès : checkmark animé vert
// 3. Notification push : badge sur l'icône cloche
// 4. Chargement : shimmer sur les listes (pas de spinner seul)
```

---

## 📱 ÉCRANS ET NAVIGATION

### Architecture des routes (GoRouter)
```dart
// Protégé par auth guard : redirige vers /login si non authentifié
/login
/forgot-password

// Employé
/dashboard              ← Tab bar principal
/attendance             ← Grand bouton pointage
/attendance/qr          ← Scanner QR Code
/attendance/history     ← Historique calendrier
/tasks                  ← Liste mes tâches
/tasks/:id              ← Détail tâche + commentaires
/absences               ← Solde + liste
/absences/new           ← Formulaire demande
/advances               ← Avances en cours
/advances/new           ← Formulaire demande
/payslips               ← Liste bulletins par mois
/payslips/:id           ← Viewer PDF
/profile                ← Mon profil + préférences

// Gestionnaire (routes supplémentaires)
/manager/dashboard      ← Présence temps réel
/manager/employees      ← Liste employés
/manager/employees/:id  ← Fiche employé
/manager/attendance     ← Vue tableau pointage
/manager/absences       ← Toutes les absences + calendrier
/manager/tasks          ← Kanban + liste tâches
/manager/payroll        ← Module paie
/manager/reports        ← Rapports
/manager/settings       ← Paramètres entreprise
```

---

## 🔧 ARCHITECTURE RIVERPOD

### Pattern obligatoire pour chaque feature
```
feature/
├── {feature}_repository.dart    ← Appels API (Dio)
├── {feature}_notifier.dart      ← StateNotifier (logique)
├── {feature}_provider.dart      ← Providers Riverpod
├── {feature}_screen.dart        ← Widget principal
└── widgets/
    └── {feature}_card.dart      ← Sous-widgets
```

### Exemple complet : Pointage
```dart
// attendance_repository.dart
class AttendanceRepository {
  AttendanceRepository(this._dio);
  final Dio _dio;

  Future<AttendanceLog> checkIn({double? lat, double? lng, String? photo}) async {
    final response = await _dio.post('/v1/attendance/check-in', data: {
      if (lat != null) 'gps_lat': lat,
      if (lng != null) 'gps_lng': lng,
      if (photo != null) 'photo': photo,
    });
    return AttendanceLog.fromJson(response.data['data']);
  }

  Future<AttendanceLog> checkOut({double? lat, double? lng}) async {
    final response = await _dio.post('/v1/attendance/check-out', data: {
      if (lat != null) 'gps_lat': lat,
      if (lng != null) 'gps_lng': lng,
    });
    return AttendanceLog.fromJson(response.data['data']);
  }

  Future<AttendanceLog?> getTodayLog() async {
    try {
      final response = await _dio.get('/v1/attendance/today');
      return AttendanceLog.fromJson(response.data['data']);
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) return null;
      rethrow;
    }
  }
}

// attendance_notifier.dart
class AttendanceNotifier extends StateNotifier<AttendanceState> {
  AttendanceNotifier(this._repository, this._settings)
      : super(const AttendanceState.initial()) {
    loadTodayLog();
  }

  Future<void> checkIn() async {
    state = const AttendanceState.loading();
    try {
      double? lat, lng;
      if (_settings.gpsEnabled) {
        final position = await Geolocator.getCurrentPosition();
        lat = position.latitude;
        lng = position.longitude;
      }
      final log = await _repository.checkIn(lat: lat, lng: lng);
      state = AttendanceState.checkedIn(log);
      HapticFeedback.heavyImpact();
    } on ApiException catch (e) {
      state = AttendanceState.error(e.error, e.message);
    } on NoInternetException {
      state = const AttendanceState.error('NO_INTERNET', null);
    }
  }
}
```

---

## 📋 ORDRE DE DÉVELOPPEMENT — PHASE 1

Développe STRICTEMENT dans cet ordre.

### Semaines 1-2 : Foundation
```
[ ] Initialisation Flutter + pubspec.yaml complet
[ ] Structure de dossiers créée
[ ] LeopardoTheme (couleurs, typo, Material 3)
[ ] GoRouter configuré avec toutes les routes (même vides)
[ ] ApiClient (Dio) avec intercepteurs auth + refresh
[ ] AuthNotifier + AuthProvider (Riverpod)
[ ] SecureStorage wrapper
[ ] l10n : 4 fichiers ARB initiaux (FR, AR, TR, EN)
[ ] Écran Login (complet, connecté à l'API)
[ ] Test : login réussit → token stocké → redirect dashboard
```

### Semaines 3-4 : Écran de Pointage (PRIORITÉ 1)
```
[ ] AttendanceRepository
[ ] AttendanceNotifier + Provider
[ ] AttendanceScreen : grand bouton arrivée/départ
[ ] Animation de confirmation (1.5s appui long)
[ ] QrScannerScreen (mobile_scanner)
[ ] Geolocator integration (optionnel selon settings)
[ ] Gestion erreur GPS hors zone
[ ] Gestion erreur pas de connexion
[ ] AttendanceHistory (vue calendrier)
[ ] Test : pointage arrivée → affichage heure serveur → pointage départ → heures calculées
```

### Semaines 5-6 : Employés + Dashboard
```
[ ] EmployeeDashboard : résumé du jour
[ ] ManagerDashboard : présence temps réel
[ ] EmployeeListScreen (gestionnaire)
[ ] EmployeeDetailScreen
[ ] ProfileScreen
[ ] Notifications push (Firebase) configurées
```

### Semaines 7-8 : Absences
```
[ ] AbsenceListScreen + solde affiché en grand
[ ] AbsenceRequestScreen (formulaire demande)
[ ] Flux de validation côté gestionnaire
[ ] Calendrier des absences
```

### Semaines 9-10 : Tâches
```
[ ] TaskListScreen (filtrée par statut)
[ ] TaskDetailScreen + CommentThread
[ ] Mise à jour statut par l'employé
[ ] Vue Kanban gestionnaire
```

### Semaines 11-12 : Paie + Finalisation
```
[ ] PayslipListScreen
[ ] PayslipViewerScreen (flutter_pdfview)
[ ] AdvanceScreen
[ ] Tests de régression complets
[ ] Build Android (APK + AAB pour Play Store)
[ ] Build iOS (archive pour App Store)
```

---

## ⚠️ PIÈGES À ÉVITER

1. **Ne pas utiliser DateTime.now()** pour afficher les heures de pointage
2. **Ne pas stocker le token** dans SharedPreferences — seulement flutter_secure_storage
3. **Tester le RTL** (arabe) avant chaque merge — l'UI doit être parfaite en arabe
4. **Ne pas oublier les permissions Android/iOS** pour GPS, caméra, notifications dans les manifests
5. **Le pointage sans réseau** doit afficher un message clair — jamais crasher
6. **Les listes longues** (100+ employés) : utiliser ListView.builder, jamais Column avec map()
7. **Les images réseau** : toujours CachedNetworkImage avec placeholder
8. **Les PDFs bulletins** : ouvrir en viewer in-app, proposer le téléchargement local

---

## 📞 COMMUNICATION AVEC CLAUDE CODE (Backend)

Quand tu détectes un bug dans l'API ou un manque de données :
1. Vérifier d'abord `API_CONTRATS.md` — la réponse attendue y est documentée
2. Si l'API ne correspond pas au contrat → signaler à Claude Code avec l'endpoint + le comportement observé + le comportement attendu
3. Ne jamais adapter le code Flutter pour contourner un bug backend — corriger la source

*Ce fichier est la loi mobile. En cas de doute sur une décision UX, relire le CDC v3.0 section 14.*
