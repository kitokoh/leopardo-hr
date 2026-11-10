import { BrandLoader } from '@/components/brand/BrandLoader';

/**
 * #8791 — État de chargement du groupe marketing `(landing)`.
 *
 * Déplacé ici depuis `src/app/loading.tsx` : un `loading.tsx` à la RACINE de
 * `app/` enveloppe **toute** l'application dans une frontière `Suspense`, ce qui
 * force Next à envoyer l'en-tête HTTP **200** (et le loader) *avant* que la page
 * n'ait fini de charger ses données. Un `notFound()` déclenché ensuite
 * s'affiche bien (contenu 404 correct) mais **le statut reste 200** : la fiche
 * publique d'un slug inexistant devenait indexable.
 *
 * Constat mesuré en mode production (`next build` + `next start`) le 2026-10-21 :
 *
 * | URL                              | avant   | après |
 * |----------------------------------|---------|-------|
 * | `/boutiques/non-existent-slug`   | HTTP 200 | 404  |
 * | `/operateurs/non-existent-slug`  | HTTP 200 | 404  |
 * | `/vitrine/non-existent-slug`     | HTTP 200 | 404  |
 * | `/stay/non-existent-slug`        | HTTP 200 | 404  |
 *
 * En restreignant le loader à `(landing)`, les pages qui appellent `notFound()`
 * (fiches publiques, `/stay/[slug]`, `/vitrine/[slug]`) ne sont plus streamées
 * avant la décision : le statut 404 est honoré. Le confort visuel de chargement
 * est conservé là où il était recherché (#8446 : transitions de la vitrine).
 */
export default function LandingLoading() {
  return (
    <div className="flex min-h-[60vh] w-full items-center justify-center p-8">
      <BrandLoader size={72} />
    </div>
  );
}
