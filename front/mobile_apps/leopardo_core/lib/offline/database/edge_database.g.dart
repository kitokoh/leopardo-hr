// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'edge_database.dart';

// ignore_for_file: type=lint
// **************************************************************************
// MoorGenerator
// **************************************************************************

// ignore_for_file: unnecessary_brace_in_string_interps, unnecessary_this
// ignore_for_file: unused_import, depend_on_referenced_packages

// **************************************************************************
// DriftDatabaseGenerator
// **************************************************************************

// ignore_for_file: type=lint
class LocalAttendanceLog extends DataClass
    implements Insertable<LocalAttendanceLog> {
  final String id;
  final String employeeId;
  final String companyId;
  final DateTime checkIn;
  final DateTime? checkOut;
  final String method;
  final String workType;
  final double? gpsLat;
  final double? gpsLng;
  final String status;
  final String syncStatus;
  final String? externalEventId;
  final DateTime createdAt;
  final DateTime updatedAt;

  const LocalAttendanceLog({
    required this.id,
    required this.employeeId,
    required this.companyId,
    required this.checkIn,
    this.checkOut,
    required this.method,
    required this.workType,
    this.gpsLat,
    this.gpsLng,
    required this.status,
    required this.syncStatus,
    this.externalEventId,
    required this.createdAt,
    required this.updatedAt,
  });

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['employee_id'] = Variable<String>(employeeId);
    map['company_id'] = Variable<String>(companyId);
    map['check_in'] = Variable<DateTime>(checkIn);
    if (!nullToAbsent || checkOut != null) {
      map['check_out'] = Variable<DateTime>(checkOut);
    }
    map['method'] = Variable<String>(method);
    map['work_type'] = Variable<String>(workType);
    if (!nullToAbsent || gpsLat != null) {
      map['gps_lat'] = Variable<double>(gpsLat);
    }
    if (!nullToAbsent || gpsLng != null) {
      map['gps_lng'] = Variable<double>(gpsLng);
    }
    map['status'] = Variable<String>(status);
    map['sync_status'] = Variable<String>(syncStatus);
    if (!nullToAbsent || externalEventId != null) {
      map['external_event_id'] = Variable<String>(externalEventId);
    }
    map['created_at'] = Variable<DateTime>(createdAt);
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  LocalAttendanceLogsCompanion toCompanion(bool nullToAbsent) {
    return LocalAttendanceLogsCompanion(
      id: Value(id),
      employeeId: Value(employeeId),
      companyId: Value(companyId),
      checkIn: Value(checkIn),
      checkOut: checkOut == null && nullToAbsent
          ? const Value.absent()
          : Value(checkOut),
      method: Value(method),
      workType: Value(workType),
      gpsLat: gpsLat == null && nullToAbsent
          ? const Value.absent()
          : Value(gpsLat),
      gpsLng: gpsLng == null && nullToAbsent
          ? const Value.absent()
          : Value(gpsLng),
      status: Value(status),
      syncStatus: Value(syncStatus),
      externalEventId: externalEventId == null && nullToAbsent
          ? const Value.absent()
          : Value(externalEventId),
      createdAt: Value(createdAt),
      updatedAt: Value(updatedAt),
    );
  }

  factory LocalAttendanceLog.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LocalAttendanceLog(
      id: serializer.fromJson<String>(json['id']),
      employeeId: serializer.fromJson<String>(json['employee_id']),
      companyId: serializer.fromJson<String>(json['company_id']),
      checkIn: serializer.fromJson<DateTime>(json['check_in']),
      checkOut: serializer.fromJson<DateTime?>(json['check_out']),
      method: serializer.fromJson<String>(json['method']),
      workType: serializer.fromJson<String>(json['work_type']),
      gpsLat: serializer.fromJson<double?>(json['gps_lat']),
      gpsLng: serializer.fromJson<double?>(json['gps_lng']),
      status: serializer.fromJson<String>(json['status']),
      syncStatus: serializer.fromJson<String>(json['sync_status']),
      externalEventId: serializer.fromJson<String?>(json['external_event_id']),
      createdAt: serializer.fromJson<DateTime>(json['created_at']),
      updatedAt: serializer.fromJson<DateTime>(json['updated_at']),
    );
  }

  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'employee_id': serializer.toJson<String>(employeeId),
      'company_id': serializer.toJson<String>(companyId),
      'check_in': serializer.toJson<DateTime>(checkIn),
      'check_out': serializer.toJson<DateTime?>(checkOut),
      'method': serializer.toJson<String>(method),
      'work_type': serializer.toJson<String>(workType),
      'gps_lat': serializer.toJson<double?>(gpsLat),
      'gps_lng': serializer.toJson<double?>(gpsLng),
      'status': serializer.toJson<String>(status),
      'sync_status': serializer.toJson<String>(syncStatus),
      'external_event_id': serializer.toJson<String?>(externalEventId),
      'created_at': serializer.toJson<DateTime>(createdAt),
      'updated_at': serializer.toJson<DateTime>(updatedAt),
    };
  }

  LocalAttendanceLog copyWith({
    String? id,
    String? employeeId,
    String? companyId,
    DateTime? checkIn,
    Value<DateTime?> checkOut = const Value.absent(),
    String? method,
    String? workType,
    Value<double?> gpsLat = const Value.absent(),
    Value<double?> gpsLng = const Value.absent(),
    String? status,
    String? syncStatus,
    Value<String?> externalEventId = const Value.absent(),
    DateTime? createdAt,
    DateTime? updatedAt,
  }) => LocalAttendanceLog(
    id: id ?? this.id,
    employeeId: employeeId ?? this.employeeId,
    companyId: companyId ?? this.companyId,
    checkIn: checkIn ?? this.checkIn,
    checkOut: checkOut.present ? checkOut.value : this.checkOut,
    method: method ?? this.method,
    workType: workType ?? this.workType,
    gpsLat: gpsLat.present ? gpsLat.value : this.gpsLat,
    gpsLng: gpsLng.present ? gpsLng.value : this.gpsLng,
    status: status ?? this.status,
    syncStatus: syncStatus ?? this.syncStatus,
    externalEventId: externalEventId.present
        ? externalEventId.value
        : this.externalEventId,
    createdAt: createdAt ?? this.createdAt,
    updatedAt: updatedAt ?? this.updatedAt,
  );

  @override
  String toString() {
    return (StringBuffer('LocalAttendanceLog(')
          ..write('id: $id, ')
          ..write('employeeId: $employeeId, ')
          ..write('companyId: $companyId, ')
          ..write('checkIn: $checkIn, ')
          ..write('checkOut: $checkOut, ')
          ..write('method: $method, ')
          ..write('workType: $workType, ')
          ..write('gpsLat: $gpsLat, ')
          ..write('gpsLng: $gpsLng, ')
          ..write('status: $status, ')
          ..write('syncStatus: $syncStatus, ')
          ..write('externalEventId: $externalEventId, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    employeeId,
    companyId,
    checkIn,
    checkOut,
    method,
    workType,
    gpsLat,
    gpsLng,
    status,
    syncStatus,
    externalEventId,
    createdAt,
    updatedAt,
  );

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LocalAttendanceLog &&
          other.id == this.id &&
          other.employeeId == this.employeeId &&
          other.companyId == this.companyId &&
          other.checkIn == this.checkIn &&
          other.checkOut == this.checkOut &&
          other.method == this.method &&
          other.workType == this.workType &&
          other.gpsLat == this.gpsLat &&
          other.gpsLng == this.gpsLng &&
          other.status == this.status &&
          other.syncStatus == this.syncStatus &&
          other.externalEventId == this.externalEventId &&
          other.createdAt == this.createdAt &&
          other.updatedAt == this.updatedAt);
}

