/// Variantes et options de plat du POS Restaurant (#9265).
///
/// Un plat n'est pas toujours un simple produit a prix fixe : le client
/// choisit une cuisson, un accompagnement, des supplements. Chaque option
/// peut ajouter un prix, et certains choix sont obligatoires. Ce module porte
/// la definition du menu (groupes + options) et la **decision** : quelles
/// selections sont valides, et quel est le prix effectif de la ligne.
///
/// Pur (aucune dependance Flutter) et partage : la regle de prix d'une ligne
/// d'options doit etre la meme dans le panier, l'apercu du ticket et le
/// ticket imprime.
library;

/// Une option selectable d'un groupe (ex. « Saignant », « Frites »,
/// « Supplément fromage »).
class PosOption {
  const PosOption({
    required this.id,
    required this.name,
    this.priceMinor = 0,
    this.available = true,
  });

  final int id;
  final String name;

  /// Prix **additionnel** en unites mineures (0 pour un choix sans frais).
  final int priceMinor;

  final bool available;

  bool get hasPrice => priceMinor > 0;

  factory PosOption.fromJson(Map<String, dynamic> json) => PosOption(
        id: (json['id'] as num).toInt(),
        name: (json['name'] as String?) ?? 'Option',
        priceMinor: (json['price_minor'] as num?)?.toInt() ?? 0,
        available: (json['available'] as bool?) ?? true,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'price_minor': priceMinor,
        'available': available,
      };
}

/// Un groupe d'options (ex. « Cuisson », « Accompagnement »).
class PosOptionGroup {
  const PosOptionGroup({
    required this.id,
    required this.name,
    this.options = const [],
    this.required = false,
    this.maxSelections = 1,
  });

  final int id;
  final String name;
  final List<PosOption> options;

  /// La selection est-elle **obligatoire** pour ajouter le produit au panier ?
  final bool required;

  /// Nombre maximal de selections dans ce groupe (1 = choix unique,
  /// > 1 = plusieurs suppléments possibles).
  final int maxSelections;

  bool get hasOptions => options.isNotEmpty;

  PosOption? optionById(int optionId) {
    for (final option in options) {
      if (option.id == optionId) {
        return option;
      }
    }

    return null;
  }

  /// Les selections donnees satisfont-elles ce groupe ?
  ///
  /// Un groupe obligatoire doit avoir au moins une selection ; aucun groupe
  /// ne peut depasser [maxSelections] selections au total (toutes quantites
  /// confondues, une option supplementaire comptant pour une selection).
  bool isSatisfiedBy(List<PosOptionSelection> selections, {bool forQuantity = false}) {
    final mine =
        selections.where((selection) => selection.groupId == id).toList();

    if (mine.isEmpty) {
      return !required;
    }

    // Une option indisponible ne peut pas etre selectionnee.
    for (final selection in mine) {
      final option = optionById(selection.optionId);

      if (option == null || !option.available) {
        return false;
      }
    }

    // Nombre de selections distinctes <= maxSelections (une option repetee
    // pour une quantite > 1 reste une seule selection).
    return mine.length <= maxSelections;
  }

  factory PosOptionGroup.fromJson(Map<String, dynamic> json) => PosOptionGroup(
        id: (json['id'] as num).toInt(),
        name: (json['name'] as String?) ?? 'Options',
        options: ((json['options'] as List<dynamic>?) ?? [])
            .map((raw) => PosOption.fromJson(raw as Map<String, dynamic>))
            .toList(),
        required: (json['required'] as bool?) ?? false,
        maxSelections: (json['max_selections'] as num?)?.toInt() ?? 1,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'options': options.map((option) => option.toJson()).toList(),
        'required': required,
        'max_selections': maxSelections,
      };
}

/// Une selection effectuee sur une ligne du panier :
/// « groupe 2 (Accompagnement) → option 5 (Frites), quantite 2 ».
class PosOptionSelection {
  const PosOptionSelection({
    required this.groupId,
    required this.optionId,
    this.quantity = 1,
  });

  final int groupId;
  final int optionId;
  final int quantity;

  /// Prix additionnel de cette selection (option x quantite).
  int priceMinorWith(PosOption option) => option.priceMinor * quantity;

  Map<String, dynamic> toJson() => {
        'group_id': groupId,
        'option_id': optionId,
        'quantity': quantity,
      };

  factory PosOptionSelection.fromJson(Map<String, dynamic> json) =>
      PosOptionSelection(
        groupId: (json['group_id'] as num).toInt(),
        optionId: (json['option_id'] as num).toInt(),
        quantity: (json['quantity'] as num?)?.toInt() ?? 1,
      );

  @override
  bool operator ==(Object other) =>
      other is PosOptionSelection &&
      other.groupId == groupId &&
      other.optionId == optionId &&
      other.quantity == quantity;

  @override
  int get hashCode => Object.hash(groupId, optionId, quantity);
}

/// Aide au calcul du prix d'une ligne portant des options.
class PosOptionsPricing {
  const PosOptionsPricing._();

  /// Prix additionnel total des selections pour un produit donne.
  static int additionalMinor({
    required PosProductOptions productOptions,
    required List<PosOptionSelection> selections,
  }) {
    var total = 0;

    for (final selection in selections) {
      final group = productOptions.groupById(selection.groupId);

      if (group == null) {
        continue;
      }

      final option = group.optionById(selection.optionId);

      if (option == null) {
        continue;
      }

      total += selection.priceMinorWith(option);
    }

    return total;
  }

  /// Toutes les selections sont-elles valides pour le produit ?
  ///
  /// Verifie que chaque selection existe, que les options sont disponibles,
  /// que les groupes obligatoires sont couverts et qu'aucun groupe ne
  /// depasse son plafond.
  static bool validate({
    required PosProductOptions productOptions,
    required List<PosOptionSelection> selections,
  }) {
    for (final group in productOptions.groups) {
      if (!group.isSatisfiedBy(selections)) {
        return false;
      }
    }

    // Une selection vers un groupe inconnu du produit est invalide.
    for (final selection in selections) {
      if (productOptions.groupById(selection.groupId) == null) {
        return false;
      }
    }

    return true;
  }
}

/// Les groupes d'options attaches a un produit du menu.
///
/// Separe du produit pour ne pas alourdir le modele de base : un plat sans
/// variantes ne porte aucun groupe, et le JSON reste compact.
class PosProductOptions {
  const PosProductOptions({this.groups = const []});

  final List<PosOptionGroup> groups;

  bool get isEmpty => groups.isEmpty;

  bool get hasRequired => groups.any((group) => group.required);

  PosOptionGroup? groupById(int groupId) {
    for (final group in groups) {
      if (group.id == groupId) {
        return group;
      }
    }

    return null;
  }

  factory PosProductOptions.fromJson(Map<String, dynamic>? json) =>
      PosProductOptions(
        groups: ((json?['groups'] as List<dynamic>?) ?? [])
            .map((raw) => PosOptionGroup.fromJson(raw as Map<String, dynamic>))
            .toList(),
      );

  Map<String, dynamic> toJson() => {
        'groups': groups.map((group) => group.toJson()).toList(),
      };
}
