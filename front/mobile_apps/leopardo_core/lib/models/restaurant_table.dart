/// Table de restaurant (plan de salle serveur, RESTO-801/#6222).
///
/// Shape serveur : `{id, name, zone, status}` — cf.
/// `RestaurantMobileServerService::openTables()`.
class RestaurantTable {
  const RestaurantTable({
    required this.id,
    required this.name,
    required this.status,
    this.zone,
  });

  final int id;
  final String name;
  final String status;
  final String? zone;

  bool get isOpen => status == 'open';

  factory RestaurantTable.fromJson(Map<String, dynamic> json) {
    return RestaurantTable(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: json['name'] as String? ?? '',
      status: json['status'] as String? ?? '',
      zone: json['zone'] as String?,
    );
  }
}
