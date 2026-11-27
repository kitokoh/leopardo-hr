import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_surface/leopardo_surface.dart';

/// Garde d'identite et de routeur de **Leopardo Emploi**.
///
/// ## Le defaut que ces tests ferment
///
/// Une app scaffoldee par copie garde l'identite de l'app d'origine :
/// manifeste web, index.html, Info.plist et AndroidManifest nomment une AUTRE
/// app. C'est arrive a `leopardo_public`, qui se presentait comme le produit
/// RH (#9110). Huit apps generees depuis une source unique multiplient ce
/// risque par huit.
///
/// ## Pourquoi chaque assertion passe par une variable
///
/// `expect(x.contains('...'), isTrue, reason: '...')` tient sur une ligne ou
/// non selon la version du formateur : Dart 3.14 la garde, Dart 3.50 la coupe.
/// Le depot epingle 3.50 en CI, le poste de travail peut avoir autre chose —
/// et `dart format --set-exit-if-changed` rougit alors sur du code correct.
/// Calculer le booleen AVANT `expect` rend la mise en forme independante de la
/// longueur des chaines, donc stable sur les deux.
void main() {
  group('identite produit', () {
    test('le manifeste web nomme cette app', () {
      final manifest = File('web/manifest.json');
      expect(manifest.existsSync(), isTrue, reason: 'manifeste absent');

      final json = manifest.readAsStringSync();
      final hasName = json.contains('Leopardo Emploi');
      final hasPlaceholder = json.contains('A new Flutter project.');

      expect(hasName, isTrue, reason: 'nom incorrect');
      expect(hasPlaceholder, isFalse, reason: 'placeholder Flutter');
    });

    test('index.html porte le titre et la description', () {
      final index = File('web/index.html');
      expect(index.existsSync(), isTrue, reason: 'index.html absent');

      final html = index.readAsStringSync();
      final hasTitle = html.contains('<title>Leopardo Emploi');
      final hasPlaceholder = html.contains('A new Flutter project.');
      final hasDomain = html.contains('emploi.leopardo.app');

      expect(hasTitle, isTrue, reason: 'titre incorrect');
      expect(hasPlaceholder, isFalse, reason: 'placeholder Flutter');
      expect(hasDomain, isTrue, reason: 'domaine absent');
    });

    test('AndroidManifest porte le label et l applicationId', () {
      final manifest = File('android/app/src/main/AndroidManifest.xml');
      expect(manifest.existsSync(), isTrue, reason: 'manifeste absent');

      final xml = manifest.readAsStringSync();
      final hasLabel = xml.contains('android:label="Leopardo Emploi"');
      final hasAppId = xml.contains('com.leopardo.careers');

      expect(hasLabel, isTrue, reason: 'android:label incorrect');
      expect(hasAppId, isTrue, reason: 'applicationId absent');
    });

    test('le build Gradle declare l applicationId', () {
      final gradle = File('android/app/build.gradle.kts');
      expect(gradle.existsSync(), isTrue, reason: 'gradle absent');

      final kts = gradle.readAsStringSync();
      const appId = 'com.leopardo.careers';
      final hasAppId = kts.contains('applicationId = "$appId"');
      final hasNamespace = kts.contains('namespace = "$appId"');

      expect(hasAppId, isTrue, reason: 'applicationId incorrect');
      expect(hasNamespace, isTrue, reason: 'namespace incorrect');
    });

    test('Info.plist nomme cette app', () {
      final plist = File('ios/Runner/Info.plist');
      expect(plist.existsSync(), isTrue, reason: 'Info.plist absent');

      final xml = plist.readAsStringSync();
      final hasName = xml.contains('Leopardo Emploi');

      expect(hasName, isTrue, reason: 'nom incorrect');
    });
  });

  group('routeur de la verticale', () {
    test('la verticale servie est careers', () {
      final router = File('lib/app_router.dart');
      expect(router.existsSync(), isTrue, reason: 'routeur absent');

      final source = router.readAsStringSync();
      final hasVertical = source.contains('SurfaceVertical.careers');

      expect(hasVertical, isTrue, reason: 'verticale non montee');
    });

    test('l URL publique de la verticale est servie', () {
      // La route est produite par `surfaceDetailRoutes` du socle : on verifie
      // donc la FABRIQUE, pas une declaration locale. Une verticale dont la
      // route publique manquerait au socle ferait echouer ce test pour les
      // huit apps a la fois — c'est voulu.
      final routes = surfaceDetailRoutes(SurfaceVertical.careers);
      final paths =
          routes.whereType<GoRoute>().map((route) => route.path).toList();

      // Deux formes d'URL publique coexistent, et ce n'est pas un oubli :
      //   - les verticales a etablissement portent un slug
      //     (`/restaurants/le-bistrot`) ;
      //   - `travel` et `careers` n'ont pas d'etablissement : leur URL
      //     publique est la recherche elle-meme (`/billets`, `/emploi`), et
      //     l'identifiant eventuel est un sous-chemin (`/billets/42`).
      final hasSlugRoute = paths.contains('/emploi/:slug');
      final hasBaseRoute = paths.contains('/emploi');
      final hasPublicUrl = hasSlugRoute || hasBaseRoute;

      expect(hasPublicUrl, isTrue, reason: 'URL publique absente');
    });

    test('le registre connait la verticale et son domaine', () {
      final identity = surfaceIdentityFor(SurfaceVertical.careers);
      expect(identity, isNotNull, reason: 'identite absente');

      final domain = identity!.canonicalDomain;
      expect(domain, 'emploi.leopardo.app', reason: 'domaine divergent');
    });
  });
}
