import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/core/i18n/app_strings.dart';
import 'package:leopardo_public/features/activity/data/public_activity_repository.dart';

/// COULOIR 16 — M3 (#9105) · Modèles de l'onglet Activités.
///
/// Ces tests couvrent le contrat de DÉSÉRIALISATION : ce que l'app accepte de
/// croire quand le serveur répond. Ils ne testent pas l'interface (elle dépend
/// de providers réseau) mais les deux règles qui, si elles cassaient, feraient
/// mentir l'écran :
///
///  1. un statut absent ou inconnu n'est jamais inventé ;
///  2. un favori indisponible reste dans la liste, marqué `available: false`.
void main() {
  group('PublicActivity.fromJson', () {
    test('lit une activité complète', () {
      final activity = PublicActivity.fromJson({
        'vertical': 'restaurant',
        'activity_type': 'order',
        'reference': 'RST-123',
        'status': 'open',
        'title': 'Commande',
        'occurred_at': '2026-11-02T10:00:00+00:00',
      });

      expect(activity.vertical, 'restaurant');
      expect(activity.reference, 'RST-123');
      expect(activity.status, 'open');
      expect(activity.isOrder, isTrue);
      expect(activity.occurredAt, isNotNull);
    });

    test('ne devine pas un statut absent', () {
      final activity = PublicActivity.fromJson({
        'vertical': 'restaurant',
        'activity_type': 'booking',
        'reference': 'RSV-9',
      });

      // Le statut est une donnée du TENANT : s'il manque, on affiche une chaîne
      // vide plutôt qu'un « en cours » inventé.
      expect(activity.status, '');
      expect(activity.isOrder, isFalse);
    });

    test('tolère une date invalide sans lever', () {
      final activity = PublicActivity.fromJson({
        'reference': 'RST-1',
        'occurred_at': 'pas-une-date',
      });

      expect(activity.occurredAt, isNull);
    });
  });

  group('PublicActivitySection.fromJson', () {
    test('lit les types déclarés par le serveur', () {
      final section = PublicActivitySection.fromJson({
        'vertical': 'restaurant',
        'types': ['order', 'booking'],
      });

      expect(section.vertical, 'restaurant');
      expect(section.types, ['order', 'booking']);
    });

    test('une section sans type reste vide, elle n’invente rien', () {
      final section = PublicActivitySection.fromJson({'vertical': 'pharmacy'});

      expect(section.types, isEmpty);
    });
  });

  group('PublicFavorite.fromJson', () {
    test('un favori indisponible est conservé et marqué', () {
      final favorite = PublicFavorite.fromJson({
        'target_type': 'restaurant',
        'target_slug': 'resto-ferme',
        'display_name': null,
        'available': false,
      });

      // Le favori n'est PAS supprimé : la personne l'a choisi. L'écran propose
      // autre chose au lieu d'un bouton mort.
      expect(favorite.available, isFalse);
      expect(favorite.displayName, isNull);
      expect(favorite.targetSlug, 'resto-ferme');
    });

    test('un nom vide devient null plutôt qu’une chaîne vide', () {
      final favorite = PublicFavorite.fromJson({
        'target_type': 'restaurant',
        'target_slug': 'x',
        'display_name': '',
        'available': true,
      });

      expect(favorite.displayName, isNull);
    });
  });

  group('PublicSearchEntry.fromJson', () {
    test('lit une entrée d’historique', () {
      final entry = PublicSearchEntry.fromJson({
        'query': 'pizza',
        'city': 'Alger',
        'result_count': 4,
        'searched_at': '2026-11-02T09:00:00+00:00',
      });

      expect(entry.query, 'pizza');
      expect(entry.city, 'Alger');
      expect(entry.resultCount, 4);
    });

    test('aucune coordonnée n’est exposée par le modèle', () {
      final entry = PublicSearchEntry.fromJson({
        'query': 'kebab',
        'latitude': 35.6971,
        'longitude': -0.6308,
      });

      // Le modèle n'a pas de champ position : même si le serveur en envoyait,
      // l'app ne pourrait pas les afficher ni les conserver.
      expect(entry.city, isNull);
      expect(entry.query, 'kebab');
    });
  });

  group('Catalogue de chaînes M3', () {
    test('les clés d’activité existent dans les 4 locales', () {
      const keys = [
        'activityOrderTitle',
        'activityBookingTitle',
        'activityEmptyTitle',
        'activitySectionOrders',
        'activitySectionBookings',
        'activityStatusOpen',
        'favoritesTitle',
        'favoritesEmptyTitle',
        'favoritesUnavailable',
        'searchHistoryTitle',
        'searchHistoryClear',
        'activitySignInRequired',
      ];

      for (final locale in AppStrings.supportedLocales) {
        final strings = AppStrings.of(locale);
        for (final key in keys) {
          // Repli sur la clé brute = clé manquante : on l'interdit ici.
          expect(
            strings.t(key),
            isNot(key),
            reason: 'clé $key absente de la locale $locale',
          );
        }
      }
    });

    test('l’arabe est bien RTL', () {
      expect(AppStrings.of('ar').isRtl, isTrue);
      expect(AppStrings.of('fr').isRtl, isFalse);
    });
  });
}
