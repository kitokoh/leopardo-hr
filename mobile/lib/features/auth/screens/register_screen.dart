import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';

/// Ecran d'information pour la creation de compte.
///
/// Leopardo RH fonctionne par invitation : un manager / RH envoie un lien
/// d'invitation (table `user_invitations`) et l'employe active son compte
/// avec un mot de passe. Il n'existe pas d'auto-inscription publique.
///
/// Cet ecran l'explique clairement et propose les deux chemins utiles :
///  - "J'ai deja recu une invitation" (retour vers la connexion)
///  - "Je veux etre contacte quand l'inscription libre sera ouverte" (capture
///    d'email locale, pas encore branchee au backend — UX placeholder).
class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  bool _submitted = false;

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  void _submit() {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() => _submitted = true);
  }

  @override
  Widget build(BuildContext context) {
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
              const _InvitationExplainerCard(),
              const SizedBox(height: 16),
              _RequestAccessCard(
                formKey: _formKey,
                controller: _emailController,
                submitted: _submitted,
                onSubmit: _submit,
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
          'Le compte est cree par votre employeur via une invitation.',
          textAlign: TextAlign.center,
          style:
              AppTypography.bodySmall.copyWith(color: AppColors.textMutedDark),
        ),
      ],
    );
  }
}

class _InvitationExplainerCard extends StatelessWidget {
  const _InvitationExplainerCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.cardDark,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.borderDark),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.verified_user_outlined, color: AppColors.rh, size: 22),
              const SizedBox(width: 8),
              Text(
                'Comment acceder a l\'application',
                style:
                    AppTypography.subtitle.copyWith(color: AppColors.textDark),
              ),
            ],
          ),
          const SizedBox(height: 14),
          const _InvitationStep(
            number: '1',
            text:
                'Votre manager ou votre RH ajoute votre email dans Leopardo RH.',
          ),
          const SizedBox(height: 10),
          const _InvitationStep(
            number: '2',
            text:
                'Vous recevez un email d\'invitation avec un lien d\'activation.',
          ),
          const SizedBox(height: 10),
          const _InvitationStep(
            number: '3',
            text:
                'Vous definissez votre mot de passe, puis vous vous connectez ici.',
          ),
        ],
      ),
    );
  }
}

class _InvitationStep extends StatelessWidget {
  const _InvitationStep({required this.number, required this.text});

  final String number;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 24,
          height: 24,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AppColors.rh.withValues(alpha: 0.15),
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.rh.withValues(alpha: 0.4)),
          ),
          child: Text(
            number,
            style: AppTypography.caption.copyWith(
              color: AppColors.rh,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            text,
            style: AppTypography.bodySmall.copyWith(color: AppColors.textDark),
          ),
        ),
      ],
    );
  }
}

class _RequestAccessCard extends StatelessWidget {
  const _RequestAccessCard({
    required this.formKey,
    required this.controller,
    required this.submitted,
    required this.onSubmit,
  });

  final GlobalKey<FormState> formKey;
  final TextEditingController controller;
  final bool submitted;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.cardDark,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.borderDark),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.schedule_outlined, color: AppColors.warning, size: 22),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Pas encore d\'entreprise ? Inscrivez-vous a la liste',
                  style: AppTypography.subtitle
                      .copyWith(color: AppColors.textDark),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            'L\'inscription libre pour les employes independants arrive. Laissez votre email pour etre prevenu des son ouverture.',
            style: AppTypography.bodySmall
                .copyWith(color: AppColors.textMutedDark),
          ),
          const SizedBox(height: 14),
          if (submitted)
            _SuccessTile(email: controller.text.trim())
          else
            Form(
              key: formKey,
              autovalidateMode: AutovalidateMode.onUserInteraction,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TextFormField(
                    controller: controller,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.done,
                    onFieldSubmitted: (_) => onSubmit(),
                    decoration: InputDecoration(
                      labelText: 'Votre email',
                      prefixIcon: const Icon(Icons.email_outlined),
                      filled: true,
                      fillColor: AppColors.bgDark,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(10),
                        borderSide: BorderSide(color: AppColors.borderDark),
                      ),
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
                  SizedBox(
                    height: 48,
                    child: ElevatedButton(
                      onPressed: onSubmit,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.rh,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                      ),
                      child: const Text('Me prevenir'),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _SuccessTile extends StatelessWidget {
  const _SuccessTile({required this.email});

  final String email;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.rh.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.rh.withValues(alpha: 0.35)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.check_circle_outline, color: AppColors.rh),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              email.isEmpty
                  ? 'Merci, vous etes dans la liste.'
                  : 'Merci, nous contacterons $email des l\'ouverture.',
              style:
                  AppTypography.bodySmall.copyWith(color: AppColors.textDark),
            ),
          ),
        ],
      ),
    );
  }
}
