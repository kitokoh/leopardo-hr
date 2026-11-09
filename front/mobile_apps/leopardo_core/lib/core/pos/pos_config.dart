/// Configuration runtime des caisses tablette (#9263).
///
/// Une tablette livree sur site ne doit pas exiger de recompilation pour
/// changer l'URL de l'API ou l'emplacement servi : le gerant ouvre un ecran
/// de reglages, saisit les valeurs, et la caisse les utilise des la session
/// suivante. Tout ce qui est **decision** vit ici, dans le noyau partage :
/// normalisation de l'URL, validation, et construction de l'URL de sante.
///
/// Pur (aucune dependance Flutter) et testable.
library;

/// Configuration d'une caisse tablette.
///
/// Les deux apps partagent le meme modele ; seule la cle d'emplacement
/// differe (`locationId` pour le retail, `branchId` pour le restaurant).
/// Les deux champs sont portes pour garder un seul modele.
class PosAppConfig {
  const PosAppConfig({
    required this.apiBaseUrl,
    this.locationId,
    this.branchId,
  });

  /// URL de base de l'API, **normalisee** (voir [normalizeBaseUrl]).
  final String apiBaseUrl;

  /// Emplacement servi par la tablette (POS Retail).
  final int? locationId;

  /// Succursale servie par la tablette (POS Restaurant).
  final int? branchId;

  /// L'ID d'emplacement pertinent pour cette caisse.
  int? get locationOrBranchId => locationId ?? branchId;

  /// L'URL de base est-elle exploitable ?
  bool get hasApiBaseUrl => apiBaseUrl.trim().isNotEmpty;

  /// Un emplacement est requis pour ouvrir une session.
  bool get hasLocation => locationId != null || branchId != null;

  /// Normalise une saisie libre en URL de base exploitable.
  ///
  /// Regles : trim, remplit `https://` si le scheme manque, retire le slash
  /// final (le client concatene ses chemins avec `/`). Une adresse vide ou
  /// illisible renvoie la valeur telle quelle : la validation la refusera.
  static String normalizeBaseUrl(String input) {
    var value = input.trim();

    if (value.isEmpty) {
      return value;
    }

    if (!value.contains('://')) {
      value = 'https://$value';
    }

    // Retire les espaces parasites et le slash final (hors racine).
    value = value.replaceAll(RegExp(r'\s+'), '');

    if (value.endsWith('/')) {
      value = value.substring(0, value.length - 1);
    }

    return value;
  }

  /// L'URL normalisee est-elle valide (scheme http(s) + hote) ?
  bool get isValid => _parsedUri != null;

  Uri? get _parsedUri {
    final candidate = normalizeBaseUrl(apiBaseUrl);
    final uri = Uri.tryParse(candidate);

    if (uri == null || uri.host.isEmpty) {
      return null;
    }

    if (uri.scheme != 'http' && uri.scheme != 'https') {
      return null;
    }

    // Un hote doit ressembler a un nom de domaine, une IP ou localhost :
    // `https://notaurl` est syntaxiquement une URI valide mais n'est pas une
    // adresse de serveur, et laisser passer cette saisie enverrait la caisse
    // vers un hote inexistant sans explication.
    final host = uri.host.toLowerCase();
    final looksLikeHost = host == 'localhost' ||
        host.contains('.') ||
        RegExp(r'^\d{1,3}(\.\d{1,3}){3}$').hasMatch(host);

    if (!looksLikeHost) {
      return null;
    }

    return uri;
  }

  /// URL de sante, utilisee par l'ecran de reglages pour tester la
  /// connexion. Le backend expose `/api/v1/health` sans authentification
  /// (route canonique du pipeline : `Route::get('/health', ...)`).
  String? get healthUrl {
    final base = _parsedUri?.toString();

    if (base == null || base.isEmpty) {
      return null;
    }

    return '$base/health';
  }

  /// Copie avec un nouvel emplacement.
  PosAppConfig withLocation({int? locationId, int? branchId}) => PosAppConfig(
        apiBaseUrl: apiBaseUrl,
        locationId: locationId ?? this.locationId,
        branchId: branchId ?? this.branchId,
      );

  Map<String, dynamic> toJson() => {
        'api_base_url': apiBaseUrl,
        'location_id': locationId,
        'branch_id': branchId,
      };

  factory PosAppConfig.fromJson(Map<String, dynamic> json) => PosAppConfig(
        apiBaseUrl: normalizeBaseUrl((json['api_base_url'] as String?) ?? ''),
        locationId: (json['location_id'] as num?)?.toInt(),
        branchId: (json['branch_id'] as num?)?.toInt(),
      );

  @override
  bool operator ==(Object other) =>
      other is PosAppConfig &&
      other.apiBaseUrl == apiBaseUrl &&
      other.locationId == locationId &&
      other.branchId == branchId;

