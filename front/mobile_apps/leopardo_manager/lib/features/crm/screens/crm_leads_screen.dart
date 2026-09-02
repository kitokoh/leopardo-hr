import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_manager/features/crm/providers/crm_providers.dart';

/// Liste des leads CRM du tenant — issue #5730.
class CrmLeadsScreen extends ConsumerWidget {
  const CrmLeadsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final leads = ref.watch(crmLeadsProvider);

    return MobilePage(
      title: l10n.crmLeads,
      showBackButton: true,
      children: [
        leads.when(
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
                    l10n.crmEmptyLeads,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: MobileSurface.muted),
                  ),
                )
              : Column(
                  children: [
                    for (final lead in items)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: MobilePanel(
                          child: ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: const Icon(
                              Icons.person_search_outlined,
                              color: MobileSurface.secondary,
                            ),
                            title: Text(
                              lead.displayName,
                              style: const TextStyle(
                                color: MobileSurface.text,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            subtitle: Text(
                              '${lead.companyName ?? ''} · ${lead.status}',
                              style: const TextStyle(
                                color: MobileSurface.muted,
                                fontSize: 12,
                              ),
                            ),
                            trailing: _StatusPill(lead.status),
                          ),
                        ),
                      ),
                  ],
                ),
        ),
      ],
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill(this.status);

  final String status;

  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'converted' => Colors.greenAccent,
      'rejected' => Colors.redAccent,
      'qualified' => Colors.lightBlueAccent,
      _ => MobileSurface.muted,
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.4)),
      ),
      child: Text(
        status,
        style: TextStyle(color: color, fontSize: 11),
      ),
    );
  }
}
