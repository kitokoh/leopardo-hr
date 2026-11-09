/// Modeles de stock partages par les surfaces mobiles (retail + restaurant).
///
/// Regle de conception : ces modeles sont **tolerants**. Un payload partiel
/// (champ absent, `null`, entier la ou un decimal est attendu) ne doit JAMAIS
/// lever : un ecran de stock qui plante sur une reponse serveur incomplete est
/// pire qu'un ecran qui affiche une valeur manquante. Chaque accesseur
/// numerique passe donc par un helper qui accepte `num`, `String` numerique ou
/// `null`.
///
/// Aucune agregation n'est faite ici : les totaux viennent du serveur
/// (`/retail/reports/stock-valuation`), jamais d'une somme calculee cote
/// client qui divergerait du backend.
library;

/// Convertit une valeur JSON heterogene en `double?` sans lever.
double? _asDouble(Object? value) {
  if (value == null) return null;
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value);
  return null;
}

/// Convertit une valeur JSON heterogene en `int?` sans lever.
int? _asInt(Object? value) {
  if (value == null) return null;
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value);
  return null;
}

/// Niveau de stock courant d'un produit dans un emplacement.
///
/// Shape serveur (`RetailStockLevelResource` / `RestaurantStockLevelResource`) :
/// `{id, location_id|branch_id, product_id|ingredient_id, quantity,
///   avg_cost_minor, reorder_level, alert_threshold, is_below_threshold}`.
class StockLevel {
  const StockLevel({
    required this.id,
    this.locationId,
    this.productId,
    this.productName,
    this.quantity = 0,
    this.unit,
    this.avgCostMinor,
    this.reorderLevel,
    this.alertThreshold,
    this.isBelowThreshold = false,
  });

  final int id;
  final int? locationId;
  final int? productId;
  final String? productName;
  final double quantity;
  final String? unit;
  final int? avgCostMinor;
  final double? reorderLevel;
  final double? alertThreshold;
  final bool isBelowThreshold;

  factory StockLevel.fromJson(Map<String, dynamic> json) {
    final quantity = _asDouble(json['quantity']) ?? 0;
    final threshold = _asDouble(json['alert_threshold']);

    // Le serveur envoie `is_below_threshold` ; on ne le recalcule que s'il
    // manque, et seulement quand le seuil est connu (sinon on affirmerait
    // « au-dessus du seuil » sans aucune donnee).
    final below = json['is_below_threshold'];
    final isBelow = below is bool
        ? below
        : (threshold != null && quantity <= threshold);

    return StockLevel(
      id: _asInt(json['id']) ?? 0,
      locationId: _asInt(json['location_id']) ?? _asInt(json['branch_id']),
      productId: _asInt(json['product_id']) ?? _asInt(json['ingredient_id']),
      productName: (json['product_name'] ?? json['ingredient']) as String?,
      quantity: quantity,
      unit: json['unit'] as String?,
      avgCostMinor: _asInt(json['avg_cost_minor']),
      reorderLevel: _asDouble(json['reorder_level']),
      alertThreshold: threshold,
      isBelowThreshold: isBelow,
    );
  }
}

/// Motif d'un mouvement de stock, normalise entre les deux verticales.
///
/// Retail (`RetailStockReasonCode`) et restaurant (`StockMovementReason`)
/// n'emploient pas les memes libelles (`purchase` vs `receiving`,
/// `loss` vs `waste`). L'ecran mobile affiche un libelle unique par geste
/// metier ; la traduction se fait ici, pas dans chaque ecran.
enum StockMovementReason {
  sale,
  receiving,
  count,
  adjustment,
  waste,
  transferIn,
  transferOut,
  refund,
  unknown;

