import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:leopardo_core/core/api/api_exceptions.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:leopardo_core/core/widgets/leopardo_qr_card.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/features/settings/providers/settings_provider.dart';
import 'package:leopardo_core/l10n/l10n.dart';

/// Issue #5683 — enrôlement / désactivation / codes de récupération 2FA
/// (follow-up #5627 : le challenge post-login était déjà mergé, pas
/// l'enrôlement).
///
/// Partagé par les apps employé et manager (composant leopardo_core).
/// Contrat backend : TwoFactorAuthController #5436 (enroll → `{secret,
/// qr_url}` otpauth://, confirm → `recovery_codes`, disable → `{enabled:
/// false}`, recovery-codes → régénération).
class TwoFactorSettingsScreen extends ConsumerStatefulWidget {
  const TwoFactorSettingsScreen({super.key});

  @override
  ConsumerState<TwoFactorSettingsScreen> createState() =>
      _TwoFactorSettingsScreenState();
}

class _TwoFactorSettingsScreenState
    extends ConsumerState<TwoFactorSettingsScreen> {
  bool _loading = true;
  bool _enabled = false;
  bool _mfaRequired = false;
  String? _error;

  // Flux d'enrôlement.
  String? _secret;
  String? _qrUrl;
  List<String> _recoveryCodes = const <String>[];
  final _codeController = TextEditingController();
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _loadStatus();
  }

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _loadStatus() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final status = await ref.read(twoFactorRepositoryProvider).status();
      if (!mounted) return;
      setState(() {
        _enabled = status['enabled'] == true;
        _mfaRequired = status['mfa_required'] == true;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = context.l10n.twoFaGenericError;
      });
    }
  }

  Future<void> _startEnroll() async {
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final data = await ref.read(twoFactorRepositoryProvider).enroll();
      if (!mounted) return;
      setState(() {
        _secret = data['secret'] as String?;
        _qrUrl = data['qr_url'] as String?;
        _submitting = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _error = _friendlyError(context, e);
      });
    }
  }

  Future<void> _confirmEnrollment() async {
    final code = _codeController.text.trim();
    if (code.isEmpty) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final codes = await ref.read(twoFactorRepositoryProvider).confirm(code);
      if (!mounted) return;
      setState(() {
        _recoveryCodes = codes;
        _enabled = true;
        _submitting = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _error = _friendlyError(context, e);
      });
    }
  }

  Future<void> _disable() async {
    final code = await _promptForCode(
      context,
      title: context.l10n.twoFaDisable,
      hint: context.l10n.twoFaDisableHint,
    );
    if (code == null) return;

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await ref.read(twoFactorRepositoryProvider).disable(code);
      if (!mounted) return;
      setState(() {
        _enabled = false;
        _secret = null;
        _qrUrl = null;
        _recoveryCodes = const <String>[];
        _codeController.clear();
        _submitting = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _error = _friendlyError(context, e);
      });
    }
  }

  Future<void> _regenerate() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.l10n.twoFaRegenerate),
        content: Text(context.l10n.twoFaRegenerateConfirm),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(context.l10n.twoFaCancel),
          ),
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(context.l10n.twoFaRegenerate),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final codes =
          await ref.read(twoFactorRepositoryProvider).regenerateRecoveryCodes();
      if (!mounted) return;
      setState(() {
        _recoveryCodes = codes;
        _submitting = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _error = _friendlyError(context, e);
      });
    }
  }

  Future<String?> _promptForCode(
    BuildContext context, {
    required String title,
    required String hint,
  }) async {
    final controller = TextEditingController();
    final code = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          autofocus: true,
          keyboardType: TextInputType.number,
          maxLength: 16,
          decoration: InputDecoration(
            labelText: context.l10n.twoFaEnterCode,
            hintText: hint,
          ),
          onSubmitted: (value) => Navigator.of(dialogContext).pop(value),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: Text(context.l10n.twoFaCancel),
          ),
          TextButton(
            onPressed: () =>
                Navigator.of(dialogContext).pop(controller.text.trim()),
            child: Text(context.l10n.twoFaConfirm),
          ),
        ],
      ),
    );
    controller.dispose();
    return code == null || code.isEmpty ? null : code;
  }

  String _friendlyError(BuildContext context, Object error) {
    if (error is ApiException && error.code == 'TWO_FACTOR_INVALID') {
      return context.l10n.twoFaInvalidCode;
    }
    return context.l10n.twoFaGenericError;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: context.l10n.twoFaTitle,
        subtitle: context.l10n.twoFaSubtitle,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: context.l10n.back,
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: _buildBody(context),
    );
  }

  Widget _buildBody(BuildContext context) {
    if (_loading) {
      return MobileEmptyLoading(label: context.l10n.twoFaLoading);
    }

    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        if (_error != null) ...[
          MobileErrorPanel(
            message: _error!,
            onRetry: _loadStatus,
          ),
          const SizedBox(height: 20),
        ],
        if (_mfaRequired && !_enabled) ...[
          _PolicyBanner(message: context.l10n.twoFaRequiredBanner),
          const SizedBox(height: 20),
        ],
        _buildStatusCard(context),
        const SizedBox(height: 20),
        if (!_enabled && _qrUrl == null) _buildActivateSection(context),
        if (!_enabled && _qrUrl != null && _recoveryCodes.isEmpty)
          _buildEnrollStep(context),
        if (_enabled && _recoveryCodes.isNotEmpty) _buildRecoverySection(),
        if (_enabled) _buildManageSection(context),
      ],
    );
  }

  Widget _buildStatusCard(BuildContext context) {
    final l10n = context.l10n;
    return GlassCard(
      padding: const EdgeInsets.all(18),
      child: Row(
        children: [
          MobileIconBubble(
            icon: _enabled
                ? Icons.verified_user_rounded
                : Icons.shield_outlined,
            color: _enabled ? AppColors.success : AppColors.warning,
            size: 40,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _enabled ? l10n.twoFaStatusEnabled : l10n.twoFaStatusDisabled,
                  style: AppTypography.subtitle.copyWith(
                    color: MobileSurface.text,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _enabled
                      ? l10n.twoFaStatusEnabledHint
                      : l10n.twoFaStatusDisabledHint,
                  style: AppTypography.caption.copyWith(
                    color: MobileSurface.secondary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildActivateSection(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        MobilePrimaryAction(
          icon: Icons.qr_code_2_rounded,
          label: context.l10n.twoFaActivate,
          onPressed: _submitting ? null : _startEnroll,
        ),
      ],
    );
  }

  Widget _buildEnrollStep(BuildContext context) {
    final l10n = context.l10n;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        LeopardoQrCard(
          data: _qrUrl ?? '',
          title: l10n.twoFaScanPrompt,
          subtitle: _secret ?? '',
          copyLabel: l10n.twoFaCopySecret,
        ),
        const SizedBox(height: 16),
        Text(
          l10n.twoFaConfirmHint,
          style: AppTypography.bodySmall.copyWith(
            color: MobileSurface.secondary,
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _codeController,
          keyboardType: TextInputType.number,
          maxLength: 6,
          decoration: InputDecoration(
            labelText: l10n.twoFaCodeLabel,
            hintText: l10n.twoFaCodeHint,
            prefixIcon: const Icon(Icons.password_rounded),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
            ),
          ),
          onSubmitted: (_) => _confirmEnrollment(),
        ),
        const SizedBox(height: 10),
        MobilePrimaryAction(
          icon: Icons.lock_open_rounded,
          label: l10n.twoFaConfirm,
          onPressed: _submitting ? null : _confirmEnrollment,
        ),
      ],
    );
  }

  Widget _buildRecoverySection() {
    final l10n = context.l10n;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        GlassCard(
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                l10n.twoFaRecoveryTitle,
                style: AppTypography.subtitle.copyWith(
                  color: MobileSurface.text,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                l10n.twoFaRecoveryHint,
                style: AppTypography.bodySmall.copyWith(
                  color: MobileSurface.secondary,
                ),
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final code in _recoveryCodes)
                    ActionChip(
                      label: Text(
                        code,
                        style: const TextStyle(
                          fontFamily: 'monospace',
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      onPressed: () async {
                        final messenger = ScaffoldMessenger.of(context);
                        await Clipboard.setData(ClipboardData(text: code));
                        messenger.showSnackBar(
                          SnackBar(content: Text(l10n.twoFaCopied)),
                        );
                      },
                    ),
                ],
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () async {
                  final all = _recoveryCodes.join('\n');
                  final messenger = ScaffoldMessenger.of(context);
                  await Clipboard.setData(ClipboardData(text: all));
                  messenger.showSnackBar(
                    SnackBar(content: Text(l10n.twoFaAllCopied)),
                  );
                },
                icon: const Icon(Icons.copy_all_rounded),
                label: Text(l10n.twoFaCopyAll),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        Text(
          l10n.twoFaDoneHint,
          textAlign: TextAlign.center,
          style: AppTypography.caption.copyWith(
            color: AppColors.success,
          ),
        ),
      ],
    );
  }

  Widget _buildManageSection(BuildContext context) {
    final l10n = context.l10n;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (_recoveryCodes.isEmpty) ...[
          OutlinedButton.icon(
            onPressed: _submitting ? null : _regenerate,
            icon: const Icon(Icons.refresh_rounded),
            label: Text(l10n.twoFaRegenerate),
          ),
          const SizedBox(height: 10),
        ],
        OutlinedButton.icon(
          onPressed: _submitting ? null : _disable,
          style: OutlinedButton.styleFrom(
            foregroundColor: AppColors.danger,
            side: const BorderSide(color: AppColors.danger),
          ),
          icon: const Icon(Icons.gpp_bad_outlined),
          label: Text(l10n.twoFaDisable),
        ),
      ],
    );
  }
}

class _PolicyBanner extends StatelessWidget {
  const _PolicyBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.warning.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.warning.withValues(alpha: 0.4)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.policy_rounded, color: AppColors.warning),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: AppTypography.bodySmall.copyWith(
                color: MobileSurface.text,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
