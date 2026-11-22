'use client';

/**
 * Page vitrine dédiée à l'école — /business/ecole (#9788).
 *
 * Point d'entrée marketing spécifique au métier école, comme /restaurateur.
 * Réutilise le même corps que `/business/packs/ecole` (`PackLandingPage`) :
 * une seule source de vérité pour le contenu, deux URL d'atterrissage (SEO et
 * campagnes) — la mission « une école vient sur la plateforme » a son adresse.
 *
 * Le CTA mène à `/business/signup?pack=ecole` : l'espace est provisionné avec
 * la verticale `edumanager` (cf. `packSolutionCode('ecole')`).
 */

import { PackLandingPage } from '@/modules/vitrine/components/pack/PackLandingPage';

export default function EcoleLandingPage() {
  return <PackLandingPage vertical="ecole" />;
}
