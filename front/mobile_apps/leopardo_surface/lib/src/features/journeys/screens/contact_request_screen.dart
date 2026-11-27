import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';

import '../../../core/i18n/app_strings.dart';
import '../../../core/providers/app_providers.dart';
import '../domain/journey_models.dart';
import '../domain/journey_registry.dart';
import '../providers/journey_providers.dart';
import '../widgets/journey_widgets.dart';

/// Demande adressée à un professionnel — restaurant, trade, VTC, retail,
/// séjour, vitrine.
///
/// ## Pourquoi le consentement est une case, jamais une mention
///
/// `POST /public/contact-requests` exige `consent: accepted`. L'app ne peut pas
/// cocher à la place de la personne : une demande qui transmet un e-mail et un
/// téléphone à un tiers sans accord explicite n'a pas de base légale. La case
/// est donc décochée par défaut et bloque l'envoi.
///
/// ## Une verticale sans resolver public n'affiche pas ce formulaire
///
/// `journeyByType('pharmacy')` existe mais `canSendRequest` est faux : le
/// serveur refuse `target_type=pharmacy` en 422. L'écran rend alors l'écran de
/// blocage, avec le motif — pas un formulaire qui échouerait à l'envoi.
class ContactRequestScreen extends ConsumerStatefulWidget {
  const ContactRequestScreen({
    super.key,
    required this.type,
    required this.slug,
  });

  final String type;
  final String slug;

  @override
  ConsumerState<ContactRequestScreen> createState() =>
      _ContactRequestScreenState();
}

class _ContactRequestScreenState extends ConsumerState<ContactRequestScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _name = TextEditingController();
  final TextEditingController _email = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _message = TextEditingController();

  bool _consent = false;
  bool _submitting = false;
  Object? _error;
  JourneyConfirmation? _confirmation;
  String? _idempotencyKey;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    _message.dispose();
    super.dispose();
  }

  Future<void> _send(String targetType) async {
    final strings = ref.read(appStringsProvider);
    if (!(_formKey.currentState?.validate() ?? false)) return;

    if (!_consent) {
      setState(() {
        _error = JourneyLocalFailure(strings.t('journeyConsentRequired'));
      });
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      final confirmation = await ref.read(contactRequestGatewayProvider).send(
            targetType: targetType,
            targetSlug: widget.slug,
            name: _name.text,
            email: _email.text,
            phone: _phone.text,
            message: _message.text,
            // Même clé tant que le formulaire n'a pas abouti : un retry réseau
            // rend la même demande au lieu d'en envoyer une deuxième au
            // commerçant.
            idempotencyKey: _idempotencyKey ??= newJourneyIdempotencyKey(),
          );

      if (!mounted) return;
      setState(() {
        _confirmation = confirmation;
        _submitting = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _submitting = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final strings = ref.watch(appStringsProvider);
    final vertical = journeyByType(widget.type);

    if (vertical == null) {
      return JourneyScaffold(
        strings: strings,
        title: strings.t('journeyTitleContact'),
        child: JourneyMessageView(
          icon: Icons.search_off,
          title: strings.t('stateNotFoundTitle'),
          body: strings.t('stateNotFoundBody'),
        ),
      );
    }

    if (!vertical.canSendRequest) {
      return JourneyScaffold(
        strings: strings,
        title: strings.t('journeyTitleContact'),
        child: JourneyBlockedView(strings: strings, vertical: vertical),
      );
    }

    return JourneyScaffold(
      strings: strings,
      title: strings.t('journeyTitleContact'),
      child: _confirmation != null
          ? _sentView(strings, _confirmation!, vertical)
          : _formView(strings, vertical),
    );
  }

  Widget _formView(AppStrings strings, JourneyVertical vertical) {
    String? required(String? value) => (value == null || value.trim().isEmpty)
        ? strings.t('journeyFieldRequired')
        : null;

    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          JourneyNotice(
            text:
                '${strings.t(vertical.labelKey)} · ${strings.t('journeyRequestNotice')}',
          ),
          JourneyField(
            controller: _name,
            label: strings.t('journeyCustomerName'),
            validator: required,
          ),
          JourneyField(
            controller: _email,
            label: strings.t('journeyCustomerEmail'),
            keyboardType: TextInputType.emailAddress,
            validator: (value) {
              final text = value?.trim() ?? '';
              if (text.isEmpty) return strings.t('journeyFieldRequired');
              return text.contains('@')
                  ? null
                  : strings.t('journeyFieldInvalidEmail');
            },
          ),
          JourneyField(
            controller: _phone,
            label: strings.t('journeyCustomerPhone'),
            keyboardType: TextInputType.phone,
          ),
          JourneyField(
            controller: _message,
            label: strings.t('journeyMessage'),
            maxLines: 5,
            validator: (value) {
              final text = value?.trim() ?? '';
              if (text.length < 10) return strings.t('journeyMessageTooShort');
              return null;
            },
          ),
          CheckboxListTile(
            value: _consent,
            onChanged: (value) => setState(() => _consent = value ?? false),
            controlAffinity: ListTileControlAffinity.leading,
            contentPadding: EdgeInsets.zero,
            title: Text(
              strings.t('journeyConsentLabel'),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ),
          if (_error != null)
            JourneyErrorView(strings: strings, error: _error!),
          FilledButton(
            onPressed: _submitting ? null : () => _send(vertical.code),
            child: Text(
              _submitting
                  ? strings.t('stateLoading')
                  : strings.t('journeySubmitRequest'),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            strings.t('journeyPrivacyNotice'),
            style: Theme.of(
              context,
            )
                .textTheme
                .bodySmall
                ?.copyWith(color: AppColors.textSecondaryFor(context)),
          ),
        ],
      ),
    );
  }

  Widget _sentView(
    AppStrings strings,
    JourneyConfirmation confirmation,
    JourneyVertical vertical,
  ) {
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        JourneyNotice(
          text: strings.t('journeyRequestSentBody'),
          color: AppColors.success,
          icon: Icons.check_circle_outline,
        ),
        JourneySectionTitle(text: strings.t('journeyConfirmationTitle')),
        SelectableText(
          confirmation.reference,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: 12),
        Text(
          strings.t('journeyRequestSentHint'),
          style: Theme.of(
            context,
          )
              .textTheme
              .bodySmall
              ?.copyWith(color: AppColors.textSecondaryFor(context)),
        ),
      ],
    );
  }
}
