/// Alerte de seuil de stock (RESTO-803/#6224).
///
/// Shape : `{id, ingredient, quantity, alert_threshold, branch_id}` — cf.
/// `RestaurantMobileManagerService::stockAlerts()`.
class RestaurantStockAlert {
  const RestaurantStockAlert({
    required this.id,
    this.ingredient,
    this.quantity = 0,
    this.alertThreshold,
    this.branchId,
  });

  final int id;
  final String? ingredient;
  final double quantity;
  final double? alertThreshold;
  final int? branchId;

  factory RestaurantStockAlert.fromJson(Map<String, dynamic> json) {
    return RestaurantStockAlert(
      id: (json['id'] as num?)?.toInt() ?? 0,
      ingredient: json['ingredient'] as String?,
      quantity: (json['quantity'] as num?)?.toDouble() ?? 0,
      alertThreshold: (json['alert_threshold'] as num?)?.toDouble(),
      branchId: (json['branch_id'] as num?)?.toInt(),
    );
  }
}
