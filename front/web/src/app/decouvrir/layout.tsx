import type { Metadata } from 'next'

/**
 * Layout de la surface publique `/decouvrir` (couloir 16, #9103).
 *
 * ## Rôle unique : déclarer le manifeste de l'app grand public
 *
 * Le layout racine (`src/app/layout.tsx`) déclare `manifest: '/manifest'`, qui
 * décrit le produit RH. Sans ce layout, installer la PWA depuis `/decouvrir`
 * produisait une icône ouvrant la page de vente RH.
 *
 * On ne touche PAS au layout racine : le produit RH garde son manifeste, et
 * les autres surfaces (`/restaurants`, `/travel`, …) ne changent pas de
 * comportement. Seule la route publique déclare le sien.
 *
 * ## Pourquoi `metadata` et pas un `<link>` manuel
 *
 * Next.js déduplique et ordonne les balises `manifest` par segment de route :
 * un `<link rel="manifest">` écrit à la main dans un composant client
 * s'ajouterait à celui du layout racine au lieu de le remplacer, et le
 * navigateur retiendrait le premier — donc celui du produit RH.
 */

export const metadata: Metadata = {
  manifest: '/decouvrir/manifest.webmanifest',
}

export default function DecouvrirLayout({ children }: { children: React.ReactNode }) {
  return children
}
