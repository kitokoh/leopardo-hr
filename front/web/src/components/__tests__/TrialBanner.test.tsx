import { render, screen } from '@testing-library/react';
import { TrialBanner } from '@/components/TrialBanner';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * Retour propriétaire — dans la barre du haut, la pastille d'essai ne doit plus
 * afficher son décompte en clair (« Il vous reste 14 jours d'essai ») : elle
 * consommait la largeur nécessaire au menu (mesuré : zone de navigation à 0 px
 * à 1440 px). Le message reste exigible pour l'accessibilité (nom accessible +
 * `sr-only`) et découvrable au survol (`title`).
 */

function trialUser(daysFromNow: number): StoredAuthUser {
  const end = new Date();
  end.setHours(0, 0, 0, 0);
  end.setDate(end.getDate() + daysFromNow);

  const subscriptionEnd = [
    end.getFullYear(),
    String(end.getMonth() + 1).padStart(2, '0'),
    String(end.getDate()).padStart(2, '0'),
  ].join('-');

  return {
    id: 1,
    first_name: 'Ahmed',
    last_name: 'Benali',
    role: 'manager',
    manager_role: 'principal',
    language: 'fr',
    company: {
      id: 'company-1',
      name: 'Nouvelle Entreprise SARL',
      status: 'trial',
      subscription_end: subscriptionEnd,
    },
  } as StoredAuthUser;
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

describe('TrialBanner — variante « compact » de la barre du haut', () => {
  it("n'affiche plus le décompte en clair, mais le garde en infobulle et en nom accessible", () => {
    render(<TrialBanner user={trialUser(14)} locale="fr" variant="compact" />);

    const badge = screen.getByTestId('trial-badge');
    const message = 'Il vous reste 14 jours d’essai';

    // Lisible par un lecteur d'écran et au survol…
    expect(badge).toHaveAttribute('title', message);
    expect(badge).toHaveAttribute('aria-label', message);
    // …mais rendu hors flux visuel (plus de largeur consommée dans la barre).
    expect(badge.querySelector('span.sr-only')).toHaveTextContent(message);
    expect(badge.querySelector('span.sr-only')).not.toBeNull();
  });

  it('porte le nombre de jours restants en attribut (contrat des tests e2e)', () => {
    render(<TrialBanner user={trialUser(14)} locale="fr" variant="compact" />);
    expect(screen.getByTestId('trial-badge')).toHaveAttribute('data-trial-days-left', '14');
  });

  it('bascule sur le libellé de fin d’essai quand l’échéance est passée', () => {
    render(<TrialBanner user={trialUser(-2)} locale="fr" variant="compact" />);

    const badge = screen.getByTestId('trial-badge');
    expect(badge).toHaveAttribute('title', 'Votre essai est terminé');
    expect(badge.querySelector('span.sr-only')).toHaveTextContent('Votre essai est terminé');
  });

  it('ne rend rien hors essai (tenant actif) — aucune date inventée côté client', () => {
    const active: StoredAuthUser = { ...trialUser(14) };
    active.company = { ...active.company, status: 'active' };

    const { container } = render(<TrialBanner user={active} locale="fr" variant="compact" />);
    expect(container).toBeEmptyDOMElement();
    expect(screen.queryByTestId('trial-badge')).toBeNull();
  });

  it('la variante « bar » (bandeau pleine largeur) conserve son libellé visible', () => {
    render(<TrialBanner user={trialUser(14)} locale="fr" variant="bar" />);

    // Le bandeau pleine largeur n'a pas la contrainte de place de la barre du
    // haut : son message reste affiché en clair (pas seulement en `sr-only`).
    const message = screen.getByText('Il vous reste 14 jours d’essai');
    expect(message.closest('.sr-only')).toBeNull();
  });
});
