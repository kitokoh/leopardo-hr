import 'package:json_annotation/json_annotation.dart';
import 'form_schema.dart';
import 'list_schema.dart';
import 'detail_schema.dart';

part 'feature.g.dart';

@JsonSerializable()
class Feature {
  final String key;
  final String title;
  final String description;
  final String endpoint;
  final List<String> methods;
  final Map<String, dynamic> parameters;
  @JsonKey(name: 'response_schema')
  final Map<String, dynamic> responseSchema;
  final List<String> permissions;
  @JsonKey(name: 'mobile_version_min')
  final String minimumMobileVersion;
  @JsonKey(name: 'mobile_version_max')
  final String? maximumMobileVersion;
  @JsonKey(name: 'ui_type')
  final FeatureType type;
  @JsonKey(name: 'form_schema')
  final FormSchema? formSchema;
  @JsonKey(name: 'list_schema')
  final ListSchema? listSchema;
  @JsonKey(name: 'detail_schema')
  final DetailSchema? detailSchema;

  const Feature({
    required this.key,
    required this.title,
    required this.description,
    required this.endpoint,
    required this.methods,
    required this.parameters,
    required this.responseSchema,
    required this.permissions,
    required this.minimumMobileVersion,
    this.maximumMobileVersion,
    required this.type,
    this.formSchema,
    this.listSchema,
    this.detailSchema,
  });

  factory Feature.fromJson(Map<String, dynamic> json) => _$FeatureFromJson(json);

  Map<String, dynamic> toJson() => _$FeatureToJson(this);

  /// Vérifie si cette fonctionnalité est compatible avec la version mobile donnée
  bool isCompatibleWith(String mobileVersion) {
    final current = Version.parse(mobileVersion);
    final min = Version.parse(minimumMobileVersion);
    final max = maximumMobileVersion != null
        ? Version.parse(maximumMobileVersion!)
        : null;

    return current >= min && (max == null || current <= max);
  }

  /// Vérifie si l'utilisateur a les permissions requises pour cette fonctionnalité
  bool hasRequiredPermissions(List<String> userPermissions) {
    return permissions.every((permission) => userPermissions.contains(permission));
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is Feature &&
          runtimeType == other.runtimeType &&
          key == other.key;

  @override
  int get hashCode => key.hashCode;

  @override
  String toString() => 'Feature(key: $key, title: $title, type: $type)';
}

@JsonEnum()
enum FeatureType {
  @JsonValue('list')
  list,
  @JsonValue('form')
  form,
  @JsonValue('detail')
  detail,
  @JsonValue('dashboard')
  dashboard,
  @JsonValue('generic')
  generic;

  static FeatureType fromString(String type) {
    return FeatureType.values.firstWhere(
      (e) => e.name == type,
      orElse: () => FeatureType.generic,
    );
  }
}

/// Classe utilitaire pour la comparaison sémantique des versions
class Version implements Comparable<Version> {
  final int major;
  final int minor;
  final int patch;

  const Version(this.major, this.minor, this.patch);

  factory Version.parse(String version) {
    final parts = version.split('.');
    if (parts.length < 2) {
      throw ArgumentError('Version invalide: $version');
    }

    final major = int.parse(parts[0]);
    final minor = int.parse(parts[1]);
    final patch = parts.length > 2 ? int.parse(parts[2]) : 0;

    return Version(major, minor, patch);
  }

  @override
  int compareTo(Version other) {
    if (major != other.major) return major.compareTo(other.major);
    if (minor != other.minor) return minor.compareTo(other.minor);
    return patch.compareTo(other.patch);
  }

  bool operator >(Version other) => compareTo(other) > 0;
  bool operator >=(Version other) => compareTo(other) >= 0;
  bool operator <(Version other) => compareTo(other) < 0;
  bool operator <=(Version other) => compareTo(other) <= 0;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is Version &&
          runtimeType == other.runtimeType &&
          major == other.major &&
          minor == other.minor &&
          patch == other.patch;

  @override
  int get hashCode => major.hashCode ^ minor.hashCode ^ patch.hashCode;

  @override
  String toString() => '$major.$minor.$patch';
}