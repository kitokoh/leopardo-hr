import React from 'react';
import { render, screen, act } from '@testing-library/react';
import { BrandLoader } from '../BrandLoader';

/**
 * #8497 — le loader de marque doit respecter `prefers-reduced-motion`
 * (Partie 9 : animation legere, accessible, non bloquante).
 */
function mockMatchMedia(matches: boolean) {
  const listeners: Array<(e: MediaQueryListEvent) => void> = [];
  const mql = {
    matches,
    media: '(prefers-reduced-motion: reduce)',
    addEventListener: (_: string, cb: (e: MediaQueryListEvent) => void) => listeners.push(cb),
    removeEventListener: () => {},
  };
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: jest.fn().mockReturnValue(mql),
  });
  return { mql, listeners };
}

describe('BrandLoader (#8497)', () => {
  it('uses the animated logo by default', () => {
    mockMatchMedia(false);
    render(<BrandLoader />);
    const img = screen.getByTestId('brand-loader-image');
    expect(img).toHaveAttribute('src', '/brand/logo-loader.svg');
    expect(img).toHaveAttribute('data-reduced-motion', 'false');
  });

  it('switches to the static asset when reduced motion is requested', () => {
    mockMatchMedia(true);
    render(<BrandLoader />);
    const img = screen.getByTestId('brand-loader-image');
    expect(img).toHaveAttribute('src', '/brand/favicon-premium.svg');
    expect(img).toHaveAttribute('data-reduced-motion', 'true');
  });

  it('reacts to a live change of the media query', () => {
    const { listeners } = mockMatchMedia(false);
    render(<BrandLoader />);
    expect(screen.getByTestId('brand-loader-image')).toHaveAttribute(
      'src',
      '/brand/logo-loader.svg',
    );
    act(() => {
      listeners.forEach((cb) => cb({ matches: true } as MediaQueryListEvent));
    });
    expect(screen.getByTestId('brand-loader-image')).toHaveAttribute(
      'src',
      '/brand/favicon-premium.svg',
    );
  });

  it('exposes an accessible status role and an optional localized label', () => {
    mockMatchMedia(false);
    render(<BrandLoader label="Chargement…" />);
    expect(screen.getByRole('status')).toBeInTheDocument();
    expect(screen.getByText('Chargement…')).toBeInTheDocument();
  });
});
