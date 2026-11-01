import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/features/business_profile/models/business_profile.dart';
import 'package:leopardo_public/features/discovery/models/discovery_category.dart';
import 'package:leopardo_public/features/discovery/models/discovery_item.dart';

/// Tests des modèles de découverte et de fiche.
///
/// ## Ce que ces tests protègent réellement
///
/// La découverte agrège plusieurs verticales dont les payloads diffèrent. Une
/// réponse d'une nouvelle verticale ne doit jamais faire planter l'écran
/// entier — c'est pourquoi le parsing est défensif. Ces tests vérifient que
/// « défensif » ne veut pas dire « devine n'importe quoi » : un item sans slug
/// est rejeté, un item avec des champs annexes manquants est conservé.
void main() {
  group('DiscoveryItem.tryParse', () {
    test('parse un item complet', () {
      final item = DiscoveryItem.tryParse({
        'type': 'restaurant',
        'slug': 'chez-nadia',
        'name': 'Chez Nadia',
        'category': 'restaurant',
        'city': 'Casablanca',
        'description': 'Cuisine marocaine',
        'image_url': 'https://cdn.example/img.jpg',
        'latitude': 33.5731,
        'longitude': -7.5898,
        'distance_km': 1.234,
        'rating_avg': 4.5,
        'reviews_count': 42,
      });

      expect(item, isNotNull);
      expect(item!.slug, 'chez-nadia');
      expect(item.name, 'Chez Nadia');
      expect(item.city, 'Casablanca');
      expect(item.distanceKm, closeTo(1.234, 0.0001));
      expect(item.ratingAvg, 4.5);
      expect(item.reviewsCount, 42);
    });

    test('rejette un item sans slug — inutilisable, rien à ouvrir', () {
      expect(DiscoveryItem.tryParse({'name': 'Sans slug'}), isNull);
      expect(DiscoveryItem.tryParse({'slug': '', 'name': 'Vide'}), isNull);
      expect(DiscoveryItem.tryParse({'slug': '   '}), isNull);
    });

    test('accepte un item minimal — une verticale pauvre ne casse pas la liste', () {
      final item = DiscoveryItem.tryParse({'slug': 'x', 'type': 'showcase'});
      expect(item, isNotNull);
      // Sans `name`, on retombe sur le slug plutôt que d'afficher une tuile
      // vide : la personne peut au moins reconnaître l'adresse.
      expect(item!.name, 'x');
      expect(item.city, isNull);
      expect(item.imageUrl, isNull);
    });

    test('tolère des types numériques en String (payload d\'un autre provider)', () {
      final item = DiscoveryItem.tryParse({
        'slug': 'x',
        'latitude': '33.5',
        'longitude': '-7.5',
        'reviews_count': '7',
      });
      expect(item!.latitude, closeTo(33.5, 0.001));
      expect(item!.longitude, closeTo(-7.5, 0.001));
      expect(item!.reviewsCount, 7);
    });

    test('une clé inutilisable produit null, sans exception', () {
      expect(DiscoveryItem.tryParse(null), isNull);
      expect(DiscoveryItem.tryParse('chaîne'), isNull);
      expect(DiscoveryItem.tryParse([1, 2, 3]), isNull);
    });

    test('key combine type ET slug — deux verticales peuvent partager un slug', () {
      final a = DiscoveryItem.tryParse({'slug': 'nadia', 'type': 'restaurant'})!;
      final b = DiscoveryItem.tryParse({'slug': 'nadia', 'type': 'showcase'})!;
      expect(a.key, isNot(b.key));
      expect(a.key, 'restaurant|nadia');
    });
  });

  group('DiscoveryPage.fromJson', () {
    test('lit meta.actions et meta.action_urls', () {
      final page = DiscoveryPage.fromJson({
        'data': [
          {'type': 'restaurant', 'slug': 'a', 'name': 'A'},
          {'type': 'showcase', 'slug': 'b', 'name': 'B'},
        ],
        'meta': {
          'total': 2,
          'page': 1,
          'last_page': 1,
          'actions': {
            'restaurant': ['contact', 'order'],
            'showcase': ['contact'],
          },
          'action_urls': {
            'restaurant': {'contact': '/conversations/new', 'order': null},
          },
        },
      });

      expect(page.items.length, 2);
      expect(page.actionsFor('restaurant'), ['contact', 'order']);
      expect(page.actionsFor('showcase'), ['contact']);
      // Type inconnu : aucune capacité → aucun bouton. Fail-closed.
      expect(page.actionsFor('inconnu'), isEmpty);
      expect(page.actionUrlFor('restaurant', 'contact'), '/conversations/new');
      expect(page.actionUrlFor('restaurant', 'order'), isNull);
    });

    test('ignore les items invalides sans perdre les valides', () {
      final page = DiscoveryPage.fromJson({
        'data': [
          {'slug': 'ok', 'name': 'OK'},
          {'name': 'sans slug'},
          null,
          'pas un objet',
        ],
        'meta': {'total': 4},
      });
      expect(page.items.length, 1);
      expect(page.items.first.slug, 'ok');
      // `total` vient du serveur (4) alors qu'on n'affiche qu'1 item : c'est
      // volontaire, le compteur reflète la réalité serveur, pas notre filtrage.
      expect(page.total, 4);
    });

    test('une réponse sans meta ne lève pas', () {
      final page = DiscoveryPage.fromJson({'data': []});
      expect(page.items, isEmpty);
      expect(page.actions, isEmpty);
      expect(page.page, 1);
      expect(page.lastPage, 1);
      expect(page.hasMore, isFalse);
    });
  });

  group('DiscoveryCategory', () {
    test('`visible` absent = false — fail-closed', () {
      // Le serveur déclare ce qui est publiable ; l'app n'affiche jamais une
      // catégorie qu'il n'a pas explicitement validée.
      final category = DiscoveryCategory.tryParse({'key': 'restaurant', 'count': 12});
      expect(category!.visible, isFalse);
    });

    test('le seuil et le compteur sont conservés pour expliquer une absence', () {
      final categories = DiscoveryCategories.fromJson({
        'data': [
          {'key': 'restaurant', 'label': 'Restaurants', 'count': 12, 'visible': true},
          {'key': 'pharmacy', 'label': 'Pharmacies', 'count': 2, 'visible': false},
        ],
        'meta': {'threshold': 5, 'city': 'Casablanca'},
      });

      expect(categories.visible.length, 1);
      expect(categories.visible.first.key, 'restaurant');
      expect(categories.threshold, 5);
      expect(categories.city, 'Casablanca');
      // La catégorie sous le seuil reste CONNUE : l'écran peut l'expliquer.
      expect(categories.categories.length, 2);
    });
  });

  group('BusinessProfile', () {
    test('accepte les deux formes de payload (restaurant et découverte)', () {
      final restaurant = BusinessProfile.tryParse('chez-nadia', {
        'slug': 'chez-nadia',
        'name': 'Chez Nadia',
        'cover_image_url': 'https://cdn/x.jpg',
        'establishment_type': 'restaurant',
        'hours': [
          {'day_of_week': 1, 'opens_at': '09:00:00', 'closes_at': '18:00:00'},
        ],
        'menu': [
          {
            'name': 'Entrées',
            'items': [
              {'name': 'Harira', 'price': 25.0},
            ],
          },
        ],
      });

      expect(restaurant!.imageUrl, 'https://cdn/x.jpg');
      expect(restaurant.category, 'restaurant');
      expect(restaurant.hours.length, 1);
      expect(restaurant.menu.length, 1);
      expect(restaurant.menu.first.items.first.price, 25.0);
    });

    test('`supportedActions` est la seule source des boutons', () {
      final profile = BusinessProfile.tryParse('x', {
        'slug': 'x',
        'name': 'X',
        'supported_actions': ['contact', 'booking'],
      })!;

      expect(profile.supports('contact'), isTrue);
      expect(profile.supports('booking'), isTrue);
      // `order` n'est PAS déclaré : aucun bouton Commander, même si le type
      // est un restaurant. C'est la règle « aucun bouton mort ».
      expect(profile.supports('order'), isFalse);
      expect(profile.supports('nimporte_quoi'), isFalse);
    });

    test('isOpenAt gère une plage normale', () {
      final profile = BusinessProfile.tryParse('x', {
        'slug': 'x',
        'name': 'X',
        'hours': [
          {'day_of_week': 3, 'opens_at': '09:00', 'closes_at': '18:00'},
        ],
      })!;

      // Mercredi = weekday 3 en Dart, day_of_week 3 côté serveur.
      expect(profile.isOpenAt(DateTime(2026, 11, 4, 10, 0)), isTrue);
      expect(profile.isOpenAt(DateTime(2026, 11, 4, 19, 0)), isFalse);
      expect(profile.isOpenAt(DateTime(2026, 11, 4, 8, 59)), isFalse);
    });

    test('isOpenAt gère une plage qui passe minuit', () {
      final profile = BusinessProfile.tryParse('x', {
        'slug': 'x',
        'name': 'X',
        'hours': [
          {'day_of_week': 3, 'opens_at': '20:00', 'closes_at': '02:00'},
        ],
      })!;

      // 23:00 le mercredi : ouvert.
      expect(profile.isOpenAt(DateTime(2026, 11, 4, 23, 0)), isTrue);
      // 01:00 le même jour calendaire : ouvert (la plage court jusque 02:00).
      expect(profile.isOpenAt(DateTime(2026, 11, 4, 1, 0)), isTrue);
      // 03:00 : fermé.
      expect(profile.isOpenAt(DateTime(2026, 11, 4, 3, 0)), isFalse);
    });

    test('isOpenAt retourne null quand les horaires sont inconnus', () {
      // Un `null` affiche l'état SANS badge : ne rien dire vaut mieux
      // qu'afficher « Fermé » sur un commerce qui est peut-être ouvert.
      final profile = BusinessProfile.tryParse('x', {'slug': 'x', 'name': 'X'})!;
      expect(profile.isOpenAt(DateTime(2026, 11, 4, 12, 0)), isNull);
    });

    test('un jour absent des horaires retourne null, pas false', () {
      final profile = BusinessProfile.tryParse('x', {
        'slug': 'x',
        'name': 'X',
        'hours': [
          {'day_of_week': 1, 'opens_at': '09:00', 'closes_at': '18:00'},
        ],
      })!;
      // Mardi : aucune ligne → on ne sait pas, on ne prétend pas que c'est fermé.
      expect(profile.isOpenAt(DateTime(2026, 11, 3, 12, 0)), isNull);
    });
  });
}
