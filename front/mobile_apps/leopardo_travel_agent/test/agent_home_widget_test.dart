import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';
import 'package:leopardo_travel_agent/core/providers/core_providers.dart';
import 'package:leopardo_travel_agent/features/travel/screens/agent_home_screen.dart';

void main() {
  group('AgentHomeScreen (TRAVEL-701/#6088)', () {
    testWidgets('affiche le hub agent avec les 4 parcours terrain',
        (tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            appPreferencesProvider.overrideWithValue(AppPreferences()),
          ],
          child: const MaterialApp(home: AgentHomeScreen()),
        ),
      );
      await tester.pumpAndSettle();

      // Titre + les 4 cartes d'action du parcours agent.
      expect(find.text('Leopardo Voyage'), findsOneWidget);
      expect(find.text('Rechercher un trajet'), findsOneWidget);
      expect(find.text('Réservations'), findsOneWidget);
      expect(find.text('Manifeste'), findsOneWidget);
      expect(find.text('Point de vente'), findsOneWidget);
    });
  });
}
