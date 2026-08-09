// ============================================================
// Leopardo Edge — Local SQLite database using Drift
// ============================================================

import 'dart:io';
import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:flutter/foundation.dart' show visibleForTesting;
import 'package:path_provider/path_provider.dart';
import 'package:path/path.dart' as p;

part 'edge_database.g.dart';

// ── Tables ────────────────────────────────────────────────

@DataClassName('LocalAttendanceLog')
class LocalAttendanceLogs extends Table {
  TextColumn get id => text().clientDefault(() => _uuid())();
  TextColumn get employeeId => text()();
  TextColumn get companyId => text()();
  DateTimeColumn get checkIn => dateTime()();
  DateTimeColumn get checkOut => dateTime().nullable()();
  TextColumn get method => text().withDefault(const Constant('manual'))();
  TextColumn get workType => text().withDefault(const Constant('onsite'))();
  RealColumn get gpsLat => real().nullable()();
  RealColumn get gpsLng => real().nullable()();
  TextColumn get status => text().withDefault(const Constant('present'))();
  TextColumn get syncStatus => text().withDefault(
    const Constant('pending'),
  )(); // pending|synced|conflict|failed
  TextColumn get externalEventId => text().nullable()();
  DateTimeColumn get createdAt =>
      dateTime().clientDefault(() => DateTime.now())();
  DateTimeColumn get updatedAt =>
      dateTime().clientDefault(() => DateTime.now())();

  @override
  Set<Column> get primaryKey => {id};
}

@DataClassName('LocalAbsence')
class LocalAbsences extends Table {
  TextColumn get id => text().clientDefault(() => _uuid())();
  TextColumn get employeeId => text()();
  TextColumn get companyId => text()();
  TextColumn get absenceTypeId => text()();
  DateTimeColumn get startDate => dateTime()();
  DateTimeColumn get endDate => dateTime()();
  TextColumn get reason => text().nullable()();
  TextColumn get status => text().withDefault(
    const Constant('pending'),
  )(); // pending|approved|rejected
  TextColumn get syncStatus => text().withDefault(const Constant('pending'))();
  DateTimeColumn get createdAt =>
      dateTime().clientDefault(() => DateTime.now())();
  DateTimeColumn get updatedAt =>
      dateTime().clientDefault(() => DateTime.now())();

  @override
  Set<Column> get primaryKey => {id};
}

@DataClassName('LocalEmployee')
class LocalEmployees extends Table {
  TextColumn get id => text()();
  TextColumn get companyId => text()();
  TextColumn get firstName => text()();
  TextColumn get lastName => text()();
  TextColumn get email => text()();
  TextColumn get phone => text().nullable()();
  TextColumn get departmentId => text().nullable()();
  TextColumn get positionId => text().nullable()();
  TextColumn get role => text().withDefault(const Constant('employee'))();
  TextColumn get status => text().withDefault(const Constant('active'))();
  TextColumn get faceEncoding =>
      text().nullable()(); // base64 for local biometric
  TextColumn get biometricId => text().nullable()();
  DateTimeColumn get updatedAt =>
      dateTime().clientDefault(() => DateTime.now())();

  @override
  Set<Column> get primaryKey => {id};
}

@DataClassName('LocalSyncQueueItem')
class LocalSyncQueue extends Table {
  TextColumn get id => text().clientDefault(() => _uuid())();
  TextColumn get entityType => text()(); // attendance_logs|absences|etc.
  TextColumn get entityId => text()();
  TextColumn get operation => text()(); // create|update|delete
  TextColumn get payload => text()(); // JSON encoded
  TextColumn get status =>
      text().withDefault(const Constant('pending'))(); // pending|synced|failed
  IntColumn get attemptCount => integer().withDefault(const Constant(0))();
  DateTimeColumn get createdAt =>
      dateTime().clientDefault(() => DateTime.now())();
  DateTimeColumn get syncedAt => dateTime().nullable()();

  @override
  Set<Column> get primaryKey => {id};
}

@DataClassName('LocalDepartment')
class LocalDepartments extends Table {
  TextColumn get id => text()();
  TextColumn get companyId => text()();
  TextColumn get name => text()();
  TextColumn get code => text().nullable()();
  DateTimeColumn get updatedAt =>
      dateTime().clientDefault(() => DateTime.now())();

  @override
  Set<Column> get primaryKey => {id};
}

String _uuid() {
  // Simple UUID v4 generator without external dep
  final now = DateTime.now().microsecondsSinceEpoch;
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replaceAllMapped(
    RegExp(r'[xy]'),
    (m) {
      final r = (now + m.start * 16) % 16;
      return (m.group(0) == 'x' ? r : (r & 0x3 | 0x8)).toRadixString(16);
    },
  );
}

// ── Database ──────────────────────────────────────────────

@DriftDatabase(
  tables: [
    LocalAttendanceLogs,
    LocalAbsences,
    LocalEmployees,
    LocalSyncQueue,
    LocalDepartments,
  ],
)
class EdgeDatabase extends _$EdgeDatabase {
  EdgeDatabase() : super(_openConnection());

