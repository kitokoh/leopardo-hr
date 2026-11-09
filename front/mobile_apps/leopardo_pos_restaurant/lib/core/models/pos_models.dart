/// Modèles du POS Restaurant tablette (#9214).
///
/// Volontairement **purs** (aucune dépendance Flutter) : ils sont testables
/// en `dart test` sans moteur graphique, et sérialisables en JSON pour Hive.
library;

/// Une catégorie du menu (regroupement de produits).
class PosCategory {
  const PosCategory({required this.id, required this.name});

  final int id;
  final String name;

  factory PosCategory.fromJson(Map<String, dynamic> json) => PosCategory(
        id: (json['id'] as num).toInt(),
        name: (json['name'] as String?) ?? 'Sans nom',
      );

  Map<String, dynamic> toJson() => {'id': id, 'name': name};
}

/// Un produit vendable en caisse.
///
/// `priceMinor` est en **unités mineures** (entiers) : jamais de flottant
/// persisté, même règle que le backend (`RetailPosService`, `BillCalculator`).
class PosProduct {
  const PosProduct({
    required this.id,
    required this.name,
    required this.priceMinor,
    this.categoryId,
    this.available = true,
    this.sku,
    this.barcode,
  });

  final int id;
  final String name;
  final int priceMinor;
  final int? categoryId;
  final bool available;

  /// Reference interne optionnelle (code plat).
  final String? sku;

  /// Code-barres optionnel : les boissons et produits emballes en portent un,
  /// ce qui permet de les scanner au lieu de les chercher dans le menu.
  final String? barcode;

  factory PosProduct.fromJson(Map<String, dynamic> json) => PosProduct(
        id: (json['id'] as num).toInt(),
        name: (json['name'] as String?) ?? 'Produit',
        priceMinor: (json['price_minor'] as num?)?.toInt() ?? 0,
        categoryId: (json['category_id'] as num?)?.toInt(),
        available: (json['available'] as bool?) ?? true,
        sku: json['sku'] as String?,
        barcode: json['barcode'] as String?,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'price_minor': priceMinor,
        'category_id': categoryId,
        'available': available,
        'sku': sku,
        'barcode': barcode,
      };
}

/// Une table du plan de salle.
class PosTable {
  const PosTable({
    required this.id,
    required this.label,
    required this.seats,
    this.occupied = false,
  });

  final int id;
  final String label;
  final int seats;
  final bool occupied;

  factory PosTable.fromJson(Map<String, dynamic> json) => PosTable(
        id: (json['id'] as num).toInt(),
        label: (json['label'] as String?) ?? 'Table',
        seats: (json['seats'] as num?)?.toInt() ?? 2,
        occupied: (json['occupied'] as bool?) ?? false,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'label': label,
        'seats': seats,
        'occupied': occupied,
      };
}

/// Une ligne du panier en cours.
class CartLine {
  const CartLine({required this.product, required this.quantity});

  final PosProduct product;
  final int quantity;

  int get lineTotalMinor => product.priceMinor * quantity;

  CartLine withQuantity(int newQuantity) =>
      CartLine(product: product, quantity: newQuantity);

  Map<String, dynamic> toJson() => {
        'product': product.toJson(),
        'quantity': quantity,
      };

  factory CartLine.fromJson(Map<String, dynamic> json) => CartLine(
        product: PosProduct.fromJson(json['product'] as Map<String, dynamic>),
        quantity: (json['quantity'] as num).toInt(),
      );
}

/// Le panier : lignes + type de commande + table + couverts.
///
/// Le type de commande et les couverts étaient **codés en dur** dans le POS
/// web (`order_type: 'dine_in', covers: 1`) : le restaurateur ne pouvait ni
/// vendre à emporter, ni livrer, ni choisir une table. Ici ils sont portés
/// par le panier et transmis au serveur.
enum OrderType {
  dineIn('dine_in', 'Sur place'),
  takeaway('takeaway', 'À emporter'),
  delivery('delivery', 'Livraison');

  const OrderType(this.wireValue, this.label);

