import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/company_branding/data/company_branding_repository.dart';
import 'package:leopardo_manager/features/company_branding/providers/company_branding_provider.dart';

class CompanyBrandingScreen extends ConsumerWidget {
  const CompanyBrandingScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final branding = ref.watch(companyBrandingProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Identite entreprise',
        subtitle: 'Logo, couleurs et affichage interne',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: RefreshIndicator(
        color: AppColors.rh,
        backgroundColor: MobileSurface.background,
        onRefresh: () async => ref.refresh(companyBrandingProvider.future),
        child: branding.when(
          data:
              (response) => ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
                children: [
                  _BrandPreview(branding: response.branding),
                  const SizedBox(height: 18),
                  _BrandingForm(initial: response.branding),
                ],
              ),
          loading:
              () => const MobileEmptyLoading(
                label: 'Chargement de l identite entreprise',
              ),
          error:
              (error, _) => ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                children: [
                  MobileErrorPanel(
                    message: error.toString(),
                    onRetry: () => ref.invalidate(companyBrandingProvider),
                  ),
                ],
              ),
        ),
      ),
    );
  }
}

class _BrandPreview extends StatelessWidget {
  const _BrandPreview({required this.branding});

  final CompanyBranding branding;

  @override
  Widget build(BuildContext context) {
    final primary = _hexColor(branding.primaryColor, AppColors.rh);
    final accent = _hexColor(branding.accentColor, AppColors.info);

    return MobilePanel(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 54,
                height: 54,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: primary.withValues(alpha: 0.16),
                  border: Border.all(color: primary.withValues(alpha: 0.5)),
                ),
                child:
                    branding.logoUrl == null
                        ? Icon(Icons.business_rounded, color: primary)
                        : ClipOval(
                          child: Image.network(
                            branding.logoUrl!,
                            fit: BoxFit.cover,
                            errorBuilder:
                                (_, __, ___) => Icon(
                                  Icons.business_rounded,
                                  color: primary,
                                ),
                          ),
                        ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      branding.displayName,
                      style: AppTypography.title.copyWith(
                        color: MobileSurface.text,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Theme ${branding.brandMode}',
                      style: AppTypography.caption.copyWith(
                        color: MobileSurface.muted,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              _ColorChip(label: 'Primaire', color: primary),
              const SizedBox(width: 10),
              _ColorChip(label: 'Accent', color: accent),
            ],
          ),
        ],
      ),
    );
  }
}

class _BrandingForm extends ConsumerStatefulWidget {
  const _BrandingForm({required this.initial});

  final CompanyBranding initial;

  @override
  ConsumerState<_BrandingForm> createState() => _BrandingFormState();
}

class _BrandingFormState extends ConsumerState<_BrandingForm> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _nameCtrl;
  late final TextEditingController _logoCtrl;
  late final TextEditingController _primaryCtrl;
  late final TextEditingController _accentCtrl;
  late String _brandMode;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _nameCtrl = TextEditingController(text: widget.initial.displayName);
    _logoCtrl = TextEditingController(text: widget.initial.logoUrl ?? '');
    _primaryCtrl = TextEditingController(text: widget.initial.primaryColor);
    _accentCtrl = TextEditingController(text: widget.initial.accentColor);
    _brandMode = widget.initial.brandMode;
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _logoCtrl.dispose();
    _primaryCtrl.dispose();
    _accentCtrl.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);

    try {
      await ref
          .read(companyBrandingRepositoryProvider)
          .update(
            CompanyBrandingPayload(
              displayName: _nameCtrl.text,
              logoUrl: _logoCtrl.text,
              primaryColor: _primaryCtrl.text,
              accentColor: _accentCtrl.text,
              brandMode: _brandMode,
            ),
          );
      ref.invalidate(companyBrandingProvider);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Identite entreprise mise a jour')),
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.toString())));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return MobilePanel(
      padding: const EdgeInsets.all(18),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Parametres',
              style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
            ),
            const SizedBox(height: 14),
            _Field(
              controller: _nameCtrl,
              label: 'Nom affiche',
              validator:
                  (value) =>
                      (value == null || value.trim().length < 2)
                          ? 'Nom trop court'
                          : null,
            ),
            _Field(
              controller: _logoCtrl,
              label: 'Logo URL',
              hint: 'https://...',
            ),
            Row(
              children: [
                Expanded(
                  child: _Field(
                    controller: _primaryCtrl,
                    label: 'Couleur principale',
                    validator: _hexValidator,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _Field(
                    controller: _accentCtrl,
                    label: 'Couleur accent',
                    validator: _hexValidator,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            DropdownButtonFormField<String>(
              initialValue: _brandMode,
              dropdownColor: MobileSurface.surface,
              decoration: _inputDecoration('Mode visuel'),
              items: const [
                DropdownMenuItem(value: 'default', child: Text('Defaut')),
                DropdownMenuItem(value: 'dark', child: Text('Sombre')),
                DropdownMenuItem(value: 'light', child: Text('Clair')),
                DropdownMenuItem(value: 'auto', child: Text('Auto')),
              ],
              onChanged:
                  (value) => setState(() => _brandMode = value ?? 'default'),
            ),
            const SizedBox(height: 18),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: _saving ? null : _save,
                icon:
                    _saving
                        ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                        : const Icon(Icons.save_outlined),
                label: Text(_saving ? 'Enregistrement' : 'Enregistrer'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  String? _hexValidator(String? value) {
    final raw = value?.trim() ?? '';
    return RegExp(r'^#[0-9A-Fa-f]{6}$').hasMatch(raw) ? null : 'Format #10B981';
  }
}

class _Field extends StatelessWidget {
  const _Field({
    required this.controller,
    required this.label,
    this.hint,
    this.validator,
  });

  final TextEditingController controller;
  final String label;
  final String? hint;
  final String? Function(String?)? validator;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: controller,
        validator: validator,
        style: AppTypography.body.copyWith(color: MobileSurface.text),
        decoration: _inputDecoration(label).copyWith(hintText: hint),
      ),
    );
  }
}

class _ColorChip extends StatelessWidget {
  const _ColorChip({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: color.withValues(alpha: 0.35)),
        ),
        child: Row(
          children: [
            Container(
              width: 18,
              height: 18,
              decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            ),
            const SizedBox(width: 8),
            Text(
              label,
              style: AppTypography.caption.copyWith(color: MobileSurface.text),
            ),
          ],
        ),
      ),
    );
  }
}

InputDecoration _inputDecoration(String label) {
  return InputDecoration(
    labelText: label,
    labelStyle: const TextStyle(color: MobileSurface.muted),
    filled: true,
    fillColor: MobileSurface.chip,
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(14),
      borderSide: const BorderSide(color: MobileSurface.border),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(14),
      borderSide: const BorderSide(color: AppColors.rh),
    ),
    errorBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(14),
      borderSide: const BorderSide(color: AppColors.danger),
    ),
  );
}

Color _hexColor(String raw, Color fallback) {
  final value = raw.replaceFirst('#', '');
  if (!RegExp(r'^[0-9A-Fa-f]{6}$').hasMatch(value)) return fallback;
  return Color(int.parse('FF$value', radix: 16));
}
