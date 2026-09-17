import { getAnalytics } from '../analytics'
import { acceptAllConsent, clearConsent, writeConsent } from '../consent'

// #7593 — deuxième barrière : même si `gtag`/`mixpanel` sont présents (extension
// de navigateur, autre script), aucun envoi ne doit partir sans autorisation.
describe('analytics — barrière de consentement', () => {
  const gtagCalls: unknown[] = []
  const mixpanelCalls: unknown[] = []

  beforeEach(() => {
    gtagCalls.length = 0
    mixpanelCalls.length = 0
    clearConsent()
    window.gtag = (...args: unknown[]) => gtagCalls.push(args)
    window.mixpanel = { track: (...args: unknown[]) => mixpanelCalls.push(args), init: () => undefined }
  })

  afterEach(() => {
    delete (window as { gtag?: unknown }).gtag
    delete (window as { mixpanel?: unknown }).mixpanel
    clearConsent()
  })

  it('sans consentement, les envois sont sans effet même si les traceurs sont présents', () => {
    const analytics = getAnalytics()

    expect(analytics.isEnabled()).toBe(false)
    analytics.trackPageView('/pricing', 'Pricing')
    analytics.trackSignup('a@example.com')
    analytics.trackDemoRequest('a@example.com', 'ACME')
    analytics.trackContact('a@example.com', 'sujet')
    analytics.trackNewsletterSignup('a@example.com')
    analytics.trackCTAClick('Essai', '/', 'hero')
    analytics.trackFormSubmission('signup', '/signup')
    analytics.trackScrollDepth('/pricing', 50)
    analytics.trackTimeOnPage('/pricing', 30)
    analytics.trackEvent('custom_event', { a: 1 })
    analytics.setUserProperties({ plan: 'pilot' })
    analytics.identifyUser('user-1')

    expect(gtagCalls).toHaveLength(0)
    expect(mixpanelCalls).toHaveLength(0)
  })

  it('la surface complète du client est couverte (aucune méthode oubliée)', () => {
    const methods = [
      'isEnabled',
      'trackPageView',
      'trackConversion',
      'trackSignup',
      'trackDemoRequest',
      'trackContact',
      'trackNewsletterSignup',
      'trackCTAClick',
      'trackFormSubmission',
      'trackScrollDepth',
      'trackTimeOnPage',
      'trackEvent',
      'setUserProperties',
      'identifyUser',
    ]

    // Toute méthode publique doit exister sur le client inerte.
    for (const method of methods) {
      expect(typeof (getAnalytics() as unknown as Record<string, unknown>)[method]).toBe('function')
    }
  })

  it('avec consentement, le client actif prend le relais et les envois repartent', () => {
    const inert = getAnalytics()
    // Sans identifiant de mesure configuré, le client actif n'enverrait rien :
    // on en fournit un pour observer l'envoi (et non la seule bascule).
    const previousGaId = process.env.NEXT_PUBLIC_GA_ID
    process.env.NEXT_PUBLIC_GA_ID = 'G-TEST'

    writeConsent(acceptAllConsent())
    const active = getAnalytics()

    expect(active).not.toBe(inert)
    active.trackSignup('a@example.com')
    expect(gtagCalls.length).toBeGreaterThan(0)

    if (previousGaId === undefined) delete process.env.NEXT_PUBLIC_GA_ID
    else process.env.NEXT_PUBLIC_GA_ID = previousGaId
  })
})
