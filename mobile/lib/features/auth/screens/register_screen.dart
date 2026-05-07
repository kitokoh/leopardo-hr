import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _firstNameController = TextEditingController();
  final _lastNameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();

  @override
  void dispose() {
    _firstNameController.dispose();
    _lastNameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }
    HapticFeedback.lightImpact();
    FocusScope.of(context).unfocus();

    final success = await ref
        .read(authProvider.notifier)
        .register(
          firstName: _firstNameController.text.trim(),
          lastName: _lastNameController.text.trim(),
          email: _emailController.text.trim(),
          password: _passwordController.text,
        );

    if (success && mounted) {
      context.go('/');
    }
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);
    return Scaffold(
      backgroundColor: AppColors.bgDark,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
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
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(24, 0, 24, 32),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const _RegisterHero(),
              const SizedBox(height: 28),
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
                        'Créer votre compte',
                        style: AppTypography.subtitle.copyWith(
                          color: AppColors.textPrimaryFor(context),
                        ),
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _firstNameController,
                        textInputAction: TextInputAction.next,
                        decoration: _fieldDecoration(
                          context,
                          label: 'Prénom',
                          icon: Icons.person_outline,
                        ),
                        validator:
                            (v) => (v ?? '').isEmpty ? 'Obligatoire' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _lastNameController,
                        textInputAction: TextInputAction.next,
                        decoration: _fieldDecoration(
                          context,
                          label: 'Nom',
                          icon: Icons.person_outline,
                        ),
                        validator:
                            (v) => (v ?? '').isEmpty ? 'Obligatoire' : null,
                      ),
                      const SizedBox(height: 12),
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
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _passwordController,
                        obscureText: true,
                        textInputAction: TextInputAction.done,
                        decoration: _fieldDecoration(
                          context,
                          label: 'Mot de passe',
                          icon: Icons.lock_outline,
                        ),
                        validator:
                            (v) =>
                                (v ?? '').length < 8
                                    ? '8 caractères minimum'
                                    : null,
                      ),
                      const SizedBox(height: 24),
                      ElevatedButton(
                        onPressed: authState.isLoading ? null : _submit,
                        child:
                            authState.isLoading
                                ? const SizedBox(
                                  height: 20,
                                  width: 20,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Colors.white,
                                    semanticsLabel: 'Création de compte en cours...',
                                  ),
                                )
                                : const Text('Créer mon compte'),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 24),
              Center(
                child: TextButton(
                  onPressed: () => context.go('/login'),
                  child: const Text(
                    'J\'ai deja un compte, me connecter',
                    style: TextStyle(color: AppColors.rh),
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

class _RegisterHero extends StatelessWidget {
  const _RegisterHero();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          width: 64,
          height: 64,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [AppColors.ia, AppColors.iaDark],
            ),
            boxShadow: [
              BoxShadow(
                color: AppColors.ia.withValues(alpha: 0.35),
                blurRadius: 20,
                spreadRadius: 1,
              ),
            ],
          ),
          child: const Icon(
            Icons.mark_email_read_outlined,
            color: Colors.white,
            size: 30,
          ),
        ),
        const SizedBox(height: 16),
        Text(
          'Creer un compte Leopardo RH',
          textAlign: TextAlign.center,
          style: AppTypography.title.copyWith(color: AppColors.textDark),
        ),
        const SizedBox(height: 6),
        Text(
          'Rejoignez la plateforme RH moderne.',
          textAlign: TextAlign.center,
          style: AppTypography.bodySmall.copyWith(
            color: AppColors.textMutedDark,
          ),
        ),
      ],
    );
  }
}

extension on _RegisterScreenState {
  InputDecoration _fieldDecoration(
    BuildContext context, {
    required String label,
    required IconData icon,
  }) {
    return InputDecoration(
      labelText: label,
      prefixIcon: Icon(icon, color: AppColors.textSecondaryFor(context)),
    );
  }
}
