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
} from 'lucide-react';

/* ─────────────────────────────────────────────
   TYPES
───────────────────────────────────────────── */
type ComparisonFeature = {
  name: string;
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
  trust: { items: string[] };
  comparison: { badge: string; title: string; subtitle: string; featureColumn: string; categories: ComparisonCategory[] };
  faq: { title: string; subtitle: string; badge: string; all: string; categories: string[]; items: FaqItem[] };
  cta: { badge: string; headline: string; subheadline: string; primary: string; secondary: string };
};

/* ─────────────────────────────────────────────
   COPY (fr / en / tr / ar)
───────────────────────────────────────────── */
const pricingPageCopy: Record<AppLocale, PricingPageCopy> = {
  fr: {
    hero: {
      badge: 'Tarification transparente',
      headline: 'Des tarifs pensés pour les équipes terrain',
      subheadline: '30 jours pour tester pointage, paie, absences et workflows RH avant tout engagement. Sans carte bancaire.',
      primary: 'Démarrer 30 jours gratuits',
      secondary: 'Parler à un expert',
    },
    plans: {
      badge: 'Nos plans',
      title: 'Un plan pour chaque étape de votre croissance',
      subtitle: 'Commencez petit, montez en puissance sans changer de plateforme.',
      monthly: 'Mensuel',
      annual: 'Annuel',
      savings: 'Économisez 20%',
      customPrice: 'Sur devis',
      periodMonthly: '/mois',
      periodAnnual: '/mois facturé annuellement',
      trialNote: '30 jours offerts · Aucune CB requise',
    },
    trust: {
      items: [
        'Sans engagement sur l\'offre pilote',
        'Support inclus dès le premier jour',
        'Données hébergées en Europe',
        'Résiliation à tout moment',
      ],
    },
    comparison: {
      badge: 'Comparaison complète',
      title: 'Tout ce qui est inclus',
      subtitle: 'par plan',
      featureColumn: 'Fonctionnalité',
      categories: [
        {
          category: 'Gestion RH',
          features: [
            { name: 'Pointage web & mobile', starter: true, business: true, enterprise: true },
            { name: 'Absences & congés', starter: true, business: true, enterprise: true },
            { name: 'Calendrier partagé', starter: true, business: true, enterprise: true },
            { name: 'Onboarding guidé', starter: true, business: true, enterprise: true },
            { name: 'Évaluations & performance', starter: false, business: true, enterprise: true },
            { name: 'Organigramme dynamique', starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'Paie & finance',
          features: [
            { name: 'Calcul automatisé de la paie', starter: true, business: true, enterprise: true },
            { name: 'Bulletins de paie PDF', starter: true, business: true, enterprise: true },
            { name: 'Exports comptables', starter: false, business: true, enterprise: true },
            { name: 'Avances sur salaire', starter: false, business: true, enterprise: true },
            { name: 'Multi-pays & multi-devises', starter: false, business: false, enterprise: true },
            { name: 'Conformité légale avancée', starter: false, business: false, enterprise: true },
          ],
        },
        {
          category: 'Terrain & mobile',
          features: [
            { name: 'App mobile Employee', starter: true, business: true, enterprise: true },
            { name: 'App mobile Manager', starter: true, business: true, enterprise: true },
            { name: 'Mode hors-ligne', starter: true, business: true, enterprise: true },
            { name: 'Intégration ZKTeco biométrie', starter: false, business: true, enterprise: true },
            { name: 'Kiosque RH dédié', starter: false, business: true, enterprise: true },
            { name: 'GPS & géofencing', starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'Sécurité & intégrations',
          features: [
            { name: 'Coffre-fort documentaire', starter: false, business: true, enterprise: true },
            { name: 'API REST & Webhooks', starter: false, business: true, enterprise: true },
            { name: 'SSO SAML / OIDC', starter: false, business: false, enterprise: true },
            { name: 'Audit trail immuable', starter: false, business: false, enterprise: true },
            { name: 'Schéma PostgreSQL isolé', starter: false, business: false, enterprise: true },
            { name: 'SLA dédié & support prioritaire', starter: false, business: false, enterprise: true },
          ],
        },
      ],
    },
    faq: {
      badge: 'FAQ tarifs',
      title: 'Questions fréquentes',
      subtitle: 'Les points à vérifier avant de démarrer',
      all: 'Tous',
      categories: ['Facturation', 'Essai', 'Support', 'Sécurité', 'Technique'],
      items: [
        { id: 'change-plan', question: 'Puis-je changer de plan ?', answer: 'Oui, à tout moment. Upgrade immédiat, downgrade au prochain cycle. Aucun frais caché.', category: 'Facturation' },
        { id: 'per-employee', question: 'Comment fonctionne la facturation par employé ?', answer: 'Chaque plan inclut un socle fixe + un tarif par employé actif (qui a pointé au moins une fois dans le mois). Les employés inactifs ne sont pas comptés.', category: 'Facturation' },
        { id: 'free-trial', question: 'L\'essai est-il vraiment gratuit ?', answer: 'Oui. 30 jours complets avec toutes les fonctionnalités du plan Pilot. Aucune carte bancaire requise pour s\'inscrire.', category: 'Essai' },
        { id: 'trial-to-paid', question: 'Que se passe-t-il à la fin de l\'essai ?', answer: 'Vous choisissez un plan ou vos données restent archivées 30 jours supplémentaires. Aucune facturation automatique sans votre accord.', category: 'Essai' },
        { id: 'support', question: 'Quel support est disponible ?', answer: 'Starter : email sous 48h. Business : priorité 24h. Enterprise : account manager dédié + SLA contractuel.', category: 'Support' },
        { id: 'data-location', question: 'Où sont hébergées mes données ?', answer: 'En Europe (Render EU / Supabase EU). Chiffrement AES-256 au repos, TLS 1.3 en transit. Isolation par tenant garantie.', category: 'Sécurité' },
        { id: 'gdpr', question: 'Êtes-vous conformes RGPD ?', answer: 'Oui. DPA disponible, données exclusivement en Europe, droit à l\'effacement implémenté, exports de données sur demande.', category: 'Sécurité' },
        { id: 'api', question: 'L\'API est-elle disponible sur le plan Starter ?', answer: 'L\'API REST et les webhooks sont disponibles à partir du plan Business. Sur Starter, vous pouvez exporter vos données en CSV/Excel.', category: 'Technique' },
      ],
    },
    cta: {
      badge: 'Prêt à démarrer',
      headline: 'Lancez vos RH terrain dès aujourd\'hui',
      subheadline: 'Rejoignez les équipes qui ont réduit leur temps de paie de 2h à 8 minutes.',
      primary: 'Démarrer gratuitement',
      secondary: 'Contacter les ventes',
    },
  },
  en: {
    hero: {
      badge: 'Transparent pricing',
      headline: 'Pricing built for field HR teams',
      subheadline: '30 days to test attendance, payroll, leave and HR workflows before committing. No credit card.',
      primary: 'Start 30-day free trial',
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
      trialNote: '30 days free · No credit card required',
    },
    trust: {
      items: [
        'No commitment on pilot plan',
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
            { name: 'Web & mobile attendance', starter: true, business: true, enterprise: true },
            { name: 'Absences & leave', starter: true, business: true, enterprise: true },
            { name: 'Shared calendar', starter: true, business: true, enterprise: true },
            { name: 'Guided onboarding', starter: true, business: true, enterprise: true },
            { name: 'Reviews & performance', starter: false, business: true, enterprise: true },
            { name: 'Dynamic org chart', starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'Payroll & finance',
          features: [
            { name: 'Automated payroll', starter: true, business: true, enterprise: true },
            { name: 'PDF pay slips', starter: true, business: true, enterprise: true },
            { name: 'Accounting exports', starter: false, business: true, enterprise: true },
            { name: 'Salary advances', starter: false, business: true, enterprise: true },
            { name: 'Multi-country & currency', starter: false, business: false, enterprise: true },
            { name: 'Advanced legal compliance', starter: false, business: false, enterprise: true },
          ],
        },
        {
          category: 'Field & mobile',
          features: [
            { name: 'Employee mobile app', starter: true, business: true, enterprise: true },
            { name: 'Manager mobile app', starter: true, business: true, enterprise: true },
            { name: 'Offline mode', starter: true, business: true, enterprise: true },
            { name: 'ZKTeco biometrics', starter: false, business: true, enterprise: true },
            { name: 'Dedicated HR kiosk', starter: false, business: true, enterprise: true },
            { name: 'GPS & geofencing', starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'Security & integrations',
          features: [
            { name: 'Document vault', starter: false, business: true, enterprise: true },
            { name: 'REST API & Webhooks', starter: false, business: true, enterprise: true },
            { name: 'SSO SAML / OIDC', starter: false, business: false, enterprise: true },
            { name: 'Immutable audit trail', starter: false, business: false, enterprise: true },
            { name: 'Isolated PostgreSQL schema', starter: false, business: false, enterprise: true },
            { name: 'Dedicated SLA & support', starter: false, business: false, enterprise: true },
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
        { id: 'change-plan', question: 'Can I change plan later?', answer: 'Yes, anytime. Upgrades are instant, downgrades apply at the next cycle. No hidden fees.', category: 'Billing' },
        { id: 'per-employee', question: 'How does per-employee billing work?', answer: 'Each plan includes a base fee plus a per-active-employee rate (employees who clocked in at least once that month). Inactive employees are not charged.', category: 'Billing' },
        { id: 'free-trial', question: 'Is the trial really free?', answer: 'Yes. 30 full days with all features of the Pilot plan. No credit card needed to sign up.', category: 'Trial' },
        { id: 'trial-to-paid', question: 'What happens when the trial ends?', answer: 'You choose a plan or your data stays archived for 30 more days. No automatic billing without your consent.', category: 'Trial' },
        { id: 'support', question: 'What support is available?', answer: 'Starter: email within 48h. Business: priority 24h. Enterprise: dedicated account manager + contractual SLA.', category: 'Support' },
        { id: 'data-location', question: 'Where is my data hosted?', answer: 'In Europe (Render EU / Supabase EU). AES-256 encryption at rest, TLS 1.3 in transit. Tenant isolation guaranteed.', category: 'Security' },
        { id: 'gdpr', question: 'Are you GDPR compliant?', answer: 'Yes. DPA available, data exclusively in Europe, right to erasure implemented, data exports on request.', category: 'Security' },
        { id: 'api', question: 'Is the API available on Starter?', answer: 'REST API and webhooks are available from the Business plan. On Starter you can export data as CSV/Excel.', category: 'Technical' },
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
      badge: 'Şeffaf fiyatlandırma',
      headline: 'Saha HR ekipleri için fiyatlandırma',
      subheadline: '30 gün boyunca yoklama, bordro, izinler ve HR iş akışlarını deneyin. Kredi kartı gerektirmez.',
      primary: '30 günlük ücretsiz deneme',
      secondary: 'Uzmanla konuş',
    },
    plans: {
      badge: 'Planlarımız',
      title: 'Büyümenizin her aşaması için bir plan',
      subtitle: 'Küçük başlayın, platform değiştirmeden büyüyün.',
      monthly: 'Aylık',
      annual: 'Yıllık',
      savings: '%20 tasarruf',
      customPrice: 'Teklif alın',
      periodMonthly: '/ay',
      periodAnnual: '/ay yıllık faturalama',
      trialNote: '30 gün ücretsiz · Kredi kartı gerekmez',
    },
    trust: {
      items: [
        'Pilot planda taahhüt yok',
        'İlk günden destek dahil',
        'Avrupa\'da barındırılan veriler',
        'İstediğiniz zaman iptal',
      ],
    },
    comparison: {
      badge: 'Tam karşılaştırma',
      title: 'Dahil olan her şey',
      subtitle: 'plan bazında',
      featureColumn: 'Özellik',
      categories: [
        {
          category: 'İK Yönetimi',
          features: [
            { name: 'Web & mobil devam takibi', starter: true, business: true, enterprise: true },
            { name: 'Devamsızlık & izin', starter: true, business: true, enterprise: true },
            { name: 'Paylaşılan takvim', starter: true, business: true, enterprise: true },
            { name: 'Rehberli işe alım', starter: true, business: true, enterprise: true },
            { name: 'Değerlendirme & performans', starter: false, business: true, enterprise: true },
            { name: 'Dinamik organizasyon şeması', starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'Bordro & Finans',
          features: [
            { name: 'Otomatik bordro hesabı', starter: true, business: true, enterprise: true },
            { name: 'PDF bordro dökümü', starter: true, business: true, enterprise: true },
            { name: 'Muhasebe dışa aktarımı', starter: false, business: true, enterprise: true },
            { name: 'Maaş avansı', starter: false, business: true, enterprise: true },
            { name: 'Çok ülke & çok para birimi', starter: false, business: false, enterprise: true },
            { name: 'Gelişmiş yasal uyumluluk', starter: false, business: false, enterprise: true },
          ],
        },
        {
          category: 'Saha & Mobil',
          features: [
            { name: 'Çalışan mobil uygulaması', starter: true, business: true, enterprise: true },
            { name: 'Yönetici mobil uygulaması', starter: true, business: true, enterprise: true },
            { name: 'Çevrimdışı mod', starter: true, business: true, enterprise: true },
            { name: 'ZKTeco biyometri entegrasyonu', starter: false, business: true, enterprise: true },
            { name: 'Özel HR kiosk', starter: false, business: true, enterprise: true },
            { name: 'GPS & coğrafi sınır', starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'Güvenlik & Entegrasyonlar',
          features: [
            { name: 'Belge kasası', starter: false, business: true, enterprise: true },
            { name: 'REST API & Webhook', starter: false, business: true, enterprise: true },
            { name: 'SSO SAML / OIDC', starter: false, business: false, enterprise: true },
            { name: 'Değiştirilemez denetim kaydı', starter: false, business: false, enterprise: true },
            { name: 'İzole PostgreSQL şeması', starter: false, business: false, enterprise: true },
            { name: 'Özel SLA & destek', starter: false, business: false, enterprise: true },
          ],
        },
      ],
    },
    faq: {
      badge: 'Fiyat SSS',
      title: 'Sık sorulan sorular',
      subtitle: 'Başlamadan önce kontrol edilecek noktalar',
      all: 'Tümü',
      categories: ['Faturalama', 'Deneme', 'Destek', 'Güvenlik', 'Teknik'],
      items: [
        { id: 'change-plan', question: 'Planı değiştirebilir miyim?', answer: 'Evet, istediğiniz zaman. Yükseltme anında, düşürme bir sonraki dönemde uygulanır. Gizli ücret yoktur.', category: 'Faturalama' },
        { id: 'per-employee', question: 'Çalışan başı faturalama nasıl çalışır?', answer: 'Her plan sabit bir temel ücret artı aktif çalışan başına ücret içerir. O ay en az bir kez giriş yapan çalışanlar aktif sayılır.', category: 'Faturalama' },
        { id: 'free-trial', question: 'Deneme gerçekten ücretsiz mi?', answer: 'Evet. Pilot planın tüm özellikleriyle 30 tam gün. Kaydolmak için kredi kartı gerekmez.', category: 'Deneme' },
        { id: 'trial-to-paid', question: 'Deneme bitince ne olur?', answer: 'Bir plan seçersiniz ya da verileriniz 30 gün daha arşivlenir. Onayınız olmadan otomatik faturalama yapılmaz.', category: 'Deneme' },
        { id: 'support', question: 'Hangi destek sağlanır?', answer: 'Starter: 48 saatte e-posta. Business: 24 saatte öncelikli yanıt. Enterprise: özel hesap yöneticisi + sözleşmesel SLA.', category: 'Destek' },
        { id: 'data-location', question: 'Verilerim nerede barındırılır?', answer: 'Avrupa\'da (Render EU / Supabase EU). Durağan veriler AES-256, iletimde TLS 1.3. Tenant izolasyonu garantili.', category: 'Güvenlik' },
        { id: 'gdpr', question: 'KVKK uyumlu musunuz?', answer: 'Evet. DPA mevcut, veriler yalnızca Avrupa\'da, silme hakkı uygulanmış, talep üzerine veri dışa aktarımı.', category: 'Güvenlik' },
        { id: 'api', question: 'Starter\'da API kullanılabilir mi?', answer: 'REST API ve webhook\'lar Business planından itibaren kullanılabilir. Starter\'da verileri CSV/Excel olarak dışa aktarabilirsiniz.', category: 'Teknik' },
      ],
    },
    cta: {
      badge: 'Başlamaya hazır',
      headline: 'Saha İK\'nızı bugün başlatın',
      subheadline: 'Bordro süresini 2 saatten 8 dakikaya düşüren ekiplere katılın.',
      primary: 'Ücretsiz başla',
      secondary: 'Satış ekibine ulaş',
    },
  },
  ar: {
    hero: {
      badge: 'تسعير شفاف',
      headline: 'أسعار مصممة لفرق الموارد البشرية الميدانية',
      subheadline: '30 يومًا لاختبار الحضور والرواتب والإجازات وسير العمل قبل الاشتراك. بدون بطاقة ائتمان.',
      primary: 'ابدأ 30 يومًا مجانًا',
      secondary: 'تحدث مع خبير',
    },
    plans: {
      badge: 'خططنا',
      title: 'خطة لكل مرحلة من مراحل نموك',
      subtitle: 'ابدأ صغيرًا، توسع دون تغيير المنصة.',
      monthly: 'شهري',
      annual: 'سنوي',
      savings: 'وفّر 20%',
      customPrice: 'حسب الطلب',
      periodMonthly: '/شهر',
      periodAnnual: '/شهر مع فوترة سنوية',
      trialNote: '30 يومًا مجانًا · لا بطاقة ائتمان مطلوبة',
    },
    trust: {
      items: [
        'بدون التزام في خطة التجربة',
        'دعم مشمول من اليوم الأول',
        'بيانات مستضافة في أوروبا',
        'إلغاء في أي وقت',
      ],
    },
    comparison: {
      badge: 'مقارنة كاملة',
      title: 'كل ما هو مشمول',
      subtitle: 'حسب الخطة',
      featureColumn: 'الميزة',
      categories: [
        {
          category: 'إدارة الموارد البشرية',
          features: [
            { name: 'تتبع الحضور ويب وموبايل', starter: true, business: true, enterprise: true },
            { name: 'الغياب والإجازات', starter: true, business: true, enterprise: true },
            { name: 'تقويم مشترك', starter: true, business: true, enterprise: true },
            { name: 'إعداد موجّه', starter: true, business: true, enterprise: true },
            { name: 'التقييمات والأداء', starter: false, business: true, enterprise: true },
            { name: 'هيكل تنظيمي ديناميكي', starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'الرواتب والمالية',
          features: [
            { name: 'حساب رواتب آلي', starter: true, business: true, enterprise: true },
            { name: 'قسائم رواتب PDF', starter: true, business: true, enterprise: true },
            { name: 'تصدير محاسبي', starter: false, business: true, enterprise: true },
            { name: 'سلف الرواتب', starter: false, business: true, enterprise: true },
            { name: 'متعدد الدول والعملات', starter: false, business: false, enterprise: true },
            { name: 'امتثال قانوني متقدم', starter: false, business: false, enterprise: true },
          ],
        },
        {
          category: 'الميدان والموبايل',
          features: [
            { name: 'تطبيق موبايل للموظفين', starter: true, business: true, enterprise: true },
            { name: 'تطبيق موبايل للمديرين', starter: true, business: true, enterprise: true },
            { name: 'وضع عدم الاتصال', starter: true, business: true, enterprise: true },
            { name: 'تكامل بصمة ZKTeco', starter: false, business: true, enterprise: true },
            { name: 'كشك HR مخصص', starter: false, business: true, enterprise: true },
            { name: 'GPS وتحديد المناطق', starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'الأمان والتكاملات',
          features: [
            { name: 'خزنة المستندات', starter: false, business: true, enterprise: true },
            { name: 'REST API وWebhooks', starter: false, business: true, enterprise: true },
            { name: 'SSO SAML / OIDC', starter: false, business: false, enterprise: true },
            { name: 'سجل تدقيق غير قابل للتغيير', starter: false, business: false, enterprise: true },
            { name: 'مخطط PostgreSQL معزول', starter: false, business: false, enterprise: true },
            { name: 'SLA مخصص ودعم أولوي', starter: false, business: false, enterprise: true },
          ],
        },
      ],
    },
    faq: {
      badge: 'أسئلة التسعير',
      title: 'أسئلة شائعة',
      subtitle: 'ما يجب التحقق منه قبل البدء',
      all: 'الكل',
      categories: ['الفوترة', 'التجربة', 'الدعم', 'الأمان', 'التقني'],
      items: [
        { id: 'change-plan', question: 'هل يمكنني تغيير الخطة لاحقًا؟', answer: 'نعم، في أي وقت. الترقية فورية والتخفيض يُطبق في الدورة التالية. لا رسوم مخفية.', category: 'الفوترة' },
        { id: 'per-employee', question: 'كيف تعمل الفوترة لكل موظف؟', answer: 'تتضمن كل خطة رسومًا أساسية ثابتة بالإضافة إلى سعر لكل موظف نشط (من سجّل حضورًا مرة واحدة على الأقل في الشهر).', category: 'الفوترة' },
        { id: 'free-trial', question: 'هل التجربة مجانية حقًا؟', answer: 'نعم. 30 يومًا كاملة بجميع مزايا خطة Pilot. لا بطاقة ائتمان للتسجيل.', category: 'التجربة' },
        { id: 'trial-to-paid', question: 'ماذا يحدث عند انتهاء التجربة؟', answer: 'تختار خطة أو تبقى بياناتك مؤرشفة 30 يومًا إضافية. لا فوترة تلقائية بدون موافقتك.', category: 'التجربة' },
        { id: 'support', question: 'ما نوع الدعم المتاح؟', answer: 'Starter: بريد إلكتروني خلال 48 ساعة. Business: أولوية 24 ساعة. Enterprise: مدير حساب مخصص + SLA تعاقدي.', category: 'الدعم' },
        { id: 'data-location', question: 'أين تُستضاف بياناتي؟', answer: 'في أوروبا (Render EU / Supabase EU). تشفير AES-256 أثناء التخزين وTLS 1.3 أثناء النقل. عزل المستأجرين مضمون.', category: 'الأمان' },
        { id: 'gdpr', question: 'هل أنتم متوافقون مع GDPR؟', answer: 'نعم. DPA متاح، البيانات في أوروبا حصرًا، حق الحذف مُطبَّق، تصدير البيانات عند الطلب.', category: 'الأمان' },
        { id: 'api', question: 'هل API متاح في خطة Starter؟', answer: 'REST API والـ Webhooks متاحة من خطة Business. في Starter يمكنك تصدير البيانات بصيغة CSV/Excel.', category: 'التقني' },
      ],
    },
    cta: {
      badge: 'جاهز للبدء',
      headline: 'أطلق إدارة الموارد البشرية الميدانية اليوم',
      subheadline: 'انضم إلى الفرق التي خفّضت وقت إعداد الرواتب من ساعتين إلى 8 دقائق.',
      primary: 'ابدأ مجانًا',
      secondary: 'تواصل مع المبيعات',
    },
  },
};

/* ─────────────────────────────────────────────
   PLAN ICONS
───────────────────────────────────────────── */
const planIcons = [Rocket, Crown, Building2] as const;
const planIconColors = [
  'text-blue-500',
  'text-emerald-500',
  'text-violet-500',
] as const;

/* ─────────────────────────────────────────────
   AVAILABILITY MARK
───────────────────────────────────────────── */
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

/* ─────────────────────────────────────────────
   FAQ ITEM
───────────────────────────────────────────── */
function FaqAccordionItem({ item, isOpen, onToggle }: {
  item: FaqItem;
  isOpen: boolean;
  onToggle: () => void;
}) {
  return (
    <div className="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
      <button
        onClick={onToggle}
        className="w-full flex items-center justify-between gap-4 p-6 text-left hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors"
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

/* ─────────────────────────────────────────────
   PAGE
───────────────────────────────────────────── */
export default function PricingPage() {
  const [isDark, setIsDark] = useState(false);
  const [isAnnual, setIsAnnual] = useState(true);
  const [openFaqId, setOpenFaqId] = useState<string | null>('free-trial');
  const [faqCategory, setFaqCategory] = useState<string | null>(null);

  const { locale, direction } = useVitrineLocale();
  const copy = pricingPageCopy[locale] ?? pricingPageCopy.fr;
  const plans = getPricingPlans(locale);
  useScrollReveal();

  function showsCurrency(price: string) {
    return !['Sur devis', 'Custom', 'Teklif', 'حسب العرض', 'Teklif alın', 'حسب الطلب'].includes(price);
  }

  function getPlanHref(plan: ReturnType<typeof getPricingPlans>[number]) {
    if (!showsCurrency(plan.price)) return '/contact?type=enterprise';
    if (plan.popular) return '/signup?plan=business';
    return '/signup?plan=starter';
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

      {/* ── HERO ───────────────────────────────── */}
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
              href="/signup"
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

      {/* ── PRICING CARDS ──────────────────────── */}
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

          {/* Cards */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl mx-auto">
            {plans.map((plan, index) => {
              const Icon = planIcons[index % planIcons.length];
              const iconColor = planIconColors[index % planIconColors.length];
              const displayPrice = isAnnual ? plan.annualPrice : plan.price;
              const hasNumericPrice = showsCurrency(displayPrice);
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
                      : 'bg-slate-200/70 dark:bg-slate-800/70 p-px'
                  }`}
                >
                  <div className="relative h-full rounded-[23px] bg-white dark:bg-slate-950 flex flex-col p-8">
                    {/* Popular badge */}
                    {plan.popular && (
                      <div className="absolute -top-4 left-1/2 -translate-x-1/2 z-10">
                        <div className="flex items-center gap-1.5 px-4 py-1.5 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white text-[11px] font-black uppercase tracking-widest rounded-full shadow-lg shadow-emerald-500/30">
                          <Star className="w-3 h-3 fill-white" />
                          Le plus populaire
                        </div>
                      </div>
                    )}

                    {/* Plan header */}
                    <div className="mb-8">
                      <div className={`inline-flex items-center justify-center w-12 h-12 rounded-2xl mb-4 bg-slate-100 dark:bg-slate-800/80`}>
                        <Icon className={`w-6 h-6 ${iconColor}`} />
                      </div>
                      <h3 className="text-xl font-black text-slate-900 dark:text-white mb-1">{plan.name}</h3>
                      <p className="text-sm text-slate-500 dark:text-slate-400">{plan.description}</p>
                    </div>

                    {/* Price */}
                    <div className="mb-6">
                      <div className="flex items-baseline gap-1.5">
                        {hasNumericPrice && (
                          <span className="text-lg font-bold text-slate-500 dark:text-slate-400">EUR</span>
                        )}
                        <span className="text-5xl font-black bg-gradient-to-b from-slate-900 to-slate-600 dark:from-white dark:to-slate-400 bg-clip-text text-transparent">
                          {displayPrice}
                        </span>
                      </div>
                      {hasNumericPrice && (
                        <div className="mt-1 space-y-0.5">
                          <p className="text-sm text-slate-500">
                            {isAnnual ? copy.plans.periodAnnual : copy.plans.periodMonthly}
                          </p>
                          {isAnnual && (
                            <p className="text-xs text-slate-400 dark:text-slate-600">
                              <span className="line-through">EUR {plan.price}</span>
                              {' '}
                              <span className="text-emerald-600 dark:text-emerald-400 font-semibold">{copy.plans.savings}</span>
                            </p>
                          )}
                        </div>
                      )}
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
                          <Check className={`w-4 h-4 flex-shrink-0 mt-0.5 ${plan.popular ? 'text-emerald-500' : 'text-slate-400 dark:text-slate-500'}`} />
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

      {/* ── COMPARISON TABLE ────────────────────── */}
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
            <table className="w-full min-w-[640px]">
              <thead>
                <tr className="bg-slate-50 dark:bg-slate-900">
                  <th className="text-left py-5 px-6 font-bold text-slate-900 dark:text-white text-sm w-[40%]">
                    {copy.comparison.featureColumn}
                  </th>
                  {plans.map((plan, i) => {
                    const Icon = planIcons[i % planIcons.length];
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
                              ★ top
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
                    <tr className={catIdx % 2 === 0 ? 'bg-slate-50/70 dark:bg-slate-900/30' : 'bg-emerald-50/30 dark:bg-emerald-950/10'}>
                      <td
                        colSpan={4}
                        className="py-3 px-6"
                      >
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
                        className="border-t border-slate-100 dark:border-slate-800/50 hover:bg-slate-50/80 dark:hover:bg-slate-900/30 transition-colors"
                      >
                        <td className="py-4 px-6 text-sm text-slate-700 dark:text-slate-300 font-medium">
                          {feature.name}
                        </td>
                        <td className="py-4 px-4 text-center">
                          <AvailabilityMark value={feature.starter} popular={false} />
                        </td>
                        <td className="py-4 px-4 text-center bg-emerald-50/40 dark:bg-emerald-950/10">
                          <AvailabilityMark value={feature.business} popular={true} />
                        </td>
                        <td className="py-4 px-4 text-center">
                          <AvailabilityMark value={feature.enterprise} popular={false} />
                        </td>
                      </motion.tr>
                    ))}
                  </Fragment>
                ))}
                {/* CTA row */}
                <tr className="border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900">
                  <td className="py-6 px-6" />
                  {plans.map((plan) => (
                    <td key={plan.name} className={`py-6 px-4 text-center ${plan.popular ? 'bg-emerald-50/40 dark:bg-emerald-950/10' : ''}`}>
                      <Link
                        href={getPlanHref(plan)}
                        className={`inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 hover:scale-[1.03] active:scale-[0.97] ${
                          plan.popular
                            ? 'bg-gradient-to-r from-emerald-500 to-emerald-600 text-white shadow-lg shadow-emerald-500/20'
                            : 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 hover:bg-slate-800 dark:hover:bg-slate-100'
                        }`}
                      >
                        {plan.cta}
                        <ArrowRight className="w-3.5 h-3.5" />
                      </Link>
                    </td>
                  ))}
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      {/* ── FAQ ─────────────────────────────────── */}
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
          <div className="mt-10 text-center p-6 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <p className="text-slate-700 dark:text-slate-300 font-semibold mb-3">
              {locale === 'fr' ? 'Une autre question ?' : locale === 'tr' ? 'Başka sorunuz var mı?' : locale === 'ar' ? 'سؤال آخر؟' : 'Another question?'}
            </p>
            <Link
              href="/contact"
              className="inline-flex items-center gap-2 px-6 py-3 bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold rounded-xl hover:scale-[1.02] transition-transform duration-200 text-sm"
            >
              <MessageCircle className="w-4 h-4" />
              {locale === 'fr' ? 'Contacter le support' : locale === 'tr' ? 'Destek ile iletişim' : locale === 'ar' ? 'تواصل مع الدعم' : 'Contact support'}
              <ArrowRight className="w-4 h-4" />
            </Link>
          </div>
        </div>
      </section>

      {/* ── CTA FINAL ───────────────────────────── */}
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
              href="/signup"
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
