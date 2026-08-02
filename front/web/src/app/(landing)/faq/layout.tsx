import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata, generateFAQSchema } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.faq.title,
  description: pageMetadata.faq.description,
  keywords: pageMetadata.faq.keywords,
  ogImage: pageMetadata.faq.ogImage,
  ogType: 'website',
  canonical: 'https://leopardo.com/faq',
});

export default function FaqLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const faqSchema = generateFAQSchema([
    {
      question: 'Leopardo RH propose-t-il un essai gratuit ?',
      answer: 'Oui, un essai guide gratuit de 14 jours est disponible sans carte bancaire.',
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
