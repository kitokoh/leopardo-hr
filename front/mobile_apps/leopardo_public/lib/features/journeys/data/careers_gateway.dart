import '../../discovery/data/public_discovery_repository.dart';
import '../domain/careers_models.dart';
import 'journey_http.dart';

/// Parcours de la verticale **Emploi** (careers) — portails carrière publics.
///
/// ## Le contrat, tel qu'il existe réellement
///
/// - `GET  /public/careers?q&page&per_page` — annuaire des entreprises ;
/// - `GET  /public/careers/{companySlug}?q&location&contract_type&remote_policy`
/// - `GET  /public/careers/{companySlug}/jobs/{jobPosting}` ;
/// - `POST /public/careers/{companySlug}/jobs/{jobPosting}/apply` ;
/// - `POST /public/careers/{companySlug}/applications/track`.
///
/// ## Deux règles que la passerelle porte, pas l'écran
///
/// 1. **Le consentement est obligatoire.** Le serveur répond 422 sans
///    `consent: accepted`. Refuser localement évite d'envoyer les données
///    personnelles d'un candidat pour rien.
/// 2. **Le suivi est un POST.** L'e-mail est une donnée personnelle : il ne
///    doit jamais apparaître dans une URL (journaux d'accès, historique,
///    proxy). Un `GET` ici serait une fuite, pas un détail de style.
class CareersGateway {
  CareersGateway(this._api);

  final JourneyApi _api;

  static const String base = '/public/careers';

  String portalPath(String companySlug) => '$base/$companySlug';

  String jobPath(String companySlug, int jobId) =>
      '$base/$companySlug/jobs/$jobId';

  /// Annuaire des entreprises qui publient au moins une offre.
  Future<List<CareerCompany>> companies({String? query, int perPage = 20}) async {
    final json = await _api.get(
      base,
      query: {
        if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
        'per_page': perPage.clamp(1, 100),
      },
    );

    final companies = <CareerCompany>[];
    for (final raw in JourneyApi.listOf(json)) {
      final company = CareerCompany.tryParse(raw);
      if (company != null) companies.add(company);
    }
    return companies;
  }

  /// Portail d'une entreprise : offres internes + offres agrégées.
  Future<CareerPortal> portal(
    String companySlug, {
    String? query,
    String? location,
    String? contractType,
    String? remotePolicy,
  }) async {
    final json = await _api.get(
      portalPath(companySlug),
      query: {
        if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
        if (location != null && location.trim().isNotEmpty)
          'location': location.trim(),
        if (contractType != null && contractType.trim().isNotEmpty)
          'contract_type': contractType.trim(),
        if (remotePolicy != null && remotePolicy.trim().isNotEmpty)
          'remote_policy': remotePolicy.trim(),
      },
    );

    final portal = CareerPortal.tryParse(json, fallbackSlug: companySlug);
    if (portal == null) {
      throw const PublicApiFailure(
        PublicFailureKind.server,
        message: 'Career portal without data',
      );
    }
    return portal;
  }

  /// Détail d'une offre interne.
  Future<CareerJob> job(String companySlug, int jobId) async {
    final json = await _api.get(jobPath(companySlug, jobId));

    final job = CareerJob.tryParse(JourneyApi.dataOf(json));
    if (job == null) {
      throw const PublicApiFailure(
        PublicFailureKind.notFound,
        message: 'Job not found',
      );
    }
    return job;
  }

  /// Corps EXACT de `PublicApplyRequest`.
  ///
  /// Les champs facultatifs vides sont **omis** : envoyer `phone: ""` ferait
  /// échouer la validation `max:20` sur une chaîne vide dans certains
  /// environnements, et `resume_url: ""` échouerait sur `url`.
  static Map<String, dynamic> buildApplicationPayload(
    CareerApplicationDraft draft, {
    String? source,
  }) {
    final firstName = draft.firstName.trim();
    final lastName = draft.lastName.trim();
    final email = draft.email.trim();

    if (firstName.isEmpty) {
      throw ArgumentError.value(firstName, 'first_name', 'Prénom requis');
    }
    if (lastName.isEmpty) {
      throw ArgumentError.value(lastName, 'last_name', 'Nom requis');
    }
    if (email.isEmpty || !email.contains('@')) {
      throw ArgumentError.value(email, 'email', 'E-mail valide requis');
    }

    // RGPD : sans consentement explicite, la candidature est refusée en 422.
    // On refuse ici, avec un message, plutôt que d'envoyer des données
    // personnelles que le serveur jettera.
    if (!draft.consent) {
      throw ArgumentError(
        'Le consentement au traitement des données est requis pour candidater.',
      );
    }

    final phone = draft.phone?.trim() ?? '';
    final coverLetter = draft.coverLetter?.trim() ?? '';
    final resumeUrl = draft.resumeUrl?.trim() ?? '';

    if (coverLetter.length > 5000) {
      throw ArgumentError.value(
        coverLetter.length,
        'cover_letter',
        'Lettre de motivation : 5000 caractères maximum',
      );
    }

    return {
      'first_name': firstName,
      'last_name': lastName,
      'email': email,
      if (phone.isNotEmpty) 'phone': phone,
      if (coverLetter.isNotEmpty) 'cover_letter': coverLetter,
      if (resumeUrl.isNotEmpty) 'resume_url': resumeUrl,
      if (source != null && source.isNotEmpty) 'source': source,
      'consent': true,
    };
  }

  /// Dépose une candidature.
  ///
  /// Un 409 (`ALREADY_APPLIED`) n'est pas une panne : c'est une information
  /// utile, et l'écran doit la dire telle quelle.
  Future<CareerApplicationReceipt> apply({
    required String companySlug,
    required int jobId,
    required CareerApplicationDraft draft,
    String? source,
  }) async {
    final json = await _api.post(
      '${jobPath(companySlug, jobId)}/apply',
      buildApplicationPayload(draft, source: source),
    );

    final receipt = CareerApplicationReceipt.tryParse(json);
    if (receipt == null) {
      throw const PublicApiFailure(
        PublicFailureKind.server,
        message: 'Application without id',
      );
    }
    return receipt;
  }

  /// Suivi par e-mail — **POST**, jamais GET.
  Future<CareerTrackingResult> track({
    required String companySlug,
    required String email,
  }) async {
    final trimmed = email.trim();
    if (trimmed.isEmpty || !trimmed.contains('@')) {
      throw ArgumentError.value(trimmed, 'email', 'E-mail valide requis');
    }

    final json = await _api.post(
      '$base/$companySlug/applications/track',
      {'email': trimmed},
    );

    return CareerTrackingResult.tryParse(json);
  }
}