class LocalAttendanceLogsCompanion extends UpdateCompanion<LocalAttendanceLog> {
  final Value<String> id;
  final Value<String> employeeId;
  final Value<String> companyId;
  final Value<DateTime> checkIn;
  final Value<DateTime?> checkOut;
  final Value<String> method;
  final Value<String> workType;
  final Value<double?> gpsLat;
  final Value<double?> gpsLng;
  final Value<String> status;
  final Value<String> syncStatus;
  final Value<String?> externalEventId;
  final Value<DateTime> createdAt;
  final Value<DateTime> updatedAt;

  const LocalAttendanceLogsCompanion({
    this.id = const Value.absent(),
    this.employeeId = const Value.absent(),
    this.companyId = const Value.absent(),
    this.checkIn = const Value.absent(),
    this.checkOut = const Value.absent(),
    this.method = const Value.absent(),
    this.workType = const Value.absent(),
    this.gpsLat = const Value.absent(),
    this.gpsLng = const Value.absent(),
    this.status = const Value.absent(),
    this.syncStatus = const Value.absent(),
    this.externalEventId = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.updatedAt = const Value.absent(),
  });

  LocalAttendanceLogsCompanion.insert({
    Value<String> id = const Value.absent(),
    required String employeeId,
    required String companyId,
    required DateTime checkIn,
    Value<DateTime?> checkOut = const Value.absent(),
    Value<String> method = const Value.absent(),
    Value<String> workType = const Value.absent(),
    Value<double?> gpsLat = const Value.absent(),
    Value<double?> gpsLng = const Value.absent(),
    Value<String> status = const Value.absent(),
    Value<String> syncStatus = const Value.absent(),
    Value<String?> externalEventId = const Value.absent(),
    Value<DateTime> createdAt = const Value.absent(),
    Value<DateTime> updatedAt = const Value.absent(),
  }) : id = id,
       employeeId = Value(employeeId),
       companyId = Value(companyId),
       checkIn = Value(checkIn),
       checkOut = checkOut,
       method = method,
       workType = workType,
       gpsLat = gpsLat,
       gpsLng = gpsLng,
       status = status,
       syncStatus = syncStatus,
       externalEventId = externalEventId,
       createdAt = createdAt,
       updatedAt = updatedAt;

