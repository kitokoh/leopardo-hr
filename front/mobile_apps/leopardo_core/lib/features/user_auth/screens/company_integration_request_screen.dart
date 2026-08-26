import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/providers/core_providers.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_core/models/app_user.dart';

/// #5540 — Recherche d'entreprises et soumission de demandes d'intégration.
///
/// L'utilisateur saisit le nom d'une entreprise, choisit parmi les résultats
/// et envoie une demande pour être lié en tant qu'employé.
class CompanyIntegrationRequestScreen extends ConsumerStatefulWidget {
  const CompanyIntegrationRequestScreen({super.key});

  @override
  ConsumerState<CompanyIntegrationRequestScreen> createState() =>
      _CompanyIntegrationRequestScreenState();
}

class _CompanyIntegrationRequestScreenState
    extends ConsumerState<CompanyIntegrationRequestScreen> {
  final _searchController = TextEditingController();
  final _messageController = TextEditingController();
  List<CompanySearchResult> _results = [];
  bool _searching = false;
  bool _sending = false;
  CompanySearchResult? _selectedCompany;
  Timer? _debounce;
  String? _searchError;

  @override
  void dispose() {
    _searchController.dispose();
    _messageController.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  void _onSearchChanged(String value) {
    _debounce?.cancel();
    if (value.trim().length < 2) {
      setState(() {
        _results = [];
        _selectedCompany = null;
        _searchError = null;
      });
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 400), () => _search(value));
  }

  Future<void> _search(String query) async {
    setState(() {
      _searching = true;
      _searchError = null;
      _selectedCompany = null;
    });
    try {
      final repo = ref.read(userAuthRepositoryProvider);
      final results = await repo.searchCompanies(query.trim());
      setState(() => _results = results);
    } catch (e) {
      setState(() {
        _results = [];
        _searchError = e.toString();
      });
    } finally {
      setState(() => _searching = false);
    }
  }

  Future<void> _sendRequest() async {
    final company = _selectedCompany;
    if (company == null) return;

    // Capture l10n before async gap.
    final sentMsg = context.l10n
        .personalOnboardingRequestSent(company.name);

    setState(() => _sending = true);
    try {
      final repo = ref.read(userAuthRepositoryProvider);
      await repo.submitIntegrationRequest(
        targetCompanyId: company.id,
        targetCompanyName: company.name,
        message: _messageController.text.trim().isEmpty
            ? null
            : _messageController.text.trim(),
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(sentMsg),
          backgroundColor: AppColors.success,
        ),
      );
      context.pop();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
              context.l10n.personalOnboardingRequestError(e.toString())),
          backgroundColor: AppColors.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final bg = AppColors.backgroundFor(context);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        backgroundColor: bg,
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back, color: text),
          onPressed: () => context.pop(),
        ),
        title: Text(
          l10n.personalOnboardingSearchTitle,
          style: AppTypography.subtitle.copyWith(color: text),
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            // ── Search bar ──────────────────────────────────────────────────
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    l10n.personalOnboardingSearchSubtitle,
                    style: AppTypography.bodySmall.copyWith(color: muted),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _searchController,
                    autofocus: true,
                    style: AppTypography.body.copyWith(color: text),
                    decoration: InputDecoration(
                      hintText: l10n.personalOnboardingSearchHint,
                      hintStyle:
                          AppTypography.body.copyWith(color: muted),
                      prefixIcon: Icon(Icons.search, color: muted),
                      suffixIcon: _searching
                          ? const Padding(
                              padding: EdgeInsets.all(12),
                              child: SizedBox(
                                width: 16,
                                height: 16,
                                child: CircularProgressIndicator(
                                    strokeWidth: 2),
                              ),
                            )
                          : _searchController.text.isNotEmpty
                              ? IconButton(
                                  icon: Icon(Icons.clear, color: muted),
                                  onPressed: () {
                                    _searchController.clear();
                                    setState(() {
                                      _results = [];
                                      _selectedCompany = null;
                                    });
                                  },
                                )
                              : null,
                      filled: true,
                      fillColor: MobileSurface.surface,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: BorderSide.none,
                      ),
                    ),
                    onChanged: _onSearchChanged,
                  ),
                  if (_searchController.text.trim().length == 1)
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Text(
                        l10n.personalOnboardingSearchMinChars,
                        style: AppTypography.caption
                            .copyWith(color: AppColors.warning),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            // ── Results list ─────────────────────────────────────────────────
            if (_selectedCompany == null)
              Expanded(
                child: _searchError != null
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(20),
                          child: Text(
                            l10n.personalOnboardingSearchError(_searchError!),
                            style: AppTypography.body
                                .copyWith(color: AppColors.danger),
                            textAlign: TextAlign.center,
                          ),
                        ),
                      )
                    : _results.isEmpty &&
                            !_searching &&
                            _searchController.text.trim().length >= 2
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(20),
                          child: Text(
                            l10n.personalOnboardingSearchEmpty(
                                _searchController.text.trim()),
                            style:
                                AppTypography.body.copyWith(color: muted),
                            textAlign: TextAlign.center,
                          ),
                        ),
                      )
                    : ListView.builder(
                        padding:
                            const EdgeInsets.fromLTRB(20, 8, 20, 20),
                        itemCount: _results.length,
                        itemBuilder: (context, index) {
                          final company = _results[index];
                          return _CompanyResultTile(
                            company: company,
                            onTap: () => setState(
                                () => _selectedCompany = company),
                          );
                        },
                      ),
              )
            else
              // ── Request form ─────────────────────────────────────────────
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
                  children: [
                    _SelectedCompanyCard(
                      company: _selectedCompany!,
                      onClear: () => setState(() => _selectedCompany = null),
                      l10n: l10n,
                    ),
                    const SizedBox(height: 20),
                    Text(
                      l10n.personalOnboardingRequestMessageLabel,
                      style: AppTypography.bodySmall.copyWith(
                        color: muted,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Container(
                      decoration: BoxDecoration(
                        color: MobileSurface.surface,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: MobileSurface.border),
                      ),
                      child: TextField(
                        controller: _messageController,
                        maxLines: 4,
                        style: AppTypography.body.copyWith(color: text),
                        decoration: InputDecoration(
                          hintText:
                              l10n.personalOnboardingRequestMessageHint,
                          hintStyle:
                              AppTypography.body.copyWith(color: muted),
                          border: InputBorder.none,
                          contentPadding: const EdgeInsets.all(16),
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        style: FilledButton.styleFrom(
                          backgroundColor: AppColors.rh,
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        onPressed: _sending ? null : _sendRequest,
                        child: _sending
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : Text(
                                l10n.personalOnboardingRequestSend,
                                style: AppTypography.body.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _CompanyResultTile extends StatelessWidget {
  const _CompanyResultTile({
    required this.company,
    required this.onTap,
  });

  final CompanySearchResult company;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          decoration: BoxDecoration(
            color: MobileSurface.surface,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: MobileSurface.border),
          ),
          child: Row(
            children: [
              const Icon(
                Icons.business_outlined,
                color: MobileSurface.secondary,
                size: 20,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      company.name,
                      style: AppTypography.body.copyWith(
                        color: text,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (company.city != null || company.country != null)
                      Text(
                        [company.city, company.country]
                            .whereType<String>()
                            .join(', '),
                        style:
                            AppTypography.caption.copyWith(color: muted),
                      ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right, color: muted, size: 20),
            ],
          ),
        ),
      ),
    );
  }
}

class _SelectedCompanyCard extends StatelessWidget {
  const _SelectedCompanyCard({
    required this.company,
    required this.onClear,
    required this.l10n,
  });

  final CompanySearchResult company;
  final VoidCallback onClear;
  final dynamic l10n;

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.rh.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.rh.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: AppColors.rh.withValues(alpha: 0.15),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.business,
              color: AppColors.rh,
              size: 18,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  company.name,
                  style: AppTypography.body.copyWith(
                    color: text,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (company.city != null || company.country != null)
                  Text(
                    [company.city, company.country]
                        .whereType<String>()
                        .join(', '),
                    style: AppTypography.caption.copyWith(color: muted),
                  ),
              ],
            ),
          ),
          IconButton(
            icon: Icon(Icons.close, color: muted, size: 20),
            onPressed: onClear,
            tooltip: 'Changer',
          ),
        ],
      ),
    );
  }
}
