import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/pos/pos_search.dart';

/// Tests du moteur de recherche partage par les deux caisses POS.
///
/// Ces regles sont celles qui font gagner du temps au comptoir : un prefixe
/// remonte avant une sous-chaine, un scan exact ne ramene jamais un voisin, et
/// un accent oublie ne rend pas un article introuvable.
class _Item implements PosSearchable {
  const _Item({
    required this.id,
    required this.name,
    this.sku,
    this.barcode,
    this.categoryId,
  });

  @override
  final int id;
  @override
  final String name;
  @override
  final String? sku;
  @override
  final String? barcode;
  @override
  final int? categoryId;
}

void main() {
  const bissap = _Item(
    id: 1,
    name: 'Jus de bissap 50cl',
    sku: 'SKU-BISSAP-50',
    barcode: '6001234567890',
    categoryId: 3,
  );
  const the =
      _Item(id: 2, name: 'The a la menthe', sku: 'SKU-THE-01', categoryId: 3);
  const cafe =
      _Item(id: 3, name: 'Cafe noir', sku: 'SKU-CAFE-01', categoryId: 4);
  const theGlace =
      _Item(id: 4, name: 'The glace citron', sku: 'SKU-THE-02', categoryId: 3);

  group('normalizePosQuery', () {
    test('met en minuscules et retire les accents', () {
      expect(normalizePosQuery('Thé'), 'the');
      expect(normalizePosQuery('CAFÉ'), 'cafe');
      expect(normalizePosQuery('  Bissap  '), 'bissap');
    });

    test('laisse intacte une chaine sans accent', () {
      expect(normalizePosQuery('menthe'), 'menthe');
    });
  });

  group('matchQuality', () {
    test('un code-barres exact gagne sur tout le reste', () {
      expect(
        matchQuality(
            query: '6001234567890', name: bissap.name, barcode: bissap.barcode),
        PosMatchQuality.barcodeExact,
      );
    });

    test('un code-barres partiel ne matche pas', () {
      expect(
        matchQuality(
            query: '600123', name: bissap.name, barcode: bissap.barcode),
        PosMatchQuality.none,
      );
    });

    test('un prefixe de libelle est reconnu', () {
      expect(
        matchQuality(query: 'jus', name: bissap.name),
        PosMatchQuality.namePrefix,
      );
    });

    test('un prefixe de mot est reconnu', () {
      expect(
        matchQuality(query: 'ment', name: the.name),
        PosMatchQuality.wordPrefix,
      );
    });

    test('un prefixe de SKU est reconnu', () {
      expect(
        matchQuality(query: 'sku-cafe', name: cafe.name, sku: cafe.sku),
        PosMatchQuality.skuPrefix,
      );
    });

    test('une sous-chaine de libelle est reconnue', () {
      expect(
        matchQuality(query: 'ssa', name: bissap.name),
        PosMatchQuality.nameContains,
      );
    });

    test('une saisie vide matche tout', () {
      expect(
          matchQuality(query: '', name: cafe.name), PosMatchQuality.namePrefix);
    });

    test('un accent oublie trouve quand meme l article', () {
      expect(
        matchQuality(query: 'the', name: the.name),
        PosMatchQuality.namePrefix,
      );
    });
  });

  group('rankPosItems', () {
    test('classe prefixe avant sous-chaine', () {
      final ranked = rankPosItems(items: [bissap, the, theGlace], query: 'the');

      // `The a la menthe` et `The glace citron` commencent par `the` ;
      // `Jus de bissap` ne matche pas du tout.
      expect(ranked.map((item) => item.id), [2, 4]);
    });

    test('un prefixe de mot passe avant une sous-chaine', () {
      final ranked = rankPosItems(items: [bissap, the], query: 'ment');

      expect(ranked.single.id, 2);
    });

    test('preserve l ordre d entree a qualite egale', () {
      final ranked = rankPosItems(items: [theGlace, the], query: 'the');

      expect(ranked.map((item) => item.id), [4, 2]);
    });

    test('une saisie vide renvoie la liste inchangee', () {
      final ranked = rankPosItems(items: [cafe, bissap], query: '   ');

      expect(ranked.map((item) => item.id), [3, 1]);
    });

    test('ne renvoie rien quand rien ne correspond', () {
      expect(rankPosItems(items: [bissap, the], query: 'zzz'), isEmpty);
    });
  });

  group('PosAffinityRegistry', () {
    test('enregistre un usage et incremente le compteur', () {
      final registry = const PosAffinityRegistry()
          .recordUse(1, at: DateTime(2026, 11, 9, 10))
          .recordUse(1, at: DateTime(2026, 11, 9, 11));

      expect(registry.affinityFor(1)!.usageCount, 2);
      expect(registry.affinityFor(1)!.lastUsedAt, DateTime(2026, 11, 9, 11));
    });

    test('epingle et depingle un favori', () {
      final pinned = const PosAffinityRegistry().toggleFavourite(2);

      expect(pinned.isFavourite(2), isTrue);
      expect(pinned.toggleFavourite(2).isFavourite(2), isFalse);
    });

    test('place les favoris en tete', () {
      final registry = const PosAffinityRegistry()
          .recordUse(1, at: DateTime(2026, 11, 9, 10))
          .recordUse(1, at: DateTime(2026, 11, 9, 11))
          .toggleFavourite(3);

      final ordered = registry.order([bissap, the, cafe]);

      expect(ordered.first.id, 3);
    });

    test('trie par usage decroissant a defaut de favori', () {
      final registry = const PosAffinityRegistry()
          .recordUse(2, at: DateTime(2026, 11, 9, 10))
          .recordUse(2, at: DateTime(2026, 11, 9, 11))
          .recordUse(1, at: DateTime(2026, 11, 9, 12));

      final ordered = registry.order([bissap, the, cafe]);

      expect(ordered.map((item) => item.id), [2, 1, 3]);
    });

    test('liste les recents du plus recent au plus ancien', () {
      final registry = const PosAffinityRegistry()
          .recordUse(1, at: DateTime(2026, 11, 9, 10))
          .recordUse(2, at: DateTime(2026, 11, 9, 12))
          .recordUse(3, at: DateTime(2026, 11, 9, 11));

      expect(registry.recentItemIds(), [2, 3, 1]);
    });

    test('survit a un aller-retour JSON', () {
      final registry = const PosAffinityRegistry()
          .recordUse(1, at: DateTime(2026, 11, 9, 10))
          .toggleFavourite(2);

      final restored = PosAffinityRegistry.fromJson(registry.toJson());

      expect(restored.affinityFor(1)!.usageCount, 1);
      expect(restored.isFavourite(2), isTrue);
    });

    test('ignore une entree corrompue sans perdre le reste', () {
      final restored = PosAffinityRegistry.fromJson({
        'entries': [
          {'item_id': 1, 'usage_count': 3},
          {'nope': true},
        ],
      });

      expect(restored.affinityFor(1)!.usageCount, 3);
      expect(restored.entries.length, 1);
    });

    test('n evince jamais un favori quand le registre est plein', () {
      var registry = const PosAffinityRegistry().toggleFavourite(999);

      for (var id = 0; id < PosAffinityRegistry.maxEntries + 5; id++) {
        registry = registry.recordUse(id, at: DateTime(2026, 11, 9));
      }

      expect(registry.isFavourite(999), isTrue);
      expect(registry.entries.length,
          lessThanOrEqualTo(PosAffinityRegistry.maxEntries));
    });
  });
}
