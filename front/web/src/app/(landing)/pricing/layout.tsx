import { headers } from 'next/headers';
import type { Metadata } from 'next';
import { SITE_URL } from '@/lib/site-url';
import {
  generateMetadata as generateSEOMetadata,
  pageMetadata,
  generateFAQSchema,
} from '@/modules/vitrine/lib/seo';
import { t } from '@/lib/i18n/locale-catalog';
import type { AppLocale } from '@/lib/i18n';

// #3487 — la meta description pricing est localisée par requête (Accept-Language
// SSR, même logique que le root layout #2719) au lieu de t('fr', …) codé en dur.
function resolveSsrLang(acceptLanguage: string | null): AppLocale {
  const base = (acceptLanguage ?? '')
    .split(',')[0]
    .trim()
    .toLowerCase()
    .slice(0, 2);

  return (['fr', 'en', 'ar', 'tr'] as const).includes(base as AppLocale)
    ? (base as AppLocale)
    : 'fr';
}

export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const locale = resolveSsrLang(headerList.get('accept-language'));

  return generateSEOMetadata({
    title: pageMetadata.pricing.title,
    description:
      locale === 'fr'
        ? pageMetadata.pricing.description
        : t(locale, 'seo.pricing.description', pageMetadata.pricing.description),
    keywords: pageMetadata.pricing.keywords,
    ogImage: pageMetadata.pricing.ogImage,
    ogType: 'website',
    canonical: `${SITE_URL}/pricing`,
    robots: 'index, follow',
  });
}

export default function PricingLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  // FAQ Schema for pricing page
  const faqSchema = generateFAQSchema([
    {
      question: 'Puis-je changer de plan?',
      answer:
        'Oui, vous pouvez changer de plan à tout moment. Les changements prendront effet au prochain cycle de facturation.',
    },
    {
      question: 'Essai gratuit inclus?',
      answer: 'Oui, tous les plans incluent un essai gratuit de 14 jours sans carte bancaire requise.',
    },
    {
      question: 'Contrat long terme?',
      answer:
        'Nous proposons des contrats mensuels ou annuels. Les contrats annuels bénéficient d\'une réduction de 20%.',
    },
    {
      question: 'Support client disponible?',
      answer:
        'Oui, nous offrons un support email pour tous les plans et un support prioritaire pour les plans Operations et Enterprise.',
    },
    {
      question: 'Données sécurisées?',
      answer:
        'Oui, toutes les données sont chiffrées avec AES-256 et stockées sur des serveurs sécurisés conformes à la RGPD.',
    },
  ]);

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(faqSchema) }}
      />
      {children}
    </>
  );
}
