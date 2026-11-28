/**
 * REGISTRE DES VERTICALES GRAND PUBLIC.
 *
 * ## Pourquoi un registre et pas une liste dans un ecran
 *
 * Une app qui se verifie ISOLEMENT ne voit jamais ce qui lui manque : elle
 * analyse, elle teste, et elle est incomplete. Ici le defaut prendrait la
 * forme « huit verticales sur neuf ont un parcours, la neuvieme n'en a pas et
 * personne ne le sait ». Le registre rend l'oubli MESURABLE : chaque verticale
 * doit y figurer, et chaque capacite absente doit porter un MOTIF textuel
 * plutot que disparaitre silencieusement.
 *
 * ## Les modes, et ce qu'ils veulent dire
 *
 * - `order`   : l'app sait commander de bout en bout, sans jeton ni compte ;
 * - `booking` : l'app sait reserver de bout en bout, sans jeton ni compte ;
 * - `request` : l'app sait envoyer une demande via `POST /public/contact-requests`
 *               (le seul contrat universel cote serveur) ;
 * - `blocked` : aucun contrat public appelable. L'ecran doit dire POURQUOI
 *               (cle de `blockers`) au lieu d'afficher un bouton mort.
 *
 * ## Ce que le registre ne decide PAS
 *
 * Il ne declare jamais qu'une capacite existe parce qu'elle « devrait »
 * exister. Chaque mode ci-dessous est mesure sur `api/routes` du depot
 * `leopardo-hr` — voir le commentaire de chaque entree, qui cite la route
 * exacte. Une verticale dont le contrat public n'existe pas est `blocked`,
 * avec son motif.
 */

/** Nature du parcours disponible depuis l'application grand public. */
export type JourneyMode = 'order' | 'booking' | 'request' | 'blocked';

/** Une capacite absente, avec le motif exact a afficher. */
export type JourneyBlocker = {
  /** Capacite concernee (`order`, `booking`, `quote`, `ride`, `catalog`, `contact`). */
  capability: string;
  /** Motif lisible, affiche tel quel a l'utilisateur. */
  reason: string;
};

/** Une verticale, son contrat public et ce qui manque. */
export type JourneyVertical = {
  /** Code stable (`restaurant`, `retail`, ...). */
  code: string;
  /** Nom affiche. */
  label: string;
  /** Sous-titre commercial (ce que le public vient y faire). */
  tagline: string;
  /** Glyphe de la tuile d'accueil. */
  glyph: string;
  /** Couleur d'accent de la verticale. */
  accent: string;
  /** Contrepartie web, pour la cartographie (jamais utilisee pour naviguer). */
  counterpartPath: string;
  mode: JourneyMode;
  /**
   * Type renvoye par `GET /public/discover` (`meta.actions`), ou `null` si la
   * verticale n'est pas exposee par la decouverte.
   */
  discoveryType: string | null;
  /**
   * Type accepte par `POST /public/contact-requests` ; `null` quand aucun
   * resolver public n'existe (une demande serait refusee en 422).
   */
  contactTargetType: string | null;
  blockers: JourneyBlocker[];
};

/**
 * Types REELLEMENT acceptes par `POST /public/contact-requests`.
 *
 * Mesure sur `PublicProfileLocator::types()`, qui agrege les
 * `PublicProfileResolver` des verticales : `hospitality_property`,
 * `restaurant`, `retail_shop`, `showcase`, `trade_company`, `vtc_operator`.
 *
 * `pharmacy` n'y figure PAS — une demande pour une pharmacie est refusee en
 * 422, donc l'app ne doit pas la proposer.
 */
export const CONTACT_TARGET_TYPES: ReadonlySet<string> = new Set([
  'hospitality_property',
  'restaurant',
  'retail_shop',
  'showcase',
  'trade_company',
  'vtc_operator',
]);

/**
 * Les 9 verticales du grand public, dans l'ordre de la grille d'accueil.
 *
 * L'ordre suit `VERTICALS` du web (`front/web/src/lib/client-features.ts`)
 * pour que le client retrouve la meme taxonomie que sur le site.
 */