  @override
  int get hashCode => Object.hash(apiBaseUrl, locationId, branchId);
}

/// Resultat d'un test de connexion au backend.
///
/// Un echec de configuration se traduit par un message **actionnable** :
/// on distingue une URL mal formee, un serveur injoignable (DNS/reseau) et
/// une reponse inattendue du serveur — le vendeur ne doit pas deviner.
enum PosHealthStatus {
  ok('Connexion reussie'),
  invalidUrl(
      'Adresse invalide — verifiez le format (ex. https://api.exemple.com)'),
  unreachable('Serveur injoignable — verifiez le reseau et l\u2019adresse'),
  timeout('Le serveur ne repond pas (delai depasse)'),
  serverError('Le serveur repond mais signale une erreur');

  const PosHealthStatus(this.label);

  final String label;

  bool get isOk => this == PosHealthStatus.ok;
}

/// Resultat d'un test de connexion.
class PosHealthResult {
  const PosHealthResult({required this.status, this.httpStatus});

  final PosHealthStatus status;
  final int? httpStatus;

  bool get isOk => status.isOk;
}

/// Verifie la connexion au backend.
///
/// Utilise la route publique `/health` : une reponse 2xx valide l'adresse ;
/// une 401/403 n'est pas une erreur de configuration (le endpoint existe,
/// l'auth releve du reste de l'app). Toute autre reponse ou un echec de
/// transport est classe avec le message le plus utile.
///
/// [performRequest] est injectable pour tester la logique sans reseau.
Future<PosHealthResult> checkPosBackendHealth({
  required String apiBaseUrl,
  required Future<int> Function(Uri healthUri) performRequest,
}) async {
  final normalized = PosAppConfig(apiBaseUrl: apiBaseUrl);
  final uri = normalized.healthUrl;

  if (!normalized.isValid || uri == null) {
    return const PosHealthResult(status: PosHealthStatus.invalidUrl);
  }

  final healthUri = Uri.parse(PosAppConfig.normalizeBaseUrl(uri));

  try {
    final statusCode = await performRequest(healthUri);

    if (statusCode >= 200 && statusCode < 400) {
      return PosHealthResult(
          status: PosHealthStatus.ok, httpStatus: statusCode);
    }

    if (statusCode == 401 || statusCode == 403) {
      // Le endpoint existe mais exige une authentification : l'adresse est
      // bonne, le reste de la configuration se fera au premier appel.
      return PosHealthResult(
          status: PosHealthStatus.ok, httpStatus: statusCode);
    }

    return PosHealthResult(
        status: PosHealthStatus.serverError, httpStatus: statusCode);
  } on PosHealthTimeout {
    return const PosHealthResult(status: PosHealthStatus.timeout);
  } catch (_) {
    // Echec de transport : DNS, connexion refusee, TLS...
    return const PosHealthResult(status: PosHealthStatus.unreachable);
  }
}

/// Levee quand le delai du test de connexion est depasse.
class PosHealthTimeout implements Exception {
  const PosHealthTimeout();
}

/// PIN de protection de l'ecran de reglages.
///
/// Le PIN ne quitte jamais la tablette : seule une empreinte (hash non
/// reversible) est persistee. Le hash est volontairement local et iteratif
/// simple — il protege contre un regard indiscret, pas contre un expert en
/// forensique, et la caisse n'a pas de compte distant a proteger.
///
/// Regles : 4 a 8 chiffres. Le PIN par defaut de premiere mise en service
/// est `0000` : l'ecran de reglages invite a le changer des la premiere
/// ouverture (voir [isDefault]).
class PosPinCode {
  const PosPinCode._();

  static const int minLength = 4;
  static const int maxLength = 8;
  static const String defaultPin = '0000';

  /// Un PIN est-il syntaxiquement valide (4-8 chiffres) ?
  static bool isValid(String pin) {
    if (pin.length < minLength || pin.length > maxLength) {
      return false;
    }

    return RegExp(r'^\d+$').hasMatch(pin);
  }

  /// Empreinte persistable d'un PIN.
  ///
  /// FNV-1a 64 bits sur `pin:salt`, en hexadecimal. Deterministe : la meme
  /// valeur donne la meme empreinte, ce qui permet la verification locale.
  static String hash(String pin, {required String salt}) {
    var hash = 0xcbf29ce484222325;

    for (final codeUnit in 'pin:$salt:$pin'.codeUnits) {
      hash ^= codeUnit;
      hash *= 0x100000001b3;
      hash &= 0xFFFFFFFFFFFFFFFF;
    }

    return hash.toRadixString(16).padLeft(16, '0');
  }

  /// Compare une saisie a une empreinte stockee.
  static bool matches(String pin,
          {required String storedHash, required String salt}) =>
      storedHash == hash(pin, salt: salt);

  /// Le PIN est-il encore celui d'usine ? (invite a le changer)
  static bool isDefault(String pin) => pin == defaultPin;
}
