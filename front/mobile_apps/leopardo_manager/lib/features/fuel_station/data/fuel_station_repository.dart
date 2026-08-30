import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';

/// Affectation de shift vue par le pompiste (endpoint /fuel-station/me/shifts).
class FuelShiftAssignmentDto {
  final int id;
  final String? shiftName;
  final String? assignmentDate;
  final String? status;

  FuelShiftAssignmentDto({
    required this.id,
    this.shiftName,
    this.assignmentDate,
    this.status,
  });

  factory FuelShiftAssignmentDto.fromJson(Map<String, dynamic> json) {
    final shift = json['shift'] as Map<String, dynamic>?;
    return FuelShiftAssignmentDto(
      id: json['id'] as int? ?? 0,
      shiftName: shift?['name'] as String? ?? (json['name'] as String?),
      assignmentDate: json['assignment_date'] as String?,
      status: json['status'] as String?,
    );
  }
}

/// Résultat d'un relevé de compteur enregistré (FUEL-004).
class FuelReadingResultDto {
  final int readingId;
  final int? deltaMinor;
  final String? calculationStatus;
  final bool replayed;

  FuelReadingResultDto({
    required this.readingId,
    this.deltaMinor,
    this.calculationStatus,
    required this.replayed,
  });

  bool get isAnomaly => calculationStatus == 'anomaly';

  factory FuelReadingResultDto.fromJson(Map<String, dynamic> json) {
    final reading = json['reading'] as Map<String, dynamic>?;
    final interval = json['interval'] as Map<String, dynamic>?;
    return FuelReadingResultDto(
      readingId: reading?['id'] as int? ?? json['id'] as int? ?? 0,
      deltaMinor: interval?['delta_minor'] as int?,
      calculationStatus: interval?['calculation_status'] as String?,
      replayed: json['replayed'] as bool? ?? false,
    );
  }
}

/// Dépôt de données du parcours pompiste FuelStation (FUEL-013, #5807).
class FuelStationRepository {
  final ApiClient apiClient;

  FuelStationRepository(this.apiClient);

  static const _readTimeout = Duration(seconds: 10);

  Future<List<FuelShiftAssignmentDto>> myShifts() async {
    final response = await apiClient.requestWithRetry(
      '/fuel-station/me/shifts',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items
        .map((e) => FuelShiftAssignmentDto.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  /// Enregistre un relevé de compteur (idempotent par clé client).
  Future<FuelReadingResultDto> recordReading({
    required int stationId,
    required int pumpId,
    required int meterId,
    required int readingValueMinor,
    required String idempotencyKey,
    int? shiftId,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/fuel-station/stations/$stationId/pumps/$pumpId/meters/$meterId/readings',
      method: 'POST',
      data: {
        'reading_value_minor': readingValueMinor,
        'idempotency_key': idempotencyKey,
        if (shiftId != null) 'shift_id': shiftId,
      },
      timeoutOverride: _readTimeout,
    );
    return FuelReadingResultDto.fromJson(extractDataMap(response.data));
  }
}
