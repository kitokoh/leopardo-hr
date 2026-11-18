/**
 * Vitrine publique — verticale B2B (Trade).
 *
 * Point d'entrée unique du bounded context.
 */

export { loadCompanyView, type CompanyView } from './application/load-company-view';
export {
  loadCompanyDirectoryView,
  type CompanyDirectoryCard,
  type CompanyDirectoryResult,
  type CompanyDirectoryState,
} from './application/load-company-directory-view';
export {
  directoryDirections,
  visibleCategories,
  type CompanyDirectoryEntry,
} from './domain/company-directory';
export {
  companyLocation,
  hasProductCategories,
  tradeDirections,
  type Company,
} from './domain/company';
export { TRADE_SURFACE } from './identity/trade-identity';
export { CompanySurface } from './components/CompanySurface';
export { CompanyDirectorySurface } from './components/CompanyDirectorySurface';