  static Insertable<LocalAttendanceLog> custom({
    Expression<String>? id,
    Expression<String>? employeeId,
    Expression<String>? companyId,
    Expression<DateTime>? checkIn,
    Expression<DateTime>? checkOut,
    Expression<String>? method,
    Expression<String>? workType,
    Expression<double>? gpsLat,
    Expression<double>? gpsLng,
    Expression<String>? status,
    Expression<String>? syncStatus,
    Expression<String>? externalEventId,
    Expression<DateTime>? createdAt,
    Expression<DateTime>? updatedAt,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (employeeId != null) 'employee_id': employeeId,
      if (companyId != null) 'company_id': companyId,
      if (checkIn != null) 'check_in': checkIn,
      if (checkOut != null) 'check_out': checkOut,
      if (method != null) 'method': method,
      if (workType != null) 'work_type': workType,
      if (gpsLat != null) 'gps_lat': gpsLat,
      if (gpsLng != null) 'gps_lng': gpsLng,
      if (status != null) 'status': status,
      if (syncStatus != null) 'sync_status': syncStatus,
      if (externalEventId != null) 'external_event_id': externalEventId,
      if (createdAt != null) 'created_at': createdAt,
      if (updatedAt != null) 'updated_at': updatedAt,
    });
  }

  LocalAttendanceLogsCompanion copyWith({
    Value<String>? id,
    Value<String>? employeeId,
    Value<String>? companyId,
    Value<DateTime>? checkIn,
    Value<DateTime?>? checkOut,
    Value<String>? method,
    Value<String>? workType,
    Value<double?>? gpsLat,
    Value<double?>? gpsLng,
    Value<String>? status,
    Value<String>? syncStatus,
    Value<String?>? externalEventId,
    Value<DateTime>? createdAt,
    Value<DateTime>? updatedAt,
  }) {
    return LocalAttendanceLogsCompanion(
      id: id ?? this.id,
      employeeId: employeeId ?? this.employeeId,
      companyId: companyId ?? this.companyId,
      checkIn: checkIn ?? this.checkIn,
      checkOut: checkOut ?? this.checkOut,
      method: method ?? this.method,
      workType: workType ?? this.workType,
      gpsLat: gpsLat ?? this.gpsLat,
      gpsLng: gpsLng ?? this.gpsLng,
      status: status ?? this.status,
      syncStatus: syncStatus ?? this.syncStatus,
      externalEventId: externalEventId ?? this.externalEventId,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) map['id'] = Variable<String>(id.value);
    if (employeeId.present)
      map['employee_id'] = Variable<String>(employeeId.value);
    if (companyId.present)
      map['company_id'] = Variable<String>(companyId.value);
    if (checkIn.present) map['check_in'] = Variable<DateTime>(checkIn.value);
    if (checkOut.present) map['check_out'] = Variable<DateTime>(checkOut.value);
    if (method.present) map['method'] = Variable<String>(method.value);
    if (workType.present) map['work_type'] = Variable<String>(workType.value);
    if (gpsLat.present) map['gps_lat'] = Variable<double>(gpsLat.value);
    if (gpsLng.present) map['gps_lng'] = Variable<double>(gpsLng.value);
    if (status.present) map['status'] = Variable<String>(status.value);
    if (syncStatus.present)
      map['sync_status'] = Variable<String>(syncStatus.value);
    if (externalEventId.present) {
      map['external_event_id'] = Variable<String>(externalEventId.value);
    }
    if (createdAt.present)
      map['created_at'] = Variable<DateTime>(createdAt.value);
    if (updatedAt.present)
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    return map;
  }

  Map<String, dynamic> toJson() {
    return {
      if (id.present) 'id': id.value,
      if (employeeId.present) 'employee_id': employeeId.value,
      if (companyId.present) 'company_id': companyId.value,
      if (checkIn.present) 'check_in': checkIn.value.toIso8601String(),
      if (checkOut.present) 'check_out': checkOut.value?.toIso8601String(),
      if (method.present) 'method': method.value,
      if (workType.present) 'work_type': workType.value,
      if (gpsLat.present) 'gps_lat': gpsLat.value,
      if (gpsLng.present) 'gps_lng': gpsLng.value,
      if (status.present) 'status': status.value,
      if (syncStatus.present) 'sync_status': syncStatus.value,
      if (externalEventId.present) 'external_event_id': externalEventId.value,
      if (createdAt.present) 'created_at': createdAt.value.toIso8601String(),
      if (updatedAt.present) 'updated_at': updatedAt.value.toIso8601String(),
    };
  }

  @override
  String toString() {
    return (StringBuffer('LocalAttendanceLogsCompanion(')
          ..write('id: $id, ')
          ..write('employeeId: $employeeId, ')
          ..write('companyId: $companyId, ')
          ..write('checkIn: $checkIn, ')
          ..write('checkOut: $checkOut, ')
          ..write('method: $method, ')
          ..write('workType: $workType, ')
          ..write('gpsLat: $gpsLat, ')
          ..write('gpsLng: $gpsLng, ')
          ..write('status: $status, ')
          ..write('syncStatus: $syncStatus, ')
          ..write('externalEventId: $externalEventId, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }
}

class $LocalAttendanceLogsTable extends LocalAttendanceLogs
    with TableInfo<$LocalAttendanceLogsTable, LocalAttendanceLog> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;

  $LocalAttendanceLogsTable(this.attachedDatabase, [this._alias]);

  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    clientDefault: () => _uuid(),
  );

  static const VerificationMeta _employeeIdMeta = const VerificationMeta(
    'employeeId',
  );
  @override
  late final GeneratedColumn<String> employeeId = GeneratedColumn<String>(
    'employee_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  static const VerificationMeta _companyIdMeta = const VerificationMeta(
    'companyId',
  );
  @override
  late final GeneratedColumn<String> companyId = GeneratedColumn<String>(
    'company_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  static const VerificationMeta _checkInMeta = const VerificationMeta(
    'checkIn',
  );
  @override
  late final GeneratedColumn<DateTime> checkIn = GeneratedColumn<DateTime>(
    'check_in',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );

  static const VerificationMeta _checkOutMeta = const VerificationMeta(
    'checkOut',
  );
  @override
  late final GeneratedColumn<DateTime> checkOut = GeneratedColumn<DateTime>(
    'check_out',
    aliasedName,
    true,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
  );

  static const VerificationMeta _methodMeta = const VerificationMeta('method');
  @override
  late final GeneratedColumn<String> method = GeneratedColumn<String>(
    'method',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('manual'),
  );

  static const VerificationMeta _workTypeMeta = const VerificationMeta(
    'workType',
  );
  @override
  late final GeneratedColumn<String> workType = GeneratedColumn<String>(
    'work_type',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('onsite'),
  );

  static const VerificationMeta _gpsLatMeta = const VerificationMeta('gpsLat');
  @override
  late final GeneratedColumn<double> gpsLat = GeneratedColumn<double>(
    'gps_lat',
    aliasedName,
    true,
    type: DriftSqlType.double,
    requiredDuringInsert: false,
  );

  static const VerificationMeta _gpsLngMeta = const VerificationMeta('gpsLng');
  @override
  late final GeneratedColumn<double> gpsLng = GeneratedColumn<double>(
    'gps_lng',
    aliasedName,
    true,
    type: DriftSqlType.double,
    requiredDuringInsert: false,
  );

  static const VerificationMeta _statusMeta = const VerificationMeta('status');
  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('present'),
  );

  static const VerificationMeta _syncStatusMeta = const VerificationMeta(
    'syncStatus',
  );
  @override
  late final GeneratedColumn<String> syncStatus = GeneratedColumn<String>(
    'sync_status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('pending'),
  );

  static const VerificationMeta _externalEventIdMeta = const VerificationMeta(
    'externalEventId',
  );
  @override
  late final GeneratedColumn<String> externalEventId = GeneratedColumn<String>(
    'external_event_id',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );

  static const VerificationMeta _createdAtMeta = const VerificationMeta(
    'createdAt',
  );
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
    clientDefault: () => DateTime.now(),
  );

  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
    clientDefault: () => DateTime.now(),
  );

  @override
  List<GeneratedColumn> get $columns => [
    id,
    employeeId,
    companyId,
    checkIn,
    checkOut,
    method,
    workType,
    gpsLat,
    gpsLng,
    status,
    syncStatus,
    externalEventId,
    createdAt,
    updatedAt,
  ];

  @override
  String get aliasedName => _alias ?? actualTableName;

  @override
  String get actualTableName => $name;

  static const String $name = 'local_attendance_logs';

  @override
  VerificationContext validateIntegrity(
    Insertable<LocalAttendanceLog> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('employee_id')) {
      context.handle(
        _employeeIdMeta,
        employeeId.isAcceptableOrUnknown(data['employee_id']!, _employeeIdMeta),
      );
    } else if (isInserting) {
      context.missing(_employeeIdMeta);
    }
    if (data.containsKey('company_id')) {
      context.handle(
        _companyIdMeta,
        companyId.isAcceptableOrUnknown(data['company_id']!, _companyIdMeta),
      );
    } else if (isInserting) {
      context.missing(_companyIdMeta);
    }
    if (data.containsKey('check_in')) {
      context.handle(
        _checkInMeta,
        checkIn.isAcceptableOrUnknown(data['check_in']!, _checkInMeta),
      );
    } else if (isInserting) {
      context.missing(_checkInMeta);
    }
    if (data.containsKey('check_out')) {
      context.handle(
        _checkOutMeta,
        checkOut.isAcceptableOrUnknown(data['check_out']!, _checkOutMeta),
      );
    }
    if (data.containsKey('method')) {
      context.handle(
        _methodMeta,
        method.isAcceptableOrUnknown(data['method']!, _methodMeta),
      );
    }
    if (data.containsKey('work_type')) {
      context.handle(
        _workTypeMeta,
        workType.isAcceptableOrUnknown(data['work_type']!, _workTypeMeta),
      );
    }
    if (data.containsKey('gps_lat')) {
      context.handle(
        _gpsLatMeta,
        gpsLat.isAcceptableOrUnknown(data['gps_lat']!, _gpsLatMeta),
      );
    }
    if (data.containsKey('gps_lng')) {
      context.handle(
        _gpsLngMeta,
        gpsLng.isAcceptableOrUnknown(data['gps_lng']!, _gpsLngMeta),
      );
    }
    if (data.containsKey('status')) {
      context.handle(
        _statusMeta,
        status.isAcceptableOrUnknown(data['status']!, _statusMeta),
      );
    }
    if (data.containsKey('sync_status')) {
      context.handle(
        _syncStatusMeta,
        syncStatus.isAcceptableOrUnknown(data['sync_status']!, _syncStatusMeta),
      );
    }
    if (data.containsKey('external_event_id')) {
      context.handle(
        _externalEventIdMeta,
        externalEventId.isAcceptableOrUnknown(
          data['external_event_id']!,
          _externalEventIdMeta,
        ),
      );
    }
    if (data.containsKey('created_at')) {
      context.handle(
        _createdAtMeta,
        createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta),
      );
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};

  @override
  LocalAttendanceLog map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LocalAttendanceLog(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}id'],
      )!,
      employeeId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}employee_id'],
      )!,
      companyId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}company_id'],
      )!,
      checkIn: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}check_in'],
      )!,
      checkOut: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}check_out'],
      ),
      method: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}method'],
      )!,
      workType: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}work_type'],
      )!,
      gpsLat: attachedDatabase.typeMapping.read(
        DriftSqlType.double,
        data['${effectivePrefix}gps_lat'],
      ),
      gpsLng: attachedDatabase.typeMapping.read(
        DriftSqlType.double,
        data['${effectivePrefix}gps_lng'],
      ),
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}status'],
      )!,
      syncStatus: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}sync_status'],
      )!,
      externalEventId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}external_event_id'],
      ),
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}created_at'],
      )!,
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $LocalAttendanceLogsTable createAlias(String alias) {
    return $LocalAttendanceLogsTable(attachedDatabase, alias);
  }
}

// ── LocalAbsence ──────────────────────────────────────────

class LocalAbsence extends DataClass implements Insertable<LocalAbsence> {
  final String id;
  final String employeeId;
  final String companyId;
  final String absenceTypeId;
  final DateTime startDate;
  final DateTime endDate;
  final String? reason;
  final String status;
  final String syncStatus;
  final DateTime createdAt;
  final DateTime updatedAt;

  const LocalAbsence({
    required this.id,
    required this.employeeId,
    required this.companyId,
    required this.absenceTypeId,
    required this.startDate,
    required this.endDate,
    this.reason,
    required this.status,
    required this.syncStatus,
    required this.createdAt,
    required this.updatedAt,
  });

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['employee_id'] = Variable<String>(employeeId);
    map['company_id'] = Variable<String>(companyId);
    map['absence_type_id'] = Variable<String>(absenceTypeId);
    map['start_date'] = Variable<DateTime>(startDate);
    map['end_date'] = Variable<DateTime>(endDate);
    if (!nullToAbsent || reason != null) {
      map['reason'] = Variable<String>(reason);
    }
    map['status'] = Variable<String>(status);
    map['sync_status'] = Variable<String>(syncStatus);
    map['created_at'] = Variable<DateTime>(createdAt);
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  LocalAbsencesCompanion toCompanion(bool nullToAbsent) {
    return LocalAbsencesCompanion(
      id: Value(id),
      employeeId: Value(employeeId),
      companyId: Value(companyId),
      absenceTypeId: Value(absenceTypeId),
      startDate: Value(startDate),
      endDate: Value(endDate),
      reason: reason == null && nullToAbsent
          ? const Value.absent()
          : Value(reason),
      status: Value(status),
      syncStatus: Value(syncStatus),
      createdAt: Value(createdAt),
      updatedAt: Value(updatedAt),
    );
  }

