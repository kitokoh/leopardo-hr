'use client';

import type { ReactNode } from 'react';
import { MotionConfig } from 'framer-motion';

/**
 * #8063 : applique `prefers-reduced-motion` à toutes les animations
 * framer-motion de l'app (les transforms des reveals sont neutralisés pour
 * les visiteurs qui demandent moins de mouvement — le contenu, lui, est
 * toujours visible par défaut).
 */
export function MotionProvider({ children }: { children: ReactNode }) {
  return <MotionConfig reducedMotion="user">{children}</MotionConfig>;
}
