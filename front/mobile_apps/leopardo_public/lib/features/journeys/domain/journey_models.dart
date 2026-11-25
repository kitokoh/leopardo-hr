/// Modèles des parcours transactionnels de la surface publique.
///
/// ## Aucun modèle « générique » qui devine
///
/// La découverte publique est tolérante (un champ accessoire manquant ne doit
/// pas casser la liste des commerces). Un parcours transactionnel ne l'est PAS :
/// une commande sans référence n'est pas une commande, et un total absent ne
/// vaut pas zéro. Ces modèles exigent donc les champs qui décident du succès,
/// et rendent `null` (ou lèvent) plutôt que d'inventer une valeur.
library;

/// Coordonnées d'une personne qui commande, réserve ou demande un devis.
class JourneyCustomer {
  const JourneyCustomer({required this.name, required this.phone, this.email});

  final String name;
  final String phone;
  final String? email;

  bool get isComplete => name.trim().isNotEmpty && phone.trim().isNotEmpty;

  Map<String, dynamic> toPayload() => {
        'name': name.trim(),
        'phone': phone.trim(),
        if (email != null && email!.trim().isNotEmpty) 'email': email!.trim(),
      };
}

/// Adresse de livraison (boutiques).
class JourneyAddress {
  const JourneyAddress({required this.address, required this.city, this.notes});

  final String address;
  final String city;
  final String? notes;

  bool get isComplete => address.trim().isNotEmpty && city.trim().isNotEmpty;

  Map<String, dynamic> toPayload() => {
        'address': address.trim(),
        'city': city.trim(),
        if (notes != null && notes!.trim().isNotEmpty) 'notes': notes!.trim(),
      };
}

/// Produit commandable d'une boutique (payload `GET /public/market/products`).
class JourneyOffer {
  const JourneyOffer({
    required this.id,
    required this.title,
    this.subtitle,
    this.priceMinor,
    this.currency = 'MAD',
    this.imageUrl,
    this.available = true,
  });

  final String id;
  final String title;
  final String? subtitle;
  final int? priceMinor;
  final String currency;
  final String? imageUrl;

  /// Faux si le serveur déclare le produit indisponible : un produit
  /// indisponible n'entre pas dans un panier, même si son prix existe.
  final bool available;

  bool get isPurchasable => available && priceMinor != null;

  /// Parse un produit ; `null` si l'item ne peut pas être commandé.
  ///
  /// Un `id` absent ou non numérique est éliminatoire : c'est la seule clé que
  /// `POST /public/market/orders` accepte pour désigner la ligne.
  static JourneyOffer? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final id = _str(raw['id']);
    final title = _str(raw['name']);
    if (id == null || title == null) return null;

    return JourneyOffer(
      id: id,
      title: title,
      subtitle: _str(raw['description']),
      priceMinor: _int(raw['price_minor']),
      currency: _str(raw['currency']) ?? 'MAD',
      imageUrl: _str(raw['image_url']),
      available: raw['available'] != false,
    );
  }

  /// Ligne de panier à quantité 1 (le geste « ajouter »).
  JourneyCartLine get line => JourneyCartLine(offer: this, quantity: 1);
}

/// Ligne de panier.
class JourneyCartLine {
  const JourneyCartLine({required this.offer, required this.quantity});

  final JourneyOffer offer;
  final int quantity;

  /// Quantité bornée à la règle serveur (`items.*.quantity` : 1..999).
  int get boundedQuantity =>
      quantity < 1 ? 1 : (quantity > 999 ? 999 : quantity);

  int get lineTotalMinor => (offer.priceMinor ?? 0) * boundedQuantity;

  JourneyCartLine withQuantity(int value) =>
      JourneyCartLine(offer: offer, quantity: value);
}

/// Type de chambre proposé par un établissement (`…/availability`).
class StayRoomOffer {
  const StayRoomOffer({
    required this.roomTypeId,
    required this.name,
    this.code,
    required this.available,
    required this.priceMinor,
    required this.currency,
  });

  final int roomTypeId;
  final String name;
  final String? code;

  /// Chambres libres sur la période demandée (0 = complet).
  final int available;
  final int priceMinor;
  final String currency;

  bool get isBookable => available > 0;

  static StayRoomOffer? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final id = _int(raw['room_type_id']);
    final name = _str(raw['name']);
    if (id == null || name == null) return null;

