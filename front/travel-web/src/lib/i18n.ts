/**
 * i18n maison fr/en (issue #7738) — même philosophie que front/web : un
 * catalogue TypeScript typé, la locale par défaut est le français, la
 * préférence est persistée dans le cookie `travel_lang` et lue côté serveur
 * par le layout racine (`<html lang>` correct dès le SSR, aucun mismatch
 * d'hydratation). L'extension ar/tr (RTL) est un lot ultérieur de l'épic
 * #7736 — la structure (catalogue par locale + provider) est prête.
 */

export type Locale = "fr" | "en";

export const SUPPORTED_LOCALES: Locale[] = ["fr", "en"];
export const DEFAULT_LOCALE: Locale = "fr";
export const LOCALE_COOKIE = "travel_lang";

export function isLocale(value: unknown): value is Locale {
  return value === "fr" || value === "en";
}

export type Dict = typeof fr;

const fr = {
  common: {
    siteName: "Leopardo Travel",
    tagline: "Vos billets de voyage, toutes agences confondues",
    loading: "Chargement…",
    error: "Une erreur est survenue. Réessayez.",
    backHome: "Retour à l'accueil",
    required: "Champ requis",
  },
  nav: {
    home: "Accueil",
    search: "Rechercher",
    findBooking: "Retrouver ma réservation",
    language: "Langue",
    login: "Connexion",
    account: "Mon compte",
  },
  home: {
    metaTitle: "Leopardo Travel — Réservez vos billets de voyage en ligne",
    metaDescription:
      "Comparez les départs de toutes les agences partenaires, choisissez votre siège et réservez votre billet en quelques minutes.",
    heroTitle: "Où voulez-vous aller ?",
    heroSubtitle:
      "Comparez les départs de toutes les agences partenaires et réservez votre billet en quelques minutes.",
    features: {
      compareTitle: "Toutes les agences, un seul site",
      compareBody:
        "Les départs de toutes les agences partenaires, comparés sur une seule page : horaires, prix et sièges disponibles.",
      seatsTitle: "Choisissez votre siège",
      seatsBody:
        "Visualisez les sièges libres du véhicule et réservez exactement la place qui vous convient.",
      ticketTitle: "E-billet et référence",
      ticketBody:
        "Recevez une référence de réservation immédiate ; votre e-billet est émis dès le paiement à l'agence.",
    },
  },
  search: {
    from: "Ville de départ",
    to: "Ville d'arrivée",
    date: "Date de départ",
    anyCity: "Toutes les villes",
    submit: "Rechercher",
    swap: "Inverser départ et arrivée",
  },
  results: {
    metaTitle: "Trajets disponibles — Leopardo Travel",
    title: "Départs disponibles",
    countOne: "départ trouvé",
    countMany: "départs trouvés",
    empty: "Aucun départ ne correspond à votre recherche.",
    emptyHint: "Essayez une autre date ou d'autres villes.",
    seatsLeft: "sièges libres",
    seatLeft: "siège libre",
    soldOut: "Complet",
    from: "à partir de",
    select: "Voir le trajet",
    agency: "Agence",
    backendDown:
      "Le service de recherche est momentanément indisponible. Réessayez dans quelques instants.",
  },
  trip: {
    metaTitle: "Détail du trajet — Leopardo Travel",
    departure: "Départ",
    arrival: "Arrivée",
    transport: "Transport",
    agency: "Agence",
    duration: "Trajet",
    classes: "Classes et tarifs",
    classLabel: "Classe",
    adult: "Adulte",
    child: "Enfant",
    notFound: "Ce trajet n'est plus disponible.",
    bookCta: "Réserver ce trajet",
  },
  seats: {
    title: "Choisissez vos sièges",
    subtitle:
      "Sélectionnez autant de sièges que de passagers — ou laissez l'agence attribuer les places.",
    free: "Libre",
    selected: "Sélectionné",
    taken: "Indisponible",
    autoAssign: "Attribution automatique par l'agence",
    selectedCount: "sièges sélectionnés",
    seatWord: "Siège",
  },
  checkout: {
    title: "Vos informations",
    contactTitle: "Contact",
    contactEmail: "E-mail",
    contactPhone: "Téléphone",
    contactHint:
      "Utilisé uniquement pour vous transmettre la confirmation et les avis de départ.",
    notifyConsent: "Je souhaite être informé(e) par e-mail/SMS des changements de départ.",
    passengersTitle: "Passagers",
    passenger: "Passager",
    fullName: "Nom complet",
    ageCategory: "Catégorie",
    ageAdult: "Adulte",
    ageChild: "Enfant",
    ageInfant: "Bébé",
    seat: "Siège",
    seatAuto: "Auto",
    classField: "Classe",
    addPassenger: "Ajouter un passager",
    removePassenger: "Retirer",
    summaryTitle: "Récapitulatif",
    total: "Total estimé",
    payAtAgency: "Paiement à l'agence",
    payAtAgencyBody:
      "Votre réservation est garantie avec une référence. Le paiement s'effectue au guichet de l'agence avant l'expiration indiquée — le paiement en ligne arrive bientôt.",
    submit: "Confirmer la réservation",
    submitting: "Réservation en cours…",
    fillName: "Renseignez le nom de chaque passager.",
    contactRequired: "Renseignez un e-mail ou un téléphone de contact.",
    loggedInAs: "Réservation rattachée au compte",
  },
  confirmation: {
    metaTitle: "Confirmation de réservation — Leopardo Travel",
    title: "Réservation confirmée !",
    reference: "Référence de réservation",
    keepIt: "Conservez précieusement cette référence : elle vous sera demandée à l'agence et pour retrouver votre réservation.",
    status: "Statut",
    payment: "Paiement",
    passengers: "Passagers",
    total: "Total",
    expires: "À payer avant",
    agency: "Agence",
    payNotice:
      "Réservation à payer à l'agence : présentez votre référence au guichet. Votre e-billet (avec code de validation) vous sera remis au paiement.",
    trackCta: "Retrouver ma réservation",
    newSearch: "Nouvelle recherche",
    missing:
      "Cette confirmation a expiré dans votre navigateur. Utilisez « Retrouver ma réservation » avec votre référence.",
  },
  track: {
    metaTitle: "Retrouver ma réservation — Leopardo Travel",
    title: "Retrouver ma réservation",
    subtitle:
      "Saisissez la référence de votre réservation et le code de validation figurant sur votre e-billet.",
    reference: "Référence de réservation",
    code: "Code de validation (e-billet)",
    codeHint:
      "Le code de validation est imprimé sur votre e-billet, remis lors du paiement à l'agence.",
    submit: "Rechercher",
    searching: "Recherche…",
    notFound:
      "Réservation introuvable — vérifiez la référence et le code de validation.",
    resultTitle: "Votre réservation",
    tickets: "Billets",
    downloadPdf: "Télécharger le PDF",
    cancelTitle: "Annuler la réservation",
    cancelReason: "Motif d'annulation",
    cancelSubmit: "Annuler ma réservation",
    cancelling: "Annulation…",
    cancelled: "Votre réservation a été annulée.",
    cancelFailed:
      "Annulation impossible (départ déjà passé ou réservation non annulable).",
  },
  status: {
    pending: "En attente",
    confirmed: "Confirmée",
    cancelled: "Annulée",
    expired: "Expirée",
    paid: "Payé",
    unpaid: "À payer",
    partial: "Partiel",
    refunded: "Remboursé",
  },
  footer: {
    legal: "Leopardo Travel — vente de billets inter-agences.",
    poweredBy: "Propulsé par la plateforme Leopardo.",
  },
  account: {
    metaLogin: "Connexion — Leopardo Travel",
    metaRegister: "Créer un compte — Leopardo Travel",
    metaDashboard: "Mon compte — Leopardo Travel",
    loginTitle: "Connexion à mon compte",
    loginSubtitle:
      "Retrouvez toutes vos réservations, toutes agences confondues.",
    email: "E-mail",
    password: "Mot de passe",
    loginSubmit: "Se connecter",
    loggingIn: "Connexion…",
    invalidCredentials: "E-mail ou mot de passe incorrect.",
    locked:
      "Compte temporairement verrouillé après plusieurs échecs. Réessayez dans 15 minutes.",
    noAccount: "Pas encore de compte ?",
    registerCta: "Créer un compte",
    registerTitle: "Créer mon compte",
    registerSubtitle:
      "Vos réservations déjà effectuées avec cet e-mail seront rattachées à votre compte.",
    name: "Nom complet",
    phone: "Téléphone (optionnel)",
    passwordHint: "12 caractères minimum, dont au moins un chiffre.",
    registerSubmit: "Créer mon compte",
    registering: "Création…",
    haveAccount: "Déjà un compte ?",
    loginCta: "Se connecter",
    dashboardTitle: "Mon compte",
    memberSince: "Client depuis",
    myBookings: "Mes réservations",
    myBookingsHint:
      "Toutes vos réservations, toutes agences confondues — y compris celles effectuées avant la création de votre compte avec le même e-mail.",
    noBookings: "Aucune réservation pour l'instant.",
    noBookingsHint: "Réservez votre premier trajet depuis la recherche.",
    searchCta: "Rechercher un trajet",
    claimedOne: "réservation existante a été rattachée à votre compte.",
    claimedMany: "réservations existantes ont été rattachées à votre compte.",
    bookedOn: "Réservée le",
    passengers: "passager(s)",
    tickets: "Billets",
    agency: "Agence",
    logout: "Se déconnecter",
    loggingOut: "Déconnexion…",
    loginRequired: "Connectez-vous pour accéder à votre compte.",
    loadError: "Impossible de charger vos réservations. Réessayez.",
  },
};

