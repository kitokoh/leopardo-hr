/// Commande restaurant vue par les surfaces mobiles (RESTO-801/#6222).
///
/// Shape serveur : `{id, reference, status, order_type, table_name,
/// total_minor, currency, items_count}` — cf.
/// `RestaurantMobileServerService::activeOrders()`.
class RestaurantOrder {
  const RestaurantOrder({
    required this.id,
    required this.reference,
    required this.status,
    required this.orderType,
    required this.totalMinor,
    this.tableName,
    this.currency = '',
    this.itemsCount = 0,
  });

  final int id;
  final String reference;
  final String status;
  final String orderType;
  final String? tableName;
  final int totalMinor;
  final String currency;
  final int itemsCount;

  bool get isReady => status == 'ready';
  bool get isServed => status == 'served';
  bool get isOpen => status == 'open' || status == 'in_preparation';

  factory RestaurantOrder.fromJson(Map<String, dynamic> json) {
    return RestaurantOrder(
      id: (json['id'] as num?)?.toInt() ?? 0,
      reference: json['reference'] as String? ?? '',
      status: json['status'] as String? ?? '',
      orderType: json['order_type'] as String? ?? '',
      tableName: json['table_name'] as String?,
      totalMinor: (json['total_minor'] as num?)?.toInt() ?? 0,
      currency: json['currency'] as String? ?? '',
      itemsCount: (json['items_count'] as num?)?.toInt() ?? 0,
    );
  }
}
