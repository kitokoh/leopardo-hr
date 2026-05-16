'use client'

import Link from 'next/link'
import { Globe } from 'lucide-react'
import { useVitrineLocale } from '../lib/vitrine-locale'

function getFooterHref(sectionIndex: number, linkIndex: number): string {
  const key = `${sectionIndex}-${linkIndex}`
  const routes: Record<string, string> = {
    '0-1': '/pricing',
    '0-4': '/changelog',
    '1-1': '/blog',
    '2-0': '/privacy',
    '2-1': '/terms',
  }

  return routes[key] ?? '#'
}

export function Footer() {
  const { copy, locale, options } = useVitrineLocale()
  const activeLocale = options.find((option) => option.value === locale)

  return (
    <footer className="relative bg-white dark:bg-slate-950 border-t border-slate-200/80 dark:border-slate-800/80">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div className="grid grid-cols-2 md:grid-cols-5 gap-8 mb-12">
          <div className="col-span-2">
            <Link href="/" className="flex items-center gap-2.5 mb-4">
              <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-emerald-500/20">
                <span className="text-white font-black text-sm">L</span>
              </div>
              <div className="flex flex-col">
                <span className="font-black text-lg text-slate-900 dark:text-white leading-none">Leopardo</span>
                <span className="text-[9px] font-bold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">
                  {copy.nav.brandTagline}
                </span>
              </div>
            </Link>
            <p className="text-sm text-slate-500 dark:text-slate-400 leading-relaxed max-w-xs mb-6">{copy.footer.description}</p>
            <div className="flex items-center gap-4">
              {['X', 'Li', 'Gh'].map((social) => (
                <Link
                  key={social}
                  href="#"
                  className="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xs font-bold text-slate-500 hover:bg-emerald-100 hover:text-emerald-600 dark:hover:bg-emerald-900/30 dark:hover:text-emerald-400 transition-colors"
                >
                  {social}
                </Link>
              ))}
            </div>
          </div>

          {copy.footer.sections.map((section, index) => (
            <div key={`${section.title}-${index}`}>
              <h4 className="text-sm font-bold text-slate-900 dark:text-white mb-4 uppercase tracking-wider">{section.title}</h4>
              <ul className="space-y-2.5">
                {section.links.map((link, linkIndex) => {
                  const href = getFooterHref(index, linkIndex)

                  return (
                    <li key={`${section.title}-link-${linkIndex}`}>
                      <Link href={href} className="text-sm text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">
                        {link}
                      </Link>
                    </li>
                  )
                })}
              </ul>
            </div>
          ))}
        </div>

        <div className="pt-8 border-t border-slate-200/80 dark:border-slate-800/80 flex flex-col md:flex-row items-center justify-between gap-4">
          <p className="text-sm text-slate-500 dark:text-slate-400">
            &copy; {new Date().getFullYear()} Leopardo RH. {copy.footer.rights}
          </p>
          <div className="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <Globe className="w-4 h-4" />
            <span>{activeLocale?.nativeLabel ?? locale}</span>
          </div>
        </div>
      </div>
    </footer>
  )
}
