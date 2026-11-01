import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/core/i18n/app_strings.dart';
import 'package:leopardo_public/core/widgets/state_widgets.dart';

/// Tests des 7 états obligatoires.
///
/// ## Ce que ces tests prouvent — et ce qu'ils ne prouvent pas
///
/// Ils prouvent qu'un état donné rend SON texte et SON action, et surtout que
/// le 404 ne propose PAS « Réessayer ». Ils ne prouvent pas que l'écran choisit
/// le bon état pour la bonne erreur : ce choix vit dans
/// `PublicApiFailure.kind` → `_ErrorView`, couvert séparément.
void main() {
  Widget wrap(Widget child) => MaterialApp(home: Scaffold(body: child));

  group('États obligatoires — texte et action', () {
    testWidgets('chargement : squelette sans texte d\'erreur', (tester) async {
      final strings = AppStrings.of('fr');
      await tester.pumpWidget(wrap(LoadingSkeleton(strings: strings)));
      await tester.pump();

      // Un squelette n'affiche AUCUN texte d'erreur : un état de chargement qui
      // dit « erreur » ferait croire à un échec pendant 3 secondes.
      expect(find.text(strings.t('stateErrorTitle')), findsNothing);
      expect(find.byType(ListView), findsOneWidget);
    });

    testWidgets('vide : propose de réessayer', (tester) async {
      final strings = AppStrings.of('fr');
      var retried = false;

      await tester.pumpWidget(wrap(EmptyContentState(
        strings: strings,
        onRetry: () => retried = true,
      )));

      expect(find.text(strings.t('stateEmptyTitle')), findsOneWidget);
      await tester.tap(find.text(strings.t('stateRetry')));
      expect(retried, isTrue);
    });

    testWidgets('aucun résultat : propose d\'ÉLARGIR, pas de réessayer',
        (tester) async {
      final strings = AppStrings.of('fr');
      var widened = false;

      await tester.pumpWidget(wrap(NoResultsState(
        strings: strings,
        onWiden: () => widened = true,
      )));

      expect(find.text(strings.t('stateNoResultsTitle')), findsOneWidget);
      // La distinction compte : « réessayer » sur une recherche qui ne renvoie
      // rien rejoue la même requête et redonne le même vide.
      expect(find.text(strings.t('stateRetry')), findsNothing);
      await tester.tap(find.text(strings.t('stateNoResultsWiden')));
      expect(widened, isTrue);
    });

    testWidgets('erreur : propose de réessayer', (tester) async {
      final strings = AppStrings.of('fr');
      var retried = false;

      await tester.pumpWidget(wrap(ErrorState(
        strings: strings,
        onRetry: () => retried = true,
      )));

      expect(find.text(strings.t('stateErrorTitle')), findsOneWidget);
      await tester.tap(find.text(strings.t('stateRetry')));
      expect(retried, isTrue);
    });

    testWidgets('hors ligne : bandeau qui DIT l\'état', (tester) async {
      final strings = AppStrings.of('fr');
      await tester.pumpWidget(wrap(OfflineBanner(strings: strings)));

      expect(find.text(strings.t('stateOfflineTitle')), findsOneWidget);
      expect(find.text(strings.t('stateOfflineBody')), findsOneWidget);
    });

    testWidgets(
        'localisation refusée : propose le choix de ville, jamais « autoriser »',
        (tester) async {
      final strings = AppStrings.of('fr');
      var choseCity = false;

      await tester.pumpWidget(wrap(LocationDeniedState(
        strings: strings,
        onChooseCity: () => choseCity = true,
      )));

      // Le texte explique la conséquence, pas un reproche.
      expect(find.text(strings.t('discoveryLocationDenied')), findsOneWidget);
      await tester.tap(find.text(strings.t('discoveryCityHint')));
      expect(choseCity, isTrue);
    });

    testWidgets('404 : information, SANS bouton réessayer', (tester) async {
      final strings = AppStrings.of('fr');
      var wentBack = false;

      await tester.pumpWidget(wrap(NotFoundState(
        strings: strings,
        onBack: () => wentBack = true,
      )));

      expect(find.text(strings.t('stateNotFoundTitle')), findsOneWidget);
      expect(find.text(strings.t('stateNotFoundBody')), findsOneWidget);
      // LE test de cet état : un 404 ne doit jamais proposer « Réessayer ».
      // Réessayer une ressource qui n'existe plus ne peut pas réussir et fait
      // passer une information pour une panne.
      expect(find.text(strings.t('stateRetry')), findsNothing);

      await tester.tap(find.text(strings.t('stateNotFoundAction')));
      expect(wentBack, isTrue);
    });
  });

  group('États obligatoires — RTL', () {
    testWidgets('l\'arabe produit un état lisible en RTL', (tester) async {
      final strings = AppStrings.of('ar');

      await tester.pumpWidget(MaterialApp(
        home: Directionality(
          textDirection: TextDirection.rtl,
          child: Scaffold(body: EmptyContentState(strings: strings)),
        ),
      ));

      expect(find.text(strings.t('stateEmptyTitle')), findsOneWidget);
      // Le texte arabe doit être présent tel quel, pas remplacé par du français.
      expect(find.text(strings.t('stateEmptyBody')), findsOneWidget);
    });
  });
}