export const JOURNEY_VERTICALS: JourneyVertical[] = [
  {
    code: 'restaurant',
    label: 'Restaurants',
    tagline: 'Commander a emporter ou en livraison',
    glyph: '\u25CF',
    accent: '#F97316',
    counterpartPath: '/restaurants',
    // RESTO-902 (#7747) — surface publique PAR SLUG, sans jeton :
    //   GET  /public/restaurants/{slug}            -> profil + menu
    //   POST /public/restaurants/{slug}/orders     -> commande
    //   GET  /public/restaurants/{slug}/orders/{ref}?secret= -> suivi
    //   POST /public/restaurants/{slug}/orders/{ref}/pay     -> paiement
    // C'est exactement ce que consomme la vitrine web `/restaurants/{slug}`.
    mode: 'order',
    discoveryType: 'restaurant',
    contactTargetType: 'restaurant',
    blockers: [],
  },
  {
    // Code aligne sur `BusinessVertical` du web (`commerce`), pas `retail` :
    // le module s'appelle `commerce`, la verticale aussi.
    code: 'commerce',
    label: 'Boutiques',
    tagline: 'Acheter en ligne, livraison a domicile',
    glyph: '\u25A0',
    accent: '#8B5CF6',
    counterpartPath: '/boutiques',
    // `market_public.php` : GET /public/market/products?seller=,
    // POST /public/market/orders, GET /public/market/orders/{ref}?token=.
    mode: 'order',
    discoveryType: 'retail_shop',
    contactTargetType: 'retail_shop',
    blockers: [],
  },
  {
    code: 'hospitality',
    label: 'Sejours',
    tagline: 'Hotels, residences, gestion locative',
    glyph: '\u25A1',
    accent: '#14B8A6',
    counterpartPath: '/stay',
    // `hospitality_manager.php` : GET /public/hospitality/properties/{slug}/availability,
    // POST .../reservations, GET /public/hospitality/reservations/{ref}?code=.
    mode: 'booking',
    discoveryType: 'hospitality_property',
    contactTargetType: 'hospitality_property',
    blockers: [],
  },
  {
    code: 'travel',
    label: 'Billets',
    tagline: 'Trajets inter-villes, reservation et e-billet',
    glyph: '\u2708',
    accent: '#0EA5E9',
    counterpartPath: '/billets',
    // `api.php` : GET /public/travel/marketplace/cities|trips|trips/{id},
    // POST /public/travel/marketplace/bookings, GET .../bookings/{ref}?code=,
    // POST .../bookings/{ref}/cancel, POST .../payments/initiate.
    // Aucun provider `public.discoverable` : la verticale a sa propre
    // recherche, elle n'a pas besoin de la decouverte.
    mode: 'booking',
    discoveryType: null,
    contactTargetType: null,
    blockers: [],
  },
  {
    // Code aligne sur le web : la verticale VTC s'appelle `mobility`.
    code: 'mobility',
    label: 'VTC & taxi',
    tagline: 'Reserver une course, suivre son chauffeur',
    glyph: '\u25B6',
    accent: '#F59E0B',
    counterpartPath: '/operateurs',
    // `vtc.php` : GET /public/vtc/marketplace/operators|operators/{slug},
    // POST /public/vtc/marketplace/estimate, POST .../rides (Idempotency-Key
    // UUID v4 OBLIGATOIRE), GET .../rides/{reference}?code=.
    mode: 'booking',
    discoveryType: 'vtc_operator',
    contactTargetType: 'vtc_operator',
    blockers: [],
  },
  {
    // Code aligne sur le web : la verticale s'appelle `health` et porte le
    // module `pharmacy` (officine).
    code: 'health',
    label: 'Pharmacies',
    tagline: 'Trouver une officine proche de vous',
    glyph: '\u271A',
    accent: '#EF4444',
    counterpartPath: '/pharmacies',
    // `PharmacyDiscoverableProvider` (tague `public.discoverable`) sert les
    // officines opt-in via `GET /public/discover?type=pharmacy`. La vitrine
    // web `/pharmacies` est alimentee par ce meme contrat.
    //
    // Le mode reste `blocked` pour le CONTACT, et c'est legitime :
    // `PublicProfileLocator::types()` ne contient pas `pharmacy`, donc
    // `POST /public/contact-requests` repondrait 422. L'annuaire est
    // consultable, la prise de contact ne l'est pas.
    mode: 'blocked',
    discoveryType: 'pharmacy',
    contactTargetType: null,
    blockers: [
      {
        capability: 'contact',
        reason:
          "La prise de contact directe n'est pas encore ouverte pour les pharmacies. Vous pouvez consulter l'annuaire et vous rendre sur place.",
      },
    ],
  },
  {
    code: 'trade',
    label: 'Entreprises',
    tagline: 'Sourcing, fournisseurs, catalogue produits',
    glyph: '\u25C8',
    accent: '#6366F1',
    counterpartPath: '/entreprises',
    // `trade_public.php` : GET /public/trade/companies/{slug} et
    // GET /public/trade/companies/{slug}/products.
    mode: 'request',
    discoveryType: 'trade_company',
    contactTargetType: 'trade_company',
    blockers: [
      {
        capability: 'quote',
        reason:
          "Le devis structure (lignes, quantites, incoterm) n'a pas de contrat public : envoyez une demande de contact, l'entreprise vous repondra avec une offre.",
      },
    ],
  },
  {
    code: 'education',
    label: 'Scolarite',
    tagline: 'Suivre la scolarite de votre enfant',
    glyph: '\u25A4',
    accent: '#22C55E',
    counterpartPath: '/guardian-portal',
    // `edu_manager.php` : GET /edu-manager/portal/{token} (public, throttle 20/min)
    // et POST /edu-manager/guardian-portal/access-links/{token}/consume.
    // Le parcours est un PORTAIL A JETON remis par l'etablissement : il n'y a
    // ni annuaire public ni inscription libre, et c'est voulu (donnees de
    // mineurs). L'app propose donc la saisie du lien recu.
    mode: 'request',
    discoveryType: null,
    contactTargetType: null,
    blockers: [
      {
        capability: 'catalog',
        reason:
          "Il n'existe pas d'annuaire public d'etablissements : l'acces se fait avec le lien transmis par l'ecole.",
      },
    ],
  },
  {
    code: 'fuel',
    label: 'Carburant',
    tagline: 'Stations et prix a la pompe',
    glyph: '\u26FD',
    accent: '#EAB308',
    counterpartPath: '/fuel',
    // `fuel_station.php` : TOUTES les routes sont tenant-scoped derriere
    // `auth:sanctum` + `module.fuel_station`. Aucune route `public/...` n'est
    // declaree, et aucun provider `public.discoverable` n'existe pour cette
    // verticale. Il n'y a donc AUCUN contrat public appelable : declarer un
    // parcours produirait un bouton mort.
    mode: 'blocked',
    discoveryType: null,
    contactTargetType: null,
    blockers: [
      {
        capability: 'catalog',
        reason:
          "Les stations-service n'ont pas encore de vitrine publique : leurs donnees sont reservees aux equipes de la station. Cette verticale arrive prochainement.",
      },
    ],
  },
];

