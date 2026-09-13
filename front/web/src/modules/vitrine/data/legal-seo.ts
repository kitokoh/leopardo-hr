/**
 * Métadonnées SEO des pages légales (`/terms`, `/privacy`).
 *
 * #AI-SEO (audit 2026-09-13) : ces pages sont **FR uniquement** (hors matcher
 * `x-vitrine-lang`) et portaient leur titre/description en clair dans
 * `src/app/**` — une surface surveillée par la garde i18n PA2-I18N-014, où
 * toute correction de libellé (marque dupliquée dans le `<title>`, accents
 * manquants) était comptée comme une nouvelle chaîne codée en dur. Le contenu
 * vit désormais ici, comme le reste de la copie vitrine
 * (`src/modules/vitrine/data/`), et les pages ne portent plus que la mécanique.
 *
 * Corrigé au passage : le `<title>` incluait déjà « | Leopardo RH » alors que
 * le template du layout racine l'ajoute — d'où un doublon en production
 * (« … | Leopardo RH | Leopardo RH »).
 */
export type LegalSeo = {
  title: string;
  description: string;
};

export const legalPageSeo: Record<'terms' | 'privacy', LegalSeo> = {
  terms: {
    title: "Conditions générales d'utilisation",
    description:
      "Conditions générales d'utilisation multilingues de Leopardo RH pour les clients, administrateurs, managers, employés et intégrateurs.",
  },
  privacy: {
    title: 'Politique de confidentialité',
    description:
      'Politique de confidentialité multilingue de Leopardo RH : données RH, conformité, droits des utilisateurs et sécurité.',
  },
};
