/// Modeles du POS Retail tablette (#9215).
///
/// Volontairement **purs** (aucune dependance Flutter) : ils sont testables en
/// `flutter test` sans moteur graphique, et serialisables en JSON pour Hive.
///
/// Regle de montant : tout est en **unites mineures entieres** (centimes,
/// centimes de XOF...). Jamais de flottant persiste — meme contrat que le
/// backend (`RetailPosService`, `BillCalculator`).
library;

/// Une categorie de produits (rayon).
class RetailCategory {
  const RetailCategory({required this.id, required this.name});

  final int id;
  final String name;

  factory RetailCategory.fromJson(Map<String, dynamic> json) => RetailCategory(
        id: (json['id'] as num).toInt(),
        name: (json['name'] as String?) ?? 'Sans nom',
      );

  Map<String, dynamic> toJson() => {'id': id, 'name': name};
}

/// Un produit vendable en caisse.
class RetailProduct {
  const RetailProduct({
    required this.id,
    required this.name,
    required this.priceMinor,
    this.sku,
    this.barcode,
    this.costMinor = 0,
    this.categoryId,
    this.available = true,
  });

  final int id;
  final String name;
  final int priceMinor;
  final String? sku;
  final String? barcode;
  final int costMinor;
  final int? categoryId;
  final bool available;

  /// Un produit est scannable s'il porte un code-barres.
  bool get isScannable => barcode != null && barcode!.isNotEmpty;

  /// Marge unitaire (prix - cout), jamais negative.
  int get marginMinor {
    final margin = priceMinor - costMinor;

    return margin < 0 ? 0 : margin;
  }

  /// Le produit correspond-il a une recherche libre (nom, SKU) ou a un
  /// code-barres scanne ?
  ///
  /// Le scan est un cas d'usage de caisse : le vendeur passe le code-barres
  /// devant la douchette, la recherche doit matcher **exactement** le code
  /// (sinon un code partiel ramenerait le mauvais article).
  bool matches(String query) {
    final needle = query.trim().toLowerCase();

    if (needle.isEmpty) {
      return true;
    }

    if (name.toLowerCase().contains(needle)) {
      return true;
    }

    if ((sku ?? '').toLowerCase().contains(needle)) {
      return true;
    }

    return (barcode ?? '').toLowerCase() == needle;
  }

  factory RetailProduct.fromJson(Map<String, dynamic> json) => RetailProduct(
        id: (json['id'] as num).toInt(),
        name: (json['name'] as String?) ?? 'Produit',
        priceMinor: (json['price_minor'] as num?)?.toInt() ?? 0,
        sku: json['sku'] as String?,
        barcode: json['barcode'] as String?,
        costMinor: (json['cost_minor'] as num?)?.toInt() ?? 0,
        categoryId: (json['category_id'] as num?)?.toInt(),
        available: (json['available'] as bool?) ?? true,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'price_minor': priceMinor,
        'sku': sku,
        'barcode': barcode,
        'cost_minor': costMinor,
        'category_id': categoryId,
        'available': available,
      };
}

/// Une ligne du panier.
class RetailCartLine {
  const RetailCartLine({required this.product, required this.quantity});

  final RetailProduct product;
  final int quantity;

  int get lineTotalMinor => product.priceMinor * quantity;

  RetailCartLine withQuantity(int newQuantity) =>
      RetailCartLine(product: product, quantity: newQuantity);

  Map<String, dynamic> toJson() => {
        'product': product.toJson(),
        'quantity': quantity,
      };

  factory RetailCartLine.fromJson(Map<String, dynamic> json) => RetailCartLine(
        product: RetailProduct.fromJson(json['product'] as Map<String, dynamic>),
        quantity: (json['quantity'] as num).toInt(),
      );
}

/// Moyens de paiement acceptes en caisse.
///
/// Les valeurs `wireValue` sont le contrat du backend
/// (`StoreRetailPaymentRequest`) : ne pas les renommer sans migrer l'API.
enum RetailPaymentMethod {
  cash('cash', 'Especes'),
  card('card', 'Carte'),
  mobileMoney('mobile_money', 'Mobile Money');

  const RetailPaymentMethod(this.wireValue, this.label);

  final String wireValue;
  final String label;
}

/// Le panier : lignes + remise + note.
class RetailCart {
  const RetailCart({this.lines = const [], this.discountMinor = 0, this.note});

  final List<RetailCartLine> lines;
  final int discountMinor;
  final String? note;

  int get subtotalMinor =>
      lines.fold(0, (sum, line) => sum + line.lineTotalMinor);

  /// Total net : jamais negatif, meme si la remise depasse le sous-total
  /// (une caisse ne rend pas d'argent par accident).
  int get totalMinor {
    final net = subtotalMinor - discountMinor;

    return net < 0 ? 0 : net;
  }

  int get itemCount => lines.fold(0, (sum, line) => sum + line.quantity);

  bool get isEmpty => lines.isEmpty;

  /// Une remise superieure au sous-total est refusee (garde-fou de saisie).
  bool get isValid => lines.isNotEmpty && discountMinor <= subtotalMinor;

  RetailCart copyWith({
    List<RetailCartLine>? lines,
    int? discountMinor,
    String? note,
  }) =>
      RetailCart(
        lines: lines ?? this.lines,
        discountMinor: discountMinor ?? this.discountMinor,
        note: note ?? this.note,
      );

  /// Ajoute un produit (ou incremente la ligne existante).
  RetailCart addProduct(RetailProduct product, {int quantity = 1}) {
    final index = lines.indexWhere((line) => line.product.id == product.id);

    if (index == -1) {
      return copyWith(
        lines: [...lines, RetailCartLine(product: product, quantity: quantity)],
      );
    }

    final updated = [...lines];
    updated[index] = updated[index].withQuantity(updated[index].quantity + quantity);

    return copyWith(lines: updated);
  }

