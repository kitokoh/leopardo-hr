import { render, screen, waitFor } from '@testing-library/react';
import MonLogicielPage from '../page';
import { storeAuthSession, clearAuthSession } from '@/lib/auth-session';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * #8447 — page « Mon Logiciel ».
 *
 * Verrouillé ici : le hub ne montre que les apps des solutions ACTIVÉES du
 * tenant (critère d'acceptation de l'issue), les cibles web réelles sont
 * cliquables, et une app sans canal de distribution n'affiche JAMAIS un faux
 * bouton « Installer » (état « Bientôt disponible » désactivé — règle
 * d'honnêteté #6697).
 */

// La constellation 3D (three.js/WebGL) n'a pas de rendu sous jsdom : on la
// remplace par un stub — le test porte sur le filtrage et les actions, pas
// sur la scène WebGL (protégée par ailleurs par son try/catch d'amorçage).
jest.mock('@/components/software/SoftwareConstellation3D', () => ({
  __esModule: true,
  default: () => <div data-testid="constellation-stub" />,
}));

// `qrcode` exige un canvas navigateur (absent sous jsdom) — même repli que
// le test cagnottes existant.
jest.mock('qrcode', () => ({
  toDataURL: jest.fn().mockResolvedValue('data:image/png;base64,qr'),
}));

function session(features: Record<string, boolean>): StoredAuthUser {
  return {
    role: 'manager',
    language: 'fr',
    company: { features },
  } as unknown as StoredAuthUser;
}

beforeEach(() => {
  window.localStorage.clear();
});

afterEach(() => {
  clearAuthSession();
});

describe('MonLogicielPage (#8447)', () => {
  it('un restaurateur voit ses apps métier avec leurs cibles web cliquables', async () => {
    storeAuthSession(null, session({ restaurantmanager: true, dashboard: true }));

    render(<MonLogicielPage />);

    expect(await screen.findByText('Caisse Restaurant (POS)')).toBeInTheDocument();
    expect(screen.getByText('Écran Cuisine (KDS)')).toBeInTheDocument();
    expect(screen.getByText('Kiosque libre-service')).toBeInTheDocument();

    // Les cibles web réelles pointent vers les surfaces du portail.
    const links = screen.getAllByRole('link', { name: /Installer/i });
    const hrefs = links.map((link) => link.getAttribute('href'));
    expect(hrefs).toContain('/restaurant/pos');
    expect(hrefs).toContain('/restaurant/kitchen');
    expect(hrefs).toContain('/kiosk');

    // Jamais d'apps d'une autre verticale.
    expect(screen.queryByText('Console Station-service')).not.toBeInTheDocument();
  });

  it('une app sans canal de distribution affiche « Bientôt disponible », jamais de lien mort', async () => {
    storeAuthSession(null, session({ restaurantmanager: true, dashboard: true }));

    render(<MonLogicielPage />);

    // App Serveur : déclarée `soon` — aucun href, aucun faux bouton.
    expect(await screen.findByText('App Serveur')).toBeInTheDocument();
    const soonBadges = screen.getAllByText('Bientôt disponible');
    expect(soonBadges.length).toBeGreaterThan(0);
    for (const badge of soonBadges) {
      expect(badge.closest('a')).toBeNull();
    }
  });

  it('un tenant sans verticale ne voit aucune app métier', async () => {
    storeAuthSession(null, session({ dashboard: true }));

    render(<MonLogicielPage />);

    expect(await screen.findByText('Leopardo Manager')).toBeInTheDocument();
    expect(screen.queryByText('Caisse Restaurant (POS)')).not.toBeInTheDocument();
    expect(screen.queryByText('Console Station-service')).not.toBeInTheDocument();
  });

  it('le QR d’installation s’ouvre pour une cible cliquable', async () => {
    storeAuthSession(null, session({ restaurantmanager: true, dashboard: true }));

    render(<MonLogicielPage />);

    await screen.findByText('Caisse Restaurant (POS)');
    const qrButtons = screen.getAllByRole('button', { name: /QR code d’installation/i });
    expect(qrButtons.length).toBeGreaterThan(0);

    qrButtons[0].click();

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });
});
