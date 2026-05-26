import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';

import '../../core/platform_providers.dart';
import '../platform/platform_models.dart';

final platformCompanyRequestsProvider =
    FutureProvider<List<PlatformCompanyRequest>>((ref) {
      return ref.watch(platformRepositoryProvider).companyRequests();
    });

class CompanyRequestsScreen extends ConsumerWidget {
  const CompanyRequestsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final requests = ref.watch(platformCompanyRequestsProvider);

    return MobilePage(
      appBar: MobileTopBar(
        title: 'Demandes clients',
        subtitle: 'Validation super-admin',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
      ),
      children: [
        requests.when(
          data:
              (items) =>
                  items.isEmpty
                      ? const MobilePanel(
                        child: Text(
                          'Aucune demande en attente.',
                          style: TextStyle(color: MobileSurface.secondary),
                        ),
                      )
                      : Column(
                        children:
                            items
                                .map(
                                  (request) =>
                                      _CompanyRequestCard(request: request),
                                )
                                .toList(),
                      ),
          loading: () => const MobileEmptyLoading(label: 'Chargement demandes'),
          error:
              (error, _) => MobileErrorPanel(
                message: error.toString(),
                onRetry: () => ref.invalidate(platformCompanyRequestsProvider),
              ),
        ),
      ],
    );
  }
}

class _CompanyRequestCard extends ConsumerWidget {
  const _CompanyRequestCard({required this.request});

  final PlatformCompanyRequest request;

  Future<void> _review(
    BuildContext context,
    WidgetRef ref,
    bool approved,
  ) async {
    await ref
        .read(platformRepositoryProvider)
        .reviewCompanyRequest(
          id: request.id,
          approved: approved,
          adminNotes:
              approved
                  ? 'Approuve depuis Leopardo Platform Admin mobile'
                  : 'Refuse depuis Leopardo Platform Admin mobile',
        );
    ref.invalidate(platformCompanyRequestsProvider);
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(approved ? 'Demande approuvee' : 'Demande refusee'),
      ),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return MobileListCard(
      icon: Icons.fact_check_rounded,
      iconColor: AppColors.warning,
      title: request.companyName,
      subtitle: '${request.city}, ${request.country} - ${request.email}',
      trailing: MobileStatusPill(
        label: request.status,
        color: AppColors.warning,
      ),
      footer: Row(
        children: [
          Expanded(
            child: OutlinedButton.icon(
              onPressed: () => _review(context, ref, false),
              icon: const Icon(Icons.close_rounded),
              label: const Text('Refuser'),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: ElevatedButton.icon(
              onPressed: () => _review(context, ref, true),
              icon: const Icon(Icons.check_rounded),
              label: const Text('Approuver'),
            ),
          ),
        ],
      ),
    );
  }
}
