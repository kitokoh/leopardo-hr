import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_sign_in/google_sign_in.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/demo_user_bottom_sheet.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_employee/features/user_auth/providers/user_auth_provider.dart';

class UserLoginScreen extends ConsumerStatefulWidget {
  const UserLoginScreen({super.key});

  @override
  ConsumerState<UserLoginScreen> createState() => _UserLoginScreenState();
}

class _UserLoginScreenState extends ConsumerState<UserLoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  bool _obscure = true;
  bool _googleLoading = false;

  @override
  void dispose() {
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    HapticFeedback.mediumImpact();

    final ok = await ref
        .read(userAuthProvider.notifier)
        .login(_emailCtrl.text.trim(), _passwordCtrl.text);

    if (ok && mounted) {
      final user = ref.read(userAuthProvider).user;
      context.go(user?.personalOnboardingCompleted == true
          ? '/user-home'
          : '/personal-onboarding');
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
        final user = ref.read(userAuthProvider).user;
        context.go(user?.personalOnboardingCompleted == true
            ? '/user-home'
            : '/personal-onboarding');
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
              AppColors.tint(context, AppColors.rh, lightAlpha: 0.08),
              bg,
              AppColors.tint(context, AppColors.ia, lightAlpha: 0.04),
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
                const SizedBox(height: 16),
                _buildHero(l10n, text, muted),
                const SizedBox(height: 24),
                _buildGoogleButton(l10n),
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
              colors: [AppColors.rh, AppColors.rhDark],
            ),
          ),
          child: const Icon(Icons.login, color: Colors.white, size: 30),
        )
            .animate()
            .fadeIn(duration: 400.ms)
            .scale(begin: const Offset(0.8, 0.8), duration: 400.ms),
        const SizedBox(height: 12),
        Text(
          l10n.userAuthPersonalLogin,
          style: AppTypography.title.copyWith(color: text),
        ).animate().fadeIn(delay: 100.ms, duration: 300.ms),
        const SizedBox(height: 4),
        Text(
          l10n.userAuthLoginSubtitle,
          textAlign: TextAlign.center,
          style: AppTypography.bodySmall.copyWith(color: muted),
        ).animate().fadeIn(delay: 200.ms, duration: 300.ms),
      ],
    );
  }

  Widget _buildGoogleButton(AppLocalizations l10n) {
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
                if (!email.contains('@')) return l10n.authEmailInvalid;
                return null;
              },
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
              validator: (v) =>
                  (v ?? '').isEmpty ? l10n.authPasswordRequired : null,
              onFieldSubmitted: (_) => _login(),
            ),
            const SizedBox(height: 22),
            ElevatedButton(
              onPressed: state.isLoading ? null : _login,
              child: state.isLoading
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : Text(l10n.login),
            ),
            const SizedBox(height: 14),
            Center(
              child: TextButton(
                onPressed: () => context.go('/user-register'),
                child: Text(
                  l10n.userAuthNoAccount,
                  style: TextStyle(color: AppColors.ia),
                ),
              ),
            ),
            const SizedBox(height: 8),
            const Divider(),
            const SizedBox(height: 8),
            ElevatedButton.icon(
              onPressed: () async {
                final user = await showDemoUserBottomSheet(
                  context,
                  allowedRoles: {'employee'},
                );
                if (user != null) {
                  _emailCtrl.text = user.email;
                  _passwordCtrl.text = user.password;
                }
              },
              icon: const Icon(Icons.group_outlined),
              label: Text(l10n.authDemoAccess),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.rhDark,
                foregroundColor: Colors.white,
              ),
            ),
          ],
        ),
      ),
    ).animate().fadeIn(delay: 400.ms, duration: 400.ms).slideY(begin: 0.05);
  }
}
