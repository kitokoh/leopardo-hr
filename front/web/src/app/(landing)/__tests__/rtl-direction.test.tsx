import React from 'react';
import { render } from '@testing-library/react';
import { PREFERRED_LOCALE_KEY } from '@/lib/i18n';

/**
 * Regression test for issue #1008 (PA2-I18N-002): the homepage, /download,
 * and /changelog pages did not forward the `dir="rtl"` attribute for
 * Arabic locale, unlike /signup, /pricing, and /checkout which already
 * applied it via `useVitrineLocale().direction`. This asserts the root
 * element of each of these pages now switches to `dir="rtl"` when the
 * preferred locale is Arabic, and stays `dir="ltr"` for French/English/Turkish.
 */

function setPreferredLocale(locale: string) {
  window.localStorage.setItem(PREFERRED_LOCALE_KEY, locale);
}

describe('Landing pages apply RTL direction for Arabic locale (issue #1008)', () => {
  beforeAll(() => {
    // gsap/ScrollTrigger (pulled in transitively via the vitrine barrel
    // export) requires window.matchMedia, which jsdom does not implement.
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: jest.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: jest.fn(),
        removeListener: jest.fn(),
        addEventListener: jest.fn(),
        removeEventListener: jest.fn(),
        dispatchEvent: jest.fn(),
      })),
    });
  });

  afterEach(() => {
    window.localStorage.clear();
  });

  it('homepage root element switches to dir="rtl" for Arabic', async () => {
    setPreferredLocale('ar');
    const LandingPage = (await import('../page')).default;
    const { container } = render(<LandingPage />);
    expect(container.firstElementChild).toHaveAttribute('dir', 'rtl');
  });

  it('homepage root element stays dir="ltr" for French', async () => {
    setPreferredLocale('fr');
    const LandingPage = (await import('../page')).default;
    const { container } = render(<LandingPage />);
    expect(container.firstElementChild).toHaveAttribute('dir', 'ltr');
  });

  it('/download root element switches to dir="rtl" for Arabic', async () => {
    setPreferredLocale('ar');
    const DownloadPage = (await import('../download/page')).default;
    const { container } = render(<DownloadPage />);
    expect(container.firstElementChild).toHaveAttribute('dir', 'rtl');
  });

  it('/download root element stays dir="ltr" for English', async () => {
    setPreferredLocale('en');
    const DownloadPage = (await import('../download/page')).default;
    const { container } = render(<DownloadPage />);
    expect(container.firstElementChild).toHaveAttribute('dir', 'ltr');
  });

  it('/changelog root element switches to dir="rtl" for Arabic', async () => {
    setPreferredLocale('ar');
    const ChangelogPage = (await import('../changelog/page')).default;
    const { container } = render(<ChangelogPage />);
    expect(container.firstElementChild).toHaveAttribute('dir', 'rtl');
  });

  it('/changelog root element stays dir="ltr" for Turkish', async () => {
    setPreferredLocale('tr');
    const ChangelogPage = (await import('../changelog/page')).default;
    const { container } = render(<ChangelogPage />);
    expect(container.firstElementChild).toHaveAttribute('dir', 'ltr');
  });
});
