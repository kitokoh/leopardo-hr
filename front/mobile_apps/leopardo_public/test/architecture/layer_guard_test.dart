import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Garde de couche — `domain/` est pur.
///
/// ## Pourquoi un test et pas une convention
///
/// ADR-0035 fait de la découpe en couches la norme des apps mobiles grand
/// public. Une règle d'architecture qu'aucun test ne vérifie est un
/// commentaire : elle survit tant que personne ne l'enfreint, et meurt au
/// premier correctif pressé.
///
/// Côté web, `public-surfaces/__tests__/layer-guard.test.ts` joue ce rôle.
/// Ce fichier est son équivalent mobile, et il est **vérifié en régression** :
/// réintroduire un import interdit dans un `domain/` fait échouer la suite.
///
/// ## Ce que la garde refuse
///
/// Un fichier de `domain/` ne peut importer **aucun** `package:` — ni
/// framework (`material_ui`, `flutter`), ni état (`flutter_riverpod`), ni
/// réseau (`dio`), ni UI partagée (`leopardo_core/core/theme`,
/// `.../core/widgets`). Seul `dart:` (le SDK) est autorisé.
///
/// Il ne peut pas non plus importer une couche supérieure du même feature
/// (`application/`, `infrastructure/`, `components/`, `screens/`, `widgets/`,
/// `data/`, `models/`, `providers/`).
void main() {
  final libDir = Directory('lib');

  /// Tous les fichiers `.dart` sous `lib/features/**/domain/`.
  List<File> domainFiles() {
    if (!libDir.existsSync()) {
      return const [];
    }

    return libDir
        .listSync(recursive: true)
        .whereType<File>()
        .where((f) => f.path.endsWith('.dart'))
        .where((f) {
          final normalized = f.path.replaceAll(r'\', '/');
          return normalized.contains('/domain/');
        })
        .toList()
      ..sort((a, b) => a.path.compareTo(b.path));
  }

  /// Spécificateurs importés par un fichier Dart.
  List<String> importsOf(String source) {
    final specifiers = <String>[];
    final pattern = RegExp(
      r'''^\s*(?:import|export)\s+['"]([^'"]+)['"]''',
      multiLine: true,
    );

    for (final match in pattern.allMatches(source)) {
      specifiers.add(match.group(1)!);
    }

    return specifiers;
  }

  /// Couches qui ne doivent jamais être importées depuis `domain/`.
  const forbiddenLayers = <String>[
    '/application/',
    '/infrastructure/',
    '/components/',
    '/screens/',
    '/widgets/',
    '/data/',
    '/models/',
    '/providers/',
  ];

  group('garde de couche — domain/ est pur', () {
    test('trouve au moins un fichier de domaine', () {
      // Sans cette assertion, un renommage de dossier rendrait la garde
      // silencieusement vide : elle passerait au vert en ne vérifiant rien.
      expect(
        domainFiles(),
        isNotEmpty,
        reason:
            'aucun fichier trouvé sous lib/features/**/domain/ — la garde '
            'ne vérifierait rien',
      );
    });

    test('aucun fichier de domaine n\'importe un package', () {
      final violations = <String>[];

      for (final file in domainFiles()) {
        final source = file.readAsStringSync();

        for (final specifier in importsOf(source)) {
          if (specifier.startsWith('package:')) {
            violations.add(
              '${file.path} → $specifier (package interdit dans '
              'domain/)',
            );
          }
        }
      }

      expect(violations, isEmpty, reason: violations.join('\n'));
    });

    test('aucun fichier de domaine n\'importe une couche supérieure', () {
      final violations = <String>[];

      for (final file in domainFiles()) {
        final source = file.readAsStringSync();

        for (final specifier in importsOf(source)) {
          if (!specifier.startsWith('.')) {
            continue;
          }

          final normalized = specifier.replaceAll(r'\', '/');

          for (final layer in forbiddenLayers) {
            if (normalized.contains(layer)) {
              violations.add(
                '${file.path} → $specifier (couche $layer '
                'importée depuis domain/)',
              );
            }
          }
        }
      }

      expect(violations, isEmpty, reason: violations.join('\n'));
    });

    test('la garde détecte réellement une violation (auto-test)', () {
      // Preuve que la garde n'est pas un test vide : on lui soumet une source
      // fautive et on vérifie qu'elle la signale. Sans ce test, une regex
      // cassée passerait au vert pour toujours.
      const faulty = '''
import 'package:material_ui/material_ui.dart';
import '../infrastructure/surface_theme.dart';
''';

      final specifiers = importsOf(faulty);

      expect(specifiers, contains('package:material_ui/material_ui.dart'));
      expect(specifiers.any((s) => s.contains('/infrastructure/')), isTrue);
    });
  });
}
