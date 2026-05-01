'use client';

import type { ReactNode } from 'react';

type ModulePageShellProps = {
  title: string;
  subtitle: string;
  accentClassName: string;
  children: ReactNode;
};

export function ModulePageShell({
  title,
  subtitle,
  accentClassName,
  children,
}: ModulePageShellProps) {
  return (
    <div className="space-y-6">
      <section className={`overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm ${accentClassName}`}>
        <div className="space-y-3 p-6">
          <p className="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Module RH</p>
          <h1 className="text-3xl font-black text-slate-950">{title}</h1>
          <p className="max-w-2xl text-sm leading-relaxed text-slate-600">{subtitle}</p>
        </div>
      </section>
      {children}
    </div>
  );
}
