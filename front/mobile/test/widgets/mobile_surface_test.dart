import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/widgets/mobile_surface.dart';

void main() {
  testWidgets(
    'MobileSurface widgets render the dark mobile design primitives',
    (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            appBar: const MobileTopBar(
              title: 'Compte',
              subtitle: 'Profil et securite',
            ),
            body: const MobilePanel(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  MobileSectionLabel('Cette semaine'),
                  MobileIconBubble(
                    icon: Icons.fingerprint,
                    color: AppColors.rh,
                  ),
                  MobileStatusPill(
                    label: 'Present',
                    color: AppColors.rh,
                    icon: Icons.check_circle,
                  ),
                ],
              ),
            ),
          ),
        ),
      );

      expect(find.text('Compte'), findsOneWidget);
      expect(find.text('Profil et securite'), findsOneWidget);
      expect(find.text('CETTE SEMAINE'), findsOneWidget);
      expect(find.byIcon(Icons.fingerprint), findsOneWidget);
      expect(find.text('Present'), findsOneWidget);
      expect(MobileSurface.muted.computeLuminance(), greaterThan(0.30));
      expect(MobileSurface.disabled.computeLuminance(), greaterThan(0.20));
    },
  );

  testWidgets('MobilePrimaryAction exposes the requested tap callback', (
    tester,
  ) async {
    var tapped = false;

    await tester.pumpWidget(
      MaterialApp(
        home: Center(
          child: MobilePrimaryAction(
            icon: Icons.play_arrow,
            label: 'Pointer',
            onPressed: () => tapped = true,
          ),
        ),
      ),
    );

    await tester.tap(find.text('Pointer'));
    await tester.pump();

    expect(tapped, isTrue);
  });
}
