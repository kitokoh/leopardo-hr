/// Livraison assignée à un livreur (RESTO-802/#6223).
///
/// Shape : `{id, reference, status, customer_name, customer_phone, address,
/// fee_minor, order_total_minor}` — cf.
/// `RestaurantMobileRiderService::myDeliveries()`.
class RestaurantDelivery {
  const RestaurantDelivery({
    required this.id,
    required this.reference,
    required this.status,
    this.customerName,
    this.customerPhone,
    this.address,
    this.feeMinor,
    this.orderTotalMinor = 0,
    this.deliveredAt,
  });

  final int id;
  final String reference;
  final String status;
  final String? customerName;
  final String? customerPhone;
  final String? address;
  final int? feeMinor;
  final int orderTotalMinor;
  final DateTime? deliveredAt;

  bool get isAssigned => status == 'assigned';
  bool get isOutForDelivery => status == 'out_for_delivery';

  factory RestaurantDelivery.fromJson(Map<String, dynamic> json) {
    return RestaurantDelivery(
      id: (json['id'] as num?)?.toInt() ?? 0,
      reference: json['reference'] as String? ?? '',
      status: json['status'] as String? ?? '',
      customerName: json['customer_name'] as String?,
      customerPhone: json['customer_phone'] as String?,
      address: json['address'] as String?,
      feeMinor: (json['fee_minor'] as num?)?.toInt(),
      orderTotalMinor: (json['order_total_minor'] as num?)?.toInt() ?? 0,
      deliveredAt: json['delivered_at'] != null
          ? DateTime.tryParse(json['delivered_at'] as String)
          : null,
    );
  }
}
