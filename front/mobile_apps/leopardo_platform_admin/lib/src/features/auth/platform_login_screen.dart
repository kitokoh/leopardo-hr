import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';

import 'platform_auth_controller.dart';

class PlatformLoginScreen extends ConsumerStatefulWidget {
  const PlatformLoginScreen({super.key});

  @override
  ConsumerState<PlatformLoginScreen> createState() =>
      _PlatformLoginScreenState();
}

class _PlatformLoginScreenState extends ConsumerState<PlatformLoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _twoFactorController = TextEditingController();
  bool _obscurePassword = true;

  void _fillDemoAccount() {
    _emailController.text = 'admin@leopardo-rh.com';
    _passwordController.text = 'password123';
    _twoFactorController.clear();
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _twoFactorController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    await ref
        .read(platformAuthControllerProvider.notifier)
        .login(
          email: _emailController.text.trim(),
          password: _passwordController.text,
          twoFactorCode: _twoFactorController.text.trim(),
        );
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(platformAuthControllerProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const MobileIconBubble(
                      icon: Icons.admin_panel_settings_rounded,
                      color: AppColors.rh,
                      size: 58,
                    ),
                    const SizedBox(height: 20),
                    const Text(
                      'Leopardo Platform',
                      style: TextStyle(
                        color: MobileSurface.text,
                        fontSize: 30,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Cockpit mobile reserve a l administration de la plateforme.',
                      style: TextStyle(
                        color: MobileSurface.secondary,
                        fontSize: 14,
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 28),
                    if (auth.error != null) ...[
                      MobileErrorPanel(message: auth.error!),
                      const SizedBox(height: 14),
                    ],
                    if (auth.requiresTwoFactor) ...[
                      const MobilePanel(
                        padding: EdgeInsets.all(14),
                        child: Row(
                          children: [
                            Icon(
                              Icons.verified_user_rounded,
                              color: AppColors.warning,
                            ),
                            SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                'Ce compte protege la plateforme : saisir le code 2FA de l application authenticator.',
                                style: TextStyle(
                                  color: MobileSurface.secondary,
                                  height: 1.35,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 14),
                    ],
                    _PlatformTextField(
                      controller: _emailController,
                      label: 'Email super-admin',
                      icon: Icons.mail_outline_rounded,
                      keyboardType: TextInputType.emailAddress,
                      validator:
                          (value) =>
                              value == null || !value.contains('@')
                                  ? 'Email requis'
                                  : null,
                    ),
                    const SizedBox(height: 12),
                    _PlatformTextField(
                      controller: _passwordController,
                      label: 'Mot de passe',
                      icon: Icons.lock_outline_rounded,
                      obscureText: _obscurePassword,
                      suffixIcon: IconButton(
                        onPressed:
                            () => setState(
                              () => _obscurePassword = !_obscurePassword,
                            ),
                        icon: Icon(
                          _obscurePassword
                              ? Icons.visibility_rounded
                              : Icons.visibility_off_rounded,
                        ),
                      ),
                      validator:
                          (value) =>
                              value == null || value.length < 6
                                  ? 'Mot de passe requis'
                                  : null,
                    ),
                    const SizedBox(height: 12),
                    _PlatformTextField(
                      controller: _twoFactorController,
                      label: 'Code 2FA si active',
                      icon: Icons.password_rounded,
                      keyboardType: TextInputType.number,
                    ),
                    const SizedBox(height: 22),
                    MobilePrimaryAction(
                      icon: Icons.login_rounded,
                      label:
                          auth.isSubmitting ? 'Connexion...' : 'Se connecter',
                      onPressed: auth.isSubmitting ? null : _submit,
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: auth.isSubmitting ? null : _fillDemoAccount,
                      icon: const Icon(Icons.science_rounded),
                      label: const Text('Utiliser le compte demo'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: MobileSurface.secondary,
                        side: const BorderSide(color: MobileSurface.border),
                        padding: const EdgeInsets.symmetric(vertical: 13),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _PlatformTextField extends StatelessWidget {
  const _PlatformTextField({
    required this.controller,
    required this.label,
    required this.icon,
    this.keyboardType,
    this.obscureText = false,
    this.suffixIcon,
    this.validator,
  });

  final TextEditingController controller;
  final String label;
  final IconData icon;
  final TextInputType? keyboardType;
  final bool obscureText;
  final Widget? suffixIcon;
  final String? Function(String?)? validator;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      obscureText: obscureText,
      validator: validator,
      style: const TextStyle(color: MobileSurface.text),
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon),
        suffixIcon: suffixIcon,
        filled: true,
        fillColor: MobileSurface.surface,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
      ),
    );
  }
}