  factory LocalAbsence.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LocalAbsence(
      id: serializer.fromJson<String>(json['id']),
      employeeId: serializer.fromJson<String>(json['employee_id']),
      companyId: serializer.fromJson<String>(json['company_id']),
      absenceTypeId: serializer.fromJson<String>(json['absence_type_id']),
      startDate: serializer.fromJson<DateTime>(json['start_date']),
      endDate: serializer.fromJson<DateTime>(json['end_date']),
      reason: serializer.fromJson<String?>(json['reason']),
      status: serializer.fromJson<String>(json['status']),
      syncStatus: serializer.fromJson<String>(json['sync_status']),
      createdAt: serializer.fromJson<DateTime>(json['created_at']),
      updatedAt: serializer.fromJson<DateTime>(json['updated_at']),
    );
  }

  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'employee_id': serializer.toJson<String>(employeeId),
      'company_id': serializer.toJson<String>(companyId),
      'absence_type_id': serializer.toJson<String>(absenceTypeId),
      'start_date': serializer.toJson<DateTime>(startDate),
      'end_date': serializer.toJson<DateTime>(endDate),
      'reason': serializer.toJson<String?>(reason),
      'status': serializer.toJson<String>(status),
      'sync_status': serializer.toJson<String>(syncStatus),
      'created_at': serializer.toJson<DateTime>(createdAt),
      'updated_at': serializer.toJson<DateTime>(updatedAt),
    };
  }

  LocalAbsence copyWith({
    String? id,
    String? employeeId,
    String? companyId,
    String? absenceTypeId,
    DateTime? startDate,
    DateTime? endDate,
    Value<String?> reason = const Value.absent(),
    String? status,
    String? syncStatus,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) => LocalAbsence(
    id: id ?? this.id,
    employeeId: employeeId ?? this.employeeId,
    companyId: companyId ?? this.companyId,
    absenceTypeId: absenceTypeId ?? this.absenceTypeId,
    startDate: startDate ?? this.startDate,
    endDate: endDate ?? this.endDate,
    reason: reason.present ? reason.value : this.reason,
    status: status ?? this.status,
    syncStatus: syncStatus ?? this.syncStatus,
    createdAt: createdAt ?? this.createdAt,
    updatedAt: updatedAt ?? this.updatedAt,
  );

  @override
  String toString() {
    return (StringBuffer('LocalAbsence(')
          ..write('id: $id, ')
          ..write('employeeId: $employeeId, ')
          ..write('companyId: $companyId, ')
          ..write('absenceTypeId: $absenceTypeId, ')
          ..write('startDate: $startDate, ')
          ..write('endDate: $endDate, ')
          ..write('reason: $reason, ')
          ..write('status: $status, ')
          ..write('syncStatus: $syncStatus, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    employeeId,
    companyId,
    absenceTypeId,
    startDate,
    endDate,
    reason,
    status,
    syncStatus,
    createdAt,
    updatedAt,
  );

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LocalAbsence &&
          other.id == this.id &&
          other.employeeId == this.employeeId &&
          other.companyId == this.companyId &&
          other.absenceTypeId == this.absenceTypeId &&
          other.startDate == this.startDate &&
          other.endDate == this.endDate &&
          other.reason == this.reason &&
          other.status == this.status &&
          other.syncStatus == this.syncStatus &&
          other.createdAt == this.createdAt &&
          other.updatedAt == this.updatedAt);
}

class LocalAbsencesCompanion extends UpdateCompanion<LocalAbsence> {
  final Value<String> id;
  final Value<String> employeeId;
  final Value<String> companyId;
  final Value<String> absenceTypeId;
  final Value<DateTime> startDate;
  final Value<DateTime> endDate;
  final Value<String?> reason;
  final Value<String> status;
  final Value<String> syncStatus;
  final Value<DateTime> createdAt;
  final Value<DateTime> updatedAt;

  const LocalAbsencesCompanion({
    this.id = const Value.absent(),
    this.employeeId = const Value.absent(),
    this.companyId = const Value.absent(),
    this.absenceTypeId = const Value.absent(),
    this.startDate = const Value.absent(),
    this.endDate = const Value.absent(),
    this.reason = const Value.absent(),
    this.status = const Value.absent(),
    this.syncStatus = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.updatedAt = const Value.absent(),
  });

  LocalAbsencesCompanion.insert({
    Value<String> id = const Value.absent(),
    required String employeeId,
    required String companyId,
    required String absenceTypeId,
    required DateTime startDate,
    required DateTime endDate,
    Value<String?> reason = const Value.absent(),
    Value<String> status = const Value.absent(),
    Value<String> syncStatus = const Value.absent(),
    Value<DateTime> createdAt = const Value.absent(),
    Value<DateTime> updatedAt = const Value.absent(),
  }) : id = id,
       employeeId = Value(employeeId),
       companyId = Value(companyId),
       absenceTypeId = Value(absenceTypeId),
       startDate = Value(startDate),
       endDate = Value(endDate),
       reason = reason,
       status = status,
       syncStatus = syncStatus,
       createdAt = createdAt,
       updatedAt = updatedAt;

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) map['id'] = Variable<String>(id.value);
    if (employeeId.present)
      map['employee_id'] = Variable<String>(employeeId.value);
    if (companyId.present)
      map['company_id'] = Variable<String>(companyId.value);
    if (absenceTypeId.present)
      map['absence_type_id'] = Variable<String>(absenceTypeId.value);
    if (startDate.present)
      map['start_date'] = Variable<DateTime>(startDate.value);
    if (endDate.present) map['end_date'] = Variable<DateTime>(endDate.value);
    if (reason.present) map['reason'] = Variable<String>(reason.value);
    if (status.present) map['status'] = Variable<String>(status.value);
    if (syncStatus.present)
      map['sync_status'] = Variable<String>(syncStatus.value);
    if (createdAt.present)
      map['created_at'] = Variable<DateTime>(createdAt.value);
    if (updatedAt.present)
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LocalAbsencesCompanion(')
          ..write('id: $id, ')
          ..write('employeeId: $employeeId, ')
          ..write('companyId: $companyId, ')
          ..write('absenceTypeId: $absenceTypeId, ')
          ..write('startDate: $startDate, ')
          ..write('endDate: $endDate, ')
          ..write('reason: $reason, ')
          ..write('status: $status, ')
          ..write('syncStatus: $syncStatus, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }
}

class $LocalAbsencesTable extends LocalAbsences
    with TableInfo<$LocalAbsencesTable, LocalAbsence> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;

  $LocalAbsencesTable(this.attachedDatabase, [this._alias]);

  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    clientDefault: () => _uuid(),
  );

  @override
  late final GeneratedColumn<String> employeeId = GeneratedColumn<String>(
    'employee_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<String> companyId = GeneratedColumn<String>(
    'company_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<String> absenceTypeId = GeneratedColumn<String>(
    'absence_type_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<DateTime> startDate = GeneratedColumn<DateTime>(
    'start_date',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<DateTime> endDate = GeneratedColumn<DateTime>(
    'end_date',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<String> reason = GeneratedColumn<String>(
    'reason',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );

  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('pending'),
  );

  @override
  late final GeneratedColumn<String> syncStatus = GeneratedColumn<String>(
    'sync_status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('pending'),
  );

  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
    clientDefault: () => DateTime.now(),
  );

  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
    clientDefault: () => DateTime.now(),
  );

  @override
  List<GeneratedColumn> get $columns => [
    id,
    employeeId,
    companyId,
    absenceTypeId,
    startDate,
    endDate,
    reason,
    status,
    syncStatus,
    createdAt,
    updatedAt,
  ];

  @override
  String get aliasedName => _alias ?? actualTableName;

  @override
  String get actualTableName => $name;

  static const String $name = 'local_absences';

  @override
  VerificationContext validateIntegrity(
    Insertable<LocalAbsence> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(
        const VerificationMeta('id'),
        id.isAcceptableOrUnknown(data['id']!, const VerificationMeta('id')),
      );
    }
    if (data.containsKey('employee_id')) {
      context.handle(
        const VerificationMeta('employeeId'),
        employeeId.isAcceptableOrUnknown(
          data['employee_id']!,
          const VerificationMeta('employeeId'),
        ),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('employeeId'));
    }
    if (data.containsKey('company_id')) {
      context.handle(
        const VerificationMeta('companyId'),
        companyId.isAcceptableOrUnknown(
          data['company_id']!,
          const VerificationMeta('companyId'),
        ),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('companyId'));
    }
    if (data.containsKey('absence_type_id')) {
      context.handle(
        const VerificationMeta('absenceTypeId'),
        absenceTypeId.isAcceptableOrUnknown(
          data['absence_type_id']!,
          const VerificationMeta('absenceTypeId'),
        ),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('absenceTypeId'));
    }
    if (data.containsKey('start_date')) {
      context.handle(
        const VerificationMeta('startDate'),
        startDate.isAcceptableOrUnknown(
          data['start_date']!,
          const VerificationMeta('startDate'),
        ),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('startDate'));
    }
    if (data.containsKey('end_date')) {
      context.handle(
        const VerificationMeta('endDate'),
        endDate.isAcceptableOrUnknown(
          data['end_date']!,
          const VerificationMeta('endDate'),
        ),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('endDate'));
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};

  @override
  LocalAbsence map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LocalAbsence(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}id'],
      )!,
      employeeId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}employee_id'],
      )!,
      companyId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}company_id'],
      )!,
      absenceTypeId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}absence_type_id'],
      )!,
      startDate: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}start_date'],
      )!,
      endDate: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}end_date'],
      )!,
      reason: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}reason'],
      ),
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}status'],
      )!,
      syncStatus: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}sync_status'],
      )!,
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}created_at'],
      )!,
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $LocalAbsencesTable createAlias(String alias) {
    return $LocalAbsencesTable(attachedDatabase, alias);
  }
}

