import 'package:json_annotation/json_annotation.dart';

part 'detail_schema.g.dart';

@JsonSerializable()
class DetailSchema {
  final List<DetailSection> sections;
  final List<DetailAction>? actions;
  final String? title;
  final String? subtitle;
  final DetailLayout layout;

  const DetailSchema({
    required this.sections,
    this.actions,
    this.title,
    this.subtitle,
    this.layout = DetailLayout.vertical,
  });

  factory DetailSchema.fromJson(Map<String, dynamic> json) =>
      _$DetailSchemaFromJson(json);

  Map<String, dynamic> toJson() => _$DetailSchemaToJson(this);

  /// Obtient une section par son nom
  DetailSection? getSection(String name) {
    return sections.where((section) => section.name == name).firstOrNull;
  }

  /// Obtient toutes les sections visibles
  List<DetailSection> get visibleSections {
    return sections.where((section) => section.visible).toList();
  }

  /// Obtient les actions disponibles pour un élément
  List<DetailAction> getActionsForItem(Map<String, dynamic> item) {
    if (actions == null) return [];

    return actions!.where((action) {
      if (action.condition == null) return true;

      // Évaluation simple des conditions
      return _evaluateCondition(action.condition!, item);
    }).toList();
  }

  bool _evaluateCondition(String condition, Map<String, dynamic> item) {
    // Implémentation basique pour les conditions simples
    // Exemple: "status == 'active'" ou "role != 'admin'"

    if (condition.contains('==')) {
      final parts = condition.split('==').map((s) => s.trim()).toList();
      if (parts.length == 2) {
        final fieldValue = item[parts[0]]?.toString();
        final expectedValue = parts[1].replaceAll("'", "").replaceAll('"', '');
        return fieldValue == expectedValue;
      }
    }

    if (condition.contains('!=')) {
      final parts = condition.split('!=').map((s) => s.trim()).toList();
      if (parts.length == 2) {
        final fieldValue = item[parts[0]]?.toString();
        final expectedValue = parts[1].replaceAll("'", "").replaceAll('"', '');
        return fieldValue != expectedValue;
      }
    }

    // Par défaut, retourner true si la condition n'est pas reconnue
    return true;
  }

  @override
  String toString() =>
      'DetailSchema(sections: ${sections.length}, layout: $layout)';
}

@JsonEnum()
enum DetailLayout {
  @JsonValue('vertical')
  vertical,
  @JsonValue('horizontal')
  horizontal,
  @JsonValue('grid')
  grid,
  @JsonValue('tabs')
  tabs;
}

@JsonSerializable()
class DetailSection {
  final String name;
  final String title;
  final List<DetailField> fields;
  final bool visible;
  final bool collapsible;
  final bool collapsed;
  final String? icon;

  const DetailSection({
    required this.name,
    required this.title,
    required this.fields,
    this.visible = true,
    this.collapsible = false,
    this.collapsed = false,
    this.icon,
  });

  factory DetailSection.fromJson(Map<String, dynamic> json) =>
      _$DetailSectionFromJson(json);

  Map<String, dynamic> toJson() => _$DetailSectionToJson(this);

  /// Obtient un champ par son nom
  DetailField? getField(String name) {
    return fields.where((field) => field.name == name).firstOrNull;
  }

  /// Obtient tous les champs visibles
  List<DetailField> get visibleFields {
    return fields.where((field) => field.visible).toList();
  }

  @override
  String toString() =>
      'DetailSection(name: $name, title: $title, fields: ${fields.length})';
}

@JsonSerializable()
class DetailField {
  final String name;
  final String label;
  final DetailFieldType type;
  final bool visible;
  final String? format;
  final DetailFieldSize size;
  final String? icon;
  final String? helpText;

  const DetailField({
    required this.name,
    required this.label,
    required this.type,
    this.visible = true,
    this.format,
    this.size = DetailFieldSize.full,
    this.icon,
    this.helpText,
  });

  factory DetailField.fromJson(Map<String, dynamic> json) =>
      _$DetailFieldFromJson(json);

  Map<String, dynamic> toJson() => _$DetailFieldToJson(this);