const en: Dict = {
  common: {
    siteName: "Leopardo Travel",
    tagline: "Your travel tickets, across every agency",
    loading: "Loading…",
    error: "Something went wrong. Please try again.",
    backHome: "Back to home",
    required: "Required field",
  },
  nav: {
    home: "Home",
    search: "Search",
    findBooking: "Find my booking",
    language: "Language",
    login: "Sign in",
    account: "My account",
  },
  home: {
    metaTitle: "Leopardo Travel — Book your travel tickets online",
    metaDescription:
      "Compare departures from all partner agencies, pick your seat and book your ticket in minutes.",
    heroTitle: "Where do you want to go?",
    heroSubtitle:
      "Compare departures from every partner agency and book your ticket in minutes.",
    features: {
      compareTitle: "Every agency, one website",
      compareBody:
        "Departures from all partner agencies compared on a single page: schedules, prices and available seats.",
      seatsTitle: "Pick your seat",
      seatsBody:
        "See the vehicle's free seats and book exactly the spot that suits you.",
      ticketTitle: "E-ticket and reference",
      ticketBody:
        "Get an instant booking reference; your e-ticket is issued as soon as you pay at the agency.",
    },
  },
  search: {
    from: "Departure city",
    to: "Arrival city",
    date: "Departure date",
    anyCity: "All cities",
    submit: "Search",
    swap: "Swap departure and arrival",
  },
  results: {
    metaTitle: "Available trips — Leopardo Travel",
    title: "Available departures",
    countOne: "departure found",
    countMany: "departures found",
    empty: "No departure matches your search.",
    emptyHint: "Try another date or different cities.",
    seatsLeft: "seats left",
    seatLeft: "seat left",
    soldOut: "Sold out",
    from: "from",
    select: "View trip",
    agency: "Agency",
    backendDown:
      "The search service is temporarily unavailable. Please try again shortly.",
  },
  trip: {
    metaTitle: "Trip details — Leopardo Travel",
    departure: "Departure",
    arrival: "Arrival",
    transport: "Transport",
    agency: "Agency",
    duration: "Trip",
    classes: "Classes & fares",
    classLabel: "Class",
    adult: "Adult",
    child: "Child",
    notFound: "This trip is no longer available.",
    bookCta: "Book this trip",
  },
  seats: {
    title: "Choose your seats",
    subtitle:
      "Select as many seats as passengers — or let the agency assign seats.",
    free: "Free",
    selected: "Selected",
    taken: "Unavailable",
    autoAssign: "Automatic assignment by the agency",
    selectedCount: "seats selected",
    seatWord: "Seat",
  },
  checkout: {
    title: "Your details",
    contactTitle: "Contact",
    contactEmail: "Email",
    contactPhone: "Phone",
    contactHint:
      "Only used to send you the confirmation and departure notices.",
    notifyConsent: "Notify me by email/SMS about departure changes.",
    passengersTitle: "Passengers",
    passenger: "Passenger",
    fullName: "Full name",
    ageCategory: "Category",
    ageAdult: "Adult",
    ageChild: "Child",
    ageInfant: "Infant",
    seat: "Seat",
    seatAuto: "Auto",
    classField: "Class",
    addPassenger: "Add a passenger",
    removePassenger: "Remove",
    summaryTitle: "Summary",
    total: "Estimated total",
    payAtAgency: "Pay at the agency",
    payAtAgencyBody:
      "Your booking is guaranteed with a reference. Payment is made at the agency desk before the indicated expiry — online payment is coming soon.",
    submit: "Confirm booking",
    submitting: "Booking…",
    fillName: "Enter every passenger's name.",
    contactRequired: "Provide a contact email or phone number.",
    loggedInAs: "Booking attached to account",
  },
  confirmation: {
    metaTitle: "Booking confirmation — Leopardo Travel",
    title: "Booking confirmed!",
    reference: "Booking reference",
    keepIt:
      "Keep this reference safe: you will need it at the agency and to find your booking later.",
    status: "Status",
    payment: "Payment",
    passengers: "Passengers",
    total: "Total",
    expires: "Pay before",
    agency: "Agency",
    payNotice:
      "Booking to be paid at the agency: show your reference at the desk. Your e-ticket (with its validation code) is issued upon payment.",
    trackCta: "Find my booking",
    newSearch: "New search",
    missing:
      "This confirmation has expired in your browser. Use “Find my booking” with your reference.",
  },
  track: {
    metaTitle: "Find my booking — Leopardo Travel",
    title: "Find my booking",
    subtitle:
      "Enter your booking reference and the validation code printed on your e-ticket.",
    reference: "Booking reference",
    code: "Validation code (e-ticket)",
    codeHint:
      "The validation code is printed on your e-ticket, handed over when you pay at the agency.",
    submit: "Search",
    searching: "Searching…",
    notFound:
      "Booking not found — check the reference and the validation code.",
    resultTitle: "Your booking",
    tickets: "Tickets",
    downloadPdf: "Download PDF",
    cancelTitle: "Cancel booking",
    cancelReason: "Cancellation reason",
    cancelSubmit: "Cancel my booking",
    cancelling: "Cancelling…",
    cancelled: "Your booking has been cancelled.",
    cancelFailed:
      "Cancellation impossible (departure already past or booking not cancellable).",
  },
  status: {
    pending: "Pending",
    confirmed: "Confirmed",
    cancelled: "Cancelled",
    expired: "Expired",
    paid: "Paid",
    unpaid: "To pay",
    partial: "Partial",
    refunded: "Refunded",
  },
  footer: {
    legal: "Leopardo Travel — inter-agency ticket sales.",
    poweredBy: "Powered by the Leopardo platform.",
  },
  account: {
    metaLogin: "Sign in — Leopardo Travel",
    metaRegister: "Create an account — Leopardo Travel",
    metaDashboard: "My account — Leopardo Travel",
    loginTitle: "Sign in to my account",
    loginSubtitle: "Find all your bookings, across every agency.",
    email: "Email",
    password: "Password",
    loginSubmit: "Sign in",
    loggingIn: "Signing in…",
    invalidCredentials: "Incorrect email or password.",
    locked:
      "Account temporarily locked after several failures. Try again in 15 minutes.",
    noAccount: "No account yet?",
    registerCta: "Create an account",
    registerTitle: "Create my account",
    registerSubtitle:
      "Bookings already made with this email will be attached to your account.",
    name: "Full name",
    phone: "Phone (optional)",
    passwordHint: "At least 12 characters, including a number.",
    registerSubmit: "Create my account",
    registering: "Creating…",
    haveAccount: "Already have an account?",
    loginCta: "Sign in",
    dashboardTitle: "My account",
    memberSince: "Customer since",
    myBookings: "My bookings",
    myBookingsHint:
      "All your bookings, across every agency — including those made with the same email before creating your account.",
    noBookings: "No booking yet.",
    noBookingsHint: "Book your first trip from the search page.",
    searchCta: "Search for a trip",
    claimedOne: "existing booking has been attached to your account.",
    claimedMany: "existing bookings have been attached to your account.",
    bookedOn: "Booked on",
    passengers: "passenger(s)",
    tickets: "Tickets",
    agency: "Agency",
    logout: "Sign out",
    loggingOut: "Signing out…",
    loginRequired: "Sign in to access your account.",
    loadError: "Could not load your bookings. Please retry.",
  },
};

const CATALOG: Record<Locale, Dict> = { fr, en };

export function getDict(locale: Locale): Dict {
  return CATALOG[locale];
}

/** Libellé humain d'un statut backend (fallback : la valeur brute). */
export function statusLabel(dict: Dict, value: string | null | undefined): string {
  if (!value) return "—";
  const map: Record<string, string> = {
    pending: dict.status.pending,
    confirmed: dict.status.confirmed,
    cancelled: dict.status.cancelled,
    canceled: dict.status.cancelled,
    expired: dict.status.expired,
    paid: dict.status.paid,
    unpaid: dict.status.unpaid,
    partial: dict.status.partial,
    partially_paid: dict.status.partial,
    refunded: dict.status.refunded,
  };
  return map[value] ?? value;
}
