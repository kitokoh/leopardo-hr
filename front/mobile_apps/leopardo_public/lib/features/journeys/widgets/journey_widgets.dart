import 'dart:math';

import 'package:material_ui/material_ui.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';
import '../../discovery/data/public_discovery_repository.dart';
import '../domain/journey_models.dart';
import '../domain/journey_registry.dart';

/// Coque commune d'un écran de parcours.
///
/// Elle porte la `Directionality` (l'arabe est RTL) et le bouton retour : sans
/// ce point unique, l'écran arabe le plus récent serait celui qui oublierait
/// l'un des deux.
class JourneyScaffold extends StatelessWidget {
  const JourneyScaffold({
    super.key,
    required this.strings,
    required this.title,
    required this.child,
    this.bottomBar,
  });

  final AppStrings strings;
  final String title;
  final Widget child;
  final Widget? bottomBar;

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: strings.isRtl ? TextDirection.rtl : TextDirection.ltr,
      child: Scaffold(
        appBar: AppBar(title: Text(title)),
        body: SafeArea(child: child),
        bottomNavigationBar: bottomBar,
      ),
    );
  }
}

/// Champ de formulaire étiqueté.
class JourneyField extends StatelessWidget {
  const JourneyField({
    super.key,
    required this.controller,
    required this.label,
    this.hint,
    this.keyboardType,
    this.maxLines = 1,
    this.validator,
    this.enabled = true,
  });

  final TextEditingController controller;
  final String label;
  final String? hint;
  final TextInputType? keyboardType;
  final int maxLines;
  final String? Function(String?)? validator;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: controller,
        enabled: enabled,
        keyboardType: keyboardType,
        maxLines: maxLines,
        validator: validator,
        decoration: InputDecoration(
          labelText: label,
          hintText: hint,
          border: const OutlineInputBorder(),
          isDense: true,
        ),
      ),
    );
  }
}

/// Titre de section, taille et couleur identiques partout.
class JourneySectionTitle extends StatelessWidget {
  const JourneySectionTitle({super.key, required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 8, bottom: 8),
      child: Text(
        text,
        style: AppTypography.title.copyWith(
          fontSize: 15,
          color: AppColors.textPrimaryFor(context),
        ),
      ),
    );
  }
}

/// Encadré d'information (ou d'avertissement).
class JourneyNotice extends StatelessWidget {
  const JourneyNotice({
    super.key,
    required this.text,
    this.color,
    this.icon = Icons.info_outline,
  });

  final String text;
  final Color? color;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final tint = color ?? AppColors.info;
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.tint(context, tint, lightAlpha: 0.12),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 16, color: tint),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: AppTypography.caption.copyWith(
                color: AppColors.textSecondaryFor(context),
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Erreur d'un appel public, classée.
///
/// Un 404 n'affiche jamais « réessayez » (le commerce a fermé, réessayer ne
/// changera rien) et une erreur réseau n'affiche jamais « introuvable ».
class JourneyErrorView extends StatelessWidget {
  const JourneyErrorView({
    super.key,
    required this.strings,
    required this.error,
    this.onRetry,
  });

  final AppStrings strings;
  final Object error;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    // Validation locale (date mal formée, contact manquant) : ce n'est ni une
    // panne ni un refus serveur, et le message dit quoi corriger.
    if (error is JourneyLocalFailure) {
      final local = error as JourneyLocalFailure;
      return JourneyMessageView(
        icon: Icons.edit_note,
        title: strings.t('journeyCheckFields'),
        body: local.message,
      );
    }

    final failure =
        error is PublicApiFailure ? error as PublicApiFailure : null;

    if (failure?.kind == PublicFailureKind.notFound) {
      return JourneyMessageView(
        icon: Icons.search_off,
        title: strings.t('stateNotFoundTitle'),
        body: strings.t('stateNotFoundBody'),
      );
    }

    if (failure?.kind == PublicFailureKind.network ||
        failure?.kind == PublicFailureKind.timeout) {
      return JourneyMessageView(
        icon: Icons.wifi_off,
        title: strings.t('stateOfflineTitle'),
        body: strings.t('stateOfflineBody'),
        action: onRetry == null
            ? null
            : _RetryButton(strings: strings, onRetry: onRetry!),
      );
    }

    // 4xx métier : le message du serveur est la seule information utile
    // (« quantité supérieure au stock », « dates indisponibles »).
    final detail = failure?.kind == PublicFailureKind.invalidRequest
        ? failure?.message
        : null;

    return JourneyMessageView(
      icon: Icons.error_outline,
      title: strings.t('stateErrorTitle'),
      body: detail != null && detail.isNotEmpty
          ? detail
          : strings.t('stateErrorBody'),
      action: onRetry == null
          ? null
          : _RetryButton(strings: strings, onRetry: onRetry!),
    );
  }
}

/// Message local, produit par la validation de l'écran.
///
/// Distinct d'un `PublicApiFailure` : le message vient de l'app et n'a pas de
/// code HTTP, il ne doit donc jamais s'afficher comme « erreur de connexion ».
class JourneyLocalFailure implements Exception {
  const JourneyLocalFailure(this.message);

  final String message;

  @override
  String toString() => message;
}

class _RetryButton extends StatelessWidget {
  const _RetryButton({required this.strings, required this.onRetry});

  final AppStrings strings;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: onRetry,
      icon: const Icon(Icons.refresh, size: 18),
      label: Text(strings.t('stateRetry')),
    );
  }
}

/// Message centré avec icône (état vide, information, erreur).
class JourneyMessageView extends StatelessWidget {
  const JourneyMessageView({
    super.key,
    required this.icon,
    required this.title,
    required this.body,
    this.action,
  });

