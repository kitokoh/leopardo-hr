import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';

import '../../core/platform_providers.dart';
import '../platform/platform_models.dart';
import 'company_screen.dart';

const _fallbackCountryDefaults = [
  PlatformCountryDefault(
    country: 'DZ',
    label: 'Algerie',
    currency: 'DZD',
    timezone: 'Africa/Algiers',
    language: 'fr',
  ),
  PlatformCountryDefault(
    country: 'MA',
    label: 'Maroc',
    currency: 'MAD',
    timezone: 'Africa/Casablanca',
    language: 'fr',
  ),
  PlatformCountryDefault(
    country: 'TN',
    label: 'Tunisie',
    currency: 'TND',
    timezone: 'Africa/Tunis',
    language: 'fr',
  ),
  PlatformCountryDefault(
    country: 'SN',
    label: 'Senegal',
    currency: 'XOF',
    timezone: 'Africa/Dakar',
    language: 'fr',
  ),
  PlatformCountryDefault(
    country: 'CI',
    label: 'Cote d Ivoire',
    currency: 'XOF',
    timezone: 'Africa/Abidjan',
    language: 'fr',
  ),
  PlatformCountryDefault(
    country: 'CM',
    label: 'Cameroun',
    currency: 'XAF',
    timezone: 'Africa/Douala',
    language: 'fr',
  ),
  PlatformCountryDefault(
    country: 'FR',
    label: 'France',
    currency: 'EUR',
    timezone: 'Europe/Paris',
    language: 'fr',
  ),
  PlatformCountryDefault(
    country: 'TR',
    label: 'Turquie',
    currency: 'TRY',
    timezone: 'Europe/Istanbul',
    language: 'tr',
  ),
  PlatformCountryDefault(
    country: 'US',
    label: 'Etats-Unis',
    currency: 'USD',
    timezone: 'America/New_York',
    language: 'en',
  ),
];

class CompanyCreateScreen extends ConsumerStatefulWidget {
  const CompanyCreateScreen({super.key});

  @override
  ConsumerState<CompanyCreateScreen> createState() =>
      _CompanyCreateScreenState();
}

