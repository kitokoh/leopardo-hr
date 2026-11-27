/// Modèles de la verticale **Billets** (travel) — marketplace inter-agences.
///
/// ## Le contrat, tel qu'il existe réellement
///
/// `GET /public/travel/marketplace/trips` rend un trajet agrégé cross-tenant :
/// villes d'origine/destination, horaires, prix par classe, places libres,
/// disposition des sièges et **nom public** de l'agence. Aucun identifiant
/// tenant, aucune donnée interne.
///
/// ## Ce que ces modèles refusent d'inventer
///
/// - Un trajet sans `id` numérique n'est pas réservable : `POST
///   /public/travel/marketplace/bookings` ne désigne le trajet que par
///   `trip_id`. Il est donc écarté au parsing, pas affiché puis refusé.
/// - Un prix absent reste `null` : afficher « 0 » ferait croire à la gratuité.
/// - Un nourrisson ne paie **pas** de siège (règle tarifaire de l'agence) :
///   c'est un `0` explicite, pas une donnée manquante.
library;

/// Catégorie d'âge d'un passager — valeurs acceptées par le serveur
/// (`AgeCategory`).
enum TravelAgeCategory {
  adult('adult'),
  child('child'),
  infant('infant');

  const TravelAgeCategory(this.wire);

  /// Valeur envoyée à l'API.
  final String wire;

  static TravelAgeCategory? tryParse(Object? raw) {
    if (raw is! String) return null;
    for (final value in TravelAgeCategory.values) {
      if (value.wire == raw) return value;
    }
    return null;
  }
}

/// Ville d'un trajet (identifiant + nom + pays ISO-2).
///
/// L'`id` est indispensable : `GET /public/travel/marketplace/trips` ne filtre
/// que par `origin_city_id` / `destination_city_id` (entiers). Une ville sans
/// identifiant ne peut donc pas servir de filtre — elle reste affichable, mais
/// l'écran ne l'utilisera pas pour la recherche.
class TravelCity {
  const TravelCity({required this.id, required this.name, this.countryIso2});

  final int id;
  final String name;
  final String? countryIso2;

  static TravelCity? tryParse(Object? raw) {
    if (raw is! Map) return null;
    final name = _str(raw['name']);
    if (name == null) return null;
    return TravelCity(
      id: _int(raw['id']) ?? 0,
      name: name,
      countryIso2: _str(raw['country_iso2']),
    );
  }
}

/// Tarif d'une classe de voyage.
class TravelTripPrice {
  const TravelTripPrice({
    required this.classId,
    required this.adultPriceMinor,
    required this.childPriceMinor,
    required this.currency,
  });

  final int classId;
  final int adultPriceMinor;
  final int childPriceMinor;
  final String currency;

  /// Prix d'un passager pour cette classe.
  ///
  /// Un nourrisson vaut **0** : il n'occupe pas de siège facturé. Ce n'est pas
  /// une absence de donnée mais une règle tarifaire — d'où le `0` explicite.
  int priceFor(TravelAgeCategory age) => switch (age) {
        TravelAgeCategory.adult => adultPriceMinor,
        TravelAgeCategory.child => childPriceMinor,
        TravelAgeCategory.infant => 0,
      };

  static TravelTripPrice? tryParse(Object? raw) {
    if (raw is! Map) return null;
    final classId = _int(raw['class_id']);
    if (classId == null) return null;
    return TravelTripPrice(
      classId: classId,
      adultPriceMinor: _int(raw['adult_price_minor']) ?? 0,
      childPriceMinor: _int(raw['child_price_minor']) ?? 0,
      currency: _str(raw['currency']) ?? 'MAD',
    );
  }
}

/// Siège du plan de bus (`GET …/trips/{id}`).
///
/// Seuls les états **persistés** existent ici (`free`, `reserved`, `sold`,
/// `blocked`, `unavailable`) : les états de présentation (`selected`, `held`)
/// ne sont jamais écrits en base et n'ont donc rien à faire dans le modèle.
class TravelSeat {
  const TravelSeat({required this.number, required this.status});

