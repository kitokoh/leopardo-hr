'use client';

/**
 * HeroScene3DPanel — la constellation « Business OS » en PLEINE SCÈNE de héros
 * (page d'accueil, layout `stage`).
 *
 * Mission Leopardo — la constellation présente les SOLUTIONS MÉTIER RÉELLES
 * (et non les liens du menu) : les 10 étoiles viennent du catalogue unique
 * `data/solution-catalog.ts` (qui réutilise `PACK_PAGES` pour les verticales
 * déjà documentées). Les entrées redondantes avec la barre du haut
 * (« Contact », « Pricing ») ont disparu : la constellation dit la plateforme
 * commune + ses environnements métier, pas la navigation.
 *
 * Le clic sur une étoile n'ouvre plus une page (#8390) : il affiche la fiche
 * interactive (`SolutionCard`) — description, profils concernés,
 * fonctionnalités incluses, CTA vers la page complète ou l'onboarding.
 *
 * Le conteneur épouse son parent : la scène (absolute inset-0) le remplit.
 */

import { useCallback, useEffect, useState } from 'react';
import { HeroScene3D } from './HeroScene3D';
import { SolutionCard } from './SolutionCard';
import { useVitrineLocale } from '../../lib/vitrine-locale';
import { SOLUTIONS, solutionLabel, type SolutionId } from '../../data/solution-catalog';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * Identifiants des modules, dans l'ordre de création de la scène 3D :
 * anneau INTÉRIEUR (5 premières entrées du catalogue — solutions offertes en
 * pack) puis anneau EXTÉRIEUR (5 suivantes — autres environnements + socle).
 * Voir `HeroBusinessOS3D` (`MODULES_PER_RING = [5, 5]`).
 */
const MODULE_IDS: readonly SolutionId[] = SOLUTIONS.map((solution) => solution.id);

/** Destination de repli d'un module (accessible depuis la fiche). */
export const MODULE_HREFS: readonly string[] = SOLUTIONS.map((solution) => solution.href);

export function HeroScene3DPanel() {
  const { locale } = useVitrineLocale();
  const [selected, setSelected] = useState<SolutionId | null>(null);
  const moduleLabels = MODULE_IDS.map((id) => solutionLabel(id, locale));

  const onSelectModule = useCallback((index: number) => {
    const id = MODULE_IDS[index];
    if (id) setSelected(id);
  }, []);

  const close = useCallback(() => setSelected(null), []);

  // Échap referme la fiche (l'état vit ici ; `SolutionCard` gère en plus le
  // piégeage du focus et la restitution du focus à l'étoile d'origine).
  useEffect(() => {
    if (!selected) return undefined;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setSelected(null);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [selected]);

  return (
    <div className="relative h-full w-full">
      <HeroScene3D
        onSelectModule={onSelectModule}
        moduleLabels={moduleLabels}
        hint={t(locale, 'vitrine.hero3d.hint')}
      />
      {selected && <SolutionCard solutionId={selected} locale={locale} onClose={close} />}
    </div>
  );
}

export default HeroScene3DPanel;
