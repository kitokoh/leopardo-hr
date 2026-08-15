import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, localizedPageMetadata, resolveSsrLang, pageMetadata, generateFAQSchema } from '@/modules/vitrine/lib/seo';

export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const lang = headerList.get('x-lang') ?? resolveSsrLang(headerList.get('accept-language'));
  const meta = localizedPageMetadata('faq', lang);
  return generateSEOMetadata({    title: meta.title,
    description: meta.description,
  keywords: pageMetadata.faq.keywords,
  ogImage: pageMetadata.faq.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/faq`,
});
}

export default function FaqLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const faqSchema = generateFAQSchema([
    {
      question: 'Leopardo RH propose-t-il un essai gratuit ?',
      answer: 'Oui, un essai guidé gratuit de 14 jours est disponible sans carte bancaire.',
    },
    {
      question: 'Mes donnees sont-elles securisees ?',
      answer: 'Oui, toutes les donnees sont chiffrees (AES-256) et isolees par entreprise (multi-tenant).',
    },
    {
      question: 'Puis-je integrer mes pointeuses biometriques existantes ?',
      answer: 'Oui, Leopardo RH prend en charge nativement les dispositifs ZKTeco via synchronisation automatique.',
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
