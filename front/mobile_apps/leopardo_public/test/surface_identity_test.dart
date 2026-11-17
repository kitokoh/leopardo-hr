import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/features/surface/domain/surface_identity.dart';
import 'package:leopardo_public/features/surface/infrastructure/surface_theme.dart';

/// Tests de la feature `surface` — vitrines publiques verticales (#9577).
///
/// Ce que ces tests protègent :
/// - chaque verticale déclarée a une identité (aucun trou dans le registre) ;
/// - chaque identité a un domaine et un chemin **distincts** (deux vitrines ne
///   peuvent pas se retrouver sur le même hôte) ;
/// - une verticale inconnue est fail-closed (`null`), jamais une identité par
///   défaut ;
/// - le thème dérivé d'une identité porte bien sa couleur d'action.
void main() {
  group('SurfaceVertical', () {
    test('résout une verticale connue', () {
      expect(SurfaceVertical.fromCode('restaurant'), SurfaceVertical.restaurant);
      expect(SurfaceVertical.fromCode('pharmacy'), SurfaceVertical.pharmacy);
    });

    test('est fail-closed sur une verticale inconnue', () {
      expect(SurfaceVertical.fromCode('boulangerie'), isNull);
      expect(SurfaceVertical.fromCode(null), isNull);
    });

    test('couvre les six verticales du périmètre', () {
      expect(SurfaceVertical.values.length, 6);
    });
  });

  group('registre des identités', () {
    test('déclare une identité pour chaque verticale', () {
      for (final vertical in SurfaceVertical.values) {
        expect(
          surfaceIdentities[vertical],
          isNotNull,
          reason: 'verticale sans identité : ${vertical.code}',
        );
      }
    });

    test('donne à chaque verticale un domaine distinct', () {
      final domains = surfaceIdentities.values.map((i) => i.canonicalDomain).toList();

      expect(domains.toSet().length, domains.length);
    });

    test('donne à chaque verticale un chemin distinct', () {
      final paths = surfaceIdentities.values.map((i) => i.basePath).toList();

      expect(paths.toSet().length, paths.length);
    });

    test('donne à chaque verticale une couleur d’action distincte', () {
      final primaries = surfaceIdentities.values.map((i) => i.palette.primary).toList();

      expect(primaries.toSet().length, primaries.length);
    });

    test('surfaceIdentityFor est fail-closed', () {
      expect(surfaceIdentityFor(null), isNull);
      expect(surfaceIdentityFor(SurfaceVertical.travel), isNotNull);
    });
  });

  group('thème de surface', () {
    test('dérive le thème de l’identité de la verticale', () {
      final identity = surfaceIdentities[SurfaceVertical.pharmacy]!;
      final theme = surfaceTheme(identity);

      expect(theme.colorScheme.primary, identity.palette.primary);
      expect(theme.scaffoldBackgroundColor, identity.palette.surface);
    });

    test('readableOn choisit un texte lisible sur un fond sombre', () {
      final identity = surfaceIdentities[SurfaceVertical.trade]!;

      expect(readableOn(identity.palette.primary), identity.palette.onPrimary);
    });
  });
}
