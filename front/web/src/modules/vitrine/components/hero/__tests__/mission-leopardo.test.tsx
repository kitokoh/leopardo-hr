import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { GeoBackdrop } from '../GeoBackdrop';
import { HeroSolutionsCTA } from '../HeroSolutionsCTA';
import { SolutionCard } from '../SolutionCard';
import { MAP_OUTLINES } from '../../../data/geo/map-paths';
import {
  SOLUTIONS,
  SOLUTION_CARD_COPY,
  getSolutionDetail,
} from '../../../data/solution-catalog';
import { PACK_PAGES, PACKS_HUB_COPY } from '../../../data/pack-pages';

/**
 * Mission Leopardo — tests unitaires des composants de la constellation.
 *
 * Couvre les retours utilisateur de la vague 5 :
 *  · un SEUL CTA sous la constellation, sans titre/sous-titre/réassurance ;
 *  · fiche solution rendue dans un PORTAIL, fermable (X, fond, Échap) ;
 *  · cartes géo sur un demi-cercle DERRIÈRE la constellation (continents
 *    d'abord, puis pays ; intérieur vide) ;
 *  · disparition du hint « Modules to explore » et des mentions
 *    « Free pack / No credit card ».
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

describe('GeoBackdrop — demi-cercle derrière la constellation', () => {
  it('est décoratif : aria-hidden, sans texte, sans interaction', () => {
    const { container } = render(<GeoBackdrop />);
    const root = container.firstElementChild as HTMLElement;
    expect(root).toHaveAttribute('aria-hidden', 'true');
    expect(root.className).toContain('pointer-events-none');
    expect(root.className).toContain('z-[1]');
    expect(container.textContent).toBe('');
  });

  it('vit sur le calque INFÉRIEUR à la constellation (z-[1] < z-10)', () => {
    const { container } = render(<GeoBackdrop />);
    const root = container.firstElementChild as HTMLElement;
    // Décor derrière la scène WebGL : jamais au milieu ni devant les étoiles.
    expect(root.className).toContain('z-[1]');
    expect(root.className).not.toContain('z-10');
  });

  it('rend une carte par entrée de l’arc, dans l’ordre continents → pays', () => {
    const { container } = render(<GeoBackdrop />);
    const cards = container.querySelectorAll('.geo-arc-card');
    // Continents (Afrique, Europe, Asie) puis 8 pays.
    expect(cards).toHaveLength(11);
    const firstPath = cards[0].querySelector('path');
    const lastPath = cards[10].querySelector('path');
    // L'Afrique ouvre le cycle, l'Arabie saoudite le ferme.
    expect(firstPath).toHaveAttribute('d', MAP_OUTLINES.africa.d);
    expect(lastPath).toHaveAttribute('d', MAP_OUTLINES.saudi.d);
    // Les trois premiers sont bien des CONTINENTS.
    expect(cards[1].querySelector('path')).toHaveAttribute('d', MAP_OUTLINES.europe.d);
    expect(cards[2].querySelector('path')).toHaveAttribute('d', MAP_OUTLINES.asia.d);
    // Puis les pays.
    expect(cards[3].querySelector('path')).toHaveAttribute('d', MAP_OUTLINES.france.d);
  });

  it('chaque carte a l’intérieur VIDE : fond + contour, aucun texte', () => {
    const { container } = render(<GeoBackdrop />);
    const cards = container.querySelectorAll('.geo-arc-card');
    cards.forEach((card) => {
      // Exactement deux <path> : le fond (fill) et le contour (stroke).
      expect(card.querySelectorAll('path')).toHaveLength(2);
      expect(card.textContent).toBe('');
      // Aucun élément de contenu qui « remplirait » la carte.
      expect(card.querySelectorAll('text, image, circle, rect, line')).toHaveLength(0);
    });
  });

  it('le décalage d’animation est réparti sur l’arc (une carte à la fois)', () => {
    const { container } = render(<GeoBackdrop />);
    const cards = Array.from(container.querySelectorAll('.geo-arc-card')) as HTMLElement[];
    const delays = cards.map((card) => card.style.animationDelay);
    // Aucune carte ne partage le départ d'une autre : pas d'apparition groupée.
    expect(new Set(delays).size).toBe(delays.length);
    expect(delays.every((delay) => delay.endsWith('s'))).toBe(true);
  });

  it('n’affiche qu’une carte statique quand reduce est demandé (CSS)', () => {
    // L'état « mouvement réduit » est porté par la media query CSS
    // (`prefers-reduced-motion: reduce` → `:not(:first-child) { display:none }`) ;
    // on vérifie ici que la structure attendue par cette règle existe.
    const { container } = render(<GeoBackdrop />);
    const cards = container.querySelectorAll('.geo-arc-card');
    expect(cards.length).toBeGreaterThan(1);
    expect((cards[0] as HTMLElement).style.animationDelay).toBe('0s');
  });
});

describe('HeroSolutionsCTA — un SEUL bouton, sous la constellation', () => {
  it('rend un unique lien, vers le hub /packs', () => {
    render(<HeroSolutionsCTA />);
    const cta = screen.getByTestId('hero-solutions-cta');
    const links = cta.querySelectorAll('a');
    expect(links).toHaveLength(1);
    expect(links[0]).toHaveAttribute('href', '/packs');
  });

  it('n’expose plus de second bouton ni de réassurance commerciale', () => {
    render(<HeroSolutionsCTA />);
    const cta = screen.getByTestId('hero-solutions-cta');
    expect(cta.querySelector('a[href="/signup"]')).toBeNull();
    const text = cta.textContent ?? '';
    expect(text).not.toMatch(/free pack/i);
    expect(text).not.toMatch(/no credit card/i);
    expect(text).not.toMatch(/pack offert/i);
    expect(text).not.toMatch(/sans carte bancaire/i);
  });

  it('n’affiche plus de titre ni de sous-titre au-dessus du bouton', () => {
    render(<HeroSolutionsCTA />);
    const cta = screen.getByTestId('hero-solutions-cta');
    expect(cta.querySelector('h2, h3, p')).toBeNull();
  });

  it('le libellé vient du catalogue, dans les quatre locales', () => {
    (['fr', 'en', 'tr', 'ar'] as const).forEach((locale) => {
      expect(SOLUTION_CARD_COPY[locale].ctaPrimary.length).toBeGreaterThan(0);
      // Les clés retirées ne doivent plus exister dans le contrat de copie.
      expect(SOLUTION_CARD_COPY[locale]).not.toHaveProperty('ctaTitle');
      expect(SOLUTION_CARD_COPY[locale]).not.toHaveProperty('ctaSubtitle');
      expect(SOLUTION_CARD_COPY[locale]).not.toHaveProperty('ctaReassurance');
      expect(SOLUTION_CARD_COPY[locale]).not.toHaveProperty('ctaSecondary');
    });
  });
});

describe('SolutionCard — portail et fermeture fiable', () => {
  it('affiche le détail de la solution et ses deux actions', () => {
    const detail = getSolutionDetail('travel', 'fr');
    expect(detail).not.toBeNull();
    render(<SolutionCard solutionId="travel" locale="fr" onClose={jest.fn()} />);
    const dialog = screen.getByRole('dialog');
    expect(dialog).toHaveAttribute('aria-modal', 'true');
    expect(dialog.getAttribute('aria-labelledby')).toBeTruthy();
    expect(dialog.querySelector(`a[href="${detail!.href}"]`)).not.toBeNull();
    expect(dialog.querySelector('a[href="/signup"]')).not.toBeNull();
  });

  it('est rendu dans document.body (portail), hors de la pile du héros', () => {
    const { container } = render(
      <div className="relative z-10 isolate">
        <SolutionCard solutionId="restaurant" locale="fr" onClose={jest.fn()} />
      </div>,
    );
    // Rien dans le conteneur React : le dialogue a quitté l'arbre du héros.
    expect(container.querySelector('[role="dialog"]')).toBeNull();
    const dialog = document.body.querySelector('[role="dialog"]');
    expect(dialog).not.toBeNull();
    // Son ancêtre direct est le body, avec un plan au-dessus de la navbar (z-50).
    const overlay = dialog!.parentElement as HTMLElement;
    expect(overlay.className).toContain('fixed');
    expect(overlay.className).toContain('z-[100]');
  });

  it('se ferme avec le bouton X', () => {
    const onClose = jest.fn();
    render(<SolutionCard solutionId="restaurant" locale="fr" onClose={onClose} />);
    const dialog = screen.getByRole('dialog');
    const closeButton = dialog.querySelector(
      `button[aria-label="${SOLUTION_CARD_COPY.fr.close}"]`,
    );
    expect(closeButton).not.toBeNull();
    fireEvent.click(closeButton!);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('se ferme au clic sur l’arrière-plan', () => {
    const onClose = jest.fn();
    render(<SolutionCard solutionId="restaurant" locale="fr" onClose={onClose} />);
    const dialog = screen.getByRole('dialog');
    const overlay = dialog.parentElement as HTMLElement;
    const backdrop = overlay.querySelector('button[tabindex="-1"]');
    expect(backdrop).not.toBeNull();
    fireEvent.click(backdrop!);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('se ferme avec Échap', () => {
    const onClose = jest.fn();
    render(<SolutionCard solutionId="education" locale="fr" onClose={onClose} />);
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(onClose).toHaveBeenCalled();
  });

  it('piège le focus : Tab depuis le dernier élément revient au premier', () => {
    render(<SolutionCard solutionId="restaurant" locale="fr" onClose={jest.fn()} />);
    const dialog = screen.getByRole('dialog');
    const focusables = Array.from(
      dialog.querySelectorAll<HTMLElement>(
        'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])',
      ),
    );
    expect(focusables.length).toBeGreaterThan(1);
    const last = focusables[focusables.length - 1];
    last.focus();
    expect(document.activeElement).toBe(last);
    fireEvent.keyDown(document, { key: 'Tab' });
    expect(document.activeElement).toBe(focusables[0]);
  });

  it('Shift+Tab depuis le premier élément va au dernier', () => {
    render(<SolutionCard solutionId="restaurant" locale="fr" onClose={jest.fn()} />);
    const dialog = screen.getByRole('dialog');
    const focusables = Array.from(
      dialog.querySelectorAll<HTMLElement>(
        'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])',
      ),
    );
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    first.focus();
    fireEvent.keyDown(document, { key: 'Tab', shiftKey: true });
    expect(document.activeElement).toBe(last);
  });

  it('restitue le focus à l’élément d’origine à la fermeture', () => {
    const star = document.createElement('button');
    star.textContent = 'étoile';
    document.body.appendChild(star);
    star.focus();
    const { unmount } = render(
      <SolutionCard solutionId="restaurant" locale="fr" onClose={jest.fn()} />,
    );
    unmount();
    expect(document.activeElement).toBe(star);
    star.remove();
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
    expect(labels.some((label) => label.includes('Fuel') || label.includes('Station'))).toBe(
      true,
    );
    expect(labels.some((label) => label.includes('Education') || label.includes('cole'))).toBe(
      true,
    );
  });
});

/**
 * Garde anti-sur-promesse (MISSION LEOPARDO BUSINESS OS — TRAVEL OS).
 *
 * Le module TravelAgency ne porte PAS de notion de commission par agent : le
 * modèle `Commission` appartient au programme partenaire (module Growth), relié
 * à `payments.partner_id`, sans aucun lien avec les ventes de l'agence. Aucune
 * surface publique ne doit donc annoncer ce que le produit ne fait pas.
 */
