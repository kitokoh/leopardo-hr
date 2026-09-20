"use client";

import Link from "next/link";

import { SUPPORTED_LOCALES } from "@/lib/i18n";
import { useAccount } from "@/lib/account-provider";
import { useLocale } from "@/lib/locale-provider";

export function SiteHeader() {
  const { locale, dict, setLocale } = useLocale();
  const { ready, account } = useAccount();

  return (
    <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <Link href="/" className="flex items-center gap-2">
          <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-lg font-bold text-white">
            L
          </span>
          <span className="text-lg font-semibold tracking-tight text-slate-900">
            Leopardo <span className="text-brand-600">Travel</span>
          </span>
        </Link>

        <nav className="flex items-center gap-2 sm:gap-4">
          <Link
            href="/booking"
            className="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-900"
          >
            {dict.nav.findBooking}
          </Link>

          {/* #7739 — compte client : « Connexion » tant que l'état n'est pas
              résolu ou sans session, sinon « Mon compte ». */}
          <Link
            href={ready && account ? "/account" : "/account/login"}
            className="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-900"
          >
            {ready && account ? dict.nav.account : dict.nav.login}
          </Link>

          <div
            className="flex overflow-hidden rounded-lg border border-slate-200"
            role="group"
            aria-label={dict.nav.language}
          >
            {SUPPORTED_LOCALES.map((code) => (
              <button
                key={code}
                type="button"
                onClick={() => setLocale(code)}
                aria-pressed={locale === code}
                className={`px-2.5 py-1.5 text-xs font-semibold uppercase transition ${
                  locale === code
                    ? "bg-brand-600 text-white"
                    : "bg-white text-slate-600 hover:bg-slate-100"
                }`}
              >
                {code}
              </button>
            ))}
          </div>
        </nav>
      </div>
    </header>
  );
}
