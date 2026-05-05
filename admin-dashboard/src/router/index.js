import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
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
      title: 'Connexion'
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
          title: 'Tableau de bord',
          icon: 'HomeIcon'
        }
      },
      {
        path: '/analytics',
        name: 'analytics',
        component: () => import('@/views/analytics/AnalyticsView.vue'),
        meta: {
          title: 'Analytics',
          icon: 'ChartBarIcon'
        }
      },
      {
        path: '/globe',
        name: 'globe',
        component: () => import('@/views/globe/GlobeView.vue'),
        meta: {
          title: 'Globe Temps Réel',
          icon: 'GlobeAltIcon'
        }
      },
      {
        path: '/users',
        name: 'users',
        component: () => import('@/views/users/UsersView.vue'),
        meta: {
          title: 'Utilisateurs',
          icon: 'UsersIcon'
        }
      },
      {
        path: '/users/:id',
        name: 'user-detail',
        component: () => import('@/views/users/UserDetailView.vue'),
        meta: {
          title: 'Détail Utilisateur',
          parent: 'users'
        }
      },
      {
        path: '/companies',
        name: 'companies',
        component: () => import('@/views/companies/CompaniesView.vue'),
        meta: {
          title: 'Entreprises',
          icon: 'BuildingOfficeIcon'
        }
      },
      {
        path: '/companies/:id',
        name: 'company-detail',
        component: () => import('@/views/companies/CompanyDetailView.vue'),
        meta: {
          title: 'Détail Entreprise',
          parent: 'companies'
        }
      },
      {
        path: '/subscriptions',
        name: 'subscriptions',
        component: () => import('@/views/subscriptions/SubscriptionsView.vue'),
        meta: {
          title: 'Abonnements',
          icon: 'CreditCardIcon'
        }
      },
      {
        path: '/support',
        name: 'support',
        component: () => import('@/views/support/SupportView.vue'),
        meta: {
          title: 'Support',
          icon: 'ChatBubbleLeftRightIcon'
        }
      },
      {
        path: '/system',
        name: 'system',
        component: () => import('@/views/system/SystemView.vue'),
        meta: {
          title: 'Système',
          icon: 'CogIcon'
        }
      },
      {
        path: '/logs',
        name: 'logs',
        component: () => import('@/views/system/LogsView.vue'),
        meta: {
          title: 'Logs',
          icon: 'DocumentTextIcon'
        }
      }
    ]
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
    meta: {
      title: 'Page non trouvée'
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

  // Vérifier l'authentification
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next('/login')
    return
  }

  // Rediriger vers dashboard si déjà connecté et tentative d'accès au login
  if (to.name === 'login' && authStore.isAuthenticated) {
    next('/')
    return
  }

  // Mettre à jour le titre de la page
  if (to.meta.title) {
    document.title = `${to.meta.title} - Leopardo RH Admin`
  }

  next()
})

router.afterEach(() => {
  NProgress.done()
})

export default router