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
  });

  final int id;
  final String name;
  final int priceMinor;
  final int? categoryId;
  final bool available;

  factory PosProduct.fromJson(Map<String, dynamic> json) => PosProduct(
        id: (json['id'] as num).toInt(),
        name: (json['name'] as String?) ?? 'Produit',
        priceMinor: (json['price_minor'] as num?)?.toInt() ?? 0,
        categoryId: (json['category_id'] as num?)?.toInt(),
        available: (json['available'] as bool?) ?? true,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'price_minor': priceMinor,
        'category_id': categoryId,
        'available': available,
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
  });

  final List<CartLine> lines;
  final OrderType orderType;
  final int? tableId;
  final int covers;
  final String? note;

  int get subtotalMinor =>
      lines.fold(0, (sum, line) => sum + line.lineTotalMinor);

  int get itemCount => lines.fold(0, (sum, line) => sum + line.quantity);

  bool get isEmpty => lines.isEmpty;

  /// `dine_in` exige une table (même règle que `StoreRestaurantOrderRequest`).
  bool get isValid =>
      lines.isNotEmpty && (orderType != OrderType.dineIn || tableId != null);

  Cart copyWith({
    List<CartLine>? lines,
    OrderType? orderType,
    int? tableId,
    int? covers,
    String? note,
  }) =>
      Cart(
        lines: lines ?? this.lines,
        orderType: orderType ?? this.orderType,
        tableId: tableId ?? this.tableId,
        covers: covers ?? this.covers,
        note: note ?? this.note,
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

  Map<String, dynamic> toJson() => {
        'lines': lines.map((line) => line.toJson()).toList(),
        'order_type': orderType.wireValue,
        'table_id': tableId,
        'covers': covers,
        'note': note,
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
      );
}
