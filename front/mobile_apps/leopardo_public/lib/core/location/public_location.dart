import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

import '../i18n/app_strings.dart';

/// Fournisseur de position, avec CONSENTEMENT explicite.
///
/// ## Trois règles non négociables
///
/// 1. **Rien n'est demandé au démarrage.** Ouvrir l'app ne déclenche aucune
///    demande de permission : un dialogue de localisation à la première frame
///    est le meilleur moyen de faire refuser pour toujours. La position n'est
///    demandée qu'après un geste (« Utiliser ma position »).
///
/// 2. **Approximative par défaut.** On demande `LocationAccuracy.low` : pour
///    afficher « les commerçants à moins de 10 km », le GPS précis est
///    inutilement intrusif. La position précise n'est jamais nécessaire ici.
///
/// 3. **Jamais conservée.** Aucune écriture disque, aucun envoi hors de la
///    requête de recherche. Fermer l'app efface la position de la mémoire.
///    Le cahier des charges l'exige et c'est aussi ce qui rend le refus
///    indolore : refuser ne coûte rien.
enum LocationOutcome {
  /// Position obtenue.
  granted,

  /// La personne a refusé la permission (décision légitime, pas une erreur).
  denied,

  /// Refusée définitivement : l'app doit proposer la ville manuelle, jamais
  /// renvoyer vers les réglages en boucle.
  deniedForever,

  /// Service de localisation désactivé au niveau du téléphone.
  serviceDisabled,
}

class LocationResult {
  const LocationResult(this.outcome, {this.position});

  final LocationOutcome outcome;
  final Position? position;

  /// `"lat,lng"` au format attendu par `?near=` — 5 décimales suffisent
  /// (~1 m) et évitent d'envoyer une précision qu'on n'a pas.
  String? get nearParam => position == null
      ? null
      : '${position!.latitude.toStringAsFixed(5)},${position!.longitude.toStringAsFixed(5)}';
}

class LocationService {
  /// Demande la position SI elle est déjà autorisée ; sinon retourne l'état
  /// du refus SANS ouvrir de dialogue.
  ///
  /// Séparer « vérifier » de « demander » permet à l'écran d'afficher d'abord
  /// un bouton explicite « Utiliser ma position » : le dialogue système
  /// n'apparaît qu'après un geste volontaire.
  Future<LocationOutcome> check() async {
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) return LocationOutcome.serviceDisabled;

    final permission = await Geolocator.checkPermission();
    return _map(permission);
  }

  /// Ouvre le dialogue système (appelée sur geste explicite uniquement).
  Future<LocationResult> request() async {
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      return const LocationResult(LocationOutcome.serviceDisabled);
    }

    final permission = await Geolocator.requestPermission();
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      return LocationResult(_map(permission));
    }

    try {
      final position = await Geolocator.getCurrentPosition(
        // `low` = position approximative : suffisant pour « près de toi » et
        // nettement moins intrusif qu'un GPS haute précision.
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.low,
          timeLimit: Duration(seconds: 12),
        ),
      );
      return LocationResult(LocationOutcome.granted, position: position);
    } catch (_) {
      // Timeout ou échec matériel : traité comme « refusée » du point de vue
      // de l'interface — on propose la ville manuelle. Surtout pas de position
      // inventée.
      return const LocationResult(LocationOutcome.denied);
    }
  }

  LocationOutcome _map(LocationPermission permission) {
    return switch (permission) {
      LocationPermission.always ||
      LocationPermission.whileInUse =>
        LocationOutcome.granted,
      LocationPermission.deniedForever => LocationOutcome.deniedForever,
      LocationPermission.denied => LocationOutcome.denied,
      // `unableToDetermine` (plateforme sans service) : impossible de savoir,
      // on traite comme un refus — fail-closed, aucune position supposée.
      LocationPermission.unableToDetermine => LocationOutcome.denied,
    };
  }
}

final locationServiceProvider =
    Provider<LocationService>((ref) => LocationService());

/// Villes proposées pour le choix manuel.
///
/// Ce n'est PAS une liste de villes « supportées » (elle n'existe pas côté
/// serveur) : c'est un secours lisible quand la position est refusée. La ville
/// est envoyée telle quelle comme filtre `?city=`, que le serveur applique en
/// insensible à la casse. La ville pilote peut être configurée au build.
class KnownCities {
  static const String pilotCity = String.fromEnvironment(
    'PILOT_CITY',
    defaultValue: '',
  );

  /// Retourne la ville pilote si elle est fournie au build, sinon vide.
  ///
  /// Pas de liste en dur : une liste inventée de villes créerait des filtres
  /// qui ne renvoient jamais rien, et ferait croire que l'app « couvre » des
  /// villes qui n'ont aucune Page publiée.
  static List<String> get suggestions =>
      pilotCity.isEmpty ? const [] : [pilotCity];
}

/// Écran de choix de ville — utilisé pour le refus de localisation ET pour le
/// changement manuel.
class CityPickerSheet extends StatefulWidget {
  const CityPickerSheet({
    super.key,
    required this.strings,
    required this.currentCity,
  });

  final AppStrings strings;
  final String? currentCity;

  @override
  State<CityPickerSheet> createState() => _CityPickerSheetState();
}

class _CityPickerSheetState extends State<CityPickerSheet> {
  late final TextEditingController _controller =
      TextEditingController(text: widget.currentCity ?? '');

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final suggestions = KnownCities.suggestions;

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.viewInsetsOf(context).bottom + 16,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              widget.strings.t('discoveryCityLabel'),
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _controller,
              autofocus: true,
              textInputAction: TextInputAction.done,
              decoration: InputDecoration(
                hintText: widget.strings.t('discoveryCityHint'),
                border: const OutlineInputBorder(),
              ),
              onSubmitted: (value) => Navigator.of(context).pop(value.trim()),
            ),
            if (suggestions.isNotEmpty) ...[
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                children: [
                  for (final city in suggestions)
                    ActionChip(
                      label: Text(city),
                      onPressed: () => Navigator.of(context).pop(city),
                    ),
                ],
              ),
            ],
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.of(context).pop(),
                    child: Text(widget.strings.t('commonCancel')),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton(
                    onPressed: () =>
                        Navigator.of(context).pop(_controller.text.trim()),
                    child: Text(widget.strings.t('commonBack')),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
