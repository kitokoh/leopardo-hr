/// Configuration d'environnement de l'app grand public.
///
/// ## Une seule source de vérité pour l'URL d'API
///
/// `leopardo_core`'s `ApiClient.resolveBaseUrl()` résout déjà `API_BASE_URL`
/// avec un comportement fail-closed en release (issues #4524 / #7963) : un
/// build release sans define LÈVE au lieu de retomber sur un backend de dev.
/// Réimplémenter cette résolution ici créerait deux sources qui divergeraient —
/// exactement le genre de duplication que la règle d'architecture n°1 interdit.
///
/// Ce fichier ne contient donc QUE ce que le core ne sait pas : l'identité
/// fonctionnelle de l'app (ville pilote) et sa politique réseau.
class AppConfig {
  const AppConfig._();

  /// Ville pilote, injectée au build (`--dart-define=PILOT_CITY=Casablanca`).
  ///
  /// Vide par défaut : l'app ne prétend pas couvrir une ville qu'elle ne
  /// connaît pas. Une ville codée en dur ici ferait afficher « Casablanca »
  /// dans un build destiné à Istanbul.
  static const String pilotCity = String.fromEnvironment('PILOT_CITY');

  /// Délai réseau par défaut — aligné sur `ApiClient._defaultTimeout` (20 s).
  ///
  /// Exposé pour que l'interface puisse afficher « cela prend plus de temps que
  /// prévu » au bon moment, sans coder 20 en dur dans un widget.
  static const Duration networkTimeout = Duration(seconds: 20);

  /// Nombre de tentatives gérées par `ApiClient` pour les GET idempotents.
  ///
  /// En LECTURE SEULE uniquement : `requestWithRetry` refuse de rejouer une
  /// mutation (POST/PATCH), car une réponse perdue après commit dupliquerait
  /// un compte ou un message. Cette app ne fait aucune mutation en M1.
  static const int readRetries = 2;
}
