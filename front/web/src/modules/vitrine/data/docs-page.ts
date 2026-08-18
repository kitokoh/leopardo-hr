import type { AppLocale } from '@/lib/i18n'

// Contenu de la page /docs par locale (issue #4175 — audit 2026-08-16 :
// la page était 100 % FR codé en dur, métadonnées localisées mais corps FR).
// Pattern #2605/#3764 : les chaînes vivent ici, les icônes/mise en page
// restent dans la page. Les exemples de code restent techniques (FR/EN).
//
// Structure des catégories : chaque entrée référence un `iconKey` résolu dans
// la page (les icônes React ne sérialisent pas dans un fichier de données).

export type DocsCategory = {
  title: string
  iconKey: string
  color: string
  items: Array<{ title: string; desc: string; href: string }>
}

export type DocsCopy = {
  hero: {
    badge: string
    headlineTop: string
    headlineHighlight: string
    subheadline: string
    searchPlaceholder: string
    tags: string[]
  }
  apiSection: {
    title: string
    subtitle: string
    copyLabel: string
    copiedLabel: string
  }
  webhooksSection: {
    title: string
    subtitle: string
    eventGroups: Array<{ group: string; events: string[] }>
    securityNote: string
    docLink: string
  }
  sdkSection: {
    title: string
    subtitle: string
    apps: Array<{ name: string; desc: string }>
    learnMore: string
  }
  kioskSection: {
    title: string
    subtitle: string
    installTitle: string
    installSteps: string[]
    howItWorksTitle: string
    howItWorksItems: string[]
    sourceNote: string
    downloadLink: string
  }
  securitySection: {
    title: string
    subtitle: string
    items: Array<{ title: string; desc: string }>
  }
  mobileInstallSection: {
    title: string
    subtitle: string
    apps: Array<{ name: string; desc: string; href: string }>
    storesNote: string
    soonLabel: string
    testerCta: string
    detailsNote: string
    mobilePageLink: string
  }
  categoriesSection: {
    emptyTitle: string
    emptyCta: string
  }
  quickLinks: {
    title: string
    items: Array<{ icon: string; label: string; desc: string; href: string }>
  }
}

