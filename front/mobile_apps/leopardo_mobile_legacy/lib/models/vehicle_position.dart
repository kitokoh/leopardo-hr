class VehiclePosition {
  final int vehicleId;
  final String plateNumber;
  final String? brand;
  final String? model;
  final double latitude;
  final double longitude;
  final double? speed;
  final String? updatedAt;

  VehiclePosition({
    required this.vehicleId,
    required this.plateNumber,
    this.brand,
    this.model,
    required this.latitude,
    required this.longitude,
    this.speed,
    this.updatedAt,
  });

  factory VehiclePosition.fromJson(Map<String, dynamic> json) {
    return VehiclePosition(
      vehicleId: json['vehicle_id'] as int? ?? json['id'] as int,
      plateNumber: json['plate_number'] as String? ?? '',
      brand: json['brand'] as String?,
      model: json['model'] as String?,
      latitude: (json['latitude'] as num?)?.toDouble() ?? 0,
      longitude: (json['longitude'] as num?)?.toDouble() ?? 0,
      speed: (json['speed'] as num?)?.toDouble(),
      updatedAt: json['updated_at'] as String?,
    );
  }
}
