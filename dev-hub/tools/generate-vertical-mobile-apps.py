#!/usr/bin/env python3
"""Génère les 8 applications mobiles grand public dédiées.

## Pourquoi un script et pas huit `flutter create`

`flutter create` produit un squelette neutre qu'il faudrait ensuite réécrire
huit fois à la main : identité produit (manifeste web, index.html, Info.plist,
AndroidManifest), applicationId, deep link, routeur, `main.dart`. Huit copies
manuelles divergent au premier oubli — c'est exactement le défaut que
`check-mobile-app-identity.sh` (#9110) a été écrit pour attraper : une app
scaffoldée par copie garde l'identité de l'app d'origine.

Ce script applique la MÊME transformation aux huit apps, à partir d'une source
unique (`leopardo_public` pour les dossiers plateforme). Une app ajoutée plus
tard se génère en ajoutant une entrée à `APPS`.

## Ce que le script ne fait PAS

Il ne touche pas au moteur : celui-ci vit dans `leopardo_surface` et chaque app
ne fait que l'assembler. Le script ne duplique donc aucune ligne de logique
métier — seulement les dossiers plateforme (android/ios/web), qui sont
inévitablement par app.
"""
from __future__ import annotations

import json
import pathlib
import re
import shutil

ROOT = pathlib.Path(__file__).resolve().parents[2] / "front" / "mobile_apps"
SOURCE = ROOT / "leopardo_public"

# ── Les huit verticales ─────────────────────────────────────────────────────
# `vertical` est le code canonique du registre (`SurfaceVertical.code`), donc
# identique au web (`front/web/src/modules/public-surfaces/<vertical>/`).
APPS = [
    {
        "vertical": "restaurant",
        "package": "leopardo_restaurant_app",
        "app_id": "com.leopardo.restaurant",
        "label": "Leopardo Restaurants",
        "short": "Restaurants",
        "description": "Trouvez votre table, découvrez la carte, commandez et suivez votre commande.",
        "tagline": "Trouvez votre table, découvrez la carte, commandez en deux gestes.",
        "domain": "restaurants.leopardo.app",
        "deep_host": "restaurant",
        "primary": "#C2410C",
    },
    {
        "vertical": "retail",
        "package": "leopardo_retail_app",
        "app_id": "com.leopardo.retail",
        "label": "Leopardo Marché",
        "short": "Marché",
        "description": "Les boutiques près de chez vous, leur catalogue et leurs nouveautés.",
        "tagline": "Les boutiques près de chez vous, leur catalogue et leurs nouveautés.",
        "domain": "marche.leopardo.app",
        "deep_host": "retail",
        "primary": "#4338CA",
    },
    {
        "vertical": "hospitality",
        "package": "leopardo_hospitality_app",
        "app_id": "com.leopardo.hospitality",
        "label": "Leopardo Séjours",
        "short": "Séjours",
        "description": "Hôtels, résidences et maisons d'hôtes — réservation directe, sans intermédiaire.",
        "tagline": "Hôtels, résidences et maisons d'hôtes — réservation directe.",
        "domain": "sejours.leopardo.app",
        "deep_host": "stay",
        "primary": "#0F766E",
    },
    {
        "vertical": "trade",
        "package": "leopardo_trade_app",
        "app_id": "com.leopardo.trade",
        "label": "Leopardo Entreprises",
        "short": "Entreprises",
        "description": "Fournisseurs et négociants vérifiés — import, export et marché domestique.",
        "tagline": "Fournisseurs et négociants vérifiés — import, export et marché domestique.",
        "domain": "entreprises.leopardo.app",
        "deep_host": "trade",
        "primary": "#1E3A8A",
    },
    {
        "vertical": "travel",
        "package": "leopardo_travel_app",
        "app_id": "com.leopardo.travel",
        "label": "Leopardo Billets",
        "short": "Billets",
        "description": "Comparez les trajets, réservez votre place, recevez votre billet.",
        "tagline": "Comparez les trajets, réservez votre place, recevez votre billet.",
        "domain": "billets.leopardo.app",
        "deep_host": "travel",
        "primary": "#6D28D9",
    },
    {
        "vertical": "pharmacy",
        "package": "leopardo_pharmacy_app",
        "app_id": "com.leopardo.pharmacy",
        "label": "Leopardo Pharmacies",
        "short": "Pharmacies",
        "description": "Trouvez une officine, ses horaires et sa garde, près de chez vous.",
        "tagline": "Trouvez une officine, ses horaires et sa garde, près de chez vous.",
        "domain": "pharmacies.leopardo.app",
        "deep_host": "pharmacy",
        "primary": "#047857",
    },
    {
        "vertical": "vtc",
        "package": "leopardo_mobility_app",
        "app_id": "com.leopardo.mobility",
        "label": "Leopardo Mobilité",
        "short": "Mobilité",
        "description": "Opérateurs de transport vérifiés — demandez un trajet et suivez-le.",
        "tagline": "Opérateurs de transport vérifiés — demandez un trajet.",
        "domain": "mobilite.leopardo.app",
        "deep_host": "mobility",
        "primary": "#1F2937",
    },
    {
        "vertical": "careers",
        "package": "leopardo_careers_app",
        "app_id": "com.leopardo.careers",
        "label": "Leopardo Emploi",
        "short": "Emploi",
        "description": "Les offres des entreprises près de chez vous, et comment postuler.",
        "tagline": "Les offres des entreprises près de chez vous, et comment postuler.",
        "domain": "emploi.leopardo.app",
        "deep_host": "careers",
        "primary": "#166534",
    },
]