// ── LocalEmployee ─────────────────────────────────────────

class LocalEmployee extends DataClass implements Insertable<LocalEmployee> {
  final String id;
  final String companyId;
  final String firstName;
  final String lastName;
  final String email;
  final String? phone;
  final String? departmentId;
  final String? positionId;
  final String role;
  final String status;
  final String? faceEncoding;
  final String? biometricId;
  final DateTime updatedAt;

  const LocalEmployee({
    required this.id,
    required this.companyId,
    required this.firstName,
    required this.lastName,
    required this.email,
    this.phone,
    this.departmentId,
    this.positionId,
    required this.role,
    required this.status,
    this.faceEncoding,
    this.biometricId,
    required this.updatedAt,
  });

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['company_id'] = Variable<String>(companyId);
    map['first_name'] = Variable<String>(firstName);
    map['last_name'] = Variable<String>(lastName);
    map['email'] = Variable<String>(email);
    if (!nullToAbsent || phone != null) map['phone'] = Variable<String>(phone);
    if (!nullToAbsent || departmentId != null)
      map['department_id'] = Variable<String>(departmentId);
    if (!nullToAbsent || positionId != null)
      map['position_id'] = Variable<String>(positionId);
    map['role'] = Variable<String>(role);
    map['status'] = Variable<String>(status);
    if (!nullToAbsent || faceEncoding != null)
      map['face_encoding'] = Variable<String>(faceEncoding);
    if (!nullToAbsent || biometricId != null)
      map['biometric_id'] = Variable<String>(biometricId);
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  LocalEmployeesCompanion toCompanion(bool nullToAbsent) {
    return LocalEmployeesCompanion(
      id: Value(id),
      companyId: Value(companyId),
      firstName: Value(firstName),
      lastName: Value(lastName),
      email: Value(email),
      phone: phone == null && nullToAbsent
          ? const Value.absent()
          : Value(phone),
      departmentId: departmentId == null && nullToAbsent
          ? const Value.absent()
          : Value(departmentId),
      positionId: positionId == null && nullToAbsent
          ? const Value.absent()
          : Value(positionId),
      role: Value(role),
      status: Value(status),
      faceEncoding: faceEncoding == null && nullToAbsent
          ? const Value.absent()
          : Value(faceEncoding),
      biometricId: biometricId == null && nullToAbsent
          ? const Value.absent()
          : Value(biometricId),
      updatedAt: Value(updatedAt),
    );
  }

  factory LocalEmployee.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LocalEmployee(
      id: serializer.fromJson<String>(json['id']),
      companyId: serializer.fromJson<String>(json['company_id']),
      firstName: serializer.fromJson<String>(json['first_name']),
      lastName: serializer.fromJson<String>(json['last_name']),
      email: serializer.fromJson<String>(json['email']),
      phone: serializer.fromJson<String?>(json['phone']),
      departmentId: serializer.fromJson<String?>(json['department_id']),
      positionId: serializer.fromJson<String?>(json['position_id']),
      role: serializer.fromJson<String>(json['role']),
      status: serializer.fromJson<String>(json['status']),
      faceEncoding: serializer.fromJson<String?>(json['face_encoding']),
      biometricId: serializer.fromJson<String?>(json['biometric_id']),
      updatedAt: serializer.fromJson<DateTime>(json['updated_at']),
    );
  }

  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'company_id': serializer.toJson<String>(companyId),
      'first_name': serializer.toJson<String>(firstName),
      'last_name': serializer.toJson<String>(lastName),
      'email': serializer.toJson<String>(email),
      'phone': serializer.toJson<String?>(phone),
      'department_id': serializer.toJson<String?>(departmentId),
      'position_id': serializer.toJson<String?>(positionId),
      'role': serializer.toJson<String>(role),
      'status': serializer.toJson<String>(status),
      'face_encoding': serializer.toJson<String?>(faceEncoding),
      'biometric_id': serializer.toJson<String?>(biometricId),
      'updated_at': serializer.toJson<DateTime>(updatedAt),
    };
  }

  LocalEmployee copyWith({
    String? id,
    String? companyId,
    String? firstName,
    String? lastName,
    String? email,
    Value<String?> phone = const Value.absent(),
    Value<String?> departmentId = const Value.absent(),
    Value<String?> positionId = const Value.absent(),
    String? role,
    String? status,
    Value<String?> faceEncoding = const Value.absent(),
    Value<String?> biometricId = const Value.absent(),
    DateTime? updatedAt,
  }) => LocalEmployee(
    id: id ?? this.id,
    companyId: companyId ?? this.companyId,
    firstName: firstName ?? this.firstName,
    lastName: lastName ?? this.lastName,
    email: email ?? this.email,
    phone: phone.present ? phone.value : this.phone,
    departmentId: departmentId.present ? departmentId.value : this.departmentId,
    positionId: positionId.present ? positionId.value : this.positionId,
    role: role ?? this.role,
    status: status ?? this.status,
    faceEncoding: faceEncoding.present ? faceEncoding.value : this.faceEncoding,
    biometricId: biometricId.present ? biometricId.value : this.biometricId,
    updatedAt: updatedAt ?? this.updatedAt,
  );

  @override
  String toString() {
    return (StringBuffer('LocalEmployee(')
          ..write('id: $id, ')
          ..write('companyId: $companyId, ')
          ..write('firstName: $firstName, ')
          ..write('lastName: $lastName, ')
          ..write('email: $email, ')
          ..write('phone: $phone, ')
          ..write('departmentId: $departmentId, ')
          ..write('positionId: $positionId, ')
          ..write('role: $role, ')
          ..write('status: $status, ')
          ..write('faceEncoding: $faceEncoding, ')
          ..write('biometricId: $biometricId, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    companyId,
    firstName,
    lastName,
    email,
    phone,
    departmentId,
    positionId,
    role,
    status,
    faceEncoding,
    biometricId,
    updatedAt,
  );

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LocalEmployee &&
          other.id == this.id &&
          other.companyId == this.companyId &&
          other.firstName == this.firstName &&
          other.lastName == this.lastName &&
          other.email == this.email &&
          other.phone == this.phone &&
          other.departmentId == this.departmentId &&
          other.positionId == this.positionId &&
          other.role == this.role &&
          other.status == this.status &&
          other.faceEncoding == this.faceEncoding &&
          other.biometricId == this.biometricId &&
          other.updatedAt == this.updatedAt);
}

