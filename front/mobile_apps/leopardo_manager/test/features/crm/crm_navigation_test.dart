import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_manager/app.dart';
import 'package:leopardo_manager/features/crm/screens/crm_hub_screen.dart';
import 'package:leopardo_manager/features/crm/screens/crm_accounts_screen.dart';
import 'package:leopardo_manager/features/crm/screens/crm_opportunities_screen.dart';

import '../../helpers/mobile_test_harness.dart';

/// Issue #5730 — navigation mobile CRM : les routes `/crm/*` existent dans
/// le routeur manager et mènent aux écrans CRM (hub → listes).
///
/// L'app `leopardo_employee` ne déclare AUCUNE route `/crm/*` (pas d'accès
/// par défaut) — verrouillé par l'absence de routes dans son routeur.
void main() {
  GoRouterAccess goRouter(WidgetTester tester) {
    final container = ProviderScope.containerOf(
      tester.element(find.byType(LeopardoApp)),
    );
    return GoRouterAccess(container.read(routerProvider));
  }

  testWidgets('manager authentifié atteint le hub CRM', (tester) async {
    await tester.pumpWidget(
      appRouterHarness(
        overrides: [authOverride(testEmployee(role: 'manager', managerRole: 'principal'))],
      ),
    );
    await tester.pumpAndSettle();

    goRouter(tester).go('/crm');
    await tester.pumpAndSettle();

    expect(find.byType(CrmHubScreen), findsOneWidget);
  });

  testWidgets('la route /crm/accounts rend la liste des comptes', (tester) async {
    await tester.pumpWidget(
      appRouterHarness(
        overrides: [authOverride(testEmployee(role: 'manager', managerRole: 'principal'))],
      ),
    );
    await tester.pumpAndSettle();

    goRouter(tester).go('/crm/accounts');
    await tester.pumpAndSettle();

    // L'écran s'affiche (la donnée dépend de l'API ; l'état d'erreur/loading
    // est géré par l'écran lui-même — ici on vérifie la NAVIGATION).
    expect(find.byType(CrmAccountsScreen), findsOneWidget);
  });

  testWidgets('la route /crm/opportunities rend les opportunités', (tester) async {
    await tester.pumpWidget(
      appRouterHarness(
        overrides: [authOverride(testEmployee(role: 'manager', managerRole: 'principal'))],
      ),
    );
    await tester.pumpAndSettle();

    goRouter(tester).go('/crm/opportunities');
    await tester.pumpAndSettle();

    expect(find.byType(CrmOpportunitiesScreen), findsOneWidget);
  });

  testWidgets('route CRM inconnue → errorBuilder (pas de crash)', (tester) async {
    await tester.pumpWidget(
      appRouterHarness(
        overrides: [authOverride(testEmployee(role: 'manager', managerRole: 'principal'))],
      ),
    );
    await tester.pumpAndSettle();

    goRouter(tester).go('/crm/inexistante');
    await tester.pumpAndSettle();

    // Pas de crash : l'errorBuilder du routeur affiche la page de secours.
    expect(find.byType(CrmHubScreen), findsNothing);
  });
}

class GoRouterAccess {
  GoRouterAccess(this.router);

  final GoRouter router;

  void go(String location) => router.go(location);
}
