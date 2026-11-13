import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_accounting/core/layout/responsive.dart';

/// #9381 — adaptation tablette.
///
/// L'app n'avait aucun `LayoutBuilder` ni `MediaQuery` : elle s'affichait en
/// colonne pleine largeur sur iPad. Ces tests verrouillent les seuils de classe
/// de largeur et le nombre de colonnes qui en découle.
void main() {
  Future<ScreenSize> sizeFor(WidgetTester tester, double width) async {
    late ScreenSize size;

    await tester.pumpWidget(
      MediaQuery(
        data: MediaQueryData(size: Size(width, 800)),
        child: Builder(
          builder: (context) {
            size = Responsive.of(context);
            return const SizedBox.shrink();
          },
        ),
      ),
    );

    return size;
  }

  testWidgets('téléphone portrait → compact', (tester) async {
    expect(await sizeFor(tester, 390), ScreenSize.compact);
  });

  testWidgets('tablette portrait → medium', (tester) async {
    expect(await sizeFor(tester, 768), ScreenSize.medium);
  });

  testWidgets('tablette paysage → expanded', (tester) async {
    expect(await sizeFor(tester, 1180), ScreenSize.expanded);
  });

  testWidgets('la grille passe de 1 à 2 puis 3 colonnes', (tester) async {
    late int compact;
    late int medium;
    late int expanded;

    Future<void> measure(double width, void Function(int) assign) async {
      await tester.pumpWidget(
        MediaQuery(
          data: MediaQueryData(size: Size(width, 800)),
          child: Builder(
            builder: (context) {
              assign(Responsive.gridColumns(context));
              return const SizedBox.shrink();
            },
          ),
        ),
      );
    }

    await measure(390, (v) => compact = v);
    await measure(768, (v) => medium = v);
    await measure(1180, (v) => expanded = v);

    expect(compact, 1);
    expect(medium, 2);
    expect(expanded, 3);
  });

  testWidgets('isTablet est faux sur téléphone et vrai sur tablette', (
    tester,
  ) async {
    late bool phone;
    late bool tablet;

    Future<void> measure(double width, void Function(bool) assign) async {
      await tester.pumpWidget(
        MediaQuery(
          data: MediaQueryData(size: Size(width, 800)),
          child: Builder(
            builder: (context) {
              assign(Responsive.isTablet(context));
              return const SizedBox.shrink();
            },
          ),
        ),
      );
    }

    await measure(390, (v) => phone = v);
    await measure(768, (v) => tablet = v);

    expect(phone, isFalse);
    expect(tablet, isTrue);
  });
}
