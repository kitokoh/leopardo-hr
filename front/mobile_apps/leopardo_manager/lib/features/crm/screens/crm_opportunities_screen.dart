import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_manager/features/crm/data/crm_repository.dart';
import 'package:leopardo_manager/features/crm/providers/crm_providers.dart';

/// Liste des opportunités CRM + transition d'étape — issue #5730.
///
/// La transition appelle la même API que le web (`PUT
/// /crm/opportunities/{id}`) ; le serveur applique les Policies manager.
class CrmOpportunitiesScreen extends ConsumerWidget {
  const CrmOpportunitiesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final opportunities = ref.watch(crmOpportunitiesProvider);

    return MobilePage(
      title: l10n.crmOpportunities,
      showBackButton: true,
      children: [
        opportunities.when(
          loading: () => const Padding(
            padding: EdgeInsets.all(32),
            child: Center(child: CircularProgressIndicator()),
          ),
          error: (error, _) => MobilePanel(
            child: Text(
              l10n.crmLoadError,
              style: const TextStyle(color: MobileSurface.muted),
            ),
          ),
          data: (items) => items.isEmpty
              ? MobilePanel(
                  child: Text(
                    l10n.crmEmptyOpportunities,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: MobileSurface.muted),
                  ),
                )
              : Column(
                  children: [
                    for (final opportunity in items)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: MobilePanel(
                          child: ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: const Icon(
                              Icons.track_changes_outlined,
                              color: MobileSurface.secondary,
                            ),
                            title: Text(
                              opportunity.name,
                              style: const TextStyle(
                                color: MobileSurface.text,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            subtitle: Text(
                              '${l10n.crmStage}: ${opportunity.stage}'
                              '${opportunity.amount != null ? ' · ${opportunity.amount}' : ''}',
                              style: const TextStyle(
                                color: MobileSurface.muted,
                                fontSize: 12,
                              ),
                            ),
                            trailing: PopupMenuButton<String>(
                              icon: const Icon(
                                Icons.more_vert,
                                color: MobileSurface.muted,
                              ),
                              onSelected: (stage) => _transition(
                                context,
                                ref,
                                opportunity.id,
                                stage,
                              ),
                              itemBuilder: (context) => [
                                for (final stage in _stages)
                                  PopupMenuItem(
                                    value: stage,
                                    child: Text(stage),
                                  ),
                              ],
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
        ),
      ],
    );
  }

  static const _stages = ['prospection', 'qualified', 'won', 'lost'];

  Future<void> _transition(
    BuildContext context,
    WidgetRef ref,
    int opportunityId,
    String stage,
  ) async {
    final messenger = ScaffoldMessenger.of(context);
    final l10n = context.l10n;

    try {
      await ref
          .read(crmRepositoryProvider)
          .transitionOpportunity(opportunityId, stage);
      ref.invalidate(crmOpportunitiesProvider);
      messenger.showSnackBar(
        SnackBar(content: Text('$stage ✓')),
      );
    } catch (_) {
      messenger.showSnackBar(
        SnackBar(content: Text(l10n.crmLoadError)),
      );
    }
  }
}
