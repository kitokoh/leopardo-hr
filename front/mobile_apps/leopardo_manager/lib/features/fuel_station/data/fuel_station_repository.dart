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

/// Station du parc (endpoint /fuel-station/stations).
///
/// FUEL-013 (#8585) — le pompiste choisit sa station, puis sa pompe, puis son
/// compteur : sans cette chaîne, le relevé était posté avec des identifiants
/// `0` et le backend le rejetait systématiquement.
class FuelStationDto {
  final int id;
  final String? name;
  final String? code;

  FuelStationDto({required this.id, this.name, this.code});

  factory FuelStationDto.fromJson(Map<String, dynamic> json) {
    return FuelStationDto(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String?,
      code: json['code'] as String?,
    );
  }
}

/// Pompe rattachee a une station. Liste : GET .../stations/`<stationId>`/pumps.
class FuelPumpDto {
  final int id;
  final int stationId;
  final String? code;
  final String? status;

  FuelPumpDto({
    required this.id,
    required this.stationId,
    this.code,
    this.status,
  });

  factory FuelPumpDto.fromJson(Map<String, dynamic> json) {
    return FuelPumpDto(
      id: json['id'] as int? ?? 0,
      stationId: json['station_id'] as int? ?? 0,
      code: json['code'] as String?,
      status: json['status'] as String?,
    );
  }
}

/// Compteur monte sur une pompe. Liste : GET .../stations/`<stationId>`/meters, filtre pump_id.
class FuelMeterDto {
  final int id;
  final int stationId;
  final int pumpId;
  final String? meterCode;
  final String? meterType;
  final String? productCode;
  final String? unitCode;

  FuelMeterDto({
    required this.id,
    required this.stationId,
    required this.pumpId,
    this.meterCode,
    this.meterType,
    this.productCode,
    this.unitCode,
  });

  factory FuelMeterDto.fromJson(Map<String, dynamic> json) {
    return FuelMeterDto(
      id: json['id'] as int? ?? 0,
      stationId: json['station_id'] as int? ?? 0,
      pumpId: json['pump_id'] as int? ?? 0,
      meterCode: json['meter_code'] as String?,
      meterType: json['meter_type'] as String?,
      productCode: json['product_code'] as String?,
      unitCode: json['unit_code'] as String?,
    );
  }
}

/// Résultat d'une vente enregistrée (FUEL-008).
class FuelSaleResultDto {
  final int saleId;
  final int? totalMinor;
  final bool replayed;

  FuelSaleResultDto({required this.saleId, this.totalMinor, required this.replayed});

  factory FuelSaleResultDto.fromJson(Map<String, dynamic> json) {
    final sale = json['sale'] as Map<String, dynamic>?;
    return FuelSaleResultDto(
      saleId: sale?['id'] as int? ?? json['id'] as int? ?? 0,
      totalMinor: sale?['total_minor'] as int? ?? json['total_minor'] as int?,
      replayed: json['replayed'] as bool? ?? false,
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

  /// Stations du parc accessibles au pompiste.
  Future<List<FuelStationDto>> stations() async {
    final response = await apiClient.requestWithRetry(
      '/fuel-station/stations',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items
        .map((e) => FuelStationDto.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  /// Pompes d'une station (lecture seule, policy `viewAny`).
  Future<List<FuelPumpDto>> pumps(int stationId) async {
    final response = await apiClient.requestWithRetry(
      '/fuel-station/stations/$stationId/pumps',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items
        .map((e) => FuelPumpDto.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  /// Compteurs d'une station, filtrés sur la pompe choisie.
  ///
  /// Le backend expose les compteurs au niveau station ; on filtre côté client
  /// sur `pump_id` pour ne proposer que les compteurs réellement montés sur la
  /// pompe, et on écarte les compteurs retirés (`retired`).
  Future<List<FuelMeterDto>> meters(int stationId, {required int pumpId}) async {
    final response = await apiClient.requestWithRetry(
      '/fuel-station/stations/$stationId/meters',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items
        .map((e) => FuelMeterDto.fromJson(e as Map<String, dynamic>))
        .where((m) => m.pumpId == pumpId)
        .toList();
  }

  /// Enregistre une vente au comptoir (FUEL-008), idempotente par clé client.
  ///
  /// Le contrat backend (`StoreFuelSaleRequest`) attend `product` (libellé),
  /// `quantity` et `unit_price` en unités décimales — pas des centimes. On
  /// convertit donc à la frontière (`/100`) plutôt que d'envoyer des centimes
  /// que le backend interpréterait comme des unités, ce qui multiplierait le
  /// montant par 100.
  Future<FuelSaleResultDto> recordSale({
    required int stationId,
    required int pumpId,
    required String product,
    required int quantityMilli,
    required int unitPriceCents,
    required String paymentMethod,
    String? externalId,
    int? shiftId,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/fuel-station/sales',
      method: 'POST',
      data: {
        'station_id': stationId,
        'pump_id': pumpId,
        'product': product,
        'quantity': quantityMilli / 1000,
        'unit_price': unitPriceCents / 100,
        'payment_method': paymentMethod,
        'source': 'mobile',
        // `external_id` est la clé d'idempotence acceptée par le contrat :
        // rejouer la même vente après une coupure réseau ne crée pas de doublon.
        if (externalId != null) 'external_id': externalId,
      },
      timeoutOverride: _readTimeout,
    );
    return FuelSaleResultDto.fromJson(extractDataMap(response.data));
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