class LocalEmployeesCompanion extends UpdateCompanion<LocalEmployee> {
  final Value<String> id;
  final Value<String> companyId;
  final Value<String> firstName;
  final Value<String> lastName;
  final Value<String> email;
  final Value<String?> phone;
  final Value<String?> departmentId;
  final Value<String?> positionId;
  final Value<String> role;
  final Value<String> status;
  final Value<String?> faceEncoding;
  final Value<String?> biometricId;
  final Value<DateTime> updatedAt;

  const LocalEmployeesCompanion({
    this.id = const Value.absent(),
    this.companyId = const Value.absent(),
    this.firstName = const Value.absent(),
    this.lastName = const Value.absent(),
    this.email = const Value.absent(),
    this.phone = const Value.absent(),
    this.departmentId = const Value.absent(),
    this.positionId = const Value.absent(),
    this.role = const Value.absent(),
    this.status = const Value.absent(),
    this.faceEncoding = const Value.absent(),
    this.biometricId = const Value.absent(),
    this.updatedAt = const Value.absent(),
  });

  LocalEmployeesCompanion.insert({
    required String id,
    required String companyId,
    required String firstName,
    required String lastName,
    required String email,
    Value<String?> phone = const Value.absent(),
    Value<String?> departmentId = const Value.absent(),
    Value<String?> positionId = const Value.absent(),
    Value<String> role = const Value.absent(),
    Value<String> status = const Value.absent(),
    Value<String?> faceEncoding = const Value.absent(),
    Value<String?> biometricId = const Value.absent(),
    Value<DateTime> updatedAt = const Value.absent(),
  }) : id = Value(id),
       companyId = Value(companyId),
       firstName = Value(firstName),
       lastName = Value(lastName),
       email = Value(email),
       phone = phone,
       departmentId = departmentId,
       positionId = positionId,
       role = role,
       status = status,
       faceEncoding = faceEncoding,
       biometricId = biometricId,
       updatedAt = updatedAt;

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) map['id'] = Variable<String>(id.value);
    if (companyId.present)
      map['company_id'] = Variable<String>(companyId.value);
    if (firstName.present)
      map['first_name'] = Variable<String>(firstName.value);
    if (lastName.present) map['last_name'] = Variable<String>(lastName.value);
    if (email.present) map['email'] = Variable<String>(email.value);
    if (phone.present) map['phone'] = Variable<String>(phone.value);
    if (departmentId.present)
      map['department_id'] = Variable<String>(departmentId.value);
    if (positionId.present)
      map['position_id'] = Variable<String>(positionId.value);
    if (role.present) map['role'] = Variable<String>(role.value);
    if (status.present) map['status'] = Variable<String>(status.value);
    if (faceEncoding.present)
      map['face_encoding'] = Variable<String>(faceEncoding.value);
    if (biometricId.present)
      map['biometric_id'] = Variable<String>(biometricId.value);
    if (updatedAt.present)
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LocalEmployeesCompanion(')
          ..write('id: $id, ')
          ..write('companyId: $companyId, ')
          ..write('firstName: $firstName, ')
          ..write('lastName: $lastName, ')
          ..write('email: $email, ')
          ..write('phone: $phone, ')
          ..write('departmentId: $departmentId, ')
          ..write('positionId: $positionId, ')
          ..write('role: $role, ')
          ..write('status: $status, ')
          ..write('faceEncoding: $faceEncoding, ')
          ..write('biometricId: $biometricId, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }
}

class $LocalEmployeesTable extends LocalEmployees
    with TableInfo<$LocalEmployeesTable, LocalEmployee> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;

  $LocalEmployeesTable(this.attachedDatabase, [this._alias]);

  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<String> companyId = GeneratedColumn<String>(
    'company_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<String> firstName = GeneratedColumn<String>(
    'first_name',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<String> lastName = GeneratedColumn<String>(
    'last_name',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<String> email = GeneratedColumn<String>(
    'email',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<String> phone = GeneratedColumn<String>(
    'phone',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );

  @override
  late final GeneratedColumn<String> departmentId = GeneratedColumn<String>(
    'department_id',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );

  @override
  late final GeneratedColumn<String> positionId = GeneratedColumn<String>(
    'position_id',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );

  @override
  late final GeneratedColumn<String> role = GeneratedColumn<String>(
    'role',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('employee'),
  );

  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('active'),
  );

  @override
  late final GeneratedColumn<String> faceEncoding = GeneratedColumn<String>(
    'face_encoding',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );

  @override
  late final GeneratedColumn<String> biometricId = GeneratedColumn<String>(
    'biometric_id',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );

  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
    clientDefault: () => DateTime.now(),
  );

  @override
  List<GeneratedColumn> get $columns => [
    id,
    companyId,
    firstName,
    lastName,
    email,
    phone,
    departmentId,
    positionId,
    role,
    status,
    faceEncoding,
    biometricId,
    updatedAt,
  ];

  @override
  String get aliasedName => _alias ?? actualTableName;

  @override
  String get actualTableName => $name;

  static const String $name = 'local_employees';

  @override
  VerificationContext validateIntegrity(
    Insertable<LocalEmployee> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(
        const VerificationMeta('id'),
        id.isAcceptableOrUnknown(data['id']!, const VerificationMeta('id')),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('id'));
    }
    if (data.containsKey('company_id')) {
      context.handle(
        const VerificationMeta('companyId'),
        companyId.isAcceptableOrUnknown(
          data['company_id']!,
          const VerificationMeta('companyId'),
        ),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('companyId'));
    }
    if (data.containsKey('first_name')) {
      context.handle(
        const VerificationMeta('firstName'),
        firstName.isAcceptableOrUnknown(
          data['first_name']!,
          const VerificationMeta('firstName'),
        ),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('firstName'));
    }
    if (data.containsKey('last_name')) {
      context.handle(
        const VerificationMeta('lastName'),
        lastName.isAcceptableOrUnknown(
          data['last_name']!,
          const VerificationMeta('lastName'),
        ),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('lastName'));
    }
    if (data.containsKey('email')) {
      context.handle(
        const VerificationMeta('email'),
        email.isAcceptableOrUnknown(
          data['email']!,
          const VerificationMeta('email'),
        ),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('email'));
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};

  @override
  LocalEmployee map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LocalEmployee(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}id'],
      )!,
      companyId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}company_id'],
      )!,
      firstName: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}first_name'],
      )!,
      lastName: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}last_name'],
      )!,
      email: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}email'],
      )!,
      phone: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}phone'],
      ),
      departmentId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}department_id'],
      ),
      positionId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}position_id'],
      ),
      role: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}role'],
      )!,
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}status'],
      )!,
      faceEncoding: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}face_encoding'],
      ),
      biometricId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}biometric_id'],
      ),
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $LocalEmployeesTable createAlias(String alias) {
    return $LocalEmployeesTable(attachedDatabase, alias);
  }
}

// ── LocalSyncQueueItem ────────────────────────────────────

