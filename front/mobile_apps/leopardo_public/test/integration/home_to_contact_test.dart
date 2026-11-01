import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/core/i18n/app_strings.dart';
import 'package:leopardo_public/core/navigation/app_shell.dart';
import 'package:leopardo_public/core/providers/app_providers.dart';
import 'package:leopardo_public/features/business_profile/data/business_profile_repository.dart';
import 'package:leopardo_public/features/business_profile/models/business_profile.dart';
import 'package:leopardo_public/features/discovery/data/public_discovery_repository.dart';
import 'package:leopardo_public/features/discovery/models/discovery_category.dart';
import 'package:leopardo_public/features/discovery/models/discovery_item.dart';
import 'package:leopardo_public/features/discovery/providers/discovery_providers.dart';
import 'package:leopardo_public/features/discovery/widgets/discovery_tile.dart';

/// COULOIR 16 — M1 · Parcours d'acceptation « Accueil → Fiche → Contact ».
///
/// ## Ce que ce test prouve, et ce qu'il refuse de prétendre
///
/// Le cahier des charges fixe comme cible d'acceptation : « tests d'intégration
/// Flutter Accueil → Fiche → Contact ». En M1, le contact lui-même n'existe
/// pas : il appartient à M2, qui est derrière la porte (b) et attend L1 + le
/// contrat d'identité. Écrire ici un test qui « passe » jusqu'à un champ de
/// message serait donc un mensonge — il testerait un écran qui n'existe pas.
///
/// Ce test prouve donc la chaîne RÉELLE, de bout en bout, et s'arrête
/// exactement à la frontière du lot :
///
///  1. l'accueil charge et affiche les résultats sans aucun compte ;
///  2. la recherche filtre la liste (le geste du parcours critique) ;
///  3. taper une tuile ouvre la fiche du bon commerce ;
///  4. la fiche affiche « Contacter » parce que le serveur a déclaré la
///     capacité `contact` — et ne l'affiche PAS quand il ne l'a pas déclarée ;
///  5. le bouton est DÉSACTIVÉ en M1, avec l'explication visible : c'est la
///     frontière assumée du lot, pas un bouton mort silencieux ;
///  6. aucun écran de connexion n'est traversé, aucun champ de téléphone n'est
///     demandé — la promesse « jamais de compte avant le contact » est vérifiée
///     mécaniquement, pas seulement documentée.
///
/// Le jour où M2 branche `onContact`, le point 5 s'inverse : ce test devra
/// alors affirmer l'inverse. C'est volontaire — un test qui ne peut pas
/// s'inverser ne prouve rien sur l'état du produit.
void main() {
  /// Catalogue figé en français : les assertions portent sur des textes, pas
  /// sur des clés, pour qu'une régression de catalogue fasse échouer le test.
  final strings = AppStrings.of('fr');

  Widget app({
    required _FakeDiscoveryRepository discovery,
    required _FakeProfileRepository profile,
  }) {
    return ProviderScope(
      overrides: [
        appStringsProvider.overrideWithValue(strings),
        publicDiscoveryRepositoryProvider.overrideWithValue(discovery),
        businessProfileRepositoryProvider.overrideWithValue(profile),
      ],
      child: Consumer(
        builder: (context, ref, _) {
          final router = ref.watch(appRouterProvider);
          return MaterialApp.router(
            routerConfig: router,
            locale: const Locale('fr'),
            supportedLocales: const [Locale('fr')],
            // Sans ces délégués, `TextField` lève « No MaterialLocalizations
            // found » : le test échouerait sur un défaut du HARNAIS, pas sur
            // l'écran. `main.dart` les monte déjà — on reproduit sa
            // configuration pour que le test mesure le vrai widget.
            localizationsDelegates: const [
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
          );
        },
      ),
    );
  }

  group('Parcours d\'acceptation — Accueil → Fiche → Contact', () {
    testWidgets(
        'accueil : la recherche est visible et les résultats arrivent '
        'sans aucun compte', (tester) async {
      final discovery = _FakeDiscoveryRepository(
        items: [_nadia, _boulangerie],
      );

      await tester.pumpWidget(
        app(discovery: discovery, profile: _FakeProfileRepository()),
      );
      await tester.pumpAndSettle();

      // 1. La barre de recherche est là, sans avoir rien fait.
      expect(find.text(strings.t('discoverySearchHint')), findsOneWidget);

      // 2. Les deux fiches sont listées.
      expect(find.byType(DiscoveryTile), findsNWidgets(2));
      expect(find.text('Chez Nadia'), findsOneWidget);
      expect(find.text('Boulangerie du Port'), findsOneWidget);

      // 3. Aucun écran de connexion, aucun champ de téléphone : le parcours
      //    de consultation ne demande rien.
      expect(find.text(strings.t('authLoginTitle')), findsNothing);
      expect(find.byType(TextField), findsOneWidget,
          reason: 'Un seul champ : la recherche. Aucun champ de téléphone.');
    });

    testWidgets('recherche : taper filtre la liste, sans quitter l\'accueil',
        (tester) async {
      final discovery = _FakeDiscoveryRepository(
        items: [_nadia, _boulangerie],
      );

      await tester.pumpWidget(
        app(discovery: discovery, profile: _FakeProfileRepository()),
      );
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextField), 'nadia');
      // L'anti-rebond est de 350 ms : on le laisse expirer.
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();

      expect(find.byType(DiscoveryTile), findsOneWidget);
      expect(find.text('Chez Nadia'), findsOneWidget);
      expect(find.text('Boulangerie du Port'), findsNothing);

      // La requête est bien partie avec le terme saisi : le filtrage n'est pas
      // un tour de passe-passe côté widget.
      expect(discovery.lastQuery, 'nadia');
    });

    testWidgets(
        'fiche : taper une tuile ouvre le bon commerce, en lecture '
        'seule', (tester) async {
      final discovery = _FakeDiscoveryRepository(items: [_nadia]);
      final profile = _FakeProfileRepository();

      await tester.pumpWidget(app(discovery: discovery, profile: profile));
      await tester.pumpAndSettle();

      await tester.tap(find.byType(DiscoveryTile));
      await tester.pumpAndSettle();

      // La fiche a bien été demandée pour CE slug, et pour le bon type.
      expect(profile.lastType, 'restaurant');
      expect(profile.lastSlug, 'chez-nadia');

      // Le contenu de la fiche est affiché.
      expect(find.text('Chez Nadia'), findsWidgets);

      // La description vit plus bas dans la fiche : on la fait défiler
      // jusqu'à l'écran plutôt que d'affirmer qu'elle est visible d'emblée —
      // sur un écran de 800 px de haut, elle ne l'est pas.
      await tester.scrollUntilVisible(
        find.text('Cuisine marocaine'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Cuisine marocaine'), findsOneWidget);
    });

    testWidgets(
        'contact : le bouton vient de supportedActions() et reste '
        'désactivé en M1 — frontière assumée du lot', (tester) async {
      final discovery = _FakeDiscoveryRepository(items: [_nadia]);
      final profile = _FakeProfileRepository();

      await tester.pumpWidget(app(discovery: discovery, profile: profile));
      await tester.pumpAndSettle();

      await tester.tap(find.byType(DiscoveryTile));
      await tester.pumpAndSettle();

      // Le bouton existe parce que le SERVEUR a déclaré `contact`.
      final contact =
          find.widgetWithText(FilledButton, strings.t('businessContact'));
      final contactTonal =
          find.widgetWithText(FilledButton, strings.t('businessContact'));
      expect(
        contact.evaluate().isNotEmpty || contactTonal.evaluate().isNotEmpty,
        isTrue,
        reason: 'La capacité `contact` est déclarée : le bouton doit exister.',
      );

      // Et il est DÉSACTIVÉ : M2 n'est pas livré. Un bouton actif qui ne mène
      // nulle part serait exactement le « bouton mort » que le cahier des
      // charges interdit.
      final button = tester.widget<FilledButton>(
        find
            .ancestor(
              of: find.text(strings.t('businessContact')),
              matching: find.byType(FilledButton),
            )
            .first,
      );
      expect(
        button.onPressed,
        isNull,
        reason: 'M1 : le contact est une frontière de lot, pas une action.',
      );

      // Aucun champ de message, aucun champ de téléphone n'a été ouvert.
      expect(find.byType(TextField), findsNothing);
    });

    testWidgets(
        'contact : sans la capacité déclarée, AUCUN bouton — pas de '
        'bouton grisé inventé', (tester) async {
      final discovery = _FakeDiscoveryRepository(
        items: [_boulangerie],
        actions: const {'restaurant': <String>[]},
      );
      final profile = _FakeProfileRepository(
        profile: _boulangerieProfile,
      );

      await tester.pumpWidget(app(discovery: discovery, profile: profile));
      await tester.pumpAndSettle();

      await tester.tap(find.byType(DiscoveryTile));
      await tester.pumpAndSettle();

      expect(find.text(strings.t('businessContact')), findsNothing);
      // L'écran le DIT au lieu de laisser un vide inexplicable.
      expect(
        find.text(strings.t('businessActionsUnavailable')),
        findsOneWidget,
      );
    });

    testWidgets('404 : une fiche retirée est une information, jamais une panne',
        (tester) async {
      final discovery = _FakeDiscoveryRepository(items: [_nadia]);
      final profile = _FakeProfileRepository(
        failure: const PublicApiFailure(PublicFailureKind.notFound),
      );

      await tester.pumpWidget(app(discovery: discovery, profile: profile));
      await tester.pumpAndSettle();

      await tester.tap(find.byType(DiscoveryTile));
      await tester.pumpAndSettle();

      expect(find.text(strings.t('stateNotFoundTitle')), findsOneWidget);
      // Le 404 ne propose PAS « Réessayer » : réessayer ne ressuscitera pas
      // un commerce dépublié.
      expect(find.text(strings.t('stateRetry')), findsNothing);
    });
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// Doublures
// ─────────────────────────────────────────────────────────────────────────────

const _nadia = DiscoveryItem(
  type: 'restaurant',
  slug: 'chez-nadia',
  name: 'Chez Nadia',
  category: 'restaurant',
  city: 'Casablanca',
  description: 'Cuisine marocaine',
  ratingAvg: 4.5,
  reviewsCount: 42,
);

const _boulangerie = DiscoveryItem(
  type: 'restaurant',
  slug: 'boulangerie-du-port',
  name: 'Boulangerie du Port',
  category: 'restaurant',
  city: 'Casablanca',
);

const _boulangerieProfile = BusinessProfile(
  slug: 'boulangerie-du-port',
  name: 'Boulangerie du Port',
  type: 'restaurant',
  city: 'Casablanca',
);

/// Dépôt de découverte factice : rend les items demandés, filtre sur `q`
/// comme le ferait le serveur, et retient la dernière requête.
class _FakeDiscoveryRepository implements PublicDiscoveryRepository {
  _FakeDiscoveryRepository({
    required this.items,
    this.actions = const {
      'restaurant': <String>['contact'],
    },
  });

  final List<DiscoveryItem> items;
  final Map<String, List<String>> actions;

  String? lastQuery;

  @override
  Future<DiscoveryPage> discover({
    String? query,
    String? city,
    String? type,
    String? near,
    double? radiusKm,
    bool openNow = false,
    int page = 1,
    int perPage = 20,
  }) async {
    lastQuery = query;

    final q = (query ?? '').trim().toLowerCase();
    final filtered = q.isEmpty
        ? items
        : items
            .where((i) =>
                i.name.toLowerCase().contains(q) ||
                i.slug.toLowerCase().contains(q))
            .toList(growable: false);

    return DiscoveryPage(
      items: filtered,
      total: filtered.length,
      page: 1,
      lastPage: 1,
      actions: actions,
      actionUrls: const {},
    );
  }

  @override
  Future<DiscoveryCategories> categories(String? city) async {
    return const DiscoveryCategories(
      categories: [
        DiscoveryCategory(
          key: 'restaurant',
          label: 'Restaurants',
          count: 12,
          visible: true,
        ),
      ],
      threshold: 5,
      city: 'Casablanca',
    );
  }
}

/// Dépôt de fiche factice : rend une fiche construite depuis l'item de
/// découverte, ou l'échec demandé.
class _FakeProfileRepository implements BusinessProfileRepository {
  _FakeProfileRepository({BusinessProfile? profile, this.failure})
      : _profile = profile;

  final BusinessProfile? _profile;
  final PublicApiFailure? failure;

  String? lastType;
  String? lastSlug;

  @override
  String detailPath(String type, String slug) => '/public/restaurants/$slug';

  @override
  Future<BusinessProfile> load({
    required String type,
    required String slug,
    Map<String, dynamic>? fallbackItem,
    List<String> supportedActions = const [],
    Map<String, String?> actionUrls = const {},
    dynamic cancelToken,
  }) async {
    lastType = type;
    lastSlug = slug;

    if (failure != null) throw failure!;

    if (_profile != null) {
      return BusinessProfile(
        slug: _profile.slug,
        name: _profile.name,
        type: _profile.type,
        category: _profile.category,
        city: _profile.city,
        address: _profile.address,
        description: _profile.description,
        imageUrl: _profile.imageUrl,
        latitude: _profile.latitude,
        longitude: _profile.longitude,
        phone: _profile.phone,
        ratingAvg: _profile.ratingAvg,
        reviewsCount: _profile.reviewsCount,
        hours: _profile.hours,
        menu: _profile.menu,
        currency: _profile.currency,
        verified: _profile.verified,
        supportedActions: supportedActions,
        actionUrls: actionUrls,
      );
    }

    return BusinessProfile(
      slug: slug,
      name: (fallbackItem?['name'] as String?) ?? slug,
      type: type,
      category: fallbackItem?['category'] as String?,
      city: fallbackItem?['city'] as String?,
      description: fallbackItem?['description'] as String?,
      ratingAvg: (fallbackItem?['rating_avg'] as num?)?.toDouble(),
      reviewsCount: (fallbackItem?['reviews_count'] as num?)?.toInt(),
      supportedActions: supportedActions,
      actionUrls: actionUrls,
    );
  }
}
