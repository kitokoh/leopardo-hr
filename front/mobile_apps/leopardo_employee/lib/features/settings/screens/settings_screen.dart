import 'dart:async';
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
import 'package:leopardo_core/offline/services/sync_service.dart';
import 'package:local_auth/local_auth.dart';
import 'package:leopardo_core/core/widgets/mobile_list_glass_card.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_core/core/providers/theme_mode_provider.dart';

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
  final TextEditingController _edgeNodeIdController = TextEditingController();
  final TextEditingController _edgeTokenController = TextEditingController();
  final TextEditingController _edgeBaseUrlController = TextEditingController();
  static Map<String, String> get _languageLabels => {
        'fr': 'Francais',
        'ar': deviceL10n.commonLanguageArabic,
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
  bool _edgeSaving = false;
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
    _selectedLanguage = _languageLabels.containsKey(employee?.language)
        ? employee!.language
        : (_languageLabels.containsKey(deviceLanguage) ? deviceLanguage : 'fr');
    _loadLocalSettings();
    _loadEnrollmentStatus();
    _loadEdgeSettings();
  }

  void _loadEdgeSettings() {
    final preferences = ref.read(appPreferencesProvider);
    _edgeNodeIdController.text = preferences.edgeNodeId;
    _edgeTokenController.text = preferences.edgeToken;
    _edgeBaseUrlController.text = preferences.edgeBaseUrl;
  }

  Future<void> _saveEdgeSettings(BuildContext context) async {
    setState(() => _edgeSaving = true);
    try {
      await ref.read(appPreferencesProvider).saveEdgeEnrollment(
            edgeNodeId: _edgeNodeIdController.text,
            edgeToken: _edgeTokenController.text,
            edgeBaseUrl: _edgeBaseUrlController.text,
          );
      // Re-run mode detection immediately so the sync banner reflects the
      // newly paired Edge node without waiting for the next periodic tick
      // or connectivity change (see issue #1287).
      unawaited(ref.read(syncServiceProvider).syncNow());
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.settingsEdgeSaved)),
      );
    } finally {
      if (mounted) setState(() => _edgeSaving = false);
    }
  }

  Future<void> _clearEdgeSettings(BuildContext context) async {
    await ref.read(appPreferencesProvider).clearEdgeEnrollment();
    _edgeNodeIdController.clear();
    _edgeTokenController.clear();
    _edgeBaseUrlController.clear();
    if (!context.mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(context.l10n.settingsEdgeRemoved)));
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
    _edgeNodeIdController.dispose();
    _edgeTokenController.dispose();
    _edgeBaseUrlController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: context.l10n.settingsAccountTitle,
        subtitle: context.l10n.settingsAccountSubtitle,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: context.l10n.back,
          onPressed: () => context.pop(),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          _buildIdentityGlassCard(context),
          const SizedBox(height: 20),
          _buildAccountOverviewSection(context),
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
          // Issue #5624 — réglage thème in-app
          _buildThemeSection(context),
          const SizedBox(height: 20),
          _buildNotificationSection(context),
          const SizedBox(height: 20),
          _buildPasswordSection(context, authState),
          const SizedBox(height: 20),
          _buildTwoFactorSection(context),
          const SizedBox(height: 20),
          _buildBiometricSection(context),
          const SizedBox(height: 20),
          _buildEdgeSection(context),
          const SizedBox(height: 28),
          _buildLogoutSection(context),
          SizedBox(height: MediaQuery.of(context).padding.bottom + 8),
        ],
      ),
    );
  }

  Widget _buildIdentityGlassCard(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.l10n.settingsMobileAccess,
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 8),
          Text(
            context.l10n.settingsEmployeeProfileHint,
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
          const SizedBox(height: 14),
          OutlinedButton.icon(
            onPressed: () => context.push('/profile'),
            icon: const Icon(Icons.person_outline_rounded),
            label: Text(context.l10n.settingsViewProfile),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(44),
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
              decoration:
                  InputDecoration(labelText: context.l10n.settingsFirstName),
              validator: (value) => (value == null || value.trim().isEmpty)
                  ? context.l10n.settingsFirstNameRequired
                  : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _lastNameController,
              decoration: InputDecoration(
                  labelText: context.l10n.settingsLastNameLabel),
              validator: (value) => (value == null || value.trim().isEmpty)
                  ? context.l10n.settingsLastNameRequired
                  : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              decoration:
                  InputDecoration(labelText: context.l10n.settingsEmailLabel),
              validator: (value) {
                final trimmed = value?.trim() ?? '';
                if (trimmed.isEmpty) return context.l10n.settingsEmailRequired;
                if (!trimmed.contains('@') || !trimmed.contains('.')) {
                  return context.l10n.settingsEmailInvalid;
                }
                return null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _personalEmailController,
              keyboardType: TextInputType.emailAddress,
              decoration: InputDecoration(
                labelText: context.l10n.settingsPersonalEmailLabel,
                helperText: context.l10n.settingsPersonalEmailHint,
              ),
              validator: _optionalEmailValidator,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _recoveryEmailController,
              keyboardType: TextInputType.emailAddress,
              decoration: InputDecoration(
                labelText: context.l10n.settingsRecoveryEmailLabel,
                helperText: context.l10n.settingsRecoveryEmailHint,
              ),
              validator: _optionalEmailValidator,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _personalPhoneController,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(
                labelText: context.l10n.settingsPersonalPhoneLabel,
                helperText: context.l10n.settingsPersonalPhoneHint,
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
                (_profileSaving
                    ? context.l10n.settingsSaving
                    : context.l10n.settingsSaveProfile),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAccountOverviewSection(BuildContext context) {
    final items = [
      (
        icon: Icons.badge_outlined,
        color: AppColors.rh,
        title: context.l10n.settingsPortableIdentityTitle,
        subtitle: context.l10n.settingsPortableIdentityHint,
      ),
      (
        icon: Icons.work_history_outlined,
        color: AppColors.info,
        title: context.l10n.settingsJourneyTitle,
        subtitle: context.l10n.settingsPortableIdentitySubtitle,
      ),
      (
        icon: Icons.folder_copy_outlined,
        color: AppColors.warning,
        title: context.l10n.settingsDigitalLockerTitle,
        subtitle: context.l10n.settingsDigitalLockerSubtitle,
      ),
      (
        icon: Icons.qr_code_2_rounded,
        color: AppColors.rhDark,
        title: context.l10n.settingsQrOnboardingTitle,
        subtitle: context.l10n.settingsQrOnboardingSubtitle,
      ),
      (
        icon: Icons.fingerprint_rounded,
        color: AppColors.info,
        title: context.l10n.settingsKioskBiometricTitle,
        subtitle: context.l10n.settingsBiometricTerminalHint,
      ),
      (
        icon: Icons.notifications_active_outlined,
        color: AppColors.danger,
        title: context.l10n.settingsNotificationsTitle,
        subtitle: context.l10n.settingsNotificationsSubtitle,
      ),
    ];

    return MobilePanel(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.l10n.settingsOverview,
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 6),
          Text(
            context.l10n.settingsAccountPortableHint,
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
          const SizedBox(height: 14),
          ...items.map(
            (item) => MobileListGlassCard(
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
        if (snapshot.hasError) {
          return _buildSectionError(
            title: context.l10n.settingsJourneyTitle,
            message: context.l10n.settingsJourneyLoadError,
          );
        }
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
                          context.l10n.settingsJourneyTitle,
                          style: AppTypography.subtitle.copyWith(
                            color: MobileSurface.text,
                          ),
                        ),
                        Text(
                          career?.availableForNewCompany == true
                              ? context.l10n.settingsJourneyAvailable
                              : context.l10n.settingsJourneyAttachedTo(
                                  career?.currentCompanyName ??
                                      context.l10n.settingsJourneyYourCompany,
                                ),
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
                  context.l10n.settingsNoJourney,
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
                      context.l10n.settingsProfessionalQr,
                      style: AppTypography.subtitle.copyWith(
                        color: MobileSurface.text,
                      ),
                    ),
                    Text(
                      context.l10n.settingsShareProfile,
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
                  context.l10n.settingsQrUnavailable,
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
                    title: context.l10n.settingsMyQrEmployee,
                    subtitle: context.l10n.settingsQrEmployeeHint,
                    expiresAt: qr.expiresAt,
                    copyLabel: context.l10n.settingsQrCopyToken,
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
                  SnackBar(
                    content: Text(
                      context.l10n.settingsNoCompanyQr,
                    ),
                  ),
                );
                return;
              }
              _companyQrController.text = text;
            },
            icon: const Icon(Icons.content_paste_rounded),
            label: Text(context.l10n.settingsPasteQrButton),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _companyQrController,
            minLines: 2,
            maxLines: 4,
            style: const TextStyle(color: MobileSurface.text),
            decoration: InputDecoration(
              labelText: context.l10n.settingsCompanyQrLabel,
              hintText: context.l10n.settingsPasteQr,
              alignLabelWithHint: true,
            ),
          ),
          const SizedBox(height: 10),
          FilledButton.icon(
            onPressed: () => _submitCompanyQr(context),
            icon: const Icon(Icons.domain_add_rounded),
            label: Text(context.l10n.settingsRequestIntegration),
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
                  entry.jobTitle ?? context.l10n.settingsJourneyUnknownPosition,
                  style: AppTypography.body.copyWith(
                    color: MobileSurface.text,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  context.l10n.settingsJourneyCompanyPeriod(
                    entry.companyName ??
                        context.l10n.settingsJourneyUnknownCompany,
                    period,
                  ),
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
        if (snapshot.hasError) {
          return _buildSectionError(
            title: context.l10n.settingsDigitalLockerTitle,
            message: context.l10n.settingsStatsLoadError,
          );
        }
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
                          context.l10n.settingsDigitalLockerTitle,
                          style: AppTypography.subtitle.copyWith(
                            color: MobileSurface.text,
                          ),
                        ),
                        Text(
                          context.l10n.settingsCabinetSubtitle,
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
                        context.l10n.settingsCabinetDocuments,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _buildCabinetMetric(
                        '${stats?.shared ?? 0}',
                        context.l10n.settingsCabinetShared,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _buildCabinetMetric(
                        '${stats?.publicDocuments ?? 0}',
                        context.l10n.settingsCabinetPublic,
                      ),
                    ),
                  ],
                ),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: () => context.push('/cabinet'),
                icon: const Icon(Icons.folder_open_rounded),
                label: Text(context.l10n.settingsOpenLocker),
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
      return context.l10n.settingsEmailInvalid;
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
            context.l10n.settingsLanguageTitle,
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 8),
          Text(
            context.l10n.settingsLanguageSyncHint,
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            initialValue: _selectedLanguage,
            decoration: InputDecoration(
                labelText: context.l10n.settingsPreferredLanguage),
            items: _languageLabels.entries
                .map(
                  (entry) => DropdownMenuItem<String>(
                    value: entry.key,
                    child: Text(entry.value),
                  ),
                )
                .toList(),
            onChanged: _languageSaving
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
              _languageSaving
                  ? context.l10n.settingsUpdating
                  : context.l10n.settingsUpdateLanguage,
            ),
          ),
        ],
      ),
    );
  }

  // ── Issue #5624 — réglage thème in-app ──────────────────────────────────
  Widget _buildThemeSection(BuildContext context) {
    final currentMode = ref.watch(themeModeProvider);

    final options = [
      (ThemeMode.system, Icons.brightness_auto_outlined, context.l10n.settingsThemeSystem),
      (ThemeMode.light, Icons.light_mode_outlined, context.l10n.settingsThemeLight),
      (ThemeMode.dark, Icons.dark_mode_outlined, context.l10n.settingsThemeDark),
    ];

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.l10n.settingsThemeTitle,
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 8),
          Text(
            context.l10n.settingsThemeHint,
            style: AppTypography.bodySmall
                .copyWith(color: MobileSurface.secondary),
          ),
          const SizedBox(height: 16),
          RadioGroup<ThemeMode>(
            groupValue: currentMode,
            onChanged: (mode) async {
              if (mode != null) {
                await ref
                    .read(themeModeProvider.notifier)
                    .setMode(mode);
              }
            },
            child: Column(
              children: options
                  .map(
                    (opt) => RadioListTile<ThemeMode>(
                      contentPadding: EdgeInsets.zero,
                      value: opt.$1,
              title: Row(
                children: [
                  Icon(opt.$2, size: 20, color: MobileSurface.secondary),
                  const SizedBox(width: 10),
                  Text(opt.$3,
                      style: AppTypography.body
                          .copyWith(color: MobileSurface.text)),
                ],
              ),
                    ),
                  )
                  .toList(),
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
              context.l10n.settingsSecurityTitle,
              style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
            ),
            const SizedBox(height: 8),
            Text(
              context.l10n.settingsPasswordModernizationHint,
              style: AppTypography.bodySmall.copyWith(
                color: MobileSurface.secondary,
              ),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _currentPasswordController,
              obscureText: true,
              decoration: InputDecoration(
                labelText: context.l10n.settingsCurrentPassword,
              ),
              validator: (value) => (value == null || value.isEmpty)
                  ? context.l10n.settingsFieldRequired
                  : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _newPasswordController,
              obscureText: true,
              decoration: InputDecoration(
                labelText: context.l10n.settingsNewPassword,
              ),
              validator: (value) {
                if (value == null || value.isEmpty) {
                  return context.l10n.settingsFieldRequired;
                }
                if (value.length < 8) {
                  return context.l10n.settingsPasswordMinLength;
                }
                return null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _confirmPasswordController,
              obscureText: true,
              decoration: InputDecoration(
                labelText: context.l10n.settingsConfirmPassword,
              ),
              validator: (value) {
                if (value != _newPasswordController.text) {
                  return context.l10n.settingsPasswordMismatch;
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
                    ? context.l10n.settingsUpdating
                    : context.l10n.settingsUpdatePassword,
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
            context.l10n.settingsBiometricPreparationTitle,
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 8),
          Text(
            context.l10n.settingsBiometricExplanation,
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
          const SizedBox(height: 12),
          if (employee != null)
            Text(
              context.l10n.settingsBiometricTodayStatus(
                employee.biometricFaceEnabled
                    ? context.l10n.settingsYes
                    : context.l10n.settingsNo,
                employee.biometricFingerprintEnabled
                    ? context.l10n.settingsYes
                    : context.l10n.settingsNo,
              ),
              style: AppTypography.caption.copyWith(
                color: MobileSurface.secondary,
              ),
            ),
          if (_latestEnrollment != null) ...[
            const SizedBox(height: 8),
            Text(
              'Derniere demande: ${_latestEnrollment!.status.toUpperCase()}',
              style: TextStyle(
                color: _latestEnrollment!.status == 'approved'
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
            title: Text(context.l10n.settingsBiometricEnablePreparation),
            value: _biometricEnabled,
            onChanged: (value) => setState(() => _biometricEnabled = value),
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(context.l10n.settingsBiometricFingerprintWanted),
            value: _fingerprintEnabled,
            onChanged: _biometricEnabled
                ? (value) =>
                    setState(() => _fingerprintEnabled = value ?? false)
                : null,
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(context.l10n.settingsBiometricFaceWanted),
            value: _faceEnabled,
            onChanged: _biometricEnabled
                ? (value) => setState(() => _faceEnabled = value ?? false)
                : null,
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(context.l10n.settingsBiometricFutureConsent),
            value: _attendanceConsent,
            onChanged: _biometricEnabled
                ? (value) => setState(() => _attendanceConsent = value ?? false)
                : null,
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _fingerprintDeviceController,
            decoration: InputDecoration(
              labelText: context.l10n.settingsBiometricSensorLabel,
              hintText: context.l10n.settingsBiometricSensorHint,
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _biometricNoteController,
            maxLines: 3,
            decoration: InputDecoration(
              labelText: context.l10n.settingsBiometricNotesTitle,
              hintText: context.l10n.settingsBiometricNotesHint,
            ),
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: _pickFaceImage,
            icon: const Icon(Icons.camera_alt_outlined),
            label: Text(
              _selectedFaceImage == null
                  ? context.l10n.settingsBiometricCaptureFace
                  : context.l10n.settingsBiometricFaceSelected,
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
                  ? context.l10n.settingsSavingShort
                  : context.l10n.settingsSaveEnrollment,
            ),
          ),
          const SizedBox(height: 12),
          FilledButton.tonal(
            onPressed: _biometricSubmitting ? null : _submitBiometricEnrollment,
            child: Text(
              _biometricSubmitting
                  ? context.l10n.settingsSubmitting
                  : context.l10n.settingsSubmitBiometric,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            context.l10n.settingsBiometricPendingExplanation,
            style: AppTypography.caption.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEdgeSection(BuildContext context) {
    final syncService = ref.watch(syncServiceProvider);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const MobileIconBubble(
                icon: Icons.lan_rounded,
                color: AppColors.info,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.l10n.settingsEdgeNodeTitle,
                      style: AppTypography.subtitle.copyWith(
                        color: MobileSurface.text,
                      ),
                    ),
                    Text(
                      context.l10n.settingsEdgeNodeHint,
                      style: AppTypography.caption.copyWith(
                        color: MobileSurface.secondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          StreamBuilder<SyncMode>(
            stream: syncService.modeStream,
            initialData: syncService.currentMode,
            builder: (context, snapshot) {
              final mode = snapshot.data ?? SyncMode.offline;
              final label = switch (mode) {
                SyncMode.cloud => context.l10n.settingsEdgeCloudStatus,
                SyncMode.edge => context.l10n.settingsEdgeLocalStatus,
                SyncMode.offline => context.l10n.settingsEdgeOfflineStatus,
              };
              return Text(
                context.l10n.settingsEdgeCurrentStatus(label),
                style: AppTypography.bodySmall.copyWith(
                  color: MobileSurface.secondary,
                ),
              );
            },
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _edgeBaseUrlController,
            decoration: InputDecoration(
              labelText: context.l10n.settingsEdgeAddressLabel,
              hintText: 'http://leopardo.local:7878',
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _edgeNodeIdController,
            decoration: InputDecoration(
              labelText: context.l10n.settingsEdgeUuidLabel,
              hintText: context.l10n.settingsEdgeUuidHint,
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _edgeTokenController,
            obscureText: true,
            decoration: InputDecoration(
              labelText: context.l10n.settingsEdgeTokenLabel,
              hintText: context.l10n.settingsEdgeTokenHint,
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: FilledButton(
                  onPressed:
                      _edgeSaving ? null : () => _saveEdgeSettings(context),
                  child: Text(
                    (_edgeSaving
                        ? context.l10n.settingsSaving
                        : context.l10n.settingsSave),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              OutlinedButton(
                onPressed: () => _clearEdgeSettings(context),
                child: Text(context.l10n.settingsRemove),
              ),
            ],
          ),
        ],
      ),
    );
  }


  /// Issue #5683 — accès à l'écran de gestion 2FA (enrôlement, codes de
  /// récupération, désactivation). Écran partagé leopardo_core.
  Widget _buildTwoFactorSection(BuildContext context) {
    return MobileListGlassCard(
      icon: Icons.verified_user_rounded,
      iconColor: AppColors.rh,
      title: context.l10n.twoFaSettingsTile,
      subtitle: context.l10n.twoFaSettingsTileSubtitle,
      trailing: const Icon(Icons.chevron_right_rounded),
      onTap: () => context.push('/settings/2fa'),
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
            context.l10n.settingsSessionLogoutHint,
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
            label: Text(context.l10n.settingsLogout),
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
                  context.l10n.settingsNotificationsTitle,
                  style: AppTypography.subtitle.copyWith(
                    color: MobileSurface.text,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  context.l10n.settingsNotificationsUnavailable,
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
                  SnackBar(
                    content: Text(context.l10n.settingsNotificationsSaved),
                  ),
                );
              } catch (e) {
                if (!context.mounted) return;
                setLocalState(() => saving = false);
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      context.l10n
                          .settingsNotificationsSaveFailed(e.toString()),
                    ),
                  ),
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
                onChanged: saving
                    ? null
                    : (next) {
                        setLocalState(() => onChanged(next));
                        save();
                      },
              );
            }

            Future<void> pickQuietHour(bool isStart) async {
              final current = isStart
                  ? preferences.quietHoursStart
                  : preferences.quietHoursEnd;
              final fallback = isStart ? '20:00' : '07:00';
              final parts = (current ?? fallback).split(':');
              final initial = TimeOfDay(
                hour: int.tryParse(parts.isNotEmpty ? parts[0] : '') ??
                    (isStart ? 20 : 7),
                minute: int.tryParse(parts.length > 1 ? parts[1] : '') ?? 0,
              );
              final picked = await showTimePicker(
                context: context,
                initialTime: initial,
              );
              if (picked == null) return;
              final formatted =
                  '${picked.hour.toString().padLeft(2, '0')}:${picked.minute.toString().padLeft(2, '0')}';
              setLocalState(() {
                preferences = isStart
                    ? preferences.copyWith(quietHoursStart: formatted)
                    : preferences.copyWith(quietHoursEnd: formatted);
              });
              save();
            }

            return Container(
              padding: const EdgeInsets.all(20),
              decoration: MobileSurface.cardDecoration(),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.l10n.settingsNotificationsTitle,
                    style: AppTypography.subtitle.copyWith(
                      color: MobileSurface.text,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    context.l10n.settingsNotificationsIntro,
                    style: AppTypography.bodySmall.copyWith(
                      color: MobileSurface.secondary,
                    ),
                  ),
                  const SizedBox(height: 12),
                  tile(
                    title: context.l10n.settingsChannelInApp,
                    subtitle: context.l10n.settingsChannelInAppHint,
                    value: preferences.appEnabled,
                    onChanged: (next) =>
                        preferences = preferences.copyWith(appEnabled: next),
                  ),
                  tile(
                    title: context.l10n.settingsChannelPush,
                    subtitle: context.l10n.settingsChannelPushHint,
                    value: preferences.pushEnabled,
                    onChanged: (next) =>
                        preferences = preferences.copyWith(pushEnabled: next),
                  ),
                  tile(
                    title: 'Email',
                    subtitle: context.l10n.settingsChannelEmailHint,
                    value: preferences.emailEnabled,
                    onChanged: (next) =>
                        preferences = preferences.copyWith(emailEnabled: next),
                  ),
                  tile(
                    title: 'SMS',
                    subtitle: context.l10n.settingsChannelSmsHint,
                    value: preferences.smsEnabled,
                    onChanged: (next) =>
                        preferences = preferences.copyWith(smsEnabled: next),
                  ),
                  tile(
                    title: 'WhatsApp',
                    subtitle: context.l10n.settingsChannelWhatsappHint,
                    value: preferences.whatsappEnabled,
                    onChanged: (next) => preferences = preferences.copyWith(
                      whatsappEnabled: next,
                    ),
                  ),
                  tile(
                    title: context.l10n.settingsQuietHours,
                    subtitle: context.l10n.settingsQuietHoursHint,
                    value: preferences.quietHoursEnabled,
                    onChanged: (next) => preferences = preferences.copyWith(
                      quietHoursEnabled: next,
                    ),
                  ),
                  if (preferences.quietHoursEnabled)
                    Padding(
                      padding: const EdgeInsets.only(top: 4, bottom: 8),
                      child: Row(
                        children: [
                          Expanded(
                            child: OutlinedButton(
                              onPressed:
                                  saving ? null : () => pickQuietHour(true),
                              child: Text(
                                'Debut ${preferences.quietHoursStart ?? '20:00'}',
                              ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: OutlinedButton(
                              onPressed:
                                  saving ? null : () => pickQuietHour(false),
                              child: Text(
                                'Fin ${preferences.quietHoursEnd ?? '07:00'}',
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  const SizedBox(height: 4),
                  DropdownButtonFormField<String>(
                    initialValue:
                        _languageLabels.containsKey(preferences.locale)
                            ? preferences.locale
                            : null,
                    decoration: InputDecoration(
                      labelText: context.l10n.settingsNotificationsLanguage,
                    ),
                    items: _languageLabels.entries
                        .map(
                          (entry) => DropdownMenuItem<String>(
                            value: entry.key,
                            child: Text(entry.value),
                          ),
                        )
                        .toList(),
                    onChanged: saving
                        ? null
                        : (value) {
                            if (value == null) return;
                            setLocalState(() {
                              preferences = preferences.copyWith(locale: value);
                            });
                            save();
                          },
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
      ).showSnackBar(SnackBar(content: Text(context.l10n.settingsQrPasted)));
      return;
    }

    try {
      final message =
          await ref.read(settingsRepositoryProvider).submitCompanyQr(token);
      if (!context.mounted) return;
      _companyQrController.clear();
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
    } catch (e) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(context.l10n.settingsQrRejected(e.toString()))));
    }
  }

  Future<void> _saveProfile() async {
    if (!_profileFormKey.currentState!.validate()) return;

    setState(() => _profileSaving = true);
    final success = await ref.read(authProvider.notifier).updateProfile(
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
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.l10n.settingsProfileUpdated)));
    }
  }

  Future<void> _savePassword() async {
    if (!_passwordFormKey.currentState!.validate()) return;

    setState(() => _passwordSaving = true);
    final success = await ref.read(authProvider.notifier).changePassword(
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
      ).showSnackBar(
          SnackBar(content: Text(context.l10n.settingsPasswordChanged)));
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
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.l10n.settingsLanguageUpdated)));
    }
  }

  Future<void> _savePreferences() async {
    setState(() => _preferencesSaving = true);

    await ref.read(settingsRepositoryProvider).saveLocalBiometricSettings(
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
      SnackBar(
        content: Text(context.l10n.settingsBiometricSavedLocal),
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
        SnackBar(
          content: Text(context.l10n.settingsBiometricEnableFirst),
        ),
      );
      return;
    }

    if (!_attendanceConsent) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.l10n.settingsBiometricConsentRequired),
        ),
      );
      return;
    }

    if (_faceEnabled && _selectedFaceImage == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.l10n.settingsBiometricFaceRequired),
        ),
      );
      return;
    }

    if (_fingerprintEnabled) {
      final localAuth = LocalAuthentication();
      final authenticated = await localAuth.authenticate(
        localizedReason: context.l10n.settingsBiometricConfirmIdentity,
        biometricOnly: true,
        persistAcrossBackgrounding: true,
      );

      if (!authenticated) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(context.l10n.settingsBiometricLocalVerifyCancel),
          ),
        );
        return;
      }
    }

    setState(() => _biometricSubmitting = true);
    try {
      final enrollment =
          await ref.read(settingsRepositoryProvider).submitBiometricEnrollment(
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
        SnackBar(
          content: Text(context.l10n.settingsBiometricSubmitted),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content:
              Text(context.l10n.settingsBiometricSubmitFailed(e.toString()))));
    } finally {
      if (mounted) {
        setState(() => _biometricSubmitting = false);
      }
    }
  }

  Widget _buildSectionError({required String title, required String message}) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 8),
          Text(
            message,
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: () => setState(() {}),
            icon: const Icon(Icons.refresh, size: 18),
            label: Text(context.l10n.retry),
          ),
        ],
      ),
    );
  }
}
