import {
  MAX_QUANTITY,
  addItem,
  cartItemFromProduct,
  clampQuantity,
  countItems,
  groupBySeller,
  removeItem,
  removeSeller,
  sanitizeCartItem,
  setQuantity,
  subtotalOf,
  type CartItem,
} from '../retail/domain/cart';
import { displayProductRating, formatPrice, isPurchasable, type Product } from '../retail/domain/product';
import {
  isTerminal,
  normalizeTimeline,
  reachedSteps,
  sequenceIndex,
} from '../retail/domain/order';

/**
 * #9627 — domaine Retail repris de `front/marketplace`.
 *
 * Ces tests verrouillent les règles qui étaient enfouies dans l'app autonome
 * (mêlées à `localStorage` et à `window`) et qui décident de ce que le client
 * voit : prix absent, produit non commandable, regroupement par vendeur,
 * timeline d'une commande annulée.
 */

function product(overrides: Partial<Product> = {}): Product {
  return {
    id: 1,
    name: 'Café',
    description: null,
    priceMinor: 1250,
    currency: 'EUR',
    imageUrl: null,
    seller: { slug: 'epicerie', name: 'Épicerie', city: 'Lyon' },
    available: true,
    rating: null,
    ratingCount: 0,
    ...overrides,
  };
}

describe('prix et disponibilité (#9627)', () => {
  it('formate un prix en unité mineure', () => {
    expect(formatPrice(1250, 'EUR', 'fr-FR')).toContain('12,50');
  });

  it('n’affiche jamais « 0 € » pour un prix inconnu', () => {
    expect(formatPrice(null, 'EUR')).toBeNull();
    expect(formatPrice(-1, 'EUR')).toBeNull();
  });

  it('retombe sur un montant lisible pour une devise inconnue d’Intl', () => {
    // `EURO` n'est pas un code ISO 4217 à 3 lettres : `Intl.NumberFormat` lève
    // un RangeError. Le repli doit rendre un montant, pas casser la page.
    expect(formatPrice(1000, 'EURO')).toContain('10.00');
  });

  it('un produit indisponible n’est pas commandable', () => {
    expect(isPurchasable(product({ available: false }))).toBe(false);
    expect(isPurchasable(product())).toBe(true);
  });

  it('une note sans avis ne s’affiche pas', () => {
    expect(displayProductRating(null, 0)).toBeNull();
    expect(displayProductRating(4.26, 3)).toBe(4.3);
  });
});

describe('panier (#9627)', () => {
  it('borne une quantité dans [1, MAX]', () => {
    expect(clampQuantity(0)).toBe(1);
    expect(clampQuantity(-5)).toBe(1);
    expect(clampQuantity(MAX_QUANTITY + 10)).toBe(MAX_QUANTITY);
    expect(clampQuantity(Number.NaN)).toBe(1);
  });

  it('cumule la quantité d’un produit déjà présent au lieu de dupliquer la ligne', () => {
    const first = addItem([], product(), 2);
    const second = addItem(first, product(), 3);

    expect(second).toHaveLength(1);
    expect(second[0].quantity).toBe(5);
  });

  it('une quantité nulle retire la ligne', () => {
    const items = addItem([], product(), 2);

    expect(setQuantity(items, 1, 0)).toHaveLength(0);
  });

  it('groupe par vendeur et calcule les sous-totaux', () => {
    const items: CartItem[] = [
      cartItemFromProduct(product({ id: 1, priceMinor: 1000 }), 2),
      cartItemFromProduct(
        product({ id: 2, priceMinor: 500, seller: { slug: 'autre', name: 'Autre', city: null } }),
        1,
      ),
      cartItemFromProduct(product({ id: 3, priceMinor: 250 }), 4),
    ];

    const groups = groupBySeller(items);

    expect(groups).toHaveLength(2);
    expect(groups[0].seller.slug).toBe('epicerie');
    expect(groups[0].subtotalMinor).toBe(1000 * 2 + 250 * 4);
    expect(groups[1].subtotalMinor).toBe(500);
  });

  it('compte les articles et le sous-total global', () => {
    const items = addItem(addItem([], product({ id: 1 }), 2), product({ id: 2 }), 3);

    expect(countItems(items)).toBe(5);
    expect(subtotalOf(items)).toBe(1250 * 5);
  });

  it('retire une ligne et tout un vendeur', () => {
    const items = addItem([], product(), 1);

    expect(removeItem(items, 1)).toHaveLength(0);
    expect(removeSeller(items, 'epicerie')).toHaveLength(0);
    expect(removeSeller(items, 'inconnu')).toHaveLength(1);
  });

  it('écarte une ligne corrompue lue depuis le stockage, sans la réparer', () => {
    expect(sanitizeCartItem(null)).toBeNull();
    expect(sanitizeCartItem({ productId: 'x' })).toBeNull();
    expect(sanitizeCartItem({ productId: 1, name: 'A', priceMinor: 1, currency: 'EUR', quantity: 1 })).toBeNull();

    const valid = sanitizeCartItem({
      productId: 1,
      name: 'A',
      priceMinor: 100,
      currency: 'EUR',
      quantity: 2,
      seller: { slug: 's', name: 'S' },
    });

    expect(valid).not.toBeNull();
    expect(valid?.quantity).toBe(2);
    expect(valid?.imageUrl).toBeNull();
  });
});

describe('suivi de commande (#9627)', () => {
  it('marque les étapes franchies dans l’ordre', () => {
    expect(reachedSteps('confirmed')).toEqual(['pending', 'confirmed']);
    expect(sequenceIndex('delivered')).toBe(4);
  });

  it('une commande annulée ne prétend pas avoir progressé', () => {
    expect(reachedSteps('cancelled')).toEqual([]);
    expect(isTerminal('cancelled')).toBe(true);
    expect(isTerminal('shipped')).toBe(false);
  });

  it('normalise les deux formes de timeline et écarte les statuts inconnus', () => {
    expect(normalizeTimeline([{ status: 'pending', at: '2026-01-01' }, { status: 'bogus' }])).toEqual([
      { status: 'pending', at: '2026-01-01' },
    ]);

    expect(normalizeTimeline({ confirmed: '2026-01-02' })).toEqual([
      { status: 'confirmed', at: '2026-01-02' },
    ]);

    expect(normalizeTimeline('nope')).toEqual([]);
  });
});
