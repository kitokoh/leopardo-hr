/**
 * Cas d'usage — verticale B2B.
 *
 * Charge une entreprise et prépare son affichage (localisation, directions).
 */

import {
  companyLocation,
  tradeDirections,
  type Company,
} from '../domain/company';
import { loadCompany } from '../infrastructure/company-gateway';
import { getSurfaceCopy } from '../../copy/surfaces-copy';

/** Entreprise prête à l'affichage. */
export interface CompanyView {
  readonly company: Company;
  readonly location: string | null;
  readonly directions: readonly string[];
}

/** Charge la vitrine d'une entreprise ; `null` si introuvable ou API en panne. */
export async function loadCompanyView(
  slug: string,
  locale?: string,
): Promise<CompanyView | null> {
  const company = await loadCompany(slug);

  if (company === null) {
    return null;
  }

  const shared = getSurfaceCopy(locale).shared;

  return {
    company,
    location: companyLocation(company),
    directions: tradeDirections(company, shared),
  };
}
