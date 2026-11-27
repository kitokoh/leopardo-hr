import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_surface/src/features/journeys/domain/payment_polling.dart';

/// Tests du planificateur de polling de paiement — #9958.
///
/// Ces tests portent sur une fonction PURE : aucune horloge, aucun widget,
/// aucun réseau. C'est précisément pourquoi la logique a été extraite de
/// l'écran — un `Timer.periodic` dans un `State` ne se teste qu'en attendant
/// réellement, ce qui rend la suite lente et instable.
void main() {
  group('isTerminalPaymentStatus', () {
    test('reconnaît les statuts terminaux', () {
      for (final status in [
        'paid',
        'succeeded',
        'captured',
        'refunded',
        'cancelled',
        'canceled',
        'expired',
        'failed',
      ]) {
        expect(
          isTerminalPaymentStatus(status),
          isTrue,
          reason: '$status doit être terminal',
        );
      }
    });

    test('pending et unpaid ne sont PAS terminaux', () {
      // Ce sont exactement les états qu'on attend de voir évoluer : les
      // traiter comme terminaux arrêterait le suivi avant le résultat.
      expect(isTerminalPaymentStatus('pending'), isFalse);
      expect(isTerminalPaymentStatus('unpaid'), isFalse);
    });

    test('null et chaîne vide ne sont pas terminaux', () {
      expect(isTerminalPaymentStatus(null), isFalse);
      expect(isTerminalPaymentStatus(''), isFalse);
    });

    test('la casse et les espaces sont tolérés', () {
      // Le serveur rend `paid` en minuscules, mais une passerelle peut rendre
      // `PAID` : refuser la variante arrêterait le suivi sur un paiement réglé.
      expect(isTerminalPaymentStatus('PAID'), isTrue);
      expect(isTerminalPaymentStatus('  Paid  '), isTrue);
    });

    test('un statut inconnu n\'est pas terminal', () {
      // Fail-open volontaire : mieux vaut interroger une fois de plus que
      // s'arrêter sur un statut qu'on ne comprend pas.
      expect(isTerminalPaymentStatus('awaiting_operator'), isFalse);
    });
  });

  group('PaymentPollPlan.decide', () {
    final plan = PaymentPollPlan(
      interval: const Duration(seconds: 5),
      maxAttempts: 3,
    );

    test('interroge tant que le statut n\'est pas terminal', () {
      expect(
        plan.decide(attempt: 1, status: 'pending', hasError: false),
        PaymentPollDecision.poll,
      );
    });

    test('s\'arrête sur statut terminal, même au premier tour', () {
      expect(
        plan.decide(attempt: 1, status: 'paid', hasError: false),
        PaymentPollDecision.stopTerminal,
      );
    });

    test('s\'arrête quand le budget est épuisé', () {
      expect(
        plan.decide(attempt: 3, status: 'pending', hasError: false),
        PaymentPollDecision.stopExhausted,
      );
    });

    test('s\'arrête sur erreur', () {
      expect(
        plan.decide(attempt: 1, status: null, hasError: true),
        PaymentPollDecision.stopError,
      );
    });

    test('un statut terminal gagne sur une erreur', () {
      // Si le serveur a rendu `paid` ET qu'une erreur est survenue, on préfère
      // annoncer le paiement que l'échec : c'est l'information utile.
      expect(
        plan.decide(attempt: 1, status: 'paid', hasError: true),
        PaymentPollDecision.stopTerminal,
      );
    });

    test('un statut terminal gagne sur un budget épuisé', () {
      expect(
        plan.decide(attempt: 99, status: 'paid', hasError: false),
        PaymentPollDecision.stopTerminal,
      );
    });

    test('une erreur gagne sur un budget épuisé', () {
      expect(
        plan.decide(attempt: 99, status: null, hasError: true),
        PaymentPollDecision.stopError,
      );
    });

    test('la fenêtre totale est le produit intervalle × tentatives', () {
      expect(plan.totalWindow, const Duration(seconds: 15));
    });

    test('le plan par défaut couvre une minute', () {
      // 12 × 5 s : assez pour un callback de mobile money, assez court pour ne
      // pas laisser l'utilisateur devant un écran qui tourne indéfiniment.
      final defaults = PaymentPollPlan();
      expect(defaults.totalWindow, const Duration(seconds: 60));
    });
  });
}
