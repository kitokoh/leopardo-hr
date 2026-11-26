import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_marketing/features/marketing/models/social_post.dart';

/// Parsing de `SocialPost` (#9963).
///
/// Le modèle est la seule barrière entre la réponse JSON du serveur et les
/// écrans : un champ absent ou d'un type inattendu ne doit jamais produire
/// une exception au rendu, mais une valeur neutre explicite.
void main() {
  group('SocialPost.fromJson', () {
    test('lit une publication complète', () {
      final post = SocialPost.fromJson({
        'id': 42,
        'content': 'Annonce de recrutement',
        'status': 'published',
        'target_platforms': ['linkedin', 'facebook'],
        'created_at': '2026-11-26T10:00:00Z',
        'scheduled_at': '2026-11-26T09:00:00Z',
        'published_at': '2026-11-26T10:05:00Z',
        'provider_post_ref': 'li_123',
      });

      expect(post.id, 42);
      expect(post.content, 'Annonce de recrutement');
      expect(post.status, 'published');
      expect(post.targetPlatforms, ['linkedin', 'facebook']);
      expect(post.providerPostRef, 'li_123');
      expect(post.isPublished, isTrue);
      expect(post.isScheduled, isFalse);
    });

    test('un id numérique sous forme de chaîne est converti', () {
      // Le serveur peut sérialiser un id en string selon le driver : le
      // modèle doit accepter les deux, sinon la fiche de détail reçoit 0.
      final post = SocialPost.fromJson({'id': '7'});
      expect(post.id, 7);
    });

    test('un id absent vaut 0 plutôt que de lever', () {
      final post = SocialPost.fromJson({});
      expect(post.id, 0);
    });

    test('un statut absent retombe sur draft', () {
      // Un statut vide afficherait un badge sans couleur ni libellé.
      final post = SocialPost.fromJson({'id': 1});
      expect(post.status, 'draft');
      expect(post.isDraft, isTrue);
    });

    test('target_platforms non-liste devient une liste vide', () {
      final post = SocialPost.fromJson({
        'id': 1,
        'target_platforms': 'linkedin',
      });
      expect(post.targetPlatforms, isEmpty);
    });

    test('les champs optionnels absents restent null', () {
      final post = SocialPost.fromJson({'id': 1, 'content': 'x'});
      expect(post.scheduledAt, isNull);
      expect(post.publishedAt, isNull);
      expect(post.providerPostRef, isNull);
    });

    test('les prédicats de statut sont exclusifs', () {
      final scheduled = SocialPost.fromJson({'id': 1, 'status': 'scheduled'});
      expect(scheduled.isScheduled, isTrue);
      expect(scheduled.isPublished, isFalse);
      expect(scheduled.isDraft, isFalse);
      expect(scheduled.isFailed, isFalse);

      final failed = SocialPost.fromJson({'id': 1, 'status': 'failed'});
      expect(failed.isFailed, isTrue);
      expect(failed.isScheduled, isFalse);
    });
  });

  group('plateformes', () {
    test('chaque plateforme disponible a une icône', () {
      // Une plateforme sans icône s'afficherait avec un préfixe vide dans la
      // liste et la fiche de détail.
      for (final platform in SocialPost.availablePlatforms) {
        expect(
          SocialPost.platformIcons.containsKey(platform),
          isTrue,
          reason: 'Plateforme "$platform" sans icône dans platformIcons.',
        );
      }
    });
  });
}
