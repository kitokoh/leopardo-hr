import 'package:flutter/material.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:go_router/go_router.dart';

/// Hub CRM client (tenant) — issue #5730.
///
/// Accessible uniquement dans `leopardo_manager` (route déclarée ici et
/// nulle part dans `leopardo_employee`) ; le serveur applique les Policies
/// manager principal/rh sur toutes les routes `/api/v1/crm/*`.
class CrmHubScreen extends StatelessWidget {
  const CrmHubScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return MobilePage(
      title: l10n.crmHubTitle,
      showBackButton: true,
      children: [
        const SizedBox(height: 4),
        Text(
          l10n.crmHubSubtitle,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: MobileSurface.muted,
              ),
        ),
        const SizedBox(height: 16),
        _HubTile(
          icon: Icons.business_outlined,
          label: l10n.crmAccounts,
          onTap: () => context.push('/crm/accounts'),
        ),
        _HubTile(
          icon: Icons.person_search_outlined,
          label: l10n.crmLeads,
          onTap: () => context.push('/crm/leads'),
        ),
        _HubTile(
          icon: Icons.track_changes_outlined,
          label: l10n.crmOpportunities,
          onTap: () => context.push('/crm/opportunities'),
        ),
      ],
    );
  }
}

class _HubTile extends StatelessWidget {
  const _HubTile({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: MobilePanel(
        child: ListTile(
          contentPadding: EdgeInsets.zero,
          leading: Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: MobileSurface.chip,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: MobileSurface.secondary, size: 22),
          ),
          title: Text(
            label,
            style: const TextStyle(
              color: MobileSurface.text,
              fontWeight: FontWeight.w600,
            ),
          ),
          trailing: const Icon(
            Icons.chevron_right,
            color: MobileSurface.muted,
          ),
          onTap: onTap,
        ),
      ),
    );
  }
}