PUBSPEC = """name: {package}
description: "{label} — application mobile grand public dédiée à la verticale {vertical}."
publish_to: 'none'

version: 1.0.0+1

environment:
  sdk: '>=3.3.0 <4.0.0'

# ## Une app, une verticale
#
# Cette application sert UNE verticale et rien d'autre. Elle n'embarque aucune
# logique métier : le moteur (découverte, fiches, parcours, identités) vit dans
# `leopardo_surface`, partagé par les huit apps. Ce qui est propre à cette app
# tient en trois fichiers : `main.dart`, `app_router.dart`, et son identité
# produit (manifeste, Info.plist, AndroidManifest).
#
# ## Pourquoi ce n'est pas une copie de leopardo_public
#
# `leopardo_public` reste l'app MULTI-VERTICALES : elle sert le visiteur qui ne
# sait pas encore ce qu'il cherche. Cette app sert celui qui sait déjà — il
# arrive par `{domain}` ou par un QR code, et il ne doit pas se demander s'il
# est au bon endroit.
dependencies:
  flutter:
    sdk: flutter
  flutter_localizations:
    sdk: flutter
  material_ui: ^1.0.0
  cupertino_ui: ^1.0.2
  cupertino_icons: ^1.0.9
  flutter_riverpod: ^3.4.2
  go_router: ^18.0.1
  intl: ^0.20.2
  url_strategy: ^0.3.0
  leopardo_core:
    path: ../leopardo_core
  leopardo_surface:
    path: ../leopardo_surface

dev_dependencies:
  flutter_test:
    sdk: flutter
  flutter_lints: ^6.0.0

flutter:
  uses-material-design: true

dependency_overrides:
  xml: ^7.0.1
  uuid: ^4.6.0
"""

