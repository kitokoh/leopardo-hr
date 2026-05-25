import 'package:flutter/material.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/widgets/mobile_surface.dart';

class MobileDecisionActions extends StatelessWidget {
  const MobileDecisionActions({
    super.key,
    required this.approveLabel,
    required this.rejectLabel,
    required this.onApprove,
    required this.onReject,
  });

  final String approveLabel;
  final String rejectLabel;
  final VoidCallback onApprove;
  final VoidCallback onReject;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        OutlinedButton.icon(
          onPressed: onReject,
          icon: const Icon(Icons.close_rounded, size: 16),
          label: Text(rejectLabel),
          style: OutlinedButton.styleFrom(
            foregroundColor: AppColors.danger,
            side: const BorderSide(color: AppColors.danger),
          ),
        ),
        ElevatedButton.icon(
          onPressed: onApprove,
          icon: const Icon(Icons.check_rounded, size: 16),
          label: Text(approveLabel),
          style: ElevatedButton.styleFrom(backgroundColor: AppColors.rh),
        ),
      ],
    );
  }
}

class MobileDecisionCommentSheet extends StatefulWidget {
  const MobileDecisionCommentSheet({
    super.key,
    required this.title,
    required this.helper,
    required this.submitLabel,
    required this.onSubmit,
    required this.successMessage,
    this.danger = false,
  });

  final String title;
  final String helper;
  final String submitLabel;
  final Future<void> Function(String comment) onSubmit;
  final String successMessage;
  final bool danger;

  @override
  State<MobileDecisionCommentSheet> createState() =>
      _MobileDecisionCommentSheetState();
}

class _MobileDecisionCommentSheetState
    extends State<MobileDecisionCommentSheet> {
  final _formKey = GlobalKey<FormState>();
  final _commentController = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _commentController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.of(context).viewInsets.bottom;
    final color = widget.danger ? AppColors.danger : AppColors.rh;

    return Padding(
      padding: EdgeInsets.fromLTRB(22, 18, 22, bottom + 24),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 38,
                height: 4,
                decoration: BoxDecoration(
                  color: MobileSurface.border,
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
            ),
            const SizedBox(height: 18),
            Text(
              widget.title,
              style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
            ),
            const SizedBox(height: 4),
            Text(
              widget.helper,
              style: AppTypography.bodySmall.copyWith(
                color: MobileSurface.secondary,
              ),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _commentController,
              maxLines: 3,
              maxLength: 240,
              style: const TextStyle(color: MobileSurface.text),
              decoration: const InputDecoration(
                labelText: 'Commentaire',
                hintText: 'Expliquez la decision en une phrase claire.',
              ),
              validator:
                  (value) =>
                      value == null || value.trim().length < 3
                          ? 'Commentaire obligatoire'
                          : null,
            ),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: _submitting ? null : _submit,
              style: ElevatedButton.styleFrom(backgroundColor: color),
              child:
                  _submitting
                      ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                      : Text(widget.submitLabel),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    try {
      await widget.onSubmit(_commentController.text);
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(widget.successMessage)));
    } catch (error) {
      if (!mounted) return;
      setState(() => _submitting = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Echec : $error')));
    }
  }
}
