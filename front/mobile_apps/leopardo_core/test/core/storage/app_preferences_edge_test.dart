import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';

// Regression test for issue #1287: AppPreferences.edgeNodeId/edgeToken/
// edgeBaseUrl are the only persisted source SyncService reads to decide
// whether a device has been paired with a local Edge node. Without a Hive
// box open, AppPreferences falls back to its static in-memory map, so these
// getters/setters can be exercised without any Flutter binding/plugin.
void main() {
  test('edge enrollment fields default to empty strings when unset', () {
    final preferences = AppPreferences();

    expect(preferences.edgeNodeId, isEmpty);
    expect(preferences.edgeToken, isEmpty);
    expect(preferences.edgeBaseUrl, isEmpty);
  });

  test(
    'saveEdgeEnrollment persists trimmed values and can be cleared',
    () async {
      final preferences = AppPreferences();

      await preferences.saveEdgeEnrollment(
        edgeNodeId: '  550e8400-e29b-41d4-a716-446655440000  ',
        edgeToken: '  secret-token  ',
        edgeBaseUrl: '  http://leopardo.local:7878  ',
      );

      expect(preferences.edgeNodeId, '550e8400-e29b-41d4-a716-446655440000');
      expect(preferences.edgeToken, 'secret-token');
      expect(preferences.edgeBaseUrl, 'http://leopardo.local:7878');

      await preferences.clearEdgeEnrollment();

      expect(preferences.edgeNodeId, isEmpty);
      expect(preferences.edgeToken, isEmpty);
      expect(preferences.edgeBaseUrl, isEmpty);
    },
  );
}
