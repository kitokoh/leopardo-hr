/// KPIs du jour calculés côté serveur (RESTO-803/#6224).
///
/// Shape : `{today_revenue_minor, orders_count, avg_basket_minor,
/// tables_opened_today, currency}` — cf.
/// `RestaurantMobileManagerService::kpis()`. Les agrégations ne sont jamais
/// calculées côté client.
class RestaurantKpis {
  const RestaurantKpis({
    this.todayRevenueMinor = 0,
    this.ordersCount = 0,
    this.avgBasketMinor = 0,
    this.tablesOpenedToday = 0,
    this.currency,
  });

  final int todayRevenueMinor;
  final int ordersCount;
  final int avgBasketMinor;
  final int tablesOpenedToday;
  final String? currency;

  factory RestaurantKpis.fromJson(Map<String, dynamic> json) {
    return RestaurantKpis(
      todayRevenueMinor: (json['today_revenue_minor'] as num?)?.toInt() ?? 0,
      ordersCount: (json['orders_count'] as num?)?.toInt() ?? 0,
      avgBasketMinor: (json['avg_basket_minor'] as num?)?.toInt() ?? 0,
      tablesOpenedToday: (json['tables_opened_today'] as num?)?.toInt() ?? 0,
      currency: json['currency'] as String?,
    );
  }
}
