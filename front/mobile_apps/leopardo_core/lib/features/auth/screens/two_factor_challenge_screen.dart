// ignore_for_file: use_build_context_synchronously

/// Issue #5627 — Écran de challenge TOTP lors du login avec 2FA actif.
///
/// Affiché quand le backend répond { mfa_challenge: true, mfa_challenge_token }
/// après /auth/login. L'utilisateur saisit son code TOTP (ou un code de
/// récupération) puis appelle POST /auth/2fa/verify pour ouvrir sa session.
library;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/l10n/l10n.dart';

/// Callback appelé après vérification réussie : le caller doit compléter
/// l'initialisation de session (charger /auth/me, etc.).
typedef OnMfaVerified = Future<void> Function(String challengeToken, String code, String? recoveryCode, bool rememberDevice);

class TwoFactorChallengeScreen extends ConsumerStatefulWidget {
  const TwoFactorChallengeScreen({
    super.key,
    required this.challengeToken,
    required this.onVerified,
    required this.onBack,
  });

  final String challengeToken;
  final OnMfaVerified onVerified;
  final VoidCallback onBack;

  @override
  ConsumerState<TwoFactorChallengeScreen> createState() =>
      _TwoFactorChallengeScreenState();
}

class _TwoFactorChallengeScreenState
    extends ConsumerState<TwoFactorChallengeScreen> {
  final _codeController = TextEditingController();
  bool _isRecovery = false;
  bool _rememberDevice = false;
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final code = _codeController.text.trim();
    if (code.isEmpty) return;

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await widget.onVerified(
        widget.challengeToken,
        _isRecovery ? '' : code,
        _isRecovery ? code : null,
        _rememberDevice,
      );
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString().replaceFirst('Exception: ', '');
        _submitting = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: AppBar(
        backgroundColor: MobileSurface.background,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.text),
          tooltip: context.l10n.back,
          onPressed: widget.onBack,
        ),
        title: Text(
          'Authentification à deux facteurs',
          style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
        ),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // En-tête
              const Icon(
                Icons.lock_outline_rounded,
                size: 48,
                color: MobileSurface.secondary,
              ),
              const SizedBox(height: 16),
              Text(
                _isRecovery ? 'Code de récupération' : 'Code de vérification',
                style: AppTypography.title
                    .copyWith(color: MobileSurface.text),
              ),
              const SizedBox(height: 8),
              Text(
                _isRecovery
                    ? 'Saisissez l\'un de vos codes de récupération à usage unique.'
                    : 'Saisissez le code à 6 chiffres de votre application authenticator.',
                style:
                    AppTypography.body.copyWith(color: MobileSurface.secondary),
              ),
              const SizedBox(height: 28),

              // Champ de saisie
              TextField(
                controller: _codeController,
                keyboardType: _isRecovery
                    ? TextInputType.text
                    : TextInputType.number,
                textAlign: TextAlign.center,
                maxLength: _isRecovery ? 36 : 8,
                autofocus: true,
                style: AppTypography.title.copyWith(
                  color: MobileSurface.text,
                  letterSpacing: 6,
                ),
                decoration: InputDecoration(
                  counterText: '',
                  hintText: _isRecovery ? 'xxxxxxxx' : '123456',
                  hintStyle: AppTypography.body
                      .copyWith(color: MobileSurface.disabled),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
                onSubmitted: (_) => _submit(),
              ),
              const SizedBox(height: 16),

              // Se souvenir de cet appareil
              if (!_isRecovery)
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _rememberDevice,
                  onChanged: (v) =>
                      setState(() => _rememberDevice = v ?? false),
                  title: Text(
                    'Se souvenir de cet appareil (30 jours)',
                    style: AppTypography.body
                        .copyWith(color: MobileSurface.text),
                  ),
                ),

              // Erreur
              if (_error != null) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.red.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.red.withValues(alpha: 0.3)),
                  ),
                  child: Text(
                    _error!,
                    style: AppTypography.body.copyWith(color: Colors.red),
                  ),
                ),
              ],

              const SizedBox(height: 24),

              // Bouton Valider
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _submitting ? null : _submit,
                  child: _submitting
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Confirmer'),
                ),
              ),

              const SizedBox(height: 12),

              // Bascule TOTP ↔ code de récupération
              Center(
                child: TextButton(
                  onPressed: () => setState(() {
                    _isRecovery = !_isRecovery;
                    _codeController.clear();
                    _error = null;
                  }),
                  child: Text(
                    _isRecovery
                        ? 'Utiliser mon code TOTP à la place'
                        : 'Utiliser un code de récupération',
                    style: AppTypography.body.copyWith(
                      color: MobileSurface.secondary,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
