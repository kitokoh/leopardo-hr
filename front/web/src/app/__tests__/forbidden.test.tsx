import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * BOS-B-02 — la page 403 doit être localisée (fr/en/tr/ar), ne rien fuiter de
 * technique (ni identifiant de permission, ni trace) et proposer des sorties
 * utiles (accueil, demande d'accès).
 *
 * `forbidden.tsx` est un Server Component asynchrone qui lit la locale via
 * `headers()` : on mocke `next/headers` pour piloter `x-vitrine-lang` de façon
 * déterministe (même mécanique que `not-found.tsx`, #7664).
 */
let mockLang = 'fr';
jest.mock('next/headers', () => ({
  headers: jest.fn(async () => ({
    get: (name: string) => (name === 'x-vitrine-lang' ? mockLang : null),
  })),
}));

import Forbidden, { generateMetadata } from '../forbidden';

async function renderForbidden(locale: string) {
  mockLang = locale;
  const ui = await Forbidden();
  render(ui);
}

describe('Forbidden (403, i18n)', () => {
  it('renders the French copy from the shared catalog', async () => {
    await renderForbidden('fr');
    expect(screen.getByRole('heading', { name: 'Acces refuse' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: "Retour a l'accueil" })).toHaveAttribute('href', '/');
    expect(screen.getByRole('link', { name: "Demander l'acces" })).toHaveAttribute('href', '/contact');
  });

  it('renders the English copy for en', async () => {
    await renderForbidden('en');
    expect(screen.getByRole('heading', { name: 'Access denied' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Request access' })).toBeInTheDocument();
  });

  it('renders the Turkish copy for tr', async () => {
    await renderForbidden('tr');
    expect(screen.getByRole('heading', { name: 'Erisim reddedildi' })).toBeInTheDocument();
  });

  it('renders the Arabic copy for ar', async () => {
    await renderForbidden('ar');
    expect(screen.getByRole('heading', { name: 'تم رفض الوصول' })).toBeInTheDocument();
  });

  it('exposes a localized mascot alt text (no hardcoded string)', async () => {
    await renderForbidden('en');
    expect(screen.getByAltText(/access denied/i)).toBeInTheDocument();
  });

  it('leaks no technical detail (no permission id, no stack, no route name)', async () => {
    await renderForbidden('fr');
    const text = document.body.textContent ?? '';
    expect(text).not.toMatch(/permission/i);
    expect(text).not.toMatch(/stack|trace|SQL|Exception/i);
    expect(text).not.toMatch(/\/api\//);
  });

  it('marks the page noindex via generateMetadata', async () => {
    mockLang = 'fr';
    const meta = await generateMetadata();
    expect(meta.robots).toEqual({ index: false, follow: false });
    expect(meta.title).toBe('Acces refuse');
  });
});
