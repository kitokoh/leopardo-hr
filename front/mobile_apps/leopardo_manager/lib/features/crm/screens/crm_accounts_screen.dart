import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_manager/features/crm/providers/crm_providers.dart';
import 'package:go_router/go_router.dart';

/// Liste des comptes CRM du tenant — issue #5730.
class CrmAccountsScreen extends ConsumerWidget {
  const CrmAccountsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final accounts = ref.watch(crmAccountsProvider);

    return MobilePage(
      title: l10n.crmAccounts,
      showBackButton: true,
      children: [
        accounts.when(
          loading: () => const Padding(
            padding: EdgeInsets.all(32),
            child: Center(child: CircularProgressIndicator()),
          ),
          error: (error, _) => _ErrorPanel(message: l10n.crmLoadError),
          data: (items) => items.isEmpty
              ? _EmptyPanel(message: l10n.crmEmptyAccounts)
              : Column(
                  children: [
                    for (final account in items)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: MobilePanel(
                          child: ListTile(
                            contentPadding: EdgeInsets.zero,
                            title: Text(
                              account.name,
                              style: const TextStyle(
                                color: MobileSurface.text,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            subtitle: Text(
                              account.ownerName ?? '',
                              style: const TextStyle(color: MobileSurface.muted),
                            ),
                            trailing: const Icon(
                              Icons.chevron_right,
                              color: MobileSurface.muted,
                            ),
                            onTap: () => context.push(
                              '/crm/accounts/${account.id}',
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
}

class _ErrorPanel extends StatelessWidget {
  const _ErrorPanel({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return MobilePanel(
      child: Column(
        children: [
          const Icon(Icons.error_outline, color: MobileSurface.secondary),
          const SizedBox(height: 8),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: MobileSurface.muted),
          ),
        ],
      ),
    );
  }
}

class _EmptyPanel extends StatelessWidget {
  const _EmptyPanel({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return MobilePanel(
      child: Text(
        message,
        textAlign: TextAlign.center,
        style: const TextStyle(color: MobileSurface.muted),
      ),
    );
  }
}
