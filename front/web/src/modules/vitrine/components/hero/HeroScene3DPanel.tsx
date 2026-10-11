'use client';

/**
 * HeroScene3DPanel — la constellation « Business OS » en COLONNE de héro
 * (page d'accueil, layout split).
 *
 * Retour propriétaire 2026-10-11 (#8389) : la scène plein cadre derrière le
 * texte nuisait à la lisibilité ; elle vit désormais dans la colonne de
 * droite, à la place du screenshot produit (déplacé, pas supprimé — il reste
 * disponible pour d'autres sections). Et #8390 : les modules orbitaux sont
 * cliquables et mènent aux pages packs / entrées clés.
 *
 * Retour propriétaire 2026-10-11, vague 2 (#8403) : la scène doit IMPRESSIONNER
 * dès le premier écran — panneau nettement agrandi (jusqu'à 660 px), aligné en
 * haut avec le récit de gauche (voir `HeroSection`, items-start), la 3D porte
 * la première page. Et #8404 : chaque losange porte son étiquette (pack ou
 * entrée) + un indice « cliquez pour explorer », sinon rien ne dit que les
 * modules sont cliquables.
 *
 * Le conteneur a une hauteur propre : la scène (absolute inset-0) le remplit.
 */

import { HeroScene3D } from './HeroScene3D';
import { useVitrineLocale } from '../../lib/vitrine-locale';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * Un href par module (ordre de création : anneau intérieur ×4 puis anneau
 * extérieur ×4). Anneau intérieur = les 4 packs métiers offerts (la promesse
 * d'entrée) ; anneau extérieur = les entrées clés de la plateforme.
 * Clavier/lecteur d'écran : ces mêmes liens sont dans la navbar, les CTA et
 * le hub /packs — le canvas n'est qu'un raccourci visuel de plus.
 */
const MODULE_LINKS: string[] = [
  // Anneau intérieur — packs métiers offerts
  '/restaurateur',
  '/packs/station-service',
  '/packs/ecole',
  '/packs/agence-de-voyage',
  // Anneau extérieur — entrées plateforme
  '/packs',
  '/pricing',
  '/demo',
  '/contact',
];

/**
 * #8404 — clé i18n du libellé de chaque module, dans le MÊME ordre que
 * `MODULE_LINKS`. Les noms de packs/entrées réutilisent les clés existantes
 * (`vitrine.nav.*`, source unique) ; seuls « Démo » et l'indice sont nouveaux
 * (`vitrine.hero3d.*`, ×4 locales, P03).
 */
const MODULE_LABEL_KEYS: string[] = [
  'vitrine.nav.packsRestaurant',
  'vitrine.nav.packsFuel',
  'vitrine.nav.packsEdu',
  'vitrine.nav.packsTravel',
  'vitrine.nav.packsHub',
  'vitrine.nav.pricing',
  'vitrine.hero3d.demo',
  'vitrine.nav.contact',
];

export function HeroScene3DPanel() {
  const { locale } = useVitrineLocale();
  const moduleLabels = MODULE_LABEL_KEYS.map((key) => t(locale, key));

  return (
    <div className="relative h-[420px] w-full sm:h-[540px] lg:h-[600px] xl:h-[660px]">
      <HeroScene3D
        moduleLinks={MODULE_LINKS}
        moduleLabels={moduleLabels}
        hint={t(locale, 'vitrine.hero3d.hint')}
      />
    </div>
  );
}

export default HeroScene3DPanel;
