import 'dart:io';
import 'dart:ui' show PlatformDispatcher;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';
import 'package:leopardo_rh/features/settings/data/biometric_enrollment.dart';
import 'package:leopardo_rh/features/settings/data/settings_repository.dart';
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
  final TextEditingController _currentPasswordController = TextEditingController();
  final TextEditingController _newPasswordController = TextEditingController();
  final TextEditingController _confirmPasswordController = TextEditingController();
  final TextEditingController _biometricNoteController = TextEditingController();
  final TextEditingController _fingerprintDeviceController = TextEditingController();
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
    final deviceLanguage = PlatformDispatcher.instance.locale.languageCode.toLowerCase();
    _firstNameController = TextEditingController(text: employee?.firstName ?? '');
    _lastNameController = TextEditingController(text: employee?.lastName ?? '');
    _emailController = TextEditingController(text: employee?.email ?? '');
    _selectedLanguage = _languageLabels.containsKey(employee?.language)
        ? employee!.language
        : (_languageLabels.containsKey(deviceLanguage) ? deviceLanguage : 'fr');
    _loadLocalSettings();
    _loadEnrollmentStatus();
  }

  Future<void> _loadLocalSettings() async {
    final settings = await ref.read(settingsRepositoryProvider).loadLocalBiometricSettings();
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
      final enrollment = await ref.read(settingsRepositoryProvider).loadBiometricEnrollment();
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
    _currentPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    _biometricNoteController.dispose();
    _fingerprintDeviceController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);
    final employee = authState.employee;
    final isManager = employee?.isManager == true;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Parametres'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          _buildIdentityCard(context, employee?.role),
          const SizedBox(height: 20),
          _buildProfileSection(context, authState),
          const SizedBox(height: 20),
          _buildLanguageSection(context, authState),
          const SizedBox(height: 20),
          _buildPasswordSection(context, authState),
          if (!isManager) ...[
            const SizedBox(height: 20),
            _buildBiometricSection(context),
          ],
        ],
      ),
    );
  }

  Widget _buildIdentityCard(BuildContext context, String? role) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Acces mobile',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          Text(
            role == 'manager'
                ? 'Profil RH / manager: acces au suivi de l equipe et a l historique.'
                : 'Profil employe: acces au pointage, a l historique personnel et aux parametres de preparation biometrie.',
            style: const TextStyle(color: AppColors.textMuted),
          ),
        ],
      ),
    );
  }

  Widget _buildProfileSection(BuildContext context, AuthState authState) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Form(
        key: _profileFormKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Mon profil',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _firstNameController,
              decoration: const InputDecoration(labelText: 'Prenom'),
              validator: (value) => (value == null || value.trim().isEmpty) ? 'Prenom requis' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _lastNameController,
              decoration: const InputDecoration(labelText: 'Nom'),
              validator: (value) => (value == null || value.trim().isEmpty) ? 'Nom requis' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(labelText: 'Email'),
              validator: (value) {
                final trimmed = value?.trim() ?? '';
                if (trimmed.isEmpty) return 'Email requis';
                if (!trimmed.contains('@') || !trimmed.contains('.')) return 'Email invalide';
                return null;
              },
            ),
            const SizedBox(height: 16),
            if (authState.error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text(authState.error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
            FilledButton(
              onPressed: _profileSaving ? null : _saveProfile,
              child: Text(_profileSaving ? 'Enregistrement...' : 'Enregistrer le profil'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLanguageSection(BuildContext context, AuthState authState) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Langue',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          const Text(
            'Cette preference est synchronisee avec votre compte et pilote aussi le mode RTL.',
            style: TextStyle(color: AppColors.textMuted),
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            value: _selectedLanguage,
            decoration: const InputDecoration(labelText: 'Langue preferee'),
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
              child: Text(authState.error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
          FilledButton(
            onPressed: _languageSaving ? null : _saveLanguage,
            child: Text(_languageSaving ? 'Mise a jour...' : 'Mettre a jour la langue'),
          ),
        ],
      ),
    );
  }

  Widget _buildPasswordSection(BuildContext context, AuthState authState) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Form(
        key: _passwordFormKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Securite',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            const Text(
              'Changez votre mot de passe avant les prochaines etapes de modernisation.',
              style: TextStyle(color: AppColors.textMuted),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _currentPasswordController,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Mot de passe actuel'),
              validator: (value) => (value == null || value.isEmpty) ? 'Champ requis' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _newPasswordController,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Nouveau mot de passe'),
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
                if (value != _newPasswordController.text) return 'La confirmation ne correspond pas';
                return null;
              },
            ),
            const SizedBox(height: 16),
            if (authState.error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text(authState.error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
            FilledButton(
              onPressed: _passwordSaving ? null : _savePassword,
              child: Text(_passwordSaving ? 'Mise a jour...' : 'Mettre a jour le mot de passe'),
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
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Preparation biometrie',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          Text(
            'Le visage peut etre capture depuis le mobile puis soumis a validation manager / RH. Pour l empreinte, Android/iOS permettent de verifier localement que vous utilisez bien un doigt enregistre, mais ne donnent pas acces au gabarit brut; l activation effective cote pointage restera donc approuvee puis exploitee par la borne entreprise.',
            style: const TextStyle(color: AppColors.textMuted),
          ),
          const SizedBox(height: 12),
          if (employee != null)
            Text(
              'Actif aujourd hui - visage: ${employee.biometricFaceEnabled ? "oui" : "non"} | empreinte: ${employee.biometricFingerprintEnabled ? "oui" : "non"}',
              style: const TextStyle(color: AppColors.textMuted),
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
                  style: const TextStyle(color: AppColors.textMuted),
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
            onChanged: _biometricEnabled
                ? (value) => setState(() => _fingerprintEnabled = value ?? false)
                : null,
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Reconnaissance faciale souhaitee'),
            value: _faceEnabled,
            onChanged: _biometricEnabled
                ? (value) => setState(() => _faceEnabled = value ?? false)
                : null,
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Consentement au futur pointage biometrie'),
            value: _attendanceConsent,
            onChanged: _biometricEnabled
                ? (value) => setState(() => _attendanceConsent = value ?? false)
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
              hintText: 'Exemple: selfie autorise, prefere borne entree principale, accord photo visage...',
            ),
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: _pickFaceImage,
            icon: const Icon(Icons.camera_alt_outlined),
            label: Text(_selectedFaceImage == null ? 'Capturer / choisir mon visage' : 'Image visage selectionnee'),
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
            child: Text(_preferencesSaving ? 'Enregistrement...' : 'Enregistrer la preparation'),
          ),
          const SizedBox(height: 12),
          FilledButton.tonal(
            onPressed: _biometricSubmitting ? null : _submitBiometricEnrollment,
            child: Text(_biometricSubmitting ? 'Soumission...' : 'Soumettre au manager / RH'),
          ),
          const SizedBox(height: 8),
          const Text(
            'Une fois soumises, vos donnees biometrie restent en attente. Toute premiere activation ou modification necessite une approbation manager/RH.',
            style: TextStyle(color: AppColors.textMuted, fontSize: 12),
          ),
        ],
      ),
    );
  }

  Future<void> _saveProfile() async {
    if (!_profileFormKey.currentState!.validate()) return;

    setState(() => _profileSaving = true);
    final success = await ref.read(authProvider.notifier).updateProfile(
          firstName: _firstNameController.text,
          lastName: _lastNameController.text,
          email: _emailController.text,
        );

    if (!mounted) return;
    setState(() => _profileSaving = false);

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Profil mis a jour.')),
      );
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
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Mot de passe mis a jour.')),
      );
    }
  }

  Future<void> _saveLanguage() async {
    setState(() => _languageSaving = true);
    final success = await ref.read(authProvider.notifier).updatePreferredLanguage(_selectedLanguage);

    if (!mounted) return;
    setState(() => _languageSaving = false);

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Langue mise a jour.')),
      );
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
      const SnackBar(content: Text('Preparation biometrie enregistree localement.')),
    );
  }

  Future<void> _pickFaceImage() async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(source: ImageSource.camera, imageQuality: 85);
    if (picked == null || !mounted) return;

    setState(() {
      _selectedFaceImage = File(picked.path);
    });
  }

  Future<void> _submitBiometricEnrollment() async {
    if (!_biometricEnabled) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Active d abord la preparation biometrie.')),
      );
      return;
    }

    if (!_attendanceConsent) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Le consentement est requis avant toute soumission.')),
      );
      return;
    }

    if (_faceEnabled && _selectedFaceImage == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Ajoute une capture visage avant soumission.')),
      );
      return;
    }

    if (_fingerprintEnabled) {
      final localAuth = LocalAuthentication();
      final authenticated = await localAuth.authenticate(
        localizedReason: 'Confirmer votre identite pour soumettre votre demande biometrie',
        options: const AuthenticationOptions(biometricOnly: true),
      );

      if (!authenticated) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Verification biometrie locale annulee.')),
        );
        return;
      }
    }

    setState(() => _biometricSubmitting = true);
    try {
      final enrollment = await ref.read(settingsRepositoryProvider).submitBiometricEnrollment(
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
        const SnackBar(content: Text('Demande envoyee au manager / RH pour validation.')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Echec de soumission: $e')),
      );
    } finally {
      if (mounted) {
        setState(() => _biometricSubmitting = false);
      }
    }
  }
}
