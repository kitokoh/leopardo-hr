import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/core/widgets/leopardo_badge.dart';

void main() {
  testWidgets('LeopardoBadge has correct Semantics label', (tester) async {
    final handle = tester.ensureSemantics();
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: LeopardoBadge(
            label: 'Présent',
            color: Colors.green,
            icon: Icons.check,
          ),
        ),
      ),
    );

    final node = tester.getSemantics(find.byType(LeopardoBadge));
    expect(node.label, contains('Statut : Présent'));
    handle.dispose();
  });
}