MAIN = """import 'dart:async';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:material_ui/material_ui.dart';
import 'package:cupertino_ui/cupertino_ui.dart'
    show GlobalCupertinoLocalizations;
import 'package:flutter_localizations/flutter_localizations.dart'
    hide GlobalMaterialLocalizations, GlobalCupertinoLocalizations;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:leopardo_core/core/i18n/device_locale.dart';
import 'package:leopardo_core/core/widgets/startup_gate.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_surface/leopardo_surface.dart';
import 'package:url_strategy/url_strategy.dart';

import 'app_router.dart';

/// Entrée de **{label}** — application dédiée à la verticale `{vertical}`.
///
/// ## Ce que cette app est
///
/// Un ASSEMBLAGE. Le moteur (découverte, fiches, parcours, identités de
/// vitrine) vit dans `leopardo_surface` et est partagé par les huit apps
/// grand public. Ce fichier ne fait que trois choses : poser la stratégie
/// d'URL web, initialiser les locales, monter le routeur de la verticale.
///
/// ## Aucun écran de connexion
///
/// Le parcours critique est « ouverture → recherche → fiche → commander ». Le
/// compte n'est demandé qu'à la dernière étape. Un garde de route au démarrage
/// forcerait une inscription pour consulter la fiche d'un restaurant trouvée
/// par QR code — ce serait contredire la promesse produit.
///
/// ## Anti page noire
///
/// `runApp` est appelé IMMÉDIATEMENT ; toute initialisation bloquante passe par
/// `StartupGate`, qui applique un timeout et continue en mode dégradé plutôt
/// que de laisser un écran noir.
///
/// ## Pourquoi la stratégie d'URL est posée AVANT `runApp`
///
/// Par défaut, Flutter web rend chaque route en `/#/restaurants/le-bistrot` :
/// le fragment n'est jamais envoyé au serveur, donc aucun crawler ne voit
/// l'URL. `setPathUrlStrategy` fait lire/écrire le chemin réel, ce qui est la
/// condition pour que `{domain}` soit indexable.
void main() {
  WidgetsFlutterBinding.ensureInitialized();

  if (kIsWeb) {
    setPathUrlStrategy();
  }

  FlutterError.onError = (details) {
    FlutterError.presentError(details);
  };

  runApp(
    const ProviderScope(
      child: StartupGate(
        appName: '{label}',
        initializer: _bootstrap,
        criticalInitializer: _criticalBootstrap,
        optionalInitializer: _optionalBootstrap,
        child: {class_name}App(),
      ),
    ),
  );
}

Future<void> _bootstrap() async {
  // Aucune authentification à restaurer : l'app est consultable sans compte.
}

Future<void> _criticalBootstrap() async {
  // Les 4 locales de l'app. `initializeDateFormatting` est indispensable pour
  // que les dates (horaires, prochaine ouverture) ne lèvent pas une
  // LocalizationError sur fr/ar/tr — le défaut d'`intl` ne connaît que `en_US`.
  await initializeDateFormatting(deviceIntlDateLocale, null);
  await initializeDateFormatting('fr', null);
  await initializeDateFormatting('fr_FR', null);
  await initializeDateFormatting('ar', null);
  await initializeDateFormatting('tr', null);
  await initializeDateFormatting('en', null);
  await initializeDateFormatting('en_US', null);
}

Future<void> _optionalBootstrap() async {
  // Réservé : rien n'est critique au-delà des locales.
}

class {class_name}App extends ConsumerWidget {
  const {class_name}App({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(appRouterProvider);
    final strings = ref.watch(appStringsProvider);

    return MaterialApp.router(
      title: '{label}',
      // Thème PROPRE à l'app grand public, aligné sur le portail web. Le thème
      // de la verticale (palette, contraste AA) est appliqué par les écrans du
      // socle via `surfaceTheme(identity)` — ici on pose la base commune.
      theme: PublicTheme.light(),
      darkTheme: PublicTheme.dark(),
      themeMode: ThemeMode.system,
      routerConfig: router,
      debugShowCheckedModeBanner: false,
      // La locale suit le catalogue : c'est le MÊME objet qui décide des textes
      // et de la locale Material, donc les libellés système ne peuvent pas être
      // dans une autre langue que l'interface.
      locale: Locale(strings.locale),
      supportedLocales: const [
        Locale('fr'),
        Locale('en'),
        Locale('ar'),
        Locale('tr'),
      ],
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
    );
  }
}
"""

