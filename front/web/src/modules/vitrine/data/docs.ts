import type { AppLocale } from '@/lib/i18n'

// Contenu de la page /docs par locale (issue #4215 — la page était 100 % FR
// codé en dur). Les icônes/couleurs restent dans la page ; ce fichier ne porte
// que les chaînes (pattern #3248 tranche guides/videos).

export type DocsCategoryId =
  | 'quickstart'
  | 'manager'
  | 'mobile'
  | 'api'
  | 'webhooks'
  | 'sdk'
  | 'playground'
  | 'admin'
  | 'integrations'

export type DocsCategoryItem = {
  title: string
  desc: string
  href: string
}

export type DocsCategory = {
  id: DocsCategoryId
  title: string
  items: DocsCategoryItem[]
}

export type DocsContent = {
  hero: {
    badge: string
    headline: string
    highlight: string
    subheadline: string
    searchPlaceholder: string
    quickPills: string[]
  }
  categories: DocsCategory[]
  apiQuickStart: {
    title: string
    subtitle: string
    copy: string
    copied: string
  }
  webhooks: {
    title: string
    subtitle: string
    groups: { group: string; events: string[] }[]
    signatureNote: string
    seeDoc: string
  }
  sdk: {
    title: string
    subtitle: string
    apps: { name: string; desc: string }[]
    learnMore: string
  }
  kiosk: {
    title: string
    subtitle: string
    installTitle: string
    installSteps: string[]
    howTitle: string
    howItems: string[]
    sourceNote: string
    seeDownload: string
  }
  security: {
    title: string
    subtitle: string
    items: { title: string; desc: string }[]
  }
  mobileInstall: {
    title: string
    subtitle: string
    apps: { name: string; desc: string; href: string }[]
    storeNote: string
    soon: string
    joinTesters: string
    moreDetails: string
    mobilePage: string
  }
  search: {
    noResults: string
    clear: string
  }
  quickLinks: {
    title: string
    links: { label: string; desc: string; href: string }[]
  }
}

