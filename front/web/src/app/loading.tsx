import { BrandLoader } from '@/components/brand/BrandLoader';

/**
 * #8446 — État de chargement racine avec le logo animé de marque Leopardo.
 *
 * Évite un flash vide pendant la transition de segment de route et monte
 * le loader de marque sobre et accessible (respecte `prefers-reduced-motion`).
 */
export default function RootLoading() {
  return (
    <div className="flex min-h-[60vh] w-full items-center justify-center p-8">
      <BrandLoader size={72} />
    </div>
  );
}
