import 'dart:io';
import 'dart:ui' show PlatformDispatcher;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/leopardo_qr_card.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/models/notification_preferences.dart';
import 'package:leopardo_employee/features/auth/providers/auth_provider.dart';
import 'package:leopardo_employee/features/settings/data/biometric_enrollment.dart';
import 'package:leopardo_employee/features/settings/data/settings_repository.dart';
import 'package:local_auth/local_auth.dart';

class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  final _profileFormKey = GlobalKey<FormState>();
  final _passwordFormKey = GlobalKey<FormState>();

  late final TextEditingController _firstNameController;
  late final TextEditingController _lastNameController;
  late final TextEditingController _emailController;
  late final TextEditingController _personalEmailController;
  late final TextEditingController _recoveryEmailController;
  late final TextEditingController _personalPhoneController;
  final TextEditingController _currentPasswordController =
      TextEditingController();
  final TextEditingController _newPasswordController = TextEditingController();
  final TextEditingController _confirmPasswordController =
      TextEditingController();
  final TextEditingController _biometricNoteController =
      TextEditingController();
  final TextEditingController _fingerprintDeviceController =
      TextEditingController();
  final TextEditingController _companyQrController = TextEditingController();
  static const Map<String, String> _languageLabels = {
    'fr': 'Francais',
    'ar': 'العربية',
    'tr': 'Turkce',
    'en': 'English',
  };

  bool _profileSaving = false;
  bool _passwordSaving = false;
  bool _preferencesSaving = false;
  bool _biometricSubmitting = false;
  bool _languageSaving = false;
  bool _biometricEnabled = false;
  bool _fingerprintEnabled = false;
  bool _faceEnabled = false;
  bool _attendanceConsent = false;
  File? _selectedFaceImage;
  BiometricEnrollment? _latestEnrollment;
  String _selectedLanguage = 'fr';

  @override
  void initState() {
    super.initState();
    final employee = ref.read(authProvider).employee;
    final deviceLanguage =
        PlatformDispatcher.instance.locale.languageCode.toLowerCase();
    _firstNameController = TextEditingController(
      text: employee?.firstName ?? '',
    );
    _lastNameController = TextEditingController(text: employee?.lastName ?? '');
    _emailController = TextEditingController(text: employee?.email ?? '');
    _personalEmailController = TextEditingController(
      text: employee?.personalEmail ?? '',
    );
    _recoveryEmailController = TextEditingController(
      text: employee?.recoveryEmail ?? '',
    );
    _personalPhoneController = TextEditingController(
      text: employee?.personalPhone ?? '',
    );
    _selectedLanguage =
        _languageLabels.containsKey(employee?.language)
            ? employee!.language
            : (_languageLabels.containsKey(deviceLanguage)
                ? deviceLanguage
                : 'fr');
    _loadLocalSettings();
    _loadEnrollmentStatus();
  }

  Future<void> _loadLocalSettings() async {
    final settings =
        await ref.read(settingsRepositoryProvider).loadLocalBiometricSettings();
    if (!mounted) return;

    setState(() {
      _biometricEnabled = settings.biometricEnabled;
      _fingerprintEnabled = settings.fingerprintEnabled;
      _faceEnabled = settings.faceEnabled;
      _attendanceConsent = settings.attendanceConsent;
      _biometricNoteController.text = settings.biometricNote;
    });
  }

  Future<void> _loadEnrollmentStatus() async {
    try {
      final enrollment =
          await ref.read(settingsRepositoryProvider).loadBiometricEnrollment();
      if (!mounted) return;
      setState(() {
        _latestEnrollment = enrollment;
      });
    } catch (_) {
      if (!mounted) return;
    }
  }

  @override
  void dispose() {
    _firstNameController.dispose();
    _lastNameController.dispose();
    _emailController.dispose();
    _personalEmailController.dispose();
    _recoveryEmailController.dispose();
    _personalPhoneController.dispose();
    _currentPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    _biometricNoteController.dispose();
    _fingerprintDeviceController.dispose();
    _companyQrController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Compte',
        subtitle: 'Profil, langue et securite',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          _buildIdentityCard(context),
          const SizedBox(height: 20),
          _buildAccountOverviewSection(),
          const SizedBox(height: 20),
          _buildProfileSection(context, authState),
          const SizedBox(height: 20),
          _buildQrOnboardingSection(context),
          const SizedBox(height: 20),
          _buildCareerSection(),
          const SizedBox(height: 20),
          _buildCabinetSection(),
          const SizedBox(height: 20),
          _buildLanguageSection(context, authState),
          const SizedBox(height: 20),
          _buildNotificationSection(context),
          const SizedBox(height: 20),
          _buildPasswordSection(context, authState),
          const SizedBox(height: 20),
          _buildBiometricSection(context),
          const SizedBox(height: 28),
          _buildLogoutSection(context),
          SizedBox(height: MediaQuery.of(context).padding.bottom + 8),
        ],
      ),
    );
  }

  Widget _buildIdentityCard(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Acces mobile',
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 8),
          Text(
            'Profil employe: acces au pointage, a l historique personnel et aux parametres de preparation biometrie.',
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildProfileSection(BuildContext context, AuthState authState) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Form(
        key: _profileFormKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Mon profil',
              style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _firstNameController,
              decoration: const InputDecoration(labelText: 'Prenom'),
              validator:
                  (value) =>
                      (value == null || value.trim().isEmpty)
                          ? 'Prenom requis'
                          : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _lastNameController,
              decoration: const InputDecoration(labelText: 'Nom'),
              validator:
                  (value) =>
                      (value == null || value.trim().isEmpty)
                          ? 'Nom requis'
                          : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(labelText: 'Email'),
              validator: (value) {
                final trimmed = value?.trim() ?? '';
                if (trimmed.isEmpty) return 'Email requis';
                if (!trimmed.contains('@') || !trimmed.contains('.')) {
                  return 'Email invalide';
                }
                return null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _personalEmailController,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(
                labelText: 'Email personnel',
                helperText: 'Optionnel, conserve votre compte hors entreprise',
              ),
              validator: _optionalEmailValidator,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _recoveryEmailController,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(
                labelText: 'Email de recuperation',
                helperText: 'Optionnel pour recuperer l acces',
              ),
              validator: _optionalEmailValidator,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _personalPhoneController,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                labelText: 'Telephone personnel',
                helperText: 'Optionnel, visible selon vos choix futurs',
              ),
            ),
            const SizedBox(height: 16),
            if (authState.error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text(
                  authState.error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            FilledButton(
              onPressed: _profileSaving ? null : _saveProfile,
              child: Text(
                _profileSaving ? 'Enregistrement...' : 'Enregistrer le profil',
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAccountOverviewSection() {
    final items = [
      (
        icon: Icons.badge_outlined,
        color: AppColors.rh,
        title: 'Identite portable',
        subtitle:
            'Email personnel, recuperation et telephone restent attaches au compte.',
      ),
      (
        icon: Icons.work_history_outlined,
        color: AppColors.info,
        title: 'Parcours',
        subtitle: 'Historique entreprise, poste, statut et disponibilite.',
      ),
      (
        icon: Icons.folder_copy_outlined,
        color: AppColors.warning,
        title: 'Placard numerique',
        subtitle:
            'CV, contrats, diplomes et documents avec visibilite controlee.',
      ),
      (
        icon: Icons.qr_code_2_rounded,
        color: AppColors.rhDark,
        title: 'QR onboarding',
        subtitle: 'Partager votre profil ou scanner une entreprise.',
      ),
      (
        icon: Icons.fingerprint_rounded,
        color: AppColors.info,
        title: 'Biometrie kiosk',
        subtitle: 'Preparer doigt et visage pour les bornes terrain.',
      ),
      (
        icon: Icons.notifications_active_outlined,
        color: AppColors.danger,
        title: 'Notifications',
        subtitle: 'Canaux, heures calmes et alertes operationnelles.',
      ),
    ];

    return MobilePanel(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Vue d ensemble',
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 6),
          Text(
            'Votre compte reste utile meme quand vous changez d entreprise.',
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
          const SizedBox(height: 14),
          ...items.map(
            (item) => MobileListCard(
              icon: item.icon,
              iconColor: item.color,
              title: item.title,
              subtitle: item.subtitle,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCareerSection() {
    return FutureBuilder<EmployeeCareer>(
      future: ref.read(settingsRepositoryProvider).loadCareer(),
      builder: (context, snapshot) {
        final career = snapshot.data;
        final timeline = career?.timeline ?? const <EmployeeCareerEntry>[];

        return Container(
          padding: const EdgeInsets.all(20),
          decoration: MobileSurface.cardDecoration(),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const MobileIconBubble(
                    icon: Icons.work_history_rounded,
                    color: AppColors.rh,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Parcours professionnel',
                          style: AppTypography.subtitle.copyWith(
                            color: MobileSurface.text,
                          ),
                        ),
                        Text(
                          career?.availableForNewCompany == true
                              ? 'Disponible pour une nouvelle entreprise'
                              : 'Rattache a ${career?.currentCompanyName ?? 'votre entreprise'}',
                          style: AppTypography.caption.copyWith(
                            color: MobileSurface.secondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              if (snapshot.connectionState == ConnectionState.waiting)
                const LinearProgressIndicator(minHeight: 2)
              else if (timeline.isEmpty)
                Text(
                  'Aucun parcours enregistre pour le moment.',
                  style: AppTypography.bodySmall.copyWith(
                    color: MobileSurface.secondary,
                  ),
                )
              else
                ...timeline.take(3).map(_buildCareerRow),
            ],
          ),
        );
      },
    );
  }

  Widget _buildQrOnboardingSection(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const MobileIconBubble(
                icon: Icons.qr_code_2_rounded,
                color: AppColors.rh,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'QR professionnel',
                      style: AppTypography.subtitle.copyWith(
                        color: MobileSurface.text,
                      ),
                    ),
                    Text(
                      'Partagez votre profil ou scannez le QR d une entreprise.',
                      style: AppTypography.caption.copyWith(
                        color: MobileSurface.secondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          FutureBuilder<EmployeeQrPayload>(
            future:
                ref.read(settingsRepositoryProvider).loadEmployeeQrPayload(),
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const LinearProgressIndicator(minHeight: 2);
              }
              if (snapshot.hasError || snapshot.data == null) {
                return Text(
                  'QR indisponible pour le moment.',
                  style: AppTypography.bodySmall.copyWith(
                    color: MobileSurface.secondary,
                  ),
                );
              }

              final qr = snapshot.data!;
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  LeopardoQrCard(
                    data: qr.token,
                    title: 'Mon QR employe',
                    subtitle:
                        'Le manager le scanne pour pre-remplir une invitation.',
                    expiresAt: qr.expiresAt,
                    copyLabel: 'Copier aussi le jeton',
                  ),
                ],
              );
            },
          ),
          const SizedBox(height: 18),
          OutlinedButton.icon(
            onPressed: () async {
              final data = await Clipboard.getData(Clipboard.kTextPlain);
              final text = data?.text?.trim();
              if (text == null || text.isEmpty) {
                if (!context.mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text(
                      'Aucun QR entreprise dans le presse-papiers.',
                    ),
                  ),
                );
                return;
              }
              _companyQrController.text = text;
            },
            icon: const Icon(Icons.content_paste_rounded),
            label: const Text('Coller le QR entreprise'),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _companyQrController,
            minLines: 2,
            maxLines: 4,
            style: const TextStyle(color: MobileSurface.text),
            decoration: const InputDecoration(
              labelText: 'QR entreprise',
              hintText: 'Coller le QR fourni par le manager',
              alignLabelWithHint: true,
            ),
          ),
          const SizedBox(height: 10),
          FilledButton.icon(
            onPressed: () => _submitCompanyQr(context),
            icon: const Icon(Icons.domain_add_rounded),
            label: const Text('Demander l integration'),
          ),
        ],
      ),
    );
  }

  Widget _buildCareerRow(EmployeeCareerEntry entry) {
    final period =
        '${entry.startDate ?? 'Date inconnue'} - ${entry.endDate ?? (entry.current ? 'Aujourd hui' : 'En cours')}';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: MobileSurface.chip,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: MobileSurface.border),
      ),
      child: Row(
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: BoxDecoration(
              color: entry.current ? AppColors.rh : MobileSurface.muted,
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  entry.jobTitle ?? 'Poste non renseigne',
                  style: AppTypography.body.copyWith(
                    color: MobileSurface.text,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  '${entry.companyName ?? 'Entreprise'} - $period',
                  style: AppTypography.caption.copyWith(
                    color: MobileSurface.secondary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCabinetSection() {
    return FutureBuilder<CabinetStats>(
      future: ref.read(settingsRepositoryProvider).loadCabinetStats(),
      builder: (context, snapshot) {
        final stats = snapshot.data;

        return Container(
          padding: const EdgeInsets.all(20),
          decoration: MobileSurface.cardDecoration(),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const MobileIconBubble(
                    icon: Icons.inventory_2_rounded,
                    color: AppColors.cabinet,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Placard numerique',
                          style: AppTypography.subtitle.copyWith(
                            color: MobileSurface.text,
                          ),
                        ),
                        Text(
                          'CV, contrats, diplomes et documents administratifs.',
                          style: AppTypography.caption.copyWith(
                            color: MobileSurface.secondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              if (snapshot.connectionState == ConnectionState.waiting)
                const LinearProgressIndicator(minHeight: 2)
              else
                Row(
                  children: [
                    Expanded(
                      child: _buildCabinetMetric(
                        '${stats?.documents ?? 0}',
                        'documents',
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _buildCabinetMetric(
                        '${stats?.shared ?? 0}',
                        'partages',
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _buildCabinetMetric(
                        '${stats?.publicDocuments ?? 0}',
                        'publics',
                      ),
                    ),
                  ],
                ),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: () => context.push('/cabinet'),
                icon: const Icon(Icons.folder_open_rounded),
                label: const Text('Ouvrir mon placard'),
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(44),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildCabinetMetric(String value, String label) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
      decoration: BoxDecoration(
        color: MobileSurface.chip,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: MobileSurface.border),
      ),
      child: Column(
        children: [
          Text(
            value,
            style: AppTypography.subtitle.copyWith(color: AppColors.cabinet),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: AppTypography.caption.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
        ],
      ),
    );
  }

  String? _optionalEmailValidator(String? value) {
    final trimmed = value?.trim() ?? '';
    if (trimmed.isEmpty) return null;
    if (!trimmed.contains('@') || !trimmed.contains('.')) {
      return 'Email invalide';
    }
    return null;
  }

  Widget _buildLanguageSection(BuildContext context, AuthState authState) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Langue',
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 8),
          Text(
            'Cette preference est synchronisee avec votre compte et pilote aussi le mode RTL.',
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            initialValue: _selectedLanguage,
            decoration: const InputDecoration(labelText: 'Langue preferee'),
            items:
                _languageLabels.entries
                    .map(
                      (entry) => DropdownMenuItem<String>(
                        value: entry.key,
                        child: Text(entry.value),
                      ),
                    )
                    .toList(),
            onChanged:
                _languageSaving
                    ? null
                    : (value) {
                      if (value == null) return;
                      setState(() => _selectedLanguage = value);
                    },
          ),
          const SizedBox(height: 16),
          if (authState.error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(
                authState.error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          FilledButton(
            onPressed: _languageSaving ? null : _saveLanguage,
            child: Text(
              _languageSaving ? 'Mise a jour...' : 'Mettre a jour la langue',
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPasswordSection(BuildContext context, AuthState authState) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Form(
        key: _passwordFormKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Securite',
              style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
            ),
            const SizedBox(height: 8),
            Text(
              'Changez votre mot de passe avant les prochaines etapes de modernisation.',
              style: AppTypography.bodySmall.copyWith(
                color: MobileSurface.secondary,
              ),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _currentPasswordController,
              obscureText: true,
              decoration: const InputDecoration(
                labelText: 'Mot de passe actuel',
              ),
              validator:
                  (value) =>
                      (value == null || value.isEmpty) ? 'Champ requis' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _newPasswordController,
              obscureText: true,
              decoration: const InputDecoration(
                labelText: 'Nouveau mot de passe',
              ),
              validator: (value) {
                if (value == null || value.isEmpty) return 'Champ requis';
                if (value.length < 8) return 'Minimum 8 caracteres';
                return null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _confirmPasswordController,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Confirmation'),
              validator: (value) {
                if (value != _newPasswordController.text) {
                  return 'La confirmation ne correspond pas';
                }
                return null;
              },
            ),
            const SizedBox(height: 16),
            if (authState.error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text(
                  authState.error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            FilledButton(
              onPressed: _passwordSaving ? null : _savePassword,
              child: Text(
                _passwordSaving
                    ? 'Mise a jour...'
                    : 'Mettre a jour le mot de passe',
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBiometricSection(BuildContext context) {
    final employee = ref.read(authProvider).employee;
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Preparation biometrie',
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 8),
          Text(
            'Le visage peut etre capture depuis le mobile puis soumis a validation manager / RH. Pour l empreinte, Android/iOS permettent de verifier localement que vous utilisez bien un doigt enregistre, mais ne donnent pas acces au gabarit brut; l activation effective cote pointage restera donc approuvee puis exploitee par la borne entreprise.',
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
          const SizedBox(height: 12),
          if (employee != null)
            Text(
              'Actif aujourd hui - visage: ${employee.biometricFaceEnabled ? "oui" : "non"} | empreinte: ${employee.biometricFingerprintEnabled ? "oui" : "non"}',
              style: AppTypography.caption.copyWith(
                color: MobileSurface.secondary,
              ),
            ),
          if (_latestEnrollment != null) ...[
            const SizedBox(height: 8),
            Text(
              'Derniere demande: ${_latestEnrollment!.status.toUpperCase()}',
              style: TextStyle(
                color:
                    _latestEnrollment!.status == 'approved'
                        ? AppColors.success
                        : _latestEnrollment!.status == 'rejected'
                        ? AppColors.danger
                        : AppColors.warning,
              ),
            ),
            if ((_latestEnrollment!.managerNote ?? '').isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  'Retour manager/RH: ${_latestEnrollment!.managerNote}',
                  style: AppTypography.caption.copyWith(
                    color: MobileSurface.secondary,
                  ),
                ),
              ),
          ],
          const SizedBox(height: 16),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Activer la preparation biometrie'),
            value: _biometricEnabled,
            onChanged: (value) => setState(() => _biometricEnabled = value),
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Empreinte digitale souhaitee'),
            value: _fingerprintEnabled,
            onChanged:
                _biometricEnabled
                    ? (value) =>
                        setState(() => _fingerprintEnabled = value ?? false)
                    : null,
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Reconnaissance faciale souhaitee'),
            value: _faceEnabled,
            onChanged:
                _biometricEnabled
                    ? (value) => setState(() => _faceEnabled = value ?? false)
                    : null,
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Consentement au futur pointage biometrie'),
            value: _attendanceConsent,
            onChanged:
                _biometricEnabled
                    ? (value) =>
                        setState(() => _attendanceConsent = value ?? false)
                    : null,
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _fingerprintDeviceController,
            decoration: const InputDecoration(
              labelText: 'Identifiant capteur empreinte / borne',
              hintText: 'Exemple: FP-ENTREE-01 ou matricule biometrie',
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _biometricNoteController,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Notes et consentement',
              hintText:
                  'Exemple: selfie autorise, prefere borne entree principale, accord photo visage...',
            ),
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: _pickFaceImage,
            icon: const Icon(Icons.camera_alt_outlined),
            label: Text(
              _selectedFaceImage == null
                  ? 'Capturer / choisir mon visage'
                  : 'Image visage selectionnee',
            ),
          ),
          if (_selectedFaceImage != null)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: Image.file(
                  _selectedFaceImage!,
                  height: 180,
                  fit: BoxFit.cover,
                ),
              ),
            ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _preferencesSaving ? null : _savePreferences,
            child: Text(
              _preferencesSaving
                  ? 'Enregistrement...'
                  : 'Enregistrer la preparation',
            ),
          ),
          const SizedBox(height: 12),
          FilledButton.tonal(
            onPressed: _biometricSubmitting ? null : _submitBiometricEnrollment,
            child: Text(
              _biometricSubmitting
                  ? 'Soumission...'
                  : 'Soumettre au manager / RH',
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Une fois soumises, vos donnees biometrie restent en attente. Toute premiere activation ou modification necessite une approbation manager/RH.',
            style: AppTypography.caption.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildLogoutSection(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: MobileSurface.cardDecoration(color: MobileSurface.chip),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Session',
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 6),
          Text(
            'Quitter proprement cet espace sur ce telephone.',
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
          const SizedBox(height: 14),
          OutlinedButton.icon(
            onPressed: () async {
              await ref.read(authProvider.notifier).logout();
              if (!context.mounted) return;
              context.go('/login');
            },
            icon: const Icon(Icons.logout_rounded),
            label: const Text('Deconnexion'),
            style: OutlinedButton.styleFrom(
              foregroundColor: AppColors.danger,
              side: BorderSide(color: AppColors.danger.withValues(alpha: 0.45)),
              minimumSize: const Size.fromHeight(46),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildNotificationSection(BuildContext context) {
    return FutureBuilder<NotificationPreferences>(
      future:
          ref.read(settingsRepositoryProvider).loadNotificationPreferences(),
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return Container(
            padding: const EdgeInsets.all(16),
            decoration: MobileSurface.cardDecoration(),
            child: const LinearProgressIndicator(minHeight: 3),
          );
        }

        if (snapshot.hasError || !snapshot.hasData) {
          return Container(
            padding: const EdgeInsets.all(16),
            decoration: MobileSurface.cardDecoration(),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Notifications',
                  style: AppTypography.subtitle.copyWith(
                    color: MobileSurface.text,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Preferences indisponibles pour le moment. Tire pour recharger plus tard.',
                  style: AppTypography.bodySmall.copyWith(
                    color: MobileSurface.secondary,
                  ),
                ),
              ],
            ),
          );
        }

        var preferences = snapshot.data!;
        var saving = false;

        return StatefulBuilder(
          builder: (context, setLocalState) {
            Future<void> save() async {
              setLocalState(() => saving = true);
              try {
                final updated = await ref
                    .read(settingsRepositoryProvider)
                    .saveNotificationPreferences(preferences);
                if (!context.mounted) return;
                setLocalState(() {
                  preferences = updated;
                  saving = false;
                });
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Preferences notifications mises a jour.'),
                  ),
                );
              } catch (e) {
                if (!context.mounted) return;
                setLocalState(() => saving = false);
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Mise a jour impossible : $e')),
                );
              }
            }

            Widget tile({
              required String title,
              required String subtitle,
              required bool value,
              required ValueChanged<bool> onChanged,
            }) {
              return SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                title: Text(title),
                subtitle: Text(subtitle),
                value: value,
                activeThumbColor: AppColors.rh,
                onChanged:
                    saving
                        ? null
                        : (next) {
                          setLocalState(() => onChanged(next));
                          save();
                        },
              );
            }

            return Container(
              padding: const EdgeInsets.all(20),
              decoration: MobileSurface.cardDecoration(),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Notifications',
                    style: AppTypography.subtitle.copyWith(
                      color: MobileSurface.text,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Choisissez les canaux utiles sans perdre les alertes RH importantes.',
                    style: AppTypography.bodySmall.copyWith(
                      color: MobileSurface.secondary,
                    ),
                  ),
                  const SizedBox(height: 12),
                  tile(
                    title: 'Alertes dans l application',
                    subtitle: 'Taches, decisions RH, pointage et rappels.',
                    value: preferences.appEnabled,
                    onChanged:
                        (next) =>
                            preferences = preferences.copyWith(
                              appEnabled: next,
                            ),
                  ),
                  tile(
                    title: 'Push mobile',
                    subtitle: 'Notifications immediates sur ce telephone.',
                    value: preferences.pushEnabled,
                    onChanged:
                        (next) =>
                            preferences = preferences.copyWith(
                              pushEnabled: next,
                            ),
                  ),
                  tile(
                    title: 'Email',
                    subtitle: 'Resume et confirmations importantes.',
                    value: preferences.emailEnabled,
                    onChanged:
                        (next) =>
                            preferences = preferences.copyWith(
                              emailEnabled: next,
                            ),
                  ),
                  tile(
                    title: 'Heures calmes',
                    subtitle: 'Limiter les canaux externes hors horaires.',
                    value: preferences.quietHoursEnabled,
                    onChanged:
                        (next) =>
                            preferences = preferences.copyWith(
                              quietHoursEnabled: next,
                            ),
                  ),
                  if (saving)
                    const Padding(
                      padding: EdgeInsets.only(top: 8),
                      child: LinearProgressIndicator(minHeight: 3),
                    ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _submitCompanyQr(BuildContext context) async {
    final token = _companyQrController.text.trim();
    if (token.isEmpty) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Collez le QR entreprise.')));
      return;
    }

    try {
      final message = await ref
          .read(settingsRepositoryProvider)
          .submitCompanyQr(token);
      if (!context.mounted) return;
      _companyQrController.clear();
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    } catch (e) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('QR refuse : $e')));
    }
  }

  Future<void> _saveProfile() async {
    if (!_profileFormKey.currentState!.validate()) return;

    setState(() => _profileSaving = true);
    final success = await ref
        .read(authProvider.notifier)
        .updateProfile(
          firstName: _firstNameController.text,
          lastName: _lastNameController.text,
          email: _emailController.text,
          personalEmail: _personalEmailController.text,
          recoveryEmail: _recoveryEmailController.text,
          personalPhone: _personalPhoneController.text,
        );

    if (!mounted) return;
    setState(() => _profileSaving = false);

    if (success) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Profil mis a jour.')));
    }
  }

  Future<void> _savePassword() async {
    if (!_passwordFormKey.currentState!.validate()) return;

    setState(() => _passwordSaving = true);
    final success = await ref
        .read(authProvider.notifier)
        .changePassword(
          currentPassword: _currentPasswordController.text,
          newPassword: _newPasswordController.text,
          confirmation: _confirmPasswordController.text,
        );

    if (!mounted) return;
    setState(() => _passwordSaving = false);

    if (success) {
      _currentPasswordController.clear();
      _newPasswordController.clear();
      _confirmPasswordController.clear();
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Mot de passe mis a jour.')));
    }
  }

  Future<void> _saveLanguage() async {
    setState(() => _languageSaving = true);
    final success = await ref
        .read(authProvider.notifier)
        .updatePreferredLanguage(_selectedLanguage);

    if (!mounted) return;
    setState(() => _languageSaving = false);

    if (success) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Langue mise a jour.')));
    }
  }

  Future<void> _savePreferences() async {
    setState(() => _preferencesSaving = true);

    await ref
        .read(settingsRepositoryProvider)
        .saveLocalBiometricSettings(
          LocalBiometricSettings(
            biometricEnabled: _biometricEnabled,
            fingerprintEnabled: _biometricEnabled && _fingerprintEnabled,
            faceEnabled: _biometricEnabled && _faceEnabled,
            attendanceConsent: _biometricEnabled && _attendanceConsent,
            biometricNote: _biometricNoteController.text,
          ),
        );

    if (!mounted) return;
    setState(() => _preferencesSaving = false);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Preparation biometrie enregistree localement.'),
      ),
    );
  }

  Future<void> _pickFaceImage() async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(
      source: ImageSource.camera,
      imageQuality: 85,
    );
    if (picked == null || !mounted) return;

    setState(() {
      _selectedFaceImage = File(picked.path);
    });
  }

  Future<void> _submitBiometricEnrollment() async {
    if (!_biometricEnabled) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Active d abord la preparation biometrie.'),
        ),
      );
      return;
    }

    if (!_attendanceConsent) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Le consentement est requis avant toute soumission.'),
        ),
      );
      return;
    }

    if (_faceEnabled && _selectedFaceImage == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Ajoute une capture visage avant soumission.'),
        ),
      );
      return;
    }

    if (_fingerprintEnabled) {
      final localAuth = LocalAuthentication();
      final authenticated = await localAuth.authenticate(
        localizedReason:
            'Confirmer votre identite pour soumettre votre demande biometrie',
        biometricOnly: true,
        persistAcrossBackgrounding: true,
      );

      if (!authenticated) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Verification biometrie locale annulee.'),
          ),
        );
        return;
      }
    }

    setState(() => _biometricSubmitting = true);
    try {
      final enrollment = await ref
          .read(settingsRepositoryProvider)
          .submitBiometricEnrollment(
            requestedFaceEnabled: _faceEnabled,
            requestedFingerprintEnabled: _fingerprintEnabled,
            employeeNote: _biometricNoteController.text,
            requestedFingerprintDeviceId: _fingerprintDeviceController.text,
            faceImage: _selectedFaceImage,
          );

      if (!mounted) return;
      setState(() {
        _latestEnrollment = enrollment;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Demande envoyee au manager / RH pour validation.'),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Echec de soumission: $e')));
    } finally {
      if (mounted) {
        setState(() => _biometricSubmitting = false);
      }
    }
  }
}
