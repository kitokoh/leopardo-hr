import { render, screen } from '@testing-library/react';
import { PackLandingPage } from '../PackLandingPage';
import { PACK_PAGES } from '@/modules/vitrine/data/pack-pages';
import { PACK_SECTORS } from '@/modules/vitrine/data/pack-pages';

/**
 * #9788 — la page vitrine école doit porter la promesse de la mission :
 * l'école, ses collaborateurs, son public et ses élèves. Elle est servie par
 * deux URL (`/business/packs/ecole` et la page dédiée `/business/ecole`), donc
 * le contenu et le CTA sont testés une seule fois, ici.
 */

jest.mock('next/navigation', () => ({
  useSearchParams: () => new URLSearchParams('lang=fr'),
  useRouter: () => ({
    push: jest.fn(),
    replace: jest.fn(),
    prefetch: jest.fn(),
    back: jest.fn(),
    refresh: jest.fn(),
  }),
  usePathname: () => '/business/ecole',
}));

// La scène WebGL n'a pas de sens en jsdom : on la neutralise (le contenu de la
// page est ce qui est testé ici, pas le rendu 3D).
jest.mock('@/modules/vitrine/components/hero/HeroScene3D', () => ({
  HeroScene3D: () => null,
}));

// gsap/ScrollTrigger (importé par le module vitrine) exige `matchMedia`.
beforeAll(() => {
  // La locale vitrine vient du stockage local (pas de l'URL) : on force le
  // français pour comparer au contenu `PACK_PAGES.ecole.fr`.
  window.localStorage.setItem('preferred_locale', 'fr');
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: (query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addListener: jest.fn(),
      removeListener: jest.fn(),
      addEventListener: jest.fn(),
      removeEventListener: jest.fn(),
      dispatchEvent: jest.fn(),
    }),
  });
});

describe('PackLandingPage école (#9788)', () => {
  it('présente les 4 piliers : école, collaborateurs, public, élèves', () => {
    render(<PackLandingPage vertical="ecole" />);

    const copy = PACK_PAGES.ecole.fr;

    expect(screen.getByText(copy.badge)).toBeInTheDocument();

    // Chaque bénéfice du pack école est rendu (au moins une fois : un titre
    // peut aussi apparaître dans la navigation).
    for (const benefit of copy.benefits) {
      expect(screen.getAllByText(benefit.title).length).toBeGreaterThan(0);
    }

    // Les 4 piliers de la mission sont couverts par le contenu.
    const benefits = copy.benefits.map((b) => `${b.title} ${b.text}`).join(' ');
    expect(benefits).toMatch(/école/i);
    expect(benefits).toMatch(/collaborateurs/i);
    expect(benefits).toMatch(/public/i);
    expect(benefits).toMatch(/élèves/i);
  });

  it('déclare le secteur education pour l\'entretien de préparation', () => {
    expect(PACK_SECTORS.ecole).toBe('education');
  });

  it('expose le CTA de création d\'espace école', () => {
    render(<PackLandingPage vertical="ecole" />);

    expect(screen.getAllByText(PACK_PAGES.ecole.fr.ctaPrimary).length).toBeGreaterThan(0);
  });
});
