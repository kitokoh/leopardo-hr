/**
 * #8231 (BOS-043) — tests de l'ecran Lea.
 *
 * Ce qui est verifie n'est pas le rendu mais les **transitions d'etat** : ce
 * sont elles qui ont des consequences serveur (activation ou non). Chaque
 * test couvre une issue du parcours qui, si elle regressait, activerait un
 * perimetre faux ou enfermerait l'utilisateur.
 */
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { LeaOnboarding } from '../LeaOnboarding';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
}));

// framer-motion n'apporte rien a la logique testee et ralentit fortement le
// rendu en jsdom : on le neutralise (meme approche que les autres tests de
// modale du projet).
jest.mock('framer-motion', () => {
  const React = require('react');
  // Les composants sont MEMOISES par balise. Sans ce cache, `motion.div`
  // renverrait un type de composant neuf a chaque rendu : React demonterait
  // et remonterait le sous-arbre a chaque frappe, et `userEvent.type`
  // perdrait la saisie (le textarea serait detache entre deux caracteres).
  const cache = new Map<string, unknown>();
  const passthrough = (tag: string) => {
    const cached = cache.get(tag);
    if (cached) return cached;
    const Component = ({ children, ...rest }: Record<string, unknown>) => {
      // Les props d'animation ne sont pas des attributs DOM valides : on les
      // retire pour ne pas polluer le rendu jsdom.
      const {
        initial: _initial,
        animate: _animate,
        exit: _exit,
        transition: _transition,
        variants: _variants,
        ...domProps
      } = rest;
      return React.createElement(tag, domProps, children as React.ReactNode);
    };
    Component.displayName = `motion.${tag}`;
    cache.set(tag, Component);
    return Component;
  };
  return {
    motion: new Proxy({}, { get: (_target, tag: string) => passthrough(tag) }),
    AnimatePresence: ({ children }: { children: React.ReactNode }) => children,
  };
});

const { apiFetch } = jest.requireMock('@/lib/api-client') as {
  apiFetch: jest.Mock;
};

function jsonResponse(body: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  } as Response;
}

const props = () => ({
  locale: 'fr' as const,
  onConfirmed: jest.fn(),
  onUseForm: jest.fn(),
  onDismiss: jest.fn(),
});

beforeEach(() => {
  apiFetch.mockReset();
});

