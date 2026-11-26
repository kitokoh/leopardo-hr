/// Modèles de la verticale **Emploi** (careers) — portails carrière publics.
///
/// ## Le contrat, tel qu'il existe réellement
///
/// - `GET  /public/careers` — annuaire des entreprises qui publient ;
/// - `GET  /public/careers/{companySlug}` — offres du portail, en **deux
///   familles** : les offres de l'entreprise (`JobPostingResource`) et les
///   offres agrégées de partenaires (`ExternalJobOfferResource`, `origin:
///   external`) ;
/// - `GET  /public/careers/{companySlug}/jobs/{id}` — détail d'une offre ;
/// - `POST /public/careers/{companySlug}/jobs/{id}/apply` — candidature ;
/// - `POST /public/careers/{companySlug}/applications/track` — suivi par e-mail.
///
/// ## Ce que ces modèles refusent d'inventer
///
/// - Une offre externe **n'est pas** candidatable sur notre portail : elle
///   porte `apply_url` et la candidature se fait chez le partenaire. La
///   confondre avec une offre interne enverrait le candidat vers un
///   formulaire qui répondra 404.
/// - Le suivi est un **POST** : l'e-mail est une donnée personnelle, il n'a
///   rien à faire dans une URL (journaux, historique, proxy).
library;

/// Entreprise présente dans l'annuaire des portails carrière.
class CareerCompany {
  const CareerCompany({
    required this.slug,
    required this.name,
    this.country,
    this.lastPublishedAt,
  });

  final String slug;
  final String name;
  final String? country;
  final String? lastPublishedAt;

  static CareerCompany? tryParse(Object? raw) {
    if (raw is! Map) return null;
    final slug = _str(raw['slug']);
    final name = _str(raw['name']);
    if (slug == null || name == null) return null;
    return CareerCompany(
      slug: slug,
      name: name,
      country: _str(raw['country']),
      lastPublishedAt: _str(raw['last_published_at']),
    );
  }
}

/// Une offre d'emploi, interne ou agrégée.
///
/// Les deux familles partagent assez de champs pour un affichage commun, mais
/// **pas** la même action : `isExternal` décide si le bouton dit « Postuler »
/// (formulaire natif) ou « Postuler chez le partenaire » (ouverture d'URL).
class CareerJob {
  const CareerJob({
    required this.id,
    required this.title,
    this.description,
    this.location,
    this.remotePolicy,
    this.contractType,
    this.departmentName,
    this.salaryMin,
    this.salaryMax,
    this.currency,
    this.skillsRequired = const [],
    this.closesAt,
    this.companyName,
    this.applyUrl,
    this.origin = 'company',
    this.sourceLabel,
  });

  final int id;
  final String title;
  final String? description;
  final String? location;
  final String? remotePolicy;
  final String? contractType;
  final String? departmentName;
  final num? salaryMin;
  final num? salaryMax;
  final String? currency;
  final List<String> skillsRequired;
  final String? closesAt;

  /// Renseigné pour les offres agrégées (l'entreprise n'est pas la nôtre).
  final String? companyName;

  /// URL de candidature chez le partenaire — offres externes uniquement.
  final String? applyUrl;

  /// `company` (offre de l'entreprise) ou `external` (offre agrégée).
  final String origin;

  /// Libellé du fournisseur d'agrégation, quand il est exposé.
  final String? sourceLabel;

  /// Une offre externe se postule ailleurs : le formulaire natif n'a pas de
  /// sens et le serveur refuserait l'identifiant (il n'existe pas chez nous).
  bool get isExternal => origin == 'external';

  /// Une offre interne est-elle encore ouverte ?
  ///
  /// `closes_at` est une date (`Y-m-d`) : une offre qui ferme aujourd'hui est
  /// encore ouverte jusqu'à la fin de la journée.
  bool get isOpen {
    final raw = closesAt;
    if (raw == null) return true;
    final parsed = DateTime.tryParse(raw);
    if (parsed == null) return true;
    final today = DateTime.now();
    final endOfDay = DateTime(parsed.year, parsed.month, parsed.day, 23, 59, 59);
    return !endOfDay.isBefore(today);
  }

