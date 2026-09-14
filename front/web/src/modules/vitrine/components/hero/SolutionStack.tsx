'use client';

/**
 * SolutionStack — visuel « Pile Leopardo » destiné au hero de la vitrine.
 *
 * Composition de trois niveaux : socle → couche horizontale → verticales.
 * Le canvas WebGL (`SolutionStack3D`) est chargé en **import dynamique** et
 * uniquement si le navigateur expose réellement un contexte WebGL ; sinon on
 * sert une composition CSS 3D équivalente (même information, zéro WebGL).
 *
 * L'intérêt du composant n'est pas décoratif : il rend lisible l'architecture
 * de l'offre — les briques horizontales sont partagées, chaque verticale s'y
 * branche. Sélectionner une verticale met en évidence les briques qu'elle
 * consomme réellement (données des manifests serveur).
 */

import dynamic from 'next/dynamic';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Layers, LayoutGrid, Blocks } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';
import {
  EXTRA_MODULE_LABELS,
  HORIZONTAL_BLOCKS,
  VERTICALS,
  getSolutionStackCopy,
  verticalGlow,
  type VerticalKey,
} from '@/modules/vitrine/data/solution-stack';

const SolutionStack3D = dynamic(
  () => import('./SolutionStack3D').then((module) => module.SolutionStack3D),
  { ssr: false },
);

export interface SolutionStackProps {
  locale: AppLocale;
}

/** WebGL réellement utilisable ? (testé une seule fois, puis mémorisé.) */
let webglSupport: boolean | null = null;
function hasWebGL(): boolean {
  if (webglSupport !== null) return webglSupport;
  try {
    const canvas = document.createElement('canvas');
    webglSupport = Boolean(
      canvas.getContext('webgl2') ??
        canvas.getContext('webgl') ??
        canvas.getContext('experimental-webgl'),
    );
  } catch {
    webglSupport = false;
  }
  return webglSupport;
}

