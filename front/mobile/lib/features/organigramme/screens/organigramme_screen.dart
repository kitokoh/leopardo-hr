import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/widgets/empty_state.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/features/organigramme/data/organigramme_repository.dart';

final orgChartProvider = FutureProvider<List<OrgNode>>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  final repo = OrganigrammeRepository(apiClient);
  return repo.getOrgChart();
});

class OrganigrammeScreen extends ConsumerWidget {
  const OrganigrammeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final orgChartAsync = ref.watch(orgChartProvider);

    return Scaffold(
      backgroundColor: AppColors.bgDark,
      appBar: AppBar(
        backgroundColor: AppColors.bgDark,
        elevation: 0,
        title: Text(
          'Organigramme',
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(orgChartProvider),
        child: orgChartAsync.when(
          data: (nodes) {
            if (nodes.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 80),
                  EmptyState(
                    icon: Icons.account_tree,
                    title: 'Aucun organigramme',
                    description:
                        'L\'organigramme sera disponible une fois les employés configurés.',
                  ),
                ],
              );
            }
            return SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              scrollDirection: Axis.vertical,
              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: nodes
                      .map((node) => _OrgTreeWidget(node: node, depth: 0))
                      .toList(),
                ),
              ),
            );
          },
          loading: () => const Center(
            child: CircularProgressIndicator(
              semanticsLabel: 'Chargement de l\'organigramme...',
            ),
          ),
          error: (e, _) => Center(
            child: Text(
              e.toString(),
              style: const TextStyle(color: AppColors.danger),
            ),
          ),
        ),
      ),
    );
  }
}

class _OrgTreeWidget extends StatefulWidget {
  final OrgNode node;
  final int depth;

  const _OrgTreeWidget({required this.node, required this.depth});

  @override
  State<_OrgTreeWidget> createState() => _OrgTreeWidgetState();
}

class _OrgTreeWidgetState extends State<_OrgTreeWidget> {
  bool _expanded = true;

  @override
  Widget build(BuildContext context) {
    final node = widget.node;
    final hasChildren = node.children.isNotEmpty;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: EdgeInsets.only(left: widget.depth * 32.0),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (widget.depth > 0)
                Container(
                  width: 24,
                  height: 2,
                  color: AppColors.textMutedDark.withValues(alpha: 0.3),
                  margin: const EdgeInsets.only(right: 4),
                ),
              GestureDetector(
                onTap: hasChildren
                    ? () => setState(() => _expanded = !_expanded)
                    : null,
                child: Container(
                  constraints: const BoxConstraints(
                    minWidth: 180,
                    maxWidth: 280,
                  ),
                  margin: const EdgeInsets.symmetric(vertical: 4),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: widget.depth == 0
                        ? AppColors.rh.withValues(alpha: 0.15)
                        : AppColors.cardDark,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: widget.depth == 0
                          ? AppColors.rh.withValues(alpha: 0.4)
                          : AppColors.textMutedDark.withValues(alpha: 0.2),
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      CircleAvatar(
                        radius: 20,
                        backgroundColor:
                            AppColors.rh.withValues(alpha: 0.2),
                        backgroundImage: node.photoUrl != null
                            ? NetworkImage(node.photoUrl!)
                            : null,
                        child: node.photoUrl == null
                            ? Text(
                                _initials(node.name),
                                style: AppTypography.bodySmall.copyWith(
                                  color: AppColors.rh,
                                  fontWeight: FontWeight.bold,
                                ),
                              )
                            : null,
                      ),
                      const SizedBox(width: 10),
                      Flexible(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              node.name,
                              style: AppTypography.subtitle.copyWith(
                                color: AppColors.textDark,
                                fontSize: 13,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                            if (node.position != null)
                              Text(
                                node.position!,
                                style: AppTypography.bodySmall.copyWith(
                                  color: AppColors.textMutedDark,
                                  fontSize: 11,
                                ),
                                overflow: TextOverflow.ellipsis,
                              ),
                            if (node.department != null)
                              Text(
                                node.department!,
                                style: AppTypography.bodySmall.copyWith(
                                  color: AppColors.info,
                                  fontSize: 10,
                                ),
                                overflow: TextOverflow.ellipsis,
                              ),
                          ],
                        ),
                      ),
                      if (hasChildren) ...[
                        const SizedBox(width: 4),
                        Icon(
                          _expanded
                              ? Icons.expand_less
                              : Icons.expand_more,
                          color: AppColors.textMutedDark,
                          size: 18,
                          semanticLabel: _expanded ? 'Réduire' : 'Développer',
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
        if (hasChildren && _expanded)
          ...node.children.map(
            (child) => _OrgTreeWidget(
              node: child,
              depth: widget.depth + 1,
            ),
          ),
      ],
    );
  }

  String _initials(String name) {
    final parts = name.trim().split(' ');
    if (parts.length >= 2) {
      return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
    }
    return name.isNotEmpty ? name[0].toUpperCase() : '?';
  }
}
