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
import 'package:leopardo_core/l10n/l10n.dart';

class ApprovalScreen extends ConsumerStatefulWidget {
  const ApprovalScreen({super.key});

  @override
  ConsumerState<ApprovalScreen> createState() => _ApprovalScreenState();
}

class _ApprovalScreenState extends ConsumerState<ApprovalScreen> {
  final _commentController = TextEditingController();
  bool _approving = false;

  @override
  void dispose() {
    _commentController.dispose();
    super.dispose();
  }

  Future<void> _approve(int id) async {
    // #4960 : double-tap = double POST sans confirmation ni état busy.
    if (_approving) return;
    setState(() => _approving = true);
    try {
      await ref.read(approvalRepositoryProvider).approve(id);
      ref.invalidate(pendingApprovalsProvider);
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(context.l10n.approvalApproved)));
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(context.l10n.errorPrefix(e.toString())),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _approving = false);
    }
  }

  Future<void> _reject(int id) async {
    _commentController.clear();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          backgroundColor: MobileSurface.card,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
          title: Text(
            context.l10n.approvalsRejectReasonLabel,
            style: AppTypography.subtitle.copyWith(
              color: MobileSurface.text,
              fontWeight: FontWeight.w600,
            ),
          ),
          content: TextField(
            controller: _commentController,
            style: AppTypography.body.copyWith(color: MobileSurface.text),
            maxLines: 3,
            onChanged: (_) => setDialogState(() {}),
            decoration: InputDecoration(
              hintText: context.l10n.approvalsRejectReasonHint,
              hintStyle: AppTypography.body.copyWith(
                color: MobileSurface.muted,
              ),
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
                context.l10n.actionCancel,
                style: AppTypography.body.copyWith(color: MobileSurface.text),
              ),
            ),
            ElevatedButton(
              // #4960 : le refus était silencieux si le commentaire était
              // vide (dialog fermé, aucune action) — le bouton reste
              // désactivé tant que le motif n'est pas renseigné.
              onPressed: _commentController.text.trim().isEmpty
                  ? null
                  : () => Navigator.pop(ctx, true),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.danger,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
                elevation: 0,
              ),
              child: Text(context.l10n.actionReject),
            ),
          ],
        ),
      ),
    );
    if (confirmed == true) {
      try {
        await ref
            .read(approvalRepositoryProvider)
            .reject(id, comment: _commentController.text);
        ref.invalidate(pendingApprovalsProvider);
        if (!mounted) return;
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(context.l10n.approvalRejected)));
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(context.l10n.errorPrefix(e.toString())),
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
      title: context.l10n.approvalsTitle,
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
                    children: [
                      const SizedBox(height: 80),
                      EmptyState(
                        icon: Icons.check_circle_outline,
                        title: context.l10n.approvalsUpToDate,
                        description: context.l10n.approvalsEmpty,
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
                                          style: AppTypography.subtitle
                                              .copyWith(
                                                color: MobileSurface.text,
                                                fontWeight: FontWeight.w600,
                                              ),
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          a.summary,
                                          style: AppTypography.bodySmall
                                              .copyWith(
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
                                      child: Text(context.l10n.actionReject),
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
                                      child: Text(context.l10n.actionApprove),
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
                  semanticsLabel: context.l10n.approvalsLoading,
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
