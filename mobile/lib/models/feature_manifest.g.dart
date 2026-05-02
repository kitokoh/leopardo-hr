// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'feature_manifest.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

FeatureManifest _$FeatureManifestFromJson(Map<String, dynamic> json) =>
    FeatureManifest(
      version: json['version'] as String,
      generatedAt: DateTime.parse(json['generated_at'] as String),
      mobileVersionMin: json['mobile_version_min'] as String,
      signature: json['signature'] as String,
      features: (json['features'] as List<dynamic>)
          .map((e) => Feature.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$FeatureManifestToJson(FeatureManifest instance) =>
    <String, dynamic>{
      'version': instance.version,
      'generated_at': instance.generatedAt.toIso8601String(),
      'mobile_version_min': instance.mobileVersionMin,
      'signature': instance.signature,
      'features': instance.features.map((e) => e.toJson()).toList(),
    };

ManifestDiff _$ManifestDiffFromJson(Map<String, dynamic> json) => ManifestDiff(
      newFeatures: (json['new_features'] as List<dynamic>)
          .map((e) => Feature.fromJson(e as Map<String, dynamic>))
          .toList(),
      removedFeatures: (json['removed_features'] as List<dynamic>)
          .map((e) => Feature.fromJson(e as Map<String, dynamic>))
          .toList(),
      modifiedFeatures: (json['modified_features'] as List<dynamic>)
          .map((e) => Feature.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$ManifestDiffToJson(ManifestDiff instance) =>
    <String, dynamic>{
      'new_features': instance.newFeatures.map((e) => e.toJson()).toList(),
      'removed_features':
          instance.removedFeatures.map((e) => e.toJson()).toList(),
      'modified_features':
          instance.modifiedFeatures.map((e) => e.toJson()).toList(),
    };
