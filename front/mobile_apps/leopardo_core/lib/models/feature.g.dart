// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'feature.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Feature _$FeatureFromJson(Map<String, dynamic> json) => Feature(
      key: json['key'] as String,
      title: json['title'] as String,
      description: json['description'] as String,
      endpoint: json['endpoint'] as String,
      methods:
          (json['methods'] as List<dynamic>).map((e) => e as String).toList(),
      parameters: json['parameters'] as Map<String, dynamic>,
      responseSchema: json['response_schema'] as Map<String, dynamic>,
      permissions: (json['permissions'] as List<dynamic>)
          .map((e) => e as String)
          .toList(),
      minimumMobileVersion: json['mobile_version_min'] as String,
      maximumMobileVersion: json['mobile_version_max'] as String?,
      type: $enumDecode(_$FeatureTypeEnumMap, json['ui_type']),
      formSchema: json['form_schema'] == null
          ? null
          : FormSchema.fromJson(json['form_schema'] as Map<String, dynamic>),
      listSchema: json['list_schema'] == null
          ? null
          : ListSchema.fromJson(json['list_schema'] as Map<String, dynamic>),
      detailSchema: json['detail_schema'] == null
          ? null
          : DetailSchema.fromJson(
              json['detail_schema'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$FeatureToJson(Feature instance) => <String, dynamic>{
      'key': instance.key,
      'title': instance.title,
      'description': instance.description,
      'endpoint': instance.endpoint,
      'methods': instance.methods,
      'parameters': instance.parameters,
      'response_schema': instance.responseSchema,
      'permissions': instance.permissions,
      'mobile_version_min': instance.minimumMobileVersion,
      if (instance.maximumMobileVersion case final value?)
        'mobile_version_max': value,
      'ui_type': _$FeatureTypeEnumMap[instance.type]!,
      if (instance.formSchema?.toJson() case final value?) 'form_schema': value,
      if (instance.listSchema?.toJson() case final value?) 'list_schema': value,
      if (instance.detailSchema?.toJson() case final value?)
        'detail_schema': value,
    };

const _$FeatureTypeEnumMap = {
  FeatureType.list: 'list',
  FeatureType.form: 'form',
  FeatureType.detail: 'detail',
  FeatureType.dashboard: 'dashboard',
  FeatureType.generic: 'generic',
};
