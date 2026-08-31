import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_manager/features/restaurant/screens/restaurant_hub_screen.dart';

import '../../helpers/mobile_test_harness.dart';

void main() {
  testWidgets('restaurant hub renders the three role surfaces', (tester) async {
    await pumpMobile(tester, const RestaurantHubScreen());

    expect(find.text('Restaurant'), findsOneWidget);
    expect(find.text('Service'), findsOneWidget);
    expect(find.text('Livraison'), findsOneWidget);
    expect(find.text('Gestion'), findsOneWidget);
  });

  testWidgets('hub shows offline banner only when queue has pending ops', (
    tester,
  ) async {
    await pumpMobile(tester, const RestaurantHubScreen());

    // File vide : pas de bandeau offline.
    expect(find.textContaining('hors ligne'), findsNothing);
  });
}