describe('sur-promesse — Travel OS', () => {
  const SOLUTIONS_IDS = SOLUTIONS.map((solution) => solution.id);

  it('aucune surface ne promet de commission d’agence (a fortiori par agent)', () => {
    const surfaces = [
      ...SOLUTIONS_IDS.map((id) => getSolutionDetail(id, 'fr')?.description ?? ''),
      ...SOLUTIONS_IDS.flatMap((id) => getSolutionDetail(id, 'fr')?.features ?? []),
      ...PACK_PAGES['agence-de-voyage'].fr.included,
      ...PACK_PAGES['agence-de-voyage'].fr.benefits.map(
        (benefit) => `${benefit.title} ${benefit.text}`,
      ),
      PACK_PAGES['agence-de-voyage'].fr.subtitle,
      ...PACKS_HUB_COPY.fr.cards.map((card) => card.line),
    ].join('\n');

    expect(surfaces.toLowerCase()).not.toContain('commission');
  });

  it('la fiche Travel OS reprend les capacités réelles du module (manifeste travelagency)', () => {
    const detail = getSolutionDetail('travel', 'fr')!;
    const text = `${detail.description} ${detail.tagline}`;
    expect(text.toLowerCase()).toContain('billetterie');
    expect(text.toLowerCase()).toMatch(/guichet|en ligne/);
    expect(text.toLowerCase()).toMatch(/routes|trajets/);
    expect(detail.features.length).toBeGreaterThan(0);
  });

  it('la fiche Travel OS expose des profils issus des rôles métier du manifeste', () => {
    const detail = getSolutionDetail('travel', 'fr')!;
    expect(detail.users).toContain('Guichetier');
    expect(detail.users).toContain('Contrôleur');
    expect(detail.users).toContain('Chauffeur');
    expect(detail.users).toContain('Chef de bureau');
  });
});
