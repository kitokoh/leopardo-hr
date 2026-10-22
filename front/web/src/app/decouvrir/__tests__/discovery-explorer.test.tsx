import { render, screen, cleanup } from '@testing-library/react';
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
  // #8761 — capacités déclarées par le backend (`supportedActions()`).
  actions: { restaurant: ['booking', 'contact', 'order'], showcase: ['contact'] },
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
  // `cleanup` explicite : sans lui, le DOM du test précédent reste monté et
  // les requêtes `getByRole` peuvent matcher deux cartes à la fois.
  afterEach(() => {
    cleanup();
  });

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

  /**
   * #8761 — les capacités ne sont plus codées en dur côté frontend : elles
   * viennent de `meta.actions`, déclarées par chaque verticale backend.
   */
  it('renders booking and order CTAs from server-declared actions', () => {
    render(
      <DiscoveryExplorer
        {...baseProps}
        initialItems={items}
        initialMeta={{
          ...meta,
          actions: { restaurant: ['booking', 'contact', 'order'], showcase: ['contact'] },
        }}
      />,
    );

    expect(screen.getAllByRole('link', { name: /réserver/i })).toHaveLength(1);
    expect(screen.getAllByRole('link', { name: /^commander$/i })).toHaveLength(1);
  });

  it('hides every business CTA when the server declares no action for the type', () => {
    render(
      <DiscoveryExplorer
        {...baseProps}
        initialItems={items}
        initialMeta={{ ...meta, actions: { restaurant: [], showcase: [] } }}
      />,
    );

    expect(screen.queryByRole('link', { name: /réserver/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /^commander$/i })).not.toBeInTheDocument();
    // Le contact reste disponible : il ne dépend d'aucun service métier.
    // (`getAllBy` : plusieurs cartes exposent un bouton « Contacter ».)
    expect(screen.getAllByRole('button', { name: /contacter/i }).length).toBeGreaterThan(0);
  });

  it('never assumes an action when meta.actions is missing (fail-closed)', () => {
    // Payload sans `actions` (ancien backend / réponse partielle) : aucune
    // capacité n'est supposée disponible.
    const { actions: _omitted, ...metaWithoutActions } = meta;

    render(
      <DiscoveryExplorer {...baseProps} initialItems={items} initialMeta={metaWithoutActions} />,
    );

    expect(screen.queryByRole('link', { name: /réserver/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /^commander$/i })).not.toBeInTheDocument();
  });

  /**
   * P1-02 — les verticales Retail et VTC ont désormais une fiche publique :
   * `item.url` est fourni par l'API (`/boutiques/{slug}`, `/operateurs/{slug}`)
   * et le libellé de type est traduit — jamais le type canonique brut.
   */
  it('links Retail and VTC results to their own public profile page', () => {
    const verticalItems: PublicDiscoveryItem[] = [
      {
        type: 'retail_shop',
        slug: 'epicerie-du-marche',
        name: 'Épicerie du Marché',
        url: '/boutiques/epicerie-du-marche',
      },
      {
        type: 'vtc_operator',
        slug: 'taxi-ville',
        name: 'Taxi Ville',
        url: '/operateurs/taxi-ville',
      },
    ];

    render(<DiscoveryExplorer {...baseProps} initialItems={verticalItems} />);

    const profileLinks = screen.getAllByRole('link', { name: /voir la fiche/i });

    expect(profileLinks[0]).toHaveAttribute('href', '/boutiques/epicerie-du-marche');
    expect(profileLinks[1]).toHaveAttribute('href', '/operateurs/taxi-ville');

    // Libellés lisibles (i18n), pas `retail_shop` / `vtc_operator`.
    expect(screen.getByText('Boutique')).toBeInTheDocument();
    expect(screen.getByText('VTC / taxi')).toBeInTheDocument();
  });
});