  /// Test-only constructor that accepts an arbitrary [QueryExecutor] (e.g.
  /// `NativeDatabase.memory()`) instead of opening the real on-device file
  /// under the app documents directory. See issue #1296 — this is what lets
  /// AttendanceOfflineService/SyncService be unit-tested without a device.
  @visibleForTesting
  EdgeDatabase.forTesting(super.executor);

  @override
  int get schemaVersion => 1;

  // ── Attendance ───────────────────────────────────────

  Future<List<LocalAttendanceLog>> getAttendanceLogs(String employeeId) =>
      (select(localAttendanceLogs)
            ..where((t) => t.employeeId.equals(employeeId))
            ..orderBy([(t) => OrderingTerm.desc(t.checkIn)]))
          .get();

  Future<String> insertAttendanceLog(LocalAttendanceLogsCompanion log) async {
    final id = await into(localAttendanceLogs).insertReturning(log);
    await _enqueue('attendance_logs', id.id, 'create', log.toJson());
    return id.id;
  }

  Future<void> checkOut(String logId) async {
    await (update(localAttendanceLogs)..where((t) => t.id.equals(logId))).write(
      LocalAttendanceLogsCompanion(
        checkOut: Value(DateTime.now()),
        updatedAt: Value(DateTime.now()),
        syncStatus: const Value('pending'),
      ),
    );
    final log = await (select(
      localAttendanceLogs,
    )..where((t) => t.id.equals(logId))).getSingle();
    await _enqueue('attendance_logs', logId, 'update', _logToJson(log));
  }

  // ── Absence ──────────────────────────────────────────

  Future<String> insertAbsence(LocalAbsencesCompanion absence) async {
    final row = await into(localAbsences).insertReturning(absence);
    await _enqueue('absences', row.id, 'create', _absenceToJson(row));
    return row.id;
  }

  // ── Employees (read-only cache from Cloud pull) ──────

  Future<void> upsertEmployee(LocalEmployeesCompanion emp) =>
      into(localEmployees).insertOnConflictUpdate(emp);

  Future<LocalEmployee?> findEmployee(String id) =>
      (select(localEmployees)..where((t) => t.id.equals(id))).getSingleOrNull();

  Future<List<LocalEmployee>> searchEmployees(String query) =>
      (select(localEmployees)..where(
            (t) =>
                t.firstName.contains(query) |
                t.lastName.contains(query) |
                t.email.contains(query),
          ))
          .get();

  // ── Sync Queue ───────────────────────────────────────

  Future<List<LocalSyncQueueItem>> getPendingItems() =>
      (select(localSyncQueue)
            ..where((t) => t.status.equals('pending'))
            ..orderBy([(t) => OrderingTerm.asc(t.createdAt)]))
          .get();

  Future<void> markSynced(String itemId) =>
      (update(localSyncQueue)..where((t) => t.id.equals(itemId))).write(
        LocalSyncQueueCompanion(
          status: const Value('synced'),
          syncedAt: Value(DateTime.now()),
        ),
      );

  Future<void> markFailed(String itemId) =>
      (update(localSyncQueue)..where((t) => t.id.equals(itemId))).write(
        const LocalSyncQueueCompanion(status: Value('failed')),
      );

  Future<void> _enqueue(
    String entityType,
    String entityId,
    String operation,
    Map<String, dynamic> payload,
  ) async {
    await into(localSyncQueue).insert(
      LocalSyncQueueCompanion.insert(
        entityType: entityType,
        entityId: entityId,
        operation: operation,
        payload: _jsonEncode(payload),
      ),
    );
  }

  // Helper serializers
  Map<String, dynamic> _logToJson(LocalAttendanceLog l) => {
    'id': l.id,
    'employee_id': l.employeeId,
    'company_id': l.companyId,
    'check_in': l.checkIn.toIso8601String(),
    'check_out': l.checkOut?.toIso8601String(),
    'method': l.method,
    'work_type': l.workType,
    'gps_lat': l.gpsLat,
    'gps_lng': l.gpsLng,
    'status': l.status,
    'external_event_id': l.externalEventId,
    'updated_at': l.updatedAt.toIso8601String(),
  };

  Map<String, dynamic> _absenceToJson(LocalAbsence a) => {
    'id': a.id,
    'employee_id': a.employeeId,
    'company_id': a.companyId,
    'absence_type_id': a.absenceTypeId,
    'start_date': a.startDate.toIso8601String(),
    'end_date': a.endDate.toIso8601String(),
    'reason': a.reason,
    'status': a.status,
    'updated_at': a.updatedAt.toIso8601String(),
  };

  String _jsonEncode(Map<String, dynamic> map) {
    // simple JSON encode — use dart:convert in real code
    return map.entries
        .map((e) => '"${e.key}":"${e.value}"')
        .join(',')
        .let((s) => '{$s}');
  }
}

LazyDatabase _openConnection() {
  return LazyDatabase(() async {
    final dbFolder = await getApplicationDocumentsDirectory();
    final file = File(p.join(dbFolder.path, 'leopardo_edge.sqlite'));
    return NativeDatabase.createInBackground(file);
  });
}

extension on String {
  T let<T>(T Function(String) block) => block(this);
}
