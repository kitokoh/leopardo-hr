import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Garde de navigation de l'app Marketing (#9963).
///
/// ## Le défaut que ce test empêche de revenir
///
/// `social_posts_screen.dart` poussait `/post/${post.id}` alors qu'aucune
/// route `/post/:id` n'était enregistrée dans le GoRouter de `main.dart`.
/// C'est le défaut #3293 sous sa forme exacte : une cible de navigation
/// déclarée dans l'UI mais absente du routeur. Au tap, GoRouter lève une
/// `GoException` et l'écran reste figé — sans qu'aucun test ne le voie,
/// puisque l'app compile et s'analyse parfaitement.
///
/// ## Pourquoi une analyse statique et pas un test de widget
///
/// Un test de widget ne couvrirait que les cibles qu'on pense à tester. Ici
/// on veut l'invariant **complet** : *toute* chaîne littérale poussée par
/// `context.push(...)` ou `context.go(...)` doit correspondre à une route
/// déclarée. L'analyse du source couvre les écrans présents et futurs sans
/// qu'il faille y penser.
void main() {
  final libDir = Directory('lib');

  /// Routes déclarées dans le routeur (`GoRoute(path: '...')`).
  Set<String> declaredRoutes() {
    final mainDart = File('lib/main.dart');
    expect(
      mainDart.existsSync(),
      isTrue,
      reason: 'lib/main.dart introuvable — le test doit tourner depuis la '
          'racine du package leopardo_marketing.',
    );
    final source = mainDart.readAsStringSync();
    final matches =
        RegExp(r"""GoRoute\(\s*path:\s*'([^']+)'""").allMatches(source);
    return matches.map((m) => m.group(1)!).toSet();
  }

  /// Cibles poussées par l'UI (`context.push('...')` / `context.go('...')`).
  Map<String, String> pushedTargets() {
    final targets = <String, String>{};
    final pattern = RegExp(r"""context\.(?:push|go)\(\s*'([^']+)'""");
    for (final entity in libDir.listSync(recursive: true)) {
      if (entity is! File || !entity.path.endsWith('.dart')) continue;
      final source = entity.readAsStringSync();
      for (final match in pattern.allMatches(source)) {
        targets[match.group(1)!] = entity.path;
      }
    }
    return targets;
  }

  /// Une cible poussée correspond-elle à une route déclarée ?
  ///
  /// Les paramètres de chemin (`:id`) sont comparés segment par segment :
  /// `/post/42` correspond à `/post/:id`, `/posts` à `/posts`.
  bool matchesDeclared(String target, Set<String> routes) {
    final targetSegments = target.split('/');
    for (final route in routes) {
      final routeSegments = route.split('/');
      if (routeSegments.length != targetSegments.length) continue;
      var ok = true;
      for (var i = 0; i < routeSegments.length; i++) {
        final r = routeSegments[i];
        if (r.startsWith(':')) continue; // paramètre : accepte n'importe quoi
        if (r != targetSegments[i]) {
          ok = false;
          break;
        }
      }
      if (ok) return true;
    }
    return false;
  }

  test('le routeur déclare les 4 écrans de l app', () {
    final routes = declaredRoutes();
    expect(routes, contains('/'));
    expect(routes, contains('/login'));
    expect(routes, contains('/posts'));
    expect(routes, contains('/create-post'));
  });

  test('la route de détail /post/:id est déclarée (#9963)', () {
    // Régression directe : la carte de liste poussait /post/{id} sans route.
    expect(declaredRoutes(), contains('/post/:id'));
  });

  test('toute cible poussée par l UI a une route déclarée', () {
    final routes = declaredRoutes();
    final targets = pushedTargets();

    expect(
      targets,
      isNotEmpty,
      reason: 'Aucune cible de navigation trouvée — le motif de recherche a '
          'probablement changé, et ce test ne vérifierait plus rien.',
    );

    final orphans = <String>[];
    targets.forEach((target, file) {
      if (!matchesDeclared(target, routes)) {
        orphans.add('$target  (poussé depuis $file)');
      }
    });

    expect(
      orphans,
      isEmpty,
      reason: 'Cibles de navigation sans route déclarée dans lib/main.dart '
          '(défaut #3293) :\n  ${orphans.join('\n  ')}',
    );
  });
}
