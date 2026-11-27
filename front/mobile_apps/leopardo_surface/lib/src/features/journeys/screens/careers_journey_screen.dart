import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/i18n/app_strings.dart';
import '../../../core/providers/app_providers.dart';
import '../data/careers_gateway.dart';
import '../domain/careers_models.dart';
import '../providers/journey_providers.dart';
import '../widgets/journey_widgets.dart';

/// Parcours de la verticale **Emploi** (careers) — #9944.
///
/// ## Quatre étapes
///
/// Annuaire des entreprises → portail → offre → candidature, plus un panneau
/// de suivi par e-mail. Même raisonnement que les autres parcours : un écran
/// unique conserve la sélection au retour arrière.
///
/// ## Deux distinctions que l'écran ne doit jamais perdre
///
/// 1. **Offre interne vs offre agrégée.** Une offre `origin: external` se
///    postule chez le partenaire (`apply_url`) : afficher un formulaire natif
///    enverrait le candidat vers un 404. Le bouton change donc de libellé et
///    d'action.
/// 2. **Consentement RGPD.** La case n'est jamais pré-cochée, et le bouton
///    reste inactif tant qu'elle n'est pas cochée — le serveur refuserait en
///    422, mais surtout : une case pré-cochée n'est pas un consentement.
class CareersJourneyScreen extends ConsumerStatefulWidget {
  const CareersJourneyScreen({super.key, this.initialCompanySlug});

  /// Ouvre directement un portail (lien profond).
  final String? initialCompanySlug;

  @override
  ConsumerState<CareersJourneyScreen> createState() =>
      _CareersJourneyScreenState();
}

enum _CareersStep { directory, portal, job, apply, applied }

class _CareersJourneyScreenState extends ConsumerState<CareersJourneyScreen> {
  _CareersStep _step = _CareersStep.directory;

  bool _loading = false;
  Object? _error;

  List<CareerCompany> _companies = const [];
  CareerPortal? _portal;
  CareerJob? _job;

  final TextEditingController _search = TextEditingController();

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _firstName = TextEditingController();
  final TextEditingController _lastName = TextEditingController();
  final TextEditingController _email = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _coverLetter = TextEditingController();
  final TextEditingController _resumeUrl = TextEditingController();
  bool _consent = false;

  bool _submitting = false;
  Object? _submitError;
  CareerApplicationReceipt? _receipt;

  @override
  void initState() {
    super.initState();
    Future.microtask(() async {
      if (widget.initialCompanySlug != null) {
        await _openPortal(widget.initialCompanySlug!);
      } else {
        await _loadCompanies();
      }
    });
  }

  @override
  void dispose() {
    _search.dispose();
    _firstName.dispose();
    _lastName.dispose();
    _email.dispose();
    _phone.dispose();
    _coverLetter.dispose();
    _resumeUrl.dispose();
    super.dispose();
  }

  CareersGateway get _gateway => ref.read(careersGatewayProvider);

