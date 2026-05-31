import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/demo_user_bottom_sheet.dart';
import 'package:leopardo_manager/features/auth/providers/auth_provider.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);
    final bg = AppColors.backgroundFor(context);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);
    final compact = MediaQuery.of(context).size.height < 700;

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
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              AppColors.tint(context, AppColors.rh, lightAlpha: 0.10),
              bg,
            ],
          ),
        ),
        child: SafeArea(
          child: SingleChildScrollView(
            padding: EdgeInsets.fromLTRB(24, compact ? 12 : 20, 24, 28),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // ── Retour ────────────────────────────────────────────────
                Align(
                  alignment: Alignment.centerLeft,
                  child: IconButton(
                    icon: const Icon(Icons.arrow_back_rounded),
                    tooltip: 'Retour',
                    onPressed: () {
                      if (context.canPop()) {
                        context.pop();
                      } else {
                        context.go('/welcome');
                      }
                    },
                  ),
                ),
                SizedBox(height: compact ? 8 : 16),

                // ── En-tête compact ───────────────────────────────────────
                Row(
                  children: [
                    Container(
                      width: 46,
                      height: 46,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        gradient: const LinearGradient(
                          colors: [AppColors.rh, AppColors.rhDark],
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.rh.withValues(alpha: 0.25),
                            blurRadius: 12,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                      child: const Center(
                        child: Text(
                          'L',
                          style: TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 22,
                            color: Colors.white,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Leopardo RH',
                          style: AppTypography.title.copyWith(color: text),
                        ),
                        Text(
                          'Connexion Manager / RH',
                          style: AppTypography.caption.copyWith(color: muted),
                        ),
                      ],
                    ),
                  ],
                ),
                SizedBox(height: compact ? 24 : 32),

                // ── Formulaire ────────────────────────────────────────────
                Form(
                  key: _formKey,
                  autovalidateMode: AutovalidateMode.onUserInteraction,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextFormField(
                        controller: _emailController,
                        keyboardType: TextInputType.emailAddress,
                        textInputAction: TextInputAction.next,
                        decoration: _inputDecoration(
                          context,
                          label: 'Email',
                          icon: Icons.email_outlined,
                        ),
                        validator: (value) {
                          final v = value?.trim() ?? '';
                          if (v.isEmpty) return 'Email obligatoire';
                          if (!v.contains('@') || !v.contains('.')) {
                            return 'Email invalide';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 14),
                      TextFormField(
                        controller: _passwordController,
                        obscureText: _obscurePassword,
                        textInputAction: TextInputAction.done,
                        onFieldSubmitted: (_) => _submit(),
                        decoration: _inputDecoration(
                          context,
                          label: 'Mot de passe',
                          icon: Icons.lock_outline_rounded,
                          suffix: IconButton(
                            icon: Icon(
                              _obscurePassword
                                  ? Icons.visibility_off_rounded
                                  : Icons.visibility_rounded,
                              color: muted,
                            ),
                            onPressed: () => setState(
                              () => _obscurePassword = !_obscurePassword,
                            ),
                          ),
                        ),
                        validator: (value) {
                          if ((value ?? '').isEmpty) {
                            return 'Mot de passe obligatoire';
                          }
                          if ((value ?? '').length < 4) {
                            return 'Mot de passe trop court';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 22),

                      // Bouton principal
                      SizedBox(
                        height: 52,
                        child: ElevatedButton(
                          onPressed: authState.isLoading ? null : _submit,
                          child: authState.isLoading
                              ? const SizedBox(
                                  width: 22,
                                  height: 22,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2.5,
                                    color: Colors.white,
                                  ),
                                )
                              : const Text(
                                  'Se connecter',
                                  style: TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                        ),
                      ),
                      const SizedBox(height: 12),

                      // Google Sign-In
                      SizedBox(
                        height: 48,
                        child: OutlinedButton.icon(
                          onPressed: authState.isLoading
                              ? null
                              : () {
                                  HapticFeedback.lightImpact();
                                  ref
                                      .read(authProvider.notifier)
                                      .loginWithGoogle();
                                },
                          icon: const Icon(Icons.login_rounded, size: 18),
                          label: const Text('Continuer avec Google'),
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Lien activation
                      Center(
                        child: TextButton(
                          onPressed: () => context.go('/register'),
                          child: const Text("Activer mon acces manager"),
                        ),
                      ),
                      const SizedBox(height: 8),

                      // Séparateur
                      Row(
                        children: [
                          Expanded(
                            child: Divider(
                              color: AppColors.borderFor(context),
                            ),
                          ),
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 12),
                            child: Text(
                              'ou',
                              style: AppTypography.caption
                                  .copyWith(color: muted),
                            ),
                          ),
                          Expanded(
                            child: Divider(
                              color: AppColors.borderFor(context),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),

                      // Demo
                      SizedBox(
                        height: 48,
                        child: ElevatedButton.icon(
                          onPressed: () async {
                            final user = await showDemoUserBottomSheet(
                              context,
                              allowedRoles: {'manager'},
                            );
                            if (user != null) {
                              _emailController.text = user.email;
                              _passwordController.text = user.password;
                              await ref
                                  .read(authProvider.notifier)
                                  .login(user.email, user.password);
                            }
                          },
                          icon: const Icon(Icons.science_outlined, size: 18),
                          label: const Text('Tester avec un compte demo'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.rhDark,
                            foregroundColor: Colors.white,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  InputDecoration _inputDecoration(
    BuildContext context, {
    required String label,
    required IconData icon,
    Widget? suffix,
  }) {
    return InputDecoration(
      labelText: label,
      prefixIcon: Icon(icon),
      suffixIcon: suffix,
    );
  }

  void _submit() {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    HapticFeedback.lightImpact();
    FocusScope.of(context).unfocus();
    ref
        .read(authProvider.notifier)
        .login(_emailController.text.trim(), _passwordController.text);
  }
}
