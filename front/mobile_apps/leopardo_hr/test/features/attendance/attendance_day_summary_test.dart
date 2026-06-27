import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_manager/features/attendance/screens/attendance_screen.dart';
import 'package:leopardo_core/models/attendance_log.dart';

void main() {
  group('AttendanceDaySummary', () {
    test('formats a worked day with late minutes and estimated earnings', () {
      final date = DateTime(2026, 5, 22);
      final summary = AttendanceDaySummary.fromLog(
        date: date,
        dayLabel: 'Ven. 22',
        log: AttendanceLog(
          id: 7,
          employeeId: 12,
          date: date,
          checkIn: DateTime(2026, 5, 22, 8, 14),
          checkOut: DateTime(2026, 5, 22, 16, 44),
          status: 'present',
          workedHours: 8.5,
          lateMinutes: 14,
        ),
      );

      expect(summary.isAbsent, isFalse);
      expect(summary.logId, 7);
      expect(summary.workedMinutes, 510);
      expect(summary.hoursFormatted, '8h30');
      expect(summary.lateMinutes, 14);
      expect(summary.estimatedEarnings, 4675);
      expect(summary.checkInFormatted, '08:14');
      expect(summary.checkOutFormatted, '16:44');
    });

    test('marks a missing log as absent with neutral values', () {
      final summary = AttendanceDaySummary.fromLog(
        date: DateTime(2026, 5, 23),
        dayLabel: 'Sam. 23',
        log: null,
      );

      expect(summary.isAbsent, isTrue);
      expect(summary.logId, isNull);
      expect(summary.workedMinutes, 0);
      expect(summary.hoursFormatted, '0h00');
      expect(summary.lateMinutes, 0);
      expect(summary.estimatedEarnings, 0);
      expect(summary.checkInFormatted, '--:--');
      expect(summary.checkOutFormatted, '--:--');
    });
  });
}
