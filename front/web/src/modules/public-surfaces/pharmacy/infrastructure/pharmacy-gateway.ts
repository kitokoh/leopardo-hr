/**
 * Adaptateur réseau — verticale Pharmacie.
 *
 * Seule couture vers l'agrégateur public de découverte
 * (`@/lib/public-discovery-api`).
 *
 * ## Ce qui a changé (#9592)
 *
 * Le provider `pharmacy` existe désormais côté API
 * (`PharmacyDiscoverableProvider`, tagué `public.discoverable`) : la recherche
 * `?type=pharmacy` remonte de vraies officines opt-in. Avant, la vitrine
 * affichait « bientôt disponible » en permanence — un état honnête, mais qui
 * masquait l'absence de sémantique de publication côté backend.
 *
 * ## Ce que l'adaptateur ne fait PAS
 *
 * Il ne devine rien. `onDuty` vient du `category` publié par le provider
 * (`'on_duty'`), et `services` reste vide tant que le contrat public ne les
 * expose pas : une liste inventée ferait promettre des services que l'officine
 * n'a pas déclarés.
 */

import { searchPublicDiscovery } from '@/lib/public-discovery-api';

import type { Pharmacy } from '../domain/pharmacy';

/** Résultat de chargement : disponibilité explicite + officines. */
export interface PharmacyLoadResult {
  /** `false` si l'API n'a pas répondu (panne), distinct d'un catalogue vide. */
  readonly available: boolean;
  readonly pharmacies: readonly Pharmacy[];
}

/**
 * Charge les officines publiques.
 *
 * `available: false` = panne API. `available: true` + liste vide = aucune
 * officine n'a encore activé son opt-in de publication.
 */
export async function loadPharmacies(params: {
  q?: string;
  city?: string;
  page?: number;
}): Promise<PharmacyLoadResult> {
  const result = await searchPublicDiscovery({
    q: params.q,
    city: params.city,
    type: 'pharmacy',
    page: params.page,
  });

  if (result === null) {
    return { available: false, pharmacies: [] };
  }

  return {
    available: true,
    pharmacies: result.items.map((item) => ({
      slug: item.slug,
      name: item.name,
      city: item.city ?? null,
      description: item.description ?? null,
      // Le provider publie `category = 'on_duty'` quand l'officine est de
      // garde, `null` sinon. On ne déduit jamais « fermée » d'une absence.
      onDuty: item.category === 'on_duty',
      services: [],
    })),
  };
}

/**
 * Charge une officine par slug.
 *
 * L'agrégateur public n'expose pas de lecture unitaire par slug : on filtre la
 * liste. `null` = introuvable (non publiée) ou API indisponible — la page
 * répond `notFound()`, jamais une fiche vide.
 */
export async function loadPharmacy(slug: string): Promise<Pharmacy | null> {
  const result = await loadPharmacies({ q: slug });

  if (!result.available) {
    return null;
  }

  return result.pharmacies.find((pharmacy) => pharmacy.slug === slug) ?? null;
}
