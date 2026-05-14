'use client'

import Link from 'next/link'
import { ArrowLeft, Globe2, ShieldCheck } from 'lucide-react'
import type { AppLocale } from '@/lib/i18n'
import { getLegalPageCopy, type LegalPageKind } from '../lib/legal-content'
import { useVitrineLocale } from '../lib/vitrine-locale'

type LegalPageShellProps = {
  page: LegalPageKind
}

export function LegalPageShell({ page }: LegalPageShellProps) {
  const { locale, direction, options, setLocale } = useVitrineLocale()
  const copy = getLegalPageCopy(locale, page)

  return (
    <main dir={direction} className="min-h-screen bg-white text-slate-950 dark:bg-slate-950 dark:text-white">
      <header className="border-b border-slate-200/80 bg-white/95 dark:border-slate-800/80 dark:bg-slate-950/95">
        <div className="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-6 sm:px-6 md:flex-row md:items-center md:justify-between">
          <Link
            href="/"
            className="inline-flex w-fit items-center gap-2 text-sm font-semibold text-slate-600 transition-colors hover:text-emerald-600 dark:text-slate-300 dark:hover:text-emerald-400"
          >
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            {copy.backLabel}
          </Link>

          <label className="flex w-full max-w-xs items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
            <Globe2 className="h-4 w-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
            <span className="sr-only">{copy.languageLabel}</span>
            <select
              aria-label={copy.languageLabel}
              value={locale}
              onChange={(event) => setLocale(event.target.value as AppLocale)}
              className="w-full bg-transparent font-semibold text-slate-900 outline-none dark:text-white"
            >
              {options.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.nativeLabel}
                </option>
              ))}
            </select>
          </label>
        </div>
      </header>

      <article className="mx-auto max-w-5xl px-4 py-14 sm:px-6 sm:py-16">
        <div className="max-w-3xl">
          <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300">
            <ShieldCheck className="h-4 w-4" aria-hidden="true" />
            {copy.eyebrow}
          </div>
          <h1 className="text-4xl font-black tracking-normal text-slate-950 dark:text-white sm:text-5xl">{copy.title}</h1>
          <p className="mt-5 text-lg leading-8 text-slate-600 dark:text-slate-300">{copy.intro}</p>
          <p className="mt-4 text-sm font-semibold text-slate-500 dark:text-slate-400">{copy.updatedAt}</p>
        </div>

        <div className="mt-12 divide-y divide-slate-200 border-y border-slate-200 dark:divide-slate-800 dark:border-slate-800">
          {copy.sections.map((section) => (
            <section key={section.title} className="grid gap-4 py-8 md:grid-cols-[220px_1fr] md:gap-10">
              <h2 className="text-xl font-black text-slate-950 dark:text-white">{section.title}</h2>
              <div className="space-y-4 text-base leading-8 text-slate-600 dark:text-slate-300">
                {section.body.map((paragraph) => (
                  <p key={paragraph}>{paragraph}</p>
                ))}
              </div>
            </section>
          ))}
        </div>

        <section className="mt-10 rounded-lg border border-slate-200 bg-slate-50 p-6 dark:border-slate-800 dark:bg-slate-900">
          <h2 className="text-lg font-black text-slate-950 dark:text-white">{copy.contact.title}</h2>
          <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">{copy.contact.body}</p>
          <a
            href={`mailto:${copy.contact.email}`}
            className="mt-4 inline-flex font-semibold text-emerald-700 transition-colors hover:text-emerald-800 dark:text-emerald-300 dark:hover:text-emerald-200"
          >
            {copy.contact.email}
          </a>
        </section>
      </article>
    </main>
  )
}
