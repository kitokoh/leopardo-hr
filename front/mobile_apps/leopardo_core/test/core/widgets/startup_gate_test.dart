import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/widgets/startup_gate.dart';

void main() {
  testWidgets(
    'renders a visible startup guard before async bootstrap finishes',
    (tester) async {
      final completer = Completer<void>();

      await tester.pumpWidget(
        StartupGate(
          appName: 'Leopardo Test',
          initializer: () => completer.future,
          child: const Directionality(
            textDirection: TextDirection.ltr,
            child: Text('Real app screen'),
          ),
        ),
      );

      expect(find.text('Leopardo Test'), findsOneWidget);
      expect(find.text('Ouverture de votre espace...'), findsOneWidget);
      expect(find.text('Real app screen'), findsOneWidget);

      completer.complete();
      await tester.pump();
      await tester.pump();

      expect(find.text('Ouverture de votre espace...'), findsNothing);
      expect(find.text('Real app screen'), findsOneWidget);
    },
  );

  testWidgets('auto-continues after a critical bootstrap timeout', (
    tester,
  ) async {
    await tester.pumpWidget(
      StartupGate(
        appName: 'Leopardo Test',
        criticalTimeout: const Duration(milliseconds: 10),
        initializer: () => Completer<void>().future,
        child: const MaterialApp(home: Text('Real app screen')),
      ),
    );

    await tester.pump(const Duration(milliseconds: 20));

    expect(find.text('Leopardo Test'), findsOneWidget);
    expect(find.text('Demarrage en mode securise'), findsOneWidget);
    expect(find.text('Continuer'), findsOneWidget);

    await tester.pump(const Duration(milliseconds: 1300));

    expect(find.text('Demarrage en mode securise'), findsNothing);
    expect(find.text('Real app screen'), findsOneWidget);
  });
}
