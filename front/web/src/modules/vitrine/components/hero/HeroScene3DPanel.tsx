'use client';

/**
 * HeroScene3DPanel — la constellation « Business OS » en PLEINE SCÈNE de héro
 * (page d'accueil, layout `stage` — vague 3, #8437/#8438).
 *
 * Historique : #8389 plaçait la scène dans la colonne de droite du héro
 * (lisibilité du texte) ; #8403 l'agrandissait et l'alignait en haut. Retour
 * propriétaire 2026-10-11 (vague 3) : la constellation doit prendre TOUTE la
 * place, la phrase passe en toile de fond — ce panneau remplit désormais tout
 * le héro (`absolute inset-0` posé par `HeroSection`), et le héro devient une
 * scène nuit permanente, belle en mode jour comme en mode nuit.
 *
 * #8390 : les modules orbitaux sont cliquables et mènent aux pages packs /
 * entrées clés. #8404 : chaque losange porte son étiquette + un indice
 * « cliquez pour explorer ». #8438 : 12 modules au lieu de 8 — la
 * constellation présente davantage de solutions.
 *
 * Le conteneur épouse son parent : la scène (absolute inset-0) le remplit.
 */

import { HeroScene3D } from './HeroScene3D';
import { useVitrineLocale } from '../../lib/vitrine-locale';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * Un href par module (ordre de création : anneau intérieur ×6 puis anneau
 * extérieur ×6). Anneau intérieur = les packs métiers offerts (la promesse
 * d'entrée, cagnottes incluses — #8438) ; anneau extérieur = les entrées clés
 * de la plateforme. Clavier/lecteur d'écran : ces mêmes liens sont dans la
 * navbar, les CTA et le hub /packs — le canvas n'est qu'un raccourci visuel.
 */
const MODULE_LINKS: string[] = [
  // Anneau intérieur — packs métiers offerts + hub
  '/restaurateur',
  '/packs/station-service',
  '/packs/ecole',
  '/packs/agence-de-voyage',
  '/packs/cagnottes',
  '/packs',
  // Anneau extérieur — entrées plateforme
  '/pricing',
  '/demo',
  '/alternatives',
  '/contact',
  '/careers',
  '/signup',
];

/**
 * #8404 — clé i18n du libellé de chaque module, dans le MÊME ordre que
 * `MODULE_LINKS`. Les noms de packs/entrées réutilisent les clés existantes
 * (`vitrine.nav.*`, source unique) ; « Cagnottes » et « Essai gratuit » sont
 * nouveaux (`vitrine.hero3d.*`, ×4 locales — #8438).
 */
const MODULE_LABEL_KEYS: string[] = [
  'vitrine.nav.packsRestaurant',
  'vitrine.nav.packsFuel',
  'vitrine.nav.packsEdu',
  'vitrine.nav.packsTravel',
  'vitrine.hero3d.cagnottes',
  'vitrine.nav.packsHub',
  'vitrine.nav.pricing',
  'vitrine.hero3d.demo',
  'vitrine.nav.comparer',
  'vitrine.nav.contact',
  'vitrine.nav.careers',
  'vitrine.hero3d.freeTrial',
];

export function HeroScene3DPanel() {
  const { locale } = useVitrineLocale();
  const moduleLabels = MODULE_LABEL_KEYS.map((key) => t(locale, key));

  return (
    <div className="relative h-full w-full">
      <HeroScene3D
        moduleLinks={MODULE_LINKS}
        moduleLabels={moduleLabels}
        hint={t(locale, 'vitrine.hero3d.hint')}
      />
    </div>
  );
}

export default HeroScene3DPanel;
