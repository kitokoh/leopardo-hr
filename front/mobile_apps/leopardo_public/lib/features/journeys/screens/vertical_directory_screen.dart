import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';
import '../../../core/providers/app_providers.dart';
import '../domain/journey_registry.dart';
import '../domain/vertical_directory_models.dart';
import '../providers/journey_providers.dart';
import '../widgets/journey_widgets.dart';
import 'contact_request_screen.dart';

/// Annuaire d'une verticale grand public — #9948.
///
/// ## Un écran, cinq verticales
///
/// `restaurant`, `retail`, `hospitality`, `trade` et `vtc` partagent le même
/// contrat de lecture (`GET /public/discover?type=…`) et la même mise en page :
/// recherche, liste, fiche. Le web a cinq vitrines parce que chacune a sa
/// charte ; côté mobile, dupliquer l'écran cinq fois multiplierait par cinq les
/// occasions d'oublier la distinction panne / annuaire vide.
///
/// ## Ce que l'écran n'invente pas
///
/// - Une note n'est affichée que si elle existe ET qu'elle a des avis.
/// - Une distance n'est affichée que si la recherche a été faite avec une
///   position : « 0 km » sans mesure serait un mensonge.
/// - Une verticale dont le contact est bloqué n'affiche pas de bouton de
///   contact — elle affiche le motif du registre.
class VerticalDirectoryScreen extends ConsumerStatefulWidget {
  const VerticalDirectoryScreen({super.key, required this.verticalCode});

  /// Code stable de la verticale (`restaurant`, `retail`, …).
  final String verticalCode;

  @override
  ConsumerState<VerticalDirectoryScreen> createState() =>
      _VerticalDirectoryScreenState();
}

