import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/core/i18n/app_strings.dart';
import 'package:leopardo_public/features/journey/domain/journey_registry.dart';
import 'package:leopardo_public/features/surface/domain/surface_identity.dart';

/// Garde du registre des parcours transactionnels (#9907).
///
/// ## Pourquoi ces tests existent
///
/// La cartographie mobile constate que 3 verticales sur 8 ne sont couvertes par
/// aucun contrat appelable et que 3 parcours précis restent bloqués à
/// l'intérieur de verticales pourtant couvertes. Ce constat se dégrade tout
/// seul : une verticale oubliée n'affiche pas d'erreur, elle affiche un bouton
/// mort. Ces tests rendent l'oubli **mécaniquement impossible**.
void main() {
  group('JourneyRegistry — intégrité', () {
    test('le registre est sain (aucun problème signalé)', () {
      expect(JourneyRegistry.validate(), isEmpty);
    });

    test('les 8 verticales sont couvertes, ni plus ni moins', () {
      expect(JourneyRegistry.all.length, SurfaceVertical.values.length);
      expect(
        JourneyRegistry.all.map((e) => e.vertical).toSet(),
        SurfaceVertical.values.toSet(),
      );
    });

    test('l\'ordre suit SurfaceVertical.values', () {
      // Un ordre libre rendrait les diffs illisibles et masquerait une entrée
      // ajoutée au mauvais endroit.
      expect(
        JourneyRegistry.all.map((e) => e.vertical).toList(),
        SurfaceVertical.values.toList(),
      );
    });

    test('chaque verticale se résout par forVertical', () {
      for (final vertical in SurfaceVertical.values) {
        expect(
          JourneyRegistry.forVertical(vertical),
          isNotNull,
          reason: '${vertical.code} introuvable dans le registre',
        );
      }
    });
  });

  group('JourneyRegistry — motifs et contrats', () {
    test('tout parcours non natif porte un motif i18n existant', () {
      for (final entry in JourneyRegistry.all) {
        if (!entry.isExplained) {
          continue;
        }

        final key = entry.reasonKey;
        expect(key, isNotNull, reason: '${entry.vertical.code} sans motif');

        // Le motif doit exister dans les 4 locales : une clé absente affiche
        // la clé brute à l'écran, sur la langue qu'on ne teste jamais.
        for (final locale in AppStrings.supportedLocales) {
          final text = AppStrings.of(locale).t(key!);
          expect(
            text,
            isNot(key),
            reason: 'clé "$key" absente en $locale',
          );
          expect(text.trim(), isNotEmpty);
        }
      }
    });

    test('un parcours natif n\'a pas de motif à afficher', () {
      for (final entry in JourneyRegistry.all) {
        if (entry.isTransactional) {
          expect(entry.reasonKey, isNull, reason: entry.vertical.code);
        }
      }
    });

    test('un parcours natif déclare au moins un chemin de contrat', () {
      for (final entry in JourneyRegistry.all) {
        if (entry.isTransactional) {
          expect(entry.contractPaths, isNotEmpty, reason: entry.vertical.code);
        }
      }
    });

    test('un parcours bloqué ne déclare aucun chemin', () {
      for (final entry in JourneyRegistry.all) {
        if (entry.kind == JourneyKind.blocked) {
          expect(entry.contractPaths, isEmpty, reason: entry.vertical.code);
        }
      }
    });

    test('les chemins déclarés sont des routes publiques', () {
      for (final entry in JourneyRegistry.all) {
        for (final path in entry.contractPaths) {
          expect(
            path.startsWith('/public/'),
            isTrue,
            reason: '${entry.vertical.code} → $path hors /public/',
          );
        }
      }
    });
  });

  group('JourneyRegistry — état mesuré le 2026-11-24', () {
    test('2 verticales ont un parcours natif (retail, hospitality)', () {
      final native = JourneyRegistry.all
          .where((e) => e.kind == JourneyKind.native)
          .map((e) => e.vertical)
          .toSet();

      expect(native, {SurfaceVertical.retail, SurfaceVertical.hospitality});
    });

    test('3 verticales passent par la passerelle de demande', () {
      final request = JourneyRegistry.all
          .where((e) => e.kind == JourneyKind.request)
          .map((e) => e.vertical)
          .toSet();

      expect(request, {
        SurfaceVertical.restaurant,
        SurfaceVertical.trade,
        SurfaceVertical.vtc,
      });
    });

    test('3 verticales sont bloquées et le disent', () {
      final blocked = JourneyRegistry.all
          .where((e) => e.kind == JourneyKind.blocked)
          .map((e) => e.vertical)
          .toSet();

      expect(blocked, {
        SurfaceVertical.travel,
        SurfaceVertical.pharmacy,
        SurfaceVertical.careers,
      });
    });
  });
}
