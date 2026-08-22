import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_sign_in/google_sign_in.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_employee/features/user_auth/providers/user_auth_provider.dart';

class UserRegisterScreen extends ConsumerStatefulWidget {
  const UserRegisterScreen({super.key});

  @override
  ConsumerState<UserRegisterScreen> createState() => _UserRegisterScreenState();
}

class _UserRegisterScreenState extends ConsumerState<UserRegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _firstNameCtrl = TextEditingController();
  final _lastNameCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  bool _obscure = true;
  bool _googleLoading = false;

  @override
  void dispose() {
    _firstNameCtrl.dispose();
    _lastNameCtrl.dispose();
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    _phoneCtrl.dispose();
    super.dispose();
  }

  Future<void> _register() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    HapticFeedback.mediumImpact();

    final ok = await ref.read(userAuthProvider.notifier).register(
          firstName: _firstNameCtrl.text.trim(),
          lastName: _lastNameCtrl.text.trim(),
          email: _emailCtrl.text.trim(),
          password: _passwordCtrl.text,
          phone: _phoneCtrl.text.trim().isEmpty ? null : _phoneCtrl.text.trim(),
        );

    if (ok && mounted) {
      context.go('/personal-onboarding');
    }
  }

  Future<void> _googleSignIn() async {
    setState(() => _googleLoading = true);
    HapticFeedback.lightImpact();

    try {
      final googleSignIn = GoogleSignIn.instance;
      final account = await googleSignIn.authenticate();

      final ok = await ref.read(userAuthProvider.notifier).googleSignIn(
            idToken: account.authentication.idToken ?? '',
          );

      if (ok && mounted) {
        context.go('/personal-onboarding');
      }
    } catch (e) {
      if (mounted) {
        final l10n = context.l10n;
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(l10n.userAuthGoogleError(e.toString()))));
      }
    } finally {
      if (mounted) setState(() => _googleLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final state = ref.watch(userAuthProvider);
    final bg = AppColors.backgroundFor(context);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    ref.listen<UserAuthState>(userAuthProvider, (prev, next) {
      if (next.error != null && next.error != prev?.error) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(next.error!)));
      }
    });

    return Scaffold(
      backgroundColor: bg,
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              AppColors.tint(context, AppColors.ia, lightAlpha: 0.08),
              bg,
              AppColors.tint(context, AppColors.rh, lightAlpha: 0.04),
            ],
          ),
        ),
        child: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
            child: Column(
              children: [
                Align(
                  alignment: Alignment.centerLeft,
                  child: IconButton(
                    tooltip: l10n.authBackTooltip,
                    icon: const Icon(Icons.arrow_back),
                    onPressed: () {
                      if (context.canPop()) {
                        context.pop();
                      } else {
                        context.go('/welcome');
                      }
                    },
                  ),
                ),
                const SizedBox(height: 8),
                _buildHero(l10n, text, muted),
                const SizedBox(height: 18),
                _buildGoogleButton(l10n, muted),
                const SizedBox(height: 16),
                _buildDivider(l10n, muted),
                const SizedBox(height: 16),
                _buildForm(l10n, state, text, muted),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHero(AppLocalizations l10n, Color text, Color muted) {
    return Column(
      children: [
        Container(
          width: 64,
          height: 64,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: const LinearGradient(
              colors: [AppColors.ia, AppColors.rh],
            ),
          ),
          child: const Icon(
            Icons.person_add_outlined,
            color: Colors.white,
            size: 30,
          ),
        )
            .animate()
            .fadeIn(duration: 400.ms)
            .scale(begin: const Offset(0.8, 0.8), duration: 400.ms),
        const SizedBox(height: 12),
        Text(
          l10n.userAuthRegisterTitle,
          style: AppTypography.title.copyWith(color: text),
        ).animate().fadeIn(delay: 100.ms, duration: 300.ms),
        const SizedBox(height: 4),
        Text(
          l10n.userAuthRegisterSubtitleAlt,
          textAlign: TextAlign.center,
          style: AppTypography.bodySmall.copyWith(color: muted),
        ).animate().fadeIn(delay: 200.ms, duration: 300.ms),
      ],
    );
  }

  Widget _buildGoogleButton(AppLocalizations l10n, Color muted) {
    return SizedBox(
      width: double.infinity,
      child: OutlinedButton.icon(
        onPressed: _googleLoading ? null : _googleSignIn,
        icon: _googleLoading
            ? const SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.g_mobiledata, size: 24),
        label: Text(l10n.authContinueWithGoogle),
      ),
    ).animate().fadeIn(delay: 300.ms, duration: 300.ms).slideY(begin: 0.1);
  }

  Widget _buildDivider(AppLocalizations l10n, Color muted) {
    return Row(
      children: [
        Expanded(child: Divider(color: muted.withValues(alpha: 0.3))),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Text(
            l10n.commonOr,
            style: AppTypography.caption.copyWith(color: muted),
          ),
        ),
        Expanded(child: Divider(color: muted.withValues(alpha: 0.3))),
      ],
    );
  }

  Widget _buildForm(AppLocalizations l10n, UserAuthState state, Color text, Color muted) {
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: AppColors.surfaceFor(context),
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: AppColors.borderFor(context)),
      ),
      child: Form(
        key: _formKey,
        autovalidateMode: AutovalidateMode.onUserInteraction,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _firstNameCtrl,
                    textInputAction: TextInputAction.next,
                    decoration: InputDecoration(
                      labelText: l10n.userAuthFirstName,
                      prefixIcon: const Icon(Icons.person_outlined),
                    ),
                    validator: (v) =>
                        (v?.trim().isEmpty ?? true) ? l10n.commonRequired : null,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextFormField(
                    controller: _lastNameCtrl,
                    textInputAction: TextInputAction.next,
                    decoration: InputDecoration(
                      labelText: l10n.userAuthLastName,
                      prefixIcon: const Icon(Icons.person_outlined),
                    ),
                    validator: (v) =>
                        (v?.trim().isEmpty ?? true) ? l10n.commonRequired : null,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _emailCtrl,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              decoration: InputDecoration(
                labelText: l10n.authEmailLabel,
                prefixIcon: const Icon(Icons.email_outlined),
              ),
              validator: (v) {
                final email = v?.trim() ?? '';
                if (email.isEmpty) return l10n.authEmailRequired;
                if (!email.contains('@') || !email.contains('.')) {
                  return l10n.authEmailInvalid;
                }
                return null;
              },
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _phoneCtrl,
              keyboardType: TextInputType.phone,
              textInputAction: TextInputAction.next,
              decoration: InputDecoration(
                labelText: l10n.userAuthPhoneOptional,
                prefixIcon: const Icon(Icons.phone_outlined),
              ),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _passwordCtrl,
              obscureText: _obscure,
              textInputAction: TextInputAction.done,
              decoration: InputDecoration(
                labelText: l10n.authPasswordLabel,
                prefixIcon: const Icon(Icons.lock_outlined),
                suffixIcon: IconButton(
                  tooltip: l10n.authTogglePasswordVisibility,
                  icon: Icon(
                    _obscure ? Icons.visibility_off : Icons.visibility,
                  ),
                  onPressed: () => setState(() => _obscure = !_obscure),
                ),
              ),
              validator: (v) {
                if ((v ?? '').length < 8) {
                  return l10n.authPasswordTooShort;
                }
                return null;
              },
              onFieldSubmitted: (_) => _register(),
            ),
            const SizedBox(height: 22),
            ElevatedButton(
              onPressed: state.isLoading ? null : _register,
              child: state.isLoading
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : Text(l10n.userAuthRegisterButton),
            ),
            const SizedBox(height: 14),
            Center(
              child: TextButton(
                onPressed: () => context.go('/user-login'),
                child: Text(
                  l10n.userAuthAlreadyAccount,
                  style: TextStyle(color: AppColors.rh),
                ),
              ),
            ),
          ],
        ),
      ),
    ).animate().fadeIn(delay: 400.ms, duration: 400.ms).slideY(begin: 0.05);
  }
}
