/**
 * RESTO-904 (#7749) — types d'établissement du profil public d'une branche
 * (enum `establishment_type` du contrat RESTO-901/#7746, api/openapi.yaml).
 */
export const ESTABLISHMENT_TYPES = [
  'restaurant',
  'fast_food',
  'pizzeria',
  'brasserie',
  'cafe',
  'patisserie',
  'traiteur',
  'autre',
] as const;

export type EstablishmentType = (typeof ESTABLISHMENT_TYPES)[number];
