import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/leopardo_badge.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';

import 'package:leopardo_core/l10n/l10n.dart';

import '../../core/platform_providers.dart';
import '../platform/platform_models.dart';
import 'company_screen.dart';

// PA2-ADM-002: fallback list used only while GET /platform/country-defaults
// has not resolved yet (or fails). Kept in sync with App\Support\CountryDefaults
// on the API and the web admin-dashboard's fallback list so mobile and web
// never diverge on which countries are offered when the network is slow.
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
    country: 'ML',
    label: 'Mali',
    currency: 'XOF',
    timezone: 'Africa/Bamako',
    language: 'fr',
  ),
  PlatformCountryDefault(
    country: 'BF',
    label: 'Burkina Faso',
    currency: 'XOF',
    timezone: 'Africa/Ouagadougou',
    language: 'fr',
  ),
  PlatformCountryDefault(
    country: 'BJ',
    label: 'Benin',
    currency: 'XOF',
    timezone: 'Africa/Porto-Novo',
    language: 'fr',
  ),
  PlatformCountryDefault(
    country: 'TG',
    label: 'Togo',
    currency: 'XOF',
    timezone: 'Africa/Lome',
    language: 'fr',
  ),
  PlatformCountryDefault(
    country: 'NE',
    label: 'Niger',
    currency: 'XOF',
    timezone: 'Africa/Niamey',
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
    country: 'GA',
    label: 'Gabon',
    currency: 'XAF',
    timezone: 'Africa/Libreville',
    language: 'fr',
  ),
  PlatformCountryDefault(
    country: 'CG',
    label: 'Congo',
    currency: 'XAF',
    timezone: 'Africa/Brazzaville',
    language: 'fr',
  ),
  PlatformCountryDefault(
    country: 'TD',
    label: 'Tchad',
    currency: 'XAF',
    timezone: 'Africa/Ndjamena',
    language: 'fr',
  ),
  PlatformCountryDefault(
    country: 'CF',
    label: 'Republique Centrafricaine',
    currency: 'XAF',
    timezone: 'Africa/Bangui',
    language: 'fr',
  ),
  PlatformCountryDefault(
    country: 'GQ',
    label: 'Guinee Equatoriale',
    currency: 'XAF',
    timezone: 'Africa/Malabo',
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
    country: 'GB',
    label: 'Royaume-Uni',
    currency: 'GBP',
    timezone: 'Europe/London',
    language: 'en',
  ),
  PlatformCountryDefault(
    country: 'US',
    label: 'Etats-Unis',
    currency: 'USD',
    timezone: 'America/New_York',
    language: 'en',
  ),
  PlatformCountryDefault(
    country: 'CA',
    label: 'Canada',
    currency: 'CAD',
    timezone: 'America/Toronto',
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

  String? _required(AppLocalizations l10n, String? value) =>
      value == null || value.trim().isEmpty
          ? l10n.companiesRequiredField
          : null;

  String? _emailValidator(AppLocalizations l10n, String? value) {
    final trimmed = value?.trim() ?? '';
    if (trimmed.isEmpty) return l10n.companiesRequiredField;
    if (!trimmed.contains('@') || !trimmed.contains('.')) {
      return l10n.authEmailInvalid;
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
      final company = await ref.read(platformRepositoryProvider).createCompany(
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
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.companiesCompanyCreated)),
      );
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
    final l10n = context.l10n;
    final countries = _countries();
    return MobilePage(
      appBar: MobileTopBar(
        title: l10n.companiesNewClient,
        subtitle: l10n.companiesProvisioning,
        leading: IconButton(
          tooltip: l10n.commonBack,
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
                  l10n.companiesCompanyname,
                  Icons.business_rounded,
                  validator: (v) => _required(l10n, v),
                ),
                _field(
                  _email,
                  l10n.companiesCompanyEmail,
                  Icons.mail_rounded,
                  keyboardType: TextInputType.emailAddress,
                  validator: (v) => _emailValidator(l10n, v),
                ),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        _city,
                        l10n.companiesCity,
                        Icons.location_city,
                        validator: (v) => _required(l10n, v),
                      ),
                    ),
                  ],
                ),
                _countryPicker(countries),
                _countryPreview(_selectedCountry(countries)),
                _activationSwitch(),
                _field(
                  _managerFirstName,
                  l10n.companiesManagerfirst,
                  Icons.person_rounded,
                  validator: (v) => _required(l10n, v),
                ),
                _field(
                  _managerLastName,
                  l10n.companiesManagerlast,
                  Icons.person_rounded,
                  validator: (v) => _required(l10n, v),
                ),
                _field(
                  _managerEmail,
                  l10n.companiesManageremail,
                  Icons.alternate_email_rounded,
                  keyboardType: TextInputType.emailAddress,
                  validator: (v) => _emailValidator(l10n, v),
                ),
                const SizedBox(height: 14),
                MobilePrimaryAction(
                  icon: Icons.add_business_rounded,
                  label: _submitting
                      ? l10n.companiesCreating
                      : l10n.companiesCreateClient,
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
    return ref.watch(platformCountryDefaultsProvider).maybeWhen(
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
          labelText: context.l10n.companiesCountry,
          prefixIcon: const Icon(Icons.public_rounded),
          filled: true,
          fillColor: MobileSurface.chip,
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
        ),
        items: countries
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
      (context.l10n.companiesCurrency, selectedCountry.currency),
      (context.l10n.companiesTimezone, selectedCountry.timezone),
      (
        context.l10n.commonLanguageLabel,
        selectedCountry.language.toUpperCase()
      ),
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
        children: items
            .map(
              (item) => LeopardoBadge(
                label: '${item.$1}: ${item.$2}',
                color: AppColors.rh,
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
        activeTrackColor: AppColors.rh,
        title: Text(
          context.l10n.companiesActivatenow,
          style: TextStyle(
            color: MobileSurface.text,
            fontWeight: FontWeight.w600,
          ),
        ),
        subtitle: Text(
          _activateImmediately
              ? context.l10n.companiesActiveImmediatelyHint
              : context.l10n.companiesTrialHint,
          style: const TextStyle(color: MobileSurface.muted, fontSize: 12),
        ),
      ),
    );
  }
}
