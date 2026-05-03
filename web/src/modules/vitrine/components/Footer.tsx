'use client';

import Link from 'next/link';
import { Globe } from 'lucide-react';

const footerLinks = [
  { title: 'Produit', links: ['Fonctionnalites', 'Tarifs', 'Integrations', 'API', 'Changelog'] },
  { title: 'Ressources', links: ['Documentation', 'Blog', 'Support', 'Status', 'Communaute'] },
  { title: 'Legal', links: ['Confidentialite', 'CGU', 'Mentions legales', 'RGPD'] },
];

export function Footer() {
  return (
    <footer className="relative bg-white dark:bg-slate-950 border-t border-slate-200/80 dark:border-slate-800/80">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div className="grid grid-cols-2 md:grid-cols-5 gap-8 mb-12">
          {/* Brand */}
          <div className="col-span-2">
            <Link href="/" className="flex items-center gap-2.5 mb-4">
              <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-emerald-500/20">
                <span className="text-white font-black text-sm">L</span>
              </div>
              <div className="flex flex-col">
                <span className="font-black text-lg text-slate-900 dark:text-white leading-none">Leopardo</span>
                <span className="text-[9px] font-bold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">RH Platform</span>
              </div>
            </Link>
            <p className="text-sm text-slate-500 dark:text-slate-400 leading-relaxed max-w-xs mb-6">
              La solution moderne et intelligente pour gerer vos ressources humaines a l&apos;ere de l&apos;IA.
            </p>
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

          {/* Links */}
          {footerLinks.map((section, i) => (
            <div key={i}>
              <h4 className="text-sm font-bold text-slate-900 dark:text-white mb-4 uppercase tracking-wider">{section.title}</h4>
              <ul className="space-y-2.5">
                {section.links.map((link, j) => (
                  <li key={j}>
                    <Link href="#" className="text-sm text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">
                      {link}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        {/* Bottom */}
        <div className="pt-8 border-t border-slate-200/80 dark:border-slate-800/80 flex flex-col md:flex-row items-center justify-between gap-4">
          <p className="text-sm text-slate-500 dark:text-slate-400">
            &copy; {new Date().getFullYear()} Leopardo RH. Tous droits reserves.
          </p>
          <div className="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <Globe className="w-4 h-4" />
            <span>Francais</span>
          </div>
        </div>
      </div>
    </footer>
  );
}
