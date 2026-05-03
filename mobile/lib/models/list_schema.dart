import 'package:json_annotation/json_annotation.dart';

part 'list_schema.g.dart';

@JsonSerializable()
class ListSchema {
  final List<ListColumn> columns;
  final ListPagination? pagination;
  final ListSorting? sorting;
  final ListFiltering? filtering;
  final List<ListAction>? actions;
  final String? searchPlaceholder;
  final bool enableSearch;
  final bool enableRefresh;

  const ListSchema({
    required this.columns,
    this.pagination,
    this.sorting,
    this.filtering,
    this.actions,
    this.searchPlaceholder,
    this.enableSearch = true,
    this.enableRefresh = true,
  });

  factory ListSchema.fromJson(Map<String, dynamic> json) =>
      _$ListSchemaFromJson(json);

  Map<String, dynamic> toJson() => _$ListSchemaToJson(this);

  /// Obtient une colonne par son nom
  ListColumn? getColumn(String name) {
    return columns.where((column) => column.name == name).firstOrNull;
  }

  /// Obtient les colonnes visibles
  List<ListColumn> get visibleColumns {
    return columns.where((column) => column.visible).toList();
  }

  /// Obtient les colonnes triables
  List<ListColumn> get sortableColumns {
    return columns.where((column) => column.sortable).toList();
  }