  /// Formate une valeur selon le type et format du champ
  String formatValue(dynamic value) {
    if (value == null) return '';

    switch (type) {
      case DetailFieldType.text:
        return value.toString();

      case DetailFieldType.number:
        final num? numValue =
            value is num ? value : num.tryParse(value.toString());
        if (numValue == null) return value.toString();

        if (format != null) {
          // Format simple pour les nombres (ex: "0.00" pour 2 décimales)
          if (format!.contains('.')) {
            final decimals = format!.split('.')[1].length;
            return numValue.toStringAsFixed(decimals);
          }
        }
        return numValue.toString();

      case DetailFieldType.date:
        if (value is DateTime) {
          return _formatDate(value);
        } else if (value is String) {
          final date = DateTime.tryParse(value);
          return date != null ? _formatDate(date) : value;
        }
        return value.toString();

      case DetailFieldType.datetime:
        if (value is DateTime) {
          return _formatDateTime(value);
        } else if (value is String) {
          final date = DateTime.tryParse(value);
          return date != null ? _formatDateTime(date) : value;
        }
        return value.toString();

      case DetailFieldType.boolean:
        if (value is bool) {
          return value ? 'Oui' : 'Non';
        }
        return value.toString().toLowerCase() == 'true' ? 'Oui' : 'Non';

      case DetailFieldType.currency:
        final num? numValue =
            value is num ? value : num.tryParse(value.toString());
        if (numValue == null) return value.toString();
        return '${numValue.toStringAsFixed(2)} €';

      case DetailFieldType.percentage:
        final num? numValue =
            value is num ? value : num.tryParse(value.toString());
        if (numValue == null) return value.toString();
        return '${numValue.toStringAsFixed(1)}%';

      case DetailFieldType.email:
        return value.toString();

      case DetailFieldType.phone:
        return value.toString();

      case DetailFieldType.url:
        return value.toString();

      case DetailFieldType.badge:
        return value.toString();

      case DetailFieldType.image:
        return value.toString(); // URL de l'image

      case DetailFieldType.list:
        if (value is List) {
          return value.join(', ');
        }
        return value.toString();

      case DetailFieldType.json:
        // Affichage formaté du JSON (simplifié)
        return value.toString();
    }
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/'
        '${date.month.toString().padLeft(2, '0')}/'
        '${date.year}';
  }

  String _formatDateTime(DateTime date) {
    return '${_formatDate(date)} '
        '${date.hour.toString().padLeft(2, '0')}:'
        '${date.minute.toString().padLeft(2, '0')}';
  }

  @override
  String toString() => 'DetailField(name: $name, label: $label, type: $type)';
}

@JsonEnum()
enum DetailFieldType {
  @JsonValue('text')
  text,
  @JsonValue('number')
  number,
  @JsonValue('date')
  date,
  @JsonValue('datetime')
  datetime,
  @JsonValue('boolean')
  boolean,
  @JsonValue('currency')
  currency,
  @JsonValue('percentage')
  percentage,
  @JsonValue('email')
  email,
  @JsonValue('phone')
  phone,
  @JsonValue('url')
  url,
  @JsonValue('badge')
  badge,
  @JsonValue('image')
  image,
  @JsonValue('list')
  list,
  @JsonValue('json')
  json;
}

@JsonEnum()
enum DetailFieldSize {
  @JsonValue('full')
  full,
  @JsonValue('half')
  half,
  @JsonValue('third')
  third,
  @JsonValue('quarter')
  quarter;
}

@JsonSerializable()
class DetailAction {
  final String name;
  final String label;
  final DetailActionType type;
  final String? icon;
  final String? endpoint;
  final String? method;
  final String? condition;
  final bool confirmRequired;
  final String? confirmMessage;

  const DetailAction({
    required this.name,
    required this.label,
    required this.type,
    this.icon,
    this.endpoint,
    this.method,
    this.condition,
    this.confirmRequired = false,
    this.confirmMessage,
  });

  factory DetailAction.fromJson(Map<String, dynamic> json) =>
      _$DetailActionFromJson(json);

  Map<String, dynamic> toJson() => _$DetailActionToJson(this);

  @override
  String toString() => 'DetailAction(name: $name, label: $label, type: $type)';
}

@JsonEnum()
enum DetailActionType {
  @JsonValue('edit')
  edit,
  @JsonValue('delete')
  delete,
  @JsonValue('share')
  share,
  @JsonValue('print')
  print,
  @JsonValue('export')
  export,
  @JsonValue('custom')
  custom;
}

/// Extension pour ajouter firstOrNull si pas disponible
extension DetailSectionExtension on List<DetailSection> {
  DetailSection? get firstOrNull => isEmpty ? null : first;
}

extension DetailFieldExtension on List<DetailField> {
  DetailField? get firstOrNull => isEmpty ? null : first;
}
