import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';
import 'package:leopardo_travel_agent/core/providers/core_providers.dart';
import 'package:leopardo_travel_agent/features/travel/data/travel_repository.dart';
import 'package:leopardo_travel_agent/features/travel/models/travel_booking.dart';
import 'package:leopardo_travel_agent/features/travel/models/travel_cash_session.dart';
import 'package:leopardo_travel_agent/features/travel/models/travel_trip.dart';

/// Providers du module TravelAgency pour l'app agent (TRAVEL-701/#6088).

/// Villes du référentiel (filtres de recherche).
final citiesProvider = FutureProvider<List<TravelCity>>((ref) {
  return ref.watch(travelRepositoryProvider).listCities();
});

/// Résultats de recherche de trajets — état mutable piloté par l'écran.
class TripSearchResults {
  const TripSearchResults({
    this.loading = false,
    this.error = false,
    this.trips = const [],
  });

  final bool loading;
  final bool error;
  final List<TravelTrip> trips;
}

class TripSearchResultsNotifier extends StateNotifier<TripSearchResults> {
  TripSearchResultsNotifier() : super(const TripSearchResults());

  Future<void> search({
    required TravelRepository repository,
    int? originCityId,
    int? destinationCityId,
    String? departureDate,
  }) async {
    state = const TripSearchResults(loading: true);
    try {
      final trips = await repository.searchTrips(
        originCityId: originCityId,
        destinationCityId: destinationCityId,
        departureDate: departureDate,
      );
      state = TripSearchResults(trips: trips);
    } catch (_) {
      state = const TripSearchResults(error: true);
    }
  }
}

final tripSearchResultsProvider =
    StateNotifierProvider<TripSearchResultsNotifier, TripSearchResults>(
  (ref) => TripSearchResultsNotifier(),
);

/// Détail d'un trajet.
final tripProvider =
    FutureProvider.autoDispose.family<TravelTrip, int>((ref, tripId) {
  return ref.watch(travelRepositoryProvider).getTrip(tripId);
});

/// Ventes du guichet.
final bookingsProvider = FutureProvider<List<TravelBooking>>((ref) {
  return ref.watch(travelRepositoryProvider).listBookings();
});

/// Manifeste d'un trajet.
final manifestProvider = FutureProvider.autoDispose
    .family<List<TravelPassenger>, int>((ref, tripId) {
  return ref.watch(travelRepositoryProvider).manifest(tripId);
});

/// Session de caisse PDV en cours (null si aucune).
final pdvSessionProvider = FutureProvider<TravelCashSession?>((ref) async {
  return ref.watch(travelRepositoryProvider).currentCashSession();
});