class LocalSyncQueueItem extends DataClass
    implements Insertable<LocalSyncQueueItem> {
  final String id;
  final String entityType;
  final String entityId;
  final String operation;
  final String payload;
  final String status;
  final int attemptCount;
  final DateTime createdAt;
  final DateTime? syncedAt;

  const LocalSyncQueueItem({
    required this.id,
    required this.entityType,
    required this.entityId,
    required this.operation,
    required this.payload,
    required this.status,
    required this.attemptCount,
    required this.createdAt,
    this.syncedAt,
  });

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['entity_type'] = Variable<String>(entityType);
    map['entity_id'] = Variable<String>(entityId);
    map['operation'] = Variable<String>(operation);
    map['payload'] = Variable<String>(payload);
    map['status'] = Variable<String>(status);
    map['attempt_count'] = Variable<int>(attemptCount);
    map['created_at'] = Variable<DateTime>(createdAt);
    if (!nullToAbsent || syncedAt != null) {
      map['synced_at'] = Variable<DateTime>(syncedAt);
    }
    return map;
  }

  LocalSyncQueueCompanion toCompanion(bool nullToAbsent) {
    return LocalSyncQueueCompanion(
      id: Value(id),
      entityType: Value(entityType),
      entityId: Value(entityId),
      operation: Value(operation),
      payload: Value(payload),
      status: Value(status),
      attemptCount: Value(attemptCount),
      createdAt: Value(createdAt),
      syncedAt: syncedAt == null && nullToAbsent
          ? const Value.absent()
          : Value(syncedAt),
    );
  }

  factory LocalSyncQueueItem.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LocalSyncQueueItem(
      id: serializer.fromJson<String>(json['id']),
      entityType: serializer.fromJson<String>(json['entity_type']),
      entityId: serializer.fromJson<String>(json['entity_id']),
      operation: serializer.fromJson<String>(json['operation']),
      payload: serializer.fromJson<String>(json['payload']),
      status: serializer.fromJson<String>(json['status']),
      attemptCount: serializer.fromJson<int>(json['attempt_count']),
      createdAt: serializer.fromJson<DateTime>(json['created_at']),
      syncedAt: serializer.fromJson<DateTime?>(json['synced_at']),
    );
  }

  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'entity_type': serializer.toJson<String>(entityType),
      'entity_id': serializer.toJson<String>(entityId),
      'operation': serializer.toJson<String>(operation),
      'payload': serializer.toJson<String>(payload),
      'status': serializer.toJson<String>(status),
      'attempt_count': serializer.toJson<int>(attemptCount),
      'created_at': serializer.toJson<DateTime>(createdAt),
      'synced_at': serializer.toJson<DateTime?>(syncedAt),
    };
  }

  LocalSyncQueueItem copyWith({
    String? id,
    String? entityType,
    String? entityId,
    String? operation,
    String? payload,
    String? status,
    int? attemptCount,
    DateTime? createdAt,
    Value<DateTime?> syncedAt = const Value.absent(),
  }) => LocalSyncQueueItem(
    id: id ?? this.id,
    entityType: entityType ?? this.entityType,
    entityId: entityId ?? this.entityId,
    operation: operation ?? this.operation,
    payload: payload ?? this.payload,
    status: status ?? this.status,
    attemptCount: attemptCount ?? this.attemptCount,
    createdAt: createdAt ?? this.createdAt,
    syncedAt: syncedAt.present ? syncedAt.value : this.syncedAt,
  );

  @override
  String toString() {
    return (StringBuffer('LocalSyncQueueItem(')
          ..write('id: $id, ')
          ..write('entityType: $entityType, ')
          ..write('entityId: $entityId, ')
          ..write('operation: $operation, ')
          ..write('payload: $payload, ')
          ..write('status: $status, ')
          ..write('attemptCount: $attemptCount, ')
          ..write('createdAt: $createdAt, ')
          ..write('syncedAt: $syncedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    entityType,
    entityId,
    operation,
    payload,
    status,
    attemptCount,
    createdAt,
    syncedAt,
  );

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LocalSyncQueueItem &&
          other.id == this.id &&
          other.entityType == this.entityType &&
          other.entityId == this.entityId &&
          other.operation == this.operation &&
          other.payload == this.payload &&
          other.status == this.status &&
          other.attemptCount == this.attemptCount &&
          other.createdAt == this.createdAt &&
          other.syncedAt == this.syncedAt);
}

class LocalSyncQueueCompanion extends UpdateCompanion<LocalSyncQueueItem> {
  final Value<String> id;
  final Value<String> entityType;
  final Value<String> entityId;
  final Value<String> operation;
  final Value<String> payload;
  final Value<String> status;
  final Value<int> attemptCount;
  final Value<DateTime> createdAt;
  final Value<DateTime?> syncedAt;

  const LocalSyncQueueCompanion({
    this.id = const Value.absent(),
    this.entityType = const Value.absent(),
    this.entityId = const Value.absent(),
    this.operation = const Value.absent(),
    this.payload = const Value.absent(),
    this.status = const Value.absent(),
    this.attemptCount = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.syncedAt = const Value.absent(),
  });

  LocalSyncQueueCompanion.insert({
    Value<String> id = const Value.absent(),
    required String entityType,
    required String entityId,
    required String operation,
    required String payload,
    Value<String> status = const Value.absent(),
    Value<int> attemptCount = const Value.absent(),
    Value<DateTime> createdAt = const Value.absent(),
    Value<DateTime?> syncedAt = const Value.absent(),
  }) : id = id,
       entityType = Value(entityType),
       entityId = Value(entityId),
       operation = Value(operation),
       payload = Value(payload),
       status = status,
       attemptCount = attemptCount,
       createdAt = createdAt,
       syncedAt = syncedAt;

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) map['id'] = Variable<String>(id.value);
    if (entityType.present)
      map['entity_type'] = Variable<String>(entityType.value);
    if (entityId.present) map['entity_id'] = Variable<String>(entityId.value);
    if (operation.present) map['operation'] = Variable<String>(operation.value);
    if (payload.present) map['payload'] = Variable<String>(payload.value);
    if (status.present) map['status'] = Variable<String>(status.value);
    if (attemptCount.present)
      map['attempt_count'] = Variable<int>(attemptCount.value);
    if (createdAt.present)
      map['created_at'] = Variable<DateTime>(createdAt.value);
    if (syncedAt.present) map['synced_at'] = Variable<DateTime>(syncedAt.value);
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LocalSyncQueueCompanion(')
          ..write('id: $id, ')
          ..write('entityType: $entityType, ')
          ..write('entityId: $entityId, ')
          ..write('operation: $operation, ')
          ..write('payload: $payload, ')
          ..write('status: $status, ')
          ..write('attemptCount: $attemptCount, ')
          ..write('createdAt: $createdAt, ')
          ..write('syncedAt: $syncedAt')
          ..write(')'))
        .toString();
  }
}

class $LocalSyncQueueTable extends LocalSyncQueue
    with TableInfo<$LocalSyncQueueTable, LocalSyncQueueItem> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;

  $LocalSyncQueueTable(this.attachedDatabase, [this._alias]);

  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    clientDefault: () => _uuid(),
  );

  @override
  late final GeneratedColumn<String> entityType = GeneratedColumn<String>(
    'entity_type',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<String> entityId = GeneratedColumn<String>(
    'entity_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<String> operation = GeneratedColumn<String>(
    'operation',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<String> payload = GeneratedColumn<String>(
    'payload',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('pending'),
  );

  @override
  late final GeneratedColumn<int> attemptCount = GeneratedColumn<int>(
    'attempt_count',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );

  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
    clientDefault: () => DateTime.now(),
  );

  @override
  late final GeneratedColumn<DateTime> syncedAt = GeneratedColumn<DateTime>(
    'synced_at',
    aliasedName,
    true,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
  );

  @override
  List<GeneratedColumn> get $columns => [
    id,
    entityType,
    entityId,
    operation,
    payload,
    status,
    attemptCount,
    createdAt,
    syncedAt,
  ];

  @override
  String get aliasedName => _alias ?? actualTableName;

  @override
  String get actualTableName => $name;

  static const String $name = 'local_sync_queue';

  @override
  VerificationContext validateIntegrity(
    Insertable<LocalSyncQueueItem> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(
        const VerificationMeta('id'),
        id.isAcceptableOrUnknown(data['id']!, const VerificationMeta('id')),
      );
    }
    if (data.containsKey('entity_type')) {
      context.handle(
        const VerificationMeta('entityType'),
        entityType.isAcceptableOrUnknown(
          data['entity_type']!,
          const VerificationMeta('entityType'),
        ),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('entityType'));
    }
    if (data.containsKey('entity_id')) {
      context.handle(
        const VerificationMeta('entityId'),
        entityId.isAcceptableOrUnknown(
          data['entity_id']!,
          const VerificationMeta('entityId'),
        ),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('entityId'));
    }
    if (data.containsKey('operation')) {
      context.handle(
        const VerificationMeta('operation'),
        operation.isAcceptableOrUnknown(
          data['operation']!,
          const VerificationMeta('operation'),
        ),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('operation'));
    }
    if (data.containsKey('payload')) {
      context.handle(
        const VerificationMeta('payload'),
        payload.isAcceptableOrUnknown(
          data['payload']!,
          const VerificationMeta('payload'),
        ),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('payload'));
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};

  @override
  LocalSyncQueueItem map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LocalSyncQueueItem(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}id'],
      )!,
      entityType: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}entity_type'],
      )!,
      entityId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}entity_id'],
      )!,
      operation: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}operation'],
      )!,
      payload: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}payload'],
      )!,
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}status'],
      )!,
      attemptCount: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}attempt_count'],
      )!,
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}created_at'],
      )!,
      syncedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}synced_at'],
      ),
    );
  }

  @override
  $LocalSyncQueueTable createAlias(String alias) {
    return $LocalSyncQueueTable(attachedDatabase, alias);
  }
}

