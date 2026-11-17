/**
 * Vitrine publique — verticale B2B (Trade).
 *
 * Point d'entrée unique du bounded context.
 */

export { loadCompanyView, type CompanyView } from './application/load-company-view';
export {
  companyLocation,
  hasProductCategories,
  tradeDirections,
  type Company,
} from './domain/company';
export { TRADE_SURFACE } from './identity/trade-identity';
export { CompanySurface } from './components/CompanySurface';
