// ============================================================
// SyncStatusBanner — Shows offline/edge/cloud mode in UI
// ============================================================

import 'package:flutter/material.dart';
import '../services/sync_service.dart';

class SyncStatusBanner extends StatelessWidget {
  final SyncMode mode;
  final bool isSyncing;
  final VoidCallback? onSyncTap;

  const SyncStatusBanner({
    super.key,
    required this.mode,
    this.isSyncing = false,
    this.onSyncTap,
  });

  @override
  Widget build(BuildContext context) {
    return switch (mode) {
      SyncMode.offline => _banner(
          color: Colors.red.shade700,
          icon: Icons.wifi_off_rounded,
          label: 'Mode hors ligne — données sauvegardées localement',
        ),
      SyncMode.edge => _banner(
          color: Colors.orange.shade700,
          icon: Icons.lan_rounded,
          label: isSyncing
              ? 'Synchronisation Edge en cours…'
              : 'Réseau local (Edge)',
          trailing: onSyncTap != null
              ? IconButton(
                  icon: const Icon(Icons.sync, color: Colors.white, size: 18),
                  onPressed: onSyncTap,
                )
              : null,
        ),
      SyncMode.cloud => const SizedBox.shrink(), // Cloud = normal mode, no banner
    };
  }

  Widget _banner({
    required Color color,
    required IconData icon,
    required String label,
    Widget? trailing,
  }) {
    return Material(
      color: color,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        child: Row(
          children: [
            Icon(icon, color: Colors.white, size: 16),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
            if (trailing != null) trailing,
          ],
        ),
      ),
    );
  }
}

/// Stream-based wrapper that auto-updates from SyncService.
class LiveSyncStatusBanner extends StatelessWidget {
  final SyncService syncService;
  final VoidCallback? onSyncTap;

  const LiveSyncStatusBanner({
    super.key,
    required this.syncService,
    this.onSyncTap,
  });

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<SyncMode>(
      stream: syncService.modeStream,
      initialData: syncService.currentMode,
      builder: (context, snapshot) {
        return SyncStatusBanner(
          mode: snapshot.data ?? SyncMode.offline,
          onSyncTap: onSyncTap,
        );
      },
    );
  }
}
