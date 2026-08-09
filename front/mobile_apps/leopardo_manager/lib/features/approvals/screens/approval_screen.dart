import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/approvals/providers/approval_provider.dart';

class ApprovalScreen extends ConsumerStatefulWidget {
  const ApprovalScreen({super.key});

  @override
  ConsumerState<ApprovalScreen> createState() => _ApprovalScreenState();
}

class _ApprovalScreenState extends ConsumerState<ApprovalScreen> {
  final _commentController = TextEditingController();

  @override
  void dispose() {
    _commentController.dispose();
    super.dispose();
  }

  Future<void> _approve(int id) async {
    try {
      await ref.read(approvalRepositoryProvider).approve(id);
      ref.invalidate(pendingApprovalsProvider);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur : $e'),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    }
  }

  Future<void> _reject(int id) async {
    _commentController.clear();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: MobileSurface.card,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Text(
          'Motif du refus',
          style: AppTypography.subtitle.copyWith(
            color: MobileSurface.text,
            fontWeight: FontWeight.w600,
          ),
        ),
        content: TextField(
          controller: _commentController,
          style: AppTypography.body.copyWith(color: MobileSurface.text),
          maxLines: 3,
          decoration: InputDecoration(
            hintText: 'Expliquez la raison...',
            hintStyle: AppTypography.body.copyWith(color: MobileSurface.muted),
            enabledBorder: OutlineInputBorder(
              borderSide: BorderSide(color: MobileSurface.border),
              borderRadius: BorderRadius.circular(12),
            ),
            focusedBorder: OutlineInputBorder(
              borderSide: const BorderSide(color: AppColors.rh),
              borderRadius: BorderRadius.circular(12),
            ),
            filled: true,
            fillColor: MobileSurface.surface,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(
              'Annuler',
              style: AppTypography.body.copyWith(color: MobileSurface.text),
            ),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.danger,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
              elevation: 0,
            ),
            child: const Text('Refuser'),
          ),
        ],
      ),
    );
    if (confirmed == true && _commentController.text.isNotEmpty) {
      try {
        await ref
            .read(approvalRepositoryProvider)
            .reject(id, comment: _commentController.text);
        ref.invalidate(pendingApprovalsProvider);
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Erreur : $e'),
              backgroundColor: AppColors.danger,
            ),
          );
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final approvalsAsync = ref.watch(pendingApprovalsProvider);

    return MobilePage(
      title: 'Approbations',
      showBackButton: true,
      onBack: () => context.pop(),
      children: [
        RefreshIndicator(
          color: AppColors.rh,
          backgroundColor: MobileSurface.card,
          onRefresh: () async =>
              await ref.refresh(pendingApprovalsProvider.future),
          child: approvalsAsync.when(
            data: (approvals) => approvals.isEmpty
                ? ListView(
                    shrinkWrap: true,
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: const [
                      SizedBox(height: 80),
                      EmptyState(
                        icon: Icons.check_circle_outline,
                        title: 'Tout est à jour',
                        description: 'Aucune approbation en attente.',
                      ),
                    ],
                  )
                : ListView.builder(
                    shrinkWrap: true,
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 20,
                      vertical: 20,
                    ),
                    itemCount: approvals.length,
                    itemBuilder: (context, index) {
                      final a = approvals[index];
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 16),
                        child: GlassCard(
                          padding: const EdgeInsets.all(20),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 10,
                                      vertical: 6,
                                    ),
                                    decoration: BoxDecoration(
                                      color: AppColors.info.withValues(
                                        alpha: 0.15,
                                      ),
                                      borderRadius: BorderRadius.circular(20),
                                    ),
                                    child: Text(
                                      a.type,
                                      style: AppTypography.caption.copyWith(
                                        color: AppColors.info,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                  Text(
                                    a.createdAt,
                                    style: AppTypography.bodySmall.copyWith(
                                      color: MobileSurface.muted,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 16),
                              Row(
                                children: [
                                  Container(
                                    width: 40,
                                    height: 40,
                                    decoration: BoxDecoration(
                                      color: MobileSurface.border.withValues(
                                        alpha: 0.5,
                                      ),
                                      shape: BoxShape.circle,
                                    ),
                                    child: const Icon(
                                      Icons.person,
                                      color: MobileSurface.muted,
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          a.requesterName,
                                          style:
                                              AppTypography.subtitle.copyWith(
                                            color: MobileSurface.text,
                                            fontWeight: FontWeight.w600,
                                          ),
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          a.summary,
                                          style:
                                              AppTypography.bodySmall.copyWith(
                                            color: MobileSurface.muted,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 20),
                              Row(
                                children: [
                                  Expanded(
                                    child: OutlinedButton(
                                      onPressed: () => _reject(a.id),
                                      style: OutlinedButton.styleFrom(
                                        foregroundColor: AppColors.danger,
                                        side: BorderSide(
                                          color: AppColors.danger.withValues(
                                            alpha: 0.5,
                                          ),
                                        ),
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(
                                            12,
                                          ),
                                        ),
                                        padding: const EdgeInsets.symmetric(
                                          vertical: 14,
                                        ),
                                      ),
                                      child: const Text('Refuser'),
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: ElevatedButton(
                                      onPressed: () => _approve(a.id),
                                      style: ElevatedButton.styleFrom(
                                        backgroundColor: AppColors.rh,
                                        foregroundColor: Colors.white,
                                        elevation: 0,
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(
                                            12,
                                          ),
                                        ),
                                        padding: const EdgeInsets.symmetric(
                                          vertical: 14,
                                        ),
                                      ),
                                      child: const Text('Approuver'),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
            loading: () => const SizedBox(
              height: 400,
              child: Center(
                child: CircularProgressIndicator(
                  color: AppColors.rh,
                  semanticsLabel: 'Chargement des approbations...',
                ),
              ),
            ),
            error: (e, _) => SizedBox(
              height: 400,
              child: Center(
                child: Text(
                  e.toString(),
                  style: const TextStyle(color: AppColors.danger),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
