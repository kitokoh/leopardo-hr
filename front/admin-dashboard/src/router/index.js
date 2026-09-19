import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'
import NProgress from 'nprogress'
import 'nprogress/nprogress.css'
import FuelManagerView from '@/views/fuel/FuelManagerView.vue'

// Configuration NProgress
NProgress.configure({
  showSpinner: false,
  minimum: 0.2,
  speed: 500
})

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: {
      requiresAuth: false,
      title: 'navigation.login'
    }
  },
  {
    path: '/logout',
    name: 'logout',
    component: () => import('@/views/auth/LogoutView.vue'),
    meta: {
      requiresAuth: true,
      title: 'navigation.logout'
    }
  },
  {
    path: '/',
    component: () => import('@/layouts/DashboardLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () => import('@/views/DashboardView.vue'),
        meta: {
          title: 'navigation.dashboard',
          icon: 'HomeIcon'
        }
      },
      {
        path: '/analytics',
        name: 'analytics',
        component: () => import('@/views/analytics/AnalyticsView.vue'),
        meta: {
          title: 'navigation.analytics',
          icon: 'ChartBarIcon'
        }
      },
      {
        path: '/solutions/survey-stats',
        // #7725 — nom normalisé kebab-case (ex-`solutionSurveyStats`).
        name: 'solution-survey-stats',
        component: () => import('@/views/solutions/SolutionSurveyStatsView.vue'),
        meta: {
          title: 'navigation.surveyStats',
          icon: 'ChatBubbleLeftRightIcon'
        }
      },
      {
        path: '/globe',
        name: 'globe',
        component: () => import('@/views/globe/GlobeView.vue'),
        meta: {
          title: 'navigation.globe',
          icon: 'GlobeAltIcon'
        }
      },
      {
        path: '/users',
        name: 'users',
        component: () => import('@/views/users/UsersView.vue'),
        meta: {
          title: 'navigation.users',
          icon: 'UsersIcon'
        }
      },
      {
        path: '/companies',
        name: 'companies',
        component: () => import('@/views/companies/CompaniesView.vue'),
        meta: {
          title: 'navigation.companies',
          icon: 'BuildingOfficeIcon'
        }
      },
      {
        path: '/companies/:id',
        name: 'company-detail',
        component: () => import('@/views/companies/CompanyDetailView.vue'),
        meta: {
          title: 'navigation.companyDetail',
          parent: 'companies'
        }
      },
      {
        path: '/subscriptions',
        name: 'subscriptions',
        component: () => import('@/views/subscriptions/SubscriptionsView.vue'),
        meta: {
          title: 'navigation.subscriptions',
          icon: 'CreditCardIcon'
        }
      },
      {
        path: '/support',
        name: 'support',
        // #7725 — vue fusionnée (demandes + centre de tickets en onglets).
        component: () => import('@/views/support/SupportCenterView.vue'),
        meta: {
          title: 'navigation.support',
          icon: 'LifebuoyIcon'
        }
      },
      {
        // #7725 — fusion Support : l'ancien « Centre support client »
        // (`/support-tickets`) redirige vers l'onglet tickets de l'entrée
        // Support unique. La query (`company_id`…, cf. CompanyDetailView)
        // est conservée par la redirection vue-router.
        path: '/support-tickets',
        name: 'support-tickets',
        redirect: (to) => ({ path: '/support', query: { ...to.query, tab: 'tickets' } }),
      },
      {
        path: '/crm/pipeline',
        name: 'crm-pipeline',
        component: () => import('@/views/crm/CrmPipelineView.vue'),
        meta: {
          title: 'navigation.crm',
          icon: 'FunnelIcon'
        }
      },
      {
        path: '/crm/acquisition-funnel',
        name: 'acquisition-funnel',
        component: () => import('@/views/crm/AcquisitionFunnelView.vue'),
        meta: {
          title: 'navigation.acquisitionFunnel',
          icon: 'ChartBarSquareIcon'
        }
      },
      {
        path: '/system',
        name: 'system',
        component: () => import('@/views/system/SystemView.vue'),
        meta: {
          title: 'navigation.system',
          icon: 'CogIcon'
        }
      },
      {
        // #7557 — équipe plateforme : comptes internes et rôles délégués.
        // Réservé à `team.manage` (porté par le seul rôle `super_admin`) :
        // l'entrée de menu est filtrée sur cette permission, l'API répond 403
        // PLATFORM_PERMISSION_REQUIRED à un compte non habilité.
        path: '/team',
        name: 'platform-team',
        component: () => import('@/views/team/PlatformTeamView.vue'),
        meta: {
          title: 'navigation.team',
          icon: 'UsersIcon'
        }
      },

      {
        path: '/settings/payroll/social-contributions',
        name: 'social-contributions',
        component: () => import('@/views/settings/SocialContributionsView.vue'),
        meta: {
          title: 'navigation.contributions',
          icon: 'BanknotesIcon'
        }
      },
      {
        path: '/settings/payroll/tax-slabs',
        name: 'tax-slabs',
        component: () => import('@/views/settings/TaxSlabsView.vue'),
        meta: {
          title: 'navigation.taxBrackets',
          icon: 'ScaleIcon'
        }
      },
      {
        path: '/settings/payroll/tax-rates',
        name: 'tax-rates',
        component: () => import('@/views/settings/TaxRatesView.vue'),
        meta: {
          title: 'navigation.legalRates',
          // #7725 — icône unique (ScaleIcon reste aux barèmes fiscaux).
          icon: 'ReceiptPercentIcon'
        }
      },
      {
        path: '/accounting/settings',
        name: 'accounting-settings',
        component: () => import(`@/views/accounting/AccountingSettingsView.vue`),
        meta: {
          title: 'navigation.accountingSettings',
          icon: 'CogIcon'
        }
      },
      {
        path: '/accounting/activation',
        name: 'accounting-activation',
        component: () => import(`@/views/accounting/AccountingActivationView.vue`),
        meta: {
          title: 'navigation.accountingActivation',
          icon: 'SparklesIcon'
        }
      },
      {
        path: '/accounting/dashboard',
        name: 'accounting-dashboard',
        component: () => import(`@/views/accounting/AccountingDashboardView.vue`),
        meta: {
          title: 'navigation.accountingDashboard',
          icon: 'ChartBarIcon'
        }
      },
      {
        // #7776 — écrans du rôle comptable (spec 5534) : documents, plan
        // comptable, grand livre/journal, lettrage, exercices, banque, états.
        path: '/accounting/documents',
        name: 'accounting-documents',
        component: () => import('@/views/accounting/AccountingDocumentsView.vue'),
        meta: {
          title: 'navigation.accountingDocuments',
          icon: 'DocumentTextIcon'
        }
      },
      {
        path: '/accounting/chart',
        name: 'accounting-chart',
        component: () => import('@/views/accounting/AccountingChartView.vue'),
        meta: {
          title: 'navigation.accountingChart',
          icon: 'TableCellsIcon'
        }
      },
      {
        path: '/accounting/ledger',
        name: 'accounting-ledger',
        component: () => import('@/views/accounting/AccountingLedgerView.vue'),
        meta: {
          title: 'navigation.accountingLedger',
          icon: 'BookOpenIcon'
        }
      },
      {
        path: '/accounting/lettering',
        name: 'accounting-lettering',
        component: () => import('@/views/accounting/AccountingLetteringView.vue'),
        meta: {
          title: 'navigation.accountingLettering',
          icon: 'LinkIcon'
        }
      },
      {
        path: '/accounting/fiscal-years',
        name: 'accounting-fiscal-years',
        component: () => import('@/views/accounting/AccountingFiscalYearsView.vue'),
        meta: {
          title: 'navigation.accountingFiscalYears',
          icon: 'CalendarDaysIcon'
        }
      },
      {
        path: '/accounting/bank',
        name: 'accounting-bank',
        component: () => import('@/views/accounting/AccountingBankView.vue'),
        meta: {
          title: 'navigation.accountingBank',
          icon: 'BuildingLibraryIcon'
        }
      },
      {
        path: '/accounting/statements',
        name: 'accounting-statements',
        component: () => import('@/views/accounting/AccountingStatementsView.vue'),
        meta: {
          title: 'navigation.accountingStatements',
          icon: 'ChartPieIcon'
        }
      },
      {
        path: '/training',
        name: 'training',
        component: () => import('@/views/training/TrainingView.vue'),
        meta: {
          title: 'navigation.training',
          icon: 'AcademicCapIcon'
        }
      },
      {
        // Chemin dédié : `/fuel-station` reste réservé au hub `fuelStation`
        // (FuelManagerView), cible de la Sidebar et des e2e. Deux routes
        // déclaraient auparavant le MÊME chemin `/fuel-station` ; la première
        // gagnait sur une URL saisie directement alors que la Sidebar
        // navigue par nom vers la seconde → rendu non déterministe.
        path: '/fuel-station/operations',
        name: 'fuel-station-operations',
        component: () => import('@/views/fuel/FuelStationView.vue'),
        meta: {
          title: 'navigation.fuelStation',
          icon: 'FireIcon'
        }
      },
      {
        path: '/fleet',
        name: 'fleet',
        component: () => import('@/views/fleet/FleetView.vue'),
        meta: {
          title: 'navigation.fleet',
          icon: 'TruckIcon'
          // requiresTenant retiré (#4710) : FleetView gère le 401 super-admin
          // via _skipAuthRedirect (#4170) et affiche un état d'erreur honnête.
        }
      },
      {
        path: '/fuel-station',
        // #7725 — nom normalisé kebab-case (ex-`fuelStation`).
        name: 'fuel-station',
        component: FuelManagerView,
        meta: {
          title: 'navigation.fuelStation',
          icon: 'BoltIcon'
        }
      },
      {
        path: '/chat',
        name: 'chat',
        component: () => import('@/views/chat/ChatView.vue'),
        meta: {
          title: 'navigation.chat',
          icon: 'SparklesIcon'
        }
      },
      {
        path: '/webhooks',
        name: 'webhooks',
        component: () => import('@/views/webhooks/WebhooksView.vue'),
        meta: {
          title: 'navigation.webhooks',
          icon: 'LinkIcon'
        }
      },
      {
        path: '/exports',
        name: 'exports',
        component: () => import('@/views/exports/ExportsView.vue'),
        meta: {
          title: 'navigation.exports',
          icon: 'ArrowDownTrayIcon'
          // requiresTenant retiré (#4710) : ExportsView gère le 401 super-admin
          // avec état d'erreur visible + retry (#3395).
        }
      },
      {
        path: '/growth',
        name: 'growth',
        component: () => import('@/views/growth/GrowthDashboardView.vue'),
        meta: {
          title: 'navigation.growth',
          icon: 'ArrowTrendingUpIcon'
        }
      },
      {
        path: '/edge',
        name: 'edge',
        component: () => import('@/views/edge/EdgeNodesView.vue'),
        meta: {
          title: 'navigation.edge',
          icon: 'ServerIcon'
        }
      },
      // ── Verticale TravelAgency (BC-24 TRAVEL, TRAVEL-601 #6078) ────────────
      // Entrée de navigation conditionnée par le flag `travelagency` (store
      // travel) ; chaque écran consomme exclusivement les endpoints réels
      // /travel/* (convention TRAVEL-041, aucune donnée mock).
      {
        path: '/travel',
        name: 'travel',
        component: () => import('../views/travel/TravelHomeView.vue'),
        meta: {
          // #7725 — clé réconciliée avec le menu (`navigation.travelAgency`,
          // même libellé partout ; `navigation.travel` divergeait).
          title: 'navigation.travelAgency',
          icon: 'PaperAirplaneIcon'
        }
      },
      {
        path: '/travel/referential',
        name: 'travel-referential',
        component: () => import('../views/travel/TravelReferentialView.vue'),
        meta: {
          title: 'travel.referential.title',
          parent: 'travel'
        }
      },
      {
        path: '/travel/network',
        name: 'travel-network',
        component: () => import('../views/travel/TravelNetworkView.vue'),
        meta: {
          title: 'travel.network.title',
          parent: 'travel'
        }
      },
      {
        path: '/travel/bookings',
        name: 'travel-bookings',
        component: () => import('../views/travel/TravelBookingsView.vue'),
        meta: {
          title: 'travel.bookings.title',
          parent: 'travel'
        }
      },
      {
        path: '/travel/checkin',
        name: 'travel-checkin',
        component: () => import('../views/travel/TravelCheckinView.vue'),
        meta: {
          title: 'travel.checkin.title',
          parent: 'travel'
        }
      },
      {
        path: '/travel/tickets',
        name: 'travel-tickets',
        component: () => import('../views/travel/TravelTicketsView.vue'),
        meta: {
          title: 'travel.tickets.title',
          parent: 'travel'
        }
      },
      {
        path: '/travel/reports',
        name: 'travel-reports',
        component: () => import('../views/travel/TravelReportsView.vue'),
        meta: {
          title: 'travel.reports.title',
          parent: 'travel'
        }
      },
      {
        path: '/travel/content',
        name: 'travel-content',
        component: () => import('../views/travel/TravelContentView.vue'),
        meta: {
          title: 'travel.content.title',
          parent: 'travel'
        }
      },
      {
        path: '/travel/catalog',
        name: 'travel-catalog',
        component: () => import('../views/travel/TravelCatalogView.vue'),
        meta: {
          title: 'travel.catalog.title',
          parent: 'travel'
        }
      },
      {
        path: '/marketing/oauth',
        name: 'marketing-oauth',
        component: () => import('@/views/marketing/MarketingOAuthView.vue'),
        meta: {
          title: 'marketing.oauth.nav_title',
          icon: 'MegaphoneIcon'
        }
      },
      {
        // BC-29 COMMUNICATION — R6 (#7691) : boîte connectée, file de
        // confirmations et réglages (politiques R5 + relances R4).
        path: '/communication',
        name: 'communication',
        component: () => import('@/views/communication/CommunicationView.vue'),
        meta: {
          title: 'communicationApp.moduleTitle',
          icon: 'EnvelopeIcon'
        }
      },
      {
        path: '/showcase',
        name: 'showcase-editor',
        component: () => import('@/views/showcase/ShowcaseEditorView.vue'),
        meta: {
          title: 'navigation.showcase',
          icon: 'GlobeAltIcon'
        }
      },
      {
        path: '/settings',
        name: 'settings',
        component: () => import('@/views/settings/SettingsView.vue'),
        meta: {
          title: 'navigation.account',
          icon: 'UserCircleIcon'
        }
      },
      {
        // #7347 — édition des contenus d'e-mails (Paramètres › E-mails).
        path: '/settings/emails',
        name: 'settings-email-templates',
        component: () => import('@/views/settings/EmailTemplatesView.vue'),
        meta: {
          title: 'navigation.emailTemplates',
          icon: 'EnvelopeIcon'
        }
      },
      {
        // #7430 — « Offres & tarifs » : le paramétrage des offres passe par
        // l'UI (CRUD réel), plus par un déploiement. Même groupe « Paramètres »
        // que les autres écrans de paramétrage (Sidebar).
        path: '/settings/plans',
        name: 'settings-plans',
        component: () => import('@/views/settings/PlansView.vue'),
        meta: {
          title: 'plans.nav',
          icon: 'TagIcon'
        }
      },
      {
        // #7726 — « Passerelles de paiement » : configuration Stripe/Chargily
        // de la plateforme (clés chiffrées en BDD, fallback env, test de
        // connexion). Même groupe que « Offres & tarifs » (#7430).
        path: '/settings/payment-gateways',
        name: 'settings-payment-gateways',
        component: () => import('@/views/settings/PaymentGatewaysView.vue'),
        meta: {
          title: 'paymentGateways.nav',
          icon: 'CreditCardIcon'
        }
      },
      {
        // #7384/#7385 — assistant IA : réglages éditables + suivi (Paramètres › Assistant IA).
        path: '/settings/ai',
        name: 'settings-ai-assistant',
        component: () => import('@/views/settings/AiAssistantView.vue'),
        meta: {
          title: 'navigation.aiAssistant',
          icon: 'CpuChipIcon'
        }
      },
      {
        path: '/settings/payroll/holidays',
        name: 'payroll-holidays',
        component: () => import('../views/settings/HolidaysView.vue'),
        meta: {
          title: 'holidays.nav.title',
          icon: 'CalendarIcon'
        }
      }
    ]
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
    meta: {
      title: 'navigation.notFound'
    }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    } else {
      return { top: 0 }
    }
  }
})

// Guards de navigation
// #7305 — Vue Router 5 déprécie la fonction `next()` (avertissement
// VUE_ROUTER_R0025) : un guard retourne désormais la route cible (redirection)
// ou `true` pour laisser passer.
router.beforeEach(async (to) => {
  NProgress.start()

  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    if (authStore.token) {
      const isValid = await authStore.checkAuth()
      if (!isValid) {
        return '/login'
      }
    } else {
      return '/login'
    }
  }

  // Rediriger vers dashboard si déjà connecté et tentative d'accès au login
  if (to.name === 'login' && authStore.isAuthenticated) {
    return '/'
  }

  // Mettre à jour le titre de la page dans la locale active.
  if (to.meta.title) {
    const localeStore = useLocaleStore()
    const title = translate(localeStore.current, to.meta.title, to.meta.title)
    document.title = `${title} - Leopardo Admin`
  }

  return true
})

router.afterEach(() => {
  NProgress.done()
})

export default router

// Export the routes table for layouts that need to resolve parent routes by
// name (e.g. DashboardLayout breadcrumbs). See issue #2335.
export { routes }