export function SolutionStack({ locale }: SolutionStackProps) {
  const copy = useMemo(() => getSolutionStackCopy(locale), [locale]);
  const extraLabels = EXTRA_MODULE_LABELS[locale] ?? EXTRA_MODULE_LABELS.fr;

  const [active, setActive] = useState<VerticalKey | null>(null);
  const [hovered, setHovered] = useState<VerticalKey | null>(null);
  const [enhanced, setEnhanced] = useState(false);
  const [reducedMotion, setReducedMotion] = useState(false);

  // Le canvas n'est monté qu'après hydratation, si le WebGL est disponible et
  // si l'utilisateur n'a pas demandé à réduire les animations.
  //
  // Le montage est différé au premier temps mort (requestIdleCallback) : le
  // chunk three.js (~130 Ko gzip) ne doit pas concurrencer le LCP ni le
  // hydratation de la vitrine. On renonce au 3D si l'utilisateur est en
  // économie de données — le repli CSS porte la même information.
  useEffect(() => {
    const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    setReducedMotion(motionQuery.matches);

    const onChange = (event: MediaQueryListEvent): void => setReducedMotion(event.matches);
    motionQuery.addEventListener('change', onChange);

    const connection = (navigator as Navigator & { connection?: { saveData?: boolean } }).connection;
    if (connection?.saveData) {
      return () => motionQuery.removeEventListener('change', onChange);
    }

    const windowWithIdle = window as Window & {
      requestIdleCallback?: (callback: () => void, options?: { timeout: number }) => number;
      cancelIdleCallback?: (handle: number) => void;
    };

    let idleHandle: number | null = null;
    let timer: ReturnType<typeof setTimeout> | null = null;

    const enable = (): void => setEnhanced(hasWebGL());

    if (typeof windowWithIdle.requestIdleCallback === 'function') {
      idleHandle = windowWithIdle.requestIdleCallback(enable, { timeout: 1800 });
    } else {
      timer = setTimeout(enable, 300);
    }

    return () => {
      motionQuery.removeEventListener('change', onChange);
      if (idleHandle !== null && typeof windowWithIdle.cancelIdleCallback === 'function') {
        windowWithIdle.cancelIdleCallback(idleHandle);
      }
      if (timer !== null) clearTimeout(timer);
    };
  }, []);

  const focus = hovered ?? active;
  const activeVertical = active ? VERTICALS.find((v) => v.key === active) : undefined;
  const consumed = activeVertical ? new Set(activeVertical.consumes) : null;

  const labels = useMemo(
    () =>
      Object.fromEntries(
        VERTICALS.map((vertical) => [vertical.key, copy.verticals[vertical.key]]),
      ) as Record<VerticalKey, string>,
    [copy],
  );

  const toggle = useCallback((key: VerticalKey) => {
    setActive((current) => (current === key ? null : key));
  }, []);

  const layers = [
    { icon: Layers, ...copy.layerPlatform, accent: 'text-slate-400', dot: 'bg-slate-400' },
    { icon: LayoutGrid, ...copy.layerHorizontal, accent: 'text-emerald-500', dot: 'bg-emerald-500' },
    { icon: Blocks, ...copy.layerVertical, accent: 'text-amber-500', dot: 'bg-gradient-to-r from-amber-400 to-rose-500' },
  ];

  return (
    <div className="w-full">
      {/* Titre de section */}
      <div className="mx-auto mb-8 max-w-2xl text-center">
        <div className="mb-3 text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">
          {copy.eyebrow}
        </div>
        <h2 className="text-balance text-xl font-black tracking-tight text-slate-900 dark:text-white sm:text-2xl">
          {copy.title}
        </h2>
        <p className="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
          {copy.subtitle}
        </p>
      </div>

      {/* Canvas */}
      <div
        role="img"
        aria-label={copy.canvasAlt}
        dir="ltr"
        className="relative mx-auto h-[320px] w-full max-w-4xl overflow-hidden rounded-3xl border border-slate-200/80 bg-gradient-to-b from-slate-900 to-slate-950 shadow-2xl sm:h-[400px] lg:h-[440px] dark:border-slate-800"
      >
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_60%_50%_at_50%_120%,rgba(16,185,129,0.22),transparent)]" />
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_40%_40%_at_50%_-10%,rgba(34,211,238,0.14),transparent)]" />

        {enhanced ? (
          <SolutionStack3D
            active={focus}
            onHover={setHovered}
            onSelect={toggle}
            labels={labels}
            dir="ltr"
            reducedMotion={reducedMotion}
          />
        ) : (
          <SolutionStackFallback
            consumed={consumed}
            active={focus}
            labels={labels}
          />
        )}

        {/* Rappel des 3 niveaux. Placé en BAS à gauche : en haut à gauche, il
            recouvrait le libellé projeté de la colonne la plus à gauche
            (« Restaurant »), qui passe par cette zone au fil du balancier. */}
        <div className="pointer-events-none absolute bottom-4 left-4 flex flex-col gap-1.5">
          {layers.map((layer) => (
            <div
              key={layer.name}
              className="flex items-center gap-2 rounded-full border border-white/10 bg-slate-950/70 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-slate-300 backdrop-blur-sm sm:text-[11px]"
            >
              <span className={`h-1.5 w-1.5 rounded-full ${layer.dot}`} />
              {layer.name}
            </div>
          ))}
        </div>
      </div>

      <p className="mt-4 text-center text-xs font-medium text-slate-500 dark:text-slate-400">
        {copy.hint}
      </p>

      {/* Légende : verticales sélectionnables + briques horizontales */}
      <div className="mx-auto mt-6 max-w-4xl space-y-5">
        <div>
          <div className="mb-2.5 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
            {copy.layerVertical.name} · {VERTICALS.length}
          </div>
          <div className="flex flex-wrap gap-2">
            {VERTICALS.map((vertical) => {
              const isActive = active === vertical.key;
              return (
                <button
                  key={vertical.key}
                  type="button"
                  aria-pressed={isActive}
                  onClick={() => toggle(vertical.key)}
                  onMouseEnter={() => setHovered(vertical.key)}
                  onMouseLeave={() => setHovered(null)}
                  onFocus={() => setHovered(vertical.key)}
                  onBlur={() => setHovered(null)}
                  className={`inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition-all duration-200 ${
                    isActive
                      ? 'scale-[1.03] text-white shadow-lg'
                      : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200'
                  }`}
                  style={
                    isActive
                      ? { backgroundColor: vertical.color, borderColor: vertical.color }
                      : undefined
                  }
                >
                  <span
                    className="h-2 w-2 rounded-full"
                    style={{ backgroundColor: isActive ? '#ffffff' : vertical.color }}
                  />
                  {copy.verticals[vertical.key]}
                  <span className={isActive ? 'text-white/80' : 'text-slate-400'}>
                    {vertical.consumes.length}
                  </span>
                </button>
              );
            })}
          </div>
        </div>

        <div>
          <div className="mb-2.5 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
            {copy.layerHorizontal.name} · {HORIZONTAL_BLOCKS.length}
          </div>
          <ul className="flex flex-wrap gap-1.5">
            {HORIZONTAL_BLOCKS.map((block) => {
              const isConsumed = consumed === null || consumed.has(block.key);
              return (
                <li
                  key={block.key}
                  className={`rounded-md border px-2 py-1 text-[11px] font-medium transition-all duration-300 ${
                    isConsumed
                      ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300'
                      : 'border-slate-200 bg-white text-slate-400 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-600'
                  }`}
                >
                  {copy.horizontals[block.key]}
                </li>
              );
            })}
          </ul>

          {activeVertical && activeVertical.extraModules.length > 0 && (
            <p className="mt-3 text-[11px] font-medium text-slate-500 dark:text-slate-400">
              {copy.alsoPrefix}{' '}
              {activeVertical.extraModules
                .map((moduleKey) => extraLabels[moduleKey] ?? moduleKey)
                .join(', ')}
            </p>
          )}
        </div>
      </div>
    </div>
  );
}