  final int number;
  final String status;

  bool get isFree => status == 'free';

  static TravelSeat? tryParse(Object? raw) {
    if (raw is! Map) return null;
    final number = _int(raw['seat_number']);
    if (number == null) return null;
    return TravelSeat(
      number: number,
      status: _str(raw['status']) ?? 'unavailable',
    );
  }
}

/// Un trajet vendu au grand public.
class TravelTrip {
  const TravelTrip({
    required this.id,
    required this.code,
    this.departureDate,
    this.departureTime,
    this.arrivalDate,
    this.arrivalTime,
    this.meansOfTransport,
    this.totalSeats,
    this.availableSeats,
    this.origin,
    this.destination,
    this.prices = const [],
    this.priceFromMinor,
    this.currency,
    this.agencyName,
    this.seats = const [],
  });

  final int id;
  final String code;
  final String? departureDate;
  final String? departureTime;
  final String? arrivalDate;
  final String? arrivalTime;
  final String? meansOfTransport;
  final int? totalSeats;

  /// Places libres. `null` = non communiqué par le serveur.
  final int? availableSeats;

  final TravelCity? origin;
  final TravelCity? destination;
  final List<TravelTripPrice> prices;

  /// Prix d'appel (le moins cher des tarifs adultes), ou `null`.
  final int? priceFromMinor;
  final String? currency;
  final String? agencyName;

  /// Plan de sièges — renseigné par le détail seulement.
  final List<TravelSeat> seats;

  /// Règle du domaine : un trajet sans places connues n'est pas déclaré
  /// complet — on ne cache pas une offre par prudence excessive, mais on
  /// n'invente pas non plus de disponibilité.
  bool get isBookable => availableSeats == null || availableSeats! > 0;

  /// Peut-on réellement envoyer une réservation ?
  ///
  /// Il faut des places ET un tarif : `POST …/bookings` désigne la classe par
  /// `class_id`, et un trajet sans tarif publié n'en a aucune. Proposer
  /// « Réserver » dans ce cas enverrait `class_id: 0` et le serveur
  /// refuserait — un bouton qui échoue systématiquement vaut moins qu'un
  /// message qui explique pourquoi.
  bool get canBook => isBookable && defaultPrice != null;

  /// Itinéraire lisible, ou `null` si l'un des deux bouts manque.
  String? get route {
    final from = origin?.name;
    final to = destination?.name;
    if (from == null || to == null) return null;
    return '$from → $to';
  }

  /// Tarif de la première classe proposée, ou `null`.
  TravelTripPrice? get defaultPrice => prices.isEmpty ? null : prices.first;

  TravelTrip withSeats(List<TravelSeat> value) => TravelTrip(
        id: id,
        code: code,
        departureDate: departureDate,
        departureTime: departureTime,
        arrivalDate: arrivalDate,
        arrivalTime: arrivalTime,
        meansOfTransport: meansOfTransport,
        totalSeats: totalSeats,
        availableSeats: availableSeats,
        origin: origin,
        destination: destination,
        prices: prices,
        priceFromMinor: priceFromMinor,
        currency: currency,
        agencyName: agencyName,
        seats: value,
      );

  /// Parse un trajet ; `null` si l'item n'est pas réservable (pas d'`id`).
  static TravelTrip? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final id = _int(raw['id']);
    if (id == null) return null;

    final prices = <TravelTripPrice>[];
    final rawPrices = raw['prices'];
    if (rawPrices is List) {
      for (final entry in rawPrices) {
        final price = TravelTripPrice.tryParse(entry);
        if (price != null) prices.add(price);
      }
    }

    final seats = <TravelSeat>[];
    final rawSeats = raw['seats'];
    if (rawSeats is List) {
      for (final entry in rawSeats) {
        final seat = TravelSeat.tryParse(entry);
        if (seat != null) seats.add(seat);
      }
    }

