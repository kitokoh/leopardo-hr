"use client";

import { useLocale } from "@/lib/locale-provider";

export function SiteFooter() {
  const { dict } = useLocale();

  return (
    <footer className="border-t border-slate-200 bg-white">
      <div className="mx-auto flex max-w-6xl flex-col items-center gap-1 px-4 py-6 text-center text-sm text-slate-500 sm:flex-row sm:justify-between">
        <p>{dict.footer.legal}</p>
        <p>{dict.footer.poweredBy}</p>
      </div>
    </footer>
  );
}