/**
 * Repli sans WebGL : même composition (socle → grille de tuiles → colonnes),
 * en CSS 3D. Aucune animation et aucun coût GPU — la version accessible de
 * l'information, pas une image cassée.
 */
function SolutionStackFallback({
  consumed,
  active,
  labels,
}: {
  consumed: Set<string> | null;
  active: VerticalKey | null;
  labels: Record<VerticalKey, string>;
}) {
  return (
    <div className="absolute inset-0 flex items-center justify-center" style={{ perspective: '1100px' }}>
      <div
        className="relative"
        style={{ transform: 'rotateX(56deg) rotateZ(-28deg)', transformStyle: 'preserve-3d' }}
      >
        {/* Socle */}
        <div className="absolute -inset-6 rounded-xl border border-emerald-500/25 bg-slate-950 shadow-[0_0_60px_rgba(16,185,129,0.25)]" />

        {/* Couche horizontale : grille 4×4 */}
        <div className="grid grid-cols-4 gap-2" style={{ transformStyle: 'preserve-3d' }}>
          {HORIZONTAL_BLOCKS.map((block) => {
            const isConsumed = consumed === null || consumed.has(block.key);
            return (
              <div
                key={block.key}
                className="h-11 w-11 rounded-md border transition-opacity duration-300"
                style={{
                  borderColor: isConsumed ? 'rgba(16,185,129,0.75)' : 'rgba(16,185,129,0.2)',
                  backgroundColor: isConsumed ? 'rgba(16,185,129,0.32)' : 'rgba(16,185,129,0.06)',
                  boxShadow: isConsumed ? '0 0 14px rgba(16,185,129,0.35)' : 'none',
                }}
              />
            );
          })}
        </div>

        {/* Colonnes verticales (contre-rotation pour se dresser) */}
        <div className="pointer-events-none absolute -top-1 left-0 flex w-full justify-between px-1">
          {VERTICALS.map((vertical) => {
            const isFocus = active === vertical.key;
            const height = 70 + Math.max(0, vertical.consumes.length - 6) * 12;
            return (
              <div
                key={vertical.key}
                className="flex flex-col items-center"
                style={{
                  transform: `rotateZ(28deg) rotateX(-56deg)`,
                  transformOrigin: 'bottom center',
                  opacity: active === null || isFocus ? 1 : 0.4,
                  transition: 'opacity 300ms',
                }}
              >
                <span className="mb-1.5 whitespace-nowrap text-[10px] font-semibold text-slate-300">
                  {labels[vertical.key]}
                </span>
                <div
                  className="w-7 rounded-t-sm border"
                  style={{
                    height: `${height}px`,
                    borderColor: vertical.color,
                    background: `linear-gradient(to top, ${vertical.color}44, ${verticalGlow(vertical.color)}cc)`,
                    boxShadow: `0 0 22px ${verticalGlow(vertical.color)}66`,
                  }}
                />
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}

export default SolutionStack;
