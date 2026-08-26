// ignore_for_file: use_build_context_synchronously
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/features/auth/providers/auth_provider.dart';
import 'package:leopardo_core/l10n/l10n.dart';

/// Écran de vérification 2FA post-login (#5627).
///
/// Affiché lorsque le backend répond `mfa_challenge: true` à /auth/login.
/// Permet de saisir le code TOTP ou un code de récupération.
///
/// Compatible [leopardo_employee] et [leopardo_manager] (utilisé depuis
/// le [routerProvider] de chaque app via GoRoute `/2fa-challenge`).
class TwoFactorChallengeScreen extends ConsumerStatefulWidget {
  const TwoFactorChallengeScreen({super.key});

  @override
  ConsumerState<TwoFactorChallengeScreen> createState() =>
      _TwoFactorChallengeScreenState();
}

class _TwoFactorChallengeScreenState
    extends ConsumerState<TwoFactorChallengeScreen> {
  final _codeController = TextEditingController();
  bool _useRecovery = false;
  bool _obscureCode = false;

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);
    final l10n = context.l10n;
    final bg = AppColors.backgroundFor(context);

    // Écouter les erreurs pour les afficher via SnackBar.
    ref.listen<AuthState>(authProvider, (previous, next) {
      if (next.error != null && next.error != previous?.error) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.error!),
            behavior: SnackBarBehavior.floating,
            backgroundColor: AppColors.danger,
          ),
        );
      }
    });

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        backgroundColor: bg,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          tooltip: l10n.authBackTooltip,
          onPressed: () {
            // Annuler le challenge : on reset l'état MFA pour retourner au login.
            ref.read(authProvider.notifier).handleSessionExpired();
          },
        ),
        title: Text(l10n.twoFaChallengeTitle, style: AppTypography.subtitle),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // ── Icône ──────────────────────────────────────────────────
              Center(
                child: Container(
                  width: 64,
                  height: 64,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.tint(
                      context,
                      AppColors.rh,
                      lightAlpha: 0.12,
                    ),
                  ),
                  child: const Icon(
                    Icons.lock_outline_rounded,
                    size: 32,
                    color: AppColors.rh,
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // ── Titre et sous-titre ────────────────────────────────────
              Text(
                l10n.twoFaChallengeTitle,
                style: AppTypography.title,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              Text(
                l10n.twoFaChallengeSubtitle,
                style: AppTypography.bodySmall.copyWith(
                  color: AppColors.textSecondaryFor(context),
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 32),

              // ── Champ de saisie ────────────────────────────────────────
              TextField(
                controller: _codeController,
                keyboardType: _useRecovery
                    ? TextInputType.text
                    : TextInputType.number,
                inputFormatters: _useRecovery
                    ? []
                    : [
                        FilteringTextInputFormatter.digitsOnly,
                        LengthLimitingTextInputFormatter(6),
                      ],
                obscureText: _obscureCode,
                textAlign: TextAlign.center,
                style: AppTypography.title.copyWith(
                  letterSpacing: _useRecovery ? 0 : 6,
                ),
                decoration: InputDecoration(
                  hintText: _useRecovery
                      ? l10n.twoFaChallengeRecoveryHint
                      : l10n.twoFaChallengeCodeHint,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  suffixIcon: _useRecovery
                      ? IconButton(
                          icon: Icon(
                            _obscureCode
                                ? Icons.visibility_off_outlined
                                : Icons.visibility_outlined,
                          ),
                          onPressed: () =>
                              setState(() => _obscureCode = !_obscureCode),
                        )
                      : null,
                ),
                autofocus: true,
                onSubmitted: (_) => _submit(),
              ),
              const SizedBox(height: 24),

              // ── Bouton Vérifier ────────────────────────────────────────
              FilledButton(
                onPressed: authState.isLoading ? null : _submit,
                child: authState.isLoading
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Text(l10n.twoFaChallengeVerifyBtn),
              ),
              const SizedBox(height: 16),

              // ── Basculer vers code de récupération ─────────────────────
              TextButton(
                onPressed: () => setState(() {
                  _useRecovery = !_useRecovery;
                  _codeController.clear();
                  _obscureCode = _useRecovery;
                }),
                child: Text(
                  l10n.twoFaChallengeRecoveryToggle,
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.rh,
                    decoration: TextDecoration.underline,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _submit() async {
    final value = _codeController.text.trim();
    if (value.isEmpty) return;

    HapticFeedback.lightImpact();
    FocusScope.of(context).unfocus();

    await ref.read(authProvider.notifier).verifyMfaChallenge(
          code: _useRecovery ? null : value,
          recoveryCode: _useRecovery ? value : null,
        );
    // La navigation est gérée par le redirect GoRouter qui observe authState.
  }
}
