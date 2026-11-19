import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/features/surface/domain/surface_identity.dart';
import 'package:leopardo_public/features/surface/infrastructure/surface_palette_tokens.dart';
import 'package:leopardo_public/features/surface/infrastructure/surface_theme.dart';

/// Tests de la feature `surface` — vitrines publiques verticales.
///
/// Ce que ces tests protègent :
/// - chaque verticale déclarée a une identité (aucun trou dans le registre) ;
/// - chaque identité a un domaine et un chemin **distincts** ;
/// - une verticale inconnue est fail-closed (`null`), jamais un défaut ;
/// - une identité invalide **fait échouer la construction** du registre ;
/// - le thème dérivé d'une identité porte bien sa couleur d'action.
void main() {
  group('SurfaceVertical', () {
    test('résout une verticale connue', () {
      expect(
        SurfaceVertical.fromCode('restaurant'),
        SurfaceVertical.restaurant,
      );
      expect(SurfaceVertical.fromCode('pharmacy'), SurfaceVertical.pharmacy);
      expect(SurfaceVertical.fromCode('careers'), SurfaceVertical.careers);
    });

    test('est fail-closed sur une verticale inconnue', () {
      expect(SurfaceVertical.fromCode('boulangerie'), isNull);
      expect(SurfaceVertical.fromCode(null), isNull);
    });

    test('couvre les huit verticales du périmètre', () {
      expect(SurfaceVertical.values.length, 8);
    });

    test('résout le code API `hospitality_property` vers hospitality', () {
      // L'API de découverte émet `hospitality_property`, le registre web nomme
      // la verticale `hospitality`. Sans cette résolution, la fiche d'un hôtel
      // ne porterait jamais son identité — défaut invisible en test unitaire
      // naïf, visible en production sur une seule verticale.
      expect(
        SurfaceVertical.fromCode('hospitality_property'),
        SurfaceVertical.hospitality,
      );
      expect(
        SurfaceVertical.fromCode('hospitality'),
        SurfaceVertical.hospitality,
      );
    });

    test('chaque verticale déclare son propre code en premier', () {
      for (final vertical in SurfaceVertical.values) {
        expect(
          vertical.apiTypeCodes.first,
          vertical.code,
          reason:
              'le premier code API de ${vertical.code} doit être son code '
              'canonique',
        );
      }
    });

    test('les chemins sont alignés sur le registre web', () {
      // Source de vérité : front/web/src/modules/public-surfaces/*/identity/.
      // Une vitrine mobile et sa vitrine web ne peuvent pas se retrouver sur
      // deux chemins différents.
      const expected = {
        SurfaceVertical.restaurant: '/restaurants',
        SurfaceVertical.retail: '/boutiques',
        SurfaceVertical.hospitality: '/stay',
        SurfaceVertical.trade: '/entreprises',
        SurfaceVertical.travel: '/billets',
        SurfaceVertical.pharmacy: '/pharmacies',
        SurfaceVertical.vtc: '/operateurs',
        SurfaceVertical.careers: '/emploi',
      };

      for (final entry in expected.entries) {
        expect(entry.key.basePath, entry.value);
      }
    });

    test('chaque verticale a un code et un chemin distincts', () {
      final codes = SurfaceVertical.values.map((v) => v.code).toList();
      final paths = SurfaceVertical.values.map((v) => v.basePath).toList();

      expect(codes.toSet().length, codes.length);
      expect(paths.toSet().length, paths.length);
    });
  });

  group('registre des identités', () {
    test('déclare une identité pour chaque verticale', () {
      for (final vertical in SurfaceVertical.values) {
        expect(
          surfaceRegistry.get(vertical),
          isNotNull,
          reason: 'verticale sans identité : ${vertical.code}',
        );
      }
    });

    test('donne à chaque verticale un domaine distinct', () {
      final domains = surfaceRegistry
          .all()
          .map((i) => i.canonicalDomain)
          .toList();

      expect(domains.toSet().length, domains.length);
    });

    test('donne à chaque verticale un chemin distinct', () {
      final paths = surfaceRegistry.all().map((i) => i.basePath).toList();

      expect(paths.toSet().length, paths.length);
    });

    test('donne à chaque verticale une couleur d’action distincte', () {
      final primaries = surfaceRegistry
          .all()
          .map((i) => i.palette.primary)
          .toList();

      expect(primaries.toSet().length, primaries.length);
    });

    test('surfaceIdentityFor est fail-closed', () {
      expect(surfaceIdentityFor(null), isNull);
      expect(surfaceIdentityFor(SurfaceVertical.travel), isNotNull);
    });

    test('has() et verticals() reflètent le registre', () {
      expect(surfaceRegistry.has(SurfaceVertical.careers), isTrue);
      expect(surfaceRegistry.verticals().length, SurfaceVertical.values.length);
    });
  });

  group('validation à la construction', () {
    SurfaceIdentity valid() => SurfaceIdentity(
      vertical: SurfaceVertical.restaurant,
      displayName: 'Test',
      tagline: 'Accroche',
      canonicalDomain: 'restaurants.leopardo.app',
      palette: const SurfacePalette(
        primary: '#C2410C',
        onPrimary: '#FFFFFF',
        accent: '#F59E0B',
        surface: '#FFFBF5',
        onSurface: '#1C1917',
        muted: '#78716C',
      ),
    );

    test('accepte une identité valide', () {
      expect(() => createSurfaceRegistry([valid()]), returnsNormally);
    });

    test('refuse un displayName vide', () {
      final identity = SurfaceIdentity(
        vertical: SurfaceVertical.restaurant,
        displayName: '   ',
        tagline: 'Accroche',
        canonicalDomain: 'restaurants.leopardo.app',
        palette: valid().palette,
      );

      expect(() => createSurfaceRegistry([identity]), throwsArgumentError);
    });

    test('refuse un domaine avec schéma', () {
      final identity = SurfaceIdentity(
        vertical: SurfaceVertical.restaurant,
        displayName: 'Test',
        tagline: 'Accroche',
        canonicalDomain: 'https://restaurants.leopardo.app',
        palette: valid().palette,
      );

      expect(() => createSurfaceRegistry([identity]), throwsArgumentError);
    });

    test('refuse un hex malformé', () {
      final identity = SurfaceIdentity(
        vertical: SurfaceVertical.restaurant,
        displayName: 'Test',
        tagline: 'Accroche',
        canonicalDomain: 'restaurants.leopardo.app',
        palette: const SurfacePalette(
          primary: 'rouge',
          onPrimary: '#FFFFFF',
          accent: '#F59E0B',
          surface: '#FFFBF5',
          onSurface: '#1C1917',
          muted: '#78716C',
        ),
      );

      expect(() => createSurfaceRegistry([identity]), throwsArgumentError);
    });

    test('refuse un contraste AA insuffisant', () {
      final identity = SurfaceIdentity(
        vertical: SurfaceVertical.restaurant,
        displayName: 'Test',
        tagline: 'Accroche',
        canonicalDomain: 'restaurants.leopardo.app',
        palette: const SurfacePalette(
          primary: '#FFFFFF',
          onPrimary: '#FFFFFF',
          accent: '#F59E0B',
          surface: '#FFFBF5',
          onSurface: '#1C1917',
          muted: '#78716C',
        ),
      );

      expect(() => createSurfaceRegistry([identity]), throwsArgumentError);
    });

    test('refuse une verticale déclarée deux fois', () {
      expect(
        () => createSurfaceRegistry([valid(), valid()]),
        throwsArgumentError,
      );
    });

    test('refuse deux vitrines sur le même domaine', () {
      final other = SurfaceIdentity(
        vertical: SurfaceVertical.retail,
        displayName: 'Autre',
        tagline: 'Accroche',
        canonicalDomain: 'restaurants.leopardo.app',
        palette: valid().palette,
      );

      expect(
        () => createSurfaceRegistry([valid(), other]),
        throwsArgumentError,
      );
    });
  });

  group('contraste et couleurs', () {
    test('parse un hex valide', () {
      expect(parseHexColor('#C2410C'), [0xC2, 0x41, 0x0C]);
      expect(parseHexColor('C2410C'), [0xC2, 0x41, 0x0C]);
    });

    test('rejette un hex malformé', () {
      expect(parseHexColor('rouge'), isNull);
      expect(parseHexColor('#FFF'), isNull);
    });

    test('calcule un contraste conforme WCAG', () {
      // Blanc sur noir = 21:1, la borne haute de l'échelle.
      expect(contrastRatio('#000000', '#FFFFFF'), closeTo(21, 0.1));
      expect(contrastRatio('#FFFFFF', '#FFFFFF'), closeTo(1, 0.01));
    });

    test('pickReadableOn choisit le noir sur un fond clair', () {
      expect(pickReadableOn('#FFFFFF'), '#000000');
    });

    test('pickReadableOn choisit le blanc sur un fond sombre', () {
      expect(pickReadableOn('#000000'), '#FFFFFF');
    });
  });

  group('thème de surface', () {
    test('dérive le thème de l’identité de la verticale', () {
      final identity = surfaceRegistry.get(SurfaceVertical.pharmacy)!;
      final theme = surfaceTheme(identity);

      expect(theme.colorScheme.primary, colorOf(identity.palette.primary));
      expect(theme.scaffoldBackgroundColor, colorOf(identity.palette.surface));
    });

    test('readableOn choisit un texte lisible sur un fond sombre', () {
      final identity = surfaceRegistry.get(SurfaceVertical.trade)!;

      expect(
        readableOn(colorOf(identity.palette.primary)),
        colorOf(identity.palette.onPrimary),
      );
    });

    test('hexOf et colorOf sont inverses', () {
      final identity = surfaceRegistry.get(SurfaceVertical.travel)!;

      expect(
        hexOf(colorOf(identity.palette.primary)),
        identity.palette.primary,
      );
    });
  });
}
