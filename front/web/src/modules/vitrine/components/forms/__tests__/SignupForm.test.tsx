import React from 'react';
import { render, screen, waitFor, fireEvent, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { SignupForm } from '../SignupForm';
import { submitSignupForm, fetchTrialStatus } from '@/modules/vitrine/lib/forms';
import { trackFunnelStep, FUNNEL_EVENTS } from '@/modules/vitrine/lib/funnel';

// ─────────────────────────────────────────────────────────────────────────────
// framer-motion neutralisé (test déterministe, issue constat QA 2026-08-15) :
// 1. `AnimatePresence mode="wait"` ne monte l'étape suivante qu'après la sortie
//    animée (0,3 s) pilotée par RAF. framer-motion capture la VRAIE
//    requestAnimationFrame au chargement du module : les fake timers ne la
//    pilotent pas → le test « polls pending → ready » échouait de façon
//    NON DÉTERMINISTE sur main (parfois l'animation se terminait en temps réel,
//    parfois non).
// 2. `motion.<tag>` rend l'élément DOM réel du tag (div, input, select, …) :
//    le design system (Input → motion.input, Select → motion.select) doit
//    produire de vrais contrôles pour les requêtes getByRole/getAllByRole.
// ─────────────────────────────────────────────────────────────────────────────
jest.mock('framer-motion', () => {
  const { createElement, Fragment, forwardRef } = require('react');

  const stripMotionProps = ({
    initial,
    animate,
    exit,
    transition,
    whileFocus,
    whileHover,
    whileTap,
    whileInView,
    variants,
    layout,
    ...rest
  }: any) => rest;

  return {
    AnimatePresence: ({ children }: any) => createElement(Fragment, null, children),
    // #7495 — MotionConfig (prefers-reduced-motion) : transparent en test.
    MotionConfig: ({ children }: any) => createElement(Fragment, null, children),
    motion: new Proxy(
      {},
      {
        get: (_target, tag: string) => {
          const MockMotionElement = forwardRef((props: any, ref: any) =>
            createElement(tag, { ...stripMotionProps(props), ref }, props.children)
          );
          MockMotionElement.displayName = `MockMotion${String(tag)}`;

          return MockMotionElement;
        },
      }
    ),
  };
});

// Mock the form submission
jest.mock('@/modules/vitrine/lib/forms', () => ({
  submitSignupForm: jest.fn(),
  submitVerifyForm: jest.fn(),
  fetchTrialStatus: jest.fn(),
  getLeadSource: () => 'signup_form',
  initialFormState: {
    isSubmitting: false,
    isSuccess: false,
    isError: false,
    message: '',
    errors: {},
  },
  createFormReducer: () => (state: any, action: any) => {
    switch (action.type) {
      case 'SUBMIT_START':
        return { ...state, isSubmitting: true };
      case 'SUBMIT_SUCCESS':
        return { ...state, isSubmitting: false, isSuccess: true, message: action.payload?.message ?? '' };
      case 'SUBMIT_ERROR':
        return { ...state, isSubmitting: false, isError: true, message: action.payload?.message ?? '' };
      case 'RESET':
        return { ...state, isSubmitting: false };
      default:
        return state;
    }
  },
}));

// #7542 — l'émission des jalons est testée dans funnel.test.ts ; ici on vérifie
// qu'elle est bien BRANCHÉE sur les transitions réelles du tunnel.
jest.mock('@/modules/vitrine/lib/funnel', () => ({
  trackFunnelStep: jest.fn(),
  FUNNEL_EVENTS: {
    signupView: 'signup_view',
    signupEmailSubmitted: 'signup_email_submitted',
    signupOtpSent: 'signup_otp_sent',
    signupOtpVerified: 'signup_otp_verified',
    spaceProvisioned: 'space_provisioned',
  },
}));

// On ne remplace QUE la fonction reseau : le mock precedent ecrasait tout le
// module, donc `SUPPORTED_COUNTRIES_FALLBACK` devenait `undefined` pour ses
// consommateurs (#7307 — `vitrine-numbers` derive le nombre de pays de paie de
// ce registre, ce qui cassait la suite a l'import). Un mock ne doit pas eraser
// les exports qu'il ne teste pas.
jest.mock('@/modules/vitrine/data/supported-countries', () => ({
  ...jest.requireActual('@/modules/vitrine/data/supported-countries'),
  fetchSupportedCountries: jest.fn().mockResolvedValue([
    { code: 'DZ', label: 'Algérie' },
    { code: 'MA', label: 'Maroc' },
  ]),
}));

/**
 * Env jsdom + React 19 : `userEvent.click` ne pose PAS le focus sur les
 * inputs et le clic sur un bouton submit ne déclenche pas toujours la
 * soumission du formulaire (quirk user-event/jest-jsdom, reproduit sur main
 * 2026-08-16). Pour des tests déterministes de la logique de validation, on
 * remplit les champs via fireEvent.change (synchrone, fiable) et on soumet
 * via fireEvent.submit(form) — le comportement navigateur réel (focus +
 * default action du submit) est couvert par les E2E Playwright.
 */
function submitForm(): void {
  const form = document.querySelector('form');
  if (!form) throw new Error('No form element rendered');
  fireEvent.submit(form);
}

async function fillField(label: RegExp, value: string): Promise<void> {
  const el = screen.getByRole('textbox', { name: label }) as HTMLInputElement;
  fireEvent.change(el, { target: { value } });
  fireEvent.blur(el);
}


/**
 * #7489 — le tunnel s'ouvre directement sur le FORMULAIRE (e-mail + nom de
 * l'espace) : le choix du profil (entreprise / indépendant) a été déplacé dans
 * l'entretien de préparation (#7493), il n'y a donc plus d'écran à traverser
 * avant les coordonnées.
 */
function renderAtFormStep() {
  return render(<SignupForm />);
}

const mockedSubmitSignupForm = submitSignupForm as jest.Mock;
const mockedTrackFunnelStep = trackFunnelStep as jest.Mock;

// The component is localized via useVitrineLocale(); the test environment
// defaults to navigator.language (en-US). Pin the locale to French so the
// assertions on the FR copy stay deterministic (issue #2648).
beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});


