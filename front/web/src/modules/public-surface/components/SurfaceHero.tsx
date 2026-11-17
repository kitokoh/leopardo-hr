/**
 * #9580 (BC-41 BRANDING) — hero de la surface publique.
 *
 * Aucune E/S : le composant reçoit l'identité déjà résolue. Le logo est rendu
 * en `<img>` simple plutôt qu'avec `next/image` : l'URL vient d'un CDN tenant
 * dont les domaines ne sont pas déclarés dans `next.config`, et une image
 * distante non déclarée ferait échouer le build. Un `<img>` avec `loading`
 * explicite reste correct et n'impose aucune configuration par tenant.
 */

import type { BrandIdentity } from '../domain/brand';

interface SurfaceHeroProps {
  identity: BrandIdentity;
  verticalLabel: string;
}

export function SurfaceHero({ identity, verticalLabel }: SurfaceHeroProps) {
  return (
    <header className="relative overflow-hidden">
      <div
        className="absolute inset-0 opacity-10"
        style={{ background: 'var(--brand-primary)' }}
        aria-hidden="true"
      />

      <div className="relative mx-auto flex max-w-6xl flex-col gap-6 px-6 py-[var(--brand-section-gap)]">
        <div className="flex items-center gap-4">
          {identity.logo_url ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={identity.logo_url}
              alt=""
              width={56}
              height={56}
              loading="eager"
              className="h-14 w-14 rounded-[var(--brand-radius)] object-contain"
            />
          ) : (
            <span
              aria-hidden="true"
              className="flex h-14 w-14 items-center justify-center rounded-[var(--brand-radius)] text-xl font-bold"
              style={{
                background: 'var(--brand-primary)',
                color: 'var(--brand-on-primary)',
              }}
            >
              {identity.display_name.slice(0, 1).toUpperCase()}
            </span>
          )}

          <span className="text-xs font-semibold uppercase tracking-widest text-slate-500">
            {verticalLabel}
          </span>
        </div>

        <h1 className="max-w-3xl text-4xl font-bold leading-tight tracking-tight text-slate-900 sm:text-5xl">
          {identity.display_name}
        </h1>

        {identity.tagline ? (
          <p className="max-w-2xl text-lg leading-relaxed text-slate-600">{identity.tagline}</p>
        ) : null}
      </div>
    </header>
  );
}