  /// Fourchette salariale lisible, ou `null` si rien n'est publié.
  String? get salaryLabel {
    final min = salaryMin;
    final max = salaryMax;
    if (min == null && max == null) return null;
    final unit = currency ?? '';
    if (min != null && max != null) return '$min – $max $unit'.trim();
    return '${min ?? max} $unit'.trim();
  }

  static CareerJob? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final id = _int(raw['id']);
    final title = _str(raw['title']);
    if (id == null || title == null) return null;

    final department = raw['department'];
    final source = raw['source'];

    final skills = <String>[];
    final rawSkills = raw['skills_required'];
    if (rawSkills is List) {
      for (final entry in rawSkills) {
        final skill = _str(entry);
        if (skill != null) skills.add(skill);
      }
    }

    return CareerJob(
      id: id,
      title: title,
      description: _str(raw['description']),
      location: _str(raw['location']),
      remotePolicy: _str(raw['remote_policy']),
      contractType: _str(raw['contract_type']),
      departmentName: department is Map ? _str(department['name']) : null,
      // Les offres internes exposent `salary_range_min/max`, les offres
      // agrégées `salary_min/max` : deux noms pour la même information.
      salaryMin: _num(raw['salary_range_min']) ?? _num(raw['salary_min']),
      salaryMax: _num(raw['salary_range_max']) ?? _num(raw['salary_max']),
      currency: _str(raw['currency']),
      skillsRequired: skills,
      closesAt: _str(raw['closes_at']) ?? _str(raw['expires_at']),
      companyName: _str(raw['company_name']),
      applyUrl: _str(raw['apply_url']),
      origin: _str(raw['origin']) ?? 'company',
      sourceLabel: source is Map ? _str(source['label']) : null,
    );
  }
}

/// Portail d'une entreprise : ses offres + les offres agrégées du pays.
class CareerPortal {
  const CareerPortal({
    required this.company,
    required this.jobs,
    this.companyCount = 0,
    this.externalCount = 0,
  });

  final CareerCompany company;
  final List<CareerJob> jobs;
  final int companyCount;
  final int externalCount;

  List<CareerJob> get internalJobs =>
      [for (final job in jobs) if (!job.isExternal) job];

  List<CareerJob> get externalJobs =>
      [for (final job in jobs) if (job.isExternal) job];

  /// Construit le portail depuis la réponse de `GET /public/careers/{slug}`.
  ///
  /// `meta.company` porte le nom de l'entreprise ; `data` mélange les deux
  /// familles d'offres. Si `meta.company` manque, le portail reste utilisable
  /// avec le slug comme nom — mieux vaut un titre imparfait qu'un écran vide.
  static CareerPortal? tryParse(Object? raw, {required String fallbackSlug}) {
    if (raw is! Map) return null;

    final jobs = <CareerJob>[];
    final data = raw['data'];
    if (data is List) {
      for (final entry in data) {
        final job = CareerJob.tryParse(entry);
        if (job != null) jobs.add(job);
      }
    }

    final meta = raw['meta'];
    final companyRaw = meta is Map ? meta['company'] : null;
    final company = CareerCompany.tryParse(companyRaw) ??
        CareerCompany(slug: fallbackSlug, name: fallbackSlug);

    final counts = meta is Map ? meta['counts'] : null;

    return CareerPortal(
      company: company,
      jobs: jobs,
      companyCount: counts is Map ? _int(counts['company']) ?? 0 : 0,
      externalCount: counts is Map ? _int(counts['external']) ?? 0 : 0,
    );
  }
}

/// Candidature en cours de saisie.
///
/// Le consentement n'est **pas** un booléen par défaut à `true` : le serveur
/// exige `accepted`, et une case pré-cochée n'est pas un consentement RGPD.
class CareerApplicationDraft {
  const CareerApplicationDraft({
    required this.firstName,
    required this.lastName,
    required this.email,
    this.phone,
    this.coverLetter,
    this.resumeUrl,
    this.consent = false,
  });

