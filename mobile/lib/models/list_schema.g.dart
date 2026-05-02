// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'list_schema.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ListSchema _$ListSchemaFromJson(Map<String, dynamic> json) => ListSchema(
      columns: (json['columns'] as List<dynamic>)
          .map((e) => ListColumn.fromJson(e as Map<String, dynamic>))
          .toList(),
      pagination: json['pagination'] == null
          ? null
          : ListPagination.fromJson(json['pagination'] as Map<String, dynamic>),
      sorting: json['sorting'] == null
          ? null
          : ListSorting.fromJson(json['sorting'] as Map<String, dynamic>),
      filtering: json['filtering'] == null
          ? null
          : ListFiltering.fromJson(json['filtering'] as Map<String, dynamic>),
      actions: (json['actions'] as List<dynamic>?)
          ?.map((e) => ListAction.fromJson(e as Map<String, dynamic>))
          .toList(),
      searchPlaceholder: json['search_placeholder'] as String?,
      enableSearch: json['enable_search'] as bool? ?? true,
      enableRefresh: json['enable_refresh'] as bool? ?? true,
    );

Map<String, dynamic> _$ListSchemaToJson(ListSchema instance) =>
    <String, dynamic>{
      'columns': instance.columns.map((e) => e.toJson()).toList(),
      if (instance.pagination?.toJson() case final value?) 'pagination': value,
      if (instance.sorting?.toJson() case final value?) 'sorting': value,
      if (instance.filtering?.toJson() case final value?) 'filtering': value,
      if (instance.actions?.map((e) => e.toJson()).toList() case final value?)
        'actions': value,
      if (instance.searchPlaceholder case final value?)
        'search_placeholder': value,
      'enable_search': instance.enableSearch,
      'enable_refresh': instance.enableRefresh,
    };

ListColumn _$ListColumnFromJson(Map<String, dynamic> json) => ListColumn(
      name: json['name'] as String,
      label: json['label'] as String,
      type: $enumDecode(_$ListColumnTypeEnumMap, json['type']),
      visible: json['visible'] as bool? ?? true,
      sortable: json['sortable'] as bool? ?? false,
      format: json['format'] as String?,
      width: (json['width'] as num?)?.toInt(),
      alignment: $enumDecodeNullable(
              _$ListColumnAlignmentEnumMap, json['alignment']) ??
          ListColumnAlignment.left,
    );

Map<String, dynamic> _$ListColumnToJson(ListColumn instance) =>
    <String, dynamic>{
      'name': instance.name,
      'label': instance.label,
      'type': _$ListColumnTypeEnumMap[instance.type]!,
      'visible': instance.visible,
      'sortable': instance.sortable,
      if (instance.format case final value?) 'format': value,
      if (instance.width case final value?) 'width': value,
      'alignment': _$ListColumnAlignmentEnumMap[instance.alignment]!,
    };

const _$ListColumnTypeEnumMap = {
  ListColumnType.text: 'text',
  ListColumnType.number: 'number',
  ListColumnType.date: 'date',
  ListColumnType.datetime: 'datetime',
  ListColumnType.boolean: 'boolean',
  ListColumnType.currency: 'currency',
  ListColumnType.percentage: 'percentage',
  ListColumnType.badge: 'badge',
  ListColumnType.image: 'image',
};

const _$ListColumnAlignmentEnumMap = {
  ListColumnAlignment.left: 'left',
  ListColumnAlignment.center: 'center',
  ListColumnAlignment.right: 'right',
};

ListPagination _$ListPaginationFromJson(Map<String, dynamic> json) =>
    ListPagination(
      pageSize: (json['page_size'] as num?)?.toInt() ?? 20,
      enabled: json['enabled'] as bool? ?? true,
      pageSizeOptions: json['page_size_options'] as String?,
    );

Map<String, dynamic> _$ListPaginationToJson(ListPagination instance) =>
    <String, dynamic>{
      'page_size': instance.pageSize,
      'enabled': instance.enabled,
      if (instance.pageSizeOptions case final value?)
        'page_size_options': value,
    };

ListSorting _$ListSortingFromJson(Map<String, dynamic> json) => ListSorting(
      defaultColumn: json['default_column'] as String?,
      defaultDirection: $enumDecodeNullable(
              _$ListSortDirectionEnumMap, json['default_direction']) ??
          ListSortDirection.asc,
      multiColumn: json['multi_column'] as bool? ?? false,
    );

Map<String, dynamic> _$ListSortingToJson(ListSorting instance) =>
    <String, dynamic>{
      if (instance.defaultColumn case final value?) 'default_column': value,
      'default_direction':
          _$ListSortDirectionEnumMap[instance.defaultDirection]!,
      'multi_column': instance.multiColumn,
    };

const _$ListSortDirectionEnumMap = {
  ListSortDirection.asc: 'asc',
  ListSortDirection.desc: 'desc',
};

ListFiltering _$ListFilteringFromJson(Map<String, dynamic> json) =>
    ListFiltering(
      filters: (json['filters'] as List<dynamic>)
          .map((e) => ListFilter.fromJson(e as Map<String, dynamic>))
          .toList(),
      quickFilters: json['quick_filters'] as bool? ?? true,
    );

Map<String, dynamic> _$ListFilteringToJson(ListFiltering instance) =>
    <String, dynamic>{
      'filters': instance.filters.map((e) => e.toJson()).toList(),
      'quick_filters': instance.quickFilters,
    };

ListFilter _$ListFilterFromJson(Map<String, dynamic> json) => ListFilter(
      name: json['name'] as String,
      label: json['label'] as String,
      type: $enumDecode(_$ListFilterTypeEnumMap, json['type']),
      options:
          (json['options'] as List<dynamic>?)?.map((e) => e as String).toList(),
      placeholder: json['placeholder'] as String?,
    );

Map<String, dynamic> _$ListFilterToJson(ListFilter instance) =>
    <String, dynamic>{
      'name': instance.name,
      'label': instance.label,
      'type': _$ListFilterTypeEnumMap[instance.type]!,
      if (instance.options case final value?) 'options': value,
      if (instance.placeholder case final value?) 'placeholder': value,
    };

const _$ListFilterTypeEnumMap = {
  ListFilterType.text: 'text',
  ListFilterType.select: 'select',
  ListFilterType.dateRange: 'date_range',
  ListFilterType.numberRange: 'number_range',
};

ListAction _$ListActionFromJson(Map<String, dynamic> json) => ListAction(
      name: json['name'] as String,
      label: json['label'] as String,
      type: $enumDecode(_$ListActionTypeEnumMap, json['type']),
      icon: json['icon'] as String?,
      endpoint: json['endpoint'] as String?,
      method: json['method'] as String?,
      condition: json['condition'] as String?,
      confirmRequired: json['confirm_required'] as bool? ?? false,
    );

Map<String, dynamic> _$ListActionToJson(ListAction instance) =>
    <String, dynamic>{
      'name': instance.name,
      'label': instance.label,
      'type': _$ListActionTypeEnumMap[instance.type]!,
      if (instance.icon case final value?) 'icon': value,
      if (instance.endpoint case final value?) 'endpoint': value,
      if (instance.method case final value?) 'method': value,
      if (instance.condition case final value?) 'condition': value,
      'confirm_required': instance.confirmRequired,
    };

const _$ListActionTypeEnumMap = {
  ListActionType.view: 'view',
  ListActionType.edit: 'edit',
  ListActionType.delete: 'delete',
  ListActionType.custom: 'custom',
};