  final String wireValue;
  final String label;
}

class Cart {
  const Cart({
    this.lines = const [],
    this.orderType = OrderType.dineIn,
    this.tableId,
    this.covers = 1,
    this.note,
    this.discountMinor = 0,
    this.tipMinor = 0,
  });

  final List<CartLine> lines;
  final OrderType orderType;
  final int? tableId;
  final int covers;
  final String? note;

  /// Remise accordee sur la commande (unites mineures, jamais negative).
  final int discountMinor;

  /// Pourboire laisse par le client (unites mineures, jamais negatif).
  ///
  /// Le pourboire n'est **pas** une remise : il s'ajoute au total et ne
  /// reduit jamais l'addition. Il est porte separement pour que le serveur
  /// puisse l'annoncer au client et que la cloture de caisse le distingue.
  final int tipMinor;

  int get subtotalMinor =>
      lines.fold(0, (sum, line) => sum + line.lineTotalMinor);

  /// Total net avant pourboire : jamais negatif, meme si la remise depasse
  /// le sous-total (une caisse ne rend pas d'argent par accident).
  int get netMinor {
    final net = subtotalMinor - discountMinor;

    return net < 0 ? 0 : net;
  }

  /// Total a payer : net + pourboire.
  int get totalMinor => netMinor + tipMinor;

  int get itemCount => lines.fold(0, (sum, line) => sum + line.quantity);

  bool get isEmpty => lines.isEmpty;

  /// Une remise superieure au sous-total est refusee (garde-fou de saisie).
  bool get discountValid => discountMinor <= subtotalMinor;

  /// `dine_in` exige une table (même règle que `StoreRestaurantOrderRequest`).
  bool get isValid =>
      lines.isNotEmpty &&
      discountValid &&
      (orderType != OrderType.dineIn || tableId != null);

  Cart copyWith({
    List<CartLine>? lines,
    OrderType? orderType,
    int? tableId,
    int? covers,
    String? note,
    int? discountMinor,
    int? tipMinor,
  }) =>
      Cart(
        lines: lines ?? this.lines,
        orderType: orderType ?? this.orderType,
        tableId: tableId ?? this.tableId,
        covers: covers ?? this.covers,
        note: note ?? this.note,
        discountMinor: discountMinor ?? this.discountMinor,
        tipMinor: tipMinor ?? this.tipMinor,
      );

  /// Ajoute un produit (ou incrémente la ligne existante).
  Cart addProduct(PosProduct product) {
    final index = lines.indexWhere((line) => line.product.id == product.id);

    if (index == -1) {
      return copyWith(lines: [...lines, CartLine(product: product, quantity: 1)]);
    }

    final updated = [...lines];
    updated[index] = updated[index].withQuantity(updated[index].quantity + 1);

    return copyWith(lines: updated);
  }

  /// Décrémente une ligne ; la retire si la quantité tombe à zéro.
  Cart removeProduct(int productId) {
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

  /// Fixe la quantite d'une ligne ; la retire si la quantite tombe a zero.
  ///
  /// Sert au pave numerique et aux raccourcis : saisir « 12 » directement est
  /// plus rapide que taper 12 fois « + ».
  Cart setQuantity(int productId, int quantity) {
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
        'order_type': orderType.wireValue,
        'table_id': tableId,
        'covers': covers,
        'note': note,
        'discount_minor': discountMinor,
        'tip_minor': tipMinor,
      };

  factory Cart.fromJson(Map<String, dynamic> json) => Cart(
        lines: ((json['lines'] as List<dynamic>?) ?? [])
            .map((raw) => CartLine.fromJson(raw as Map<String, dynamic>))
            .toList(),
        orderType: OrderType.values.firstWhere(
          (type) => type.wireValue == json['order_type'],
          orElse: () => OrderType.dineIn,
        ),
        tableId: (json['table_id'] as num?)?.toInt(),
        covers: (json['covers'] as num?)?.toInt() ?? 1,
        note: json['note'] as String?,
        discountMinor: (json['discount_minor'] as num?)?.toInt() ?? 0,
        tipMinor: (json['tip_minor'] as num?)?.toInt() ?? 0,
      );
}

/// Un **moyen de paiement** accepte en salle.
///
/// `wireValue` est le contrat du backend (`StoreRestaurantPaymentRequest`) :
/// ne pas le renommer sans migrer l'API.
enum PosPaymentMethod {
  cash('cash', 'Especes'),
  card('card', 'Carte'),
  mobileMoney('mobile_money', 'Mobile Money');

