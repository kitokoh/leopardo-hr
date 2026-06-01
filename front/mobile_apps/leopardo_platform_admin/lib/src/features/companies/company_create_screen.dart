import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';

import '../../core/platform_providers.dart';
import 'company_screen.dart';

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
  final _country = TextEditingController(text: 'DZ');
  final _city = TextEditingController();
  final _managerFirstName = TextEditingController();
  final _managerLastName = TextEditingController();
  final _managerEmail = TextEditingController();
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

  String? _countryValidator(String? value) {
    final trimmed = value?.trim().toUpperCase() ?? '';
    if (trimmed.length != 2) return 'Code pays ISO sur 2 lettres';
    return null;
  }

  @override
  void dispose() {
    for (final controller in [
      _name,
      _email,
      _country,
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
    setState(() => _submitting = true);
    try {
      final company = await ref
          .read(platformRepositoryProvider)
          .createCompany(
            name: _name.text.trim(),
            email: _email.text.trim(),
            country: _country.text.trim(),
            city: _city.text.trim(),
            managerFirstName: _managerFirstName.text.trim(),
            managerLastName: _managerLastName.text.trim(),
            managerEmail: _managerEmail.text.trim(),
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
                        _country,
                        'Pays',
                        Icons.flag,
                        validator: _countryValidator,
                      ),
                    ),
                    const SizedBox(width: 10),
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
}