  /// Normalise un code serveur (les deux verticales confondues).
  static StockMovementReason fromWire(String? code) {
    switch (code) {
      case 'sale':
        return StockMovementReason.sale;
      case 'purchase':
      case 'receiving':
        return StockMovementReason.receiving;
      case 'count':
        return StockMovementReason.count;
      case 'adjustment':
        return StockMovementReason.adjustment;
      case 'loss':
      case 'waste':
        return StockMovementReason.waste;
      case 'transfer_in':
        return StockMovementReason.transferIn;
      case 'transfer_out':
        return StockMovementReason.transferOut;
      case 'return':
      case 'refund':
        return StockMovementReason.refund;
      default:
        return StockMovementReason.unknown;
    }
  }

  /// Code a envoyer au serveur pour une saisie mobile.
  ///
  /// Seuls les gestes saisissables a la main sont exposes : une vente ou un
  /// transfert ne se declarent pas depuis un formulaire d'ajustement.
  String? get wireCode {
    switch (this) {
      case StockMovementReason.receiving:
        return 'purchase';
      case StockMovementReason.adjustment:
        return 'adjustment';
      case StockMovementReason.waste:
        return 'loss';
      case StockMovementReason.count:
        return 'adjustment';
      default:
        return null;
    }
  }

  /// Vrai si le motif correspond a une ENTREE de stock.
  bool get isInbound =>
      this == StockMovementReason.receiving ||
      this == StockMovementReason.transferIn ||
      this == StockMovementReason.refund;
}

/// Mouvement de stock journalise (append-only cote serveur).
class StockMovement {
  const StockMovement({
    required this.id,
    this.locationId,
    this.productId,
    this.productName,
    this.quantityDelta = 0,
    this.unitCostMinor,
    this.reason = StockMovementReason.unknown,
    this.reasonCode,
    this.referenceType,
    this.referenceId,
    this.note,
    this.userId,
    this.createdAt,
  });

  final int id;
  final int? locationId;
  final int? productId;
  final String? productName;
  final double quantityDelta;
  final int? unitCostMinor;
  final StockMovementReason reason;
  final String? reasonCode;
  final String? referenceType;
  final int? referenceId;
  final String? note;
  final int? userId;
  final DateTime? createdAt;

  bool get isInbound => quantityDelta > 0;

  factory StockMovement.fromJson(Map<String, dynamic> json) {
    final reasonCode = json['reason_code'] as String?;

    return StockMovement(
      id: _asInt(json['id']) ?? 0,
      locationId: _asInt(json['location_id']) ?? _asInt(json['branch_id']),
      productId: _asInt(json['product_id']) ?? _asInt(json['ingredient_id']),
      productName: (json['product_name'] ?? json['ingredient']) as String?,
      quantityDelta: _asDouble(json['quantity_delta']) ?? 0,
      unitCostMinor: _asInt(json['unit_cost_minor']),
      reason: StockMovementReason.fromWire(reasonCode),
      reasonCode: reasonCode,
      referenceType: json['reference_type'] as String?,
      referenceId: _asInt(json['reference_id']),
      note: json['note'] as String?,
      userId: _asInt(json['user_id']),
      createdAt: DateTime.tryParse((json['created_at'] as String?) ?? ''),
    );
  }
}

/// Alerte de seuil de stock (niveau dont la quantite a atteint le seuil).
class StockAlert {
  const StockAlert({
    required this.id,
    this.locationId,
    this.productId,
    this.productName,
    this.quantity = 0,
    this.alertThreshold,
  });

  final int id;
  final int? locationId;
  final int? productId;
  final String? productName;
  final double quantity;
  final double? alertThreshold;

  factory StockAlert.fromJson(Map<String, dynamic> json) {
    return StockAlert(
      id: _asInt(json['id']) ?? 0,
      locationId: _asInt(json['location_id']) ?? _asInt(json['branch_id']),
      productId: _asInt(json['product_id']) ?? _asInt(json['ingredient_id']),
      productName: (json['product_name'] ?? json['ingredient']) as String?,
      quantity: _asDouble(json['quantity']) ?? 0,
      alertThreshold: _asDouble(json['alert_threshold']),
    );
  }
}
