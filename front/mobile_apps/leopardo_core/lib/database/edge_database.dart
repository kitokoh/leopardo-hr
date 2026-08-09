// Edge local SQLite database — powered by Drift.
//
// Ce fichier déclare toutes les tables offline utilisées par le nœud Edge
// Leopardo. Le fichier `edge_database.g.dart` est généré automatiquement via:
//   dart run build_runner build
//
// Tables :
//   - [AttendanceLogs]   : pointages en attente de sync
//   - [EmployeeCache]    : snapshot employés pour mode offline
//   - [SyncQueue]        : opérations à remonter vers le Cloud
//   - [EdgeConfig]       : clé/valeur de configuration locale

library;

import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

part 'edge_database.g.dart';

// ---------------------------------------------------------------------------
// Table : attendance_logs
// ---------------------------------------------------------------------------

class AttendanceLogs extends Table {
  /// UUID local généré sur l'appareil avant sync
  TextColumn get localId => text().named('local_id')();

  /// ID Cloud une fois synchronisé (null = en attente)
  IntColumn get remoteId => integer().named('remote_id').nullable()();

  IntColumn get employeeId => integer().named('employee_id')();
  TextColumn get type => textEnum<AttendanceType>().named('type')();
  DateTimeColumn get checkedAt => dateTime().named('checked_at')();
  TextColumn get source =>
      text().named('source').withDefault(const Constant('edge'))();

  /// JSON brut de position GPS (nullable)
  TextColumn get locationJson => text().named('location_json').nullable()();

  /// 'pending' | 'synced' | 'conflict'
  TextColumn get syncStatus =>
      text().named('sync_status').withDefault(const Constant('pending'))();

  DateTimeColumn get createdAt =>
      dateTime().named('created_at').withDefault(currentDateAndTime)();

  @override
  Set<Column> get primaryKey => {localId};
}

enum AttendanceType { checkIn, checkOut, breakStart, breakEnd }

// ---------------------------------------------------------------------------
// Table : employee_cache
// ---------------------------------------------------------------------------

class EmployeeCache extends Table {
  IntColumn get id => integer()();
  TextColumn get firstName => text().named('first_name')();
  TextColumn get lastName => text().named('last_name')();
  TextColumn get email => text()();
  TextColumn get badgeQr => text().named('badge_qr').nullable()();
  TextColumn get department => text().nullable()();
  TextColumn get jobTitle => text().named('job_title').nullable()();
  BoolColumn get isActive =>
      boolean().named('is_active').withDefault(const Constant(true))();

  /// Epoch ms de la dernière synchro depuis le Cloud
  IntColumn get syncedAt => integer().named('synced_at').nullable()();

  @override
  Set<Column> get primaryKey => {id};
}

// ---------------------------------------------------------------------------
// Table : sync_queue
// ---------------------------------------------------------------------------

class SyncQueue extends Table {
  IntColumn get id => integer().autoIncrement()();

  /// 'attendance_log' | 'absence_request' | etc.
  TextColumn get entityType => text().named('entity_type')();

  /// ID local de l'entité concernée
  TextColumn get entityLocalId => text().named('entity_local_id')();

  /// 'create' | 'update' | 'delete'
  TextColumn get operation => text()();

  /// Payload JSON complet à envoyer
  TextColumn get payloadJson => text().named('payload_json')();

  /// 0 = pas encore tenté, incrémenté à chaque échec
  IntColumn get retryCount =>
      integer().named('retry_count').withDefault(const Constant(0))();

  /// Dernier message d'erreur HTTP/réseau
  TextColumn get lastError => text().named('last_error').nullable()();

  /// 'pending' | 'processing' | 'done' | 'failed'
  TextColumn get status => text().withDefault(const Constant('pending'))();

  DateTimeColumn get createdAt =>
      dateTime().named('created_at').withDefault(currentDateAndTime)();

  DateTimeColumn get processedAt =>
      dateTime().named('processed_at').nullable()();
}

// ---------------------------------------------------------------------------
// Table : edge_config
// ---------------------------------------------------------------------------

class EdgeConfig extends Table {
  TextColumn get key => text()();
  TextColumn get value => text()();
  DateTimeColumn get updatedAt =>
      dateTime().named('updated_at').withDefault(currentDateAndTime)();

