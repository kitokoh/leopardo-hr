import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';

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
    final background = AppColors.backgroundFor(context);

    ref.listen<AuthState>(authProvider, (previous, next) {
      if (next.error != null && next.error != previous?.error) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(next.error!)));
      }
    });

    return Scaffold(
      backgroundColor: background,
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              AppColors.tint(context, AppColors.rh, lightAlpha: 0.10),
              background,
              AppColors.tint(context, AppColors.ia, lightAlpha: 0.05),
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
                const SizedBox(height: 8),
                const _LoginHero(),
                const SizedBox(height: 18),
                Container(
                  padding: const EdgeInsets.all(22),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceFor(context),
                    borderRadius: BorderRadius.circular(30),
                    border: Border.all(color: AppColors.borderFor(context)),
                  ),
                  child: Form(
                    key: _formKey,
                    autovalidateMode: AutovalidateMode.onUserInteraction,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          'Connexion employe',
                          style: AppTypography.title.copyWith(
                            color: AppColors.textPrimaryFor(context),
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Retrouvez votre home Leo, vos modules RH actifs et vos actions du jour sans passer par un dashboard froid.',
                          style: AppTypography.bodySmall.copyWith(
                            color: AppColors.textSecondaryFor(context),
                          ),
                        ),
                        const SizedBox(height: 22),
                        _BenefitStrip(),
                        const SizedBox(height: 20),
                        TextFormField(
                          controller: _emailController,
                          keyboardType: TextInputType.emailAddress,
                          textInputAction: TextInputAction.next,
                          decoration: _fieldDecoration(
                            context,
                            label: 'Email',
                            icon: Icons.email_outlined,
                          ),
                          validator: (value) {
                            final email = value?.trim() ?? '';
                            if (email.isEmpty) return 'Email obligatoire';
                            if (!email.contains('@') || !email.contains('.')) {
                              return 'Email invalide';
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: 16),
                        TextFormField(
                          controller: _passwordController,
                          obscureText: _obscurePassword,
                          textInputAction: TextInputAction.done,
                          onFieldSubmitted: (_) => _submit(),
                          decoration: _fieldDecoration(
                            context,
                            label: 'Mot de passe',
                            icon: Icons.lock_outline,
                            suffix: IconButton(
                              icon: Icon(
                                _obscurePassword
                                    ? Icons.visibility_off
                                    : Icons.visibility,
                                color: AppColors.textSecondaryFor(context),
                              ),
                              tooltip: _obscurePassword
                                  ? 'Afficher le mot de passe'
                                  : 'Masquer le mot de passe',
                              onPressed: () {
                                setState(() {
                                  _obscurePassword = !_obscurePassword;
                                });
                              },
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
                        ElevatedButton(
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
                              : const Text('Entrer dans Leopardo RH'),
                        ),
                        const SizedBox(height: 16),
                        OutlinedButton.icon(
                          onPressed: authState.isLoading
                              ? null
                              : () => ref
                                  .read(authProvider.notifier)
                                  .loginWithGoogle(),
                          icon: const Icon(Icons.login),
                          label: const Text('Continuer avec Google'),
                        ),
                        const SizedBox(height: 14),
                        Text(
                          'Votre entreprise vous a invite ? Activez d abord votre acces depuis l email recu, puis revenez vous connecter ici.',
                          textAlign: TextAlign.center,
                          style: AppTypography.caption.copyWith(
                            color: AppColors.textSecondaryFor(context),
                          ),
                        ),
                        const SizedBox(height: 10),
                        TextButton(
                          onPressed: () => context.go('/register'),
                          child: const Text('Je n ai pas encore d invitation'),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  InputDecoration _fieldDecoration(
    BuildContext context, {
    required String label,
    required IconData icon,
    Widget? suffix,
  }) {
    return InputDecoration(
      labelText: label,
      prefixIcon: Icon(icon, color: AppColors.textSecondaryFor(context)),
      suffixIcon: suffix,
    );
  }

  void _submit() {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    FocusScope.of(context).unfocus();
    ref
        .read(authProvider.notifier)
        .login(_emailController.text.trim(), _passwordController.text);
  }
}

class _LoginHero extends StatelessWidget {
  const _LoginHero();

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: AppColors.surfaceFor(context),
        borderRadius: BorderRadius.circular(30),
        border: Border.all(color: AppColors.borderFor(context)),
        boxShadow: [
          BoxShadow(
            color: AppColors.rh.withValues(alpha: 0.06),
            blurRadius: 28,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 58,
                height: 58,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: const LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [AppColors.rh, AppColors.rhDark],
                  ),
                ),
                child: const Center(
                  child: Text(
                    'L',
                    style: TextStyle(
                      fontFamily: AppTypography.fontFamily,
                      fontWeight: FontWeight.w700,
                      fontSize: 28,
                      color: Colors.white,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Leopardo RH',
                      style: AppTypography.title.copyWith(color: text),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'L experience mobile des equipes terrain.',
                      style: AppTypography.bodySmall.copyWith(color: muted),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          Text(
            'Ouvrez votre journee avec une home claire et guidee.',
            style: AppTypography.display.copyWith(color: text, fontSize: 30),
          ),
          const SizedBox(height: 10),
          Text(
            'Connexion, modules actifs, pointage et suivi personnel se retrouvent dans un langage plus simple, plus humain et plus moderne.',
            style: AppTypography.body.copyWith(color: muted),
          ),
        ],
      ),
    );
  }
}

class _BenefitStrip extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [
        _BenefitPill(
          icon: Icons.fingerprint,
          label: 'Pointage',
          color: AppColors.rh,
        ),
        _BenefitPill(
          icon: Icons.stacked_bar_chart,
          label: 'Mon mois',
          color: AppColors.info,
        ),
        _BenefitPill(
          icon: Icons.auto_awesome,
          label: 'Leo',
          color: AppColors.ia,
        ),
      ],
    );
  }
}

class _BenefitPill extends StatelessWidget {
  const _BenefitPill({
    required this.icon,
    required this.label,
    required this.color,
  });

  final IconData icon;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.tint(
          context,
          color,
          lightAlpha: 0.16,
          darkAlpha: 0.24,
        ),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: color),
          const SizedBox(width: 6),
          Text(
            label,
            style: AppTypography.caption.copyWith(color: color),
          ),
        ],
      ),
    );
  }
}
