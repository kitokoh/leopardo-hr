import '../domain/journey_models.dart';
import 'journey_http.dart';

/// Types réellement acceptés par `POST /public/contact-requests`.
///
/// Mesuré sur `PublicProfileLocator::types()` (qui agrège les
/// `PublicProfileResolver` des verticales) au 2026-11-24 :
/// `hospitality_property`, `restaurant`, `retail_shop`, `showcase`,
/// `trade_company`, `vtc_operator`. `pharmacy` n'y figure PAS — une demande
/// pour une pharmacie est refusée en 422, donc l'app ne doit pas la proposer.
///
/// Cette liste est verrouillée par `test/journey_registry_test.dart` : une
/// verticale du registre qui déclarerait un `contactTargetType` absent d'ici
/// ferait échouer le test, et inversement.
const Set<String> contactTargetTypes = {
  'hospitality_property',
  'restaurant',
  'retail_shop',
  'showcase',
  'trade_company',
  'vtc_operator',
};

/// Demande adressée à un professionnel (« Contacter », « Demander un devis »).
///
/// ## Pourquoi c'est LE parcours universel
///
/// `POST /public/contact-requests` est le seul contrat public qui accepte
/// plusieurs verticales sans jeton ni compte : le tenant destinataire est
/// résolu CÔTÉ SERVEUR par (`target_type`, `target_slug`), et un 404 uniforme
/// empêche d'énumérer les profils. C'est ce qui rend restaurant, trade et VTC
/// transactionnels sans réécrire leur tunnel métier.
///
/// ## Idempotence et anti-spam
///
/// La clé d'idempotence est fournie par l'appelant : un retry réseau rend la
/// même demande (201 puis 200). Le champ piège `company_website` n'est JAMAIS
/// envoyé par l'app (il n'existe pas dans le formulaire) — envoyer une valeur
/// vide serait correct mais inutile.
class ContactRequestGateway {
  ContactRequestGateway(this._api);

  final JourneyApi _api;

  static const String path = '/public/contact-requests';

  /// Longueurs du contrat serveur (`message` : 10..2000).
  static const int messageMinLength = 10;
  static const int messageMaxLength = 2000;

  static Map<String, dynamic> buildPayload({
    required String targetType,
    required String targetSlug,
    required String name,
    required String email,
    required String message,
    String? phone,
    required String idempotencyKey,
  }) {
    if (!contactTargetTypes.contains(targetType)) {
      throw ArgumentError.value(
        targetType,
        'targetType',
        'Type sans resolver public (demande refusée en 422)',
      );
    }

    final slug = targetSlug.trim();
    if (slug.isEmpty || !RegExp(r'^[A-Za-z0-9\-_]+$').hasMatch(slug)) {
      throw ArgumentError.value(
          targetSlug, 'targetSlug', 'Slug public invalide');
    }

    final senderName = name.trim();
    if (senderName.length < 2 || senderName.length > 120) {
      throw ArgumentError.value(name, 'name', 'Nom 2..120 caractères');
    }

    final senderEmail = email.trim();
    if (!senderEmail.contains('@') || senderEmail.length > 255) {
      throw ArgumentError.value(email, 'email', 'E-mail invalide');
    }

    final body = message.trim();
    if (body.length < messageMinLength || body.length > messageMaxLength) {
      throw ArgumentError.value(
        message,
        'message',
        'Message $messageMinLength..$messageMaxLength caractères',
      );
    }

    if (idempotencyKey.trim().length < 8 || idempotencyKey.length > 80) {
      throw ArgumentError.value(
        idempotencyKey,
        'idempotencyKey',
        'Clé 8..80 caractères',
      );
    }

    return {
      'target_type': targetType,
      'target_slug': slug,
      'sender_name': senderName,
      'sender_email': senderEmail,
      if (phone != null && phone.trim().isNotEmpty)
        'sender_phone': phone.trim(),
      'message': body,
      // Consentement explicite : le serveur le REFUSE s'il est absent
      // (RGPD, `consent: required|accepted`). L'écran le fait cocher, il n'est
      // jamais coché d'office.
      'consent': true,
      'idempotency_key': idempotencyKey.trim(),
    };
  }

  Future<JourneyConfirmation> send({
    required String targetType,
    required String targetSlug,
    required String name,
    required String email,
    required String message,
    String? phone,
    required String idempotencyKey,
  }) async {
    final json = await _api.post(
      path,
      buildPayload(
        targetType: targetType,
        targetSlug: targetSlug,
        name: name,
        email: email,
        message: message,
        phone: phone,
        idempotencyKey: idempotencyKey,
      ),
    );

    final data = JourneyApi.dataOf(json);
    final reference = data['reference'];
    final status = data['status'];

    // `reference` est la clé d'idempotence rendue par le serveur : elle sert
    // de preuve de dépôt, pas de piste de suivi (il n'existe pas de suivi
    // public des demandes).
    return JourneyConfirmation(
      reference: reference is String && reference.isNotEmpty
          ? reference
          : idempotencyKey,
      status: status is String ? status : null,
    );
  }
}
