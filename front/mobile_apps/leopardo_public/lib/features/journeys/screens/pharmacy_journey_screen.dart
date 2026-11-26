import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';
import '../../../core/providers/app_providers.dart';
import '../domain/pharmacy_models.dart';
import '../providers/journey_providers.dart';
import '../widgets/journey_widgets.dart';

/// Parcours de la verticale **Pharmacie** (pharmacy) — #9945.
///
/// ## Deux écrans, en lecture seule
///
/// Annuaire → fiche. Il n'y a **pas** de formulaire de contact : `pharmacy`
/// n'est pas dans `PublicProfileLocator::types()`, une demande serait refusée
/// en 422. Le blocage `contact` du registre reste donc déclaré, et l'écran ne
/// promet pas une action qu'il ne peut pas tenir.
///
/// ## Trois états distincts, jamais confondus
///
/// 1. **Panne API** → « service indisponible », avec un bouton réessayer.
/// 2. **Aucune officine publiée** → « aucune officine n'a activé sa
///    publication » : c'est un état honnête, pas une erreur.
/// 3. **Aucun résultat pour cette recherche** → inviter à élargir.
///
/// Les confondre ferait croire à une panne là où il n'y a simplement pas
/// encore d'opt-in, ou l'inverse.
class PharmacyJourneyScreen extends ConsumerStatefulWidget {
  const PharmacyJourneyScreen({super.key, this.initialSlug});

  /// Ouvre directement une fiche (lien profond).
  final String? initialSlug;

  @override
  ConsumerState<PharmacyJourneyScreen> createState() =>
      _PharmacyJourneyScreenState();
}

class _PharmacyJourneyScreenState extends ConsumerState<PharmacyJourneyScreen> {
  bool _loading = false;
  PharmacyDirectory? _directory;
  Pharmacy? _selected;

  final TextEditingController _query = TextEditingController();
  final TextEditingController _city = TextEditingController();

  @override
  void initState() {
    super.initState();
    Future.microtask(() async {
      if (widget.initialSlug != null) {
        await _openSlug(widget.initialSlug!);
      } else {
        await _load();
      }
    });
  }

  @override
  void dispose() {
    _query.dispose();
    _city.dispose();
    super.dispose();
  }

  PharmacyGateway get _gateway => ref.read(pharmacyGatewayProvider);

  Future<void> _load() async {
    setState(() => _loading = true);

    final directory = await _gateway.directory(
      query: _query.text,
      city: _city.text,
    );

    if (!mounted) return;
    setState(() {
      _directory = directory;
      _loading = false;
    });
  }

