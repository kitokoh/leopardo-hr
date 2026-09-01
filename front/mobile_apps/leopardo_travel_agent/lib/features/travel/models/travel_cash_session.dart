/// Session de caisse PDV (TRAVEL-810/#6100, /travel/pdv/session/*).
class TravelCashSession {
  const TravelCashSession({
    this.id,
    this.status,
    this.openedByUserId,
    this.openedAt,
    this.closedAt,
    this.openingBalanceMinor,
    this.expectedBalanceMinor,
    this.actualBalanceMinor,
    this.differenceMinor,
  });

  final int? id;
  final String? status;
  final int? openedByUserId;
  final String? openedAt;
  final String? closedAt;
  final int? openingBalanceMinor;
  final int? expectedBalanceMinor;
  final int? actualBalanceMinor;
  final int? differenceMinor;

  bool get isOpen => status == 'open';

  factory TravelCashSession.fromJson(Map<String, dynamic> json) {
    return TravelCashSession(
      id: json['id'] as int?,
      status: json['status'] as String?,
      openedByUserId: json['opened_by_user_id'] as int?,
      openedAt: json['opened_at'] as String?,
      closedAt: json['closed_at'] as String?,
      openingBalanceMinor: json['opening_balance_minor'] as int?,
      expectedBalanceMinor: json['expected_balance_minor'] as int?,
      actualBalanceMinor: json['actual_balance_minor'] as int?,
      differenceMinor: json['difference_minor'] as int?,
    );
  }
}

/// Reçu d'encaissement d'une réservation (GET /travel/pdv/receipts/{booking}).
class TravelReceipt {
  const TravelReceipt({
    this.reference,
    this.tripId,
    this.passengerCount,
    this.totalAmountMinor,
    this.currency,
    this.paymentStatus,
    this.passengers = const [],
  });

  final String? reference;
  final int? tripId;
  final int? passengerCount;
  final int? totalAmountMinor;
  final String? currency;
  final String? paymentStatus;
  final List<TravelReceiptPassenger> passengers;

  factory TravelReceipt.fromJson(Map<String, dynamic> json) {
    return TravelReceipt(
      reference: json['reference'] as String?,
      tripId: json['trip_id'] as int?,
      passengerCount: json['passenger_count'] as int?,
      totalAmountMinor: json['total_amount_minor'] as int?,
      currency: json['currency'] as String?,
      paymentStatus: json['payment_status'] as String?,
      passengers: (json['passengers'] as List?)
              ?.whereType<Map>()
              .map(
                (e) => TravelReceiptPassenger.fromJson(
                  e.cast<String, dynamic>(),
                ),
              )
              .toList() ??
          const [],
    );
  }
}

class TravelReceiptPassenger {
  const TravelReceiptPassenger({
    this.fullName,
    this.seatNumber,
    this.unitPriceMinor,
  });

  final String? fullName;
  final int? seatNumber;
  final int? unitPriceMinor;

  factory TravelReceiptPassenger.fromJson(Map<String, dynamic> json) {
    return TravelReceiptPassenger(
      fullName: json['full_name'] as String?,
      seatNumber: json['seat_number'] as int?,
      unitPriceMinor: json['unit_price_minor'] as int?,
    );
  }
}
