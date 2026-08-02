'use client';

import { Fragment, useState } from 'react';
import Link from 'next/link';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Navbar,
  Footer,
  useScrollReveal,
} from '@/modules/vitrine';
import { getPricingPlans } from '@/modules/vitrine/data/pricing';
import { CURRENCY_OPTIONS, DEFAULT_CURRENCY_OPTION, convertEurPrice, type CurrencyOption } from '@/modules/vitrine/data/currency';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import type { AppLocale } from '@/lib/i18n';
import {
  Check,
  X,
  Zap,
  ArrowRight,
  ShieldCheck,
  Users,
  Star,
  ChevronDown,
  MessageCircle,
  Building2,
  Rocket,
  Crown,
  Gift,
} from 'lucide-react';

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   TYPES
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
type ComparisonFeature = {
  name: string;
  free: boolean | string;
  starter: boolean | string;
  business: boolean | string;
  enterprise: boolean | string;
};

type ComparisonCategory = {
  category: string;
  features: ComparisonFeature[];
};

type FaqItem = {
  id: string;
  question: string;
  answer: string;
  category: string;
};

type PricingPageCopy = {
  hero: { headline: string; subheadline: string; primary: string; secondary: string; badge: string };
  plans: { title: string; subtitle: string; badge: string; monthly: string; annual: string; savings: string; customPrice: string; periodMonthly: string; periodAnnual: string; trialNote: string };
  currency: { label: string; approx: string };
  trust: { items: string[] };
  comparison: { badge: string; title: string; subtitle: string; featureColumn: string; categories: ComparisonCategory[] };
  faq: { title: string; subtitle: string; badge: string; all: string; categories: string[]; items: FaqItem[] };
  cta: { badge: string; headline: string; subheadline: string; primary: string; secondary: string };
};

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   COPY (fr / en / tr / ar)
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
const pricingPageCopy: Record<AppLocale, PricingPageCopy> = {
  fr: {
    hero: {
      badge: 'Tarification transparente',
      headline: 'Des tarifs pensÃ©s pour les Ã©quipes terrain',
      subheadline: 'Commencez gratuitement â€” sans carte bancaire â€” et passez Ã  un plan payant quand vous Ãªtes prÃªt.',
      primary: 'Commencer gratuitement',
      secondary: 'Parler Ã  un expert',
    },
    plans: {
      badge: 'Nos plans',
      title: 'Un plan pour chaque Ã©tape de votre croissance',
      subtitle: 'Commencez petit, montez en puissance sans changer de plateforme.',
      monthly: 'Mensuel',
      annual: 'Annuel',
      savings: 'Ã‰conomisez 20%',
      customPrice: 'Sur devis',
      periodMonthly: '/mois',
      periodAnnual: '/mois facturÃ© annuellement',
      trialNote: '30 jours offerts Â· Aucune CB requise',
    },
    currency: {
      label: 'Afficher les prix en',
      approx: 'Conversion approximative depuis le prix de rÃ©fÃ©rence en EUR ; le tarif contractuel reste fixÃ© en EUR.',
    },
    trust: {
      items: [
        'Plan gratuit sans CB',
        'Support inclus dÃ¨s le premier jour',
        'DonnÃ©es hÃ©bergÃ©es en Europe',
        'RÃ©siliation Ã  tout moment',
      ],
    },
    comparison: {
      badge: 'Comparaison complÃ¨te',
      title: 'Tout ce qui est inclus',
      subtitle: 'par plan',
      featureColumn: 'FonctionnalitÃ©',
      categories: [
        {
          category: 'Gestion RH',
          features: [
            { name: 'Pointage web & mobile', free: 'Web seulement', starter: true, business: true, enterprise: true },
            { name: 'Absences & congÃ©s', free: true, starter: true, business: true, enterprise: true },
            { name: 'Calendrier partagÃ©', free: false, starter: true, business: true, enterprise: true },
            { name: 'Onboarding guidÃ©', free: false, starter: true, business: true, enterprise: true },
            { name: 'Ã‰valuations & performance', free: false, starter: false, business: true, enterprise: true },
            { name: 'Organigramme dynamique', free: false, starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'Paie & finance',
          features: [
            { name: 'Calcul automatisÃ© de la paie', free: false, starter: true, business: true, enterprise: true },
            { name: 'Bulletins de paie PDF', free: false, starter: true, business: true, enterprise: true },
            { name: 'Exports comptables', free: false, starter: false, business: true, enterprise: true },
            { name: 'Avances sur salaire', free: false, starter: false, business: true, enterprise: true },
            { name: 'Multi-pays & multi-devises', free: false, starter: false, business: false, enterprise: true },
            { name: 'ConformitÃ© lÃ©gale avancÃ©e', free: false, starter: false, business: false, enterprise: true },
          ],
        },
        {
          category: 'Terrain & mobile',
          features: [
            { name: 'App mobile Employee', free: true, starter: true, business: true, enterprise: true },
            { name: 'App mobile Manager', free: false, starter: true, business: true, enterprise: true },
            { name: 'Mode hors-ligne', free: false, starter: true, business: true, enterprise: true },
            { name: 'IntÃ©gration ZKTeco biomÃ©trie', free: false, starter: false, business: true, enterprise: true },
            { name: 'Kiosque RH dÃ©diÃ©', free: false, starter: false, business: true, enterprise: true },
            { name: 'GPS & gÃ©ofencing', free: false, starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'SÃ©curitÃ© & intÃ©grations',
          features: [
            { name: 'Coffre-fort documentaire', free: false, starter: false, business: true, enterprise: true },
            { name: 'API REST & Webhooks', free: false, starter: false, business: true, enterprise: true },
            { name: 'SSO SAML / OIDC', free: false, starter: false, business: false, enterprise: true },
            { name: 'Audit trail immuable', free: false, starter: false, business: false, enterprise: true },
            { name: 'SchÃ©ma PostgreSQL isolÃ©', free: false, starter: false, business: false, enterprise: true },
            { name: 'SLA dÃ©diÃ© & support prioritaire', free: false, starter: false, business: false, enterprise: true },
          ],
        },
      ],
    },
    faq: {
      badge: 'FAQ tarifs',
      title: 'Questions frÃ©quentes',
      subtitle: 'Les points Ã  vÃ©rifier avant de dÃ©marrer',
      all: 'Tous',
      categories: ['Facturation', 'Essai', 'Support', 'SÃ©curitÃ©', 'Technique'],
      items: [
        { id: 'free-plan', question: 'Le plan Free est-il vraiment gratuit ?', answer: 'Oui, 100% gratuit. Aucune carte bancaire requise, jamais. AccÃ¨s immÃ©diat jusqu\'Ã  5 employÃ©s. Vous pouvez passer Ã  un plan payant Ã  tout moment.', category: 'Essai' },
        { id: 'change-plan', question: 'Puis-je changer de plan ?', answer: 'Oui, Ã  tout moment. Upgrade immÃ©diat, downgrade au prochain cycle. Aucun frais cachÃ©.', category: 'Facturation' },
        { id: 'per-employee', question: 'Comment fonctionne la facturation par employÃ© ?', answer: 'Chaque plan inclut un socle fixe + un tarif par employÃ© actif (qui a pointÃ© au moins une fois dans le mois). Les employÃ©s inactifs ne sont pas comptÃ©s.', category: 'Facturation' },
        { id: 'free-trial', question: 'L\'essai payant est-il vraiment gratuit ?', answer: 'Oui. 30 jours complets avec toutes les fonctionnalitÃ©s du plan Pilot. Aucune carte bancaire requise pour s\'inscrire.', category: 'Essai' },
        { id: 'trial-to-paid', question: 'Que se passe-t-il Ã  la fin de l\'essai ?', answer: 'Vous choisissez un plan ou vos donnÃ©es restent archivÃ©es 30 jours supplÃ©mentaires. Aucune facturation automatique sans votre accord.', category: 'Essai' },
        { id: 'support', question: 'Quel support est disponible ?', answer: 'Free : communautÃ©. Starter : email sous 48h. Business : prioritÃ© 24h. Enterprise : account manager dÃ©diÃ© + SLA contractuel.', category: 'Support' },
        { id: 'data-location', question: 'OÃ¹ sont hÃ©bergÃ©es mes donnÃ©es ?', answer: 'En Europe (Render EU / Supabase EU). Chiffrement AES-256 au repos, TLS 1.3 en transit. Isolation par tenant garantie.', category: 'SÃ©curitÃ©' },
        { id: 'gdpr', question: 'ÃŠtes-vous conformes RGPD ?', answer: 'Oui. DPA disponible, donnÃ©es exclusivement en Europe, droit Ã  l\'effacement implÃ©mentÃ©, exports de donnÃ©es sur demande.', category: 'SÃ©curitÃ©' },
        { id: 'api', question: 'L\'API est-elle disponible sur le plan Free ?', answer: 'L\'API REST et les webhooks sont disponibles Ã  partir du plan Business. Sur Free et Starter, vous pouvez exporter vos donnÃ©es en CSV/Excel.', category: 'Technique' },
      ],
    },
    cta: {
      badge: 'PrÃªt Ã  dÃ©marrer',
      headline: 'Lancez vos RH terrain dÃ¨s aujourd\'hui',
      subheadline: 'Rejoignez les Ã©quipes qui ont rÃ©duit leur temps de paie de 2h Ã  8 minutes.',
      primary: 'DÃ©marrer gratuitement',
      secondary: 'Contacter les ventes',
    },
  },
  en: {
    hero: {
      badge: 'Transparent pricing',
      headline: 'Pricing built for field HR teams',
      subheadline: 'Start for free â€” no credit card â€” and upgrade when you are ready.',
      primary: 'Start for free',
      secondary: 'Talk to an expert',
    },
    plans: {
      badge: 'Our plans',
      title: 'A plan for every stage of your growth',
      subtitle: 'Start small, scale up without switching platforms.',
      monthly: 'Monthly',
      annual: 'Annual',
      savings: 'Save 20%',
      customPrice: 'Custom',
      periodMonthly: '/month',
      periodAnnual: '/month billed annually',
      trialNote: '30 days free Â· No credit card required',
    },
    currency: {
      label: 'Show prices in',
      approx: 'Approximate conversion from the EUR reference price; the contractual price stays denominated in EUR.',
    },
    trust: {
      items: [
        'Free plan â€” no credit card',
        'Support included from day one',
        'Data hosted in Europe',
        'Cancel anytime',
      ],
    },
    comparison: {
      badge: 'Full comparison',
      title: 'Everything that\'s included',
      subtitle: 'per plan',
      featureColumn: 'Feature',
      categories: [
        {
          category: 'HR management',
          features: [
            { name: 'Web & mobile attendance', free: 'Web only', starter: true, business: true, enterprise: true },
            { name: 'Absences & leave', free: true, starter: true, business: true, enterprise: true },
            { name: 'Shared calendar', free: false, starter: true, business: true, enterprise: true },
            { name: 'Guided onboarding', free: false, starter: true, business: true, enterprise: true },
            { name: 'Reviews & performance', free: false, starter: false, business: true, enterprise: true },
            { name: 'Dynamic org chart', free: false, starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'Payroll & finance',
          features: [
            { name: 'Automated payroll', free: false, starter: true, business: true, enterprise: true },
            { name: 'PDF pay slips', free: false, starter: true, business: true, enterprise: true },
            { name: 'Accounting exports', free: false, starter: false, business: true, enterprise: true },
            { name: 'Salary advances', free: false, starter: false, business: true, enterprise: true },
            { name: 'Multi-country & currency', free: false, starter: false, business: false, enterprise: true },
            { name: 'Advanced legal compliance', free: false, starter: false, business: false, enterprise: true },
          ],
        },
        {
          category: 'Field & mobile',
          features: [
            { name: 'Employee mobile app', free: true, starter: true, business: true, enterprise: true },
            { name: 'Manager mobile app', free: false, starter: true, business: true, enterprise: true },
            { name: 'Offline mode', free: false, starter: true, business: true, enterprise: true },
            { name: 'ZKTeco biometrics', free: false, starter: false, business: true, enterprise: true },
            { name: 'Dedicated HR kiosk', free: false, starter: false, business: true, enterprise: true },
            { name: 'GPS & geofencing', free: false, starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'Security & integrations',
          features: [
            { name: 'Document vault', free: false, starter: false, business: true, enterprise: true },
            { name: 'REST API & Webhooks', free: false, starter: false, business: true, enterprise: true },
            { name: 'SSO SAML / OIDC', free: false, starter: false, business: false, enterprise: true },
            { name: 'Immutable audit trail', free: false, starter: false, business: false, enterprise: true },
            { name: 'Isolated PostgreSQL schema', free: false, starter: false, business: false, enterprise: true },
            { name: 'Dedicated SLA & support', free: false, starter: false, business: false, enterprise: true },
          ],
        },
      ],
    },
    faq: {
      badge: 'Pricing FAQ',
      title: 'Frequently asked questions',
      subtitle: 'What to check before you start',
      all: 'All',
      categories: ['Billing', 'Trial', 'Support', 'Security', 'Technical'],
      items: [
        { id: 'free-plan', question: 'Is the Free plan really free?', answer: 'Yes, 100% free. No credit card ever required. Immediate access for up to 5 employees. Upgrade to a paid plan anytime.', category: 'Trial' },
        { id: 'change-plan', question: 'Can I change plan later?', answer: 'Yes, anytime. Upgrades are instant, downgrades apply at the next cycle. No hidden fees.', category: 'Billing' },
        { id: 'per-employee', question: 'How does per-employee billing work?', answer: 'Each plan includes a base fee plus a per-active-employee rate (employees who clocked in at least once that month). Inactive employees are not charged.', category: 'Billing' },
        { id: 'free-trial', question: 'Is the paid trial really free?', answer: 'Yes. 30 full days with all features of the Pilot plan. No credit card needed to sign up.', category: 'Trial' },
        { id: 'trial-to-paid', question: 'What happens when the trial ends?', answer: 'You choose a plan or your data stays archived for 30 more days. No automatic billing without your consent.', category: 'Trial' },
        { id: 'support', question: 'What support is available?', answer: 'Free: community. Starter: email within 48h. Business: priority 24h. Enterprise: dedicated account manager + contractual SLA.', category: 'Support' },
        { id: 'data-location', question: 'Where is my data hosted?', answer: 'In Europe (Render EU / Supabase EU). AES-256 encryption at rest, TLS 1.3 in transit. Tenant isolation guaranteed.', category: 'Security' },
        { id: 'gdpr', question: 'Are you GDPR compliant?', answer: 'Yes. DPA available, data exclusively in Europe, right to erasure implemented, data exports on request.', category: 'Security' },
        { id: 'api', question: 'Is the API available on the Free plan?', answer: 'REST API and webhooks are available from the Business plan. On Free / Starter you can export data as CSV/Excel.', category: 'Technical' },
      ],
    },
    cta: {
      badge: 'Ready to start',
      headline: 'Launch your field HR today',
      subheadline: 'Join teams that reduced payroll time from 2 hours to 8 minutes.',
      primary: 'Start for free',
      secondary: 'Contact sales',
    },
  },
  tr: {
    hero: {
      badge: 'Åžeffaf fiyatlandÄ±rma',
      headline: 'Saha HR ekipleri iÃ§in fiyatlandÄ±rma',
      subheadline: 'Ãœcretsiz baÅŸlayÄ±n â€” kredi kartÄ± gerekmez â€” hazÄ±r olduÄŸunuzda yÃ¼kseltin.',
      primary: 'Ãœcretsiz baÅŸla',
      secondary: 'Uzmanla konuÅŸ',
    },
    plans: {
      badge: 'PlanlarÄ±mÄ±z',
      title: 'BÃ¼yÃ¼menizin her aÅŸamasÄ± iÃ§in bir plan',
      subtitle: 'KÃ¼Ã§Ã¼k baÅŸlayÄ±n, platform deÄŸiÅŸtirmeden bÃ¼yÃ¼yÃ¼n.',
      monthly: 'AylÄ±k',
      annual: 'YÄ±llÄ±k',
      savings: '%20 tasarruf',
      customPrice: 'Teklif alÄ±n',
      periodMonthly: '/ay',
      periodAnnual: '/ay yÄ±llÄ±k faturalama',
      trialNote: '30 gÃ¼n Ã¼cretsiz Â· Kredi kartÄ± gerekmez',
    },
    currency: {
      label: 'FiyatlarÄ± ÅŸu para birimiyle gÃ¶ster',
      approx: 'EUR referans fiyatÄ±ndan yaklaÅŸÄ±k dÃ¶nÃ¼ÅŸÃ¼m; sÃ¶zleÅŸme tutarÄ± EUR olarak kalÄ±r.',
    },
    trust: {
      items: [
        'Ãœcretsiz plan â€” kredi kartÄ± yok',
        'Ä°lk gÃ¼nden destek dahil',
        'Avrupa\'da barÄ±ndÄ±rÄ±lan veriler',
        'Ä°stediÄŸiniz zaman iptal',
      ],
    },
    comparison: {
      badge: 'Tam karÅŸÄ±laÅŸtÄ±rma',
      title: 'Dahil olan her ÅŸey',
      subtitle: 'plan bazÄ±nda',
      featureColumn: 'Ã–zellik',
      categories: [
        {
          category: 'Ä°K YÃ¶netimi',
          features: [
            { name: 'Web & mobil devam takibi', free: 'YalnÄ±zca web', starter: true, business: true, enterprise: true },
            { name: 'DevamsÄ±zlÄ±k & izin', free: true, starter: true, business: true, enterprise: true },
            { name: 'PaylaÅŸÄ±lan takvim', free: false, starter: true, business: true, enterprise: true },
            { name: 'Rehberli iÅŸe alÄ±m', free: false, starter: true, business: true, enterprise: true },
            { name: 'DeÄŸerlendirme & performans', free: false, starter: false, business: true, enterprise: true },
            { name: 'Dinamik organizasyon ÅŸemasÄ±', free: false, starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'Bordro & Finans',
          features: [
            { name: 'Otomatik bordro hesabÄ±', free: false, starter: true, business: true, enterprise: true },
            { name: 'PDF bordro dÃ¶kÃ¼mÃ¼', free: false, starter: true, business: true, enterprise: true },
            { name: 'Muhasebe dÄ±ÅŸa aktarÄ±mÄ±', free: false, starter: false, business: true, enterprise: true },
            { name: 'MaaÅŸ avansÄ±', free: false, starter: false, business: true, enterprise: true },
            { name: 'Ã‡ok Ã¼lke & Ã§ok para birimi', free: false, starter: false, business: false, enterprise: true },
            { name: 'GeliÅŸmiÅŸ yasal uyumluluk', free: false, starter: false, business: false, enterprise: true },
          ],
        },
        {
          category: 'Saha & Mobil',
          features: [
            { name: 'Ã‡alÄ±ÅŸan mobil uygulamasÄ±', free: true, starter: true, business: true, enterprise: true },
            { name: 'YÃ¶netici mobil uygulamasÄ±', free: false, starter: true, business: true, enterprise: true },
            { name: 'Ã‡evrimdÄ±ÅŸÄ± mod', free: false, starter: true, business: true, enterprise: true },
            { name: 'ZKTeco biyometri entegrasyonu', free: false, starter: false, business: true, enterprise: true },
            { name: 'Ã–zel HR kiosk', free: false, starter: false, business: true, enterprise: true },
            { name: 'GPS & coÄŸrafi sÄ±nÄ±r', free: false, starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'GÃ¼venlik & Entegrasyonlar',
          features: [
            { name: 'Belge kasasÄ±', free: false, starter: false, business: true, enterprise: true },
            { name: 'REST API & Webhook', free: false, starter: false, business: true, enterprise: true },
            { name: 'SSO SAML / OIDC', free: false, starter: false, business: false, enterprise: true },
            { name: 'DeÄŸiÅŸtirilemez denetim kaydÄ±', free: false, starter: false, business: false, enterprise: true },
            { name: 'Ä°zole PostgreSQL ÅŸemasÄ±', free: false, starter: false, business: false, enterprise: true },
            { name: 'Ã–zel SLA & destek', free: false, starter: false, business: false, enterprise: true },
          ],
        },
      ],
    },
    faq: {
      badge: 'Fiyat SSS',
      title: 'SÄ±k sorulan sorular',
      subtitle: 'BaÅŸlamadan Ã¶nce kontrol edilecek noktalar',
      all: 'TÃ¼mÃ¼',
      categories: ['Faturalama', 'Deneme', 'Destek', 'GÃ¼venlik', 'Teknik'],
      items: [
        { id: 'free-plan', question: 'Free plan gerÃ§ekten Ã¼cretsiz mi?', answer: 'Evet, %100 Ã¼cretsiz. HiÃ§bir zaman kredi kartÄ± gerekmez. 5 Ã§alÄ±ÅŸana kadar anÄ±nda eriÅŸim. Ä°stediÄŸiniz zaman Ã¼cretli plana geÃ§in.', category: 'Deneme' },
        { id: 'change-plan', question: 'PlanÄ± deÄŸiÅŸtirebilir miyim?', answer: 'Evet, istediÄŸiniz zaman. YÃ¼kseltme anÄ±nda, dÃ¼ÅŸÃ¼rme bir sonraki dÃ¶nemde uygulanÄ±r. Gizli Ã¼cret yoktur.', category: 'Faturalama' },
        { id: 'per-employee', question: 'Ã‡alÄ±ÅŸan baÅŸÄ± faturalama nasÄ±l Ã§alÄ±ÅŸÄ±r?', answer: 'Her plan sabit bir temel Ã¼cret artÄ± aktif Ã§alÄ±ÅŸan baÅŸÄ±na Ã¼cret iÃ§erir. O ay en az bir kez giriÅŸ yapan Ã§alÄ±ÅŸanlar aktif sayÄ±lÄ±r.', category: 'Faturalama' },
        { id: 'free-trial', question: 'Ãœcretli deneme gerÃ§ekten Ã¼cretsiz mi?', answer: 'Evet. Pilot planÄ±n tÃ¼m Ã¶zellikleriyle 30 tam gÃ¼n. Kaydolmak iÃ§in kredi kartÄ± gerekmez.', category: 'Deneme' },
        { id: 'trial-to-paid', question: 'Deneme bitince ne olur?', answer: 'Bir plan seÃ§ersiniz ya da verileriniz 30 gÃ¼n daha arÅŸivlenir. OnayÄ±nÄ±z olmadan otomatik faturalama yapÄ±lmaz.', category: 'Deneme' },
        { id: 'support', question: 'Hangi destek saÄŸlanÄ±r?', answer: 'Free: topluluk. Starter: 48 saatte e-posta. Business: 24 saatte Ã¶ncelikli yanÄ±t. Enterprise: Ã¶zel hesap yÃ¶neticisi + sÃ¶zleÅŸmesel SLA.', category: 'Destek' },
        { id: 'data-location', question: 'Verilerim nerede barÄ±ndÄ±rÄ±lÄ±r?', answer: 'Avrupa\'da (Render EU / Supabase EU). DuraÄŸan veriler AES-256, iletimde TLS 1.3. Tenant izolasyonu garantili.', category: 'GÃ¼venlik' },
        { id: 'gdpr', question: 'KVKK uyumlu musunuz?', answer: 'Evet. DPA mevcut, veriler yalnÄ±zca Avrupa\'da, silme hakkÄ± uygulanmÄ±ÅŸ, talep Ã¼zerine veri dÄ±ÅŸa aktarÄ±mÄ±.', category: 'GÃ¼venlik' },
        { id: 'api', question: 'Free planda API kullanÄ±labilir mi?', answer: 'REST API ve webhook\'lar Business planÄ±ndan itibaren kullanÄ±labilir. Free ve Starter\'da verileri CSV/Excel olarak dÄ±ÅŸa aktarabilirsiniz.', category: 'Teknik' },
      ],
    },
    cta: {
      badge: 'BaÅŸlamaya hazÄ±r',
      headline: 'Saha Ä°K\'nÄ±zÄ± bugÃ¼n baÅŸlatÄ±n',
      subheadline: 'Bordro sÃ¼resini 2 saatten 8 dakikaya dÃ¼ÅŸÃ¼ren ekiplere katÄ±lÄ±n.',
      primary: 'Ãœcretsiz baÅŸla',
      secondary: 'SatÄ±ÅŸ ekibine ulaÅŸ',
    },
  },
  ar: {
    hero: {
      badge: 'ØªØ³Ø¹ÙŠØ± Ø´ÙØ§Ù',
      headline: 'Ø£Ø³Ø¹Ø§Ø± Ù…ØµÙ…Ù…Ø© Ù„ÙØ±Ù‚ Ø§Ù„Ù…ÙˆØ§Ø±Ø¯ Ø§Ù„Ø¨Ø´Ø±ÙŠØ© Ø§Ù„Ù…ÙŠØ¯Ø§Ù†ÙŠØ©',
      subheadline: 'Ø§Ø¨Ø¯Ø£ Ù…Ø¬Ø§Ù†Ù‹Ø§ â€” Ø¨Ø¯ÙˆÙ† Ø¨Ø·Ø§Ù‚Ø© Ø§Ø¦ØªÙ…Ø§Ù† â€” ÙˆØ§Ù†ØªÙ‚Ù„ Ø¥Ù„Ù‰ Ø®Ø·Ø© Ù…Ø¯ÙÙˆØ¹Ø© Ù…ØªÙ‰ ÙƒÙ†Øª Ù…Ø³ØªØ¹Ø¯Ù‹Ø§.',
      primary: 'Ø§Ø¨Ø¯Ø£ Ù…Ø¬Ø§Ù†Ù‹Ø§',
      secondary: 'ØªØ­Ø¯Ø« Ù…Ø¹ Ø®Ø¨ÙŠØ±',
    },
    plans: {
      badge: 'Ø®Ø·Ø·Ù†Ø§',
      title: 'Ø®Ø·Ø© Ù„ÙƒÙ„ Ù…Ø±Ø­Ù„Ø© Ù…Ù† Ù…Ø±Ø§Ø­Ù„ Ù†Ù…ÙˆÙƒ',
      subtitle: 'Ø§Ø¨Ø¯Ø£ ØµØºÙŠØ±Ù‹Ø§ØŒ ØªÙˆØ³Ø¹ Ø¯ÙˆÙ† ØªØºÙŠÙŠØ± Ø§Ù„Ù…Ù†ØµØ©.',
      monthly: 'Ø´Ù‡Ø±ÙŠ',
      annual: 'Ø³Ù†ÙˆÙŠ',
      savings: 'ÙˆÙÙ‘Ø± 20%',
      customPrice: 'Ø­Ø³Ø¨ Ø§Ù„Ø·Ù„Ø¨',
      periodMonthly: '/Ø´Ù‡Ø±',
      periodAnnual: '/Ø´Ù‡Ø± Ù…Ø¹ ÙÙˆØªØ±Ø© Ø³Ù†ÙˆÙŠØ©',
      trialNote: '30 ÙŠÙˆÙ…Ù‹Ø§ Ù…Ø¬Ø§Ù†Ù‹Ø§ Â· Ù„Ø§ Ø¨Ø·Ø§Ù‚Ø© Ø§Ø¦ØªÙ…Ø§Ù† Ù…Ø·Ù„ÙˆØ¨Ø©',
    },
    currency: {
      label: 'Ø¹Ø±Ø¶ Ø§Ù„Ø£Ø³Ø¹Ø§Ø± Ø¨Ø¹Ù…Ù„Ø©',
      approx: 'ØªØ­ÙˆÙŠÙ„ ØªÙ‚Ø±ÙŠØ¨ÙŠ Ù…Ù† Ø§Ù„Ø³Ø¹Ø± Ø§Ù„Ù…Ø±Ø¬Ø¹ÙŠ Ø¨Ø§Ù„ÙŠÙˆØ±ÙˆØ› ÙŠØ¨Ù‚Ù‰ Ø§Ù„Ø³Ø¹Ø± Ø§Ù„ØªØ¹Ø§Ù‚Ø¯ÙŠ Ù…Ø­Ø¯Ø¯Ù‹Ø§ Ø¨Ø§Ù„ÙŠÙˆØ±Ùˆ.',
    },
    trust: {
      items: [
        'Ø®Ø·Ø© Ù…Ø¬Ø§Ù†ÙŠØ© Ø¨Ù„Ø§ Ø¨Ø·Ø§Ù‚Ø© Ø§Ø¦ØªÙ…Ø§Ù†',
        'Ø¯Ø¹Ù… Ù…Ø´Ù…ÙˆÙ„ Ù…Ù† Ø§Ù„ÙŠÙˆÙ… Ø§Ù„Ø£ÙˆÙ„',
        'Ø¨ÙŠØ§Ù†Ø§Øª Ù…Ø³ØªØ¶Ø§ÙØ© ÙÙŠ Ø£ÙˆØ±ÙˆØ¨Ø§',
        'Ø¥Ù„ØºØ§Ø¡ ÙÙŠ Ø£ÙŠ ÙˆÙ‚Øª',
      ],
    },
    comparison: {
      badge: 'Ù…Ù‚Ø§Ø±Ù†Ø© ÙƒØ§Ù…Ù„Ø©',
      title: 'ÙƒÙ„ Ù…Ø§ Ù‡Ùˆ Ù…Ø´Ù…ÙˆÙ„',
      subtitle: 'Ø­Ø³Ø¨ Ø§Ù„Ø®Ø·Ø©',
      featureColumn: 'Ø§Ù„Ù…ÙŠØ²Ø©',
      categories: [
        {
          category: 'Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…ÙˆØ§Ø±Ø¯ Ø§Ù„Ø¨Ø´Ø±ÙŠØ©',
          features: [
            { name: 'ØªØªØ¨Ø¹ Ø§Ù„Ø­Ø¶ÙˆØ± ÙˆÙŠØ¨ ÙˆÙ…ÙˆØ¨Ø§ÙŠÙ„', free: 'ÙˆÙŠØ¨ ÙÙ‚Ø·', starter: true, business: true, enterprise: true },
            { name: 'Ø§Ù„ØºÙŠØ§Ø¨ ÙˆØ§Ù„Ø¥Ø¬Ø§Ø²Ø§Øª', free: true, starter: true, business: true, enterprise: true },
            { name: 'ØªÙ‚ÙˆÙŠÙ… Ù…Ø´ØªØ±Ùƒ', free: false, starter: true, business: true, enterprise: true },
            { name: 'Ø¥Ø¹Ø¯Ø§Ø¯ Ù…ÙˆØ¬Ù‘Ù‡', free: false, starter: true, business: true, enterprise: true },
            { name: 'Ø§Ù„ØªÙ‚ÙŠÙŠÙ…Ø§Øª ÙˆØ§Ù„Ø£Ø¯Ø§Ø¡', free: false, starter: false, business: true, enterprise: true },
            { name: 'Ù‡ÙŠÙƒÙ„ ØªÙ†Ø¸ÙŠÙ…ÙŠ Ø¯ÙŠÙ†Ø§Ù…ÙŠÙƒÙŠ', free: false, starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'Ø§Ù„Ø±ÙˆØ§ØªØ¨ ÙˆØ§Ù„Ù…Ø§Ù„ÙŠØ©',
          features: [
            { name: 'Ø­Ø³Ø§Ø¨ Ø±ÙˆØ§ØªØ¨ Ø¢Ù„ÙŠ', free: false, starter: true, business: true, enterprise: true },
            { name: 'Ù‚Ø³Ø§Ø¦Ù… Ø±ÙˆØ§ØªØ¨ PDF', free: false, starter: true, business: true, enterprise: true },
            { name: 'ØªØµØ¯ÙŠØ± Ù…Ø­Ø§Ø³Ø¨ÙŠ', free: false, starter: false, business: true, enterprise: true },
            { name: 'Ø³Ù„Ù Ø§Ù„Ø±ÙˆØ§ØªØ¨', free: false, starter: false, business: true, enterprise: true },
            { name: 'Ù…ØªØ¹Ø¯Ø¯ Ø§Ù„Ø¯ÙˆÙ„ ÙˆØ§Ù„Ø¹Ù…Ù„Ø§Øª', free: false, starter: false, business: false, enterprise: true },
            { name: 'Ø§Ù…ØªØ«Ø§Ù„ Ù‚Ø§Ù†ÙˆÙ†ÙŠ Ù…ØªÙ‚Ø¯Ù…', free: false, starter: false, business: false, enterprise: true },
          ],
        },
        {
          category: 'Ø§Ù„Ù…ÙŠØ¯Ø§Ù† ÙˆØ§Ù„Ù…ÙˆØ¨Ø§ÙŠÙ„',
          features: [
            { name: 'ØªØ·Ø¨ÙŠÙ‚ Ù…ÙˆØ¨Ø§ÙŠÙ„ Ù„Ù„Ù…ÙˆØ¸ÙÙŠÙ†', free: true, starter: true, business: true, enterprise: true },
            { name: 'ØªØ·Ø¨ÙŠÙ‚ Ù…ÙˆØ¨Ø§ÙŠÙ„ Ù„Ù„Ù…Ø¯ÙŠØ±ÙŠÙ†', free: false, starter: true, business: true, enterprise: true },
            { name: 'ÙˆØ¶Ø¹ Ø¹Ø¯Ù… Ø§Ù„Ø§ØªØµØ§Ù„', free: false, starter: true, business: true, enterprise: true },
            { name: 'ØªÙƒØ§Ù…Ù„ Ø¨ØµÙ…Ø© ZKTeco', free: false, starter: false, business: true, enterprise: true },
            { name: 'ÙƒØ´Ùƒ HR Ù…Ø®ØµØµ', free: false, starter: false, business: true, enterprise: true },
            { name: 'GPS ÙˆØªØ­Ø¯ÙŠØ¯ Ø§Ù„Ù…Ù†Ø§Ø·Ù‚', free: false, starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'Ø§Ù„Ø£Ù…Ø§Ù† ÙˆØ§Ù„ØªÙƒØ§Ù…Ù„Ø§Øª',
          features: [
            { name: 'Ø®Ø²Ù†Ø© Ø§Ù„Ù…Ø³ØªÙ†Ø¯Ø§Øª', free: false, starter: false, business: true, enterprise: true },
            { name: 'REST API ÙˆWebhooks', free: false, starter: false, business: true, enterprise: true },
            { name: 'SSO SAML / OIDC', free: false, starter: false, business: false, enterprise: true },
            { name: 'Ø³Ø¬Ù„ ØªØ¯Ù‚ÙŠÙ‚ ØºÙŠØ± Ù‚Ø§Ø¨Ù„ Ù„Ù„ØªØºÙŠÙŠØ±', free: false, starter: false, business: false, enterprise: true },
            { name: 'Ù…Ø®Ø·Ø· PostgreSQL Ù…Ø¹Ø²ÙˆÙ„', free: false, starter: false, business: false, enterprise: true },
            { name: 'SLA Ù…Ø®ØµØµ ÙˆØ¯Ø¹Ù… Ø£ÙˆÙ„ÙˆÙŠ', free: false, starter: false, business: false, enterprise: true },
          ],
        },
      ],
    },
    faq: {
      badge: 'Ø£Ø³Ø¦Ù„Ø© Ø§Ù„ØªØ³Ø¹ÙŠØ±',
      title: 'Ø£Ø³Ø¦Ù„Ø© Ø´Ø§Ø¦Ø¹Ø©',
      subtitle: 'Ù…Ø§ ÙŠØ¬Ø¨ Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù†Ù‡ Ù‚Ø¨Ù„ Ø§Ù„Ø¨Ø¯Ø¡',
      all: 'Ø§Ù„ÙƒÙ„',
      categories: ['Ø§Ù„ÙÙˆØªØ±Ø©', 'Ø§Ù„ØªØ¬Ø±Ø¨Ø©', 'Ø§Ù„Ø¯Ø¹Ù…', 'Ø§Ù„Ø£Ù…Ø§Ù†', 'Ø§Ù„ØªÙ‚Ù†ÙŠ'],
      items: [
        { id: 'free-plan', question: 'Ù‡Ù„ Ø§Ù„Ø®Ø·Ø© Ø§Ù„Ù…Ø¬Ø§Ù†ÙŠØ© Ù…Ø¬Ø§Ù†ÙŠØ© Ø­Ù‚Ù‹Ø§ØŸ', answer: 'Ù†Ø¹Ù…ØŒ Ù…Ø¬Ø§Ù†ÙŠØ© 100%. Ù„Ø§ Ø¨Ø·Ø§Ù‚Ø© Ø§Ø¦ØªÙ…Ø§Ù† Ù…Ø·Ù„ÙˆØ¨Ø© Ø£Ø¨Ø¯Ù‹Ø§. ÙˆØµÙˆÙ„ ÙÙˆØ±ÙŠ Ù„Ø­ØªÙ‰ 5 Ù…ÙˆØ¸ÙÙŠÙ†. ÙŠÙ…ÙƒÙ†Ùƒ Ø§Ù„ØªØ±Ù‚ÙŠØ© Ø¥Ù„Ù‰ Ø®Ø·Ø© Ù…Ø¯ÙÙˆØ¹Ø© ÙÙŠ Ø£ÙŠ ÙˆÙ‚Øª.', category: 'Ø§Ù„ØªØ¬Ø±Ø¨Ø©' },
        { id: 'change-plan', question: 'Ù‡Ù„ ÙŠÙ…ÙƒÙ†Ù†ÙŠ ØªØºÙŠÙŠØ± Ø§Ù„Ø®Ø·Ø© Ù„Ø§Ø­Ù‚Ù‹Ø§ØŸ', answer: 'Ù†Ø¹Ù…ØŒ ÙÙŠ Ø£ÙŠ ÙˆÙ‚Øª. Ø§Ù„ØªØ±Ù‚ÙŠØ© ÙÙˆØ±ÙŠØ© ÙˆØ§Ù„ØªØ®ÙÙŠØ¶ ÙŠÙØ·Ø¨Ù‚ ÙÙŠ Ø§Ù„Ø¯ÙˆØ±Ø© Ø§Ù„ØªØ§Ù„ÙŠØ©. Ù„Ø§ Ø±Ø³ÙˆÙ… Ù…Ø®ÙÙŠØ©.', category: 'Ø§Ù„ÙÙˆØªØ±Ø©' },
        { id: 'per-employee', question: 'ÙƒÙŠÙ ØªØ¹Ù…Ù„ Ø§Ù„ÙÙˆØªØ±Ø© Ù„ÙƒÙ„ Ù…ÙˆØ¸ÙØŸ', answer: 'ØªØªØ¶Ù…Ù† ÙƒÙ„ Ø®Ø·Ø© Ø±Ø³ÙˆÙ…Ù‹Ø§ Ø£Ø³Ø§Ø³ÙŠØ© Ø«Ø§Ø¨ØªØ© Ø¨Ø§Ù„Ø¥Ø¶Ø§ÙØ© Ø¥Ù„Ù‰ Ø³Ø¹Ø± Ù„ÙƒÙ„ Ù…ÙˆØ¸Ù Ù†Ø´Ø· (Ù…Ù† Ø³Ø¬Ù‘Ù„ Ø­Ø¶ÙˆØ±Ù‹Ø§ Ù…Ø±Ø© ÙˆØ§Ø­Ø¯Ø© Ø¹Ù„Ù‰ Ø§Ù„Ø£Ù‚Ù„ ÙÙŠ Ø§Ù„Ø´Ù‡Ø±).', category: 'Ø§Ù„ÙÙˆØªØ±Ø©' },
        { id: 'free-trial', question: 'Ù‡Ù„ Ø§Ù„ØªØ¬Ø±Ø¨Ø© Ø§Ù„Ù…Ø¯ÙÙˆØ¹Ø© Ù…Ø¬Ø§Ù†ÙŠØ© Ø­Ù‚Ù‹Ø§ØŸ', answer: 'Ù†Ø¹Ù…. 30 ÙŠÙˆÙ…Ù‹Ø§ ÙƒØ§Ù…Ù„Ø© Ø¨Ø¬Ù…ÙŠØ¹ Ù…Ø²Ø§ÙŠØ§ Ø®Ø·Ø© Pilot. Ù„Ø§ Ø¨Ø·Ø§Ù‚Ø© Ø§Ø¦ØªÙ…Ø§Ù† Ù„Ù„ØªØ³Ø¬ÙŠÙ„.', category: 'Ø§Ù„ØªØ¬Ø±Ø¨Ø©' },
        { id: 'trial-to-paid', question: 'Ù…Ø§Ø°Ø§ ÙŠØ­Ø¯Ø« Ø¹Ù†Ø¯ Ø§Ù†ØªÙ‡Ø§Ø¡ Ø§Ù„ØªØ¬Ø±Ø¨Ø©ØŸ', answer: 'ØªØ®ØªØ§Ø± Ø®Ø·Ø© Ø£Ùˆ ØªØ¨Ù‚Ù‰ Ø¨ÙŠØ§Ù†Ø§ØªÙƒ Ù…Ø¤Ø±Ø´ÙØ© 30 ÙŠÙˆÙ…Ù‹Ø§ Ø¥Ø¶Ø§ÙÙŠØ©. Ù„Ø§ ÙÙˆØªØ±Ø© ØªÙ„Ù‚Ø§Ø¦ÙŠØ© Ø¨Ø¯ÙˆÙ† Ù…ÙˆØ§ÙÙ‚ØªÙƒ.', category: 'Ø§Ù„ØªØ¬Ø±Ø¨Ø©' },
        { id: 'support', question: 'Ù…Ø§ Ù†ÙˆØ¹ Ø§Ù„Ø¯Ø¹Ù… Ø§Ù„Ù…ØªØ§Ø­ØŸ', answer: 'Free: Ù…Ø¬ØªÙ…Ø¹. Starter: Ø¨Ø±ÙŠØ¯ Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ Ø®Ù„Ø§Ù„ 48 Ø³Ø§Ø¹Ø©. Business: Ø£ÙˆÙ„ÙˆÙŠØ© 24 Ø³Ø§Ø¹Ø©. Enterprise: Ù…Ø¯ÙŠØ± Ø­Ø³Ø§Ø¨ Ù…Ø®ØµØµ + SLA ØªØ¹Ø§Ù‚Ø¯ÙŠ.', category: 'Ø§Ù„Ø¯Ø¹Ù…' },
        { id: 'data-location', question: 'Ø£ÙŠÙ† ØªÙØ³ØªØ¶Ø§Ù Ø¨ÙŠØ§Ù†Ø§ØªÙŠØŸ', answer: 'ÙÙŠ Ø£ÙˆØ±ÙˆØ¨Ø§ (Render EU / Supabase EU). ØªØ´ÙÙŠØ± AES-256 Ø£Ø«Ù†Ø§Ø¡ Ø§Ù„ØªØ®Ø²ÙŠÙ† ÙˆTLS 1.3 Ø£Ø«Ù†Ø§Ø¡ Ø§Ù„Ù†Ù‚Ù„. Ø¹Ø²Ù„ Ø§Ù„Ù…Ø³ØªØ£Ø¬Ø±ÙŠÙ† Ù…Ø¶Ù…ÙˆÙ†.', category: 'Ø§Ù„Ø£Ù…Ø§Ù†' },
        { id: 'gdpr', question: 'Ù‡Ù„ Ø£Ù†ØªÙ… Ù…ØªÙˆØ§ÙÙ‚ÙˆÙ† Ù…Ø¹ GDPRØŸ', answer: 'Ù†Ø¹Ù…. DPA Ù…ØªØ§Ø­ØŒ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª ÙÙŠ Ø£ÙˆØ±ÙˆØ¨Ø§ Ø­ØµØ±Ù‹Ø§ØŒ Ø­Ù‚ Ø§Ù„Ø­Ø°Ù Ù…ÙØ·Ø¨ÙŽÙ‘Ù‚ØŒ ØªØµØ¯ÙŠØ± Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø¹Ù†Ø¯ Ø§Ù„Ø·Ù„Ø¨.', category: 'Ø§Ù„Ø£Ù…Ø§Ù†' },
        { id: 'api', question: 'Ù‡Ù„ API Ù…ØªØ§Ø­ ÙÙŠ Ø®Ø·Ø© FreeØŸ', answer: 'REST API ÙˆØ§Ù„Ù€ Webhooks Ù…ØªØ§Ø­Ø© Ù…Ù† Ø®Ø·Ø© Business. ÙÙŠ Free ÙˆStarter ÙŠÙ…ÙƒÙ†Ùƒ ØªØµØ¯ÙŠØ± Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø¨ØµÙŠØºØ© CSV/Excel.', category: 'Ø§Ù„ØªÙ‚Ù†ÙŠ' },
      ],
    },
    cta: {
      badge: 'Ø¬Ø§Ù‡Ø² Ù„Ù„Ø¨Ø¯Ø¡',
      headline: 'Ø£Ø·Ù„Ù‚ Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…ÙˆØ§Ø±Ø¯ Ø§Ù„Ø¨Ø´Ø±ÙŠØ© Ø§Ù„Ù…ÙŠØ¯Ø§Ù†ÙŠØ© Ø§Ù„ÙŠÙˆÙ…',
      subheadline: 'Ø§Ù†Ø¶Ù… Ø¥Ù„Ù‰ Ø§Ù„ÙØ±Ù‚ Ø§Ù„ØªÙŠ Ø®ÙÙ‘Ø¶Øª ÙˆÙ‚Øª Ø¥Ø¹Ø¯Ø§Ø¯ Ø§Ù„Ø±ÙˆØ§ØªØ¨ Ù…Ù† Ø³Ø§Ø¹ØªÙŠÙ† Ø¥Ù„Ù‰ 8 Ø¯Ù‚Ø§Ø¦Ù‚.',
      primary: 'Ø§Ø¨Ø¯Ø£ Ù…Ø¬Ø§Ù†Ù‹Ø§',
      secondary: 'ØªÙˆØ§ØµÙ„ Ù…Ø¹ Ø§Ù„Ù…Ø¨ÙŠØ¹Ø§Øª',
    },
  },
};

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   PLAN ICONS
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
const planIcons = [Gift, Rocket, Crown, Building2] as const;
const planIconColors = [
  'text-slate-500',
  'text-blue-500',
  'text-emerald-500',
  'text-violet-500',
] as const;

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   AVAILABILITY MARK
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function AvailabilityMark({
  value,
  popular,
}: {
  value: boolean | string;
  popular: boolean;
}) {
  if (value === true) {
    return (
      <span
        className={`inline-flex items-center justify-center w-7 h-7 rounded-full ${
          popular
            ? 'bg-emerald-500/10'
            : 'bg-slate-100 dark:bg-slate-800'
        }`}
      >
        <Check
          className={`w-4 h-4 ${
            popular ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'
          }`}
        />
      </span>
    );
  }
  if (typeof value === 'string') {
    return (
      <span className="text-xs text-slate-500 dark:text-slate-400 font-medium">
        {value}
      </span>
    );
  }
  return (
    <span className="inline-flex items-center justify-center w-7 h-7">
      <X className="w-4 h-4 text-slate-300 dark:text-slate-700" />
    </span>
  );
}

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   FAQ ITEM
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function FaqAccordionItem({ item, isOpen, onToggle }: {
  item: FaqItem;
  isOpen: boolean;
  onToggle: () => void;
}) {
  return (
    <div className="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
      <button
        onClick={onToggle}
        className="w-full flex items-center justify-between gap-4 p-6 text-left hover:bg-transparent dark:hover:bg-slate-900/50 transition-colors"
        aria-expanded={isOpen}
      >
        <span className="font-semibold text-slate-900 dark:text-white text-base">
          {item.question}
        </span>
        <motion.span
          animate={{ rotate: isOpen ? 180 : 0 }}
          transition={{ duration: 0.2 }}
          className="flex-shrink-0 w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center"
        >
          <ChevronDown className="w-4 h-4 text-slate-500 dark:text-slate-400" />
        </motion.span>
      </button>
      <AnimatePresence initial={false}>
        {isOpen && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: 'auto', opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{ duration: 0.25, ease: 'easeInOut' }}
          >
            <div className="px-6 pb-6 text-slate-600 dark:text-slate-400 leading-relaxed border-t border-slate-100 dark:border-slate-800 pt-4">
              {item.answer}
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   HELPER: get comparison feature value by plan name
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function getFeatureValue(feature: ComparisonFeature, planName: string): boolean | string {
  const lower = planName.toLowerCase();
  if (lower === 'free') return feature.free;
  if (lower === 'pilot' || lower === 'starter') return feature.starter;
  if (lower === 'operations' || lower === 'business') return feature.business;
  if (lower === 'enterprise' || lower === 'scale') return feature.enterprise;
  return false;
}

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   PAGE
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
export default function PricingPage() {
  const [isDark, setIsDark] = useState(false);
  const [isAnnual, setIsAnnual] = useState(true);
  const [openFaqId, setOpenFaqId] = useState<string | null>('free-plan');
  const [faqCategory, setFaqCategory] = useState<string | null>(null);
  // PA2-MKT-003: let PME prospects in DZ/MA/TN/TR/CA/US see an approximate
  // price in their own currency instead of only EUR. The contractual price
  // stays EUR (see currency.ts docblock); this is a display convenience.
  const [currencyOption, setCurrencyOption] = useState<CurrencyOption>(DEFAULT_CURRENCY_OPTION);

  const { locale, direction } = useVitrineLocale();
  const copy = pricingPageCopy[locale] ?? pricingPageCopy.fr;
  const plans = getPricingPlans(locale);
  useScrollReveal();

  function showsCurrency(price: string) {
    return !['Sur devis', 'Custom', 'Teklif', 'Ø­Ø³Ø¨ Ø§Ù„Ø¹Ø±Ø¶', 'Teklif alÄ±n', 'Ø­Ø³Ø¨ Ø§Ù„Ø·Ù„Ø¨'].includes(price);
  }

  const isEurSelected = currencyOption.currency === 'EUR';
  const convertedPrice = (eurAmount: string) => convertEurPrice(eurAmount, currencyOption);

  function getPlanHref(plan: ReturnType<typeof getPricingPlans>[number]) {
    // Free plan â†’ direct account creation, no payment
    if (plan.price === '0') return '/checkout?plan=free';
    // Enterprise â†’ contact
    if (!showsCurrency(plan.price)) return '/contact?type=enterprise';
    // Paid plans â†’ checkout with payment
    if (plan.popular) return '/checkout?plan=business';
    return '/checkout?plan=starter';
  }

  const filteredFaq = faqCategory
    ? copy.faq.items.filter((i) => i.category === faqCategory)
    : copy.faq.items;

  return (
    <div
      dir={direction}
      className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}
    >
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      {/* â”€â”€ HERO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */}
      <section className="relative min-h-[60vh] flex items-center justify-center overflow-hidden pt-24 pb-20">
        <div className="absolute inset-0 bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 dark:from-slate-950 dark:via-indigo-950 dark:to-slate-900" />
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_80%_50%_at_50%_-10%,rgba(99,102,241,0.15),transparent)]" />
        <div className="absolute top-1/4 left-1/4 w-[600px] h-[600px] bg-violet-500/10 rounded-full blur-[140px] animate-pulse" />
        <div className="absolute bottom-1/4 right-1/4 w-[400px] h-[400px] bg-emerald-500/10 rounded-full blur-[100px] animate-pulse [animation-delay:2s]" />
        <div className="absolute inset-0 opacity-[0.04]" style={{ backgroundImage: 'linear-gradient(rgba(255,255,255,0.15) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.15) 1px, transparent 1px)', backgroundSize: '60px 60px' }} />

        <div className="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-violet-500/10 border border-violet-500/20 text-violet-300 text-sm font-semibold mb-8"
          >
            <Zap className="w-3.5 h-3.5" />
            {copy.hero.badge}
          </motion.div>

          <motion.h1
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.1 }}
            className="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white mb-6 leading-[1.1]"
          >
            {copy.hero.headline}
          </motion.h1>

          <motion.p
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.25 }}
            className="text-lg sm:text-xl text-slate-300 mb-10 max-w-2xl mx-auto leading-relaxed"
          >
            {copy.hero.subheadline}
          </motion.p>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.4 }}
            className="flex flex-col sm:flex-row items-center justify-center gap-4"
          >
            <Link
              href="/checkout?plan=free"
              className="group relative px-8 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-bold rounded-2xl overflow-hidden transition-all duration-300 hover:shadow-[0_20px_60px_-15px_rgba(16,185,129,0.4)] hover:scale-[1.03] active:scale-[0.98]"
            >
              <span className="relative z-10 flex items-center gap-2.5">
                {copy.hero.primary}
                <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
              </span>
              <div className="absolute inset-0 bg-gradient-to-r from-emerald-600 to-cyan-600 opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
            </Link>
            <Link
              href="/contact?type=enterprise"
              className="group flex items-center gap-2.5 px-8 py-4 bg-white/10 text-white font-semibold rounded-2xl border border-white/20 hover:bg-white/20 transition-all duration-300 backdrop-blur-sm"
            >
              <MessageCircle className="w-5 h-5" />
              {copy.hero.secondary}
            </Link>
          </motion.div>

          {/* Trust pills */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.6 }}
            className="flex flex-wrap items-center justify-center gap-3 mt-10"
          >
            {copy.trust.items.map((item) => (
              <div
                key={item}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-white/5 border border-white/10 rounded-full text-slate-300 text-xs font-medium"
              >
                <ShieldCheck className="w-3 h-3 text-emerald-400" />
                {item}
              </div>
            ))}
          </motion.div>
        </div>
      </section>

      {/* â”€â”€ PRICING CARDS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */}
      <section className="relative py-24 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/50 to-white dark:from-slate-950 dark:via-slate-900/50 dark:to-slate-950" />

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Section header */}
          <div className="text-center mb-12">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-violet-500/[0.08] border border-violet-500/15 text-violet-700 dark:text-violet-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse" />
              {copy.plans.badge}
            </div>
            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">
              {copy.plans.title}
            </h2>
            <p className="text-lg text-slate-500 dark:text-slate-400 max-w-xl mx-auto">
              {copy.plans.subtitle}
            </p>
          </div>

          {/* PA2-MKT-003: currency/country selector for approximate local pricing */}
          <div className="flex items-center justify-center gap-2 mb-6">
            <label className="flex items-center gap-2 rounded-xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/80 px-3 py-2 text-sm text-slate-600 dark:text-slate-300">
              <span className="font-medium">{copy.currency.label}</span>
              <select
                value={currencyOption.country}
                onChange={(event) => {
                  const next = CURRENCY_OPTIONS.find((o) => o.country === event.target.value);
                  if (next) setCurrencyOption(next);
                }}
                className="bg-transparent outline-none font-semibold"
                aria-label={copy.currency.label}
              >
                {CURRENCY_OPTIONS.map((option) => (
                  <option key={option.country} value={option.country}>
                    {option.label[locale] ?? option.label.fr}
                  </option>
                ))}
              </select>
            </label>
          </div>
          {!isEurSelected && (
            <p className="text-center text-xs text-slate-400 dark:text-slate-500 mb-8 max-w-md mx-auto">
              {copy.currency.approx}
            </p>
          )}

          {/* Billing toggle */}
          <div className="flex items-center justify-center gap-4 mb-14">
            <span className={`text-sm font-semibold transition-colors ${!isAnnual ? 'text-slate-900 dark:text-white' : 'text-slate-400 dark:text-slate-500'}`}>
              {copy.plans.monthly}
            </span>
            <button
              onClick={() => setIsAnnual(!isAnnual)}
              aria-label="Toggle billing period"
              className="relative w-16 h-8 rounded-full bg-emerald-500 shadow-inner shadow-emerald-700/30 transition-colors"
            >
              <motion.div
                className="absolute top-1 w-6 h-6 rounded-full bg-white shadow-md"
                animate={{ left: isAnnual ? '2.25rem' : '0.25rem' }}
                transition={{ type: 'spring', stiffness: 500, damping: 35 }}
              />
            </button>
            <span className={`text-sm font-semibold transition-colors ${isAnnual ? 'text-slate-900 dark:text-white' : 'text-slate-400 dark:text-slate-500'}`}>
              {copy.plans.annual}
            </span>
            <AnimatePresence>
              {isAnnual && (
                <motion.span
                  initial={{ opacity: 0, scale: 0.8, x: -8 }}
                  animate={{ opacity: 1, scale: 1, x: 0 }}
                  exit={{ opacity: 0, scale: 0.8, x: -8 }}
                  className="px-3 py-1 text-xs font-black text-emerald-700 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400 rounded-full"
                >
                  {copy.plans.savings}
                </motion.span>
              )}
            </AnimatePresence>
          </div>

          {/* Cards â€” 4 plans in a responsive grid */}
          <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 max-w-7xl mx-auto">
            {plans.map((plan, index) => {
              const Icon = planIcons[index % planIcons.length];
              const iconColor = planIconColors[index % planIconColors.length];
              const displayPrice = isAnnual ? plan.annualPrice : plan.price;
              const isFree = plan.price === '0';
              const hasNumericPrice = !['Sur devis', 'Custom', 'Teklif', 'Ø­Ø³Ø¨ Ø§Ù„Ø¹Ø±Ø¶', 'Teklif alÄ±n', 'Ø­Ø³Ø¨ Ø§Ù„Ø·Ù„Ø¨'].includes(displayPrice);
              const ctaHref = getPlanHref(plan);

              return (
                <motion.div
                  key={plan.name}
                  initial={{ opacity: 0, y: 40 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ duration: 0.6, delay: index * 0.1 }}
                  whileHover={{ y: -6, transition: { duration: 0.2 } }}
                  className={`relative rounded-3xl ${
                    plan.popular
                      ? 'bg-gradient-to-b from-emerald-400 via-emerald-500 to-cyan-600 p-px shadow-2xl shadow-emerald-500/25'
                      : isFree
                        ? 'bg-gradient-to-b from-slate-300 to-slate-400 dark:from-slate-600 dark:to-slate-700 p-px'
                        : 'bg-slate-200/70 dark:bg-slate-800/70 p-px'
                  }`}
                >
                  <div className="relative h-full rounded-[23px] bg-white dark:bg-slate-950 flex flex-col p-8">
                    {/* Plan badge */}
                    {plan.popular && (
                      <div className="absolute -top-4 left-1/2 -translate-x-1/2 z-10">
                        <div className="flex items-center gap-1.5 px-4 py-1.5 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white text-[11px] font-black uppercase tracking-widest rounded-full shadow-lg shadow-emerald-500/30">
                          <Star className="w-3 h-3 fill-white" />
                          Le plus populaire
                        </div>
                      </div>
                    )}
                    {isFree && (
                      <div className="absolute -top-4 left-1/2 -translate-x-1/2 z-10">
                        <div className="flex items-center gap-1.5 px-4 py-1.5 bg-gradient-to-r from-slate-600 to-slate-700 text-white text-[11px] font-black uppercase tracking-widest rounded-full shadow-lg">
                          <Gift className="w-3 h-3" />
                          100% Gratuit
                        </div>
                      </div>
                    )}

                    {/* Plan header */}
                    <div className="mb-8">
                      <div className="inline-flex items-center justify-center w-12 h-12 rounded-2xl mb-4 bg-slate-100 dark:bg-slate-800/80">
                        <Icon className={`w-6 h-6 ${iconColor}`} />
                      </div>
                      <h3 className="text-xl font-black text-slate-900 dark:text-white mb-1">{plan.name}</h3>
                      <p className="text-sm text-slate-500 dark:text-slate-400">{plan.description}</p>
                    </div>

                    {/* Price */}
                    <div className="mb-6">
                      <div className="flex items-baseline gap-1.5">
                        {isFree ? (
                          <span className="text-5xl font-black bg-gradient-to-b from-slate-900 to-slate-600 dark:from-white dark:to-slate-400 bg-clip-text text-transparent">
                            Gratuit
                          </span>
                        ) : hasNumericPrice ? (
                          <>
                            <span className="text-lg font-bold text-slate-500 dark:text-slate-400">
                              {isEurSelected ? 'EUR' : currencyOption.currency}
                            </span>
                            <span className="text-5xl font-black bg-gradient-to-b from-slate-900 to-slate-600 dark:from-white dark:to-slate-400 bg-clip-text text-transparent">
                              {isEurSelected ? displayPrice : (convertedPrice(displayPrice) ?? displayPrice)}
                            </span>
                          </>
                        ) : (
                          <span className="text-4xl font-black bg-gradient-to-b from-slate-900 to-slate-600 dark:from-white dark:to-slate-400 bg-clip-text text-transparent">
                            {displayPrice}
                          </span>
                        )}
                      </div>
                      {isFree ? (
                        <p className="mt-1 text-sm text-emerald-600 dark:text-emerald-400 font-semibold">
                          Sans carte bancaire Â· Pour toujours
                        </p>
                      ) : hasNumericPrice ? (
                        <div className="mt-1 space-y-0.5">
                          <p className="text-sm text-slate-500">
                            {isAnnual ? copy.plans.periodAnnual : copy.plans.periodMonthly}
                          </p>
                          {isAnnual && (
                            <p className="text-xs text-slate-400 dark:text-slate-600">
                              <span className="line-through">
                                {isEurSelected ? 'EUR' : currencyOption.currency} {isEurSelected ? plan.price : (convertedPrice(plan.price) ?? plan.price)}
                              </span>
                              {' '}
                              <span className="text-emerald-600 dark:text-emerald-400 font-semibold">{copy.plans.savings}</span>
                            </p>
                          )}
                          {!isEurSelected && (
                            <p className="text-xs text-slate-400 dark:text-slate-600">â‰ˆ EUR {displayPrice}</p>
                          )}
                        </div>
                      ) : null}
                      {plan.priceNote && (
                        <p className="mt-2 text-xs text-slate-500 dark:text-slate-400 font-medium">
                          {plan.priceNote}
                        </p>
                      )}
                      <div className="inline-flex items-center gap-1.5 mt-3 px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-xs text-slate-500 dark:text-slate-400">
                        <Users className="w-3 h-3" />
                        {plan.employeeLimit}
                      </div>
                    </div>

                    {/* Features */}
                    <ul className="flex-1 space-y-3 mb-8">
                      {plan.features.map((feature, fi) => (
                        <li key={fi} className="flex items-start gap-3">
                          <Check className={`w-4 h-4 flex-shrink-0 mt-0.5 ${plan.popular ? 'text-emerald-500' : isFree ? 'text-slate-500' : 'text-slate-400 dark:text-slate-500'}`} />
                          <span className="text-sm text-slate-700 dark:text-slate-300 leading-snug">{feature}</span>
                        </li>
                      ))}
                    </ul>

                    {/* CTA */}
                    <Link
                      href={ctaHref}
                      className={`flex items-center justify-center gap-2 w-full py-4 rounded-2xl font-bold text-sm transition-all duration-300 ${
                        plan.popular
                          ? 'bg-gradient-to-r from-emerald-500 to-emerald-600 text-white hover:from-emerald-600 hover:to-cyan-600 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:scale-[1.02] active:scale-[0.98]'
                          : isFree
                            ? 'bg-gradient-to-r from-slate-700 to-slate-900 text-white hover:from-slate-800 hover:to-black hover:scale-[1.01] active:scale-[0.98] shadow-md'
                            : hasNumericPrice
                              ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 hover:bg-slate-800 dark:hover:bg-slate-100 hover:scale-[1.01] active:scale-[0.98]'
                              : 'bg-gradient-to-r from-violet-500 to-fuchsia-600 text-white hover:from-violet-600 hover:to-fuchsia-700 shadow-lg hover:scale-[1.02] active:scale-[0.98]'
                      }`}
                    >
                      {plan.cta}
                      <ArrowRight className="w-4 h-4" />
                    </Link>
                  </div>
                </motion.div>
              );
            })}
          </div>

          {/* Trial note */}
          <motion.p
            initial={{ opacity: 0 }}
            whileInView={{ opacity: 1 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6, delay: 0.4 }}
            className="text-center text-sm text-slate-500 dark:text-slate-400 mt-8 flex items-center justify-center gap-2"
          >
            <ShieldCheck className="w-4 h-4 text-emerald-500" />
            {copy.plans.trialNote}
          </motion.p>
        </div>
      </section>

      {/* â”€â”€ COMPARISON TABLE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */}
      <section className="relative py-24 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-b from-slate-50/50 to-white dark:from-slate-900/50 dark:to-slate-950" />

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
              {copy.comparison.badge}
            </div>
            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
              {copy.comparison.title}{' '}
              <span className="bg-gradient-to-r from-emerald-500 to-cyan-500 bg-clip-text text-transparent">
                {copy.comparison.subtitle}
              </span>
            </h2>
          </div>

          <div className="overflow-x-auto rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xl shadow-slate-100/50 dark:shadow-slate-950/50">
            <table className="w-full min-w-[720px]">
              <thead>
                <tr className="bg-transparent dark:bg-slate-900">
                  <th className="text-left py-5 px-6 font-bold text-slate-900 dark:text-white text-sm w-[32%]">
                    {copy.comparison.featureColumn}
                  </th>
                  {plans.map((plan, i) => {
                    const Icon = planIcons[i % planIcons.length];
                    const isFree = plan.price === '0';
                    return (
                      <th
                        key={plan.name}
                        className={`text-center py-5 px-4 font-black text-sm ${
                          plan.popular
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-slate-700 dark:text-slate-300'
                        }`}
                      >
                        <div className="flex flex-col items-center gap-1.5">
                          <Icon className={`w-5 h-5 ${planIconColors[i % planIconColors.length]}`} />
                          {plan.name}
                          {plan.popular && (
                            <span className="text-[9px] px-2 py-0.5 bg-emerald-500 text-white rounded-full font-black uppercase tracking-wider">
                              â˜… top
                            </span>
                          )}
                          {isFree && (
                            <span className="text-[9px] px-2 py-0.5 bg-slate-600 text-white rounded-full font-black uppercase tracking-wider">
                              gratuit
                            </span>
                          )}
                        </div>
                      </th>
                    );
                  })}
                </tr>
              </thead>
              <tbody>
                {copy.comparison.categories.map((cat, catIdx) => (
                  <Fragment key={cat.category}>
                    {/* Category row */}
                    <tr className={catIdx % 2 === 0 ? 'bg-transparent/70 dark:bg-slate-900/30' : 'bg-emerald-50/30 dark:bg-emerald-950/10'}>
                      <td colSpan={plans.length + 1} className="py-3 px-6">
                        <span className="text-xs font-black uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">
                          {cat.category}
                        </span>
                      </td>
                    </tr>
                    {/* Feature rows */}
                    {cat.features.map((feature, fIdx) => (
                      <motion.tr
                        key={feature.name}
                        initial={{ opacity: 0 }}
                        whileInView={{ opacity: 1 }}
                        viewport={{ once: true, margin: '-40px' }}
                        transition={{ duration: 0.3, delay: fIdx * 0.05 }}
                        className="border-t border-slate-100 dark:border-slate-800/50 hover:bg-transparent/80 dark:hover:bg-slate-900/30 transition-colors"
                      >
                        <td className="py-4 px-6 text-sm text-slate-700 dark:text-slate-300 font-medium">
                          {feature.name}
                        </td>
                        {plans.map((plan) => (
                          <td
                            key={plan.name}
                            className={`py-4 px-4 text-center ${plan.popular ? 'bg-emerald-50/40 dark:bg-emerald-950/10' : ''}`}
                          >
                            <AvailabilityMark
                              value={getFeatureValue(feature, plan.name)}
                              popular={plan.popular}
                            />
                          </td>
                        ))}
                      </motion.tr>
                    ))}
                  </Fragment>
                ))}
                {/* CTA row */}
                <tr className="border-t border-slate-200 dark:border-slate-800 bg-transparent dark:bg-slate-900">
                  <td className="py-6 px-6" />
                  {plans.map((plan) => {
                    const isFree = plan.price === '0';
                    const hasNumericPrice = !['Sur devis', 'Custom', 'Teklif', 'Ø­Ø³Ø¨ Ø§Ù„Ø¹Ø±Ø¶', 'Teklif alÄ±n', 'Ø­Ø³Ø¨ Ø§Ù„Ø·Ù„Ø¨'].includes(plan.price);
                    return (
                      <td key={plan.name} className={`py-6 px-4 text-center ${plan.popular ? 'bg-emerald-50/40 dark:bg-emerald-950/10' : ''}`}>
                        <Link
                          href={getPlanHref(plan)}
                          className={`inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 hover:scale-[1.03] active:scale-[0.97] ${
                            plan.popular
                              ? 'bg-gradient-to-r from-emerald-500 to-emerald-600 text-white shadow-lg shadow-emerald-500/20'
                              : isFree
                                ? 'bg-slate-700 text-white hover:bg-slate-800'
                                : hasNumericPrice
                                  ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 hover:bg-slate-800 dark:hover:bg-slate-100'
                                  : 'bg-gradient-to-r from-violet-500 to-fuchsia-600 text-white hover:from-violet-600 hover:to-fuchsia-700'
                          }`}
                        >
                          {plan.cta}
                          <ArrowRight className="w-3.5 h-3.5" />
                        </Link>
                      </td>
                    );
                  })}
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      {/* â”€â”€ FAQ â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */}
      <section className="relative py-24 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-b from-white to-slate-50/50 dark:from-slate-950 dark:to-slate-900/50" />

        <div className="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-violet-500/[0.08] border border-violet-500/15 text-violet-700 dark:text-violet-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse" />
              {copy.faq.badge}
            </div>
            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">
              {copy.faq.title}
            </h2>
            <p className="text-lg text-slate-500 dark:text-slate-400">{copy.faq.subtitle}</p>
          </div>

          {/* Category filter */}
          <div className="flex flex-wrap gap-2 justify-center mb-8">
            <button
              onClick={() => setFaqCategory(null)}
              className={`px-4 py-1.5 rounded-full text-sm font-semibold transition-all duration-200 ${
                !faqCategory
                  ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900'
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'
              }`}
            >
              {copy.faq.all}
            </button>
            {copy.faq.categories.map((cat) => (
              <button
                key={cat}
                onClick={() => setFaqCategory(faqCategory === cat ? null : cat)}
                className={`px-4 py-1.5 rounded-full text-sm font-semibold transition-all duration-200 ${
                  faqCategory === cat
                    ? 'bg-emerald-500 text-white'
                    : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'
                }`}
              >
                {cat}
              </button>
            ))}
          </div>

          {/* Accordion */}
          <div className="space-y-3">
            <AnimatePresence mode="wait">
              {filteredFaq.map((item) => (
                <motion.div
                  key={item.id}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: -10 }}
                  transition={{ duration: 0.2 }}
                >
                  <FaqAccordionItem
                    item={item}
                    isOpen={openFaqId === item.id}
                    onToggle={() => setOpenFaqId(openFaqId === item.id ? null : item.id)}
                  />
                </motion.div>
              ))}
            </AnimatePresence>
          </div>

          {/* Still have questions */}
          <div className="mt-10 text-center p-6 rounded-2xl bg-transparent dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <p className="text-slate-700 dark:text-slate-300 font-semibold mb-3">
              {locale === 'fr' ? 'Une autre question ?' : locale === 'tr' ? 'BaÅŸka sorunuz var mÄ±?' : locale === 'ar' ? 'Ø³Ø¤Ø§Ù„ Ø¢Ø®Ø±ØŸ' : 'Another question?'}
            </p>
            <Link
              href="/contact"
              className="inline-flex items-center gap-2 px-6 py-3 bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold rounded-xl hover:scale-[1.02] transition-transform duration-200 text-sm"
            >
              <MessageCircle className="w-4 h-4" />
              {locale === 'fr' ? 'Contacter le support' : locale === 'tr' ? 'Destek ile iletiÅŸim' : locale === 'ar' ? 'ØªÙˆØ§ØµÙ„ Ù…Ø¹ Ø§Ù„Ø¯Ø¹Ù…' : 'Contact support'}
              <ArrowRight className="w-4 h-4" />
            </Link>
          </div>
        </div>
      </section>

      {/* â”€â”€ CTA FINAL â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */}
      <section className="relative py-32 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-r from-emerald-500 via-emerald-600 to-cyan-600" />
        <div className="absolute top-1/4 -left-32 w-[500px] h-[500px] bg-white/10 rounded-full blur-[120px]" />
        <div className="absolute bottom-1/4 -right-32 w-[500px] h-[500px] bg-white/10 rounded-full blur-[120px]" />
        <div className="absolute inset-0 opacity-10" style={{ backgroundImage: 'radial-gradient(circle, rgba(255,255,255,0.3) 1px, transparent 1px)', backgroundSize: '30px 30px' }} />

        <div className="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6 }}
            className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 border border-white/20 text-white text-sm font-semibold mb-8"
          >
            <Zap className="w-3.5 h-3.5" />
            {copy.cta.badge}
          </motion.div>

          <motion.h2
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.8, delay: 0.1 }}
            className="text-4xl sm:text-5xl lg:text-6xl font-black text-white mb-6 tracking-tight leading-[1.1]"
          >
            {copy.cta.headline}
          </motion.h2>

          <motion.p
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.8, delay: 0.2 }}
            className="text-xl text-white/80 mb-12 max-w-2xl mx-auto leading-relaxed"
          >
            {copy.cta.subheadline}
          </motion.p>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.8, delay: 0.3 }}
            className="flex flex-col sm:flex-row items-center justify-center gap-4"
          >
            <Link
              href="/checkout?plan=free"
              className="group relative px-10 py-4 bg-white text-emerald-600 font-black rounded-2xl overflow-hidden transition-all duration-300 hover:shadow-2xl hover:scale-[1.03] active:scale-[0.98] text-base"
            >
              <span className="relative z-10 flex items-center gap-2.5">
                {copy.cta.primary}
                <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
              </span>
            </Link>
            <Link
              href="/contact?type=enterprise"
              className="group flex items-center gap-2.5 px-10 py-4 bg-white/10 text-white font-bold rounded-2xl border border-white/20 hover:bg-white/20 transition-all duration-300 backdrop-blur-sm text-base"
            >
              <Building2 className="w-5 h-5" />
              {copy.cta.secondary}
            </Link>
          </motion.div>
        </div>
      </section>

      <Footer />
    </div>
  );
}


