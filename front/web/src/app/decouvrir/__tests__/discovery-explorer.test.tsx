import { render, screen } from '@testing-library/react';
import DiscoveryExplorer from '../discovery-explorer';
import type { PublicDiscoveryItem, PublicDiscoveryMeta } from '@/lib/public-discovery-api';

/**
 * SUPERAPP-P0 (#8611) — rendu de la passerelle publique.
 *
 * Vérifie les états obligatoires (résultats, vide, erreur), l'absence de
 * données internes dans le rendu, et que chaque résultat pointe vers la fiche
 * publique verticale fournie par l'API (`item.url`) — jamais une route
 * inventée côté frontend.
 */

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), prefetch: jest.fn() }),
  usePathname: () => '/decouvrir',
  useSearchParams: () => new URLSearchParams(),
}));

jest.mock('@/lib/api-client', () => ({ apiFetch: jest.fn() }));

const meta: PublicDiscoveryMeta = {
  total: 2,
  per_page: 20,
  page: 1,
  last_page: 1,
  types: ['hospitality_property', 'restaurant', 'showcase'],
  radius_km: null,
};

const items: PublicDiscoveryItem[] = [
  {
    type: 'restaurant',
    slug: 'chez-fatima',
    name: 'Chez Fatima',
    category: 'restaurant',
    city: 'Casablanca',
    description: 'Cuisine marocaine',
    url: '/public/restaurants/chez-fatima',
  },
  {
    type: 'showcase',
    slug: 'atelier-bois',
    name: 'Atelier Bois',
    category: 'menuiserie',
    city: 'Rabat',
    description: 'Menuiserie sur mesure',
    url: '/vitrine/atelier-bois',
  },
];

const baseProps = {
  locale: 'fr' as const,
  initialMeta: meta,
  initialError: false,
  initialQuery: { q: '', city: '', type: '', page: 1 },
};

describe('DiscoveryExplorer', () => {
  it('renders aggregated results from several verticals', () => {
    render(<DiscoveryExplorer {...baseProps} initialItems={items} />);

    expect(screen.getByText('Chez Fatima')).toBeInTheDocument();
    expect(screen.getByText('Atelier Bois')).toBeInTheDocument();
  });

  it('links each result to its own vertical public page', () => {
    render(<DiscoveryExplorer {...baseProps} initialItems={items} />);

    const profileLinks = screen.getAllByRole('link', { name: /voir la fiche/i });

    expect(profileLinks[0]).toHaveAttribute('href', '/public/restaurants/chez-fatima');
    expect(profileLinks[1]).toHaveAttribute('href', '/vitrine/atelier-bois');
  });

  it('shows the empty state when nothing matches', () => {
    render(<DiscoveryExplorer {...baseProps} initialItems={[]} />);

    expect(screen.getByText(/aucun professionnel/i)).toBeInTheDocument();
  });

  it('shows the error state with a retry action', () => {
    render(<DiscoveryExplorer {...baseProps} initialItems={[]} initialError />);

    expect(screen.getByText(/impossible de charger/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /réessayer/i })).toBeInTheDocument();
  });

  it('never renders internal identifiers', () => {
    const { container } = render(<DiscoveryExplorer {...baseProps} initialItems={items} />);
    const html = container.innerHTML;

    expect(html).not.toContain('company_id');
    expect(html).not.toContain('latitude');
    expect(html).not.toContain('longitude');
  });

  it('offers a booking action only for verticals that support it', () => {
    render(<DiscoveryExplorer {...baseProps} initialItems={items} />);

    // restaurant → réservation prise en charge ; showcase → aucune action de
    // réservation (jamais de bouton inopérant).
    expect(screen.getAllByRole('link', { name: /réserver/i })).toHaveLength(1);
  });
});
