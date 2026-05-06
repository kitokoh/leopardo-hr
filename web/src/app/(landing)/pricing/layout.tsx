import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata, generateFAQSchema } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.pricing.title,
  description: pageMetadata.pricing.description,
  keywords: pageMetadata.pricing.keywords,
  ogImage: pageMetadata.pricing.ogImage,
  ogType: 'website',
  canonical: 'https://leopardo.com/pricing',
  robots: 'index, follow',
});

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
        'Oui, nous offrons un support email pour tous les plans et un support prioritaire pour les plans Business et Enterprise.',
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
