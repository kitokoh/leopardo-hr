import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'
import NProgress from 'nprogress'
import 'nprogress/nprogress.css'

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
        component: () => import('@/views/support/SupportView.vue'),
        meta: {
          title: 'navigation.support',
          icon: 'ChatBubbleLeftRightIcon'
        }
      },
      {
        path: '/support-tickets',
        name: 'support-tickets',
        component: () => import('@/views/support/SupportTicketsView.vue'),
        meta: {
          title: 'navigation.supportTickets',
          icon: 'LifebuoyIcon'
        }
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
        path: '/system',
        name: 'system',
        component: () => import('@/views/system/SystemView.vue'),
        meta: {
          title: 'navigation.system',
          icon: 'CogIcon'
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
          icon: 'ScaleIcon'
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
        path: '/training',
        name: 'training',
        component: () => import('@/views/training/TrainingView.vue'),
        meta: {
          title: 'navigation.training',
          icon: 'AcademicCapIcon'
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
          title: 'navigation.travel',
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
        path: '/travel/adverts',
        name: 'travel-adverts',
        component: () => import('../views/travel/TravelAdvertsView.vue'),
        meta: {
          title: 'travel.adverts.title',
          parent: 'travel'
        }
      },
      {
        path: '/travel/quizzes',
        name: 'travel-quizzes',
        component: () => import('../views/travel/TravelQuizzesView.vue'),
        meta: {
          title: 'travel.quiz.title',
          parent: 'travel'
        }
      },
      {
        path: '/travel/sites',
        name: 'travel-sites',
        component: () => import('../views/travel/TravelSitesView.vue'),
        meta: {
          title: 'travel.sites.title',
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
        path: '/settings',
        name: 'settings',
        component: () => import('@/views/settings/SettingsView.vue'),
        meta: {
          title: 'navigation.account',
          icon: 'UserCircleIcon'
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
router.beforeEach(async (to, from, next) => {
  NProgress.start()

  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    if (authStore.token) {
      const isValid = await authStore.checkAuth()
      if (!isValid) {
        next('/login')
        return
      }
    } else {
      next('/login')
      return
    }
  }

  // Rediriger vers dashboard si déjà connecté et tentative d'accès au login
  if (to.name === 'login' && authStore.isAuthenticated) {
    next('/')
    return
  }

  // Mettre à jour le titre de la page dans la locale active.
  if (to.meta.title) {
    const localeStore = useLocaleStore()
    const title = translate(localeStore.current, to.meta.title, to.meta.title)
    document.title = `${title} - Leopardo RH Admin`
  }

  next()
})

router.afterEach(() => {
  NProgress.done()
})

export default router

// Export the routes table for layouts that need to resolve parent routes by
// name (e.g. DashboardLayout breadcrumbs). See issue #2335.
export { routes }
