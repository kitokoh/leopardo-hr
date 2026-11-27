/// Clés de test stables du parcours commande restaurant.
///
/// ## Le défaut que ce fichier ferme
///
/// Les flows Maestro (`maestro/restaurant/`) et les tests widget doivent
/// désigner les mêmes éléments. Sans clé explicite, un flow ne peut viser qu'un
/// **texte** — or le texte est traduit (4 locales), et il change dès qu'un
/// libellé produit est retouché. Un flow qui casse sur une virgule n'est pas un
/// test de régression, c'est un test de copie.
///
/// Ces clés sont donc :
/// - **stables** : elles ne dépendent ni de la locale ni du contenu serveur ;
/// - **publiques** : `const`, donc utilisables depuis `test/` et depuis les
///   flows Maestro (`id:` en YAML) sans dupliquer la chaîne ;
/// - **uniques** : une clé par élément interactif du parcours, jamais deux
///   widgets qui la partagent (Maestro échoue sur une ambiguïté).
///
/// ## Convention
///
/// `restaurant-<zone>-<élément>`, en kebab-case, préfixé par la verticale. Le
/// préfixe évite qu'une clé `submit` du restaurant entre en collision avec une
/// clé `submit` d'une autre verticale quand les flows tournent dans la même app.
library;

/// Clés du parcours commande restaurant.
abstract final class RestaurantTestKeys {
  /// Champ de recherche dans la carte.
  static const String menuSearch = 'restaurant-menu-search';

  /// Puce de catégorie « Tout ».
  static const String categoryAll = 'restaurant-category-all';

  /// Bouton « Voir le panier » (n'apparaît qu'avec au moins un article).
  static const String goToCart = 'restaurant-go-to-cart';

  /// Champ nom du client (obligatoire au contrat).
  static const String customerName = 'restaurant-customer-name';

  /// Champ téléphone du client (obligatoire au contrat).
  static const String customerPhone = 'restaurant-customer-phone';

  /// Bouton de validation de la commande.
  static const String submitOrder = 'restaurant-submit-order';

  /// Bandeau de confirmation (commande créée).
  static const String confirmation = 'restaurant-confirmation';

  /// Bouton de paiement.
  static const String payAction = 'restaurant-pay-action';

  /// Bouton de suivi manuel.
  static const String track = 'restaurant-track';

  /// Bloc de statut du suivi.
  static const String trackingStatus = 'restaurant-tracking-status';

  /// Bouton d'ajout d'un produit, dérivé de son code catalogue.
  ///
  /// Le code produit vient du serveur : il est stable pour un établissement
  /// donné, contrairement au nom affiché.
  static String productAdd(String productCode) =>
      'restaurant-product-add-$productCode';

  /// Bouton de retrait d'un produit, dérivé de son code catalogue.
  static String productRemove(String productCode) =>
      'restaurant-product-remove-$productCode';

  /// Puce de catégorie, dérivée du nom de catégorie serveur.
  static String category(String name) => 'restaurant-category-$name';
}