  Future<void> _openSlug(String slug) async {
    setState(() => _loading = true);

    final pharmacy = await _gateway.bySlug(slug);

    if (!mounted) return;
    setState(() {
      _selected = pharmacy;
      _loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final strings = ref.watch(appStringsProvider);

    return JourneyScaffold(
      strings: strings,
      title: strings.t('journeyVerticalPharmacy'),
      child: _selected != null
          ? _detailView(strings, _selected!)
          : _directoryView(strings),
    );
  }

  Widget _directoryView(AppStrings strings) {
    final directory = _directory;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        JourneyNotice(text: strings.t('pharmacyDirectoryNotice')),
        JourneyField(
          controller: _query,
          label: strings.t('pharmacySearch'),
          hint: strings.t('pharmacySearchHint'),
        ),
        JourneyField(
          controller: _city,
          label: strings.t('pharmacyCity'),
          hint: strings.t('travelCityHint'),
        ),
        FilledButton.icon(
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.search, size: 18),
          label: Text(
            _loading ? strings.t('stateLoading') : strings.t('pharmacySearchCta'),
          ),
        ),
        const SizedBox(height: 16),
        if (_loading && directory == null)
          const Center(child: CircularProgressIndicator())
        // Panne API : ce n'est pas « aucune officine », c'est « on ne sait
        // pas ». Le message et l'action diffèrent.
        else if (directory != null && !directory.available)
          JourneyMessageView(
            icon: Icons.wifi_off,
            title: strings.t('stateOfflineTitle'),
            body: strings.t('stateOfflineBody'),
            action: OutlinedButton.icon(
              onPressed: _load,
              icon: const Icon(Icons.refresh, size: 18),
              label: Text(strings.t('stateRetry')),
            ),
          )
        // Aucune officine n'a activé sa publication : état honnête.
        else if (directory != null && directory.isEmpty)
          JourneyMessageView(
            icon: Icons.local_pharmacy_outlined,
            title: strings.t('pharmacyEmptyTitle'),
            body: strings.t('pharmacyEmptyBody'),
          )
        else if (directory != null)
          for (final pharmacy in directory.pharmacies)
            _pharmacyCard(strings, pharmacy),
      ],
    );
  }

  Widget _pharmacyCard(AppStrings strings, Pharmacy pharmacy) {
    final duty = _dutyLabel(strings, pharmacy.onDuty);

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: ListTile(
        leading: const Icon(Icons.local_pharmacy_outlined, size: 20),
        title: Text(
          pharmacy.name,
          style: AppTypography.title.copyWith(fontSize: 14),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (pharmacy.city != null)
              Text(
                pharmacy.city!,
                style: AppTypography.caption.copyWith(
                  color: AppColors.textSecondaryFor(context),
                ),
              ),
            // `null` n'affiche RIEN : l'absence d'information n'est pas
            // l'information « pas de garde ».
            if (duty != null)
              Text(
                duty,
                style: AppTypography.caption.copyWith(
                  color: pharmacy.onDuty == true
                      ? AppColors.success
                      : AppColors.textSecondaryFor(context),
                ),
              ),
          ],
        ),
        trailing: const Icon(Icons.chevron_right, size: 18),
        onTap: () => setState(() => _selected = pharmacy),
      ),
    );
  }

  Widget _detailView(AppStrings strings, Pharmacy pharmacy) {
    final duty = _dutyLabel(strings, pharmacy.onDuty);

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        JourneySectionTitle(text: pharmacy.name),
        if (pharmacy.city != null)
          _KeyValue(label: strings.t('pharmacyCity'), value: pharmacy.city!),
        if (pharmacy.country != null)
          _KeyValue(label: strings.t('careersCompany'), value: pharmacy.country!),
        if (duty != null)
          _KeyValue(label: strings.t('pharmacyDuty'), value: duty),
        if (pharmacy.description != null) ...[
          const Divider(height: 28),
          JourneySectionTitle(text: strings.t('careersDescription')),
          Text(
            pharmacy.description!,
            style: AppTypography.bodySmall.copyWith(
              color: AppColors.textPrimaryFor(context),
              height: 1.5,
            ),
          ),
        ],
        if (pharmacy.hasServices) ...[
          const Divider(height: 28),
          JourneySectionTitle(text: strings.t('pharmacyServices')),
          for (final service in pharmacy.services)
            Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Text(
                '· $service',
                style: AppTypography.bodySmall.copyWith(
                  color: AppColors.textPrimaryFor(context),
                ),
              ),
            ),
        ],
        const SizedBox(height: 20),
        // Le contact n'est PAS proposé : `pharmacy` n'est pas dans
        // `PublicProfileLocator::types()`, la demande serait refusée en 422.
        // On le dit, plutôt que d'afficher un bouton inopérant.
        JourneyNotice(
          text: strings.t('pharmacyContactUnavailable'),
          color: AppColors.warning,
          icon: Icons.info_outline,
        ),
        OutlinedButton.icon(
          onPressed: () => setState(() => _selected = null),
          icon: const Icon(Icons.arrow_back, size: 18),
          label: Text(strings.t('pharmacyBackToDirectory')),
        ),
      ],
    );
  }

  /// Libellé de garde, ou `null` si l'information n'est pas publiée.
  ///
  /// Règle du domaine (identique au web) : on n'écrit jamais « fermée » quand
  /// on ne sait pas.
  String? _dutyLabel(AppStrings strings, bool? onDuty) {
    if (onDuty == null) return null;
    return onDuty
        ? strings.t('pharmacyOnDuty')
        : strings.t('pharmacyRegularHours');
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
