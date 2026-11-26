import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/providers/base_providers.dart';

import '../data/contact_request_gateway.dart';
import '../data/journey_http.dart';
import '../data/retail_order_gateway.dart';
import '../data/stay_reservation_gateway.dart';
import '../data/travel_gateway.dart';
import '../data/careers_gateway.dart';

/// Client HTTP des parcours — un seul par application, comme la découverte.
final journeyApiProvider = Provider<JourneyApi>(
  (ref) => JourneyApi(ref.watch(apiClientProvider)),
);

final retailOrderGatewayProvider = Provider<RetailOrderGateway>(
  (ref) => RetailOrderGateway(ref.watch(journeyApiProvider)),
);

final stayReservationGatewayProvider = Provider<StayReservationGateway>(
  (ref) => StayReservationGateway(ref.watch(journeyApiProvider)),
);

final contactRequestGatewayProvider = Provider<ContactRequestGateway>(
  (ref) => ContactRequestGateway(ref.watch(journeyApiProvider)),
);

/// Verticale Billets (travel) — marketplace inter-agences.
final travelGatewayProvider = Provider<TravelGateway>(
  (ref) => TravelGateway(ref.watch(journeyApiProvider)),
);

/// Verticale Emploi (careers) — portails carrière publics.
final careersGatewayProvider = Provider<CareersGateway>(
  (ref) => CareersGateway(ref.watch(journeyApiProvider)),
);
