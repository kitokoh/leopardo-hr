import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_employee/features/attendance/models/attendance_anomaly.dart';

/// Tests critiques (issue #1560) — parsing des anomalies de pointage
/// (modèle pur, aucune dépendance plugin).
void main() {
  group('AttendanceAnomaly.fromJson', () {
    test('parse tous les champs fournis', () {
      final anomaly = AttendanceAnomaly.fromJson({
        'type': 'late_arrival',
        'severity': 'warning',
        'title': 'Retard de 14 min',
        'date': '2026-08-07',
        'requires_manager_action': true,
        'recommended_action': 'Corriger le pointage',
      });

      expect(anomaly.type, 'late_arrival');
      expect(anomaly.severity, 'warning');
      expect(anomaly.title, 'Retard de 14 min');
      expect(anomaly.date, '2026-08-07');
      expect(anomaly.requiresManagerAction, isTrue);
      expect(anomaly.recommendedAction, 'Corriger le pointage');
    });

    test('applique des valeurs par défaut sûres quand les clés manquent', () {
      final anomaly = AttendanceAnomaly.fromJson(const {});

      expect(anomaly.type, 'unknown');
      expect(anomaly.severity, 'info');
      expect(anomaly.title, 'Anomalie');
      expect(anomaly.date, '');
      expect(anomaly.requiresManagerAction, isFalse);
      expect(anomaly.recommendedAction, '');
    });

    test('tolère les valeurs non-booléennes pour requires_manager_action', () {
      final anomaly = AttendanceAnomaly.fromJson({
        'requires_manager_action': 'true', // string, pas bool
      });

      expect(anomaly.requiresManagerAction, isFalse);
    });
  });

  group('AttendanceAnomalyReport.fromJson', () {
    test('calcule le rapport depuis summary + items', () {
      final report = AttendanceAnomalyReport.fromJson({
        'summary': {'total': 3, 'critical': 1, 'warning': 1, 'info': 1},
        'items': [
          {
            'type': 'missing_checkout',
            'severity': 'critical',
            'title': 'Fin de journée non pointée',
            'date': '2026-08-06',
          },
          {
            'type': 'late_arrival',
            'severity': 'warning',
            'title': 'Retard',
            'date': '2026-08-07',
          },
        ],
      });

      expect(report.total, 3);
      expect(report.critical, 1);
      expect(report.warning, 1);
      expect(report.info, 1);
      expect(report.items.length, 2);
      expect(report.items.first.type, 'missing_checkout');
    });

    test('rapport vide quand aucun summary', () {
      final report = AttendanceAnomalyReport.fromJson(const {});

      expect(report.total, 0);
      expect(report.critical, 0);
      expect(report.warning, 0);
      expect(report.info, 0);
      expect(report.items, isEmpty);
    });

    test('coerce les comptes numériques en int (string inclus)', () {
      final report = AttendanceAnomalyReport.fromJson({
        'summary': {'total': '4', 'critical': 2, 'warning': '1', 'info': 1},
        'items': const [],
      });

      expect(report.total, 4);
      expect(report.critical, 2);
      expect(report.warning, 1);
    });

    test('le singleton empty est stable', () {
      expect(AttendanceAnomalyReport.empty.total, 0);
      expect(AttendanceAnomalyReport.empty.items, isEmpty);
      expect(
        identical(AttendanceAnomalyReport.empty, AttendanceAnomalyReport.empty),
        isTrue,
      );
    });
  });
}