  /// Decremente une ligne ; la retire si la quantite tombe a zero.
  RetailCart removeProduct(int productId) {
    final index = lines.indexWhere((line) => line.product.id == productId);

    if (index == -1) {
      return this;
    }

    final updated = [...lines];
    final line = updated[index];

    if (line.quantity <= 1) {
      updated.removeAt(index);
    } else {
      updated[index] = line.withQuantity(line.quantity - 1);
    }

    return copyWith(lines: updated);
  }

  /// Fixe la quantite d'une ligne (saisie directe au pave numerique).
  RetailCart setQuantity(int productId, int quantity) {
    final index = lines.indexWhere((line) => line.product.id == productId);

    if (index == -1) {
      return this;
    }

    final updated = [...lines];

    if (quantity <= 0) {
      updated.removeAt(index);
    } else {
      updated[index] = updated[index].withQuantity(quantity);
    }

    return copyWith(lines: updated);
  }

  Map<String, dynamic> toJson() => {
        'lines': lines.map((line) => line.toJson()).toList(),
        'discount_minor': discountMinor,
        'note': note,
      };

  factory RetailCart.fromJson(Map<String, dynamic> json) => RetailCart(
        lines: ((json['lines'] as List<dynamic>?) ?? [])
            .map((raw) => RetailCartLine.fromJson(raw as Map<String, dynamic>))
            .toList(),
        discountMinor: (json['discount_minor'] as num?)?.toInt() ?? 0,
        note: json['note'] as String?,
      );
}

/// Type de mouvement de tiroir-caisse (#9212).
///
/// `wireValue` = contrat backend (`RetailCashMovementType`).
enum RetailCashMovementType {
  cashIn('in', 'Entree de monnaie'),
  cashOut('out', 'Sortie de monnaie'),
  drop('drop', 'Remise en banque');

  const RetailCashMovementType(this.wireValue, this.label);

  final String wireValue;
  final String label;
}

/// Un mouvement de tiroir-caisse saisi en caisse.
class RetailCashMovement {
  const RetailCashMovement({
    required this.type,
    required this.amountMinor,
    required this.reason,
  });

  final RetailCashMovementType type;
  final int amountMinor;
  final String reason;

  /// Un mouvement exige un montant strictement positif ET un motif : c'est
  /// une piece de justification comptable, pas une note libre.
  bool get isValid => amountMinor > 0 && reason.trim().isNotEmpty;

  /// Effet signe sur l'attendu de caisse.
  int get signedEffectMinor =>
      type == RetailCashMovementType.cashIn ? amountMinor : -amountMinor;

  Map<String, dynamic> toJson() => {
        'type': type.wireValue,
        'amount_minor': amountMinor,
        'reason': reason,
      };
}

/// Un remboursement demande en caisse (#9208).
class RetailRefund {
  const RetailRefund({
    required this.amountMinor,
    required this.reason,
    required this.method,
  });

  final int amountMinor;
  final String reason;
  final RetailPaymentMethod method;

  /// Montant positif + motif obligatoire (meme regle que
  /// `RefundRetailOrderRequest`).
  bool get isValid => amountMinor > 0 && reason.trim().isNotEmpty;

  /// Le remboursement est-il cumulativement acceptable ?
  bool fitsWithin(int orderTotalMinor, int alreadyRefundedMinor) =>
      amountMinor > 0 && alreadyRefundedMinor + amountMinor <= orderTotalMinor;

  Map<String, dynamic> toJson() => {
        'amount_minor': amountMinor,
        'reason': reason,
        'method': method.wireValue,
      };
}

/// Une session de caisse (ouverture -> cloture).
class RetailSession {
  const RetailSession({
    required this.id,
    required this.locationId,
    required this.openingCashMinor,
    this.expectedCashMinor = 0,
    this.status = 'open',
  });

  final int id;
  final int locationId;
  final int openingCashMinor;
  final int expectedCashMinor;
  final String status;

  bool get isOpen => status == 'open';

  /// Attendu de caisse a la cloture.
  ///
  /// `fonds + ventes especes + entrees - sorties - remises - remboursements`.
  /// Jamais negatif : un attendu negatif signalerait une saisie incoherente,
  /// on borne a zero plutot que d'afficher un montant impossible.
  static int expectedCash({
    required int openingCashMinor,
    required int cashSalesMinor,
    required int cashInMinor,
    required int cashOutMinor,
    required int dropsMinor,
    required int cashRefundsMinor,
  }) {
    final value = openingCashMinor +
        cashSalesMinor +
        cashInMinor -
        cashOutMinor -
        dropsMinor -
        cashRefundsMinor;

    return value < 0 ? 0 : value;
  }

  /// Ecart de comptage : compte - attendu. Positif = excedent.
  static int variance({required int countedCashMinor, required int expectedCashMinor}) =>
      countedCashMinor - expectedCashMinor;

  factory RetailSession.fromJson(Map<String, dynamic> json) => RetailSession(
        id: (json['id'] as num).toInt(),
        locationId: (json['location_id'] as num).toInt(),
        openingCashMinor: (json['opening_cash_minor'] as num?)?.toInt() ?? 0,
        expectedCashMinor: (json['expected_cash_minor'] as num?)?.toInt() ?? 0,
        status: (json['status'] as String?) ?? 'open',
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'location_id': locationId,
        'opening_cash_minor': openingCashMinor,
        'expected_cash_minor': expectedCashMinor,
        'status': status,
      };
}
