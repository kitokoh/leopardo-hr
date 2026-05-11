import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';

class CompanyRequestScreen extends ConsumerStatefulWidget {
  const CompanyRequestScreen({super.key});

  @override
  ConsumerState<CompanyRequestScreen> createState() =>
      _CompanyRequestScreenState();
}

class _CompanyRequestScreenState extends ConsumerState<CompanyRequestScreen> {
  final _formKey = GlobalKey<FormState>();
  final _companyNameController = TextEditingController();
  final _sectorController = TextEditingController();
  final _cityController = TextEditingController();
  final _managerNameController = TextEditingController();
  final _managerIdCardController = TextEditingController();
  final _managerPhoneController = TextEditingController();
  final _notesController = TextEditingController();

  bool _isLoading = false;

  @override
  void dispose() {
    _companyNameController.dispose();
    _sectorController.dispose();
    _cityController.dispose();
    _managerNameController.dispose();
    _managerIdCardController.dispose();
    _managerPhoneController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() => _isLoading = true);

    try {
      final apiClient = ref.read(apiClientProvider);
      await apiClient.dio.post(
        '/company-requests',
        data: {
          'company_name': _companyNameController.text.trim(),
          'sector': _sectorController.text.trim(),
          'country': 'DZ', // Default for MVP
          'city': _cityController.text.trim(),
          'manager_name': _managerNameController.text.trim(),
          'manager_id_card': _managerIdCardController.text.trim(),
          'manager_phone': _managerPhoneController.text.trim(),
          'notes': _notesController.text.trim(),
        },
      );

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Demande envoyée avec succès !')),
        );
        context.pop();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Erreur : $e')));
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.backgroundFor(context),
      appBar: AppBar(title: const Text('Demande d\'entreprise')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Parlez-nous de votre entreprise',
                style: AppTypography.title.copyWith(
                  color: AppColors.textPrimaryFor(context),
                ),
              ),
              const SizedBox(height: 24),
              _buildSectionTitle('Informations Entreprise'),
              _buildField(
                _companyNameController,
                'Nom de l\'entreprise',
                Icons.business,
              ),
              _buildField(
                _sectorController,
                'Secteur d\'activité',
                Icons.category,
              ),
              _buildField(_cityController, 'Ville', Icons.location_city),

              const SizedBox(height: 24),
              _buildSectionTitle('Détails du Responsable'),
              _buildField(
                _managerNameController,
                'Nom complet du responsable',
                Icons.person,
              ),
              _buildField(
                _managerIdCardController,
                'N° Carte d\'identité (Optionnel)',
                Icons.badge,
                required: false,
              ),
              _buildField(
                _managerPhoneController,
                'Téléphone du responsable',
                Icons.phone,
              ),

              const SizedBox(height: 24),
              _buildSectionTitle('Notes Complémentaires'),
              TextFormField(
                controller: _notesController,
                maxLines: 3,
                decoration: const InputDecoration(
                  hintText: 'Comment pouvons-nous vous aider ?',
                ),
              ),
              const SizedBox(height: 32),
              ElevatedButton(
                onPressed: _isLoading ? null : _submit,
                child:
                    _isLoading
                        ? const CircularProgressIndicator(color: Colors.white)
                        : const Text('Envoyer la demande'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSectionTitle(String title) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Text(
        title,
        style: AppTypography.subtitle.copyWith(
          color: AppColors.rh,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }

  Widget _buildField(
    TextEditingController controller,
    String label,
    IconData icon, {
    bool required = true,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: controller,
        decoration: InputDecoration(labelText: label, prefixIcon: Icon(icon)),
        validator:
            required ? (v) => (v ?? '').isEmpty ? 'Obligatoire' : null : null,
      ),
    );
  }
}