// ── LocalDepartment ───────────────────────────────────────

class LocalDepartment extends DataClass implements Insertable<LocalDepartment> {
  final String id;
  final String companyId;
  final String name;
  final String? code;
  final DateTime updatedAt;

  const LocalDepartment({
    required this.id,
    required this.companyId,
    required this.name,
    this.code,
    required this.updatedAt,
  });

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['company_id'] = Variable<String>(companyId);
    map['name'] = Variable<String>(name);
    if (!nullToAbsent || code != null) map['code'] = Variable<String>(code);
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  LocalDepartmentsCompanion toCompanion(bool nullToAbsent) {
    return LocalDepartmentsCompanion(
      id: Value(id),
      companyId: Value(companyId),
      name: Value(name),
      code: code == null && nullToAbsent ? const Value.absent() : Value(code),
      updatedAt: Value(updatedAt),
    );
  }

  factory LocalDepartment.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LocalDepartment(
      id: serializer.fromJson<String>(json['id']),
      companyId: serializer.fromJson<String>(json['company_id']),
      name: serializer.fromJson<String>(json['name']),
      code: serializer.fromJson<String?>(json['code']),
      updatedAt: serializer.fromJson<DateTime>(json['updated_at']),
    );
  }

  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'company_id': serializer.toJson<String>(companyId),
      'name': serializer.toJson<String>(name),
      'code': serializer.toJson<String?>(code),
      'updated_at': serializer.toJson<DateTime>(updatedAt),
    };
  }

  LocalDepartment copyWith({
    String? id,
    String? companyId,
    String? name,
    Value<String?> code = const Value.absent(),
    DateTime? updatedAt,
  }) => LocalDepartment(
    id: id ?? this.id,
    companyId: companyId ?? this.companyId,
    name: name ?? this.name,
    code: code.present ? code.value : this.code,
    updatedAt: updatedAt ?? this.updatedAt,
  );

  @override
  String toString() {
    return (StringBuffer('LocalDepartment(')
          ..write('id: $id, ')
          ..write('companyId: $companyId, ')
          ..write('name: $name, ')
          ..write('code: $code, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(id, companyId, name, code, updatedAt);

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LocalDepartment &&
          other.id == this.id &&
          other.companyId == this.companyId &&
          other.name == this.name &&
          other.code == this.code &&
          other.updatedAt == this.updatedAt);
}

class LocalDepartmentsCompanion extends UpdateCompanion<LocalDepartment> {
  final Value<String> id;
  final Value<String> companyId;
  final Value<String> name;
  final Value<String?> code;
  final Value<DateTime> updatedAt;

  const LocalDepartmentsCompanion({
    this.id = const Value.absent(),
    this.companyId = const Value.absent(),
    this.name = const Value.absent(),
    this.code = const Value.absent(),
    this.updatedAt = const Value.absent(),
  });

  LocalDepartmentsCompanion.insert({
    required String id,
    required String companyId,
    required String name,
    Value<String?> code = const Value.absent(),
    Value<DateTime> updatedAt = const Value.absent(),
  }) : id = Value(id),
       companyId = Value(companyId),
       name = Value(name),
       code = code,
       updatedAt = updatedAt;

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) map['id'] = Variable<String>(id.value);
    if (companyId.present)
      map['company_id'] = Variable<String>(companyId.value);
    if (name.present) map['name'] = Variable<String>(name.value);
    if (code.present) map['code'] = Variable<String>(code.value);
    if (updatedAt.present)
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LocalDepartmentsCompanion(')
          ..write('id: $id, ')
          ..write('companyId: $companyId, ')
          ..write('name: $name, ')
          ..write('code: $code, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }
}

class $LocalDepartmentsTable extends LocalDepartments
    with TableInfo<$LocalDepartmentsTable, LocalDepartment> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;

  $LocalDepartmentsTable(this.attachedDatabase, [this._alias]);

  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<String> companyId = GeneratedColumn<String>(
    'company_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<String> name = GeneratedColumn<String>(
    'name',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );

  @override
  late final GeneratedColumn<String> code = GeneratedColumn<String>(
    'code',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );

  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
    clientDefault: () => DateTime.now(),
  );

  @override
  List<GeneratedColumn> get $columns => [id, companyId, name, code, updatedAt];

  @override
  String get aliasedName => _alias ?? actualTableName;

  @override
  String get actualTableName => $name;

  static const String $name = 'local_departments';

  @override
  VerificationContext validateIntegrity(
    Insertable<LocalDepartment> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(
        const VerificationMeta('id'),
        id.isAcceptableOrUnknown(data['id']!, const VerificationMeta('id')),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('id'));
    }
    if (data.containsKey('company_id')) {
      context.handle(
        const VerificationMeta('companyId'),
        companyId.isAcceptableOrUnknown(
          data['company_id']!,
          const VerificationMeta('companyId'),
        ),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('companyId'));
    }
    if (data.containsKey('name')) {
      context.handle(
        const VerificationMeta('name'),
        name.isAcceptableOrUnknown(
          data['name']!,
          const VerificationMeta('name'),
        ),
      );
    } else if (isInserting) {
      context.missing(const VerificationMeta('name'));
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};

  @override
  LocalDepartment map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LocalDepartment(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}id'],
      )!,
      companyId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}company_id'],
      )!,
      name: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}name'],
      )!,
      code: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}code'],
      ),
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $LocalDepartmentsTable createAlias(String alias) {
    return $LocalDepartmentsTable(attachedDatabase, alias);
  }
}

// ── Database abstract ─────────────────────────────────────

abstract class _$EdgeDatabase extends GeneratedDatabase {
  _$EdgeDatabase(QueryExecutor e) : super(e);

  late final $LocalAttendanceLogsTable localAttendanceLogs =
      $LocalAttendanceLogsTable(this);
  late final $LocalAbsencesTable localAbsences = $LocalAbsencesTable(this);
  late final $LocalEmployeesTable localEmployees = $LocalEmployeesTable(this);
  late final $LocalSyncQueueTable localSyncQueue = $LocalSyncQueueTable(this);
  late final $LocalDepartmentsTable localDepartments = $LocalDepartmentsTable(
    this,
  );

  @override
  Iterable<TableInfo<Table, Object?>> get allTables =>
      allSchemaEntities.whereType<TableInfo<Table, Object?>>();

  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [
    localAttendanceLogs,
    localAbsences,
    localEmployees,
    localSyncQueue,
    localDepartments,
  ];
}
