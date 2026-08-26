/// Modèle de configuration du pointage intelligent retourné par l'API.
/// Endpoint : GET /api/v1/attendance/config
class AttendanceGeoConfig {
  /// Mode forcé par l'entreprise : 'gps_auto' | 'qr_code' | 'manual' | null
  final String? forcedMode;

  /// GPS activé pour cette entreprise
  final bool gpsEnabled;

  /// Latitude du centre de la zone de l'entreprise
  final double? latitude;

  /// Longitude du centre de la zone de l'entreprise
  final double? longitude;

  /// Rayon de la zone géographique autorisée en mètres
  final double? radius;

  const AttendanceGeoConfig({
    this.forcedMode,
    required this.gpsEnabled,
    this.latitude,
    this.longitude,
    this.radius,
  });

  factory AttendanceGeoConfig.fromJson(Map<String, dynamic> json) {
    return AttendanceGeoConfig(
      forcedMode: json['forced_mode'] as String?,
      gpsEnabled: (json['gps_enabled'] as bool?) ?? false,
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
      radius: (json['radius'] as num?)?.toDouble(),
    );
  }

  Map<String, dynamic> toJson() => {
    'forced_mode': forcedMode,
    'gps_enabled': gpsEnabled,
    'latitude': latitude,
    'longitude': longitude,
    'radius': radius,
  };

  /// Indique si la zone géographique est complètement configurée
  bool get hasValidZone =>
      latitude != null && longitude != null && radius != null && radius! > 0;

  /// Mode effectif : forcedMode si défini, sinon null (l'employé choisit)
  bool get isForcedGps => forcedMode == 'gps_auto';
  bool get isForcedQr => forcedMode == 'qr_code';
  bool get isForcedManual => forcedMode == 'manual';
  bool get hasForced => forcedMode != null && forcedMode!.isNotEmpty;

  @override
  // dev-only: diagnostic representation, not user-visible (#5510)
  String toString() =>
      'AttendanceGeoConfig(forcedMode: $forcedMode, gpsEnabled: $gpsEnabled, '
      'lat: $latitude, lng: $longitude, radius: $radius)';
}
