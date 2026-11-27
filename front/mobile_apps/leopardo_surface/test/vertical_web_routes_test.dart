import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_surface/src/features/journeys/domain/journey_registry.dart';

/// Parité web ↔ mobile des verticales boutique, séjour et billets (#9997-#9999).
///
/// ## Le défaut que ces tests ferment
///
/// La verticale restaurant avait une URL publique (`/restaurants/{slug}`) et
/// les trois autres non : elles n'étaient atteignables que par
/// `/journey/order/:slug`, `/journey/stay/:slug` et `/journey/travel?trip=`.
/// Un lien partagé depuis la vitrine web (`/boutiques/x`, `/stay/x`,
/// `/billets/42`) tombait donc sur le 404 de l'app.
///
/// Ce n'est pas un défaut de mise en page : c'est un défaut de **contrat
/// d'URL**. Il ne se voit ni en `flutter analyze` ni dans un test widget qui
/// monte l'écran directement — il faut lire le routeur. Ces tests le lisent.
///
/// ## Pourquoi lire le source plutôt que monter le routeur
///
/// `appRouterProvider` construit un `GoRouter` complet (4 branches, 15 routes,
/// providers Riverpod) : le monter dans un test unitaire demanderait de
/// simuler toute la coque. Lire `surface_router.dart` vérifie exactement ce qui
/// compte ici — la présence et la cible de chaque route — sans cette
/// dépendance. Le test échoue si le fichier disparaît, donc il ne peut pas
/// passer silencieusement.
void main() {
  final shell = File('lib/src/core/navigation/surface_router.dart');

  late String source;

  setUpAll(() {
    expect(
      shell.existsSync(),
      isTrue,
      reason: 'surface_router.dart doit exister : c\'est le routeur de l\'app',
    );
    source = shell.readAsStringSync();
  });

  /// Vérifie qu'une route est déclarée ET qu'elle construit l'écran attendu.
  void expectRoute(String path, String screen) {
    expect(
      source.contains("path: '$path'"),
      isTrue,
      reason:
          'La route publique `$path` est absente de surface_router.dart. Sans elle, '
          'un lien partagé depuis la vitrine web tombe sur le 404 de l\'app.',
    );

    // L'écran doit être construit dans les ~400 caractères qui suivent la
    // déclaration de la route : assez pour couvrir le `builder:`, pas assez
    // pour attraper la route suivante.
    final index = source.indexOf("path: '$path'");
    final window = source.substring(
      index,
      (index + 400).clamp(0, source.length),
    );

    expect(
      window.contains(screen),
      isTrue,
      reason:
          'La route `$path` ne construit pas `$screen`. Une route qui pointe '
          'sur le mauvais écran est pire qu\'une route absente : elle répond '
          '200 avec le contenu d\'une autre verticale.',
    );
  }

  group('verticale boutique (#9997)', () {
    test('URL publique /boutiques/:slug → RetailOrderScreen', () {
      expectRoute('/boutiques/:slug', 'RetailOrderScreen');
    });

    test('la route interne /journey/order/:slug est conservée', () {
      expect(
        source.contains("path: '/journey/order/:slug'"),
        isTrue,
        reason:
            'La convention interne du dépôt doit survivre à l\'ajout de l\'URL '
            'publique : des liens existants pointent dessus.',
      );
    });
  });

  group('verticale séjour (#9998)', () {
    test('URL publique /stay/:slug → StayReservationScreen', () {
      expectRoute('/stay/:slug', 'StayReservationScreen');
    });

    test('la route interne /journey/stay/:slug est conservée', () {
      expect(
        source.contains("path: '/journey/stay/:slug'"),
        isTrue,
        reason:
            'La convention interne doit survivre à l\'ajout de l\'URL publique.',
      );
    });
  });

  group('verticale billets (#9999)', () {
    test('URL publique /billets → TravelJourneyScreen', () {
      expectRoute('/billets', 'TravelJourneyScreen');
    });

    test('URL publique /billets/:id → TravelJourneyScreen avec le trajet', () {
      expect(
        source.contains("path: ':id'"),
        isTrue,
        reason: 'La fiche trajet doit être une route ENFANT de /billets, sinon '
            '`/billets/42` n\'est pas une URL canonique partageable.',
      );

      final index = source.indexOf("path: ':id'");
      final window = source.substring(
        index,
        (index + 400).clamp(0, source.length),
      );

      expect(
        window.contains('TravelJourneyScreen'),
        isTrue,
        reason: 'La route /billets/:id doit construire TravelJourneyScreen.',
      );
      expect(
        window.contains("pathParameters['id']"),
        isTrue,
        reason: 'L\'identifiant doit venir du CHEMIN, pas d\'un query param : '
            '`?trip=42` n\'est pas une URL canonique indexable.',
      );
    });

    test('la route interne /journey/travel est conservée', () {
      expect(
        source.contains("path: '/journey/travel'"),
        isTrue,
        reason:
            'La convention interne doit survivre à l\'ajout de l\'URL publique.',
      );
    });
  });

  group('cohérence avec le registre des verticales', () {
    test('chaque verticale à parcours natif a une URL publique', () {
      // Le registre déclare `counterpartPath` — la contrepartie web. C'est la
      // source de vérité : si une verticale y annonce `/boutiques` mais que le
      // routeur ne sert pas `/boutiques/:slug`, la cartographie ment.
      const publicRoutes = <String, String>{
        'restaurant': '/restaurants/:slug',
        'retail': '/boutiques/:slug',
        'hospitality': '/stay/:slug',
        'travel': '/billets',
      };

      for (final entry in publicRoutes.entries) {
        final vertical = journeyByCode(entry.key);
        expect(
          vertical,
          isNotNull,
          reason: 'La verticale `${entry.key}` doit exister dans le registre.',
        );

        expect(
          source.contains("path: '${entry.value}'"),
          isTrue,
          reason: 'La verticale `${entry.key}` annonce la contrepartie web '
              '`${vertical!.counterpartPath}` mais le routeur ne sert pas '
              '`${entry.value}`.',
        );
      }
    });

    test('les verticales à parcours natif sont bien order/booking', () {
      // Une URL publique n'a de sens que si le parcours derrière est complet.
      // Exposer `/boutiques/{slug}` sur une verticale `blocked` promettrait
      // une commande que le serveur refuserait.
      for (final code in ['restaurant', 'retail', 'hospitality', 'travel']) {
        final vertical = journeyByCode(code)!;
        expect(
          vertical.mode,
          anyOf(JourneyMode.order, JourneyMode.booking),
          reason:
              'La verticale `$code` expose une URL publique mais son mode est '
              '`${vertical.mode}` : le parcours n\'est pas complet.',
        );
      }
    });
  });
}