class _VerticalDirectoryScreenState
    extends ConsumerState<VerticalDirectoryScreen> {
  final TextEditingController _query = TextEditingController();
  final TextEditingController _city = TextEditingController();

  bool _loading = false;
  VerticalDirectory? _directory;
  Object? _error;
  VerticalEntry? _selected;

  JourneyVertical? get _vertical => journeyByCode(widget.verticalCode);

  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  @override
  void dispose() {
    _query.dispose();
    _city.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final vertical = _vertical;
    final type = vertical?.discoveryType;
    if (type == null) {
      setState(() => _directory = VerticalDirectory.unavailable);
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final directory =
          await ref.read(verticalDirectoryGatewayProvider).directory(
                type: type,
                query: _query.text,
                city: _city.text,
              );
      if (!mounted) return;
      setState(() {
        _directory = directory;
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
    final strings = ref.watch(appStringsProvider);
    final vertical = _vertical;

    if (vertical == null) {
      return JourneyScaffold(
        strings: strings,
        title: strings.t('journeyDirectoryTitle'),
        child: JourneyMessageView(
          icon: Icons.help_outline,
          title: strings.t('journeyDirectoryUnknownTitle'),
          body: strings.t('journeyDirectoryUnknownBody'),
        ),
      );
    }

    return JourneyScaffold(
      strings: strings,
      title: strings.t(vertical.labelKey),
      child: _selected == null
          ? _listView(strings, vertical)
          : _detailView(strings, vertical, _selected!),
    );
  }

  Widget _listView(AppStrings strings, JourneyVertical vertical) {
    final directory = _directory;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        JourneyNotice(text: strings.t('journeyDirectoryNotice')),
        JourneyField(
          controller: _query,
          label: strings.t('journeyDirectorySearch'),
          hint: strings.t('journeyDirectorySearchHint'),
        ),
        JourneyField(
          controller: _city,
          label: strings.t('journeyDirectoryCity'),
          hint: strings.t('journeyDirectoryCityHint'),
        ),
        FilledButton.icon(
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.search, size: 18),
          label: Text(
            _loading
                ? strings.t('stateLoading')
                : strings.t('journeyDirectorySearchAction'),
          ),
        ),
        const SizedBox(height: 16),
        if (_error != null)
          JourneyErrorView(strings: strings, error: _error!, onRetry: _load)
        else if (directory == null)
          const Center(child: CircularProgressIndicator())
        else if (!directory.available)
          // Panne API : ce n'est PAS « rien à proximité ».
          JourneyMessageView(
            icon: Icons.cloud_off_outlined,
            title: strings.t('journeyDirectoryUnavailableTitle'),
            body: strings.t('journeyDirectoryUnavailableBody'),
            action: OutlinedButton.icon(
              onPressed: _load,
              icon: const Icon(Icons.refresh, size: 18),
              label: Text(strings.t('stateRetry')),
            ),
          )
        else if (directory.isEmpty)
          JourneyMessageView(
            icon: Icons.storefront_outlined,
            title: strings.t('journeyDirectoryEmptyTitle'),
            body: strings.t('journeyDirectoryEmptyBody'),
          )
        else
          for (final entry in directory.entries) _entryCard(strings, entry),
      ],
    );
  }

  Widget _entryCard(AppStrings strings, VerticalEntry entry) {
    final location = entry.location;

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: ListTile(
        title: Text(
          entry.name,
          style: AppTypography.title.copyWith(fontSize: 14),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (location != null)
              Text(
                location,
                style: AppTypography.caption.copyWith(
                  color: AppColors.textSecondaryFor(context),
                ),
              ),
            if (entry.hasRating)
              Text(
                '★ ${entry.ratingAvg!.toStringAsFixed(1)} (${entry.reviewsCount})',
                style: AppTypography.caption.copyWith(
                  color: AppColors.textSecondaryFor(context),
                ),
              ),
            if (entry.distanceKm != null)
              Text(
                '${entry.distanceKm!.toStringAsFixed(1)} km',
                style: AppTypography.caption.copyWith(
                  color: AppColors.textSecondaryFor(context),
                ),
              ),
          ],
        ),
        trailing: const Icon(Icons.chevron_right, size: 20),
        onTap: () => setState(() => _selected = entry),
      ),
    );
  }

  Widget _detailView(
    AppStrings strings,
    JourneyVertical vertical,
    VerticalEntry entry,
  ) {
    final location = entry.location;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Row(
          children: [
            IconButton(
              onPressed: () => setState(() => _selected = null),
              icon: const Icon(Icons.arrow_back, size: 20),
            ),
            Expanded(
              child: JourneySectionTitle(text: entry.name),
            ),
          ],
        ),
        if (location != null)
          _KeyValue(label: strings.t('journeyDirectoryCity'), value: location),
        if (entry.category != null)
          _KeyValue(
            label: strings.t('journeyDirectoryCategory'),
            value: entry.category!,
          ),
        if (entry.hasRating)
          _KeyValue(
            label: strings.t('journeyDirectoryRating'),
            value:
                '★ ${entry.ratingAvg!.toStringAsFixed(1)} (${entry.reviewsCount})',
          ),
        if (entry.distanceKm != null)
          _KeyValue(
            label: strings.t('journeyDirectoryDistance'),
            value: '${entry.distanceKm!.toStringAsFixed(1)} km',
          ),
        if (entry.description != null) ...[
          const Divider(height: 28),
          Text(entry.description!, style: AppTypography.bodySmall),
        ],
        const Divider(height: 28),

        // Le contact n'est proposé que si le registre déclare un resolver
        // public : sinon `POST /public/contact-requests` répondrait 422 et le
        // bouton serait mort.
        if (vertical.canSendRequest)
          FilledButton.icon(
            onPressed: () => _openContact(strings, vertical, entry),
            icon: const Icon(Icons.mail_outline, size: 18),
            label: Text(strings.t('journeyContactAction')),
          )
        else
          JourneyNotice(
            text: strings.t(
              vertical.blockerFor('contact') ?? 'journeyBlockerContactGeneric',
            ),
            color: AppColors.warning,
            icon: Icons.info_outline,
          ),
      ],
    );
  }

  void _openContact(
    AppStrings strings,
    JourneyVertical vertical,
    VerticalEntry entry,
  ) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ContactRequestScreen(
          type: vertical.contactTargetType ?? vertical.code,
          slug: entry.slug,
        ),
      ),
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
