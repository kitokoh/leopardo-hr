import type { AppLocale } from '@/lib/i18n';
import {
  Fingerprint,
  CreditCard,
  CalendarClock,
  Globe,
  FileText,
  Shield,
  Webhook,
  Smartphone,
  Building2,
} from 'lucide-react';

/**
 * Contenu de la page /integrations (catalogue inline ×4 locales).
 *
 * #AI-SEO (audit 2026-09-13) : ce catalogue vivait DANS `src/app/(landing)/
 * integrations/page.tsx` — une surface surveillée par la garde i18n
 * PA2-I18N-014, où toute retouche de libellé était comptée comme une nouvelle
 * chaîne codée en dur (constaté : la correction « Integrations » →
 * « Intégrations » a fait échouer la garde). Toutes les autres pages vitrine
 * portent leur copie dans `src/modules/vitrine/data/` : on aligne
 * /integrations sur ce modèle.
 */

export type Integration = {
  icon: React.ReactNode;
  name: string;
  description: string;
  status: 'available' | 'coming_soon';
  category: string;
};

export const integrationsByLocale: Record<AppLocale, { title: string; subtitle: string; badge: string; docsNote: string; statusLabels: { available: string; coming_soon: string }; categories: string[]; integrations: Integration[] }> = {
  fr: {
    title: 'Intégrations',
    subtitle: 'Connectez Leopardo RH à vos outils existants',
    badge: 'Ecosysteme',
    docsNote: 'API publique documentee sur',
    statusLabels: { available: 'Disponible', coming_soon: 'Bientot' },
    categories: ['Tous', 'Pointage', 'Paiement', 'Calendrier', 'API', 'Sécurité'],
    integrations: [
      { icon: <Fingerprint className="w-6 h-6" />, name: 'ZKTeco', description: 'Pointeuses biometriques TCP/IP. Synchronisation automatique des pointages.', status: 'available', category: 'Pointage' },
      { icon: <CreditCard className="w-6 h-6" />, name: 'Stripe', description: 'Paiement SaaS par carte bancaire. Abonnements et factures automatisés.', status: 'available', category: 'Paiement' },
      { icon: <CreditCard className="w-6 h-6" />, name: 'Chargily', description: 'Paiement en ligne pour l\'Algerie. CIB, EDAHABIA et virement bancaire.', status: 'available', category: 'Paiement' },
      { icon: <CalendarClock className="w-6 h-6" />, name: 'Google Calendar', description: 'Synchronisation des congés et formations avec Google Calendar.', status: 'available', category: 'Calendrier' },
      { icon: <CalendarClock className="w-6 h-6" />, name: 'Outlook Calendar', description: 'Synchronisation des événements RH avec Microsoft Outlook.', status: 'available', category: 'Calendrier' },
      { icon: <Globe className="w-6 h-6" />, name: 'API REST publique', description: 'API versionnee (v1) avec documentation OpenAPI. Rate limiting par plan.', status: 'available', category: 'API' },
      { icon: <Webhook className="w-6 h-6" />, name: 'Webhooks', description: 'Notifications HTTP pour les événements RH (embauche, paie, congé, pointage).', status: 'available', category: 'API' },
      { icon: <Shield className="w-6 h-6" />, name: 'SSO SAML/OIDC', description: 'Authentification unique via Azure AD, Google Workspace ou Okta.', status: 'coming_soon', category: 'Sécurité' },
      { icon: <FileText className="w-6 h-6" />, name: 'Sage Comptabilité', description: 'Export des ecritures de paie vers Sage 50/100. Format FEC compatible.', status: 'coming_soon', category: 'API' },
      { icon: <FileText className="w-6 h-6" />, name: 'QuickBooks', description: 'Synchronisation des ecritures de paie vers QuickBooks Online.', status: 'coming_soon', category: 'API' },
      { icon: <Smartphone className="w-6 h-6" />, name: 'Firebase', description: 'Push notifications pour l\'app mobile. Alertes pointage, paie et conges.', status: 'available', category: 'API' },
      { icon: <Building2 className="w-6 h-6" />, name: 'Slack / Teams', description: 'Notifications RH dans vos canaux de communication existants.', status: 'coming_soon', category: 'API' },
    ],
  },
  en: {
    title: 'Integrations',
    subtitle: 'Connect Leopardo RH to your existing tools',
    badge: 'Ecosystem',
    docsNote: 'Public API documented at',
    statusLabels: { available: 'Available', coming_soon: 'Coming soon' },
    categories: ['All', 'Attendance', 'Payment', 'Calendar', 'API', 'Security'],
    integrations: [
      { icon: <Fingerprint className="w-6 h-6" />, name: 'ZKTeco', description: 'Biometric attendance terminals via TCP/IP. Automatic attendance sync.', status: 'available', category: 'Attendance' },
      { icon: <CreditCard className="w-6 h-6" />, name: 'Stripe', description: 'SaaS card payments. Automated subscriptions and invoices.', status: 'available', category: 'Payment' },
      { icon: <CreditCard className="w-6 h-6" />, name: 'Chargily', description: 'Online payments for Algeria. CIB, EDAHABIA and bank transfer.', status: 'available', category: 'Payment' },
      { icon: <CalendarClock className="w-6 h-6" />, name: 'Google Calendar', description: 'Sync leave and training events with Google Calendar.', status: 'available', category: 'Calendar' },
      { icon: <CalendarClock className="w-6 h-6" />, name: 'Outlook Calendar', description: 'Sync HR events with Microsoft Outlook.', status: 'available', category: 'Calendar' },
      { icon: <Globe className="w-6 h-6" />, name: 'Public REST API', description: 'Versioned API (v1) with OpenAPI docs. Rate limiting per plan.', status: 'available', category: 'API' },
      { icon: <Webhook className="w-6 h-6" />, name: 'Webhooks', description: 'HTTP notifications for HR events (hire, payroll, leave, attendance).', status: 'available', category: 'API' },
      { icon: <Shield className="w-6 h-6" />, name: 'SSO SAML/OIDC', description: 'Single sign-on via Azure AD, Google Workspace or Okta.', status: 'coming_soon', category: 'Security' },
      { icon: <FileText className="w-6 h-6" />, name: 'Sage Accounting', description: 'Export payroll entries to Sage 50/100. FEC-compatible format.', status: 'coming_soon', category: 'API' },
      { icon: <FileText className="w-6 h-6" />, name: 'QuickBooks', description: 'Sync payroll entries to QuickBooks Online.', status: 'coming_soon', category: 'API' },
      { icon: <Smartphone className="w-6 h-6" />, name: 'Firebase', description: 'Push notifications for the mobile app. Attendance, payroll and leave alerts.', status: 'available', category: 'API' },
      { icon: <Building2 className="w-6 h-6" />, name: 'Slack / Teams', description: 'HR notifications in your existing communication channels.', status: 'coming_soon', category: 'API' },
    ],
  },
  tr: {
    title: 'Entegrasyonlar',
    subtitle: 'Leopardo RH yi kullandiginiz araclara baglayin',
    badge: 'Ekosistem',
    docsNote: 'Herkese acik API dokumani',
    statusLabels: { available: 'Hazir', coming_soon: 'Yakinda' },
    categories: ['Tumu', 'Devam', 'Odeme', 'Takvim', 'API', 'Guvenlik'],
    integrations: [
      { icon: <Fingerprint className="w-6 h-6" />, name: 'ZKTeco', description: 'TCP/IP biyometrik cihazlar. Devam kayitlari otomatik senkronize edilir.', status: 'available', category: 'Devam' },
      { icon: <CreditCard className="w-6 h-6" />, name: 'Stripe', description: 'Kartla SaaS odemeleri. Abonelik ve faturalar otomatik yonetilir.', status: 'available', category: 'Odeme' },
      { icon: <CreditCard className="w-6 h-6" />, name: 'Chargily', description: 'Cezayir icin online odeme: CIB, EDAHABIA ve banka transferi.', status: 'available', category: 'Odeme' },
      { icon: <CalendarClock className="w-6 h-6" />, name: 'Google Calendar', description: 'Izin ve egitim etkinliklerini Google Calendar ile senkronize edin.', status: 'available', category: 'Takvim' },
      { icon: <CalendarClock className="w-6 h-6" />, name: 'Outlook Calendar', description: 'IK etkinliklerini Microsoft Outlook ile senkronize edin.', status: 'available', category: 'Takvim' },
      { icon: <Globe className="w-6 h-6" />, name: 'Herkese acik REST API', description: 'OpenAPI dokumanli versiyonlu API (v1). Plana gore rate limit.', status: 'available', category: 'API' },
      { icon: <Webhook className="w-6 h-6" />, name: 'Webhooks', description: 'Ise alim, bordro, izin ve devam olaylari icin HTTP bildirimleri.', status: 'available', category: 'API' },
      { icon: <Shield className="w-6 h-6" />, name: 'SSO SAML/OIDC', description: 'Azure AD, Google Workspace veya Okta ile tek oturum acma.', status: 'coming_soon', category: 'Guvenlik' },
      { icon: <FileText className="w-6 h-6" />, name: 'Sage Muhasebe', description: 'Bordro muhasebe kayitlarini Sage 50/100 formatina aktarim.', status: 'coming_soon', category: 'API' },
      { icon: <FileText className="w-6 h-6" />, name: 'QuickBooks', description: 'Bordro muhasebe kayitlarini QuickBooks Online ile senkronize edin.', status: 'coming_soon', category: 'API' },
      { icon: <Smartphone className="w-6 h-6" />, name: 'Firebase', description: 'Mobil uygulama icin push bildirimleri: devam, bordro ve izin uyarilari.', status: 'available', category: 'API' },
      { icon: <Building2 className="w-6 h-6" />, name: 'Slack / Teams', description: 'IK bildirimlerini mevcut iletisim kanallariniza tasiyin.', status: 'coming_soon', category: 'API' },
    ],
  },
  ar: {
    title: 'التكاملات',
    subtitle: 'اربط Leopardo RH بأدواتك الحالية',
    badge: 'النظام البيئي',
    docsNote: 'توثيق API العام على',
    statusLabels: { available: 'متاح', coming_soon: 'قريبا' },
    categories: ['الكل', 'الحضور', 'الدفع', 'التقويم', 'API', 'الأمان'],
    integrations: [
      { icon: <Fingerprint className="w-6 h-6" />, name: 'ZKTeco', description: 'أجهزة حضور بيومترية عبر TCP/IP مع مزامنة تلقائية للحضور.', status: 'available', category: 'الحضور' },
      { icon: <CreditCard className="w-6 h-6" />, name: 'Stripe', description: 'مدفوعات SaaS بالبطاقة مع اشتراكات وفواتير آلية.', status: 'available', category: 'الدفع' },
      { icon: <CreditCard className="w-6 h-6" />, name: 'Chargily', description: 'مدفوعات إلكترونية للجزائر عبر CIB و EDAHABIA والتحويل البنكي.', status: 'available', category: 'الدفع' },
      { icon: <CalendarClock className="w-6 h-6" />, name: 'Google Calendar', description: 'مزامنة الإجازات والتكوينات مع Google Calendar.', status: 'available', category: 'التقويم' },
      { icon: <CalendarClock className="w-6 h-6" />, name: 'Outlook Calendar', description: 'مزامنة أحداث الموارد البشرية مع Microsoft Outlook.', status: 'available', category: 'التقويم' },
      { icon: <Globe className="w-6 h-6" />, name: 'REST API عام', description: 'API بإصدار v1 مع توثيق OpenAPI وحدود استخدام حسب الخطة.', status: 'available', category: 'API' },
      { icon: <Webhook className="w-6 h-6" />, name: 'Webhooks', description: 'إشعارات HTTP لأحداث التوظيف والرواتب والإجازات والحضور.', status: 'available', category: 'API' },
      { icon: <Shield className="w-6 h-6" />, name: 'SSO SAML/OIDC', description: 'تسجيل دخول موحد عبر Azure AD أو Google Workspace أو Okta.', status: 'coming_soon', category: 'الأمان' },
      { icon: <FileText className="w-6 h-6" />, name: 'Sage Accounting', description: 'تصدير قيود الرواتب إلى Sage 50/100 بتنسيق متوافق.', status: 'coming_soon', category: 'API' },
      { icon: <FileText className="w-6 h-6" />, name: 'QuickBooks', description: 'مزامنة قيود الرواتب مع QuickBooks Online.', status: 'coming_soon', category: 'API' },
      { icon: <Smartphone className="w-6 h-6" />, name: 'Firebase', description: 'إشعارات فورية لتطبيق الهاتف: الحضور، الرواتب والإجازات.', status: 'available', category: 'API' },
      { icon: <Building2 className="w-6 h-6" />, name: 'Slack / Teams', description: 'إشعارات الموارد البشرية داخل قنوات التواصل الحالية.', status: 'coming_soon', category: 'API' },
    ],
  },
};
