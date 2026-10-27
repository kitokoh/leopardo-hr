import 'package:flutter/material.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_manager/features/fuel_station/data/fuel_station_repository.dart';

/// Rendu texte des entités FuelStation (#8585).
///
/// Ces libellés vivaient dans les DTO (`FuelStationDto.label`), c'est-à-dire
/// dans la couche données : n'importe quel appelant pouvait donc afficher un
/// texte utilisateur construit hors du catalogue i18n — refusé par
/// PA2-I18N-014. Les remonter ici garde la règle structurelle : un DTO ne sait
/// pas parler, seule la vue parle, et elle parle via `l10n`.
extension FuelStationLabels on BuildContext {
  /// Nom de station, à défaut son code, à défaut un identifiant technique.
  String fuelStationLabel(FuelStationDto station) {
    final name = station.name;
    if (name != null && name.trim().isNotEmpty) return name;
    final code = station.code;
    if (code != null && code.trim().isNotEmpty) return code;
    return l10n.fuelTechnicalRef(station.id);
  }

  /// Code de pompe, à défaut un identifiant technique.
  String fuelPumpLabel(FuelPumpDto pump) {
    final code = pump.code;
    if (code != null && code.trim().isNotEmpty) return code;
    return l10n.fuelTechnicalRef(pump.id);
  }

  /// Code de compteur, suffixé du produit mesuré quand il est connu.
  String fuelMeterLabel(FuelMeterDto meter) {
    final code = meter.meterCode;
    final base = (code != null && code.trim().isNotEmpty)
        ? code
        : l10n.fuelTechnicalRef(meter.id);
    final product = meter.productCode;
    if (product == null || product.trim().isEmpty) return base;

    // Le séparateur est un texte : il passe par le catalogue comme le reste.
    return l10n.fuelMeterLabelWithProduct(base, product);
  }
}