    final agency = raw['agency'];

    return TravelTrip(
      id: id,
      code: _str(raw['code']) ?? '$id',
      departureDate: _str(raw['departure_date']),
      departureTime: _str(raw['departure_time']),
      arrivalDate: _str(raw['arrival_date']),
      arrivalTime: _str(raw['arrival_time']),
      meansOfTransport: _str(raw['means_of_transport']),
      totalSeats: _int(raw['total_seats']),
      availableSeats: _int(raw['available_seats']),
      origin: TravelCity.tryParse(raw['origin_city']),
      destination: TravelCity.tryParse(raw['destination_city']),
      prices: prices,
      priceFromMinor: _int(raw['price_from_minor']),
      currency: _str(raw['currency']),
      agencyName: agency is Map ? _str(agency['name']) : null,
      seats: seats,
    );
  }
}

/// Un passager en cours de saisie.
class TravelPassengerDraft {
  const TravelPassengerDraft({
    required this.fullName,
    required this.ageCategory,
    required this.classId,
    this.seatNumber,
  });

  final String fullName;
  final TravelAgeCategory ageCategory;
  final int classId;
  final int? seatNumber;

  bool get isComplete => fullName.trim().isNotEmpty;

  Map<String, dynamic> toPayload() => {
        'full_name': fullName.trim(),
        'age_category': ageCategory.wire,
        'class_id': classId,
        if (seatNumber != null) 'seat_number': seatNumber,
      };

  TravelPassengerDraft copyWith({
    String? fullName,
    TravelAgeCategory? ageCategory,
    int? classId,
    int? seatNumber,
  }) =>
      TravelPassengerDraft(
        fullName: fullName ?? this.fullName,
        ageCategory: ageCategory ?? this.ageCategory,
        classId: classId ?? this.classId,
        seatNumber: seatNumber ?? this.seatNumber,
      );
}

/// Nombre maximal de passagers par réservation (règle serveur `max:20`).
const int travelMaxPassengers = 20;

/// Moyens de paiement proposés au voyageur en ligne — #9947.
///
/// ## Pourquoi `cash` n'est pas ici
///
/// Le serveur accepte `cash` (`in:cash,pvit,momo,card`), mais il n'a de sens
/// qu'au guichet, où un agent encaisse physiquement. L'exposer dans l'app
/// laisserait croire à un paiement réglé alors qu'aucune transaction n'aurait
/// eu lieu. La vitrine web fait le même choix
/// (`ONLINE_PAYMENT_PROVIDERS` dans `booking-gateway.ts`).
enum TravelPaymentProvider {
  momo('momo'),
  card('card'),
  pvit('pvit');

  const TravelPaymentProvider(this.wire);

  /// Valeur envoyée à l'API (`provider_code`).
  final String wire;

  static TravelPaymentProvider? tryParse(Object? raw) {
    if (raw is! String) return null;
    for (final value in TravelPaymentProvider.values) {
      if (value.wire == raw) return value;
    }
    return null;
  }
}

/// Paiement initié côté serveur (`POST …/payments/initiate`).
///
/// ## Ce que ce modèle refuse d'inventer
///
/// - Une réponse sans `reference` n'est pas un paiement : elle est rejetée au
///   parsing, pas affichée avec une référence vide qui ferait croire à une
///   transaction engagée.
/// - `provider_reference` reste `null` quand le fournisseur ne l'a pas encore
///   rendue (paiement mobile en attente de confirmation) : ce n'est pas une
///   erreur, c'est un état.
class TravelPaymentInitiation {
  const TravelPaymentInitiation({
    required this.reference,
    required this.status,
    this.providerReference,
  });

  /// Référence interne du paiement (celle à conserver).
  final String reference;

  /// Statut renvoyé par le serveur (`pending`, `paid`, …).
  final String status;

  /// Référence chez le fournisseur (opérateur mobile money, PSP carte).
  final String? providerReference;