export const docsPageCopy: Record<AppLocale, DocsContent> = {
  fr: {
    hero: {
      badge: 'Documentation — Developer Ecosystem',
      headline: 'Tout savoir sur',
      highlight: 'Leopardo RH',
      subheadline:
        'Guides, références API REST, webhooks, SDK mobiles, playground interactif et bonnes pratiques pour intégrer et étendre votre Mobile-First Company OS.',
      searchPlaceholder: 'Rechercher dans la documentation...',
      quickPills: ['API REST', 'Webhooks', 'SDK Flutter', 'Playground', 'Authentification', 'Multi-tenant'],
    },
    categories: [
      {
        id: 'quickstart',
        title: 'Démarrage rapide',
        items: [
          { title: 'Introduction', desc: "Vue d'ensemble de Leopardo RH — Mobile-First Company OS", href: '/docs#intro' },
          { title: 'Inscription & premier tenant', desc: 'Créer un compte et configurer votre entreprise', href: '/docs#api-quickstart' },
          { title: 'Inviter votre équipe', desc: 'Ajouter des managers et des employés', href: '/docs#api-quickstart' },
          { title: 'Pointage depuis le kiosque', desc: 'Configurer une borne ZKTeco', href: '/docs#kiosk' },
        ],
      },
      {
        id: 'manager',
        title: 'Espace Manager',
        items: [
          { title: 'Tableau de bord', desc: 'KPIs, alertes, activité récente', href: '/docs#api-quickstart' },
          { title: 'Gestion des absences', desc: 'Demandes, approbations, soldes', href: '/docs#webhooks-overview' },
          { title: 'Paie & bulletins', desc: 'Lancer une paie, générer les bulletins PDF', href: '/docs#webhooks-overview' },
          { title: 'Contrats & documents', desc: 'Gestion documentaire sécurisée', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'mobile',
        title: 'Applications Mobiles',
        items: [
          { title: 'Leopardo Employee', desc: 'Pointage, demandes, bulletin, notifications push', href: '/docs#sdk-overview' },
          { title: 'Leopardo Manager', desc: 'Équipe, horaires, tâches, approbations', href: '/docs#sdk-overview' },
          { title: 'Platform Admin', desc: 'Super-admin : création tenant, supervision', href: '/docs#sdk-overview' },
          { title: 'Installer les applications', desc: 'Android / iOS, distribution, versions', href: '/docs#mobile-install' },
          { title: 'Notifications push (FCM)', desc: 'Configurer Firebase Cloud Messaging', href: '/docs#sdk-overview' },
        ],
      },
      {
        id: 'api',
        title: 'API REST — Référence',
        items: [
          { title: 'Authentification', desc: 'Bearer token, /auth/login, /auth/me, Google OAuth', href: '/docs#api-quickstart' },
          { title: 'Employés & RH', desc: 'CRUD employés, absences, pointages, paie', href: '/docs#api-quickstart' },
          { title: 'Platform Admin', desc: 'Tenants, création entreprise, super-admin', href: '/docs#api-quickstart' },
          { title: 'Erreurs & pagination', desc: 'Codes erreur standards, throttling, curseur', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'webhooks',
        title: 'Webhooks & Events',
        items: [
          { title: 'Introduction aux webhooks', desc: 'Signature HMAC-SHA256, retry, idempotence', href: '/docs#webhooks-overview' },
          { title: 'Événements disponibles', desc: 'attendance.*, leave.*, salary_advance.*, payroll.*', href: '/docs#webhooks-events' },
          { title: 'Sécurité & vérification', desc: 'Valider la signature X-Leopardo-Signature', href: '/docs#webhooks-security' },
          { title: 'Tester en local', desc: "ngrok, cli-test, rejeu d'événements", href: '/docs#webhooks-overview' },
        ],
      },
      {
        id: 'sdk',
        title: 'SDK Mobiles',
        items: [
          { title: 'leopardo_core (Flutter)', desc: 'Package partagé — ApiClient, SecureStorage, modèles', href: '/docs#sdk-overview' },
          { title: 'Auth & Google Sign-In', desc: 'GoogleSignIn v7+ initialize(), idToken, backend JWT', href: '/docs#sdk-overview' },
          { title: 'Notifications (FCM)', desc: 'FirebaseMessaging, foreground/background, deep links', href: '/docs#sdk-overview' },
          { title: 'Publication & CI', desc: 'GitHub Actions flutter-ci.yml, build, tests', href: '/docs#sdk-overview' },
        ],
      },
      {
        id: 'playground',
        title: 'API Playground',
        items: [
          { title: 'Environnement sandbox', desc: 'URL démo Render, comptes de test, token Bearer démo', href: '/docs#api-quickstart' },
          { title: 'Explorer les endpoints', desc: 'Interface Swagger / Redoc interactive', href: '/docs#api-quickstart' },
          { title: 'Exemples cURL', desc: "Collection d'appels prêts à l'emploi pour tous les modules", href: '/docs#api-quickstart' },
          { title: 'Tokens développeur', desc: 'Créer un token scope-réduit pour tests partenaires', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'admin',
        title: 'Administration',
        items: [
          { title: 'Rôles & permissions', desc: 'Principal, RH, Employé, Super Admin, RBAC', href: '/docs#api-quickstart' },
          { title: 'Multi-tenant', desc: 'Architecture par schéma PostgreSQL', href: '/docs#api-quickstart' },
          { title: 'Sécurité & RGPD', desc: 'Chiffrement, audit trail, conformité', href: '/docs#security' },
          { title: 'Déploiement', desc: 'Docker, Render, Vercel, variables env', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'integrations',
        title: 'Intégrations',
        items: [
          { title: 'ZKTeco', desc: 'Configuration des bornes biométriques', href: '/docs#kiosk' },
          { title: 'Calendrier (CalDAV)', desc: 'Synchronisation agenda', href: '/docs#api-quickstart' },
          { title: 'Exports bancaires', desc: 'SEPA, CCP, CSV', href: '/docs#api-quickstart' },
          { title: 'Guide partenaire API', desc: "Guide d'intégration pour ISV et partenaires", href: '/docs#api-quickstart' },
        ],
      },
    ],
    apiQuickStart: {
      title: 'API Quick Start',
      subtitle: 'Exemples prêts à copier-coller',
      copy: 'Copier',
      copied: 'Copié !',
    },
    webhooks: {
      title: 'Webhooks en temps réel',
      subtitle: "Recevez les événements RH directement dans vos systèmes",
      groups: [
        { group: 'Pointage', events: ['attendance.checked_in', 'attendance.checked_out', 'attendance.auto_closed'] },
        { group: 'Absences', events: ['leave.requested', 'leave.approved', 'leave.rejected'] },
        { group: 'Paie & avances', events: ['salary_advance.requested', 'salary_advance.paid', 'payroll.run_completed'] },
        { group: 'Notifications', events: ['notification.sent', 'notification.failed'] },
      ],
      signatureNote:
        "Chaque payload est signé avec X-Leopardo-Signature (HMAC-SHA256).",
      seeDoc: 'Voir la doc →',
    },
    sdk: {
      title: 'SDK Mobiles Flutter',
      subtitle: 'leopardo_core — le package partagé entre les 3 apps',
      apps: [
        { name: 'leopardo_employee', desc: "App employé : pointage, bulletin, demandes d'absence, notifications" },
        { name: 'leopardo_manager', desc: 'App manager : équipe, horaires, tâches, validation avances, paie' },
        { name: 'leopardo_platform_admin', desc: 'Super-admin : création tenants, provisioning, 2FA, monitoring' },
      ],
      learnMore: 'En savoir plus',
    },
    kiosk: {
      title: 'Pointage depuis le kiosque (ZKTeco)',
      subtitle: "Borne d'entrée biométrie/QR + bridge desktop local offline-first",
      installTitle: 'Installation',
      installSteps: [
        'Copier config.example.json en config.json',
        'Renseigner apiBaseUrl, deviceCode et kioskToken (générés depuis l’app manager)',
        'Lancer python desktop-bridge/bridge.py sur le PC/mini-PC local',
        'Ouvrir la borne sur http://127.0.0.1:8037/index.html',
      ],
      howTitle: 'Fonctionnement',
      howItems: [
        'Empreinte, visage ou QR/matricule en fallback clavier HID',
        'Mode hors-ligne : file locale SQLite, synchronisation automatique au retour réseau',
        'Admin local (admin.html) pour forcer une synchronisation manuelle',
        'Le matching biométrique brut reste géré par le terminal/SDK ZKTeco',
      ],
      sourceNote: 'Code source complet du bridge et de l’UI kiosque :',
      seeDownload: 'Voir la page téléchargement →',
    },
    security: {
      title: 'Sécurité & RGPD',
      subtitle: 'Chiffrement, isolation multi-tenant et conformité réglementaire',
      items: [
        { title: 'Chiffrement en transit', desc: "Toutes les communications passent par TLS 1.3. Aucun échange en clair entre les clients, l'API et les bornes." },
        { title: 'Chiffrement au repos', desc: 'Les données sensibles sont chiffrées en AES-256. Les données biométriques restent sur le terminal, seuls des hash transitent.' },
        { title: 'Isolation multi-tenant', desc: 'Un schéma PostgreSQL isolé par entreprise. Les accès sont contrôlés par RBAC (Principal, RH, Employé, Super Admin).' },
        { title: 'Conformité RGPD', desc: 'Hébergement européen, audit trail complet, exports et suppression des données personnelles conformes au RGPD.' },
      ],
    },
    mobileInstall: {
      title: 'Installer les applications mobiles',
      subtitle: 'Employee, Manager et Platform Admin — iOS & Android',
      apps: [
        { name: 'Leopardo Employee', desc: "Pointage mobile, géolocalisation, bulletins, demandes d'absence et notifications push.", href: '/signup?source=download_employee_android' },
        { name: 'Leopardo Manager', desc: 'Équipe, horaires, tâches, approbations des demandes et paie simplifiée depuis le terrain.', href: '/signup?source=download_manager_android' },
        { name: 'Leopardo Platform Admin', desc: 'Création de tenants, supervision des entreprises clientes et provisioning 2FA.', href: '/signup?source=download_platform-admin_android' },
      ],
      storeNote: 'Google Play et App Store :',
      soon: 'bientôt disponibles',
      joinTesters: 'Rejoindre les testeurs',
      moreDetails: "Plus de détails sur les trois apps :",
      mobilePage: 'page Applications mobiles →',
    },
    search: {
      noResults: 'Aucun résultat pour « {query} »',
      clear: 'Effacer la recherche',
    },
    quickLinks: {
      title: 'Liens rapides',
      links: [
        { label: 'API Explorer', desc: 'Tester les endpoints en direct', href: '/integrations#api' },
        { label: "Guide d'authentification", desc: 'Bearer tokens, Google OAuth, scopes', href: '/docs#api-quickstart' },
        { label: 'Guide de déploiement', desc: 'Docker, Render, Vercel', href: '/docs#api-quickstart' },
      ],
    },
  },
  en: {
    hero: {
      badge: 'Documentation — Developer Ecosystem',
      headline: 'Everything about',
      highlight: 'Leopardo RH',
      subheadline:
        'Guides, REST API references, webhooks, mobile SDKs, interactive playground and best practices to integrate and extend your Mobile-First Company OS.',
      searchPlaceholder: 'Search the documentation...',
      quickPills: ['REST API', 'Webhooks', 'Flutter SDK', 'Playground', 'Authentication', 'Multi-tenant'],
    },
    categories: [
      {
        id: 'quickstart',
        title: 'Quick start',
        items: [
          { title: 'Introduction', desc: 'Overview of Leopardo RH — Mobile-First Company OS', href: '/docs#intro' },
          { title: 'Sign up & first tenant', desc: 'Create an account and set up your company', href: '/docs#api-quickstart' },
          { title: 'Invite your team', desc: 'Add managers and employees', href: '/docs#api-quickstart' },
          { title: 'Kiosk time tracking', desc: 'Set up a ZKTeco terminal', href: '/docs#kiosk' },
        ],
      },
      {
        id: 'manager',
        title: 'Manager space',
        items: [
          { title: 'Dashboard', desc: 'KPIs, alerts, recent activity', href: '/docs#api-quickstart' },
          { title: 'Leave management', desc: 'Requests, approvals, balances', href: '/docs#webhooks-overview' },
          { title: 'Payroll & payslips', desc: 'Run payroll, generate PDF payslips', href: '/docs#webhooks-overview' },
          { title: 'Contracts & documents', desc: 'Secure document management', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'mobile',
        title: 'Mobile apps',
        items: [
          { title: 'Leopardo Employee', desc: 'Time tracking, requests, payslip, push notifications', href: '/docs#sdk-overview' },
          { title: 'Leopardo Manager', desc: 'Team, schedules, tasks, approvals', href: '/docs#sdk-overview' },
          { title: 'Platform Admin', desc: 'Super-admin: tenant creation, supervision', href: '/docs#sdk-overview' },
          { title: 'Install the apps', desc: 'Android / iOS, distribution, versions', href: '/docs#mobile-install' },
          { title: 'Push notifications (FCM)', desc: 'Configure Firebase Cloud Messaging', href: '/docs#sdk-overview' },
        ],
      },
      {
        id: 'api',
        title: 'REST API — Reference',
        items: [
          { title: 'Authentication', desc: 'Bearer token, /auth/login, /auth/me, Google OAuth', href: '/docs#api-quickstart' },
          { title: 'Employees & HR', desc: 'Employee CRUD, absences, time tracking, payroll', href: '/docs#api-quickstart' },
          { title: 'Platform Admin', desc: 'Tenants, company creation, super-admin', href: '/docs#api-quickstart' },
          { title: 'Errors & pagination', desc: 'Standard error codes, throttling, cursor', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'webhooks',
        title: 'Webhooks & Events',
        items: [
          { title: 'Webhooks introduction', desc: 'HMAC-SHA256 signature, retry, idempotency', href: '/docs#webhooks-overview' },
          { title: 'Available events', desc: 'attendance.*, leave.*, salary_advance.*, payroll.*', href: '/docs#webhooks-events' },
          { title: 'Security & verification', desc: 'Validate the X-Leopardo-Signature header', href: '/docs#webhooks-security' },
          { title: 'Test locally', desc: 'ngrok, cli-test, event replay', href: '/docs#webhooks-overview' },
        ],
      },
      {
        id: 'sdk',
        title: 'Mobile SDKs',
        items: [
          { title: 'leopardo_core (Flutter)', desc: 'Shared package — ApiClient, SecureStorage, models', href: '/docs#sdk-overview' },
          { title: 'Auth & Google Sign-In', desc: 'GoogleSignIn v7+ initialize(), idToken, backend JWT', href: '/docs#sdk-overview' },
          { title: 'Notifications (FCM)', desc: 'FirebaseMessaging, foreground/background, deep links', href: '/docs#sdk-overview' },
          { title: 'Releases & CI', desc: 'GitHub Actions flutter-ci.yml, build, tests', href: '/docs#sdk-overview' },
        ],
      },
      {
        id: 'playground',
        title: 'API Playground',
        items: [
          { title: 'Sandbox environment', desc: 'Render demo URL, test accounts, demo Bearer token', href: '/docs#api-quickstart' },
          { title: 'Explore endpoints', desc: 'Interactive Swagger / Redoc interface', href: '/docs#api-quickstart' },
          { title: 'cURL examples', desc: 'Ready-to-use call collection for every module', href: '/docs#api-quickstart' },
          { title: 'Developer tokens', desc: 'Create a scope-restricted token for partner tests', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'admin',
        title: 'Administration',
        items: [
          { title: 'Roles & permissions', desc: 'Principal, HR, Employee, Super Admin, RBAC', href: '/docs#api-quickstart' },
          { title: 'Multi-tenant', desc: 'Per-schema PostgreSQL architecture', href: '/docs#api-quickstart' },
          { title: 'Security & GDPR', desc: 'Encryption, audit trail, compliance', href: '/docs#security' },
          { title: 'Deployment', desc: 'Docker, Render, Vercel, environment variables', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'integrations',
        title: 'Integrations',
        items: [
          { title: 'ZKTeco', desc: 'Biometric terminal configuration', href: '/docs#kiosk' },
          { title: 'Calendar (CalDAV)', desc: 'Agenda synchronization', href: '/docs#api-quickstart' },
          { title: 'Bank exports', desc: 'SEPA, CCP, CSV', href: '/docs#api-quickstart' },
          { title: 'API partner guide', desc: 'Integration guide for ISVs and partners', href: '/docs#api-quickstart' },
        ],
      },
    ],
    apiQuickStart: {
      title: 'API Quick Start',
      subtitle: 'Copy-paste ready examples',
      copy: 'Copy',
      copied: 'Copied!',
    },
    webhooks: {
      title: 'Real-time webhooks',
      subtitle: 'Receive HR events directly in your systems',
      groups: [
        { group: 'Time tracking', events: ['attendance.checked_in', 'attendance.checked_out', 'attendance.auto_closed'] },
        { group: 'Leave', events: ['leave.requested', 'leave.approved', 'leave.rejected'] },
        { group: 'Payroll & advances', events: ['salary_advance.requested', 'salary_advance.paid', 'payroll.run_completed'] },
        { group: 'Notifications', events: ['notification.sent', 'notification.failed'] },
      ],
      signatureNote: 'Every payload is signed with X-Leopardo-Signature (HMAC-SHA256).',
      seeDoc: 'Read the docs →',
    },
    sdk: {
      title: 'Flutter Mobile SDK',
      subtitle: 'leopardo_core — the package shared by the 3 apps',
      apps: [
        { name: 'leopardo_employee', desc: 'Employee app: time tracking, payslip, leave requests, notifications' },
        { name: 'leopardo_manager', desc: 'Manager app: team, schedules, tasks, advance approvals, payroll' },
        { name: 'leopardo_platform_admin', desc: 'Super-admin: tenant creation, provisioning, 2FA, monitoring' },
      ],
      learnMore: 'Learn more',
    },
    kiosk: {
      title: 'Kiosk time tracking (ZKTeco)',
      subtitle: 'Biometric/QR entry terminal + local offline-first desktop bridge',
      installTitle: 'Installation',
      installSteps: [
        'Copy config.example.json to config.json',
        'Fill in apiBaseUrl, deviceCode and kioskToken (generated from the manager app)',
        'Run python desktop-bridge/bridge.py on the local PC/mini-PC',
        'Open the terminal at http://127.0.0.1:8037/index.html',
      ],
      howTitle: 'How it works',
      howItems: [
        'Fingerprint, face or QR/employee-code with HID keyboard fallback',
        'Offline mode: local SQLite queue, automatic sync when the network returns',
        'Local admin (admin.html) to force a manual sync',
        'Raw biometric matching stays on the ZKTeco terminal/SDK',
      ],
      sourceNote: 'Full source code of the bridge and kiosk UI:',
      seeDownload: 'See the download page →',
    },
    security: {
      title: 'Security & GDPR',
      subtitle: 'Encryption, multi-tenant isolation and regulatory compliance',
      items: [
        { title: 'Encryption in transit', desc: 'All communications use TLS 1.3. No plaintext exchange between clients, the API and terminals.' },
        { title: 'Encryption at rest', desc: 'Sensitive data is encrypted with AES-256. Biometric data stays on the terminal; only hashes travel.' },
        { title: 'Multi-tenant isolation', desc: 'One isolated PostgreSQL schema per company. Access is controlled by RBAC (Principal, HR, Employee, Super Admin).' },
        { title: 'GDPR compliance', desc: 'European hosting, full audit trail, GDPR-compliant personal data export and deletion.' },
      ],
    },
    mobileInstall: {
      title: 'Install the mobile apps',
      subtitle: 'Employee, Manager and Platform Admin — iOS & Android',
      apps: [
        { name: 'Leopardo Employee', desc: 'Mobile time tracking, geolocation, payslips, leave requests and push notifications.', href: '/signup?source=download_employee_android' },
        { name: 'Leopardo Manager', desc: 'Team, schedules, tasks, request approvals and simplified payroll from the field.', href: '/signup?source=download_manager_android' },
        { name: 'Leopardo Platform Admin', desc: 'Tenant creation, customer company supervision and 2FA provisioning.', href: '/signup?source=download_platform-admin_android' },
      ],
      storeNote: 'Google Play and App Store:',
      soon: 'coming soon',
      joinTesters: 'Join the testers',
      moreDetails: 'More details about the three apps:',
      mobilePage: 'mobile apps page →',
    },
    search: {
      noResults: 'No results for “{query}”',
      clear: 'Clear search',
    },
    quickLinks: {
      title: 'Quick links',
      links: [
        { label: 'API Explorer', desc: 'Test endpoints live', href: '/integrations#api' },
        { label: 'Authentication guide', desc: 'Bearer tokens, Google OAuth, scopes', href: '/docs#api-quickstart' },
        { label: 'Deployment guide', desc: 'Docker, Render, Vercel', href: '/docs#api-quickstart' },
      ],
    },
  },
  tr: {
    hero: {
      badge: 'Dokümantasyon — Geliştirici Ekosistemi',
      headline: 'Hakkında her şey',
      highlight: 'Leopardo RH',
      subheadline:
        'Mobile-First Company OS’unuzu entegre etmek ve genişletmek için rehberler, REST API referansları, webhooks, mobil SDK’lar, etkileşimli playground ve en iyi uygulamalar.',
      searchPlaceholder: 'Dokümantasyonda ara...',
      quickPills: ['REST API', 'Webhooks', 'Flutter SDK', 'Playground', 'Kimlik doğrulama', 'Multi-tenant'],
    },
    categories: [
      {
        id: 'quickstart',
        title: 'Hızlı başlangıç',
        items: [
          { title: 'Giriş', desc: 'Leopardo RH genel bakış — Mobile-First Company OS', href: '/docs#intro' },
          { title: 'Kayıt ve ilk tenant', desc: 'Hesap oluşturun ve şirketinizi yapılandırın', href: '/docs#api-quickstart' },
          { title: 'Ekibinizi davet edin', desc: 'Yönetici ve çalışan ekleyin', href: '/docs#api-quickstart' },
          { title: 'Kiosk yoklama', desc: 'Bir ZKTeco terminali kurun', href: '/docs#kiosk' },
        ],
      },
      {
        id: 'manager',
        title: 'Yönetici alanı',
        items: [
          { title: 'Gösterge paneli', desc: 'KPI’lar, uyarılar, son aktivite', href: '/docs#api-quickstart' },
          { title: 'İzin yönetimi', desc: 'Talepler, onaylar, bakiyeler', href: '/docs#webhooks-overview' },
          { title: 'Bordro ve maaş fişleri', desc: 'Bordro çalıştırın, PDF fiş üretin', href: '/docs#webhooks-overview' },
          { title: 'Sözleşmeler ve belgeler', desc: 'Güvenli belge yönetimi', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'mobile',
        title: 'Mobil uygulamalar',
        items: [
          { title: 'Leopardo Employee', desc: 'Yoklama, talepler, maaş fişi, push bildirimleri', href: '/docs#sdk-overview' },
          { title: 'Leopardo Manager', desc: 'Ekip, çalışma saatleri, görevler, onaylar', href: '/docs#sdk-overview' },
          { title: 'Platform Admin', desc: 'Süper yönetici: tenant oluşturma, denetim', href: '/docs#sdk-overview' },
          { title: 'Uygulamaları kurun', desc: 'Android / iOS, dağıtım, sürümler', href: '/docs#mobile-install' },
          { title: 'Push bildirimleri (FCM)', desc: 'Firebase Cloud Messaging yapılandırın', href: '/docs#sdk-overview' },
        ],
      },
      {
        id: 'api',
        title: 'REST API — Referans',
        items: [
          { title: 'Kimlik doğrulama', desc: 'Bearer token, /auth/login, /auth/me, Google OAuth', href: '/docs#api-quickstart' },
          { title: 'Çalışanlar ve İK', desc: 'Çalışan CRUD, izinler, yoklama, bordro', href: '/docs#api-quickstart' },
          { title: 'Platform Admin', desc: 'Tenantlar, şirket oluşturma, süper yönetici', href: '/docs#api-quickstart' },
          { title: 'Hatalar ve sayfalama', desc: 'Standart hata kodları, throttling, imleç', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'webhooks',
        title: 'Webhooks ve Olaylar',
        items: [
          { title: 'Webhooks giriş', desc: 'HMAC-SHA256 imza, retry, idempotence', href: '/docs#webhooks-overview' },
          { title: 'Mevcut olaylar', desc: 'attendance.*, leave.*, salary_advance.*, payroll.*', href: '/docs#webhooks-events' },
          { title: 'Güvenlik ve doğrulama', desc: 'X-Leopardo-Signature başlığını doğrulayın', href: '/docs#webhooks-security' },
          { title: 'Yerel test', desc: 'ngrok, cli-test, olay tekrarı', href: '/docs#webhooks-overview' },
        ],
      },
      {
        id: 'sdk',
        title: 'Mobil SDK’lar',
        items: [
          { title: 'leopardo_core (Flutter)', desc: 'Paylaşılan paket — ApiClient, SecureStorage, modeller', href: '/docs#sdk-overview' },
          { title: 'Auth ve Google Sign-In', desc: 'GoogleSignIn v7+ initialize(), idToken, backend JWT', href: '/docs#sdk-overview' },
          { title: 'Bildirimler (FCM)', desc: 'FirebaseMessaging, foreground/background, deep linkler', href: '/docs#sdk-overview' },
          { title: 'Sürümler ve CI', desc: 'GitHub Actions flutter-ci.yml, build, testler', href: '/docs#sdk-overview' },
        ],
      },
      {
        id: 'playground',
        title: 'API Playground',
        items: [
          { title: 'Sandbox ortamı', desc: 'Render demo URL, test hesapları, demo Bearer token', href: '/docs#api-quickstart' },
          { title: 'Uç noktaları keşfedin', desc: 'Etkileşimli Swagger / Redoc arayüzü', href: '/docs#api-quickstart' },
          { title: 'cURL örnekleri', desc: 'Her modül için hazır çağrı koleksiyonu', href: '/docs#api-quickstart' },
          { title: 'Geliştirici tokenları', desc: 'Partner testleri için kapsamı kısıtlı token oluşturun', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'admin',
        title: 'Yönetim',
        items: [
          { title: 'Roller ve yetkiler', desc: 'Principal, İK, Çalışan, Süper Yönetici, RBAC', href: '/docs#api-quickstart' },
          { title: 'Multi-tenant', desc: 'PostgreSQL şema tabanlı mimari', href: '/docs#api-quickstart' },
          { title: 'Güvenlik ve KVKK', desc: 'Şifreleme, denetim izi, uyumluluk', href: '/docs#security' },
          { title: 'Dağıtım', desc: 'Docker, Render, Vercel, ortam değişkenleri', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'integrations',
        title: 'Entegrasyonlar',
        items: [
          { title: 'ZKTeco', desc: 'Biyometrik terminal yapılandırması', href: '/docs#kiosk' },
          { title: 'Takvim (CalDAV)', desc: 'Ajanda senkronizasyonu', href: '/docs#api-quickstart' },
          { title: 'Banka dışa aktarımları', desc: 'SEPA, CCP, CSV', href: '/docs#api-quickstart' },
          { title: 'API partner rehberi', desc: 'ISV’ler ve partnerler için entegrasyon rehberi', href: '/docs#api-quickstart' },
        ],
      },
    ],
    apiQuickStart: {
      title: 'API Quick Start',
      subtitle: 'Kopyala-yapıştır için hazır örnekler',
      copy: 'Kopyala',
      copied: 'Kopyalandı!',
    },
    webhooks: {
      title: 'Gerçek zamanlı webhooks',
      subtitle: 'İK olaylarını doğrudan sistemlerinizde alın',
      groups: [
        { group: 'Yoklama', events: ['attendance.checked_in', 'attendance.checked_out', 'attendance.auto_closed'] },
        { group: 'İzinler', events: ['leave.requested', 'leave.approved', 'leave.rejected'] },
        { group: 'Bordro ve avanslar', events: ['salary_advance.requested', 'salary_advance.paid', 'payroll.run_completed'] },
        { group: 'Bildirimler', events: ['notification.sent', 'notification.failed'] },
      ],
      signatureNote: 'Her payload X-Leopardo-Signature (HMAC-SHA256) ile imzalanır.',
      seeDoc: 'Dokümanı okuyun →',
    },
    sdk: {
      title: 'Flutter Mobil SDK',
      subtitle: 'leopardo_core — 3 uygulamanın paylaştığı paket',
      apps: [
        { name: 'leopardo_employee', desc: 'Çalışan uygulaması: yoklama, maaş fişi, izin talepleri, bildirimler' },
        { name: 'leopardo_manager', desc: 'Yönetici uygulaması: ekip, çalışma saatleri, görevler, avans onayları, bordro' },
        { name: 'leopardo_platform_admin', desc: 'Süper yönetici: tenant oluşturma, provisioning, 2FA, izleme' },
      ],
      learnMore: 'Daha fazla bilgi',
    },
    kiosk: {
      title: 'Kiosk yoklama (ZKTeco)',
      subtitle: 'Biyometri/QR giriş terminali + yerel offline-first masaüstü bridge',
      installTitle: 'Kurulum',
      installSteps: [
        'config.example.json dosyasını config.json olarak kopyalayın',
        'apiBaseUrl, deviceCode ve kioskToken alanlarını doldurun (yönetici uygulamasından üretilir)',
        'Yerel PC/mini-PC üzerinde python desktop-bridge/bridge.py çalıştırın',
        'Terminali http://127.0.0.1:8037/index.html adresinde açın',
      ],
      howTitle: 'Nasıl çalışır',
      howItems: [
        'Parmak izi, yüz veya HID klavye yedeğiyle QR/çalışan kodu',
        'Çevrimdışı mod: yerel SQLite kuyruğu, ağ dönünce otomatik senkronizasyon',
        'Manuel senkronizasyon için yerel yönetici (admin.html)',
        'Ham biyometrik eşleştirme ZKTeco terminal/SDK tarafında kalır',
      ],
      sourceNote: 'Bridge ve kiosk arayüzünün tam kaynak kodu:',
      seeDownload: 'İndirme sayfasına bakın →',
    },
    security: {
      title: 'Güvenlik ve KVKK',
      subtitle: 'Şifreleme, multi-tenant izolasyon ve yasal uyumluluk',
      items: [
        { title: 'Aktarımda şifreleme', desc: 'Tüm iletişim TLS 1.3 kullanır. İstemciler, API ve terminaller arasında düz metin alışverişi yoktur.' },
        { title: 'Beklemede şifreleme', desc: 'Hassas veriler AES-256 ile şifrelenir. Biyometrik veriler terminalde kalır; yalnızca hash’ler taşınır.' },
        { title: 'Multi-tenant izolasyon', desc: 'Her şirket için izole bir PostgreSQL şeması. Erişim RBAC ile kontrol edilir (Principal, İK, Çalışan, Süper Yönetici).' },
        { title: 'KVKK uyumluluğu', desc: 'Avrupa barındırma, eksiksiz denetim izi, KVKK uyumlu kişisel veri dışa aktarma ve silme.' },
      ],
    },
    mobileInstall: {
      title: 'Mobil uygulamaları kurun',
      subtitle: 'Employee, Manager ve Platform Admin — iOS ve Android',
      apps: [
        { name: 'Leopardo Employee', desc: 'Mobil yoklama, konum, maaş fişleri, izin talepleri ve push bildirimleri.', href: '/signup?source=download_employee_android' },
        { name: 'Leopardo Manager', desc: 'Ekip, çalışma saatleri, görevler, talep onayları ve sahada basitleştirilmiş bordro.', href: '/signup?source=download_manager_android' },
        { name: 'Leopardo Platform Admin', desc: 'Tenant oluşturma, müşteri şirketlerini denetleme ve 2FA provisioning.', href: '/signup?source=download_platform-admin_android' },
      ],
      storeNote: 'Google Play ve App Store:',
      soon: 'yakında',
      joinTesters: 'Testçilere katılın',
      moreDetails: 'Üç uygulama hakkında daha fazla bilgi:',
      mobilePage: 'mobil uygulamalar sayfası →',
    },
    search: {
      noResults: '« {query} »',
      clear: 'Aramayı temizle',
    },
    quickLinks: {
      title: 'Hızlı bağlantılar',
      links: [
        { label: 'API Explorer', desc: 'Uç noktaları canlı test edin', href: '/integrations#api' },
        { label: 'Kimlik doğrulama rehberi', desc: 'Bearer tokenlar, Google OAuth, kapsamlar', href: '/docs#api-quickstart' },
        { label: 'Dağıtım rehberi', desc: 'Docker, Render, Vercel', href: '/docs#api-quickstart' },
      ],
    },
  },
  ar: {
    hero: {
      badge: 'التوثيق — النظام البيئي للمطورين',
      headline: 'كل ما تريد معرفته عن',
      highlight: 'Leopardo RH',
      subheadline:
        'أدلة، مراجع REST API، ويب هوكس، SDK للهواتف، بيئة تجريبية تفاعلية وأفضل الممارسات لدمج وتوسيع نظام تشغيل الشركات الخاص بك.',
      searchPlaceholder: 'ابحث في التوثيق...',
      quickPills: ['REST API', 'Webhooks', 'Flutter SDK', 'Playground', 'المصادقة', 'Multi-tenant'],
    },
    categories: [
      {
        id: 'quickstart',
        title: 'بداية سريعة',
        items: [
          { title: 'مقدمة', desc: 'نظرة عامة على Leopardo RH — نظام تشغيل الشركات', href: '/docs#intro' },
          { title: 'التسجيل وأول tenant', desc: 'أنشئ حسابًا واعدّ شركتك', href: '/docs#api-quickstart' },
          { title: 'دعوة فريقك', desc: 'أضف المديرين والموظفين', href: '/docs#api-quickstart' },
          { title: 'تسجيل الحضور عبر الكشك', desc: 'إعداد جهاز ZKTeco', href: '/docs#kiosk' },
        ],
      },
      {
        id: 'manager',
        title: 'مساحة المدير',
        items: [
          { title: 'لوحة القيادة', desc: 'مؤشرات الأداء، التنبيهات، النشاط الأخير', href: '/docs#api-quickstart' },
          { title: 'إدارة الإجازات', desc: 'الطلبات، الموافقات، الأرصدة', href: '/docs#webhooks-overview' },
          { title: 'الرواتب وكشوفها', desc: 'شغّل دفعة رواتب وأنشئ كشوف PDF', href: '/docs#webhooks-overview' },
          { title: 'العقود والمستندات', desc: 'إدارة آمنة للمستندات', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'mobile',
        title: 'تطبيقات الهاتف',
        items: [
          { title: 'Leopardo Employee', desc: 'تسجيل الحضور، الطلبات، كشف الراتب، إشعارات فورية', href: '/docs#sdk-overview' },
          { title: 'Leopardo Manager', desc: 'الفريق، الجداول، المهام، الموافقات', href: '/docs#sdk-overview' },
          { title: 'Platform Admin', desc: 'مدير عام: إنشاء tenants، إشراف', href: '/docs#sdk-overview' },
          { title: 'تثبيت التطبيقات', desc: 'Android / iOS، التوزيع، الإصدارات', href: '/docs#mobile-install' },
          { title: 'الإشعارات الفورية (FCM)', desc: 'إعداد Firebase Cloud Messaging', href: '/docs#sdk-overview' },
        ],
      },
      {
        id: 'api',
        title: 'REST API — المرجع',
        items: [
          { title: 'المصادقة', desc: 'Bearer token، /auth/login، /auth/me، Google OAuth', href: '/docs#api-quickstart' },
          { title: 'الموظفون والموارد البشرية', desc: 'إدارة الموظفين، الإجازات، الحضور، الرواتب', href: '/docs#api-quickstart' },
          { title: 'Platform Admin', desc: 'الـ tenants، إنشاء الشركات، المدير العام', href: '/docs#api-quickstart' },
          { title: 'الأخطاء والترقيم', desc: 'أكواد خطأ موحدة، تقييد، مؤشر', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'webhooks',
        title: 'Webhooks والأحداث',
        items: [
          { title: 'مقدمة إلى webhooks', desc: 'توقيع HMAC-SHA256، إعادة المحاولة، الثبات', href: '/docs#webhooks-overview' },
          { title: 'الأحداث المتاحة', desc: 'attendance.*, leave.*, salary_advance.*, payroll.*', href: '/docs#webhooks-events' },
          { title: 'الأمان والتحقق', desc: 'التحقق من ترويسة X-Leopardo-Signature', href: '/docs#webhooks-security' },
          { title: 'الاختبار محليًا', desc: 'ngrok، cli-test، إعادة تشغيل الأحداث', href: '/docs#webhooks-overview' },
        ],
      },
      {
        id: 'sdk',
        title: 'SDK للهواتف',
        items: [
          { title: 'leopardo_core (Flutter)', desc: 'حزمة مشتركة — ApiClient، SecureStorage، النماذج', href: '/docs#sdk-overview' },
          { title: 'المصادقة وGoogle Sign-In', desc: 'GoogleSignIn v7+ initialize()، idToken، JWT خلفي', href: '/docs#sdk-overview' },
          { title: 'الإشعارات (FCM)', desc: 'FirebaseMessaging، أمامي/خلفي، روابط عميقة', href: '/docs#sdk-overview' },
          { title: 'الإصدارات وCI', desc: 'GitHub Actions flutter-ci.yml، البناء، الاختبارات', href: '/docs#sdk-overview' },
        ],
      },
      {
        id: 'playground',
        title: 'API Playground',
        items: [
          { title: 'بيئة الاختبار', desc: 'رابط Render التجريبي، حسابات اختبار، رمز Bearer تجريبي', href: '/docs#api-quickstart' },
          { title: 'استكشاف النقاط', desc: 'واجهة Swagger / Redoc تفاعلية', href: '/docs#api-quickstart' },
          { title: 'أمثلة cURL', desc: 'مجموعة استدعاءات جاهزة لكل وحدة', href: '/docs#api-quickstart' },
          { title: 'رموز المطور', desc: 'إنشاء رمز محدود الصلاحيات لاختبارات الشركاء', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'admin',
        title: 'الإدارة',
        items: [
          { title: 'الأدوار والصلاحيات', desc: 'المدير الرئيسي، الموارد البشرية، الموظف، المدير العام، RBAC', href: '/docs#api-quickstart' },
          { title: 'Multi-tenant', desc: 'معمارية PostgreSQL حسب المخطط', href: '/docs#api-quickstart' },
          { title: 'الأمان والخصوصية', desc: 'التشفير، سجل التدقيق، الامتثال', href: '/docs#security' },
          { title: 'النشر', desc: 'Docker، Render، Vercel، متغيرات البيئة', href: '/docs#api-quickstart' },
        ],
      },
      {
        id: 'integrations',
        title: 'التكاملات',
        items: [
          { title: 'ZKTeco', desc: 'إعداد أجهزة البصمة', href: '/docs#kiosk' },
          { title: 'التقويم (CalDAV)', desc: 'مزامنة الأجندة', href: '/docs#api-quickstart' },
          { title: 'تصدير بنكي', desc: 'SEPA، CCP، CSV', href: '/docs#api-quickstart' },
          { title: 'دليل شريك API', desc: 'دليل التكامل لشركات البرمجيات والشركاء', href: '/docs#api-quickstart' },
        ],
      },
    ],
    apiQuickStart: {
      title: 'API Quick Start',
      subtitle: 'أمثلة جاهزة للنسخ واللصق',
      copy: 'نسخ',
      copied: 'تم النسخ!',
    },
    webhooks: {
      title: 'Webhooks في الوقت الفعلي',
      subtitle: 'استقبل أحداث الموارد البشرية مباشرة في أنظمتك',
      groups: [
        { group: 'الحضور', events: ['attendance.checked_in', 'attendance.checked_out', 'attendance.auto_closed'] },
        { group: 'الإجازات', events: ['leave.requested', 'leave.approved', 'leave.rejected'] },
        { group: 'الرواتب والسلف', events: ['salary_advance.requested', 'salary_advance.paid', 'payroll.run_completed'] },
        { group: 'الإشعارات', events: ['notification.sent', 'notification.failed'] },
      ],
      signatureNote: 'كل حمولة موقعة بـ X-Leopardo-Signature (HMAC-SHA256).',
      seeDoc: 'اقرأ التوثيق ←',
    },
    sdk: {
      title: 'Flutter SDK للهواتف',
      subtitle: 'leopardo_core — الحزمة المشتركة بين التطبيقات الثلاثة',
      apps: [
        { name: 'leopardo_employee', desc: 'تطبيق الموظف: الحضور، كشف الراتب، طلبات الإجازة، الإشعارات' },
        { name: 'leopardo_manager', desc: 'تطبيق المدير: الفريق، الجداول، المهام، موافقات السلف، الرواتب' },
        { name: 'leopardo_platform_admin', desc: 'مدير عام: إنشاء tenants، التجهيز، 2FA، المراقبة' },
      ],
      learnMore: 'اعرف المزيد',
    },
    kiosk: {
      title: 'تسجيل الحضور عبر الكشك (ZKTeco)',
      subtitle: 'جهاز بصمة/QR + جسر سطح مكتب محلي يعمل دون اتصال',
      installTitle: 'التثبيت',
      installSteps: [
        'انسخ config.example.json إلى config.json',
        'املأ apiBaseUrl وdeviceCode وkioskToken (تُنشأ من تطبيق المدير)',
        'شغّل python desktop-bridge/bridge.py على الكمبيوتر المحلي',
        'افتح الجهاز على http://127.0.0.1:8037/index.html',
      ],
      howTitle: 'طريقة العمل',
      howItems: [
        'بصمة أو وجه أو رمز QR/رقم الموظف مع بديل لوحة مفاتيح HID',
        'وضع عدم الاتصال: قائمة انتظار SQLite محلية ومزامنة تلقائية عند عودة الشبكة',
        'إدارة محلية (admin.html) لفرض مزامنة يدوية',
        'مطابقة البصمة الخام تبقى على جهاز/SDK ZKTeco',
      ],
      sourceNote: 'الكود المصدري الكامل للجسر وواجهة الكشك:',
      seeDownload: 'راجع صفحة التحميل ←',
    },
    security: {
      title: 'الأمان والخصوصية',
      subtitle: 'التشفير وعزل multi-tenant والامتثال التنظيمي',
      items: [
        { title: 'التشفير أثناء النقل', desc: 'جميع الاتصالات عبر TLS 1.3. لا تبادل نصي صريح بين العملاء وAPI والأجهزة.' },
        { title: 'التشفير عند التخزين', desc: 'البيانات الحساسة مشفرة بـ AES-256. تبقى البيانات البيومترية على الجهاز؛ تنتقل التجزئات فقط.' },
        { title: 'عزل multi-tenant', desc: 'مخطط PostgreSQL معزول لكل شركة. الوصول محكوم بـ RBAC (مدير رئيسي، موارد بشرية، موظف، مدير عام).' },
        { title: 'الامتثال للخصوصية', desc: 'استضافة أوروبية، سجل تدقيق كامل، تصدير وحذف البيانات الشخصية بما يتوافق مع اللائحة العامة.' },
      ],
    },
    mobileInstall: {
      title: 'تثبيت تطبيقات الهاتف',
      subtitle: 'Employee وManager وPlatform Admin — iOS وAndroid',
      apps: [
        { name: 'Leopardo Employee', desc: 'تسجيل حضور عبر الهاتف، تحديد الموقع، كشوف الراتب، طلبات الإجازة وإشعارات فورية.', href: '/signup?source=download_employee_android' },
        { name: 'Leopardo Manager', desc: 'الفريق، الجداول، المهام، الموافقات على الطلبات ورواتب مبسطة من الميدان.', href: '/signup?source=download_manager_android' },
        { name: 'Leopardo Platform Admin', desc: 'إنشاء tenants، الإشراف على شركات العملاء وتجهيز 2FA.', href: '/signup?source=download_platform-admin_android' },
      ],
      storeNote: 'Google Play وApp Store:',
      soon: 'قريبًا',
      joinTesters: 'انضم إلى المختبِرين',
      moreDetails: 'مزيد من التفاصيل حول التطبيقات الثلاثة:',
      mobilePage: 'صفحة تطبيقات الهاتف ←',
    },
    search: {
      noResults: 'لا توجد نتائج لـ « {query} »',
      clear: 'مسح البحث',
    },
    quickLinks: {
      title: 'روابط سريعة',
      links: [
        { label: 'API Explorer', desc: 'اختبر النقاط مباشرة', href: '/integrations#api' },
        { label: 'دليل المصادقة', desc: 'Bearer tokens، Google OAuth، الصلاحيات', href: '/docs#api-quickstart' },
        { label: 'دليل النشر', desc: 'Docker، Render، Vercel', href: '/docs#api-quickstart' },
      ],
    },
  },
}