export const docsCategoriesCopy: Record<AppLocale, DocsCategory[]> = {
  fr: [
    {
      title: 'Démarrage rapide',
      iconKey: 'zap',
      color: 'emerald',
      items: [
        { title: 'Introduction', desc: "Vue d'ensemble de Leopardo RH — Mobile-First Company OS", href: '/docs#intro' },
        { title: 'Inscription & premier tenant', desc: 'Créer un compte et configurer votre entreprise', href: '/docs#api-quickstart' },
        { title: 'Inviter votre équipe', desc: 'Ajouter des managers et des employés', href: '/docs#api-quickstart' },
        { title: 'Pointage depuis le kiosque', desc: 'Configurer une borne ZKTeco', href: '/docs#kiosk' },
      ],
    },
    {
      title: 'Espace Manager',
      iconKey: 'layout',
      color: 'blue',
      items: [
        { title: 'Tableau de bord', desc: 'KPIs, alertes, activité recente', href: '/docs#api-quickstart' },
        { title: 'Gestion des absences', desc: 'Demandes, approbations, soldes', href: '/docs#webhooks-overview' },
        { title: 'Paie & bulletins', desc: 'Lancer une paie, generer les bulletins PDF', href: '/docs#webhooks-overview' },
        { title: 'Contrats & documents', desc: 'Gestion documentaire securisee', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'Applications Mobiles',
      iconKey: 'smartphone',
      color: 'violet',
      items: [
        { title: 'Leopardo Employee', desc: 'Pointage, demandes, bulletin, notifications push', href: '/docs#sdk-overview' },
        { title: 'Leopardo Manager', desc: 'Équipe, horaires, taches, approbations', href: '/docs#sdk-overview' },
        { title: 'Platform Admin', desc: 'Super-admin : creation tenant, supervision', href: '/docs#sdk-overview' },
        { title: 'Installer les applications', desc: 'Android / iOS, distribution, versions', href: '/docs#mobile-install' },
        { title: 'Notifications push (FCM)', desc: 'Configurer Firebase Cloud Messaging', href: '/docs#sdk-overview' },
      ],
    },
    {
      title: 'API REST — Reference',
      iconKey: 'code',
      color: 'amber',
      items: [
        { title: 'Authentification', desc: 'Bearer token, /auth/login, /auth/me, Google OAuth', href: '/docs#api-quickstart' },
        { title: 'Employés & RH', desc: 'CRUD employés, absences, pointages, paie', href: '/docs#api-quickstart' },
        { title: 'Platform Admin', desc: 'Tenants, création entreprise, super-admin', href: '/docs#api-quickstart' },
        { title: 'Erreurs & pagination', desc: 'Codes erreur standards, throttling, curseur', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'Webhooks & Events',
      iconKey: 'webhook',
      color: 'pink',
      items: [
        { title: 'Introduction aux webhooks', desc: 'Signature HMAC-SHA256, retry, idempotence', href: '/docs#webhooks-overview' },
        { title: 'Événements disponibles', desc: 'attendance.*, leave.*, salary_advance.*, payroll.*', href: '/docs#webhooks-events' },
        { title: 'Securite & verification', desc: 'Valider la signature X-Leopardo-Signature', href: '/docs#webhooks-security' },
        { title: 'Tester en local', desc: "ngrok, cli-test, replay d'evenements", href: '/docs#webhooks-overview' },
      ],
    },
    {
      title: 'SDK Mobiles',
      iconKey: 'package',
      color: 'orange',
      items: [
        { title: 'leopardo_core (Flutter)', desc: 'Package partagé — ApiClient, SecureStorage, modeles', href: '/docs#sdk-overview' },
        { title: 'Auth & Google Sign-In', desc: 'GoogleSignIn v7+ initialize(), idToken, backend JWT', href: '/docs#sdk-overview' },
        { title: 'Notifications (FCM)', desc: 'FirebaseMessaging, foreground/background, deep links', href: '/docs#sdk-overview' },
        { title: 'Publication & CI', desc: 'GitHub Actions flutter-ci.yml, build, tests', href: '/docs#sdk-overview' },
      ],
    },
    {
      title: 'API Playground',
      iconKey: 'play',
      color: 'teal',
      items: [
        { title: 'Environnement sandbox', desc: 'URL demo Render, comptes de test, token Bearer demo', href: '/docs#api-quickstart' },
        { title: 'Explorer les endpoints', desc: 'Interface Swagger / Redoc interactive', href: '/docs#api-quickstart' },
        { title: 'Exemples cURL', desc: "Collection d'appels prêts à l'emploi pour tous les modules", href: '/docs#api-quickstart' },
        { title: 'Tokens développeur', desc: 'Creer un token scope-reduit pour tests partenaires', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'Administration',
      iconKey: 'shield',
      color: 'red',
      items: [
        { title: 'Roles & permissions', desc: 'Principal, RH, Employé, Super Admin, RBAC', href: '/docs#api-quickstart' },
        { title: 'Multi-tenant', desc: 'Architecture par schema PostgreSQL', href: '/docs#api-quickstart' },
        { title: 'Securite & RGPD', desc: 'Chiffrement, audit trail, conformite', href: '/docs#security' },
        { title: 'Déploiement', desc: 'Docker, Render, Vercel, variables env', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'Integrations',
      iconKey: 'plug',
      color: 'cyan',
      items: [
        { title: 'ZKTeco', desc: 'Configuration des bornes biometriques', href: '/docs#kiosk' },
        { title: 'Calendrier (CalDAV)', desc: 'Synchronisation agenda', href: '/docs#api-quickstart' },
        { title: 'Exports bancaires', desc: 'SEPA, CCP, CSV', href: '/docs#api-quickstart' },
        { title: 'Guide partenaire API', desc: "Guide d'integration pour ISV et partenaires", href: '/docs#api-quickstart' },
      ],
    },
  ],
  en: [
    {
      title: 'Quick Start',
      iconKey: 'zap',
      color: 'emerald',
      items: [
        { title: 'Introduction', desc: 'Leopardo RH overview — Mobile-First Company OS', href: '/docs#intro' },
        { title: 'Sign up & first tenant', desc: 'Create an account and set up your company', href: '/docs#api-quickstart' },
        { title: 'Invite your team', desc: 'Add managers and employees', href: '/docs#api-quickstart' },
        { title: 'Kiosk check-in', desc: 'Set up a ZKTeco terminal', href: '/docs#kiosk' },
      ],
    },
    {
      title: 'Manager Space',
      iconKey: 'layout',
      color: 'blue',
      items: [
        { title: 'Dashboard', desc: 'KPIs, alerts, recent activity', href: '/docs#api-quickstart' },
        { title: 'Absence management', desc: 'Requests, approvals, balances', href: '/docs#webhooks-overview' },
        { title: 'Payroll & payslips', desc: 'Run payroll, generate PDF payslips', href: '/docs#webhooks-overview' },
        { title: 'Contracts & documents', desc: 'Secure document management', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'Mobile Apps',
      iconKey: 'smartphone',
      color: 'violet',
      items: [
        { title: 'Leopardo Employee', desc: 'Check-in, requests, payslip, push notifications', href: '/docs#sdk-overview' },
        { title: 'Leopardo Manager', desc: 'Team, schedules, tasks, approvals', href: '/docs#sdk-overview' },
        { title: 'Platform Admin', desc: 'Super-admin: tenant creation, supervision', href: '/docs#sdk-overview' },
        { title: 'Install the apps', desc: 'Android / iOS, distribution, versions', href: '/docs#mobile-install' },
        { title: 'Push notifications (FCM)', desc: 'Set up Firebase Cloud Messaging', href: '/docs#sdk-overview' },
      ],
    },
    {
      title: 'REST API — Reference',
      iconKey: 'code',
      color: 'amber',
      items: [
        { title: 'Authentication', desc: 'Bearer token, /auth/login, /auth/me, Google OAuth', href: '/docs#api-quickstart' },
        { title: 'Employees & HR', desc: 'Employee CRUD, absences, check-ins, payroll', href: '/docs#api-quickstart' },
        { title: 'Platform Admin', desc: 'Tenants, company creation, super-admin', href: '/docs#api-quickstart' },
        { title: 'Errors & pagination', desc: 'Standard error codes, throttling, cursor', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'Webhooks & Events',
      iconKey: 'webhook',
      color: 'pink',
      items: [
        { title: 'Webhooks introduction', desc: 'HMAC-SHA256 signature, retry, idempotency', href: '/docs#webhooks-overview' },
        { title: 'Available events', desc: 'attendance.*, leave.*, salary_advance.*, payroll.*', href: '/docs#webhooks-events' },
        { title: 'Security & verification', desc: 'Validate the X-Leopardo-Signature header', href: '/docs#webhooks-security' },
        { title: 'Test locally', desc: 'ngrok, cli-test, event replay', href: '/docs#webhooks-overview' },
      ],
    },
    {
      title: 'Mobile SDKs',
      iconKey: 'package',
      color: 'orange',
      items: [
        { title: 'leopardo_core (Flutter)', desc: 'Shared package — ApiClient, SecureStorage, models', href: '/docs#sdk-overview' },
        { title: 'Auth & Google Sign-In', desc: 'GoogleSignIn v7+ initialize(), idToken, backend JWT', href: '/docs#sdk-overview' },
        { title: 'Notifications (FCM)', desc: 'FirebaseMessaging, foreground/background, deep links', href: '/docs#sdk-overview' },
        { title: 'Release & CI', desc: 'GitHub Actions flutter-ci.yml, build, tests', href: '/docs#sdk-overview' },
      ],
    },
    {
      title: 'API Playground',
      iconKey: 'play',
      color: 'teal',
      items: [
        { title: 'Sandbox environment', desc: 'Demo Render URL, test accounts, demo Bearer token', href: '/docs#api-quickstart' },
        { title: 'Explore endpoints', desc: 'Interactive Swagger / Redoc interface', href: '/docs#api-quickstart' },
        { title: 'cURL examples', desc: 'Ready-to-use calls for every module', href: '/docs#api-quickstart' },
        { title: 'Developer tokens', desc: 'Create a scoped token for partner testing', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'Administration',
      iconKey: 'shield',
      color: 'red',
      items: [
        { title: 'Roles & permissions', desc: 'Principal, HR, Employee, Super Admin, RBAC', href: '/docs#api-quickstart' },
        { title: 'Multi-tenancy', desc: 'PostgreSQL schema architecture', href: '/docs#api-quickstart' },
        { title: 'Security & GDPR', desc: 'Encryption, audit trail, compliance', href: '/docs#security' },
        { title: 'Deployment', desc: 'Docker, Render, Vercel, env variables', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'Integrations',
      iconKey: 'plug',
      color: 'cyan',
      items: [
        { title: 'ZKTeco', desc: 'Biometric terminal configuration', href: '/docs#kiosk' },
        { title: 'Calendar (CalDAV)', desc: 'Agenda synchronisation', href: '/docs#api-quickstart' },
        { title: 'Bank exports', desc: 'SEPA, CCP, CSV', href: '/docs#api-quickstart' },
        { title: 'Partner API guide', desc: 'Integration guide for ISVs and partners', href: '/docs#api-quickstart' },
      ],
    },
  ],
  tr: [
    {
      title: 'Hızlı Başlangıç',
      iconKey: 'zap',
      color: 'emerald',
      items: [
        { title: 'Giriş', desc: 'Leopardo RH genel bakış — Mobile-First Company OS', href: '/docs#intro' },
        { title: 'Kayıt ve ilk kiracı', desc: 'Hesap oluşturun ve şirketinizi yapılandırın', href: '/docs#api-quickstart' },
        { title: 'Ekibinizi davet edin', desc: 'Yönetici ve çalışan ekleyin', href: '/docs#api-quickstart' },
        { title: 'Kiosk girişi', desc: 'ZKTeco terminali kurun', href: '/docs#kiosk' },
      ],
    },
    {
      title: 'Yönetici Alanı',
      iconKey: 'layout',
      color: 'blue',
      items: [
        { title: 'Panel', desc: 'KPI, uyarılar, son aktivite', href: '/docs#api-quickstart' },
        { title: 'İzin yönetimi', desc: 'Talepler, onaylar, bakiyeler', href: '/docs#webhooks-overview' },
        { title: 'Maaş ve bordro', desc: 'Maaş çalıştırın, PDF bordro oluşturun', href: '/docs#webhooks-overview' },
        { title: 'Sözleşmeler ve belgeler', desc: 'Güvenli belge yönetimi', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'Mobil Uygulamalar',
      iconKey: 'smartphone',
      color: 'violet',
      items: [
        { title: 'Leopardo Employee', desc: 'Giriş, talepler, bordro, push bildirimleri', href: '/docs#sdk-overview' },
        { title: 'Leopardo Manager', desc: 'Ekip, programlar, görevler, onaylar', href: '/docs#sdk-overview' },
        { title: 'Platform Admin', desc: 'Süper yönetici: kiracı oluşturma, denetim', href: '/docs#sdk-overview' },
        { title: 'Uygulamaları kurun', desc: 'Android / iOS, dağıtım, sürümler', href: '/docs#mobile-install' },
        { title: 'Push bildirimleri (FCM)', desc: 'Firebase Cloud Messaging kurulumu', href: '/docs#sdk-overview' },
      ],
    },
    {
      title: 'REST API — Referans',
      iconKey: 'code',
      color: 'amber',
      items: [
        { title: 'Kimlik doğrulama', desc: 'Bearer token, /auth/login, /auth/me, Google OAuth', href: '/docs#api-quickstart' },
        { title: 'Çalışanlar ve İK', desc: 'Çalışan CRUD, izinler, girişler, maaş', href: '/docs#api-quickstart' },
        { title: 'Platform Admin', desc: 'Kiracılar, şirket oluşturma, süper yönetici', href: '/docs#api-quickstart' },
        { title: 'Hatalar ve sayfalama', desc: 'Standart hata kodları, sınırlama, imleç', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'Webhooks ve Olaylar',
      iconKey: 'webhook',
      color: 'pink',
      items: [
        { title: 'Webhook giriş', desc: 'HMAC-SHA256 imza, yeniden deneme, idempotans', href: '/docs#webhooks-overview' },
        { title: 'Mevcut olaylar', desc: 'attendance.*, leave.*, salary_advance.*, payroll.*', href: '/docs#webhooks-events' },
        { title: 'Güvenlik ve doğrulama', desc: 'X-Leopardo-Signature imzasını doğrulayın', href: '/docs#webhooks-security' },
        { title: 'Yerel test', desc: 'ngrok, cli-test, olay tekrarı', href: '/docs#webhooks-overview' },
      ],
    },
    {
      title: 'Mobil SDK',
      iconKey: 'package',
      color: 'orange',
      items: [
        { title: 'leopardo_core (Flutter)', desc: 'Paylaşılan paket — ApiClient, SecureStorage, modeller', href: '/docs#sdk-overview' },
        { title: 'Auth ve Google Sign-In', desc: 'GoogleSignIn v7+ initialize(), idToken, backend JWT', href: '/docs#sdk-overview' },
        { title: 'Bildirimler (FCM)', desc: 'FirebaseMessaging, ön/arka plan, deep linkler', href: '/docs#sdk-overview' },
        { title: 'Yayın ve CI', desc: 'GitHub Actions flutter-ci.yml, build, testler', href: '/docs#sdk-overview' },
      ],
    },
    {
      title: 'API Playground',
      iconKey: 'play',
      color: 'teal',
      items: [
        { title: 'Sandbox ortamı', desc: 'Demo Render URL, test hesapları, demo Bearer token', href: '/docs#api-quickstart' },
        { title: 'Uç noktaları keşfedin', desc: 'Etkileşimli Swagger / Redoc arayüzü', href: '/docs#api-quickstart' },
        { title: 'cURL örnekleri', desc: 'Tüm modüller için hazır çağrılar', href: '/docs#api-quickstart' },
        { title: 'Geliştirici tokenları', desc: 'Partner testleri için kapsamlı token oluşturun', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'Yönetim',
      iconKey: 'shield',
      color: 'red',
      items: [
        { title: 'Roller ve izinler', desc: 'Principal, İK, Çalışan, Süper Yönetici, RBAC', href: '/docs#api-quickstart' },
        { title: 'Çoklu kiracılık', desc: 'PostgreSQL şema mimarisi', href: '/docs#api-quickstart' },
        { title: 'Güvenlik ve KVKK', desc: 'Şifreleme, denetim izi, uyumluluk', href: '/docs#security' },
        { title: 'Dağıtım', desc: 'Docker, Render, Vercel, env değişkenleri', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'Entegrasyonlar',
      iconKey: 'plug',
      color: 'cyan',
      items: [
        { title: 'ZKTeco', desc: 'Biyometrik terminal yapılandırması', href: '/docs#kiosk' },
        { title: 'Takvim (CalDAV)', desc: 'Ajanda senkronizasyonu', href: '/docs#api-quickstart' },
        { title: 'Banka dışa aktarımları', desc: 'SEPA, CCP, CSV', href: '/docs#api-quickstart' },
        { title: 'Partner API rehberi', desc: 'ISV ve partnerler için entegrasyon rehberi', href: '/docs#api-quickstart' },
      ],
    },
  ],
  ar: [
    {
      title: 'البدء السريع',
      iconKey: 'zap',
      color: 'emerald',
      items: [
        { title: 'مقدمة', desc: 'نظرة عامة على ليباردو RH — نظام تشغيل للشركات يعتمد الجوال أولاً', href: '/docs#intro' },
        { title: 'التسجيل وأول مستأجر', desc: 'أنشئ حسابًا واعدّ شركتك', href: '/docs#api-quickstart' },
        { title: 'دعوة فريقك', desc: 'أضف المدراء والموظفين', href: '/docs#api-quickstart' },
        { title: 'تسجيل الدخول من الكشك', desc: 'اعدّ جهاز ZKTeco', href: '/docs#kiosk' },
      ],
    },
    {
      title: 'مساحة المدير',
      iconKey: 'layout',
      color: 'blue',
      items: [
        { title: 'لوحة التحكم', desc: 'مؤشرات الأداء والتنبيهات والنشاط الأخير', href: '/docs#api-quickstart' },
        { title: 'إدارة الغياب', desc: 'الطلبات والموافقات والأرصدة', href: '/docs#webhooks-overview' },
        { title: 'الرواتب وكشوفها', desc: 'شغّل الرواتب وأنشئ كشوف PDF', href: '/docs#webhooks-overview' },
        { title: 'العقود والمستندات', desc: 'إدارة آمنة للمستندات', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'التطبيقات الجوالة',
      iconKey: 'smartphone',
      color: 'violet',
      items: [
        { title: 'Leopardo Employee', desc: 'تسجيل الحضور والطلبات وكشف الراتب والإشعارات', href: '/docs#sdk-overview' },
        { title: 'Leopardo Manager', desc: 'الفريق والجداول والمهام والموافقات', href: '/docs#sdk-overview' },
        { title: 'Platform Admin', desc: 'المشرف العام: إنشاء المستأجرين والإشراف', href: '/docs#sdk-overview' },
        { title: 'تثبيت التطبيقات', desc: 'أندرويد / آيفون، التوزيع والإصدارات', href: '/docs#mobile-install' },
        { title: 'الإشعارات الفورية (FCM)', desc: 'إعداد Firebase Cloud Messaging', href: '/docs#sdk-overview' },
      ],
    },
    {
      title: 'مرجع REST API',
      iconKey: 'code',
      color: 'amber',
      items: [
        { title: 'المصادقة', desc: 'Bearer token و /auth/login و /auth/me و Google OAuth', href: '/docs#api-quickstart' },
        { title: 'الموظفون والموارد البشرية', desc: 'إدارة الموظفين والغياب والحضور والرواتب', href: '/docs#api-quickstart' },
        { title: 'Platform Admin', desc: 'المستأجرون وإنشاء الشركات والمشرف العام', href: '/docs#api-quickstart' },
        { title: 'الأخطاء والترقيم', desc: 'رموز الأخطاء القياسية وتحديد المعدل والمؤشر', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'Webhooks والأحداث',
      iconKey: 'webhook',
      color: 'pink',
      items: [
        { title: 'مقدمة عن Webhooks', desc: 'توقيع HMAC-SHA256 وإعادة المحاولة والاستدامة', href: '/docs#webhooks-overview' },
        { title: 'الأحداث المتاحة', desc: 'attendance.*, leave.*, salary_advance.*, payroll.*', href: '/docs#webhooks-events' },
        { title: 'الأمان والتحقق', desc: 'تحقق من توقيع X-Leopardo-Signature', href: '/docs#webhooks-security' },
        { title: 'الاختبار محليًا', desc: 'ngrok و cli-test وإعادة الأحداث', href: '/docs#webhooks-overview' },
      ],
    },
    {
      title: 'SDK الجوال',
      iconKey: 'package',
      color: 'orange',
      items: [
        { title: 'leopardo_core (Flutter)', desc: 'حزمة مشتركة — ApiClient و SecureStorage والنماذج', href: '/docs#sdk-overview' },
        { title: 'Auth و Google Sign-In', desc: 'GoogleSignIn v7+ initialize() و idToken و JWT', href: '/docs#sdk-overview' },
        { title: 'الإشعارات (FCM)', desc: 'FirebaseMessaging والخلفية والروابط العميقة', href: '/docs#sdk-overview' },
        { title: 'النشر و CI', desc: 'GitHub Actions flutter-ci.yml والبناء والاختبارات', href: '/docs#sdk-overview' },
      ],
    },
    {
      title: 'تجربة API',
      iconKey: 'play',
      color: 'teal',
      items: [
        { title: 'بيئة الاختبار', desc: 'رابط Render التجريبي وحسابات الاختبار ورمز تجريبي', href: '/docs#api-quickstart' },
        { title: 'استكشاف النقاط', desc: 'واجهة Swagger / Redoc تفاعلية', href: '/docs#api-quickstart' },
        { title: 'أمثلة cURL', desc: 'مجموعة استدعاءات جاهزة لكل الوحدات', href: '/docs#api-quickstart' },
        { title: 'رموز المطور', desc: 'أنشئ رمزًا محدد الصلاحيات لاختبارات الشركاء', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'الإدارة',
      iconKey: 'shield',
      color: 'red',
      items: [
        { title: 'الأدوار والصلاحيات', desc: 'Principal و HR و موظف و Super Admin و RBAC', href: '/docs#api-quickstart' },
        { title: 'تعدد المستأجرين', desc: 'بنية مخطط PostgreSQL', href: '/docs#api-quickstart' },
        { title: 'الأمان و GDPR', desc: 'التشفير وسجل التدقيق والامتثال', href: '/docs#security' },
        { title: 'النشر', desc: 'Docker و Render و Vercel ومتغيرات البيئة', href: '/docs#api-quickstart' },
      ],
    },
    {
      title: 'التكاملات',
      iconKey: 'plug',
      color: 'cyan',
      items: [
        { title: 'ZKTeco', desc: 'إعداد الأجهزة البيومترية', href: '/docs#kiosk' },
        { title: 'التقويم (CalDAV)', desc: 'مزامنة المواعيد', href: '/docs#api-quickstart' },
        { title: 'التصدير البنكي', desc: 'SEPA و CCP و CSV', href: '/docs#api-quickstart' },
        { title: 'دليل شركاء API', desc: 'دليل التكامل لمزودي البرمجيات والشركاء', href: '/docs#api-quickstart' },
      ],
    },
  ],
}

export const docsPageCopy: Record<AppLocale, DocsCopy> = {
  fr: {
    hero: {
      badge: 'Documentation développeurs',
      headlineTop: 'Tout savoir sur',
      headlineHighlight: 'Leopardo RH',
      subheadline: 'Guides, références API, webhooks et SDK pour intégrer Leopardo RH à vos outils.',
      searchPlaceholder: 'Rechercher dans la documentation…',
      tags: ['API REST', 'Webhooks', 'SDK Flutter', 'Playground', 'Authentification', 'Multi-tenant'],
    },
    apiSection: { title: 'API Quick Start', subtitle: 'Exemples prets a copier-coller', copyLabel: 'Copier', copiedLabel: 'Copié!' },
    webhooksSection: {
      title: 'Webhooks en temps reel',
      subtitle: 'Recevez les événements RH directement dans vos systèmes',
      eventGroups: [
        { group: 'Pointage', events: ['attendance.checked_in', 'attendance.checked_out', 'attendance.auto_closed'] },
        { group: 'Absences', events: ['leave.requested', 'leave.approved', 'leave.rejected'] },
        { group: 'Paie & avances', events: ['salary_advance.requested', 'salary_advance.paid', 'payroll.run_completed'] },
        { group: 'Notifications', events: ['notification.sent', 'notification.failed'] },
      ],
      securityNote: 'Chaque payload est signe avec',
      docLink: 'Voir la doc →',
    },
    sdkSection: {
      title: 'SDK Mobiles Flutter',
      subtitle: 'leopardo_core — le package partage entre les 3 apps',
      apps: [
        { name: 'leopardo_employee', desc: "App employé : pointage, bulletin, demandes d'absence, notifications" },
        { name: 'leopardo_manager', desc: 'App manager : équipe, horaires, taches, validation avances, paie' },
        { name: 'leopardo_platform_admin', desc: 'Super-admin : creation tenants, provisioning, 2FA, monitoring' },
      ],
      learnMore: 'En savoir plus',
    },
    kioskSection: {
      title: 'Pointage depuis le kiosque (ZKTeco)',
      subtitle: "Borne d'entree biometrie/QR + bridge desktop local offline-first",
      installTitle: 'Installation',
      installSteps: [
        'Copier config.example.json en config.json',
        'Renseigner apiBaseUrl, deviceCode et kioskToken (generes depuis l\'app manager)',
        'Lancer python desktop-bridge/bridge.py sur le PC/mini-PC local',
        'Ouvrir la borne sur http://127.0.0.1:8037/index.html',
      ],
      howItWorksTitle: 'Fonctionnement',
      howItWorksItems: [
        'Empreinte, visage ou QR/matricule en fallback clavier HID',
        'Mode hors-ligne : file locale SQLite, synchronisation automatique au retour reseau',
        'Admin local (admin.html) pour forcer une synchronisation manuelle',
        'Le matching biometrique brut reste gere par le terminal/SDK ZKTeco',
      ],
      sourceNote: 'Code source complet du bridge et de l\'UI kiosque :',
      downloadLink: 'Voir la page téléchargement →',
    },
    securitySection: {
      title: 'Sécurité & RGPD',
      subtitle: 'Chiffrement, isolation multi-tenant et conformité réglementaire',
      items: [
        { title: 'Chiffrement en transit', desc: 'Toutes les communications passent par TLS 1.3. Aucun échange en clair entre les clients, l\'API et les bornes.' },
        { title: 'Chiffrement au repos', desc: 'Les données sensibles sont chiffrées en AES-256. Les données biométriques restent sur le terminal, seuls des hash transitent.' },
        { title: 'Isolation multi-tenant', desc: 'Un schéma PostgreSQL isolé par entreprise. Les accès sont contrôlés par RBAC (Principal, RH, Employé, Super Admin).' },
        { title: 'Conformité RGPD', desc: 'Hébergement européen, audit trail complet, exports et suppression des données personnelles conformes au RGPD.' },
      ],
    },
    mobileInstallSection: {
      title: 'Installer les applications mobiles',
      subtitle: 'Employee, Manager et Platform Admin — iOS & Android',
      apps: [
        { name: 'Leopardo Employee', desc: "Pointage mobile, géolocalisation, bulletins, demandes d'absence et notifications push.", href: '/signup?source=download_employee_android' },
        { name: 'Leopardo Manager', desc: 'Équipe, horaires, tâches, approbations des demandes et paie simplifiée depuis le terrain.', href: '/signup?source=download_manager_android' },
        { name: 'Leopardo Platform Admin', desc: 'Création de tenants, supervision des entreprises clientes et provisioning 2FA.', href: '/signup?source=download_platform-admin_android' },
      ],
      storesNote: 'Google Play et App Store :',
      soonLabel: 'bientôt disponibles',
      testerCta: 'Rejoindre les testeurs',
      detailsNote: 'Plus de détails sur les trois apps :',
      mobilePageLink: 'page Applications mobiles →',
    },
    categoriesSection: { emptyTitle: 'Aucun resultat pour « {query} »', emptyCta: 'Effacer la recherche' },
    quickLinks: {
      title: 'Liens rapides',
      items: [
        { icon: 'terminal', label: 'API Explorer', desc: 'Tester les endpoints en direct', href: '/integrations#api' },
        { icon: 'key', label: "Guide d'authentification", desc: 'Bearer tokens, Google OAuth, scopes', href: '/docs#api-quickstart' },
        { icon: 'server', label: 'Guide de déploiement', desc: 'Docker, Render, Vercel', href: '/docs#api-quickstart' },
      ],
    },
  },
  en: {
    hero: {
      badge: 'Developer documentation',
      headlineTop: 'Everything about',
      headlineHighlight: 'Leopardo RH',
      subheadline: 'Guides, API reference, webhooks and SDKs to integrate Leopardo RH into your tools.',
      searchPlaceholder: 'Search the documentation…',
      tags: ['REST API', 'Webhooks', 'Flutter SDK', 'Playground', 'Authentication', 'Multi-tenancy'],
    },
    apiSection: { title: 'API Quick Start', subtitle: 'Ready-to-copy examples', copyLabel: 'Copy', copiedLabel: 'Copied!' },
    webhooksSection: {
      title: 'Real-time webhooks',
      subtitle: 'Receive HR events directly in your systems',
      eventGroups: [
        { group: 'Check-in', events: ['attendance.checked_in', 'attendance.checked_out', 'attendance.auto_closed'] },
        { group: 'Absences', events: ['leave.requested', 'leave.approved', 'leave.rejected'] },
        { group: 'Payroll & advances', events: ['salary_advance.requested', 'salary_advance.paid', 'payroll.run_completed'] },
        { group: 'Notifications', events: ['notification.sent', 'notification.failed'] },
      ],
      securityNote: 'Every payload is signed with',
      docLink: 'Read the docs →',
    },
    sdkSection: {
      title: 'Flutter Mobile SDKs',
      subtitle: 'leopardo_core — the shared package across the 3 apps',
      apps: [
        { name: 'leopardo_employee', desc: 'Employee app: check-in, payslip, leave requests, notifications' },
        { name: 'leopardo_manager', desc: 'Manager app: team, schedules, tasks, advance approvals, payroll' },
        { name: 'leopardo_platform_admin', desc: 'Super-admin: tenant creation, provisioning, 2FA, monitoring' },
      ],
      learnMore: 'Learn more',
    },
    kioskSection: {
      title: 'Kiosk check-in (ZKTeco)',
      subtitle: 'Biometric/QR entry terminal + local offline-first desktop bridge',
      installTitle: 'Installation',
      installSteps: [
        'Copy config.example.json to config.json',
        'Fill in apiBaseUrl, deviceCode and kioskToken (generated from the manager app)',
        'Run python desktop-bridge/bridge.py on the local PC/mini-PC',
        'Open the terminal at http://127.0.0.1:8037/index.html',
      ],
      howItWorksTitle: 'How it works',
      howItWorksItems: [
        'Fingerprint, face or QR/matricule with HID keyboard fallback',
        'Offline mode: local SQLite queue, automatic sync when the network returns',
        'Local admin (admin.html) to force a manual synchronisation',
        'Raw biometric matching stays on the ZKTeco terminal/SDK',
      ],
      sourceNote: 'Full source of the bridge and kiosk UI:',
      downloadLink: 'See the download page →',
    },
    securitySection: {
      title: 'Security & GDPR',
      subtitle: 'Encryption, multi-tenant isolation and regulatory compliance',
      items: [
        { title: 'Encryption in transit', desc: 'All communications go through TLS 1.3. No plain-text exchange between clients, the API and terminals.' },
        { title: 'Encryption at rest', desc: 'Sensitive data is AES-256 encrypted. Biometric data stays on the terminal; only hashes travel.' },
        { title: 'Multi-tenant isolation', desc: 'One isolated PostgreSQL schema per company. Access is controlled by RBAC (Principal, HR, Employee, Super Admin).' },
        { title: 'GDPR compliance', desc: 'European hosting, full audit trail, GDPR-compliant exports and deletion of personal data.' },
      ],
    },
    mobileInstallSection: {
      title: 'Install the mobile apps',
      subtitle: 'Employee, Manager and Platform Admin — iOS & Android',
      apps: [
        { name: 'Leopardo Employee', desc: 'Mobile check-in, geolocation, payslips, leave requests and push notifications.', href: '/signup?source=download_employee_android' },
        { name: 'Leopardo Manager', desc: 'Team, schedules, tasks, request approvals and simplified payroll from the field.', href: '/signup?source=download_manager_android' },
        { name: 'Leopardo Platform Admin', desc: 'Tenant creation, supervision of client companies and 2FA provisioning.', href: '/signup?source=download_platform-admin_android' },
      ],
      storesNote: 'Google Play and App Store:',
      soonLabel: 'coming soon',
      testerCta: 'Join the testers',
      detailsNote: 'More details about the three apps:',
      mobilePageLink: 'mobile apps page →',
    },
    categoriesSection: { emptyTitle: 'No results for « {query} »', emptyCta: 'Clear search' },
    quickLinks: {
      title: 'Quick links',
      items: [
        { icon: 'terminal', label: 'API Explorer', desc: 'Test endpoints live', href: '/integrations#api' },
        { icon: 'key', label: 'Authentication guide', desc: 'Bearer tokens, Google OAuth, scopes', href: '/docs#api-quickstart' },
        { icon: 'server', label: 'Deployment guide', desc: 'Docker, Render, Vercel', href: '/docs#api-quickstart' },
      ],
    },
  },
  tr: {
    hero: {
      badge: 'Geliştirici dokümantasyonu',
      headlineTop: 'Her şey',
      headlineHighlight: 'Leopardo RH',
      subheadline: 'Leopardo RH\'yi araçlarınıza entegre etmek için rehberler, API referansı, webhooklar ve SDK\'lar.',
      searchPlaceholder: 'Dokümantasyonda ara…',
      tags: ['REST API', 'Webhooks', 'Flutter SDK', 'Playground', 'Kimlik doğrulama', 'Çoklu kiracılık'],
    },
    apiSection: { title: 'API Hızlı Başlangıç', subtitle: 'Kopyalamaya hazır örnekler', copyLabel: 'Kopyala', copiedLabel: 'Kopyalandı!' },
    webhooksSection: {
      title: 'Gerçek zamanlı webhooklar',
      subtitle: 'İK olaylarını doğrudan sistemlerinizde alın',
      eventGroups: [
        { group: 'Giriş', events: ['attendance.checked_in', 'attendance.checked_out', 'attendance.auto_closed'] },
        { group: 'İzinler', events: ['leave.requested', 'leave.approved', 'leave.rejected'] },
        { group: 'Maaş ve avanslar', events: ['salary_advance.requested', 'salary_advance.paid', 'payroll.run_completed'] },
        { group: 'Bildirimler', events: ['notification.sent', 'notification.failed'] },
      ],
      securityNote: 'Her yük şununla imzalanır:',
      docLink: 'Dokümanları okuyun →',
    },
    sdkSection: {
      title: 'Flutter Mobil SDK',
      subtitle: 'leopardo_core — 3 uygulama arasında paylaşılan paket',
      apps: [
        { name: 'leopardo_employee', desc: 'Çalışan uygulaması: giriş, bordro, izin talepleri, bildirimler' },
        { name: 'leopardo_manager', desc: 'Yönetici uygulaması: ekip, programlar, görevler, avans onayları, maaş' },
        { name: 'leopardo_platform_admin', desc: 'Süper yönetici: kiracı oluşturma, sağlama, 2FA, izleme' },
      ],
      learnMore: 'Daha fazla bilgi',
    },
    kioskSection: {
      title: 'Kiosk girişi (ZKTeco)',
      subtitle: 'Biyometrik/QR giriş terminali + yerel çevrimdışı masaüstü köprüsü',
      installTitle: 'Kurulum',
      installSteps: [
        'config.example.json dosyasını config.json olarak kopyalayın',
        'apiBaseUrl, deviceCode ve kioskToken alanlarını doldurun (yönetici uygulamasından üretilir)',
        'Yerel PC/mini-PC üzerinde python desktop-bridge/bridge.py çalıştırın',
        'Terminali http://127.0.0.1:8037/index.html adresinde açın',
      ],
      howItWorksTitle: 'Nasıl çalışır',
      howItWorksItems: [
        'Parmak izi, yüz veya HID klavye yedeğiyle QR/matrikül',
        'Çevrimdışı mod: yerel SQLite kuyruğu, ağ dönünce otomatik senkronizasyon',
        'Manuel senkronizasyon zorlamak için yerel yönetici (admin.html)',
        'Ham biyometrik eşleştirme ZKTeco terminal/SDK tarafında kalır',
      ],
      sourceNote: 'Köprü ve kiosk arayüzünün tüm kaynak kodu:',
      downloadLink: 'İndirme sayfasına bakın →',
    },
    securitySection: {
      title: 'Güvenlik ve KVKK',
      subtitle: 'Şifreleme, çoklu kiracı izolasyonu ve yasal uyum',
      items: [
        { title: 'Aktarım sırasında şifreleme', desc: 'Tüm iletişim TLS 1.3 üzerinden geçer. İstemciler, API ve terminaller arasında açık metin alışverişi yoktur.' },
        { title: 'Bekleme sırasında şifreleme', desc: 'Hassas veriler AES-256 ile şifrelenir. Biyometrik veriler terminalde kalır; yalnızca özetler taşınır.' },
        { title: 'Çoklu kiracı izolasyonu', desc: 'Her şirket için izole bir PostgreSQL şeması. Erişim RBAC ile kontrol edilir (Principal, İK, Çalışan, Süper Yönetici).' },
        { title: 'KVKK uyumu', desc: 'Avrupa barındırma, eksiksiz denetim izi, KVKK uyumlu dışa aktarım ve kişisel veri silme.' },
      ],
    },
    mobileInstallSection: {
      title: 'Mobil uygulamaları kurun',
      subtitle: 'Employee, Manager ve Platform Admin — iOS ve Android',
      apps: [
        { name: 'Leopardo Employee', desc: 'Mobil giriş, konum, bordrolar, izin talepleri ve push bildirimleri.', href: '/signup?source=download_employee_android' },
        { name: 'Leopardo Manager', desc: 'Ekip, programlar, görevler, talep onayları ve sahada basitleştirilmiş maaş.', href: '/signup?source=download_manager_android' },
        { name: 'Leopardo Platform Admin', desc: 'Kiracı oluşturma, müşteri şirketlerin denetimi ve 2FA sağlama.', href: '/signup?source=download_platform-admin_android' },
      ],
      storesNote: 'Google Play ve App Store:',
      soonLabel: 'yakında',
      testerCta: 'Test kullanıcılarına katılın',
      detailsNote: 'Üç uygulama hakkında daha fazla ayrıntı:',
      mobilePageLink: 'mobil uygulamalar sayfası →',
    },
    categoriesSection: { emptyTitle: '« {query} » için sonuç yok', emptyCta: 'Aramayı temizle' },
    quickLinks: {
      title: 'Hızlı bağlantılar',
      items: [
        { icon: 'terminal', label: 'API Explorer', desc: 'Uç noktaları canlı test edin', href: '/integrations#api' },
        { icon: 'key', label: 'Kimlik doğrulama rehberi', desc: 'Bearer tokenlar, Google OAuth, kapsamlar', href: '/docs#api-quickstart' },
        { icon: 'server', label: 'Dağıtım rehberi', desc: 'Docker, Render, Vercel', href: '/docs#api-quickstart' },
      ],
    },
  },
  ar: {
    hero: {
      badge: 'توثيق المطورين',
      headlineTop: 'كل ما تريد معرفته عن',
      headlineHighlight: 'ليباردو RH',
      subheadline: 'أدلة ومرجع API وWebhooks وSDK لدمج ليباردو RH في أدواتك.',
      searchPlaceholder: 'ابحث في التوثيق…',
      tags: ['REST API', 'Webhooks', 'Flutter SDK', 'تجربة API', 'المصادقة', 'تعدد المستأجرين'],
    },
    apiSection: { title: 'بدء سريع لـ API', subtitle: 'أمثلة جاهزة للنسخ', copyLabel: 'نسخ', copiedLabel: 'تم النسخ!' },
    webhooksSection: {
      title: 'Webhooks فورية',
      subtitle: 'استقبل أحداث الموارد البشرية مباشرة في أنظمتك',
      eventGroups: [
        { group: 'تسجيل الحضور', events: ['attendance.checked_in', 'attendance.checked_out', 'attendance.auto_closed'] },
        { group: 'الغياب', events: ['leave.requested', 'leave.approved', 'leave.rejected'] },
        { group: 'الرواتب والسلف', events: ['salary_advance.requested', 'salary_advance.paid', 'payroll.run_completed'] },
        { group: 'الإشعارات', events: ['notification.sent', 'notification.failed'] },
      ],
      securityNote: 'كل حمولة موقّعة بـ',
      docLink: 'اقرأ التوثيق ←',
    },
    sdkSection: {
      title: 'SDK الجوال Flutter',
      subtitle: 'leopardo_core — الحزمة المشتركة بين التطبيقات الثلاثة',
      apps: [
        { name: 'leopardo_employee', desc: 'تطبيق الموظف: الحضور وكشف الراتب وطلبات الغياب والإشعارات' },
        { name: 'leopardo_manager', desc: 'تطبيق المدير: الفريق والجداول والمهام وموافقات السلف والرواتب' },
        { name: 'leopardo_platform_admin', desc: 'المشرف العام: إنشاء المستأجرين والتجهيز و2FA والمراقبة' },
      ],
      learnMore: 'اعرف المزيد',
    },
    kioskSection: {
      title: 'تسجيل الدخول من الكشك (ZKTeco)',
      subtitle: 'جهاز دخول بصمة/QR + جسر سطح مكتب محلي يعمل دون اتصال',
      installTitle: 'التثبيت',
      installSteps: [
        'انسخ config.example.json إلى config.json',
        'املأ apiBaseUrl و deviceCode و kioskToken (تُنشأ من تطبيق المدير)',
        'شغّل python desktop-bridge/bridge.py على الكمبيوتر المحلي',
        'افتح الجهاز على http://127.0.0.1:8037/index.html',
      ],
      howItWorksTitle: 'كيف يعمل',
      howItWorksItems: [
        'بصمة أو وجه أو QR/رقم الموظف مع لوحة مفاتيح HID احتياطية',
        'وضع عدم الاتصال: قائمة SQLite محلية ومزامنة تلقائية عند عودة الشبكة',
        'إدارة محلية (admin.html) لفرض مزامنة يدوية',
        'مطابقة البصمة الخام تبقى على جهاز/SDK ZKTeco',
      ],
      sourceNote: 'الكود المصدري الكامل للجسر وواجهة الكشك:',
      downloadLink: 'شاهد صفحة التنزيل ←',
    },
    securitySection: {
      title: 'الأمان و GDPR',
      subtitle: 'التشفير وعزل المستأجرين والامتثال التنظيمي',
      items: [
        { title: 'التشفير أثناء النقل', desc: 'جميع الاتصالات عبر TLS 1.3. لا تبادل نصي صريح بين العملاء وAPI والأجهزة.' },
        { title: 'التشفير عند التخزين', desc: 'البيانات الحساسة مشفرة بـ AES-256. البيانات البيومترية تبقى على الجهاز؛ لا تنتقل سوى التجزئات.' },
        { title: 'عزل المستأجرين', desc: 'مخطط PostgreSQL معزول لكل شركة. الوصول محكوم بـ RBAC (Principal و HR و موظف و Super Admin).' },
        { title: 'الامتثال لـ GDPR', desc: 'استضافة أوروبية وسجل تدقيق كامل وتصدير وحذف متوافق مع GDPR للبيانات الشخصية.' },
      ],
    },
    mobileInstallSection: {
      title: 'تثبيت التطبيقات الجوالة',
      subtitle: 'Employee و Manager و Platform Admin — iOS و Android',
      apps: [
        { name: 'Leopardo Employee', desc: 'حضور جوال وتحديد المواقع وكشوف رواتب وطلبات غياب وإشعارات فورية.', href: '/signup?source=download_employee_android' },
        { name: 'Leopardo Manager', desc: 'الفريق والجداول والمهام وموافقات الطلبات ورواتب مبسطة من الميدان.', href: '/signup?source=download_manager_android' },
        { name: 'Leopardo Platform Admin', desc: 'إنشاء المستأجرين والإشراف على الشركات العميلة وتجهيز 2FA.', href: '/signup?source=download_platform-admin_android' },
      ],
      storesNote: 'Google Play و App Store:',
      soonLabel: 'قريبًا',
      testerCta: 'انضم إلى المختبرين',
      detailsNote: 'مزيد من التفاصيل حول التطبيقات الثلاثة:',
      mobilePageLink: 'صفحة التطبيقات الجوالة ←',
    },
    categoriesSection: { emptyTitle: 'لا نتائج لـ « {query} »', emptyCta: 'مسح البحث' },
    quickLinks: {
      title: 'روابط سريعة',
      items: [
        { icon: 'terminal', label: 'API Explorer', desc: 'اختبر النقاط مباشرة', href: '/integrations#api' },
        { icon: 'key', label: 'دليل المصادقة', desc: 'Bearer tokens و Google OAuth والنطاقات', href: '/docs#api-quickstart' },
        { icon: 'server', label: 'دليل النشر', desc: 'Docker و Render و Vercel', href: '/docs#api-quickstart' },
      ],
    },
  },
}
