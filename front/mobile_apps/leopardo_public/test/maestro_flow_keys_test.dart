import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_surface/leopardo_surface.dart';

/// Garde de cohérence entre les flows Maestro et les clés de test Dart.
///
/// ## Le défaut que ce test ferme
///
/// Un flow Maestro vise un élément par `id:`. Si la clé est renommée côté Dart
/// et pas côté YAML, **rien ne casse au moment du renommage** : le flow échoue
/// seulement plus tard, sur un émulateur, avec « element not found » — un
/// message qui ne dit pas que la cause est un renommage. Ce test transforme
/// cette panne silencieuse en échec immédiat, dans la suite `flutter test` que
/// la CI exécute déjà.
///
/// ## Ce qu'il vérifie
///
/// 1. Chaque `id:` d'un flow correspond à une clé **déclarée** dans
///    [RestaurantTestKeys] (ou à une clé paramétrée par variable, qui est
///    résolue à l'exécution).
/// 2. Les clés fixes du parcours sont bien présentes dans les flows — sinon un
///    flow pourrait « passer » en ne testant plus rien.
void main() {
  final flowsDir = Directory('maestro/restaurant');

  /// Clés fixes attendues dans au moins un flow.
  const requiredKeys = <String>[
    RestaurantTestKeys.menuSearch,
    RestaurantTestKeys.categoryAll,
    RestaurantTestKeys.goToCart,
    RestaurantTestKeys.customerName,
    RestaurantTestKeys.customerPhone,
    RestaurantTestKeys.submitOrder,
    RestaurantTestKeys.confirmation,
    RestaurantTestKeys.payAction,
    RestaurantTestKeys.track,
    RestaurantTestKeys.trackingStatus,
  ];

  /// Toutes les clés fixes déclarées, pour valider les `id:` des flows.
  final declaredKeys = <String>{
    ...requiredKeys,
    // Clés paramétrées : le préfixe est fixe, le suffixe vient d'une variable.
    'restaurant-product-add-',
    'restaurant-product-remove-',
    'restaurant-category-',
  };

  test('le dossier de flows Maestro existe et contient des flows', () {
    expect(
      flowsDir.existsSync(),
      isTrue,
      reason: 'maestro/restaurant/ doit exister (voir maestro/README.md)',
    );

    final flows = flowsDir
        .listSync()
        .whereType<File>()
        .where((f) => f.path.endsWith('.yaml'))
        .toList();

    expect(
      flows.length,
      greaterThanOrEqualTo(3),
      reason: 'le parcours est découpé en 3 flows (fiche, commande, paiement)',
    );
  });

  test('chaque id: des flows correspond à une clé déclarée', () {
    final flows = flowsDir
        .listSync()
        .whereType<File>()
        .where((f) => f.path.endsWith('.yaml'));

    final unknown = <String>[];

    for (final flow in flows) {
      for (final line in flow.readAsLinesSync()) {
        final match = RegExp(r'^\s*id:\s*(\S+)\s*$').firstMatch(line);
        if (match == null) continue;

        final id = match.group(1)!;
        // Une clé paramétrée (`...-${VAR || 'defaut'}`) : on ne valide que le
        // préfixe, le reste est résolu par Maestro à l'exécution.
        final isParameterized = id.contains(r'${');
        final prefix = isParameterized ? id.split(r'${').first : id;

        final known = declaredKeys.any(
          (key) => isParameterized ? prefix.startsWith(key) : key == id,
        );

        if (!known) {
          unknown.add('${flow.path}: $id');
        }
      }
    }

    expect(
      unknown,
      isEmpty,
      reason:
          'Ces ids de flow ne correspondent à aucune clé de RestaurantTestKeys. '
          'Une clé renommée côté Dart casse le flow silencieusement :\n'
          '${unknown.join('\n')}',
    );
  });

  test('les clés du parcours nominal sont réellement exercées', () {
    final allFlows = flowsDir
        .listSync()
        .whereType<File>()
        .where((f) => f.path.endsWith('.yaml'))
        .map((f) => f.readAsStringSync())
        .join('\n');

    final missing =
        requiredKeys.where((key) => !allFlows.contains(key)).toList();

    expect(
      missing,
      isEmpty,
      reason:
          'Ces clés ne sont visées par aucun flow : le parcours ne les teste '
          'plus. Soit un flow a été supprimé, soit une étape a été retirée :\n'
          '${missing.join('\n')}',
    );
  });

  test('chaque flow déclare le bon appId', () {
    // `com.leopardo.public` n'existe pas : l'applicationId réel est
    // `com.leopardo.publicapp` (android/app/build.gradle). Un appId faux fait
    // échouer `launchApp` sur « app not installed » — un message qui ne dit
    // pas que la cause est une faute de frappe dans le YAML.
    final flows = flowsDir
        .listSync()
        .whereType<File>()
        .where((f) => f.path.endsWith('.yaml'));

    for (final flow in flows) {
      expect(
        flow.readAsStringSync(),
        contains('appId: com.leopardo.publicapp'),
        reason: '${flow.path} ne déclare pas `appId: com.leopardo.publicapp`. '
            'L\'applicationId réel est dans android/app/build.gradle.',
      );
    }
  });

  test('chaque flow ouvre la fiche par le deep link déclaré', () {
    // Le manifest déclare `leopardo://public` (AndroidManifest.xml). Un flow
    // qui ouvrirait `leopardo://restaurants/...` ne résoudrait aucun
    // intent-filter : l'app ne se lancerait pas du tout.
    final flows = flowsDir
        .listSync()
        .whereType<File>()
        .where((f) => f.path.endsWith('.yaml'));

    for (final flow in flows) {
      final content = flow.readAsStringSync();
      expect(
        content,
        contains('openLink: leopardo://public/'),
        reason:
            '${flow.path} n\'ouvre pas la fiche par `leopardo://public/...`. '
            'C\'est le seul scheme/host déclaré dans AndroidManifest.xml.',
      );
    }
  });

  test('aucun flow ne sélectionne un élément par texte traduit', () {
    // Un `tapOn: "Valider"` casse dès qu'on change de locale ou qu'on retouche
    // un libellé. Les flows doivent viser des `id:`.
    final flows = flowsDir
        .listSync()
        .whereType<File>()
        .where((f) => f.path.endsWith('.yaml'));

    final offenders = <String>[];

    for (final flow in flows) {
      final lines = flow.readAsLinesSync();
      for (var i = 0; i < lines.length; i++) {
        final line = lines[i];
        // `tapOn:` suivi d'une chaîne littérale (pas `id:`).
        if (RegExp(r'^\s*-?\s*tapOn:\s*["\x27]').hasMatch(line)) {
          offenders.add('${flow.path}:${i + 1} → ${line.trim()}');
        }
      }
    }

    expect(
      offenders,
      isEmpty,
      reason: 'Ces taps visent un texte littéral au lieu d\'un id stable :\n'
          '${offenders.join('\n')}',
    );
  });
}
