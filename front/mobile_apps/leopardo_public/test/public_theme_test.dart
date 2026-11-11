import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/core/theme/public_theme.dart';

/// Garde du thème grand public (#9359).
///
/// ## Ce que ce test protège
///
/// 1. **Les deux modes sont complets.** Un `ThemeData` dont une seule couleur
///    est définie laisse Material retomber sur ses valeurs par défaut (bleu
///    indigo) : c'est exactement ce qui se passait avant, quand l'app
///    appliquait `TenantTheme.apply(..., null)` qui retournait la `ThemeData`
///    Material inchangée.
/// 2. **Les tokens correspondent au web.** Les valeurs sont dupliquées depuis
///    `front/web/tailwind.config.ts` ; si quelqu'un change la palette d'un
///    côté, ce test ne le détectera pas — mais il documente la valeur attendue
///    et échoue si elle dérive côté Flutter.
/// 3. **Aucune couleur Material par défaut ne fuit.** On vérifie que la
///    couleur primaire n'est pas le bleu Material par défaut.
void main() {
  group('PublicTheme', () {
    test('clair : primaire émeraude, fond slate-50', () {
      final theme = PublicTheme.light();

      expect(theme.brightness, Brightness.light);
      expect(theme.colorScheme.primary, PublicTheme.emerald600);
      expect(theme.scaffoldBackgroundColor, PublicTheme.slate50);
      expect(theme.colorScheme.onSurface, PublicTheme.slate900);
    });

    test('sombre : primaire émeraude-400, fond slate-950', () {
      final theme = PublicTheme.dark();

      expect(theme.brightness, Brightness.dark);
      expect(theme.colorScheme.primary, PublicTheme.emerald400);
      expect(theme.scaffoldBackgroundColor, PublicTheme.slate950);
    });

    test('ne retombe pas sur le bleu Material par défaut', () {
      // `Colors.blue` est la primaire historique de Material ; sa présence
      // signifierait qu'un token n'a pas été posé.
      for (final theme in [PublicTheme.light(), PublicTheme.dark()]) {
        expect(theme.colorScheme.primary, isNot(Colors.blue));
        expect(theme.colorScheme.primary, isNot(Colors.blueAccent));
      }
    });

    test('les deux modes partagent les mêmes rayons', () {
      for (final theme in [PublicTheme.light(), PublicTheme.dark()]) {
        final shape = theme.filledButtonTheme.style?.shape?.resolve({});
        expect(shape, isA<RoundedRectangleBorder>());
        expect(
          (shape! as RoundedRectangleBorder).borderRadius,
          BorderRadius.circular(PublicTheme.radiusXl),
        );
      }
    });

    test('les champs de saisie ont un focus visible dans les deux modes', () {
      for (final theme in [PublicTheme.light(), PublicTheme.dark()]) {
        final focused = theme.inputDecorationTheme.focusedBorder;
        expect(focused, isA<OutlineInputBorder>());
        expect(
          (focused! as OutlineInputBorder).borderSide.color,
          theme.colorScheme.primary,
        );
      }
    });

    test('la navigation basse signale l’onglet actif par la couleur ET la graisse', () {
      final theme = PublicTheme.light();
      final selected = theme.navigationBarTheme.labelTextStyle?.resolve({
        WidgetState.selected,
      });
      final unselected = theme.navigationBarTheme.labelTextStyle?.resolve({});

      expect(selected?.fontWeight, FontWeight.w700);
      expect(unselected?.fontWeight, FontWeight.w500);
      // La couleur seule ne suffit pas (WCAG 1.4.1) : la graisse change aussi.
      expect(selected?.color, isNot(unselected?.color));
    });
  });
}
