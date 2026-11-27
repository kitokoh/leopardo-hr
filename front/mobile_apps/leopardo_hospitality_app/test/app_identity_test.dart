import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_surface/leopardo_surface.dart';

/// Garde d'identité et de routeur de **Leopardo Séjours**.
///
/// ## Le défaut que ces tests ferment
///
/// Une app scaffoldée par copie garde l'identité de l'app d'origine : manifeste
/// web, index.html, Info.plist et AndroidManifest nomment une AUTRE app. C'est
/// arrivé à `leopardo_public`, qui se présentait comme le produit RH (#9110).
/// Huit apps générées depuis une source unique multiplient ce risque par huit.
///
/// Ces tests vérifient, pour CETTE app :
///   - qu'elle se présente comme elle-même dans les quatre fichiers d'identité ;
///   - que son routeur sert bien la verticale `hospitality` et son URL publique ;
///   - qu'elle ne monte pas les routes d'une autre verticale.
void main() {{
  group('identité produit', () {{
    test('le manifeste web nomme cette app', () {{
      final manifest = File('web/manifest.json');
      expect(manifest.existsSync(), isTrue, reason: 'web/manifest.json absent');

      final json = manifest.readAsStringSync();
      expect(json.contains('Leopardo Séjours'), isTrue,
          reason: 'le manifeste web ne nomme pas « Leopardo Séjours »');
      expect(json.contains('A new Flutter project.'), isFalse,
          reason: 'le manifeste porte encore le placeholder Flutter');
    }});

    test('index.html porte le titre et la description de cette app', () {{
      final index = File('web/index.html');
      expect(index.existsSync(), isTrue, reason: 'web/index.html absent');

      final html = index.readAsStringSync();
      expect(html.contains('<title>Leopardo Séjours'), isTrue,
          reason: 'le <title> ne nomme pas « Leopardo Séjours »');
      expect(html.contains('A new Flutter project.'), isFalse,
          reason: 'index.html porte encore le placeholder Flutter');
      expect(html.contains('sejours.leopardo.app'), isTrue,
          reason: 'index.html ne déclare pas le domaine canonique sejours.leopardo.app');
    }});

    test('AndroidManifest porte le label et l\'applicationId de cette app', () {{
      final manifest = File('android/app/src/main/AndroidManifest.xml');
      expect(manifest.existsSync(), isTrue, reason: 'AndroidManifest absent');

      final xml = manifest.readAsStringSync();
      expect(xml.contains('android:label="Leopardo Séjours"'), isTrue,
          reason: 'android:label ne nomme pas « Leopardo Séjours »');
      expect(xml.contains('com.leopardo.hospitality'), isTrue,
          reason: 'le manifeste ne référence pas l\'applicationId com.leopardo.hospitality');
    }});

    test('le build Gradle déclare l\'applicationId de cette app', () {{
      final gradle = File('android/app/build.gradle.kts');
      expect(gradle.existsSync(), isTrue, reason: 'build.gradle.kts absent');

      final kts = gradle.readAsStringSync();
      expect(kts.contains('applicationId = "com.leopardo.hospitality"'), isTrue,
          reason: 'applicationId incorrect dans build.gradle.kts');
      expect(kts.contains('namespace = "com.leopardo.hospitality"'), isTrue,
          reason: 'namespace incorrect dans build.gradle.kts');
    }});

    test('Info.plist nomme cette app', () {{
      final plist = File('ios/Runner/Info.plist');
      expect(plist.existsSync(), isTrue, reason: 'Info.plist absent');

      final xml = plist.readAsStringSync();
      expect(xml.contains('Leopardo Séjours'), isTrue,
          reason: 'Info.plist ne nomme pas « Leopardo Séjours »');
    }});
  }});

  group('routeur de la verticale', () {{
    test('la verticale servie est hospitality', () {{
      final router = File('lib/app_router.dart');
      expect(router.existsSync(), isTrue, reason: 'lib/app_router.dart absent');

      final source = router.readAsStringSync();
      expect(source.contains('SurfaceVertical.hospitality'), isTrue,
          reason: 'le routeur ne monte pas la verticale hospitality');
    }});

    test('l\'URL publique de la verticale est servie', () {{
      // La route est produite par `surfaceDetailRoutes` du socle : on vérifie
      // donc la FABRIQUE, pas une déclaration locale. Une verticale dont la
      // route publique manquerait au socle ferait échouer ce test pour les
      // huit apps à la fois — c'est voulu.
      final routes = surfaceDetailRoutes(SurfaceVertical.hospitality);
      final paths = routes
          .whereType<GoRoute>()
          .map((route) => route.path)
          .toList();

      // Deux formes d'URL publique coexistent, et ce n'est pas un oubli :
      //   - les verticales à établissement portent un slug
      //     (`/restaurants/le-bistrot`) ;
      //   - `travel` et `careers` n'ont pas d'établissement : leur URL
      //     publique est la recherche elle-même (`/billets`, `/emploi`), et
      //     l'identifiant éventuel est un sous-chemin (`/billets/42`).
      // Exiger `:slug` partout ferait échouer ces deux verticales pour une
      // raison qui n'est pas un défaut.
      final hasSlugRoute = paths.contains('/stay/:slug');
      final hasBaseRoute = paths.contains('/stay');

      expect(hasSlugRoute || hasBaseRoute, isTrue,
          reason:
              'Aucune URL publique (/stay/:slug ou /stay) n\'est '
              'servie par le socle pour la verticale hospitality. Sans elle, un '
              'lien partagé depuis la vitrine web tombe sur le 404 de l\'app.');
    }});

    test('le registre connaît la verticale et son domaine', () {{
      final identity = surfaceIdentityFor(SurfaceVertical.hospitality);
      expect(identity, isNotNull,
          reason: 'aucune identité déclarée pour hospitality');
      expect(identity!.canonicalDomain, 'sejours.leopardo.app',
          reason: 'le domaine canonique a divergé du registre web');
    }});
  }});
}}
