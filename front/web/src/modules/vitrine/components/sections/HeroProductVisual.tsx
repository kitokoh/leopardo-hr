'use client';

import Image from 'next/image';

export interface HeroProductVisualProps {
  /** Screenshot served from `front/web/public/screenshots` (real product UI, not a mockup). */
  src: string;
  alt: string;
}

/**
 * Browser-chrome frame around a real product screenshot, shown directly in
 * the hero (above the fold) so a visitor sees actual product UI before
 * scrolling or leaving an email (PA2-MKT-001).
 */
export function HeroProductVisual({ src, alt }: HeroProductVisualProps) {
  return (
    <div className="relative mx-auto w-full max-w-4xl">
      <div className="absolute -inset-4 bg-gradient-to-r from-emerald-500/10 to-cyan-500/10 rounded-3xl blur-2xl" />
      <div className="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-slate-900 shadow-2xl overflow-hidden">
        <div className="flex items-center gap-1.5 h-8 px-4 bg-slate-800 dark:bg-slate-800">
          <span className="w-2.5 h-2.5 rounded-full bg-red-500/60" />
          <span className="w-2.5 h-2.5 rounded-full bg-amber-500/60" />
          <span className="w-2.5 h-2.5 rounded-full bg-green-500/60" />
        </div>
        <div className="relative aspect-[16/9] bg-slate-950">
          <Image
            src={src}
            alt={alt}
            fill
            priority
            sizes="(max-width: 768px) 100vw, 896px"
            className="object-cover object-top"
          />
        </div>
      </div>
    </div>
  );
}
