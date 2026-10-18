import { Navbar } from '@/modules/vitrine/components/Navbar';

/**
 * Navbar des portails carri\u00e8res.
 *
 * #8461 (refonte vitrine) : ce wrapper ne porte plus AUCUNE logique de th\u00e8me.
 * La bascule jour/nuit a \u00e9t\u00e9 retir\u00e9e de la vitrine ; il n'y a donc plus de
 * classe `dark` \u00e0 synchroniser, pas de `localStorage`, pas de r\u00e9solution
 * `prefers-color-scheme`. Le rendu est un th\u00e8me unique clair, identique sur
 * toutes les pages. Le composant est conserv\u00e9 comme point d'ancrage des deux
 * pages carri\u00e8res (elles l'importent par chemin).
 */
export function CareersNavbar() {
  return <Navbar />;
}
