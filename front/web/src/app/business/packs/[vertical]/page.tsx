'use client';

/**
 * Page vitrine « Pack offert » par métier — /business/packs/[vertical].
 *
 * Même promesse que /restaurateur : le pack métier est OFFERT, activé à la
 * création de l'espace. La page sert les verticales qui n'ont pas (encore) de
 * wizard dédié : station-service, école, agence de voyage (cf.
 * `data/pack-pages.ts`). Le héros réutilise la constellation WebGL du site —
 * vivante, avec repli 2D automatique.
 *
 * #9788 — le corps de page est extrait dans `PackLandingPage` pour être
 * partagé avec les pages dédiées (`/business/ecole`) sans duplication.
 */

import { notFound } from 'next/navigation';
import { use } from 'react';
import { isPackVertical } from '@/modules/vitrine/data/pack-pages';
import { PackLandingPage } from '@/modules/vitrine/components/pack/PackLandingPage';

export default function PackVerticalPage({
  params,
}: {
  params: Promise<{ vertical: string }>;
}) {
  const { vertical } = use(params);

  if (!isPackVertical(vertical)) {
    notFound();
  }

  return <PackLandingPage vertical={vertical} />;
}
