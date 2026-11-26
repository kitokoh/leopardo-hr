/// Planificateur de polling du statut de paiement — #9958.
///
/// ## Pourquoi une classe et pas un `Timer.periodic` dans l'écran
///
/// Un `Timer.periodic` posé dans un `State` a trois défauts connus :
///
/// 1. il continue après la sortie de l'écran si `dispose()` l'oublie ;
/// 2. il ne s'arrête jamais sur un statut terminal — on interroge une
///    réservation payée jusqu'à la fin des temps ;
/// 3. il n'a pas de budget : sur une panne réseau persistante, il martèle
///    l'API indéfiniment.
///
/// Cette classe rend les trois mesurables et testables sans widget ni horloge
/// réelle : [shouldContinue] est une fonction pure, et [PaymentPollPlan] décrit
/// la décision au lieu de l'exécuter.
library;

/// Statuts de paiement considérés comme **terminaux**.
///
/// Une fois l'un d'eux atteint, plus rien ne changera sans une action de
/// l'utilisateur (nouveau paiement, annulation) : continuer à interroger
/// consommerait du réseau et de la batterie pour un résultat figé.
///
/// `pending` et `unpaid` n'y figurent pas : ce sont précisément les états
/// qu'on attend de voir évoluer.
const Set<String> terminalPaymentStatuses = {
  'paid',
  'succeeded',
  'captured',
  'refunded',
  'cancelled',
  'canceled',
  'expired',
  'failed',
};

/// Vrai si le statut est terminal (plus de polling utile).
bool isTerminalPaymentStatus(String? status) {
  if (status == null) return false;
  return terminalPaymentStatuses.contains(status.trim().toLowerCase());
}

/// Décision de polling pour un tour donné.
enum PaymentPollDecision {
  /// Interroger le serveur.
  poll,

  /// Statut terminal atteint : arrêter, c'est fini.
  stopTerminal,

  /// Budget de tentatives épuisé : arrêter et le dire à l'utilisateur.
  stopExhausted,

  /// Une erreur est survenue : arrêter (l'utilisateur peut relancer).
  stopError,
}

/// Plan de polling : combien de tours, à quel rythme, et quand s'arrêter.
///
/// ## Le budget est explicite, pas implicite
///
/// `maxAttempts` × `interval` borne la durée totale d'attente. Avec les valeurs
/// par défaut (12 × 5 s) on couvre une minute — assez pour un callback de
/// mobile money, assez court pour ne pas laisser l'utilisateur devant un écran
/// qui tourne. Au-delà, l'écran propose un rafraîchissement manuel plutôt que
/// de continuer en silence.
class PaymentPollPlan {
  /// Les bornes sont vérifiées à la construction.
  ///
  /// Le constructeur n'est volontairement **pas** `const` : comparer deux
  /// `Duration` dans un `assert` n'est pas une expression constante valide en
  /// Dart. Un plan est de toute façon construit une fois par écran, jamais
  /// dans une boucle.
  PaymentPollPlan({
    this.interval = const Duration(seconds: 5),
    this.maxAttempts = 12,
  })  : assert(maxAttempts > 0, 'maxAttempts doit être > 0'),
        assert(interval > Duration.zero, 'interval doit être > 0');

  /// Délai entre deux interrogations.
  final Duration interval;

  /// Nombre maximal d'interrogations avant abandon.
  final int maxAttempts;

  /// Durée totale d'attente couverte par le plan.
  Duration get totalWindow => interval * maxAttempts;

  /// Décide du sort d'un tour.
  ///
  /// L'ordre des tests compte : un statut terminal gagne toujours sur un budget
  /// épuisé (on préfère dire « payé » que « abandon »), et une erreur gagne sur
  /// le budget (on préfère dire « erreur » que « abandon »).
  PaymentPollDecision decide({
    required int attempt,
    required String? status,
    required bool hasError,
  }) {
    if (isTerminalPaymentStatus(status)) return PaymentPollDecision.stopTerminal;
    if (hasError) return PaymentPollDecision.stopError;
    if (attempt >= maxAttempts) return PaymentPollDecision.stopExhausted;
    return PaymentPollDecision.poll;
  }
}
