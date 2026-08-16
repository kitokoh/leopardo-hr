import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'package:leopardo_core/core/api/api_exceptions.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';

class CompanyRequestScreen extends ConsumerStatefulWidget {
  const CompanyRequestScreen({super.key});

  @override
  ConsumerState<CompanyRequestScreen> createState() =>
      _CompanyRequestScreenState();
}

class _CompanyRequestScreenState extends ConsumerState<CompanyRequestScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _sectorCtrl = TextEditingController();
  final _countryCtrl = TextEditingController();
  final _cityCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  bool _loading = false;
  bool _submitted = false;

  @override
  void dispose() {
    _nameCtrl.dispose();
    _emailCtrl.dispose();
    _sectorCtrl.dispose();
    _countryCtrl.dispose();
    _cityCtrl.dispose();
    _phoneCtrl.dispose();
    _descCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    HapticFeedback.mediumImpact();

    setState(() => _loading = true);

    try {
      final repo = ref.read(userAuthRepositoryProvider);
      await repo.submitCompanyRequest(
        companyName: _nameCtrl.text.trim(),
        email: _emailCtrl.text.trim(),
        sector:
            _sectorCtrl.text.trim().isEmpty ? null : _sectorCtrl.text.trim(),
        country:
            _countryCtrl.text.trim().isEmpty ? null : _countryCtrl.text.trim(),
        city: _cityCtrl.text.trim().isEmpty ? null : _cityCtrl.text.trim(),
        phone: _phoneCtrl.text.trim().isEmpty ? null : _phoneCtrl.text.trim(),
        description:
            _descCtrl.text.trim().isEmpty ? null : _descCtrl.text.trim(),
      );

      if (mounted) {
        setState(() => _submitted = true);
      }
    } catch (e) {
      if (mounted) {
        final l10n = context.l10n;
        final msg =
            e is ApiException ? e.message : l10n.userAuthSubmitError;
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(msg)));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final bg = AppColors.backgroundFor(context);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    if (_submitted) {
      return Scaffold(
        backgroundColor: bg,
        body: SafeArea(
          child: Center(
            child: Padding(
              padding: const EdgeInsets.all(32),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 80,
                    height: 80,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: AppColors.success.withValues(alpha: 0.12),
                    ),
                    child: const Icon(
                      Icons.check_circle_outline,
                      color: AppColors.success,
                      size: 40,
                    ),
                  )
                      .animate()
                      .fadeIn(duration: 400.ms)
                      .scale(begin: const Offset(0.5, 0.5)),
                  const SizedBox(height: 20),
                  Text(
                    l10n.userAuthCompanyRequestTitle,
                    style: AppTypography.title.copyWith(color: text),
                    textAlign: TextAlign.center,
                  ).animate().fadeIn(delay: 200.ms),
                  const SizedBox(height: 8),
                  Text(
                    l10n.userAuthCompanyRequestBody,
                    style: AppTypography.bodySmall.copyWith(color: muted),
                    textAlign: TextAlign.center,
                  ).animate().fadeIn(delay: 300.ms),
                  const SizedBox(height: 28),
                  ElevatedButton(
                    onPressed: () => context.go('/user-home'),
                    child: Text(l10n.userAuthBackToHome),
                  ).animate().fadeIn(delay: 400.ms),
                ],
              ),
            ),
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        title: Text(l10n.userAuthCreateCompany),
        leading: IconButton(
          tooltip: l10n.authBackTooltip,
          icon: const Icon(Icons.arrow_back),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/user-home');
            }
          },
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppColors.ia.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.ia.withValues(alpha: 0.2)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.info_outline, color: AppColors.ia, size: 20),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      l10n.userAuthCompanyRequestInfo,
                      style: AppTypography.caption.copyWith(color: muted),
                    ),
                  ),
                ],
              ),
            ).animate().fadeIn(duration: 300.ms),
            const SizedBox(height: 20),
            _buildForm(l10n, text, muted),
          ],
        ),
      ),
    );
  }

  Widget _buildForm(AppLocalizations l10n, Color text, Color muted) {
    return Form(
      key: _formKey,
      autovalidateMode: AutovalidateMode.onUserInteraction,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TextFormField(
            controller: _nameCtrl,
            textInputAction: TextInputAction.next,
            decoration: InputDecoration(
              labelText: '${l10n.userAuthCompanyName} *',
              prefixIcon: const Icon(Icons.business_outlined),
            ),
            validator: (v) => (v?.trim().isEmpty ?? true) ? l10n.commonRequired : null,
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _emailCtrl,
            keyboardType: TextInputType.emailAddress,
            textInputAction: TextInputAction.next,
            decoration: InputDecoration(
              labelText: '${l10n.userAuthCompanyEmail} *',
              prefixIcon: const Icon(Icons.email_outlined),
            ),
            validator: (v) {
              final email = v?.trim() ?? '';
              if (email.isEmpty) return l10n.authEmailRequired;
              if (!email.contains('@')) return l10n.authEmailInvalid;
              return null;
            },
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _sectorCtrl,
            textInputAction: TextInputAction.next,
            decoration: InputDecoration(
              labelText: l10n.userAuthSector,
              prefixIcon: const Icon(Icons.category_outlined),
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: TextFormField(
                  controller: _countryCtrl,
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(
                    labelText: l10n.userAuthCountry,
                    prefixIcon: const Icon(Icons.public_outlined),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextFormField(
                  controller: _cityCtrl,
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(
                    labelText: l10n.userAuthCity,
                    prefixIcon: const Icon(Icons.location_city_outlined),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _phoneCtrl,
            keyboardType: TextInputType.phone,
            textInputAction: TextInputAction.next,
            decoration: InputDecoration(
              labelText: l10n.userAuthPhone,
              prefixIcon: const Icon(Icons.phone_outlined),
            ),
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _descCtrl,
            maxLines: 3,
            textInputAction: TextInputAction.done,
            decoration: InputDecoration(
              labelText: l10n.userAuthDescription,
              prefixIcon: const Icon(Icons.description_outlined),
              alignLabelWithHint: true,
            ),
          ),
          const SizedBox(height: 24),
          ElevatedButton.icon(
            onPressed: _loading ? null : _submit,
            icon: _loading
                ? const SizedBox(
                    height: 18,
                    width: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.send_outlined),
            label: Text(l10n.userAuthSubmitRequest),
          ),
        ],
      ),
    ).animate().fadeIn(delay: 200.ms, duration: 400.ms);
  }
}
