import React from 'react';
import { render, screen } from '@testing-library/react';
import { GeoBackdrop } from '../GeoBackdrop';
import { HeroSolutionsCTA } from '../HeroSolutionsCTA';
import { SolutionCard } from '../SolutionCard';
import { MAP_OUTLINES } from '../../../data/geo/map-paths';
import { SOLUTIONS, getSolutionDetail } from '../../../data/solution-catalog';

/**
 * Mission Leopardo — tests unitaires des composants ajoutés/refondus :
 * cartes géographiques du fond, CTA sous la constellation, fiche interactive.
 */

jest.mock('next/navigation', () => ({
  useSearchParams: () => new URLSearchParams(''),
}));

function mockMatchMedia(matches: boolean) {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    configurable: true,
    value: jest.fn().mockImplementation((query: string) => ({
      matches: query.includes('prefers-reduced-motion') ? matches : false,
      media: query,
      onchange: null,
      addEventListener: jest.fn(),
      removeEventListener: jest.fn(),
      addListener: jest.fn(),
      removeListener: jest.fn(),
      dispatchEvent: jest.fn(),
    })),
  });
}

describe('GeoBackdrop', () => {
  afterEach(() => {
    // @ts-expect-error — retour à l'état sans matchMedia (setup jsdom)
    delete window.matchMedia;
  });

  it('est décoratif : aria-hidden, sans texte, sans interaction', () => {
    const { container } = render(<GeoBackdrop />);
    const root = container.firstElementChild as HTMLElement;
    expect(root).toHaveAttribute('aria-hidden', 'true');
    expect(root.className).toContain('pointer-events-none');
    expect(container.textContent).toBe('');
  });

  it('n’affiche que la première carte quand reduce est demandé', () => {
    mockMatchMedia(true);
    const { container } = render(<GeoBackdrop />);
    // Une seule couche rendue (la France) : pas de décor animé inutile.
    expect(container.querySelectorAll('svg')).toHaveLength(1);
    expect(container.querySelector('path')).toHaveAttribute('d', MAP_OUTLINES.france.d);
  });

  it('rend les 5 cartes (France → Afrique) quand le mouvement est permis', () => {
    mockMatchMedia(false);
    const { container } = render(<GeoBackdrop />);
    const svgs = container.querySelectorAll('svg');
    expect(svgs).toHaveLength(5);
    // La première carte est la France (continuité avec l'existant).
    expect(svgs[0].querySelector('path')).toHaveAttribute('d', MAP_OUTLINES.france.d);
    expect(svgs[4].querySelector('path')).toHaveAttribute('d', MAP_OUTLINES.africa.d);
    // La couche active (France) est visible, les autres en opacité nulle.
    expect(svgs[0].className.baseVal).toContain('opacity-[0.22]');
    expect(svgs[1].className.baseVal).toContain('opacity-0');
  });

  it('reste sain si matchMedia est absent (jsdom, très vieux navigateurs)', () => {
    // @ts-expect-error — simule l'absence de l'API
    delete window.matchMedia;
    const { container } = render(<GeoBackdrop />);
    expect(container.querySelectorAll('svg')).toHaveLength(1);
  });
});

describe('HeroSolutionsCTA', () => {
  it('renders the primary CTA toward the packs hub', () => {
    render(<HeroSolutionsCTA />);
    const cta = screen.getByTestId('hero-solutions-cta');
    expect(cta).toBeTruthy();
    const packsLink = cta.querySelector('a[href="/packs"]');
    expect(packsLink).not.toBeNull();
    // Second CTA : démarrage de l'onboarding.
    expect(cta.querySelector('a[href="/signup"]')).not.toBeNull();
  });
});

describe('SolutionCard', () => {
  it('affiche le détail de la solution et ses deux actions', () => {
    const detail = getSolutionDetail('travel', 'fr');
    expect(detail).not.toBeNull();
    render(<SolutionCard solutionId="travel" locale="fr" onClose={jest.fn()} />);
    const dialog = screen.getByRole('dialog');
    expect(dialog).toHaveAttribute('aria-modal', 'true');
    // Titre lié au dialogue (accessibilité).
    expect(dialog.getAttribute('aria-labelledby')).toBeTruthy();
    // CTA principal vers la page de la solution.
    expect(dialog.querySelector(`a[href="${detail!.href}"]`)).not.toBeNull();
    expect(dialog.querySelector('a[href="/signup"]')).not.toBeNull();
  });

  it('reprend le contenu du catalogue (aucune donnée dupliquée)', () => {
    const detail = getSolutionDetail('fuel', 'fr')!;
    render(<SolutionCard solutionId="fuel" locale="fr" onClose={jest.fn()} />);
    const dialog = screen.getByRole('dialog');
    expect(dialog.textContent).toContain(detail.name);
    detail.features.forEach((feature) => {
      expect(dialog.textContent).toContain(feature);
    });
  });

  it('se ferme avec Échap', () => {
    const onClose = jest.fn();
    render(<SolutionCard solutionId="education" locale="fr" onClose={onClose} />);
    // La fiche écoute sur `document` (comme l'implémentation).
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(onClose).toHaveBeenCalled();
  });

  it('ne rend rien pour un identifiant inconnu (fail-closed)', () => {
    const { container } = render(
      // @ts-expect-error — identifiant volontairement invalide
      <SolutionCard solutionId="inexistant" locale="fr" onClose={jest.fn()} />,
    );
    expect(container.firstChild).toBeNull();
    expect(screen.queryByRole('dialog')).toBeNull();
  });
});

describe('SOLUTIONS (catalogue de la constellation)', () => {
  it('n’expose que des solutions avec un détail résolvable', () => {
    SOLUTIONS.forEach((solution) => {
      expect(getSolutionDetail(solution.id, 'fr')).not.toBeNull();
      expect(solution.href.startsWith('/')).toBe(true);
    });
  });

  it('ne contient aucune entrée redondante avec le menu (Contact, Pricing…)', () => {
    const hrefs = SOLUTIONS.map((solution) => solution.href);
    expect(hrefs).not.toContain('/contact');
    expect(hrefs).not.toContain('/pricing');
    expect(hrefs).not.toContain('/careers');
    expect(hrefs).not.toContain('/alternatives');
    expect(hrefs).not.toContain('/demo');
  });

  it('propose les environnements métier attendus', () => {
    const labels = SOLUTIONS.map((solution) => getSolutionDetail(solution.id, 'fr')!.name);
    expect(labels.some((label) => label.includes('Restaurant'))).toBe(true);
    expect(labels.some((label) => label.includes('Travel'))).toBe(true);
    expect(labels.some((label) => label.includes('Trade'))).toBe(true);
    expect(labels.some((label) => label.includes('Fuel') || label.includes('Station'))).toBe(true);
    expect(labels.some((label) => label.includes('Education') || label.includes('cole'))).toBe(true);
  });
});