  final String firstName;
  final String lastName;
  final String email;
  final String? phone;
  final String? coverLetter;
  final String? resumeUrl;
  final bool consent;

  CareerApplicationDraft copyWith({
    String? firstName,
    String? lastName,
    String? email,
    String? phone,
    String? coverLetter,
    String? resumeUrl,
    bool? consent,
  }) =>
      CareerApplicationDraft(
        firstName: firstName ?? this.firstName,
        lastName: lastName ?? this.lastName,
        email: email ?? this.email,
        phone: phone ?? this.phone,
        coverLetter: coverLetter ?? this.coverLetter,
        resumeUrl: resumeUrl ?? this.resumeUrl,
        consent: consent ?? this.consent,
      );
}

/// Candidature déposée (`ApplicantResource`).
class CareerApplicationReceipt {
  const CareerApplicationReceipt({
    required this.id,
    required this.status,
    this.fullName,
    this.jobTitle,
    this.appliedAt,
  });

  final int id;
  final String status;
  final String? fullName;
  final String? jobTitle;
  final String? appliedAt;

  static CareerApplicationReceipt? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final data = raw['data'];
    final body = data is Map ? data : raw;

    final id = _int(body['id']);
    if (id == null) return null;

    final job = body['job_posting'];

    return CareerApplicationReceipt(
      id: id,
      status: _str(body['status']) ?? 'new',
      fullName: _str(body['full_name']),
      jobTitle: job is Map ? _str(job['title']) : null,
      appliedAt: _str(body['applied_at']),
    );
  }
}

/// Une ligne du suivi de candidatures (`POST …/applications/track`).
class CareerApplicationStatus {
  const CareerApplicationStatus({
    required this.reference,
    required this.status,
    this.jobTitle,
    this.jobLocation,
    this.contractType,
    this.appliedAt,
    this.lastUpdate,
  });

  final String reference;
  final String status;
  final String? jobTitle;
  final String? jobLocation;
  final String? contractType;
  final String? appliedAt;
  final String? lastUpdate;

  static CareerApplicationStatus? tryParse(Object? raw) {
    if (raw is! Map) return null;
    final reference = _str(raw['reference']);
    if (reference == null) return null;
    return CareerApplicationStatus(
      reference: reference,
      status: _str(raw['status']) ?? 'new',
      jobTitle: _str(raw['job_title']),
      jobLocation: _str(raw['job_location']),
      contractType: _str(raw['contract_type']),
      appliedAt: _str(raw['applied_at']),
      lastUpdate: _str(raw['last_update']),
    );
  }
}

/// Résultat du suivi : les candidatures + la durée de conservation RGPD.
class CareerTrackingResult {
  const CareerTrackingResult({
    required this.applications,
    this.retentionDays,
  });

  final List<CareerApplicationStatus> applications;

  /// Durée de conservation annoncée par le serveur — obligation d'information
  /// RGPD, et réponse à « pourquoi ma candidature a disparu ».
  final int? retentionDays;

  static CareerTrackingResult tryParse(Object? raw) {
    if (raw is! Map) return const CareerTrackingResult(applications: []);

    final applications = <CareerApplicationStatus>[];
    final data = raw['data'];
    if (data is List) {
      for (final entry in data) {
        final status = CareerApplicationStatus.tryParse(entry);
        if (status != null) applications.add(status);
      }
    }

    final meta = raw['meta'];

    return CareerTrackingResult(
      applications: applications,
      retentionDays: meta is Map ? _int(meta['retention_days']) : null,
    );
  }
}

String? _str(Object? value) {
  if (value is String && value.trim().isNotEmpty) return value.trim();
  if (value is num) return value.toString();
  return null;
}

int? _int(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value);
  return null;
}

num? _num(Object? value) {
  if (value is num) return value;
  if (value is String) return num.tryParse(value);
  return null;
}