describe('SignupForm Component', () => {
  // QA onboarding 2026-09-14 : le composant reprend désormais le suivi au
  // montage quand un provisioning_token est présent en sessionStorage. Sans
  // nettoyage systématique, un token posé par un test de suivi « fuit » vers
  // les tests suivants (qui attendent l'écran de profil).
  beforeEach(() => {
    sessionStorage.clear();
    // #7542 — les jalons du funnel sont comptés par test : sans ce nettoyage,
    // les appels des tests précédents feraient échouer les assertions d'ordre.
    mockedTrackFunnelStep.mockClear();
  });

  describe('Rendering', () => {
    it('should render signup form', () => {
      renderAtFormStep();
      expect(screen.getByRole('textbox', { name: /email/i })).toBeInTheDocument();
    });

    it('should render email input', () => {
      renderAtFormStep();
      expect(screen.getByRole('textbox', { name: /email/i })).toBeInTheDocument();
    });

    it('should render company input', () => {
      renderAtFormStep();
      expect(screen.getByRole('textbox', { name: /entreprise/i })).toBeInTheDocument();
    });

    it('should render submit button', () => {
      renderAtFormStep();
      expect(screen.getByRole('button', { name: /créer mon espace/i })).toBeInTheDocument();
    });
  });

  describe('Form Validation', () => {
    it('should show error for invalid email', async () => {
      renderAtFormStep();
      await fillField(/email/i, 'invalid-email');
      submitForm();
      
      await waitFor(() => {
        expect(screen.getByText(/email invalide|valid email/i)).toBeInTheDocument();
      });
    });

    it('should show error for empty email', async () => {
      renderAtFormStep();
      submitForm();
      
      await waitFor(() => {
        expect(screen.getByText(/email (invalide|trop court)/i)).toBeInTheDocument();
      });
    });

    it('should show error for empty company', async () => {
      renderAtFormStep();
      await fillField(/email/i, 'test@example.com');
      submitForm();
      
      await waitFor(() => {
        expect(screen.getByText(/entreprise doit contenir/i)).toBeInTheDocument();
      });
    });

    // Le pays n'est plus demandé : il est résolu côté serveur par
    // géolocalisation (`request.geo`) dans /api/forms/signup, et reste
    // modifiable ensuite dans les paramètres de l'entreprise.
    it('ne demande plus le pays à l’utilisateur', async () => {
      renderAtFormStep();
      expect(screen.queryByRole('combobox', { name: /pays/i })).not.toBeInTheDocument();
    });

    // L'e-mail est vérifié par code : le téléphone n'est plus demandé.
    it('ne demande plus le téléphone', async () => {
      renderAtFormStep();
      expect(screen.queryByRole('textbox', { name: /téléphone/i })).not.toBeInTheDocument();
    });
  });

  describe('Form Submission', () => {
    it('should accept valid trial request fields', async () => {
      renderAtFormStep();
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      await userEvent.type(emailInput, 'test@example.com');
      await userEvent.type(screen.getByRole('textbox', { name: /entreprise/i }), 'Acme Corp');

      const submitButton = screen.getByRole('button', { name: /créer mon espace/i });
      expect(submitButton).not.toBeDisabled();
    });
  });

  describe('Accessibility', () => {
    it('should have accessible form labels', () => {
      renderAtFormStep();
      expect(screen.getByRole('textbox', { name: /email/i })).toBeInTheDocument();
    });

    it('should be keyboard navigable', async () => {
      renderAtFormStep();
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      emailInput.focus();
      expect(emailInput).toHaveFocus();
      
      await userEvent.tab();
      const nextElement = document.activeElement;
      expect(nextElement).not.toBe(emailInput);
    });

    it('should have proper form structure', () => {
      const { container } = renderAtFormStep();
      const form = container.querySelector('form');
      expect(form).toBeInTheDocument();
    });
  });

  describe('Cold-start fallback (PA2-MKT-002)', () => {
    beforeEach(() => {
      mockedSubmitSignupForm.mockReset();
    });

    async function fillValidForm() {
      await fillField(/email/i, 'test@example.com');
      await fillField(/entreprise/i, 'Acme Corp');
      // Formulaire simplifié : plus de rôle, de taille d'équipe, de pays
      // (détecté côté serveur) ni de téléphone — e-mail + entreprise + CGU.
      fireEvent.click(screen.getByRole('checkbox'));
    }

    it('shows the "we will contact you" pending screen instead of a fake OTP step when provisioned is false', async () => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: false,
        message: "Demande d'essai reçue. Notre équipe vous contacte sous 24h ouvrables.",
        data: { nextStep: 'contact_under_24h' },
      });

      renderAtFormStep();
      await fillValidForm();
      submitForm();

      await waitFor(() => {
        expect(screen.getByRole('heading', { name: /demande d'essai reçue/i })).toBeInTheDocument();
      });
      expect(screen.getByText(/notre équipe vous contacte sous 24h ouvrables/i)).toBeInTheDocument();
      expect(screen.queryByText(/vérifiez votre email/i)).not.toBeInTheDocument();
    });

    it('shows the OTP verification step when provisioned is true (default backend path)', async () => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: true,
        message: 'Code de vérification envoyé.',
        data: {},
      });

      renderAtFormStep();
      await fillValidForm();
      submitForm();

      await waitFor(() => {
        expect(screen.getByText(/vérifiez votre email/i)).toBeInTheDocument();
      });
    });
  });

  describe('Guided trial tracking (#2469)', () => {
    beforeEach(() => {
      mockedSubmitSignupForm.mockReset();
      (fetchTrialStatus as jest.Mock).mockReset();
      sessionStorage.clear();
    });

    async function fillValidForm() {
      await fillField(/email/i, 'test@example.com');
      await fillField(/entreprise/i, 'Acme Corp');
      // Formulaire simplifié : plus de rôle, de taille d'équipe, de pays
      // (détecté côté serveur) ni de téléphone — e-mail + entreprise + CGU.
      fireEvent.click(screen.getByRole('checkbox'));
    }

    it('shows the "Suivre l\'etat de mon espace" link on the OTP screen when a provisioning token is returned', async () => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: true,
        message: 'Code de vérification envoyé.',
        data: { provisioning_token: 'a'.repeat(64) },
      });

      renderAtFormStep();
      await fillValidForm();
      submitForm();

      await waitFor(() => {
        expect(screen.getByText(/vérifiez votre email/i)).toBeInTheDocument();
      });
      expect(screen.getByRole('button', { name: /suivre l'état de mon espace/i })).toBeInTheDocument();
    });

    it('reprend le suivi au montage quand un token est déjà en sessionStorage (après rechargement)', async () => {
      sessionStorage.setItem('lp_trial_provisioning_token', 'r'.repeat(64));
      (fetchTrialStatus as jest.Mock).mockResolvedValue({
        success: true,
        data: { status: 'ready', login_url: '/auth/login', password_set: false, access_sent: false },
      });

      render(<SignupForm />);

      await waitFor(() => {
        expect(screen.getByText(/votre espace est prêt/i)).toBeInTheDocument();
      });
      expect(screen.queryByRole('button', { name: /créer mon espace/i })).not.toBeInTheDocument();
    });

    it('affiche un écran d\'échec actionnable et permet de repartir du formulaire', async () => {
      sessionStorage.setItem('lp_trial_provisioning_token', 'f'.repeat(64));
      (fetchTrialStatus as jest.Mock).mockResolvedValue({
        success: true,
        data: { status: 'failed', provisioned_at: null, password_set: false, access_sent: false },
      });

      render(<SignupForm />);

      await waitFor(() => {
        expect(screen.getByText(/création interrompue/i)).toBeInTheDocument();
      });
      expect(screen.getByRole('button', { name: /rafraîchir le statut/i })).toBeInTheDocument();

      fireEvent.click(screen.getByRole('button', { name: /retour/i }));

      expect(screen.getByRole('button', { name: /créer mon espace/i })).toBeInTheDocument();
      expect(sessionStorage.getItem('lp_trial_provisioning_token')).toBeNull();
    });

    it('does not show the tracking link without a provisioning token', async () => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: true,
        message: 'Code de vérification envoyé.',
        data: {},
      });

      renderAtFormStep();
      await fillValidForm();
      submitForm();

      await waitFor(() => {
        expect(screen.getByText(/vérifiez votre email/i)).toBeInTheDocument();
      });
      expect(screen.queryByRole('button', { name: /suivre l'état de mon espace/i })).not.toBeInTheDocument();
    });

    it("guided_trial (status provisioning_sandbox) → tracking direct, jamais d'écran OTP (#6959)", async () => {
      jest.useFakeTimers();
      try {
        mockedSubmitSignupForm.mockResolvedValue({
          success: true,
          provisioned: false,
          message: "Votre demande d'essai est enregistrée. Votre espace est en cours de préparation, vous pouvez suivre son état ci-dessous.",
          data: { status: 'provisioning_sandbox', provisioning_token: 'g'.repeat(64) },
        });
        (fetchTrialStatus as jest.Mock).mockResolvedValue({ success: true, data: { status: 'pending' } });

        renderAtFormStep();
        await fillField(/email/i, 'test@example.com');
        await fillField(/entreprise/i, 'Acme Corp');
        // Formulaire simplifié : e-mail + entreprise + CGU uniquement.
        fireEvent.click(screen.getByRole('checkbox'));
        submitForm();

        // Le flux guidé n'envoie aucun OTP : on doit arriver sur le suivi du
        // provisioning (poll immédiat → « Nous préparons votre espace » (copy #7495)) sans
        // jamais voir l'écran « Verify your email ».
        expect(await screen.findByText(/nous préparons votre espace/i)).toBeInTheDocument();
        expect(screen.queryByText(/vérifiez votre email/i)).not.toBeInTheDocument();
        expect(fetchTrialStatus).toHaveBeenCalled();
      } finally {
        jest.useRealTimers();
      }
    });

    it('polls pending → ready and shows the access link', async () => {
      jest.useFakeTimers();
      const user = userEvent.setup({ advanceTimers: jest.advanceTimersByTime });
      try {
        mockedSubmitSignupForm.mockResolvedValue({
          success: true,
          provisioned: true,
          message: 'Code de vérification envoyé.',
          data: { provisioning_token: 'b'.repeat(64) },
        });
        (fetchTrialStatus as jest.Mock)
          .mockResolvedValueOnce({ success: true, data: { status: 'pending' } })
          .mockResolvedValueOnce({
            success: true,
            data: {
              status: 'ready',
              login_url: 'https://demo.leopardo.app/access?t=123',
              password_set: true,
            },
          });

        renderAtFormStep();
        await fillField(/email/i, 'test@example.com');
        await fillField(/entreprise/i, 'Acme Corp');
        // Formulaire simplifié : plus de sélecteurs rôle / taille / pays.
        // fireEvent (et non user.click) : avec jest.useFakeTimers() actif au
        // milieu de la suite, les clicks userEvent sont intermittemment avalés
        // (désynchronisation pointerup/click par l'avancement des timers) —
        // échec non déterministe sur main. fireEvent est synchrone.
        fireEvent.click(screen.getByRole('checkbox'));
        submitForm();
        await screen.findByText(/vérifiez votre email/i);

        fireEvent.click(screen.getByRole('button', { name: /suivre l'état de mon espace/i }));

        // premier poll immédiat : pending → spinner
        expect(await screen.findByText(/nous préparons votre espace/i)).toBeInTheDocument();

        // second poll après 5 s : ready → lien d'accès
        await act(async () => {
          jest.advanceTimersByTime(5000);
        });
        expect(await screen.findByText(/votre espace est prêt/i)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /accéder à mon espace/i })).toHaveAttribute(
          'href',
          'https://demo.leopardo.app/access?t=123'
        );
      } finally {
        jest.useRealTimers();
      }
    });

    it('propose de définir le mot de passe quand l’espace est prêt sans mot de passe (onboarding sans mailer)', async () => {
      jest.useFakeTimers();
      const user = userEvent.setup({ advanceTimers: jest.advanceTimersByTime });
      try {
        mockedSubmitSignupForm.mockResolvedValue({
          success: true,
          provisioned: true,
          message: 'Code de vérification envoyé.',
          data: { provisioning_token: 'c'.repeat(64) },
        });
        (fetchTrialStatus as jest.Mock)
          .mockResolvedValueOnce({ success: true, data: { status: 'pending' } })
          .mockResolvedValueOnce({
            success: true,
            data: { status: 'ready', login_url: '/auth/login', password_set: false },
          });

        renderAtFormStep();
        await fillValidForm();
        submitForm();
        await screen.findByText(/vérifiez votre email/i);

        await user.click(screen.getByRole('button', { name: /suivre l'état de mon espace/i }));

        await act(async () => {
          jest.advanceTimersByTime(5000);
        });

        expect(await screen.findByText(/votre espace est prêt/i)).toBeInTheDocument();
        // Le prospect choisit lui-même son mot de passe : sans mailer, c'est le
        // seul chemin d'accès possible. Un seul champ depuis #7243 (la double
        // saisie a été retirée du parcours).
        expect(screen.getByLabelText(/^mot de passe$/i)).toBeInTheDocument();
        expect(
          screen.getByRole('button', { name: /définir mon mot de passe/i })
        ).toBeInTheDocument();
        // Tant que le mot de passe n'est pas défini, on ne propose pas un lien
        // de connexion par mot de passe (il mènerait à une impasse).
        expect(screen.queryByRole('link', { name: /accéder à mon espace/i })).toBeNull();
        // #7298 — parcours guidé SANS mailer : ne jamais annoncer un envoi par
        // e-mail (`access_sent` absent/false). Le message s'affichait à tort.
        expect(screen.queryByText(/également été envoyé par email/i)).toBeNull();
      } finally {
        jest.useRealTimers();
      }
    });
  });

  describe('Renvoi du code OTP (#7495)', () => {
    beforeEach(() => {
      mockedSubmitSignupForm.mockReset();
      sessionStorage.clear();
    });

    // Les describes suivants lisent mock.calls[0] sans reset préalable :
    // on nettoie derrière nous pour ne pas polluer leur historique d'appels.
    afterEach(() => {
      mockedSubmitSignupForm.mockReset();
    });

    it('renvoie le code en 1 clic depuis l’écran OTP (rejoue la soumission d’origine)', async () => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: true,
        message: 'Code de vérification envoyé.',
        data: {},
      });

      renderAtFormStep();
      await fillField(/email/i, 'resend@example.com');
      await fillField(/entreprise/i, 'Acme Corp');
      fireEvent.click(screen.getByRole('checkbox'));
      submitForm();

      await waitFor(() => {
        expect(screen.getByText(/vérifiez votre email/i)).toBeInTheDocument();
      });
      expect(mockedSubmitSignupForm).toHaveBeenCalledTimes(1);

      const resendButton = screen.getByRole('button', { name: /renvoyer le code/i });
      fireEvent.click(resendButton);

      await waitFor(() => {
        expect(mockedSubmitSignupForm).toHaveBeenCalledTimes(2);
      });
      // Confirmation honnête + délai anti-spam : le bouton se désactive.
      expect(await screen.findByText(/nouveau code envoyé/i)).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /renvoyer le code/i })).toBeDisabled();
    });

    it('affiche une erreur honnête quand le renvoi échoue', async () => {
      mockedSubmitSignupForm
        .mockResolvedValueOnce({
          success: true,
          provisioned: true,
          message: 'Code de vérification envoyé.',
          data: {},
        })
        .mockResolvedValueOnce({ success: false, message: 'boom' });

      renderAtFormStep();
      await fillField(/email/i, 'resend-fail@example.com');
      await fillField(/entreprise/i, 'Acme Corp');
      fireEvent.click(screen.getByRole('checkbox'));
      submitForm();

      await waitFor(() => {
        expect(screen.getByText(/vérifiez votre email/i)).toBeInTheDocument();
      });

      fireEvent.click(screen.getByRole('button', { name: /renvoyer le code/i }));

      expect(await screen.findByText(/le renvoi n'a pas abouti/i)).toBeInTheDocument();
      // Pas de faux délai : l'échec laisse le bouton réutilisable.
      expect(screen.getByRole('button', { name: /renvoyer le code/i })).toBeEnabled();
    });
  });

  describe('User Interactions', () => {
    it('should submit valid data (incl. country #4476) and move to OTP', async () => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: true,
        message: 'Code de vérification envoyé.',
        data: {},
      });
      renderAtFormStep();
      await fillField(/email/i, 'test@example.com');
      await fillField(/entreprise/i, 'Acme Corp');
      // Formulaire simplifié : e-mail + entreprise + CGU uniquement.
      fireEvent.click(screen.getByRole('checkbox'));
      submitForm();

      await waitFor(() => {
        expect(screen.getByText(/vérifiez votre email/i)).toBeInTheDocument();
      });
      const [payload] = mockedSubmitSignupForm.mock.calls[0] as [Record<string, unknown>, unknown];
      expect(payload).toEqual(
        expect.objectContaining({ email: 'test@example.com', company: 'Acme Corp' })
      );
      // Le créateur du compte EST le fondateur : le rôle est implicite.
      expect(payload.role).toBe('founder');
      // Le pays n'est plus transmis par le formulaire (détecté côté serveur).
      expect(payload.country).toBeUndefined();
    });

    it('should show loading state during submission', async () => {
      renderAtFormStep();
      const submitButton = screen.getByRole('button', { name: /créer mon espace/i });
      
      expect(submitButton).not.toHaveAttribute('disabled');
    });

    // #7542 — critère 1 : chaque jalon émis une seule fois, sur la transition réelle.
    it('émet les jalons du funnel sur les transitions réelles du tunnel (#7542)', async () => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: true,
        message: 'Code de vérification envoyé.',
        data: {},
      });
      renderAtFormStep();

      // Ouverture de /signup → un seul jalon de vue, malgré les re-renders.
      expect(mockedTrackFunnelStep).toHaveBeenCalledTimes(1);
      expect(mockedTrackFunnelStep).toHaveBeenCalledWith(FUNNEL_EVENTS.signupView, {
        page: '/signup',
      });

      await fillField(/email/i, 'test@example.com');
      await fillField(/entreprise/i, 'Acme Corp');
      fireEvent.click(screen.getByRole('checkbox'));
      submitForm();

      await waitFor(() => {
        expect(screen.getByText(/vérifiez votre email/i)).toBeInTheDocument();
      });

      const emitted = mockedTrackFunnelStep.mock.calls.map(([event]) => event);
      // La soumission puis l'écran du code — dans cet ordre, et sans doublon.
      expect(emitted).toEqual([
        FUNNEL_EVENTS.signupView,
        FUNNEL_EVENTS.signupEmailSubmitted,
        FUNNEL_EVENTS.signupOtpSent,
      ]);
    });
  });
});
