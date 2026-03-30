# MOCK DATA — Guide d'intégration Flutter
# Leopardo RH | Version 1.0 | Mars 2026

---

## Comment utiliser ces fichiers dans Flutter

### 1. Copier les fichiers dans le projet Flutter

```
mobile/assets/mock/
├── mock_auth_login.json
├── mock_auth_me.json
├── mock_attendance_today_A_not_checked.json
├── mock_attendance_today_B_checked_in.json
├── mock_attendance_history.json
├── mock_absences.json
├── mock_tasks.json
├── mock_payroll.json
└── mock_notifications.json
```

### 2. Déclarer les assets dans pubspec.yaml

```yaml
flutter:
  assets:
    - assets/mock/
```

### 3. Service MockDataService (Riverpod)

```dart
// lib/shared/services/mock_data_service.dart
import 'dart:convert';
import 'package:flutter/services.dart';

class MockDataService {
  static const bool useMock = true; // Mettre à false quand l'API est prête

  static Future<Map<String, dynamic>> load(String fileName) async {
    final jsonString = await rootBundle.loadString('assets/mock/$fileName');
    return json.decode(jsonString) as Map<String, dynamic>;
  }
}
```

### 4. Repository avec basculement mock/API

```dart
// lib/features/attendance/data/attendance_repository.dart
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../shared/services/mock_data_service.dart';
import '../../../shared/models/attendance_log.dart';

class AttendanceRepository {
  final Dio _dio;

  AttendanceRepository(this._dio);

  Future<AttendanceLog?> getTodayAttendance() async {
    if (MockDataService.useMock) {
      // Choisir le cas A (pas pointé) ou B (pointé) pour tester
      final json = await MockDataService.load('mock_attendance_today_A_not_checked.json');
      final data = json['data'];
      return data != null ? AttendanceLog.fromJson(data) : null;
    }
    // API réelle
    final response = await _dio.get('/attendance/today');
    final data = response.data['data'];
    return data != null ? AttendanceLog.fromJson(data) : null;
  }
}
```

### 5. Correspondance fichiers ↔ endpoints

| Fichier mock | Endpoint API réel | Utilisé par |
|---|---|---|
| mock_auth_login.json | POST /auth/login | AuthScreen |
| mock_auth_me.json | GET /auth/me | ProfileScreen, HomeScreen |
| mock_attendance_today_A_not_checked.json | GET /attendance/today | HomeScreen (bouton arrivée) |
| mock_attendance_today_B_checked_in.json | GET /attendance/today | HomeScreen (bouton départ) |
| mock_attendance_history.json | GET /attendance | AttendanceHistoryScreen |
| mock_absences.json | GET /absences | AbsenceListScreen |
| mock_tasks.json | GET /tasks | TaskListScreen |
| mock_payroll.json | GET /payroll | PayrollListScreen |
| mock_notifications.json | GET /notifications | NotificationScreen |

### 6. Basculement vers l'API réelle

Quand le backend est déployé et testable :
1. Mettre `useMock = false` dans `MockDataService`
2. Vérifier que le `DioInterceptor` injecte bien le token Bearer
3. Tester endpoint par endpoint (commencer par /auth/me)
4. Corriger les éventuelles divergences de structure entre le mock et la réponse réelle
