import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/leopardo_badge.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/l10n/l10n.dart';

import '../../core/platform_providers.dart';
import '../platform/platform_models.dart';

final platformEdgeNodesProvider =
    FutureProvider<List<PlatformEdgeNode>>((ref) async {
  return ref.watch(platformRepositoryProvider).edgeNodes();
});

class EdgeNodesScreen extends ConsumerWidget {
  const EdgeNodesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final nodes = ref.watch(platformEdgeNodesProvider);
    final l10n = context.l10n;

    return MobilePage(
      appBar: MobileTopBar(
        title: l10n.edgeNodesTitle,
        subtitle: l10n.edgeNodesSubtitle,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            tooltip: l10n.edgeNodesRefreshTooltip,
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(platformEdgeNodesProvider),
          ),
        ],
      ),
      children: [
        nodes.when(
          data: (items) {
            if (items.isEmpty) {
              return MobilePanel(
                child: Column(
                  children: [
                    const Icon(
                      Icons.router_rounded,
                      size: 48,
                      color: MobileSurface.disabled,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      l10n.edgeNodesEmpty,
                      style: const TextStyle(color: MobileSurface.secondary),
                    ),
                  ],
                ),
              );
            }

            final online = items.where((n) => n.isOnline).length;
            final offline = items.length - online;

            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Summary
                Row(
                  children: [
                    MobileMetricTile(
                      value: '$online',
                      label: l10n.edgeNodesOnline,
                      color: AppColors.success,
                    ),
                    const SizedBox(width: 10),
                    MobileMetricTile(
                      value: '$offline',
                      label: l10n.edgeNodesOffline,
                      color: offline > 0 ? AppColors.danger : MobileSurface.disabled,
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                MobileSectionLabel(l10n.edgeNodesSectionLabel),
                ...items.map((node) => _EdgeNodeCard(node: node)),
              ],
            );
          },
          loading: () => MobileEmptyLoading(label: l10n.edgeNodesLoading),
          error: (e, _) => MobileErrorPanel(
            message: e.toString(),
            onRetry: () => ref.invalidate(platformEdgeNodesProvider),
          ),
        ),
      ],
    );
  }
}

class _EdgeNodeCard extends ConsumerWidget {
  const _EdgeNodeCard({required this.node});

  final PlatformEdgeNode node;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final statusColor = node.isOnline ? AppColors.success : AppColors.danger;
    final l10n = context.l10n;
    // Format last sync date (trim to YYYY-MM-DD HH:MM if longer).
    final lastSync = node.lastSyncAt.length > 16
        ? node.lastSyncAt.substring(0, 16).replaceAll('T', ' ')
        : node.lastSyncAt;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: MobilePanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                MobileIconBubble(
                  icon: Icons.router_rounded,
                  color: statusColor,
                  size: 40,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        node.companyName,
                        style: const TextStyle(
                          color: MobileSurface.text,
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        l10n.edgeNodesVersionInfo(node.version, node.employeesCount),
                        style: const TextStyle(
                          color: MobileSurface.secondary,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                LeopardoBadge(label: node.status, color: statusColor),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              l10n.edgeNodesLastSync(lastSync),
              style: const TextStyle(
                color: MobileSurface.secondary,
                fontSize: 11,
              ),
            ),
            Text(
              l10n.edgeNodesNodeId(node.id),
              style: const TextStyle(
                color: MobileSurface.secondary,
                fontSize: 11,
                fontFamily: 'monospace',
              ),
            ),
            const SizedBox(height: 10),
            MobilePrimaryAction(
              icon: Icons.sync_rounded,
              label: l10n.edgeNodesForceSync,
              onPressed: () async {
                // Capture l10n before async gap.
                final syncTriggeredMsg = l10n.edgeNodesSyncTriggered;
                try {
                  await ref
                      .read(platformRepositoryProvider)
                      .forceEdgeNodeSync(node.id);
                  ref.invalidate(platformEdgeNodesProvider);
                  if (!context.mounted) return;
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(syncTriggeredMsg)),
                  );
                } catch (e) {
                  if (!context.mounted) return;
                  ScaffoldMessenger.of(context)
                      .showSnackBar(SnackBar(content: Text(e.toString())));
                }
              },
            ),
          ],
        ),
      ),
    );
  }
}
