import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/features/journeys/data/restaurant_order_gateway.dart';
import 'package:leopardo_public/features/journeys/domain/restaurant_order_models.dart';

/// Tests du contrat de commande restaurant par slug — #9959.
///
/// Ces tests portent sur le corps de requête et le parsing, sans réseau : c'est
/// là que se trouvent les règles qui, si elles sont fausses, produisent un 422
/// ou un affichage trompeur.
void main() {
  RestaurantMenuProduct product({
    String code = 'PIZZA-01',
    String name = 'Pizza',
    int? priceMinor = 4500,
    String? currency = 'MAD',
  }) =>
      RestaurantMenuProduct(
        code: code,
        name: name,
        priceMinor: priceMinor,
        currency: currency,
      );

  group('RestaurantMenuProduct.tryParse', () {
    test('écarte un produit sans code', () {
      // Le serveur désigne les lignes par `product_code` : un produit sans code
      // n'est pas commandable. L'afficher puis refuser la commande serait pire
      // que de ne pas l'afficher.
      expect(RestaurantMenuProduct.tryParse({'name': 'Pizza'}), isNull);
    });

    test('accepte un produit sans prix, prix null', () {
      final parsed = RestaurantMenuProduct.tryParse({
        'code': 'X',
        'name': 'X',
      });
      expect(parsed, isNotNull);
      expect(parsed!.priceMinor, isNull);
    });

    test('lit le prix et la devise', () {
      final parsed = RestaurantMenuProduct.tryParse({
        'code': 'X',
        'name': 'X',
        'price_minor': 1200,
        'currency': 'EUR',
      });
      expect(parsed!.priceMinor, 1200);
      expect(parsed.currency, 'EUR');
    });
  });

  group('RestaurantPublicProfile.tryParse', () {
    test('écarte une catégorie vide du menu', () {
      // Une catégorie sans produit publié n'a rien à montrer : la garder
      // afficherait un titre suivi de rien.
      final profile = RestaurantPublicProfile.tryParse({
        'slug': 'chez-paul',
        'name': 'Chez Paul',
        'menu': [
          {'name': 'Vide', 'products': []},
          {
            'name': 'Plats',
            'products': [
              {'code': 'A', 'name': 'A'},
            ],
          },
        ],
      });

      expect(profile!.menu.length, 1);
      expect(profile.menu.first.name, 'Plats');
    });

    test('hasMenu est faux sans aucun produit', () {
      final profile = RestaurantPublicProfile.tryParse({
        'slug': 'x',
        'name': 'X',
        'menu': [
          {'name': 'Vide', 'products': []},
        ],
      });
      expect(profile!.hasMenu, isFalse);
    });

    test('productCount additionne toutes les catégories', () {
      final profile = RestaurantPublicProfile.tryParse({
        'slug': 'x',
        'name': 'X',
        'menu': [
          {
            'name': 'A',
            'products': [
              {'code': '1', 'name': '1'},
              {'code': '2', 'name': '2'},
            ],
          },
          {
            'name': 'B',
            'products': [
              {'code': '3', 'name': '3'},
            ],
          },
        ],
      });
      expect(profile!.productCount, 3);
    });

    test('écarte un profil sans slug', () {
      expect(RestaurantPublicProfile.tryParse({'name': 'X'}), isNull);
    });
  });

  group('RestaurantCartLine', () {
    test('le total de ligne est le prix × quantité', () {
      final line = RestaurantCartLine(product: product(), quantity: 3);
      expect(line.lineTotalMinor, 13500);
    });

    test('un prix absent rend un total de ligne null, jamais 0', () {
      // Remplacer un prix manquant par 0 donnerait un total inférieur au vrai :
      // l'utilisateur croirait à un montant que le serveur ne confirmera pas.
      final line = RestaurantCartLine(
        product: product(priceMinor: null),
        quantity: 2,
      );
      expect(line.lineTotalMinor, isNull);
    });

    test('toPayload omet une note vide', () {
      final line = RestaurantCartLine(
        product: product(),
        quantity: 1,
        note: '   ',
      );
      expect(line.toPayload().containsKey('note'), isFalse);
    });

    test('toPayload inclut une note non vide, élaguée', () {
      final line = RestaurantCartLine(
        product: product(),
        quantity: 1,
        note: '  sans oignons  ',
      );
      expect(line.toPayload()['note'], 'sans oignons');
    });
  });

  group('buildOrderPayload', () {
    test('produit le corps exact du contrat', () {
      final payload = RestaurantOrderGateway.buildOrderPayload(
        customerName: 'Awa',
        customerPhone: '+212600000000',
        lines: [RestaurantCartLine(product: product(), quantity: 2)],
        idempotencyKey: 'key-1',
      );

      expect(payload['customer_name'], 'Awa');
      expect(payload['customer_phone'], '+212600000000');
      expect(payload['order_type'], 'pickup');
      expect(payload['idempotency_key'], 'key-1');
      expect(payload['items'], [
        {'product_code': 'PIZZA-01', 'quantity': 2},
      ]);
    });

    test('n\'envoie AUCUN montant', () {
      // Prix et TVA sont calculés serveur : envoyer un total serait au mieux
      // ignoré, au pire une faille de confiance.
      final payload = RestaurantOrderGateway.buildOrderPayload(
        customerName: 'Awa',
        customerPhone: '06',
        lines: [RestaurantCartLine(product: product(), quantity: 1)],
        idempotencyKey: 'k',
      );

      expect(payload.containsKey('total_minor'), isFalse);
      expect(payload.containsKey('subtotal_minor'), isFalse);
      expect(payload.containsKey('tax_minor'), isFalse);
    });

    test('refuse un nom vide', () {
      expect(
        () => RestaurantOrderGateway.buildOrderPayload(
          customerName: '   ',
          customerPhone: '06',
          lines: [RestaurantCartLine(product: product(), quantity: 1)],
          idempotencyKey: 'k',
        ),
        throwsArgumentError,
      );
    });

    test('refuse un téléphone vide', () {
      // `customer_phone` est REQUIS par StoreRestaurantPublicSlugOrderRequest :
      // l'omettre produirait un 422 que l'utilisateur ne comprendrait pas.
      expect(
        () => RestaurantOrderGateway.buildOrderPayload(
          customerName: 'Awa',
          customerPhone: '',
          lines: [RestaurantCartLine(product: product(), quantity: 1)],
          idempotencyKey: 'k',
        ),
        throwsArgumentError,
      );
    });

    test('refuse un panier vide', () {
      expect(
        () => RestaurantOrderGateway.buildOrderPayload(
          customerName: 'Awa',
          customerPhone: '06',
          lines: const [],
          idempotencyKey: 'k',
        ),
        throwsArgumentError,
      );
    });

    test('refuse une quantité hors bornes', () {
      expect(
        () => RestaurantOrderGateway.buildOrderPayload(
          customerName: 'Awa',
          customerPhone: '06',
          lines: [
            RestaurantCartLine(
              product: product(),
              quantity: restaurantMaxLineQuantity + 1,
            ),
          ],
          idempotencyKey: 'k',
        ),
        throwsArgumentError,
      );
    });

    test('refuse une clé d\'idempotence trop longue', () {
      // Le contrat plafonne à 64 caractères.
      expect(
        () => RestaurantOrderGateway.buildOrderPayload(
          customerName: 'Awa',
          customerPhone: '06',
          lines: [RestaurantCartLine(product: product(), quantity: 1)],
          idempotencyKey: 'x' * 65,
        ),
        throwsArgumentError,
      );
    });

    test('transmet le type de commande demandé', () {
      final payload = RestaurantOrderGateway.buildOrderPayload(
        customerName: 'Awa',
        customerPhone: '06',
        lines: [RestaurantCartLine(product: product(), quantity: 1)],
        idempotencyKey: 'k',
        orderType: RestaurantOrderType.delivery,
      );
      expect(payload['order_type'], 'delivery');
    });

    test('omet une note globale vide', () {
      final payload = RestaurantOrderGateway.buildOrderPayload(
        customerName: 'Awa',
        customerPhone: '06',
        lines: [RestaurantCartLine(product: product(), quantity: 1)],
        idempotencyKey: 'k',
        note: '  ',
      );
      expect(payload.containsKey('note'), isFalse);
    });
  });

  group('RestaurantOrderResult.tryParse', () {
    test('lit le secret de suivi à la création', () {
      final result = RestaurantOrderResult.tryParse({
        'reference': 'RST-1',
        'status': 'pending',
        'total_minor': 9000,
        'currency': 'MAD',
        'created': true,
        'tracking_secret': 'abc123',
      });

      expect(result!.created, isTrue);
      expect(result.trackingSecret, 'abc123');
    });

    test('le secret est null au rejeu idempotent', () {
      // Le serveur ne le rend qu'à la création réelle : au rejeu il est absent,
      // et l'écran doit le dire plutôt qu'afficher un champ vide.
      final result = RestaurantOrderResult.tryParse({
        'reference': 'RST-1',
        'status': 'pending',
        'created': false,
      });

      expect(result!.created, isFalse);
      expect(result.trackingSecret, isNull);
    });

    test('écarte une réponse sans référence', () {
      expect(RestaurantOrderResult.tryParse({'status': 'pending'}), isNull);
    });
  });

  group('RestaurantOrderTracking.tryParse', () {
    test('lit les lignes de la commande', () {
      final tracking = RestaurantOrderTracking.tryParse(
        {
          'reference': 'RST-1',
          'status': 'preparing',
          'total_minor': 9000,
          'items': [
            {'name': 'Pizza', 'quantity': 2, 'line_total_minor': 9000},
          ],
        },
        fallbackReference: 'RST-1',
      );

      expect(tracking!.items.length, 1);
      expect(tracking.items.first.name, 'Pizza');
      expect(tracking.items.first.quantity, 2);
    });

    test('utilise la référence de repli quand elle manque', () {
      final tracking = RestaurantOrderTracking.tryParse(
        {'status': 'preparing'},
        fallbackReference: 'RST-9',
      );
      expect(tracking!.reference, 'RST-9');
    });
  });

  group('RestaurantPaymentMethod', () {
    test('n\'expose que les moyens ouverts au public', () {
      // Le contrôleur n'ouvre que cash_on_delivery et mobile_money : la
      // passerelle carte reste fermée côté serveur (spec #5272 en attente).
      // Proposer « carte » enverrait une valeur que le service refuserait.
      final wires = RestaurantPaymentMethod.values.map((m) => m.wire).toList();
      expect(wires, ['cash_on_delivery', 'mobile_money']);
      expect(wires.contains('card'), isFalse);
    });
  });
}
