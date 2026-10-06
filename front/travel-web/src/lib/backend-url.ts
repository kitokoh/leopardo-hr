/**
 * #7964 — Re-export canonique depuis le package partagé @leopardo/shared-web.
 *
 * Conserve la rétrocompatibilité d'import pour tous les consommateurs de front/travel-web.
 */
export * from '../../../packages/shared-web/src/backend-url';
