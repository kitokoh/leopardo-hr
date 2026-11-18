/**
 * Vitrine publique — verticale Mobilité (VTC / taxi) (#9622).
 *
 * Point d'entrée unique du bounded context.
 *
 * ## Pourquoi cette verticale existait sans vitrine
 *
 * `VtcDiscoverableProvider` publie `vtc_operator` depuis P1-02 (#8773) et le
 * contrat public accepte des courses invitées. Mais la verticale vivait
 * uniquement dans l'annuaire générique `/operateurs` (`buildDirectoryPage`),
 * sans identité ni composition propres : le grand public arrivait sur une liste
 * sommaire, et la verticale n'avait ni domaine canonique ni déploiement dédié.
 */

export {
  loadMobilitySurface,
  type MobilityCard,
  type MobilitySurfaceResult,
  type MobilitySurfaceState,
} from './application/load-mobility-surface';
export {
  MOBILITY_ACTIONS,
  currencyLabel,
  isContactable,
  operatorMeta,
  type MobilityAction,
  type MobilityOperator,
} from './domain/operator';
export { MOBILITY_SURFACE } from './identity/mobility-identity';
export { MobilitySurface } from './components/MobilitySurface';
export { loadOperators, type MobilityLoadResult } from './infrastructure/operator-gateway';