  @override
  Set<Column> get primaryKey => {key};
}

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------

@DriftDatabase(tables: [AttendanceLogs, EmployeeCache, SyncQueue, EdgeConfig])
class EdgeDatabase extends _$EdgeDatabase {
  EdgeDatabase() : super(_openConnection());
  EdgeDatabase.forTesting(super.e);

  @override
  int get schemaVersion => 1;

  @override
  MigrationStrategy get migration => MigrationStrategy(
        onCreate: (m) => m.createAll(),
        onUpgrade: (m, from, to) async {
          // Migrations futures ici
        },
      );

  // -------------------------------------------------------------------------
  // Helpers — AttendanceLogs
  // -------------------------------------------------------------------------

  Future<List<AttendanceLog>> pendingLogs() => (select(attendanceLogs)
        ..where((t) => t.syncStatus.equals('pending'))
        ..orderBy([(t) => OrderingTerm.asc(t.createdAt)]))
      .get();

  Future<int> upsertLog(AttendanceLogsCompanion entry) =>
      into(attendanceLogs).insertOnConflictUpdate(entry);

  Future<bool> markLogSynced(String localId, int remoteId) async {
    final count = await (update(
      attendanceLogs,
    )..where((t) => t.localId.equals(localId)))
        .write(
      AttendanceLogsCompanion(
        remoteId: Value(remoteId),
        syncStatus: const Value('synced'),
      ),
    );
    return count > 0;
  }

  // -------------------------------------------------------------------------
  // Helpers — EmployeeCache
  // -------------------------------------------------------------------------

  Future<List<EmployeeCacheData>> allActiveEmployees() =>
      (select(employeeCache)..where((t) => t.isActive.equals(true))).get();

  Future<EmployeeCacheData?> employeeByBadge(String qr) => (select(
        employeeCache,
      )..where((t) => t.badgeQr.equals(qr)))
          .getSingleOrNull();

  Future<void> replaceEmployees(List<EmployeeCacheCompanion> employees) =>
      transaction(() async {
        await delete(employeeCache).go();
        await batch((b) => b.insertAll(employeeCache, employees));
      });

  // -------------------------------------------------------------------------
  // Helpers — SyncQueue
  // -------------------------------------------------------------------------

  Future<List<SyncQueueData>> pendingQueueItems({int limit = 20}) =>
      (select(syncQueue)
            ..where((t) => t.status.equals('pending'))
            ..orderBy([(t) => OrderingTerm.asc(t.createdAt)])
            ..limit(limit))
          .get();

  Future<int> enqueue(SyncQueueCompanion entry) =>
      into(syncQueue).insert(entry);

  Future<bool> markQueueDone(int id) async {
    final count =
        await (update(syncQueue)..where((t) => t.id.equals(id))).write(
      SyncQueueCompanion(
        status: const Value('done'),
        processedAt: Value(DateTime.now()),
      ),
    );
    return count > 0;
  }

  Future<bool> markQueueFailed(int id, String error) async {
    final existing = await (select(
      syncQueue,
    )..where((t) => t.id.equals(id)))
        .getSingle();
    final count =
        await (update(syncQueue)..where((t) => t.id.equals(id))).write(
      SyncQueueCompanion(
        status: const Value('failed'),
        retryCount: Value(existing.retryCount + 1),
        lastError: Value(error),
      ),
    );
    return count > 0;
  }

  // -------------------------------------------------------------------------
  // Helpers — EdgeConfig
  // -------------------------------------------------------------------------

  Future<String?> getConfig(String key) async {
    final row = await (select(
      edgeConfig,
    )..where((t) => t.key.equals(key)))
        .getSingleOrNull();
    return row?.value;
  }

  Future<void> setConfig(String key, String value) =>
      into(edgeConfig).insertOnConflictUpdate(
        EdgeConfigCompanion(
          key: Value(key),
          value: Value(value),
          updatedAt: Value(DateTime.now()),
        ),
      );
}

// ---------------------------------------------------------------------------
// Connection factory
// ---------------------------------------------------------------------------

LazyDatabase _openConnection() {
  return LazyDatabase(() async {
    final dir = await getApplicationDocumentsDirectory();
    final file = File(p.join(dir.path, 'leopardo_edge.sqlite'));
    return NativeDatabase.createInBackground(file);
  });
}