describe('LeaOnboarding', () => {
  it("garde le bouton d'analyse desactive tant que la description est trop courte", async () => {
    const p = props();
    render(<LeaOnboarding {...p} />);

    const analyse = screen.getByRole('button', { name: /analyser/i });
    expect(analyse).toBeDisabled();

    await userEvent.type(screen.getByRole('textbox'), 'agence');
    expect(analyse).toBeDisabled();

    await userEvent.type(screen.getByRole('textbox'), ' de voyages a Lyon');
    await waitFor(() => expect(analyse).toBeEnabled());
  });

  it('affiche le resume editable et confirme avec le plan_token recu', async () => {
    const p = props();
    apiFetch
      .mockResolvedValueOnce(
        jsonResponse({
          data: {
            outcome: 'plan_ready',
            answers: { vertical: 'travel', employees: 12 },
            plan: { solutions: ['travel_agency'], tools: ['crm'] },
            plan_token: 'tok-123',
          },
        }),
      )
      .mockResolvedValueOnce(
        jsonResponse({
          data: { plan: { solutions: ['travel_agency'], tools: ['crm'] } },
        }),
      );

    render(<LeaOnboarding {...p} />);
    await userEvent.type(
      screen.getByRole('textbox'),
      'agence de voyages, deux bureaux, douze personnes',
    );
    await userEvent.click(screen.getByRole('button', { name: /analyser/i }));

    // Le resume s'affiche AVANT toute activation : rien n'est engage ici.
    expect(await screen.findByText('travel_agency')).toBeInTheDocument();
    expect(p.onConfirmed).not.toHaveBeenCalled();

    await userEvent.click(
      screen.getByRole('button', { name: /confirmer et activer/i }),
    );

    await waitFor(() => expect(p.onConfirmed).toHaveBeenCalledTimes(1));
    // Le jeton scelle les reponses affichees : il doit partir tel quel, sinon
    // le serveur refuserait le plan (409) ou activerait un autre perimetre.
    const [, options] = apiFetch.mock.calls[1];
    expect(JSON.parse(options.body as string)).toEqual({
      answers: { vertical: 'travel', employees: 12 },
      plan_token: 'tok-123',
    });
    expect(p.onConfirmed).toHaveBeenCalledWith({
      solutions: ['travel_agency'],
      tools: ['crm'],
    });
  });

  it("bascule sur le formulaire quand le serveur repond fallback (Lea indisponible)", async () => {
    const p = props();
    apiFetch.mockResolvedValueOnce(
      jsonResponse({ data: { outcome: 'fallback', reason_code: 'llm_unavailable' } }),
    );

    render(<LeaOnboarding {...p} />);
    await userEvent.type(
      screen.getByRole('textbox'),
      'je tiens une petite pharmacie de quartier',
    );
    await userEvent.click(screen.getByRole('button', { name: /analyser/i }));

    await waitFor(() => expect(p.onUseForm).toHaveBeenCalled());
    // Un fallback n'est pas une activation : on n'a surtout pas confirme.
    expect(p.onConfirmed).not.toHaveBeenCalled();
  });

  it('bascule sur le formulaire quand les routes sont absentes (flag serveur OFF)', async () => {
    const p = props();
    apiFetch.mockResolvedValueOnce(jsonResponse({}, 404));

    render(<LeaOnboarding {...p} />);
    await userEvent.type(
      screen.getByRole('textbox'),
      'cabinet comptable, huit collaborateurs',
    );
    await userEvent.click(screen.getByRole('button', { name: /analyser/i }));

    await waitFor(() => expect(p.onUseForm).toHaveBeenCalled());
    expect(p.onConfirmed).not.toHaveBeenCalled();
  });

  it('ne boucle pas indefiniment sur les clarifications (2 tours maximum)', async () => {
    const p = props();
    // Trois clarifications d'affilee : le serveur insiste, le client doit
    // rendre la main plutot que de reposer la meme question.
    apiFetch.mockResolvedValue(
      jsonResponse({ data: { outcome: 'clarification', questions: ['Combien de personnes ?'] } }),
    );

    render(<LeaOnboarding {...p} />);
    await userEvent.type(
      screen.getByRole('textbox'),
      'je gere un hotel avec un restaurant',
    );
    await userEvent.click(screen.getByRole('button', { name: /analyser/i }));

    // Tour 1
    await userEvent.type(
      await screen.findByLabelText('Combien de personnes ?'),
      '20',
    );
    await userEvent.click(screen.getByRole('button', { name: /continuer/i }));

    // Tour 2 : le serveur redemande encore. C'est la borne (2 tours) qui doit
    // trancher — pas un 3e aller-retour.
    await userEvent.type(
      await screen.findByLabelText('Combien de personnes ?'),
      '20',
    );
    await userEvent.click(screen.getByRole('button', { name: /continuer/i }));

    // Borne atteinte : bascule formulaire, pas de 3e question.
    await waitFor(() => expect(p.onUseForm).toHaveBeenCalled());
    expect(p.onConfirmed).not.toHaveBeenCalled();
  });

  it('offre la sortie vers le formulaire a chaque etape', async () => {
    const p = props();
    apiFetch.mockResolvedValueOnce(
      jsonResponse({
        data: {
          outcome: 'plan_ready',
          answers: { vertical: 'restaurant' },
          plan: { solutions: ['restaurant'], tools: [] },
          plan_token: 'tok-9',
        },
      }),
    );

    render(<LeaOnboarding {...p} />);
    expect(
      screen.getByRole('button', { name: /utiliser le formulaire/i }),
    ).toBeInTheDocument();

    await userEvent.type(
      screen.getByRole('textbox'),
      'restaurant de quartier, quinze couverts',
    );
    await userEvent.click(screen.getByRole('button', { name: /analyser/i }));

    await screen.findByText('restaurant');
    // La sortie reste disponible APRES analyse : c'est la condition pour que
    // l'assistant ne soit jamais un entonnoir sans issue.
    await userEvent.click(
      screen.getByRole('button', { name: /utiliser le formulaire/i }),
    );
    expect(p.onUseForm).toHaveBeenCalled();
  });

  it('redemande une verification quand le plan est perime (409)', async () => {
    const p = props();
    apiFetch
      .mockResolvedValueOnce(
        jsonResponse({
          data: {
            outcome: 'plan_ready',
            answers: { vertical: 'travel' },
            plan: { solutions: ['travel_agency'], tools: [] },
            plan_token: 'tok-old',
          },
        }),
      )
      .mockResolvedValueOnce(jsonResponse({ message: 'stale' }, 409));

    render(<LeaOnboarding {...p} />);
    await userEvent.type(
      screen.getByRole('textbox'),
      'agence de voyages specialisee groupe',
    );
    await userEvent.click(screen.getByRole('button', { name: /analyser/i }));
    await screen.findByText('travel_agency');
    await userEvent.click(
      screen.getByRole('button', { name: /confirmer et activer/i }),
    );

    // 409 = le plan affiche n'est plus celui des reponses : on ne pretend pas
    // avoir active, on revient a la saisie avec un message explicite.
    expect(await screen.findByRole('alert')).toHaveTextContent(/r[ée]sum[ée] a chang[ée]/i);
    expect(p.onConfirmed).not.toHaveBeenCalled();
  });
});
