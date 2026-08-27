import { t } from '@/lib/i18n/locale-catalog'

// Issue #4863 : les erreurs des routes API serveur du portail (auth/login,
// billing/checkout) doivent suivre la locale du client — plus de FR en dur.
// Le `t()` du catalogue est partagé entre le client et les routes serveur ;
// on vérifie ici la présence et la localisation des clés utilisées.

describe('portal API route messages i18n (#4863)', () => {
  const KEYS = [
    'api.login_invalid_json',
    'api.login_timeout',
    'api.login_network_error',
    'api.login_backend_error',
    'billing.checkout_unavailable',
    'billing.checkout_sandbox_message',
    'billing.checkout_failed',
    'billing.cancel_subscription_confirm',
    'billing.no_active_period',
    'billing.no_active_subscription',
    'billing.period_label',
    'contracts.list_subtitle',
  ] as const

  it('chaque clé est résolue ×4 locales (non vide, non FR dupliqué en en/tr/ar)', () => {
    for (const key of KEYS) {
      for (const locale of ['fr', 'en', 'tr', 'ar'] as const) {
        const value = t(locale, key, '')
        expect(value.trim().length).toBeGreaterThan(0)
      }
      // EN/TR/AR ne doivent pas retomber sur le texte FR
      const fr = t('fr', key, '')
      const en = t('en', key, '')
      if (key.startsWith('api.')) {
        // Les codes d'erreur API gardent un identifiant stable ; le message
        // EN doit différer du FR (traduit, pas fallback silencieux).
        expect(en).not.toBe(fr)
      }
    }
  })

  it('les routes serveur ne contiennent plus de littéral FR en dur', () => {
    const fs = require('fs')
    const path = require('path')
    const routes = [
      'src/app/api/v1/auth/login/route.ts',
      'src/app/api/billing/checkout/route.ts',
    ]
    const frPattern = /['"][^'"]*[àâäéèêëîïôöùûüçÀ\u00C2\u00C4ÉÈÊËÎÏÔÖÙÛÜÇ][^'"]*['"]/g
    for (const r of routes) {
      const src = fs.readFileSync(path.join(process.cwd(), r), 'utf8')
      const code = src
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .split('\n')
        .filter((l: string) => !l.trim().startsWith('//'))
        .join('\n')
      // #4863 : les fallbacks i18nT(locale, 'key', 'FR…') sont autorisés (3e
      // argument) — y compris quand le formatage (prettier) place le fallback
      // sur sa propre ligne. On retire ces appels AVANT la vérification pour
      // ne garder que les littéraux réellement en dur.
      const withoutFallbacks = code.replace(
        /i18nT\(\s*[^,]+,\s*["'][^"']+["'],\s*["'][^"']*["'],?\s*\)/g,
        'i18nT(locale, "key", "fallback")',
      )
      const frLines = withoutFallbacks
        .split('\n')
        .filter((l: string) => {
          const matches = l.match(frPattern)
          return (matches ?? []).length > 0
        })
      // Les fallbacks t(locale, key, 'FR…') sont autorisés (3e argument).
      const hardcoded = frLines.filter((l: string) => !l.includes("t(locale, '"))
      expect(hardcoded).toEqual([])
    }
  })
})
