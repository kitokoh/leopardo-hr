import { submitSignupForm } from '../forms';

/**
 * #8454 — métier présélectionné depuis une page pack de la vitrine
 * (`/packs/[vertical]` → `/signup?solution=<code catalogue>`) : le payload
 * d'inscription transmet `solutions: [code]` à l'API (BC-25) pour que la
 * verticale soit provisionnée avec le tenant et que l'entretien de
 * préparation ne repose pas la question « secteur ».
 *
 * Le serveur reste fail-closed (422 sur code inconnu) ; l'allowlist client
 * ignore simplement les codes exotiques.
 */

function goto(url: string): void {
  window.history.pushState({}, '', url);
}

function mockSignupFetch(): jest.Mock {
  const fetchMock = jest.fn().mockResolvedValue({
    json: () => Promise.resolve({ success: true, data: {} }),
  } as unknown as Response);
  global.fetch = fetchMock;
  return fetchMock;
}

function postedBody(fetchMock: jest.Mock): Record<string, unknown> {
  const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
  return JSON.parse(String(init?.body)) as Record<string, unknown>;
}

const BASE_FORM = {
  email: 'patron@resto.dz',
  agreeToTerms: true,
} as Parameters<typeof submitSignupForm>[0];

describe('forms — #8454 métier présélectionné (?solution=)', () => {
  afterEach(() => {
    jest.restoreAllMocks();
    goto('/signup');
  });

  it('transmet solutions:[code] quand l’URL porte ?solution=fuel_station', async () => {
    goto('/signup?solution=fuel_station');
    const fetchMock = mockSignupFetch();

    await submitSignupForm({ ...BASE_FORM }, '/packs/station-service');

    expect(postedBody(fetchMock).solutions).toEqual(['fuel_station']);
  });

  it('normalise le code (casse/espaces) avant envoi', async () => {
    goto('/signup?solution=%20Restaurant%20');
    const fetchMock = mockSignupFetch();

    await submitSignupForm({ ...BASE_FORM }, '/restaurateur');

    expect(postedBody(fetchMock).solutions).toEqual(['restaurant']);
  });

  it('ignore un code hors allowlist (le serveur resterait fail-closed)', async () => {
    goto('/signup?solution=crypto_mining');
    const fetchMock = mockSignupFetch();

    await submitSignupForm({ ...BASE_FORM }, '/signup');

    expect(postedBody(fetchMock).solutions).toBeUndefined();
  });

  it('/signup nu : aucune clé solutions (comportement historique)', async () => {
    goto('/signup');
    const fetchMock = mockSignupFetch();

    await submitSignupForm({ ...BASE_FORM }, '/signup');

    expect(postedBody(fetchMock).solutions).toBeUndefined();
  });
});
