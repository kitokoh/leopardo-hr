/// Moteur commun des applications mobiles grand public Leopardo.
///
/// ## Ce que ce package est
///
/// Le MOTEUR : découverte, fiches commerce, parcours transactionnels des huit
/// verticales, identités de vitrine, catalogue de chaînes, thème public.
///
/// ## Ce que ce package n'est PAS
///
/// Une application. Il ne contient ni `main.dart`, ni coque d'onglets, ni
/// routeur : ce sont exactement les trois choses qui doivent différer d'une
/// verticale à l'autre. Chaque app de verticale (`leopardo_restaurant_app`,
/// `leopardo_pharmacy_app`, …) est un assemblage mince qui apporte son
/// identité, son routeur et son point d'entrée.
///
/// ## Pourquoi un package et pas huit copies
///
/// Huit copies d'un moteur de ~6 800 lignes divergent au premier correctif. Le
/// dépôt a déjà payé ce prix une fois (`check-mobile-duplicated-drift.sh`,
/// #7091) et le garde existe pour l'empêcher. Le moteur vit donc ici, une
/// seule fois.
library;

// ── Configuration et thème ──────────────────────────────────────────────────
export 'src/core/config/app_config.dart';
export 'src/core/theme/public_theme.dart';

// ── Chaînes (catalogue local, 4 locales) ────────────────────────────────────
export 'src/core/i18n/app_strings.dart';

// ── Providers transverses ───────────────────────────────────────────────────
export 'src/core/providers/app_providers.dart';

// ── Localisation ────────────────────────────────────────────────────────────
export 'src/core/location/public_location.dart';

// ── Widgets d'état partagés ─────────────────────────────────────────────────
export 'src/core/widgets/state_widgets.dart';

// ── Identité de vitrine (domaine pur + pont tokens + thème) ─────────────────
export 'src/features/surface/domain/surface_identity.dart';
export 'src/features/surface/infrastructure/surface_palette_tokens.dart';
export 'src/features/surface/infrastructure/surface_theme.dart';

// ── Registre des verticales et de leurs parcours ────────────────────────────
export 'src/features/journeys/domain/journey_registry.dart';
export 'src/features/journeys/domain/restaurant_test_keys.dart';

// ── Écrans de parcours (une app de verticale en monte un ou deux) ───────────
export 'src/features/journeys/screens/careers_journey_screen.dart';
export 'src/features/journeys/screens/contact_request_screen.dart';
export 'src/features/journeys/screens/pharmacy_journey_screen.dart';
export 'src/features/journeys/screens/restaurant_order_screen.dart';
export 'src/features/journeys/screens/retail_order_screen.dart';
export 'src/features/journeys/screens/stay_reservation_screen.dart';
export 'src/features/journeys/screens/travel_journey_screen.dart';
export 'src/features/journeys/screens/vertical_directory_screen.dart';
export 'src/features/journeys/screens/vtc_journey_screen.dart';

// ── Fiche commerce et découverte ────────────────────────────────────────────
export 'src/features/business_profile/screens/business_profile_route.dart';
export 'src/features/discovery/screens/discovery_home_screen.dart';

// ── Onglets secondaires (Messages, Profil) ──────────────────────────────────
export 'src/features/tabs/secondary_tabs.dart';
export 'src/features/activity/screens/activity_tab.dart';

// ── Routeur partagé et coques ───────────────────────────────────────────────
export 'src/core/navigation/surface_router.dart';
export 'src/core/navigation/multi_vertical_shell.dart';
