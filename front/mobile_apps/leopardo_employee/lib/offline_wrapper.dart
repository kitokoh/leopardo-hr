// ============================================================
// OfflineWrapper — Wraps the employee app with offline support
// Add this around your MaterialApp or top-level widget
// ============================================================

import 'package:flutter/material.dart';
import 'package:leopardo_core/offline/services/sync_service.dart';
import 'package:leopardo_core/offline/widgets/sync_status_banner.dart';

/// Wraps any scaffold body with the sync status banner at the top.
/// Usage:
///   OfflineWrapper(syncService: syncService, child: MyPage())
class OfflineWrapper extends StatelessWidget {
  final Widget child;
  final SyncService syncService;

  const OfflineWrapper({
    super.key,
    required this.child,
    required this.syncService,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        LiveSyncStatusBanner(
          syncService: syncService,
          onSyncTap: () => syncService.syncNow(),
        ),
        Expanded(child: child),
      ],
    );
  }
}