  final IconData icon;
  final String title;
  final String body;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 42, color: AppColors.textSecondaryFor(context)),
            const SizedBox(height: 12),
            Text(
              title,
              textAlign: TextAlign.center,
              style: AppTypography.title.copyWith(
                color: AppColors.textPrimaryFor(context),
              ),
            ),
            const SizedBox(height: 8),
            Text(
              body,
              textAlign: TextAlign.center,
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondaryFor(context),
              ),
            ),
            if (action != null) ...[const SizedBox(height: 16), action!],
          ],
        ),
      ),
    );
  }
}

/// Verticale sans contrat public : on dit POURQUOI, capacité par capacité.
///
/// C'est la différence entre « bientôt disponible » (vague, personne ne sait
/// quoi faire) et « la commande restaurant exige un jeton boutique émis par
/// l'établissement » (une information actionnable, et un rappel pour la
/// prochaine tranche serveur).
class JourneyBlockedView extends StatelessWidget {
  const JourneyBlockedView({
    super.key,
    required this.strings,
    required this.vertical,
  });

  final AppStrings strings;
  final JourneyVertical vertical;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        Icon(
          Icons.lock_outline,
          size: 42,
          color: AppColors.textSecondaryFor(context),
        ),
        const SizedBox(height: 12),
        Text(
          strings.t('journeyBlockedTitle'),
          textAlign: TextAlign.center,
          style: AppTypography.title.copyWith(
            color: AppColors.textPrimaryFor(context),
          ),
        ),
        const SizedBox(height: 8),
        Text(
          strings.t('journeyBlockedBody'),
          textAlign: TextAlign.center,
          style: AppTypography.bodySmall.copyWith(
            color: AppColors.textSecondaryFor(context),
          ),
        ),
        const SizedBox(height: 20),
        for (final blocker in vertical.blockers)
          JourneyNotice(
            text: strings.t(blocker.reasonKey),
            color: AppColors.warning,
            icon: Icons.construction_outlined,
          ),
      ],
    );
  }
}

/// Suivi public : référence + code, puis statut.
///
/// Le code n'est présenté qu'UNE fois à la création (le serveur n'en garde
/// qu'une empreinte). L'écran de confirmation le pré-remplit quand il l'a en
/// main ; ce panneau reste utilisable plus tard, quand la personne l'a noté.
class JourneyTrackingPanel extends StatefulWidget {
  const JourneyTrackingPanel({
    super.key,
    required this.strings,
    required this.onTrack,
    this.initialReference,
    this.initialCode,
    this.codeLabel,
  });

  final AppStrings strings;
  final Future<JourneyStatusReport> Function(String reference, String code)
      onTrack;
  final String? initialReference;
  final String? initialCode;
  final String? codeLabel;

  @override
  State<JourneyTrackingPanel> createState() => _JourneyTrackingPanelState();
}

class _JourneyTrackingPanelState extends State<JourneyTrackingPanel> {
  late final TextEditingController _reference = TextEditingController(
    text: widget.initialReference ?? '',
  );
  late final TextEditingController _code = TextEditingController(
    text: widget.initialCode ?? '',
  );

  bool _loading = false;
  Object? _error;
  JourneyStatusReport? _report;

  @override
  void dispose() {
    _reference.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _track() async {
    if (_reference.text.trim().isEmpty || _code.text.trim().isEmpty) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final report = await widget.onTrack(
        _reference.text.trim(),
        _code.text.trim(),
      );
      if (!mounted) return;
      setState(() {
        _report = report;
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
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        JourneySectionTitle(text: widget.strings.t('journeyTrackTitle')),
        JourneyField(
          controller: _reference,
          label: widget.strings.t('journeyConfirmationReference'),
        ),
        JourneyField(
          controller: _code,
          label: widget.codeLabel ?? widget.strings.t('journeyTrackCode'),
        ),
        OutlinedButton.icon(
          onPressed: _loading ? null : _track,
          icon: const Icon(Icons.travel_explore_outlined, size: 18),
          label: Text(widget.strings.t('journeyTrack')),
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
              strings: widget.strings,
              error: _error!,
              onRetry: _track,
            ),
          ),
        if (_report != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: JourneyStatusView(strings: widget.strings, report: _report!),
          ),
      ],
    );
  }
}

/// Affichage d'un statut de suivi.
class JourneyStatusView extends StatelessWidget {
  const JourneyStatusView({
    super.key,
    required this.strings,
    required this.report,
  });

  final AppStrings strings;
  final JourneyStatusReport report;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.tint(context, AppColors.success, lightAlpha: 0.10),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${strings.t('journeyTrackStatus')} : ${report.status}',
            style: AppTypography.title.copyWith(fontSize: 14),
          ),
          const SizedBox(height: 6),
          for (final entry in report.fields.entries)
            Text(
              '${entry.key} : ${entry.value}',
              style: AppTypography.caption.copyWith(
                color: AppColors.textSecondaryFor(context),
              ),
            ),
          for (final event in report.events)
            Text(
              event,
              style: AppTypography.caption.copyWith(
                color: AppColors.textSecondaryFor(context),
              ),
            ),
        ],
      ),
    );
  }
}

/// Clé d'idempotence générée localement.
///
/// `Random` suffit : la clé n'est pas un secret, elle identifie UNE tentative
/// pour que le rejeu réseau rende la même commande au lieu d'en créer une
/// seconde. 35 caractères tiennent dans les limites du contrat (64/80).
String newJourneyIdempotencyKey() {
  final stamp = DateTime.now().toUtc().microsecondsSinceEpoch;
  final salt = Random().nextInt(1 << 32);
  return 'pub-$stamp-$salt';
}