  const PosPaymentMethod(this.wireValue, this.label);

  final String wireValue;
  final String label;
}

/// Une **part de paiement** d'une addition partagee (#9214).
///
/// Un groupe de clients demande souvent a payer separement : deux cartes et
/// un peu d'especes, ou une part par convive. Chaque part porte son moyen et
/// son montant ; la somme des parts doit egaler le total de l'addition.
class PaymentSplit {
  const PaymentSplit({required this.method, required this.amountMinor});

  final PosPaymentMethod method;
  final int amountMinor;

  bool get isValid => amountMinor > 0;

  Map<String, dynamic> toJson() => {
        'method': method.wireValue,
        'amount_minor': amountMinor,
      };

  factory PaymentSplit.fromJson(Map<String, dynamic> json) => PaymentSplit(
        method: PosPaymentMethod.values.firstWhere(
          (method) => method.wireValue == json['method'],
          orElse: () => PosPaymentMethod.cash,
        ),
        amountMinor: (json['amount_minor'] as num?)?.toInt() ?? 0,
      );
}

/// Repartition d'une addition entre plusieurs moyens de paiement.
///
/// Regle de caisse : la somme des parts doit **exactement** egaler le total.
/// Un reste non couvert laisserait une addition ouverte ; un excedent
/// reviendrait a rendre de la monnaie qu'on n'a pas encaissee.
class SplitPayment {
  const SplitPayment({this.splits = const []});

  final List<PaymentSplit> splits;

  int get paidMinor => splits.fold(0, (sum, split) => sum + split.amountMinor);

  /// Reste a couvrir pour un total donne (jamais negatif).
  int remainingMinor(int totalMinor) {
    final remaining = totalMinor - paidMinor;

    return remaining < 0 ? 0 : remaining;
  }

  /// La repartition couvre-t-elle exactement le total ?
  bool covers(int totalMinor) => splits.isNotEmpty && paidMinor == totalMinor;

  /// Depasse-t-elle le total ?
  bool exceeds(int totalMinor) => paidMinor > totalMinor;

  SplitPayment add(PaymentSplit split) =>
      SplitPayment(splits: [...splits, split]);

  SplitPayment removeAt(int index) {
    if (index < 0 || index >= splits.length) {
      return this;
    }

    final updated = [...splits]..removeAt(index);

    return SplitPayment(splits: updated);
  }

  /// Repartit un total en [parts] parts egales, le reste allant a la premiere.
  ///
  /// La repartition entiere evite les centimes perdus : `100 / 3` donne
  /// `34, 33, 33` et non `33, 33, 33` avec un centime orphelin.
  static SplitPayment equalParts({
    required int totalMinor,
    required int parts,
    PosPaymentMethod method = PosPaymentMethod.cash,
  }) {
    if (parts <= 0 || totalMinor <= 0) {
      return const SplitPayment();
    }

    final base = totalMinor ~/ parts;
    final remainder = totalMinor % parts;

    return SplitPayment(
      splits: [
        for (var index = 0; index < parts; index++)
          PaymentSplit(
            method: method,
            amountMinor: index == 0 ? base + remainder : base,
          ),
      ],
    );
  }

  Map<String, dynamic> toJson() => {
        'splits': splits.map((split) => split.toJson()).toList(),
      };

  factory SplitPayment.fromJson(Map<String, dynamic> json) => SplitPayment(
        splits: ((json['splits'] as List<dynamic>?) ?? [])
            .map((raw) => PaymentSplit.fromJson(raw as Map<String, dynamic>))
            .toList(),
      );
}
