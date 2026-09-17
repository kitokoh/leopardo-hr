import {
  CONSENT_COOKIE,
  CONSENT_VERSION,
  acceptAllConsent,
  clearConsent,
  deniedConsent,
  readConsent,
  toConsentMode,
  trackingAllowed,
  writeConsent,
} from '../consent'

// #7593 — le consentement doit être explicite, mémorisé, et son absence vaut refus.
describe('consentement — état et stockage', () => {
  beforeEach(() => {
    clearConsent()
  })

  it('aucun choix mémorisé → readConsent renvoie null (donc rien n’est autorisé)', () => {
    expect(readConsent()).toBeNull()
    expect(trackingAllowed()).toBe(false)
  })

  it('mémorise le choix et le relit', () => {
    writeConsent(acceptAllConsent(), new Date('2026-09-16T10:00:00.000Z'))

    expect(readConsent()).toEqual({
      version: CONSENT_VERSION,
      necessary: true,
      analytics: true,
      marketing: true,
      decidedAt: '2026-09-16T10:00:00.000Z',
    })
    expect(trackingAllowed()).toBe(true)
  })

  it('un refus est un choix mémorisé (et reste un refus)', () => {
    writeConsent(deniedConsent())

    expect(readConsent()?.analytics).toBe(false)
    expect(trackingAllowed()).toBe(false)
  })

  it('un choix portant sur une version périmée du texte re-sollicite le visiteur', () => {
    writeConsent(acceptAllConsent())
    document.cookie = `${CONSENT_COOKIE}=${encodeURIComponent(
      JSON.stringify({ version: CONSENT_VERSION - 1, analytics: true, marketing: true }),
    )}; Path=/`
    window.localStorage.setItem(
      CONSENT_COOKIE,
      JSON.stringify({ version: CONSENT_VERSION - 1, analytics: true, marketing: true }),
    )

    expect(readConsent()).toBeNull()
    expect(trackingAllowed()).toBe(false)
  })

  it('un cookie illisible ne casse rien (traité comme « pas de choix »)', () => {
    document.cookie = `${CONSENT_COOKIE}=pas-du-json; Path=/`
    window.localStorage.setItem(CONSENT_COOKIE, 'pas-du-json')

    expect(readConsent()).toBeNull()
  })

  it('annonce le changement aux composants abonnés', () => {
    const seen: unknown[] = []
    const listener = (event: Event) => seen.push((event as CustomEvent).detail)
    window.addEventListener('leopardo-consent-changed', listener)

    writeConsent(acceptAllConsent())

    window.removeEventListener('leopardo-consent-changed', listener)
    expect(seen).toHaveLength(1)
    expect((seen[0] as { analytics: boolean }).analytics).toBe(true)
  })
})

describe('consentement — Consent Mode v2', () => {
  it('tout refusé par défaut', () => {
    expect(toConsentMode(deniedConsent())).toEqual({
      ad_storage: 'denied',
      ad_user_data: 'denied',
      ad_personalization: 'denied',
      analytics_storage: 'denied',
    })
  })

  it('mesure d’audience seule : la publicité reste refusée', () => {
    expect(toConsentMode({ analytics: true, marketing: false })).toEqual({
      ad_storage: 'denied',
      ad_user_data: 'denied',
      ad_personalization: 'denied',
      analytics_storage: 'granted',
    })
  })
})