ROUTER = """import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_surface/leopardo_surface.dart';

/// Routeur de **{label}** — une seule verticale.
///
/// ## Ce que ce fichier ne fait pas
///
/// Il n'écrit AUCUNE route à la main. `buildSurfaceRouter` produit la coque à
/// 4 onglets, la fiche commerce hors coque, l'URL publique de la verticale
/// (`{base_path}/{{slug}}`) et son alias interne, plus le 404. C'est ce qui
/// garantit qu'une URL publique ajoutée au socle apparaît ici
/// automatiquement — et qu'elle ne peut pas manquer dans cette app.
///
/// ## `initialLocation: '/'`
///
/// Sur un domaine dédié (`{domain}`), la racine EST l'accueil de la verticale.
/// L'app multi-verticales conserve `/home` pour ne pas casser ses liens
/// existants ; ici il n'y a rien à préserver.
final appRouterProvider = Provider<GoRouter>((ref) {{
  return buildSurfaceRouter(
    vertical: SurfaceVertical.{vertical},
    initialLocation: '/',
  );
}});
"""

TEST = """import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_surface/leopardo_surface.dart';

/// Garde d'identite et de routeur de **{label}**.
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
      final hasName = json.contains('{label}');
      final hasPlaceholder = json.contains('A new Flutter project.');

      expect(hasName, isTrue, reason: 'nom incorrect');
      expect(hasPlaceholder, isFalse, reason: 'placeholder Flutter');
    });

    test('index.html porte le titre et la description', () {
      final index = File('web/index.html');
      expect(index.existsSync(), isTrue, reason: 'index.html absent');

      final html = index.readAsStringSync();
      final hasTitle = html.contains('<title>{label}');
      final hasPlaceholder = html.contains('A new Flutter project.');
      final hasDomain = html.contains('{domain}');

      expect(hasTitle, isTrue, reason: 'titre incorrect');
      expect(hasPlaceholder, isFalse, reason: 'placeholder Flutter');
      expect(hasDomain, isTrue, reason: 'domaine absent');
    });

    test('AndroidManifest porte le label et l applicationId', () {
      final manifest = File('android/app/src/main/AndroidManifest.xml');
      expect(manifest.existsSync(), isTrue, reason: 'manifeste absent');

      final xml = manifest.readAsStringSync();
      final hasLabel = xml.contains('android:label="{label}"');
      final hasAppId = xml.contains('{app_id}');

      expect(hasLabel, isTrue, reason: 'android:label incorrect');
      expect(hasAppId, isTrue, reason: 'applicationId absent');
    });

    test('le build Gradle declare l applicationId', () {
      final gradle = File('android/app/build.gradle.kts');
      expect(gradle.existsSync(), isTrue, reason: 'gradle absent');

      final kts = gradle.readAsStringSync();
      final hasAppId = kts.contains('applicationId = "{app_id}"');
      final hasNamespace = kts.contains('namespace = "{app_id}"');

      expect(hasAppId, isTrue, reason: 'applicationId incorrect');
      expect(hasNamespace, isTrue, reason: 'namespace incorrect');
    });

    test('Info.plist nomme cette app', () {
      final plist = File('ios/Runner/Info.plist');
      expect(plist.existsSync(), isTrue, reason: 'Info.plist absent');

      final xml = plist.readAsStringSync();
      final hasName = xml.contains('{label}');

      expect(hasName, isTrue, reason: 'nom incorrect');
    });
  });

  group('routeur de la verticale', () {
    test('la verticale servie est {vertical}', () {
      final router = File('lib/app_router.dart');
      expect(router.existsSync(), isTrue, reason: 'routeur absent');

      final source = router.readAsStringSync();
      final hasVertical = source.contains('SurfaceVertical.{vertical}');

      expect(hasVertical, isTrue, reason: 'verticale non montee');
    });

    test('l URL publique de la verticale est servie', () {
      // La route est produite par `surfaceDetailRoutes` du socle : on verifie
      // donc la FABRIQUE, pas une declaration locale. Une verticale dont la
      // route publique manquerait au socle ferait echouer ce test pour les
      // huit apps a la fois — c'est voulu.
      final routes = surfaceDetailRoutes(SurfaceVertical.{vertical});
      final paths =
          routes.whereType<GoRoute>().map((route) => route.path).toList();

      // Deux formes d'URL publique coexistent, et ce n'est pas un oubli :
      //   - les verticales a etablissement portent un slug
      //     (`/restaurants/le-bistrot`) ;
      //   - `travel` et `careers` n'ont pas d'etablissement : leur URL
      //     publique est la recherche elle-meme (`/billets`, `/emploi`), et
      //     l'identifiant eventuel est un sous-chemin (`/billets/42`).
      final hasSlugRoute = paths.contains('{base_path}/:slug');
      final hasBaseRoute = paths.contains('{base_path}');
      final hasPublicUrl = hasSlugRoute || hasBaseRoute;

      expect(hasPublicUrl, isTrue, reason: 'URL publique absente');
    });

    test('le registre connait la verticale et son domaine', () {
      final identity = surfaceIdentityFor(SurfaceVertical.{vertical});
      expect(identity, isNotNull, reason: 'identite absente');

      final domain = identity!.canonicalDomain;
      expect(domain, '{domain}', reason: 'domaine divergent');
    });
  });
}
"""