  Future<void> _loadCompanies() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final companies = await _gateway.companies(query: _search.text);
      if (!mounted) return;
      setState(() {
        _companies = companies;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _loading = false;
      });
    }
  }

  Future<void> _openPortal(String slug) async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final portal = await _gateway.portal(slug);
      if (!mounted) return;
      setState(() {
        _portal = portal;
        _loading = false;
        _step = _CareersStep.portal;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _loading = false;
      });
    }
  }

  Future<void> _openJob(CareerJob job) async {
    // Une offre agrégée n'existe pas chez nous : pas de détail à charger, on
    // affiche ce qu'on a et on propose la candidature chez le partenaire.
    if (job.isExternal) {
      setState(() {
        _job = job;
        _step = _CareersStep.job;
      });
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final detail = await _gateway.job(_portal!.company.slug, job.id);
      if (!mounted) return;
      setState(() {
        _job = detail;
        _loading = false;
        _step = _CareersStep.job;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _loading = false;
      });
    }
  }

  Future<void> _openPartnerApplication() async {
    final url = _job?.applyUrl;
    if (url == null) return;

    final uri = Uri.tryParse(url);
    if (uri == null) return;

    // `externalApplication` : on quitte l'app, donc on le dit avant.
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _submitting = true;
      _submitError = null;
    });

    try {
      final receipt = await _gateway.apply(
        companySlug: _portal!.company.slug,
        jobId: _job!.id,
        draft: CareerApplicationDraft(
          firstName: _firstName.text,
          lastName: _lastName.text,
          email: _email.text,
          phone: _phone.text,
          coverLetter: _coverLetter.text,
          resumeUrl: _resumeUrl.text,
          consent: _consent,
        ),
      );

      if (!mounted) return;
      setState(() {
        _receipt = receipt;
        _submitting = false;
        _step = _CareersStep.applied;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _submitError = error;
        _submitting = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final strings = ref.watch(appStringsProvider);

    return JourneyScaffold(
      strings: strings,
      title: strings.t('journeyVerticalCareers'),
      child: switch (_step) {
        _CareersStep.directory => _directoryView(strings),
        _CareersStep.portal => _portalView(strings),
        _CareersStep.job => _jobView(strings),
        _CareersStep.apply => _applyView(strings),
        _CareersStep.applied => _appliedView(strings),
      },
    );
  }

  Widget _directoryView(AppStrings strings) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        JourneyNotice(text: strings.t('careersDirectoryNotice')),
        JourneyField(
          controller: _search,
          label: strings.t('careersSearchCompany'),
          hint: strings.t('careersSearchHint'),
        ),
        FilledButton.icon(
          onPressed: _loading ? null : _loadCompanies,
          icon: const Icon(Icons.search, size: 18),
          label: Text(
            _loading ? strings.t('stateLoading') : strings.t('careersSearch'),
          ),
        ),
        const SizedBox(height: 16),
        if (_error != null)
          JourneyErrorView(
            strings: strings,
            error: _error!,
            onRetry: _loadCompanies,
          )
        else if (!_loading && _companies.isEmpty)
          JourneyMessageView(
            icon: Icons.work_outline,
            title: strings.t('careersEmptyTitle'),
            body: strings.t('careersEmptyBody'),
          )
        else
          for (final company in _companies)
            Card(
              margin: const EdgeInsets.only(bottom: 10),
              child: ListTile(
                leading: const Icon(Icons.business_outlined, size: 20),
                title: Text(
                  company.name,
                  style: AppTypography.title.copyWith(fontSize: 14),
                ),
                subtitle: company.country == null
                    ? null
                    : Text(
                        company.country!,
                        style: AppTypography.caption.copyWith(
                          color: AppColors.textSecondaryFor(context),
                        ),
                      ),
                trailing: const Icon(Icons.chevron_right, size: 18),
                onTap: () => _openPortal(company.slug),
              ),
            ),
        const Divider(height: 32),
        _trackingPanel(strings),
      ],
    );
  }

  Widget _portalView(AppStrings strings) {
    final portal = _portal;
    if (portal == null) {
      return JourneyErrorView(strings: strings, error: _error ?? '');
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        JourneySectionTitle(text: portal.company.name),
        if (portal.companyCount > 0 || portal.externalCount > 0)
          JourneyNotice(
            text:
                '${strings.t('careersCompanyOffers')} : ${portal.companyCount} · '
                '${strings.t('careersPartnerOffers')} : ${portal.externalCount}',
          ),
        if (portal.jobs.isEmpty)
          JourneyMessageView(
            icon: Icons.work_off_outlined,
            title: strings.t('careersNoOffersTitle'),
            body: strings.t('careersNoOffersBody'),
          )
        else
          for (final job in portal.jobs) _jobCard(strings, job),
        const Divider(height: 32),
        _trackingPanel(strings, companySlug: portal.company.slug),
      ],
    );
  }

  Widget _jobCard(AppStrings strings, CareerJob job) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: ListTile(
        title: Text(
          job.title,
          style: AppTypography.title.copyWith(fontSize: 14),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (job.location != null)
              Text(
                job.location!,
                style: AppTypography.caption.copyWith(
                  color: AppColors.textSecondaryFor(context),
                ),
              ),
            if (job.contractType != null)
              Text(
                job.contractType!,
                style: AppTypography.caption.copyWith(
                  color: AppColors.textSecondaryFor(context),
                ),
              ),
            // La provenance est dite honnêtement : le candidat doit savoir
            // qu'il postule chez un partenaire, pas ici.
            if (job.isExternal)
              Text(
                '${strings.t('careersExternalOffer')}'
                '${job.sourceLabel == null ? '' : ' · ${job.sourceLabel}'}',
                style: AppTypography.caption.copyWith(color: AppColors.info),
              ),
            if (!job.isOpen)
              Text(
                strings.t('careersClosed'),
                style: AppTypography.caption.copyWith(color: AppColors.danger),
              ),
          ],
        ),
        trailing: const Icon(Icons.chevron_right, size: 18),
        onTap: () => _openJob(job),
      ),
    );
  }

  Widget _jobView(AppStrings strings) {
    final job = _job;
    if (job == null) {
      return JourneyErrorView(strings: strings, error: _error ?? '');
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        JourneySectionTitle(text: job.title),
        if (job.companyName != null)
          _KeyValue(
            label: strings.t('careersCompany'),
            value: job.companyName!,
          ),
        if (job.departmentName != null)
          _KeyValue(
            label: strings.t('careersDepartment'),
            value: job.departmentName!,
          ),
        if (job.location != null)
          _KeyValue(label: strings.t('careersLocation'), value: job.location!),
        if (job.contractType != null)
          _KeyValue(
            label: strings.t('careersContract'),
            value: job.contractType!,
          ),
        if (job.remotePolicy != null)
          _KeyValue(
            label: strings.t('careersRemote'),
            value: job.remotePolicy!,
          ),
        if (job.salaryLabel != null)
          _KeyValue(label: strings.t('careersSalary'), value: job.salaryLabel!),
        if (job.closesAt != null)
          _KeyValue(label: strings.t('careersClosesAt'), value: job.closesAt!),
        if (job.skillsRequired.isNotEmpty)
          _KeyValue(
            label: strings.t('careersSkills'),
            value: job.skillsRequired.join(' · '),
          ),
        if (job.description != null) ...[
          const Divider(height: 28),
          JourneySectionTitle(text: strings.t('careersDescription')),
          Text(
            job.description!,
            style: AppTypography.bodySmall.copyWith(
              color: AppColors.textPrimaryFor(context),
              height: 1.5,
            ),
          ),
        ],
        const SizedBox(height: 20),
        if (!job.isOpen)
          JourneyNotice(
            text: strings.t('careersClosed'),
            color: AppColors.danger,
            icon: Icons.event_busy_outlined,
          )
        else if (job.isExternal)
          FilledButton.icon(
            onPressed: job.applyUrl == null ? null : _openPartnerApplication,
            icon: const Icon(Icons.open_in_new, size: 18),
            label: Text(strings.t('careersApplyPartner')),
          )
        else
          FilledButton(
            onPressed: () => setState(() {
              _submitError = null;
              _step = _CareersStep.apply;
            }),
            child: Text(strings.t('careersApply')),
          ),
      ],
    );
  }

  Widget _applyView(AppStrings strings) {
    String? required(String? value) => (value == null || value.trim().isEmpty)
        ? strings.t('journeyFieldRequired')
        : null;

    String? emailValidator(String? value) {
      final text = value?.trim() ?? '';
      if (text.isEmpty) return strings.t('journeyFieldRequired');
      return text.contains('@') ? null : strings.t('journeyFieldInvalidEmail');
    }

    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          JourneyNotice(text: strings.t('careersApplyNotice')),
          JourneyField(
            controller: _firstName,
            label: strings.t('careersFirstName'),
            validator: required,
          ),
          JourneyField(
            controller: _lastName,
            label: strings.t('careersLastName'),
            validator: required,
          ),
          JourneyField(
            controller: _email,
            label: strings.t('journeyCustomerEmail'),
            keyboardType: TextInputType.emailAddress,
            validator: emailValidator,
          ),
          JourneyField(
            controller: _phone,
            label: strings.t('journeyCustomerPhone'),
            keyboardType: TextInputType.phone,
          ),
          JourneyField(
            controller: _coverLetter,
            label: strings.t('careersCoverLetter'),
            maxLines: 6,
          ),
          JourneyField(
            controller: _resumeUrl,
            label: strings.t('careersResumeUrl'),
            hint: strings.t('careersResumeUrlHint'),
            keyboardType: TextInputType.url,
          ),
          // Jamais pré-cochée : une case cochée d'avance n'est pas un
          // consentement RGPD, et le serveur exige `accepted`.
          CheckboxListTile(
            value: _consent,
            onChanged: (value) => setState(() => _consent = value ?? false),
            title: Text(
              strings.t('careersConsent'),
              style: AppTypography.bodySmall,
            ),
            controlAffinity: ListTileControlAffinity.leading,
            contentPadding: EdgeInsets.zero,
          ),
          if (_submitError != null)
            JourneyErrorView(strings: strings, error: _submitError!),
          FilledButton(
            onPressed: (_submitting || !_consent) ? null : _submit,
            child: Text(
              _submitting
                  ? strings.t('stateLoading')
                  : strings.t('careersSubmitApplication'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _appliedView(AppStrings strings) {
    final receipt = _receipt;
    if (receipt == null) {
      return JourneyErrorView(strings: strings, error: _error ?? '');
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        JourneyNotice(
          text: strings.t('careersAppliedNotice'),
          color: AppColors.success,
          icon: Icons.check_circle_outline,
        ),
        JourneySectionTitle(text: strings.t('careersAppliedTitle')),
        _KeyValue(
          label: strings.t('careersApplicationStatus'),
          value: receipt.status,
        ),
        if (receipt.jobTitle != null)
          _KeyValue(label: strings.t('careersJob'), value: receipt.jobTitle!),
        if (receipt.appliedAt != null)
          _KeyValue(
            label: strings.t('careersAppliedAt'),
            value: receipt.appliedAt!,
          ),
        JourneyNotice(
          text: strings.t('careersTrackHint'),
          color: AppColors.warning,
        ),
        const Divider(height: 32),
        _trackingPanel(strings, companySlug: _portal?.company.slug),
      ],
    );
  }

  /// Panneau de suivi par e-mail.
  ///
  /// Le suivi est un POST : l'e-mail ne doit jamais finir dans une URL.
  Widget _trackingPanel(AppStrings strings, {String? companySlug}) {
    final slug = companySlug ?? _portal?.company.slug;
    if (slug == null) {
      return JourneyNotice(text: strings.t('careersTrackNeedsCompany'));
    }

    return _CareersTrackingPanel(
      strings: strings,
      companySlug: slug,
      onTrack: (email) => _gateway.track(companySlug: slug, email: email),
    );
  }
}

/// Suivi des candidatures d'un candidat, par e-mail.
class _CareersTrackingPanel extends StatefulWidget {
  const _CareersTrackingPanel({
    required this.strings,
    required this.companySlug,
    required this.onTrack,
  });

  final AppStrings strings;
  final String companySlug;
  final Future<CareerTrackingResult> Function(String email) onTrack;

  @override
  State<_CareersTrackingPanel> createState() => _CareersTrackingPanelState();
}

class _CareersTrackingPanelState extends State<_CareersTrackingPanel> {
  final TextEditingController _email = TextEditingController();
  bool _loading = false;
  Object? _error;
  CareerTrackingResult? _result;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _track() async {
    final email = _email.text.trim();
    if (email.isEmpty || !email.contains('@')) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final result = await widget.onTrack(email);
      if (!mounted) return;
      setState(() {
        _result = result;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final strings = widget.strings;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        JourneySectionTitle(text: strings.t('careersTrackTitle')),
        JourneyField(
          controller: _email,
          label: strings.t('journeyCustomerEmail'),
          keyboardType: TextInputType.emailAddress,
        ),
        OutlinedButton.icon(
          onPressed: _loading ? null : _track,
          icon: const Icon(Icons.travel_explore_outlined, size: 18),
          label: Text(strings.t('careersTrack')),
        ),
        if (_loading)
          const Padding(
            padding: EdgeInsets.only(top: 12),
            child: Center(child: CircularProgressIndicator()),
          ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: JourneyErrorView(
              strings: strings,
              error: _error!,
              onRetry: _track,
            ),
          ),
        if (_result != null) ...[
          if (_result!.applications.isEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: JourneyMessageView(
                icon: Icons.inbox_outlined,
                title: strings.t('careersTrackEmptyTitle'),
                body: strings.t('careersTrackEmptyBody'),
              ),
            )
          else
            for (final application in _result!.applications)
              Card(
                margin: const EdgeInsets.only(top: 10),
                child: ListTile(
                  title: Text(
                    application.jobTitle ?? application.reference,
                    style: AppTypography.title.copyWith(fontSize: 13),
                  ),
                  subtitle: Text(
                    '${application.status}'
                    '${application.jobLocation == null ? '' : ' · ${application.jobLocation}'}',
                    style: AppTypography.caption.copyWith(
                      color: AppColors.textSecondaryFor(context),
                    ),
                  ),
                ),
              ),
          if (_result!.retentionDays != null)
            Padding(
              padding: const EdgeInsets.only(top: 10),
              child: JourneyNotice(
                text:
                    '${strings.t('careersRetention')} : ${_result!.retentionDays} ${strings.t('careersDays')}',
              ),
            ),
        ],
      ],
    );
  }
}

class _KeyValue extends StatelessWidget {
  const _KeyValue({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '$label : ',
            style: AppTypography.bodySmall.copyWith(
              color: AppColors.textSecondaryFor(context),
            ),
          ),
          Expanded(
            child: SelectableText(
              value,
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textPrimaryFor(context),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
