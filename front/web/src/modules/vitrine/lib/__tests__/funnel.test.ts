import {
  FUNNEL_EVENTS,
  getFunnelAttribution,
  getFunnelCorrelationId,
  trackFunnelStep,
} from '../funnel';
import { getAnalytics } from '../analytics';

// #7542 — on isole l'émission : on veut vérifier CE QUI est émis (nom, corrélation,
// attribution, absence de donnée sensible), pas le transport GA/Mixpanel.
jest.mock('../analytics', () => ({
  getAnalytics: jest.fn(),
}));

// #7496 — le module respecte le consentement (#7593) : les tests d'émission
// tournent avec la mesure d'audience accordée ; un cas dédié couvre le refus.
jest.mock('../consent', () => ({
  trackingAllowed: jest.fn(() => true),
}));

const mockedTrackingAllowed = require('../consent').trackingAllowed as jest.Mock;

const mockedGetAnalytics = getAnalytics as jest.Mock;

function goto(url: string): void {
  window.history.pushState({}, '', url);
}

describe('funnel — #7542 (tranche 1 de #7496)', () => {
  let trackEvent: jest.Mock;

  beforeEach(() => {
    window.sessionStorage.clear();
    goto('/signup');
    trackEvent = jest.fn();
    mockedGetAnalytics.mockReturnValue({ trackEvent });
    mockedTrackingAllowed.mockReturnValue(true);
    // #7496 — beacon first-party : transport isolé.
    global.fetch = jest.fn().mockResolvedValue({ ok: true }) as unknown as typeof fetch;
  });

  describe('correlation id', () => {
    it('garde le même identifiant pour tout le parcours', () => {
      const first = getFunnelCorrelationId();
      expect(first).toBeTruthy();
      expect(getFunnelCorrelationId()).toBe(first);
    });

    it('partage l’identifiant avec les événements émis', () => {
      const id = getFunnelCorrelationId();
      trackFunnelStep(FUNNEL_EVENTS.signupView);
      expect(trackEvent).toHaveBeenCalledWith(
        'signup_view',
        expect.objectContaining({ correlation_id: id }),
      );
    });
  });

  describe('attribution', () => {
    it('capte source et utm_* depuis l’URL', () => {
      goto('/signup?source=download_employee_android&utm_source=facebook&utm_campaign=rentree');
      expect(getFunnelAttribution()).toEqual({
        source: 'download_employee_android',
        utm_source: 'facebook',
        utm_campaign: 'rentree',
      });
    });

    it('ne perd pas l’attribution quand l’URL du tunnel change', () => {
      goto('/signup?source=download_employee_android&utm_source=facebook');
      getFunnelAttribution();

      // Retour de Google OAuth : plus aucune paramètre d'attribution dans l'URL.
      goto('/signup?google=1');
      expect(getFunnelAttribution()).toEqual({
        source: 'download_employee_android',
        utm_source: 'facebook',
      });
    });

    it('n’écrase pas une valeur déjà captée au premier écran', () => {
      goto('/signup?source=campagne_a');
      getFunnelAttribution();

      goto('/signup?source=campagne_b');
      expect(getFunnelAttribution().source).toBe('campagne_a');
    });
  });

  describe('émission', () => {
    it('joint l’attribution à l’événement', () => {
      goto('/signup?source=campagne_a&utm_medium=cpc');
      trackFunnelStep(FUNNEL_EVENTS.signupView);

      expect(trackEvent).toHaveBeenCalledWith(
        'signup_view',
        expect.objectContaining({ source: 'campagne_a', utm_medium: 'cpc' }),
      );
    });

    it('transmet les clés de contexte sans aucune valeur de formulaire', () => {
      trackFunnelStep(FUNNEL_EVENTS.signupEmailSubmitted, { page: '/signup' });

      const [, payload] = trackEvent.mock.calls[0];
      expect(payload).toMatchObject({ page: '/signup' });
      // Critère 4 de #7496 : jamais de secret ni de valeur saisie.
      const serialized = JSON.stringify(payload);
      expect(serialized).not.toMatch(/password|passwd|otp_code|secret|token/i);
    });

    it('couvre le plan de tracking complet de #7496', () => {
      expect(Object.values(FUNNEL_EVENTS)).toEqual([
        'signup_view',
        'signup_email_submitted',
        'signup_otp_sent',
        'signup_otp_verified',
        'space_provisioned',
        'welcome_seen',
        'interview_started',
        'interview_question_answered',
        'interview_skipped',
        'interview_completed',
        'interview_dismissed',
        'first_module_opened',
      ]);
    });

    it('envoie aussi un beacon first-party vers /api/forms/funnel-event (#7496)', () => {
      goto('/signup?source=campagne_a');
      trackFunnelStep(FUNNEL_EVENTS.signupView, { page: '/signup' });

      expect(global.fetch).toHaveBeenCalledTimes(1);
      const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
      expect(url).toBe('/api/forms/funnel-event');
      const body = JSON.parse((init as RequestInit).body as string);
      expect(body).toMatchObject({
        event: 'signup_view',
        attribution: { source: 'campagne_a' },
        context: { page: '/signup' },
      });
      expect(typeof body.correlation_id).toBe('string');
      // Critère 4 de #7496 : aucune PII dans le beacon.
      expect(JSON.stringify(body)).not.toMatch(/@|password|otp_code/i);
    });

    it('n’émet RIEN sans consentement à la mesure d’audience (#7593)', () => {
      mockedTrackingAllowed.mockReturnValue(false);
      trackFunnelStep(FUNNEL_EVENTS.signupView);

      expect(trackEvent).not.toHaveBeenCalled();
      expect(global.fetch).not.toHaveBeenCalled();
    });

    it('le beacon ne casse jamais le parcours quand fetch lève', () => {
      (global.fetch as jest.Mock).mockImplementation(() => {
        throw new Error('réseau coupé');
      });

      expect(() => trackFunnelStep(FUNNEL_EVENTS.signupView)).not.toThrow();
      expect(trackEvent).toHaveBeenCalled();
    });

    it('ne casse jamais le parcours quand la mesure échoue', () => {
      mockedGetAnalytics.mockImplementation(() => {
        throw new Error('analytics indisponible');
      });

      expect(() => trackFunnelStep(FUNNEL_EVENTS.signupView)).not.toThrow();
    });

    it('ne casse pas non plus si le transport lève', () => {
      trackEvent.mockImplementation(() => {
        throw new Error('gtag explode');
      });

      expect(() => trackFunnelStep(FUNNEL_EVENTS.signupView)).not.toThrow();
    });
  });
});