    return StayRoomOffer(
      roomTypeId: id,
      name: name,
      code: _str(raw['code']),
      available: _int(raw['available']) ?? 0,
      priceMinor: _int(raw['base_price_minor']) ?? 0,
      currency: _str(raw['currency']) ?? 'MAD',
    );
  }
}

/// Résultat d'une création (commande, réservation, demande).
class JourneyConfirmation {
  const JourneyConfirmation({
    required this.reference,
    this.trackingCode,
    this.status,
    this.totalMinor,
    this.currency,
    this.checkoutUrl,
  });

  final String reference;

  /// Code présenté UNE SEULE FOIS par le serveur (empreinte seule en base) :
  /// l'écran doit donc demander de le conserver.
  final String? trackingCode;
  final String? status;
  final int? totalMinor;
  final String? currency;

  /// URL de paiement en ligne renvoyée par le serveur (boutiques, méthode
  /// `online`). Ouverte dans le navigateur du téléphone : l'app ne saisit
  /// jamais de données de carte.
  final String? checkoutUrl;

  static JourneyConfirmation? tryParse(Object? raw, {int? totalMinor}) {
    if (raw is! Map) return null;

    final reference = _str(raw['reference']);
    if (reference == null) return null;

    return JourneyConfirmation(
      reference: reference,
      trackingCode: _str(raw['tracking_code']) ?? _str(raw['tracking_token']),
      status: _str(raw['status']),
      totalMinor: totalMinor ?? _int(raw['total_minor']),
      currency: _str(raw['currency']),
    );
  }
}

/// Suivi public d'une commande ou d'une réservation.
class JourneyStatusReport {
  const JourneyStatusReport({
    required this.reference,
    required this.status,
    this.fields = const {},
    this.events = const [],
  });

  final String reference;
  final String status;

  /// Champs déjà formatés pour l'affichage (ordre d'insertion conservé).
  final Map<String, String> fields;

  /// Étapes horodatées (« Confirmée : 2026-11-24T18:00:00Z »).
  final List<String> events;

  static JourneyStatusReport? tryParse(
    Object? raw, {
    required String fallbackReference,
  }) {
    if (raw is! Map) return null;

    final fields = <String, String>{};

    final seller = raw['seller'];
    if (seller is Map) {
      final name = _str(seller['name']);
      if (name != null) fields['seller'] = name;
    }

    final total = _int(raw['total_minor']);
    final currency = _str(raw['currency']);
    if (total != null) fields['total'] = formatMinorPrice(total, currency);

    final payment = raw['payment'];
    if (payment is Map) {
      final status = _str(payment['status']);
      if (status != null) fields['payment'] = status;
    }

    final events = <String>[];
    final timeline = raw['timeline'];
    if (timeline is Map) {
      for (final entry in timeline.entries) {
        final stamp = _str(entry.value);
        if (entry.key is String && stamp != null) {
          events.add('${entry.key}: $stamp');
        }
      }
    }

    return JourneyStatusReport(
      reference: _str(raw['reference']) ?? fallbackReference,
      status:
          _str(raw['fulfillment_status']) ?? _str(raw['status']) ?? 'unknown',
      fields: fields,
      events: events,
    );
  }
}

/// Prix en unité mineure → texte lisible, sans dépendre d'une locale.
///
/// Le serveur facture en unité mineure (`price_minor`). Diviser en flottant
/// pour l'affichage est le seul endroit où une imprécision est tolérable : le
/// montant envoyé au serveur ne passe JAMAIS par ce chemin.
String formatMinorPrice(int minor, String? currency) {
  final sign = minor < 0 ? '-' : '';
  final absolute = minor.abs();
  final units = absolute ~/ 100;
  final cents = (absolute % 100).toString().padLeft(2, '0');
  final code = (currency == null || currency.isEmpty) ? '' : ' $currency';
  return '$sign$units,$cents$code';
}

/// Date au format exigé par les contrats publics (`Y-m-d`).
String isoDate(DateTime date) {
  final month = date.month.toString().padLeft(2, '0');
  final day = date.day.toString().padLeft(2, '0');
  return '${date.year}-$month-$day';
}

/// Vrai si [value] est une date `Y-m-d` que `DateTime.parse` accepte.
bool isIsoDate(String value) {
  if (!RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(value)) return false;
  final parsed = DateTime.tryParse(value);
  return parsed != null && isoDate(parsed) == value;
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
