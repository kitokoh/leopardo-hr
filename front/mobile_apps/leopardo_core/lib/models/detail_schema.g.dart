// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'detail_schema.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DetailSchema _$DetailSchemaFromJson(Map<String, dynamic> json) => DetailSchema(
  sections:
      (json['sections'] as List<dynamic>)
          .map((e) => DetailSection.fromJson(e as Map<String, dynamic>))
          .toList(),
  actions:
      (json['actions'] as List<dynamic>?)
          ?.map((e) => DetailAction.fromJson(e as Map<String, dynamic>))
          .toList(),
  title: json['title'] as String?,
  subtitle: json['subtitle'] as String?,
  layout:
      $enumDecodeNullable(_$DetailLayoutEnumMap, json['layout']) ??
      DetailLayout.vertical,
);

Map<String, dynamic> _$DetailSchemaToJson(DetailSchema instance) =>
    <String, dynamic>{
      'sections': instance.sections.map((e) => e.toJson()).toList(),
      if (instance.actions?.map((e) => e.toJson()).toList() case final value?)
        'actions': value,
      if (instance.title case final value?) 'title': value,
      if (instance.subtitle case final value?) 'subtitle': value,
      'layout': _$DetailLayoutEnumMap[instance.layout]!,
    };

const _$DetailLayoutEnumMap = {
  DetailLayout.vertical: 'vertical',
  DetailLayout.horizontal: 'horizontal',
  DetailLayout.grid: 'grid',
  DetailLayout.tabs: 'tabs',
};

DetailSection _$DetailSectionFromJson(Map<String, dynamic> json) =>
    DetailSection(
      name: json['name'] as String,
      title: json['title'] as String,
      fields:
          (json['fields'] as List<dynamic>)
              .map((e) => DetailField.fromJson(e as Map<String, dynamic>))
              .toList(),
      visible: json['visible'] as bool? ?? true,
      collapsible: json['collapsible'] as bool? ?? false,
      collapsed: json['collapsed'] as bool? ?? false,
      icon: json['icon'] as String?,
    );

Map<String, dynamic> _$DetailSectionToJson(DetailSection instance) =>
    <String, dynamic>{
      'name': instance.name,
      'title': instance.title,
      'fields': instance.fields.map((e) => e.toJson()).toList(),
      'visible': instance.visible,
      'collapsible': instance.collapsible,
      'collapsed': instance.collapsed,
      if (instance.icon case final value?) 'icon': value,
    };

DetailField _$DetailFieldFromJson(Map<String, dynamic> json) => DetailField(
  name: json['name'] as String,
  label: json['label'] as String,
  type: $enumDecode(_$DetailFieldTypeEnumMap, json['type']),
  visible: json['visible'] as bool? ?? true,
  format: json['format'] as String?,
  size:
      $enumDecodeNullable(_$DetailFieldSizeEnumMap, json['size']) ??
      DetailFieldSize.full,
  icon: json['icon'] as String?,
  helpText: json['help_text'] as String?,
);

Map<String, dynamic> _$DetailFieldToJson(DetailField instance) =>
    <String, dynamic>{
      'name': instance.name,
      'label': instance.label,
      'type': _$DetailFieldTypeEnumMap[instance.type]!,
      'visible': instance.visible,
      if (instance.format case final value?) 'format': value,
      'size': _$DetailFieldSizeEnumMap[instance.size]!,
      if (instance.icon case final value?) 'icon': value,
      if (instance.helpText case final value?) 'help_text': value,
    };

const _$DetailFieldTypeEnumMap = {
  DetailFieldType.text: 'text',
  DetailFieldType.number: 'number',
  DetailFieldType.date: 'date',
  DetailFieldType.datetime: 'datetime',
  DetailFieldType.boolean: 'boolean',
  DetailFieldType.currency: 'currency',
  DetailFieldType.percentage: 'percentage',
  DetailFieldType.email: 'email',
  DetailFieldType.phone: 'phone',
  DetailFieldType.url: 'url',
  DetailFieldType.badge: 'badge',
  DetailFieldType.image: 'image',
  DetailFieldType.list: 'list',
  DetailFieldType.json: 'json',
};

const _$DetailFieldSizeEnumMap = {
  DetailFieldSize.full: 'full',
  DetailFieldSize.half: 'half',
  DetailFieldSize.third: 'third',
  DetailFieldSize.quarter: 'quarter',
};

DetailAction _$DetailActionFromJson(Map<String, dynamic> json) => DetailAction(
  name: json['name'] as String,
  label: json['label'] as String,
  type: $enumDecode(_$DetailActionTypeEnumMap, json['type']),
  icon: json['icon'] as String?,
  endpoint: json['endpoint'] as String?,
  method: json['method'] as String?,
  condition: json['condition'] as String?,
  confirmRequired: json['confirm_required'] as bool? ?? false,
  confirmMessage: json['confirm_message'] as String?,
);

Map<String, dynamic> _$DetailActionToJson(DetailAction instance) =>
    <String, dynamic>{
      'name': instance.name,
      'label': instance.label,
      'type': _$DetailActionTypeEnumMap[instance.type]!,
      if (instance.icon case final value?) 'icon': value,
      if (instance.endpoint case final value?) 'endpoint': value,
      if (instance.method case final value?) 'method': value,
      if (instance.condition case final value?) 'condition': value,
      'confirm_required': instance.confirmRequired,
      if (instance.confirmMessage case final value?) 'confirm_message': value,
    };

const _$DetailActionTypeEnumMap = {
  DetailActionType.edit: 'edit',
  DetailActionType.delete: 'delete',
  DetailActionType.share: 'share',
  DetailActionType.print: 'print',
  DetailActionType.export: 'export',
  DetailActionType.custom: 'custom',
};
