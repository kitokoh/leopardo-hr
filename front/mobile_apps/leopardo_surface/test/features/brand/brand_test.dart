/// #9581 (BC-41 BRANDING) — tests du domaine et du chargement de marque.
///
/// Ces tests n'ont besoin ni de serveur, ni de widget, ni de Dio : le domaine
/// est pur et le chargeur est injecté. C'est exactement ce que la séparation
/// en couches achète.
library;

import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_surface/src/features/brand/domain/brand.dart';
import 'package:leopardo_surface/src/features/brand/infrastructure/brand_api.dart';

Map<String, dynamic> validDto() => <String, dynamic>{
      'slug': 'chez-lea',
      'locale': 'fr',
      'direction': 'ltr',
      'locales': <String>['fr', 'en'],
      'default_locale': 'fr',
      'vertical': 'restaurant',
      'identity': <String, dynamic>{
        'display_name': 'Chez Léa',
        'tagline': 'Cuisine de saison',
        'logo_url': null,
        'favicon_url': null,
        'og_image_url': null,
      },
      'palette': <String, dynamic>{
        'primary': '#a3e635',
        'accent': '#0d9488',
        'surface': '#ffffff',
        'on_primary': '#111827',
      },
      'surface': <String, dynamic>{
        'host': 'chez-lea.sn',
        'path_prefix': null,
        'locales': <String>['fr', 'en'],
        'default_locale': 'fr',
      },
      'profile': <String, dynamic>{
        'vertical': 'restaurant',
        'label': 'Restauration',
        'capabilities': <String>['order', 'reserve', 'contact'],
        'block_types': <String>['core.hero', 'restaurant.menu'],
        'primary_cta': 'order',
      },
    };

void main() {
  group('Brand.tryParse', () {
    test('accepte un DTO complet', () {
      final brand = Brand.tryParse(validDto());

      expect(brand, isNotNull);
      expect(brand!.slug, 'chez-lea');
      expect(brand.identity.displayName, 'Chez Léa');
      expect(brand.profile.vertical, 'restaurant');
    });

    test('rejette un DTO sans slug', () {
      final dto = validDto()..['slug'] = '';

      expect(Brand.tryParse(dto), isNull);
    });

    test('rejette une identité sans nom public', () {
      final dto = validDto();
      (dto['identity'] as Map<String, dynamic>)['display_name'] = '';

      expect(Brand.tryParse(dto), isNull);
    });

    test('rejette une palette incomplète', () {
      final dto = validDto();
      dto['palette'] = <String, dynamic>{'primary': '#a3e635'};

      expect(Brand.tryParse(dto), isNull);
    });

    test('rejette une couleur non hexadécimale', () {
      final dto = validDto();
      (dto['palette'] as Map<String, dynamic>)['primary'] = 'lime';

      expect(Brand.tryParse(dto), isNull);
    });

    test('rejette un profil absent', () {
      final dto = validDto()..remove('profile');

      expect(Brand.tryParse(dto), isNull);
    });

    test('neutralise un CTA primaire non déclaré', () {
      final dto = validDto();
      final profile = dto['profile'] as Map<String, dynamic>;
      profile['capabilities'] = <String>['contact'];
      profile['primary_cta'] = 'order';

      expect(Brand.tryParse(dto)!.profile.primaryCta, isNull);
    });

    test('respecte la direction RTL', () {
      final dto = validDto()
        ..['locale'] = 'ar'
        ..['direction'] = 'rtl';

      expect(Brand.tryParse(dto)!.direction, TextDirectionality.rtl);
    });
  });

  group('normalizeBrandLocale', () {
    test('accepte les quatre locales du produit', () {
      expect(normalizeBrandLocale('fr'), 'fr');
      expect(normalizeBrandLocale('EN'), 'en');
      expect(normalizeBrandLocale('ar'), 'ar');
      expect(normalizeBrandLocale('tr'), 'tr');
    });

    test('retombe sur vide pour une locale inconnue', () {
      expect(normalizeBrandLocale('xx'), '');
      expect(normalizeBrandLocale(''), '');
      expect(normalizeBrandLocale(null), '');
    });
  });

  group('directionForLocale', () {
    test('donne RTL pour l’arabe uniquement', () {
      expect(directionForLocale('ar'), TextDirectionality.rtl);
      expect(directionForLocale('fr'), TextDirectionality.ltr);
    });
  });

  group('orderedCapabilities', () {
    test('place le CTA primaire en tête', () {
      final brand = Brand.tryParse(validDto())!;

      expect(orderedCapabilities(brand.profile).first, 'order');
    });

    test('conserve l’ordre du profil sans CTA primaire', () {
      final brand = Brand.tryParse(validDto())!;
      final profile = BrandSurfaceProfile(
        vertical: brand.profile.vertical,
        label: brand.profile.label,
        capabilities: brand.profile.capabilities,
        blockTypes: brand.profile.blockTypes,
        primaryCta: null,
      );

      expect(orderedCapabilities(profile), brand.profile.capabilities);
    });
  });

  group('brandPublicPath', () {
    test('encode le slug', () {
      expect(brandPublicPath('chez lea'), '/public/brands/chez%20lea');
    });

    test('ajoute la locale quand elle est fournie', () {
      expect(brandPublicPath('chez-lea', locale: 'ar'),
          '/public/brands/chez-lea?lang=ar');
    });
  });

  group('loadBrand', () {
    test('classe un 404 en notFound', () async {
      final result = await loadBrand(
        slug: 'inconnu',
        loader: (_) async => null,
      );

      expect(result.outcome, BrandLoadOutcome.notFound);
      expect(result.brand, isNull);
    });

    test('classe une réponse malformée en invalid', () async {
      final result = await loadBrand(
        slug: 'chez-lea',
        loader: (_) async => <String, dynamic>{
          'data': <String, dynamic>{'slug': 'chez-lea'}
        },
      );

      expect(result.outcome, BrandLoadOutcome.invalid);
    });

    test('classe une exception réseau en unavailable', () async {
      final result = await loadBrand(
        slug: 'chez-lea',
        loader: (_) async => throw Exception('timeout'),
      );

      expect(result.outcome, BrandLoadOutcome.unavailable);
    });

    test('charge une marque valide depuis l’enveloppe data', () async {
      final result = await loadBrand(
        slug: 'chez-lea',
        loader: (_) async => <String, dynamic>{'data': validDto()},
      );

      expect(result.outcome, BrandLoadOutcome.found);
      expect(result.brand!.identity.displayName, 'Chez Léa');
    });

    test('transmet la locale au chemin', () async {
      String? received;

      await loadBrand(
        slug: 'chez-lea',
        locale: 'ar',
        loader: (path) async {
          received = path;

          return null;
        },
      );

      expect(received, contains('lang=ar'));
    });
  });
}
