import 'package:json_annotation/json_annotation.dart';
import 'feature.dart';

part 'feature_manifest.g.dart';

@JsonSerializable()
class FeatureManifest {
  final String version;
  @JsonKey(name: 'generated_at')
  final DateTime generatedAt;
  @JsonKey(name: 'mobile_version_min')
  final String mobileVersionMin;
  final String signature;
  final List<Feature> features;

  const FeatureManifest({
    required this.version,
    required this.generatedAt,
    required this.mobileVersionMin,
    required this.signature,
    required this.features,
  });

  factory FeatureManifest.fromJson(Map<String, dynamic> json) =>
      _$FeatureManifestFromJson(json);

  Map<String, dynamic> toJson() => _$FeatureManifestToJson(this);

  /// Valide la signature cryptographique du manifeste
  bool validateSignature(String publicKey) {
    // TODO: Implémenter la validation cryptographique
    // Cette méthode sera implémentée dans la phase sécurité
    return signature.isNotEmpty;
  }

  /// Filtre les fonctionnalités compatibles avec la version mobile donnée
  List<Feature> getCompatibleFeatures(String mobileVersion) {
    return features
        .where((feature) => feature.isCompatibleWith(mobileVersion))
        .toList();
  }

  /// Filtre les fonctionnalités selon les permissions de l'utilisateur
  List<Feature> getAuthorizedFeatures(List<String> userPermissions) {
    return features
        .where((feature) => feature.hasRequiredPermissions(userPermissions))
        .toList();
  }

  /// Obtient les fonctionnalités compatibles ET autorisées
  List<Feature> getAvailableFeatures(
    String mobileVersion,
    List<String> userPermissions,
  ) {
    return features
        .where((feature) =>
            feature.isCompatibleWith(mobileVersion) &&
            feature.hasRequiredPermissions(userPermissions))
        .toList();
  }

  /// Compare ce manifeste avec un autre pour identifier les différences
  ManifestDiff compareWith(FeatureManifest other) {
    final currentKeys = features.map((f) => f.key).toSet();
    final otherKeys = other.features.map((f) => f.key).toSet();

    final newFeatures =
        other.features.where((f) => !currentKeys.contains(f.key)).toList();

    final removedFeatures =
        features.where((f) => !otherKeys.contains(f.key)).toList();

    final modifiedFeatures = <Feature>[];
    for (final otherFeature in other.features) {
      final currentFeature =
          features.where((f) => f.key == otherFeature.key).firstOrNull;

      if (currentFeature != null && currentFeature != otherFeature) {
        modifiedFeatures.add(otherFeature);
      }
    }

    return ManifestDiff(
      newFeatures: newFeatures,
      removedFeatures: removedFeatures,
      modifiedFeatures: modifiedFeatures,
    );
  }

  /// Vérifie si ce manifeste est plus récent que l'autre
  bool isNewerThan(FeatureManifest other) {
    return generatedAt.isAfter(other.generatedAt);
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is FeatureManifest &&
          runtimeType == other.runtimeType &&
          version == other.version &&
          signature == other.signature;

  @override
  int get hashCode => version.hashCode ^ signature.hashCode;

  @override
  String toString() =>
      'FeatureManifest(version: $version, features: ${features.length})';
}

/// Représente les différences entre deux manifestes
@JsonSerializable()
class ManifestDiff {
  final List<Feature> newFeatures;
  final List<Feature> removedFeatures;
  final List<Feature> modifiedFeatures;

  const ManifestDiff({
    required this.newFeatures,
    required this.removedFeatures,
    required this.modifiedFeatures,
  });

  factory ManifestDiff.fromJson(Map<String, dynamic> json) =>
      _$ManifestDiffFromJson(json);

  Map<String, dynamic> toJson() => _$ManifestDiffToJson(this);

  /// Vérifie s'il y a des changements
  bool get hasChanges =>
      newFeatures.isNotEmpty ||
      removedFeatures.isNotEmpty ||
      modifiedFeatures.isNotEmpty;

  /// Nombre total de changements
  int get totalChanges =>
      newFeatures.length + removedFeatures.length + modifiedFeatures.length;

  @override
  String toString() => 'ManifestDiff(new: ${newFeatures.length}, '
      'removed: ${removedFeatures.length}, '
      'modified: ${modifiedFeatures.length})';
}

/// Extension pour ajouter firstOrNull si pas disponible
extension ListExtension<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