def render(template: str, **values: str) -> str:
    """Substitue `{clé}` sans interpréter les accolades Dart.

    `str.format` traiterait `{super.key}`, `void main() {` ou `${{...}}` comme
    des champs et lèverait « unexpected '{' in field name ». Les templates
    contiennent du Dart réel : on ne remplace donc QUE les clés connues.
    """
    out = template
    for key, value in values.items():
        out = out.replace("{" + key + "}", str(value))
    return out


def write(path: pathlib.Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


def format_dart(target: pathlib.Path) -> None:
    """Formate le Dart généré, pour ne pas produire un dépôt non conforme.

    Les templates sont écrits à la main ; `dart format` a des regles precises
    (retours a la ligne, virgules finales) qu'aucun template ne devine. Sans
    cette etape, chaque generation recree un diff de format et fait echouer
    `dart format --set-exit-if-changed` en CI. Le formateur est donc appele
    ICI, une fois, plutot que corrige a la main dans huit templates.
    """
    import shutil
    import subprocess

    dart = shutil.which("dart") or shutil.which("flutter")
    if dart is None:
        print("   (dart introuvable : le Dart genere ne sera pas formate)")
        return

    for sub in ("lib", "test"):
        directory = target / sub
        if not directory.exists():
            continue
        subprocess.run(
            [dart, "format", str(directory)],
            check=False,
            capture_output=True,
        )


def copy_platform_dirs(target: pathlib.Path) -> None:
    """Copie les dossiers plateforme depuis leopardo_public.

    `android/`, `ios/`, `web/` sont inévitablement par app (un manifeste, un
    Info.plist, un index.html). Les copier puis réécrire l'identité est le
    patron du dépôt ; le garde `check-mobile-app-identity.sh` vérifie que la
    réécriture a bien eu lieu.
    """
    for name in ("android", "ios", "web"):
        src = SOURCE / name
        dst = target / name
        if dst.exists():
            shutil.rmtree(dst)
        shutil.copytree(src, dst)

    # Le dossier `build/` et les artefacts ne doivent jamais être copiés.
    for junk in ("build", ".dart_tool"):
        if (target / junk).exists():
            shutil.rmtree(target / junk)

    # Le README de l'écran de lancement iOS est du boilerplate Flutter, pas de
    # la documentation de projet. Le budget documentaire du dépôt est gelé
    # (`check-docs-inventory.py`, #9981) : huit copies de ce fichier le
    # dépasseraient de huit, et le garde a raison — un README qui explique
    # comment remplacer une image n'est pas une décision d'architecture.
    launch_readme = (
        target
        / "ios/Runner/Assets.xcassets/LaunchImage.imageset/README.md"
    )
    if launch_readme.exists():
        launch_readme.unlink()


def rewrite_android(target: pathlib.Path, app: dict) -> None:
    app_id = app["app_id"]
    label = app["label"]
    deep_host = app["deep_host"]

    gradle = target / "android/app/build.gradle.kts"
    text = gradle.read_text(encoding="utf-8")
    text = text.replace("com.leopardo.publicapp", app_id)
    gradle.write_text(text, encoding="utf-8")

    manifest = target / "android/app/src/main/AndroidManifest.xml"
    text = manifest.read_text(encoding="utf-8")
    text = text.replace("com.leopardo.publicapp", app_id)
    text = text.replace('android:label="Leopardo"', f'android:label="{label}"')
    # Deep link : `leopardo://<vertical>/<route>`. Un host par verticale, pour
    # qu'un QR code de restaurant n'ouvre pas l'app pharmacie.
    text = text.replace(
        '<data android:scheme="leopardo" android:host="public"/>',
        f'<data android:scheme="leopardo" android:host="{deep_host}"/>',
    )
    manifest.write_text(text, encoding="utf-8")

    # MainActivity : le package Kotlin doit suivre l'applicationId, sinon
    # `flutter build apk` échoue sur « class not found ».
    old_dir = target / "android/app/src/main/kotlin/com/leopardo/publicapp"
    new_dir = target / "android/app/src/main/kotlin" / app_id.replace(".", "/")
    if old_dir.exists():
        new_dir.parent.mkdir(parents=True, exist_ok=True)
        shutil.move(str(old_dir), str(new_dir))
        activity = new_dir / "MainActivity.kt"
        if activity.exists():
            text = activity.read_text(encoding="utf-8")
            text = text.replace("com.leopardo.publicapp", app_id)
            activity.write_text(text, encoding="utf-8")
        # Nettoie les dossiers vides laissés par le déplacement.
        for parent in (old_dir.parent, old_dir.parent.parent):
            if parent.exists() and not any(parent.iterdir()):
                parent.rmdir()


def rewrite_ios(target: pathlib.Path, app: dict) -> None:
    app_id = app["app_id"]
    label = app["label"]

    plist = target / "ios/Runner/Info.plist"
    text = plist.read_text(encoding="utf-8")
    text = re.sub(
        r"(<key>CFBundleName</key>\s*<string>)[^<]*(</string>)",
        rf"\g<1>{label}\g<2>",
        text,
    )
    text = re.sub(
        r"(<key>CFBundleDisplayName</key>\s*<string>)[^<]*(</string>)",
        rf"\g<1>{label}\g<2>",
        text,
    )
    plist.write_text(text, encoding="utf-8")

    pbx = target / "ios/Runner.xcodeproj/project.pbxproj"
    text = pbx.read_text(encoding="utf-8")
    text = text.replace("com.leopardo.publicapp", app_id)
    pbx.write_text(text, encoding="utf-8")


BOOTSTRAP = """/**
 * Enrichissement social des fiches de **{label}**.
 *
 * ## Le problème que ce script résout — et celui qu'il ne résout PAS
 *
 * Flutter web rend l'application dans un `<canvas>` : le HTML de la page ne
 * contient jamais le nom de l'établissement. Pour un lien
 * `{base_path}/le-bistrot` partagé sur WhatsApp, la carte sociale (Open Graph)
 * et l'onglet du navigateur afficheraient donc le libellé statique générique.
 *
 * Ce script lit le chemin et remplace `document.title`, `og:title`,
 * `og:description` par un libellé dérivé du slug.
 *
 * **Ce qu'il ne fait pas :** ce n'est pas du rendu serveur. Les robots qui
 * n'exécutent pas JavaScript verront encore les balises statiques
 * d'`index.html`. Pour une vraie carte sociale par établissement, il faut un
 * rendu serveur du `<head>` — c'est le rôle de la vitrine Next.js
 * (`front/web/src/app/{web_path}`), déjà en place. Ce script améliore le titre
 * côté navigateur et l'aperçu des crawlers qui exécutent JS.
 *
 * ## Pourquoi une seule verticale ici
 *
 * L'app multi-verticales (`leopardo_public`) porte une table de quatre
 * verticales. Cette app n'en sert qu'UNE : la table d'une entrée rend le
 * contrat explicite — un chemin d'une autre verticale n'est pas reconnu, et
 * l'app ne prétend pas le servir.
 */
(function () {
  'use strict';

  var VERTICAL = {
    prefix: '{base_path}/',
    title: '{name} — {short} | {label}',
    description:
      '{tagline} {name} sur {label}.',
    numeric: {numeric}
  };

  /** Segment de chemin qui suit `prefix`, ou `null` si le chemin ne correspond pas. */
  function segmentAfter(prefix) {
    var path = window.location.pathname || '';
    var index = path.indexOf(prefix);
    if (index === -1) return null;

    var rest = path.slice(index + prefix.length);
    var segment = rest.split('/')[0];
    if (!segment) return null;

    try {
      segment = decodeURIComponent(segment);
    } catch (error) {
      // Segment mal encodé : on garde la valeur brute plutôt que de lever.
    }

    return segment.trim() || null;
  }

  /** `le-bistrot-chez-paul` → `Le Bistrot Chez Paul`. */
  function humanize(slug) {
    return slug
      .replace(/[-_]+/g, ' ')
      .replace(/\\s+/g, ' ')
      .trim()
      .replace(/\\b\\w/g, function (letter) {
        return letter.toUpperCase();
      });
  }

  function setMeta(selector, content) {
    var element = document.querySelector(selector);
    if (!element) return;

    var previous = element.getAttribute('content') || '';
    // Un libellé de plus de 60 caractères n'est pas un slug légitime : on ne
    // remplace pas une description soignée par du bruit.
    if (content.length > 60) return;
    if (previous === content) return;

    element.setAttribute('content', content);
  }

  function run() {
    var segment = segmentAfter(VERTICAL.prefix);
    if (!segment) return;

    var name = VERTICAL.numeric ? segment : humanize(segment);
    var title = VERTICAL.title.replace('{name}', name);
    var description = VERTICAL.description.replace('{name}', name);

    document.title = title;
    setMeta('meta[property="og:title"]', title);
    setMeta('meta[property="og:description"]', description);
    setMeta('meta[name="twitter:title"]', title);
    setMeta('meta[name="description"]', description);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
"""


def rewrite_web(target: pathlib.Path, app: dict) -> None:
    label = app["label"]
    short = app["short"]
    description = app["description"]
    tagline = app["tagline"]
    domain = app["domain"]
    primary = app["primary"]
    base_path = base_path_for(app["vertical"])

    manifest = target / "web/manifest.json"
    data = json.loads(manifest.read_text(encoding="utf-8"))
    data["name"] = label
    data["short_name"] = short
    data["description"] = description
    # La couleur de marque de la verticale, pas le bleu nuit générique : une
    # app dédiée qui garde la couleur de l'app multi-verticales ne se
    # distingue pas sur l'écran d'accueil.
    data["theme_color"] = primary
    data["background_color"] = primary
    manifest.write_text(
        json.dumps(data, ensure_ascii=False, indent=4) + "\n", encoding="utf-8"
    )

    index = target / "web/index.html"
    text = index.read_text(encoding="utf-8")
    text = re.sub(r"<title>[^<]*</title>", f"<title>{label}</title>", text)
    text = re.sub(
        r'(<meta name="apple-mobile-web-app-title" content=")[^"]*(")',
        rf"\g<1>{short}\g<2>",
        text,
    )
    text = re.sub(
        r'(<meta name="description" content=")[^"]*(")',
        rf"\g<1>{description}\g<2>",
        text,
    )
    text = re.sub(
        r'(<meta name="theme-color" content=")[^"]*(")',
        rf"\g<1>{primary}\g<2>",
        text,
    )
    text = re.sub(
        r'(<meta property="og:title" content=")[^"]*(")',
        rf"\g<1>{label} — {tagline}\g<2>",
        text,
    )
    text = re.sub(
        r'(<meta property="og:description" content=")[^"]*(")',
        rf"\g<1>{description}\g<2>",
        text,
    )
    text = re.sub(
        r'(<meta name="twitter:title" content=")[^"]*(")',
        rf"\g<1>{label} — {tagline}\g<2>",
        text,
    )
    text = re.sub(
        r'(<meta name="twitter:description" content=")[^"]*(")',
        rf"\g<1>{description}\g<2>",
        text,
    )
    # Domaine canonique de la verticale : c'est lui qui doit apparaître dans
    # `og:url`, pas le domaine de l'app multi-verticales.
    text = text.replace("https://leopardo.app", f"https://{domain}")
    text = text.replace(
        '<meta property="og:locale" content="fr_FR">',
        f'<meta property="og:locale" content="fr_FR">\n'
        f'  <meta property="og:url" content="https://{domain}">',
    )
    # Le bootstrap de l'app multi-verticales couvre quatre verticales ; cette
    # app n'en sert qu'une, elle a donc le sien.
    text = text.replace(
        "vertical_web_bootstrap.js", "vertical_web_bootstrap.js"
    )
    index.write_text(text, encoding="utf-8")

    write(
        target / "web/vertical_web_bootstrap.js",
        render(
            BOOTSTRAP,
            **app,
            base_path=base_path,
            numeric="true" if app["vertical"] == "travel" else "false",
            web_path={
                "restaurant": "restaurants",
                "retail": "boutiques",
                "hospitality": "stay",
                "trade": "entreprises",
                "travel": "billets",
                "pharmacy": "pharmacies",
                "vtc": "operateurs",
                "careers": "emploi",
            }[app["vertical"]],
        ),
    )


def main() -> None:
    for app in APPS:
        target = ROOT / app["package"]
        class_name = "".join(
            part.capitalize() for part in app["package"].replace("_app", "").split("_")
        )

        copy_platform_dirs(target)

        write(target / "pubspec.yaml", render(PUBSPEC, **app))
        write(
            target / "analysis_options.yaml",
            (SOURCE / "analysis_options.yaml").read_text(encoding="utf-8"),
        )
        write(
            target / "lib/main.dart",
            render(MAIN, **app, class_name=class_name),
        )
        write(
            target / "lib/app_router.dart",
            render(ROUTER, **app, base_path=base_path_for(app["vertical"])),
        )
        write(
            target / "test/app_identity_test.dart",
            render(TEST, **app, base_path=base_path_for(app["vertical"])),
        )

        rewrite_android(target, app)
        rewrite_ios(target, app)
        rewrite_web(target, app)

        format_dart(target)

        print(f"✅ {app['package']} ({app['vertical']})")


def base_path_for(vertical: str) -> str:
    """Chemin public de la verticale — identique au registre web."""
    return {
        "restaurant": "/restaurants",
        "retail": "/boutiques",
        "hospitality": "/stay",
        "trade": "/entreprises",
        "travel": "/billets",
        "pharmacy": "/pharmacies",
        "vtc": "/operateurs",
        "careers": "/emploi",
    }[vertical]


if __name__ == "__main__":
    main()
