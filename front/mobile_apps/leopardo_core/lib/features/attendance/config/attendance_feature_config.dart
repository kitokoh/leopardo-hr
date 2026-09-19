import 'package:flutter_riverpod/flutter_riverpod.dart';

/// #7652 — configuration par app/rôle de la feature attendance partagée.
///
/// La version canonique du provider attendance vit dans `leopardo_core` ; les
/// différences légitimes entre apps (ton du message hors-zone employé vs
/// manager/RH, etc.) passent par cette config surchargée dans le `main.dart`
/// de chaque app via `ProviderScope(overrides: [...])` — plus AUCUN fork de
/// fichier dans `apps/*/lib/features/attendance`.
class AttendanceFeatureConfig {
  const AttendanceFeatureConfig({this.outsideZoneManagerTone = true});

  /// `true` (manager/RH, défaut) : un pointage hors zone affiche le message
  /// orienté supervision (`attendanceOutsideZoneManagerNotice`).
  /// `false` (employé) : message self-service
  /// (`attendanceOutsideZoneNotice`).
  final bool outsideZoneManagerTone;
}

/// Défaut : profil manager/RH (comportement historique de `leopardo_core`).
/// `leopardo_employee` surcharge ce provider dans son `main.dart`.
final attendanceFeatureConfigProvider = Provider<AttendanceFeatureConfig>(
  (ref) => const AttendanceFeatureConfig(),
);