  /// Obtient les actions disponibles pour un élément
  List<ListAction> getActionsForItem(Map<String, dynamic> item) {
    if (actions == null) return [];

    return actions!.where((action) {
      if (action.condition == null) return true;

      // Évaluation simple des conditions
      // TODO: Implémenter un évaluateur d'expressions plus sophistiqué
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
  String toString() => 'ListSchema(columns: ${columns.length})';
}

@JsonSerializable()
class ListColumn {
  final String name;
  final String label;
  final ListColumnType type;
  final bool visible;
  final bool sortable;
  final String? format;
  final int? width;
  final ListColumnAlignment alignment;

  const ListColumn({
    required this.name,
    required this.label,
    required this.type,
    this.visible = true,
    this.sortable = false,
    this.format,
    this.width,
    this.alignment = ListColumnAlignment.left,
  });

  factory ListColumn.fromJson(Map<String, dynamic> json) =>
      _$ListColumnFromJson(json);

  Map<String, dynamic> toJson() => _$ListColumnToJson(this);

  /// Formate une valeur selon le type et format de la colonne
  String formatValue(dynamic value) {
    if (value == null) return '';

    switch (type) {
      case ListColumnType.text:
        return value.toString();

      case ListColumnType.number:
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

      case ListColumnType.date:
        if (value is DateTime) {
          return _formatDate(value);
        } else if (value is String) {
          final date = DateTime.tryParse(value);
          return date != null ? _formatDate(date) : value;
        }
        return value.toString();

      case ListColumnType.datetime:
        if (value is DateTime) {
          return _formatDateTime(value);
        } else if (value is String) {
          final date = DateTime.tryParse(value);
          return date != null ? _formatDateTime(date) : value;
        }
        return value.toString();

      case ListColumnType.boolean:
        if (value is bool) {
          return value ? 'Oui' : 'Non';
        }
        return value.toString().toLowerCase() == 'true' ? 'Oui' : 'Non';

      case ListColumnType.currency:
        final num? numValue =
            value is num ? value : num.tryParse(value.toString());
        if (numValue == null) return value.toString();
        return '${numValue.toStringAsFixed(2)} €';

      case ListColumnType.percentage:
        final num? numValue =
            value is num ? value : num.tryParse(value.toString());
        if (numValue == null) return value.toString();
        return '${numValue.toStringAsFixed(1)}%';

      case ListColumnType.badge:
        return value.toString();

      case ListColumnType.image:
        return value.toString(); // URL de l'image
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
  String toString() => 'ListColumn(name: $name, label: $label, type: $type)';
}

@JsonEnum()
enum ListColumnType {
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
  @JsonValue('badge')
  badge,
  @JsonValue('image')
  image,
}

@JsonEnum()
enum ListColumnAlignment {
  @JsonValue('left')
  left,
  @JsonValue('center')
  center,
  @JsonValue('right')
  right,
}

@JsonSerializable()
class ListPagination {
  final int pageSize;
  final bool enabled;
  final String? pageSizeOptions;

  const ListPagination({
    this.pageSize = 20,
    this.enabled = true,
    this.pageSizeOptions,
  });

  factory ListPagination.fromJson(Map<String, dynamic> json) =>
      _$ListPaginationFromJson(json);

  Map<String, dynamic> toJson() => _$ListPaginationToJson(this);

  /// Obtient les options de taille de page
  List<int> get pageSizeOptionsList {
    if (pageSizeOptions == null) return [10, 20, 50, 100];

    return pageSizeOptions!
        .split(',')
        .map((s) => int.tryParse(s.trim()))
        .where((i) => i != null)
        .cast<int>()
        .toList();
  }

  @override
  String toString() => 'ListPagination(pageSize: $pageSize, enabled: $enabled)';
}

@JsonSerializable()
class ListSorting {
  final String? defaultColumn;
  final ListSortDirection defaultDirection;
  final bool multiColumn;

  const ListSorting({
    this.defaultColumn,
    this.defaultDirection = ListSortDirection.asc,
    this.multiColumn = false,
  });

  factory ListSorting.fromJson(Map<String, dynamic> json) =>
      _$ListSortingFromJson(json);

  Map<String, dynamic> toJson() => _$ListSortingToJson(this);

  @override
  String toString() =>
      'ListSorting(defaultColumn: $defaultColumn, direction: $defaultDirection)';
}

@JsonEnum()
enum ListSortDirection {
  @JsonValue('asc')
  asc,
  @JsonValue('desc')
  desc,
}

@JsonSerializable()
class ListFiltering {
  final List<ListFilter> filters;
  final bool quickFilters;

  const ListFiltering({required this.filters, this.quickFilters = true});

  factory ListFiltering.fromJson(Map<String, dynamic> json) =>
      _$ListFilteringFromJson(json);

  Map<String, dynamic> toJson() => _$ListFilteringToJson(this);

  @override
  String toString() => 'ListFiltering(filters: ${filters.length})';
}

@JsonSerializable()
class ListFilter {
  final String name;
  final String label;
  final ListFilterType type;
  final List<String>? options;
  final String? placeholder;

  const ListFilter({
    required this.name,
    required this.label,
    required this.type,
    this.options,
    this.placeholder,
  });

  factory ListFilter.fromJson(Map<String, dynamic> json) =>
      _$ListFilterFromJson(json);

  Map<String, dynamic> toJson() => _$ListFilterToJson(this);

  @override
  String toString() => 'ListFilter(name: $name, label: $label, type: $type)';
}

@JsonEnum()
enum ListFilterType {
  @JsonValue('text')
  text,
  @JsonValue('select')
  select,
  @JsonValue('date_range')
  dateRange,
  @JsonValue('number_range')
  numberRange,
}

@JsonSerializable()
class ListAction {
  final String name;
  final String label;
  final ListActionType type;
  final String? icon;
  final String? endpoint;
  final String? method;
  final String? condition;
  final bool confirmRequired;

  const ListAction({
    required this.name,
    required this.label,
    required this.type,
    this.icon,
    this.endpoint,
    this.method,
    this.condition,
    this.confirmRequired = false,
  });

  factory ListAction.fromJson(Map<String, dynamic> json) =>
      _$ListActionFromJson(json);

  Map<String, dynamic> toJson() => _$ListActionToJson(this);

  @override
  String toString() => 'ListAction(name: $name, label: $label, type: $type)';
}

@JsonEnum()
enum ListActionType {
  @JsonValue('view')
  view,
  @JsonValue('edit')
  edit,
  @JsonValue('delete')
  delete,
  @JsonValue('custom')
  custom,
}

/// Extension pour ajouter firstOrNull si pas disponible
extension ListColumnExtension on List<ListColumn> {
  ListColumn? get firstOrNull => isEmpty ? null : first;
}
