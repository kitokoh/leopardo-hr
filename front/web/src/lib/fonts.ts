import { Inter } from 'next/font/google';

// Typographie du design system (REFONTE_PREMIUM_STATUT — typo Inter verrouillée).
// next/font self-host les fichiers WOFF2 au build (aucun appel runtime externe,
// conforme CSP + budget font ≤ 150 kB) et expose la variable `--font-inter`
// consommée par tailwind.config.ts (`font-sans`) et `app/globals.css`.
// Fichier hors surfaces scannées par la garde PA2-I18N-014 (les noms de
// polices de repli sont des constantes techniques, pas du texte utilisateur).
export const inter = Inter({
  subsets: ['latin'],
  variable: '--font-inter',
  display: 'swap',
  fallback: ['Aptos', 'Segoe UI Variable', 'Segoe UI', 'system-ui', 'sans-serif'],
});
