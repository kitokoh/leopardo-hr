/// Représentation d'un trajet daté (TRAVEL-308, GET /travel/trips).
class TravelTrip {
  const TravelTrip({
    required this.id,
    required this.code,
    this.routeId,
    this.carrierId,
    this.vehicleId,
    required this.departureDate,
    this.departureTime,
    this.arrivalDate,
    this.arrivalTime,
    this.meansOfTransport,
    this.totalSeats,
    this.status,
    this.prices = const [],
    this.route,
  });

  final int id;
  final String code;
  final int? routeId;
  final int? carrierId;
  final int? vehicleId;
  final String departureDate;
  final String? departureTime;
  final String? arrivalDate;
  final String? arrivalTime;
  final String? meansOfTransport;
  final int? totalSeats;
  final String? status;
  final List<TravelTripPrice> prices;
  final TravelRoute? route;

  factory TravelTrip.fromJson(Map<String, dynamic> json) {
    return TravelTrip(
      id: json['id'] as int,
      code: (json['code'] as String?) ?? '',
      routeId: json['route_id'] as int?,
      carrierId: json['carrier_id'] as int?,
      vehicleId: json['vehicle_id'] as int?,
      departureDate: (json['departure_date'] as String?) ?? '',
      departureTime: json['departure_time'] as String?,
      arrivalDate: json['arrival_date'] as String?,
      arrivalTime: json['arrival_time'] as String?,
      meansOfTransport: json['means_of_transport'] as String?,
      totalSeats: json['total_seats'] as int?,
      status: json['status'] as String?,
      prices: (json['prices'] as List?)
              ?.whereType<Map>()
              .map((e) => TravelTripPrice.fromJson(e.cast<String, dynamic>()))
              .toList() ??
          const [],
      route: json['route'] is Map
          ? TravelRoute.fromJson((json['route'] as Map).cast<String, dynamic>())
          : null,
    );
  }

  /// Libellé d'origine/destination depuis la route embarquée (si présente).
  String? get originLabel => route?.originCityName ?? route?.originCityId?.toString();
  String? get destinationLabel => route?.destinationCityName ?? route?.destinationCityId?.toString();

  /// Tarif adulte du premier tarif disponible (affichage rapide).
  int? get firstAdultPriceMinor {
    for (final price in prices) {
      if (price.adultPriceMinor != null) {
        return price.adultPriceMinor;
      }
    }
    return null;
  }

  String? get currency => prices.isNotEmpty ? prices.first.currency : null;
}

/// Tarif d'un trajet par classe (TRAVEL-309) — montants en unités mineures.
class TravelTripPrice {
  const TravelTripPrice({
    this.id,
    this.tripId,
    this.classId,
    this.adultPriceMinor,
    this.childPriceMinor,
    this.currency,
  });

  final int? id;
  final int? tripId;
  final int? classId;
  final int? adultPriceMinor;
  final int? childPriceMinor;
  final String? currency;

  factory TravelTripPrice.fromJson(Map<String, dynamic> json) {
    return TravelTripPrice(
      id: json['id'] as int?,
      tripId: json['trip_id'] as int?,
      classId: json['class_id'] as int?,
      adultPriceMinor: json['adult_price_minor'] as int?,
      childPriceMinor: json['child_price_minor'] as int?,
      currency: json['currency'] as String?,
    );
  }
}

/// Route d'un trajet (référentiel) — villes d'origine/destination.
class TravelRoute {
  const TravelRoute({
    this.id,
    this.code,
    this.originCityId,
    this.destinationCityId,
    this.originCityName,
    this.destinationCityName,
  });

  final int? id;
  final String? code;
  final int? originCityId;
  final int? destinationCityId;
  final String? originCityName;
  final String? destinationCityName;

  factory TravelRoute.fromJson(Map<String, dynamic> json) {
    return TravelRoute(
      id: json['id'] as int?,
      code: json['code'] as String?,
      originCityId: json['origin_city_id'] as int?,
      destinationCityId: json['destination_city_id'] as int?,
      originCityName: json['origin_city_name'] as String?,
      destinationCityName: json['destination_city_name'] as String?,
    );
  }
}
