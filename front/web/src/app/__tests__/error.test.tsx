import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * #8497 (I-10) — la frontière d'erreur globale doit être localisée (fr/en/tr/ar)
 * et proposer des sorties utiles (réessayer, accueil, support).
 *
 * `useVitrineLocale` résout la locale après montage depuis `navigator.language`
 * (jsdom = en-US) : on le mocke pour tester chaque langue de façon déterministe.
 */
let mockLocale = 'fr';
jest.mock('@/modules/vitrine/lib/vitrine-locale', () => ({
  useVitrineLocale: () => ({ locale: mockLocale, direction: 'ltr' }),
}));

import GlobalError from '../error';

function renderError(locale: string, reset = jest.fn()) {
  mockLocale = locale;
  render(<GlobalError error={Object.assign(new Error('boom'), { digest: 'abc123' })} reset={reset} />);
  return reset;
}

describe('GlobalError (i18n)', () => {
  it('renders the French copy from the shared catalog', () => {
    renderError('fr');
    expect(screen.getByRole('heading', { name: "Quelque chose s'est mal passé" })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Réessayer' })).toBeInTheDocument();
  });

  it('renders the English copy for en', () => {
    renderError('en');
    expect(screen.getByRole('heading', { name: 'Something went wrong' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Try again' })).toBeInTheDocument();
  });

  it('renders the Turkish copy for tr', () => {
    renderError('tr');
    expect(screen.getByRole('heading', { name: 'Bir şeyler ters gitti' })).toBeInTheDocument();
  });

  it('renders the Arabic copy for ar', () => {
    renderError('ar');
    expect(screen.getByRole('heading', { name: 'حدث خطأ ما' })).toBeInTheDocument();
  });

  it('calls reset when the retry button is clicked', async () => {
    const reset = renderError('fr');
    await userEvent.click(screen.getByRole('button', { name: 'Réessayer' }));
    expect(reset).toHaveBeenCalledTimes(1);
  });

  it('exposes a support link and a home link', () => {
    renderError('fr');
    expect(screen.getByRole('link', { name: "Retour à l'accueil" })).toHaveAttribute('href', '/');
    expect(screen.getByRole('link', { name: 'Contacter le support' })).toHaveAttribute('href', '/contact');
  });
});