/** Verticale par code stable. Fail-closed : code inconnu -> `null`. */
export function journeyByCode(code: string | null | undefined): JourneyVertical | null {
  if (!code) return null;
  for (const vertical of JOURNEY_VERTICALS) {
    if (vertical.code === code) return vertical;
  }
  return null;
}

/** Verticale exposee par `GET /public/discover` pour un `type` donne. */
export function journeyByType(type: string | null | undefined): JourneyVertical | null {
  if (!type) return null;
  for (const vertical of JOURNEY_VERTICALS) {
    if (vertical.discoveryType === type) return vertical;
  }
  return null;
}

/** Vrai si la verticale sait faire un parcours transactionnel natif. */
export function hasNativeJourney(vertical: JourneyVertical): boolean {
  return vertical.mode === 'order' || vertical.mode === 'booking';
}

/** Motif d'une capacite bloquee, ou `null` si elle ne l'est pas. */
export function blockerFor(vertical: JourneyVertical, capability: string): string | null {
  for (const blocker of vertical.blockers) {
    if (blocker.capability === capability) return blocker.reason;
  }
  return null;
}

/**
 * Verticales qui exposent un annuaire consultable via `/public/discover`.
 *
 * Sert a construire l'ecran « Explorer » : seules ces verticales ont des
 * fiches a lister. Les autres (travel, education) ont leur propre parcours et
 * ne passent pas par la decouverte.
 */
export const DISCOVERABLE_VERTICALS: JourneyVertical[] = JOURNEY_VERTICALS.filter(
  (vertical) => vertical.discoveryType !== null,
);