  static TravelPaymentInitiation? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final reference = _str(raw['reference']);
    if (reference == null) return null;

    return TravelPaymentInitiation(
      reference: reference,
      status: _str(raw['status']) ?? 'pending',
      providerReference: _str(raw['provider_reference']),
    );
  }
}

/// Réservation créée (`TravelBookingResource` + `agency`).
class TravelBookingConfirmation {
  const TravelBookingConfirmation({
    required this.reference,
    required this.status,
    required this.paymentStatus,
    required this.passengerCount,
    required this.totalMinor,
    required this.currency,
    this.expiresAt,
    this.agencyName,
  });

  final String reference;
  final String status;
  final String paymentStatus;
  final int passengerCount;
  final int totalMinor;
  final String currency;
  final String? expiresAt;
  final String? agencyName;

  static TravelBookingConfirmation? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final reference = _str(raw['reference']);
    if (reference == null) return null;

    final agency = raw['agency'];

    return TravelBookingConfirmation(
      reference: reference,
      status: _str(raw['status']) ?? 'pending',
      paymentStatus: _str(raw['payment_status']) ?? 'pending',
      passengerCount: _int(raw['passenger_count']) ?? 0,
      totalMinor: _int(raw['total_amount_minor']) ?? 0,
      currency: _str(raw['currency']) ?? 'MAD',
      expiresAt: _str(raw['expires_at']),
      agencyName: agency is Map ? _str(agency['name']) : null,
    );
  }
}

/// Réservation suivie (`GET …/bookings/{reference}?code=`).
class TravelBookingStatus {
  const TravelBookingStatus({
    required this.reference,
    required this.status,
    required this.paymentStatus,
    required this.passengerCount,
    required this.totalMinor,
    required this.currency,
    this.tripCode,
    this.departureDate,
    this.departureTime,
    this.tickets = const [],
  });

  final String reference;
  final String status;
  final String paymentStatus;
  final int passengerCount;
  final int totalMinor;
  final String currency;
  final String? tripCode;
  final String? departureDate;
  final String? departureTime;
  final List<TravelTicket> tickets;

  static TravelBookingStatus? tryParse(
    Object? raw, {
    required String fallbackReference,
  }) {
    if (raw is! Map) return null;

    final trip = raw['trip'];
    final tickets = <TravelTicket>[];
    final rawTickets = raw['tickets'];
    if (rawTickets is List) {
      for (final entry in rawTickets) {
        final ticket = TravelTicket.tryParse(entry);
        if (ticket != null) tickets.add(ticket);
      }
    }

    return TravelBookingStatus(
      reference: _str(raw['reference']) ?? fallbackReference,
      status: _str(raw['status']) ?? 'pending',
      paymentStatus: _str(raw['payment_status']) ?? 'pending',
      passengerCount: _int(raw['passenger_count']) ?? 0,
      totalMinor: _int(raw['total_amount_minor']) ?? 0,
      currency: _str(raw['currency']) ?? 'MAD',
      tripCode: trip is Map ? _str(trip['code']) : null,
      departureDate: trip is Map ? _str(trip['departure_date']) : null,
      departureTime: trip is Map ? _str(trip['departure_time']) : null,
      tickets: tickets,
    );
  }
}

/// E-billet émis.
class TravelTicket {
  const TravelTicket({
    required this.id,
    required this.number,
    required this.status,
  });

  final int id;
  final String number;
  final String status;

  static TravelTicket? tryParse(Object? raw) {
    if (raw is! Map) return null;
    final id = _int(raw['id']);
    if (id == null) return null;
    return TravelTicket(
      id: id,
      number: _str(raw['ticket_number']) ?? '',
      status: _str(raw['status']) ?? '',
    );
  }
}

String? _str(Object? value) {
  if (value is String && value.trim().isNotEmpty) return value.trim();
  if (value is num) return value.toString();
  return null;
}

int? _int(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value);
  return null;
}
