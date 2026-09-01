/// Réservation (TRAVEL-312..316, GET/POST /travel/bookings).
class TravelBooking {
  const TravelBooking({
    required this.id,
    this.reference,
    this.tripId,
    this.status,
    this.passengerCount,
    this.totalAmountMinor,
    this.currency,
    this.bookingSource,
    this.paymentStatus,
    this.expiresAt,
    this.contactEmail,
    this.contactPhone,
    this.notifyConsent,
    this.passengers = const [],
    this.tickets = const [],
  });

  final int id;
  final String? reference;
  final int? tripId;
  final String? status;
  final int? passengerCount;
  final int? totalAmountMinor;
  final String? currency;
  final String? bookingSource;
  final String? paymentStatus;
  final String? expiresAt;
  final String? contactEmail;
  final String? contactPhone;
  final bool? notifyConsent;
  final List<TravelPassenger> passengers;
  final List<TravelTicket> tickets;

  factory TravelBooking.fromJson(Map<String, dynamic> json) {
    return TravelBooking(
      id: json['id'] as int,
      reference: json['reference'] as String?,
      tripId: json['trip_id'] as int?,
      status: json['status'] as String?,
      passengerCount: json['passenger_count'] as int?,
      totalAmountMinor: json['total_amount_minor'] as int?,
      currency: json['currency'] as String?,
      bookingSource: json['booking_source'] as String?,
      paymentStatus: json['payment_status'] as String?,
      expiresAt: json['expires_at'] as String?,
      contactEmail: json['contact_email'] as String?,
      contactPhone: json['contact_phone'] as String?,
      notifyConsent: json['notify_consent'] as bool?,
      passengers: (json['passengers'] as List?)
              ?.whereType<Map>()
              .map((e) => TravelPassenger.fromJson(e.cast<String, dynamic>()))
              .toList() ??
          const [],
      tickets: (json['tickets'] as List?)
              ?.whereType<Map>()
              .map((e) => TravelTicket.fromJson(e.cast<String, dynamic>()))
              .toList() ??
          const [],
    );
  }
}

/// Passager d'une réservation (RGPD : jamais le n° de pièce d'identité).
class TravelPassenger {
  const TravelPassenger({
    this.id,
    this.bookingId,
    this.fullName,
    this.birthDate,
    this.documentType,
    this.hasDocument = false,
    this.ageCategory,
    this.classId,
    this.seatNumber,
    this.unitPriceMinor,
  });

  final int? id;
  final int? bookingId;
  final String? fullName;
  final String? birthDate;
  final String? documentType;
  final bool hasDocument;
  final String? ageCategory;
  final int? classId;
  final int? seatNumber;
  final int? unitPriceMinor;

  factory TravelPassenger.fromJson(Map<String, dynamic> json) {
    return TravelPassenger(
      id: json['id'] as int?,
      bookingId: json['booking_id'] as int?,
      fullName: json['full_name'] as String?,
      birthDate: json['birth_date'] as String?,
      documentType: json['document_type'] as String?,
      hasDocument: json['has_document'] as bool? ?? false,
      ageCategory: json['age_category'] as String?,
      classId: json['class_id'] as int?,
      seatNumber: json['seat_number'] as int?,
      unitPriceMinor: json['unit_price_minor'] as int?,
    );
  }
}

/// Billet émis (TRAVEL-316..317, POST /travel/bookings/{id}/issue-ticket).
class TravelTicket {
  const TravelTicket({
    this.id,
    this.ticketNumber,
    this.bookingId,
    this.passengerId,
    this.status,
    this.issuedAt,
    this.validFrom,
    this.validUntil,
    this.checkedInAt,
  });

  final int? id;
  final String? ticketNumber;
  final int? bookingId;
  final int? passengerId;
  final String? status;
  final String? issuedAt;
  final String? validFrom;
  final String? validUntil;
  final String? checkedInAt;

  factory TravelTicket.fromJson(Map<String, dynamic> json) {
    return TravelTicket(
      id: json['id'] as int?,
      ticketNumber: json['ticket_number'] as String?,
      bookingId: json['booking_id'] as int?,
      passengerId: json['passenger_id'] as int?,
      status: json['status'] as String?,
      issuedAt: json['issued_at'] as String?,
      validFrom: json['valid_from'] as String?,
      validUntil: json['valid_until'] as String?,
      checkedInAt: json['checked_in_at'] as String?,
    );
  }
}
