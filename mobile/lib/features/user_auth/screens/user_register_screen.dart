import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_sign_in/google_sign_in.dart';

import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/features/user_auth/providers/user_auth_provider.dart';

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

    final ok = await ref
        .read(userAuthProvider.notifier)
        .register(
          firstName: _firstNameCtrl.text.trim(),
          lastName: _lastNameCtrl.text.trim(),
          email: _emailCtrl.text.trim(),
          password: _passwordCtrl.text,
          phone: _phoneCtrl.text.trim().isEmpty ? null : _phoneCtrl.text.trim(),
        );

    if (ok && mounted) {
      context.go('/user-home');
    }
  }

  Future<void> _googleSignIn() async {
    setState(() => _googleLoading = true);
    HapticFeedback.lightImpact();

    try {
      final account = await GoogleSignIn.instance.authenticate(
        scopeHint: ['email', 'profile'],
      );

      final ok = await ref
          .read(userAuthProvider.notifier)
          .googleSignIn(
            googleId: account.id,
            email: account.email,
            firstName: account.displayName?.split(' ').first ?? '',
            lastName: account.displayName?.split(' ').skip(1).join(' ') ?? '',
            avatarUrl: account.photoUrl,
          );

      if (ok && mounted) {
        context.go('/user-home');
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Erreur Google: $e')));
      }
    } finally {
      if (mounted) setState(() => _googleLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
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
                _buildHero(text, muted),
                const SizedBox(height: 18),
                _buildGoogleButton(muted),
                const SizedBox(height: 16),
                _buildDivider(muted),
                const SizedBox(height: 16),
                _buildForm(state, text, muted),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHero(Color text, Color muted) {
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
          'Creer un compte',
          style: AppTypography.title.copyWith(color: text),
        ).animate().fadeIn(delay: 100.ms, duration: 300.ms),
        const SizedBox(height: 4),
        Text(
          'Accedez a votre espace personnel et organisez vos documents.',
          textAlign: TextAlign.center,
          style: AppTypography.bodySmall.copyWith(color: muted),
        ).animate().fadeIn(delay: 200.ms, duration: 300.ms),
      ],
    );
  }

  Widget _buildGoogleButton(Color muted) {
    return SizedBox(
      width: double.infinity,
      child: OutlinedButton.icon(
        onPressed: _googleLoading ? null : _googleSignIn,
        icon:
            _googleLoading
                ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
                : const Icon(Icons.g_mobiledata, size: 24),
        label: const Text('Continuer avec Google'),
      ),
    ).animate().fadeIn(delay: 300.ms, duration: 300.ms).slideY(begin: 0.1);
  }

  Widget _buildDivider(Color muted) {
    return Row(
      children: [
        Expanded(child: Divider(color: muted.withValues(alpha: 0.3))),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Text(
            'ou',
            style: AppTypography.caption.copyWith(color: muted),
          ),
        ),
        Expanded(child: Divider(color: muted.withValues(alpha: 0.3))),
      ],
    );
  }

  Widget _buildForm(UserAuthState state, Color text, Color muted) {
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
                    decoration: const InputDecoration(
                      labelText: 'Prenom',
                      prefixIcon: Icon(Icons.person_outlined),
                    ),
                    validator:
                        (v) => (v?.trim().isEmpty ?? true) ? 'Requis' : null,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextFormField(
                    controller: _lastNameCtrl,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: 'Nom',
                      prefixIcon: Icon(Icons.person_outlined),
                    ),
                    validator:
                        (v) => (v?.trim().isEmpty ?? true) ? 'Requis' : null,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _emailCtrl,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(
                labelText: 'Email',
                prefixIcon: Icon(Icons.email_outlined),
              ),
              validator: (v) {
                final email = v?.trim() ?? '';
                if (email.isEmpty) return 'Email requis';
                if (!email.contains('@') || !email.contains('.')) {
                  return 'Email invalide';
                }
                return null;
              },
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _phoneCtrl,
              keyboardType: TextInputType.phone,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(
                labelText: 'Telephone (optionnel)',
                prefixIcon: Icon(Icons.phone_outlined),
              ),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _passwordCtrl,
              obscureText: _obscure,
              textInputAction: TextInputAction.done,
              decoration: InputDecoration(
                labelText: 'Mot de passe',
                prefixIcon: const Icon(Icons.lock_outlined),
                suffixIcon: IconButton(
                  icon: Icon(
                    _obscure ? Icons.visibility_off : Icons.visibility,
                  ),
                  onPressed: () => setState(() => _obscure = !_obscure),
                ),
              ),
              validator: (v) {
                if ((v ?? '').length < 8) {
                  return '8 caracteres minimum';
                }
                return null;
              },
              onFieldSubmitted: (_) => _register(),
            ),
            const SizedBox(height: 22),
            ElevatedButton(
              onPressed: state.isLoading ? null : _register,
              child:
                  state.isLoading
                      ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                      : const Text('Creer mon compte'),
            ),
            const SizedBox(height: 14),
            Center(
              child: TextButton(
                onPressed: () => context.go('/user-login'),
                child: Text(
                  'Deja un compte ? Se connecter',
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
