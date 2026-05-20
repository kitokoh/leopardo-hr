import * as Sentry from '@sentry/vue'

export function initSentry(app, router) {
  const dsn = import.meta.env.VITE_SENTRY_DSN
  if (!dsn) return

  Sentry.init({
    app,
    dsn,
    integrations: [Sentry.browserTracingIntegration({ router })],
    tracesSampleRate: parseFloat(import.meta.env.VITE_SENTRY_TRACES_RATE || '0.2'),
    environment: import.meta.env.MODE,
    beforeBreadcrumb(breadcrumb) {
      if (breadcrumb.category === 'xhr' || breadcrumb.category === 'fetch') {
        const url = breadcrumb.data?.url || ''
        if (url.includes('/health')) return null
      }
      return breadcrumb
    },
  })
}

export function addApiErrorBreadcrumb(error) {
  if (!import.meta.env.VITE_SENTRY_DSN) return

  const response = error?.response
  const config = error?.config

  Sentry.addBreadcrumb({
    category: 'api.error',
    message: `${config?.method?.toUpperCase() || 'UNKNOWN'} ${config?.url || 'unknown'} → ${response?.status || 'network'}`,
    level: response?.status >= 500 ? 'error' : 'warning',
    data: {
      status: response?.status,
      url: config?.url,
      method: config?.method,
      responseMessage: response?.data?.message,
    },
  })

  if (response?.status >= 500) {
    Sentry.captureException(error, {
      tags: { api_status: response.status, api_endpoint: config?.url },
      extra: { responseBody: response?.data },
    })
  }
}