class _CompanyCreateScreenState extends ConsumerState<CompanyCreateScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _city = TextEditingController();
  final _managerFirstName = TextEditingController();
  final _managerLastName = TextEditingController();
  final _managerEmail = TextEditingController();
  String _selectedCountryCode = _fallbackCountryDefaults.first.country;
  bool _activateImmediately = false;
  bool _submitting = false;

  String? _required(String? value) =>
      value == null || value.trim().isEmpty ? 'Champ requis' : null;

  String? _emailValidator(String? value) {
    final trimmed = value?.trim() ?? '';
    if (trimmed.isEmpty) return 'Champ requis';
    if (!trimmed.contains('@') || !trimmed.contains('.')) {
      return 'Email invalide';
    }
    return null;
  }

  @override
  void dispose() {
    for (final controller in [
      _name,
      _email,
      _city,
      _managerFirstName,
      _managerLastName,
      _managerEmail,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final selectedCountry = _selectedCountry(_countries());
    setState(() => _submitting = true);
    try {
      final company = await ref
          .read(platformRepositoryProvider)
          .createCompany(
            name: _name.text.trim(),
            email: _email.text.trim(),
            country: selectedCountry.country,
            city: _city.text.trim(),
            managerFirstName: _managerFirstName.text.trim(),
            managerLastName: _managerLastName.text.trim(),
            managerEmail: _managerEmail.text.trim(),
            status: _activateImmediately ? 'active' : 'trial',
          );
      ref.invalidate(platformCompaniesProvider);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Entreprise creee')));
      if (company.id.isNotEmpty) {
        final route = '/platform/companies/${Uri.encodeComponent(company.id)}';
        context.go(route);
      } else {
        context.go('/platform/companies');
      }
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.toString())));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final countries = _countries();
    return MobilePage(
      appBar: MobileTopBar(
        title: 'Nouveau client',
        subtitle: 'Provisionnement plateforme',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
      ),
      children: [
        Form(
          key: _formKey,
          child: MobilePanel(
            child: Column(
              children: [
                _field(
                  _name,
                  'Nom entreprise',
                  Icons.business_rounded,
                  validator: _required,
                ),
                _field(
                  _email,
                  'Email entreprise',
                  Icons.mail_rounded,
                  keyboardType: TextInputType.emailAddress,
                  validator: _emailValidator,
                ),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        _city,
                        'Ville',
                        Icons.location_city,
                        validator: _required,
                      ),
                    ),
                  ],
                ),
                _countryPicker(countries),
                _countryPreview(_selectedCountry(countries)),
                _activationSwitch(),
                _field(
                  _managerFirstName,
                  'Prenom manager principal',
                  Icons.person_rounded,
                  validator: _required,
                ),
                _field(
                  _managerLastName,
                  'Nom manager principal',
                  Icons.person_rounded,
                  validator: _required,
                ),
                _field(
                  _managerEmail,
                  'Email manager principal',
                  Icons.alternate_email_rounded,
                  keyboardType: TextInputType.emailAddress,
                  validator: _emailValidator,
                ),
                const SizedBox(height: 14),
                MobilePrimaryAction(
                  icon: Icons.add_business_rounded,
                  label: _submitting ? 'Creation...' : 'Creer le client',
                  onPressed: _submitting ? null : _submit,
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  List<PlatformCountryDefault> _countries() {
    return ref
        .watch(platformCountryDefaultsProvider)
        .maybeWhen(
          data: (items) => items.isNotEmpty ? items : _fallbackCountryDefaults,
          orElse: () => _fallbackCountryDefaults,
        );
  }

  PlatformCountryDefault _selectedCountry(
    List<PlatformCountryDefault> countries,
  ) {
    return countries.firstWhere(
      (country) => country.country == _selectedCountryCode,
      orElse: () => countries.first,
    );
  }

  Widget _field(
    TextEditingController controller,
    String label,
    IconData icon, {
    TextInputType? keyboardType,
    String? Function(String?)? validator,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: controller,
        keyboardType: keyboardType,
        style: const TextStyle(color: MobileSurface.text),
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: Icon(icon),
          filled: true,
          fillColor: MobileSurface.chip,
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
        ),
        validator: validator,
      ),
    );
  }

  Widget _countryPicker(List<PlatformCountryDefault> countries) {
    final selected = _selectedCountry(countries);

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DropdownButtonFormField<PlatformCountryDefault>(
        initialValue: selected,
        dropdownColor: MobileSurface.surface,
        iconEnabledColor: MobileSurface.secondary,
        style: const TextStyle(color: MobileSurface.text),
        decoration: InputDecoration(
          labelText: 'Pays du client',
          prefixIcon: const Icon(Icons.public_rounded),
          filled: true,
          fillColor: MobileSurface.chip,
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
        ),
        items:
            countries
                .map(
                  (country) => DropdownMenuItem(
                    value: country,
                    child: Text('${country.label} (${country.country})'),
                  ),
                )
                .toList(),
        onChanged: (country) {
          if (country == null) return;
          setState(() => _selectedCountryCode = country.country);
        },
      ),
    );
  }

  Widget _countryPreview(PlatformCountryDefault selectedCountry) {
    final items = [
      ('Devise', selectedCountry.currency),
      ('Fuseau', selectedCountry.timezone),
      ('Langue', selectedCountry.language.toUpperCase()),
    ];

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: MobileSurface.cardDecoration(
        color: MobileSurface.chip,
        radius: 12,
      ),
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children:
            items
                .map(
                  (item) => Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 7,
                    ),
                    decoration: BoxDecoration(
                      color: MobileSurface.surface,
                      borderRadius: BorderRadius.circular(999),
                      border: Border.all(color: MobileSurface.border),
                    ),
                    child: Text(
                      '${item.$1}: ${item.$2}',
                      style: const TextStyle(
                        color: MobileSurface.secondary,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                )
                .toList(),
      ),
    );
  }

  Widget _activationSwitch() {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: MobileSurface.cardDecoration(
        color: MobileSurface.chip,
        radius: 12,
      ),
      child: SwitchListTile.adaptive(
        value: _activateImmediately,
        onChanged: (value) => setState(() => _activateImmediately = value),
        activeThumbColor: Colors.white,
        activeTrackColor: const Color(0xFF10B981),
        title: const Text(
          'Activer immediatement',
          style: TextStyle(
            color: MobileSurface.text,
            fontWeight: FontWeight.w600,
          ),
        ),
        subtitle: Text(
          _activateImmediately
              ? 'Le client sera cree en statut actif.'
              : 'Le client demarre en essai, puis peut etre active depuis sa fiche.',
          style: const TextStyle(color: MobileSurface.muted, fontSize: 12),
        ),
      ),
    );
  }
}
