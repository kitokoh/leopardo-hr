import {
  EMPTY_CUSTOMER,
  buildOrderPayload,
  immediateCheckoutUrl,
  isComplete,
  missingFields,
  type CheckoutCustomer,
  type CreatedOrder,
} from '../retail/domain/checkout';
import {
  canReview,
  isValidBuyer,
  isValidRating,
  normalizeBuyer,
} from '../retail/domain/buyer';
import { formatPrice, hasZeroDecimals } from '../kernel/domain/money';
import type { CartGroup } from '../retail/domain/cart';

const CUSTOMER: CheckoutCustomer = {
  name: 'Amina B.',
  phone: '+213 555 01 02',
  email: '',
  address: '12 rue Didouche',
  city: 'Alger',
  notes: '',
};

const GROUP: CartGroup = {
  seller: { slug: 'boulangerie-didouche', name: 'Boulangerie Didouche', city: 'Alger' },
  items: [
    {
      productId: 7,
      name: 'Galette',
      priceMinor: 12000,
      currency: 'DZD',
      quantity: 2,
      imageUrl: null,
      seller: { slug: 'boulangerie-didouche', name: 'Boulangerie Didouche', city: 'Alger' },
    },
  ],
  subtotalMinor: 24000,
  currency: 'DZD',
};

describe('checkout — validation', () => {
  it('accepte un formulaire complet', () => {
    expect(missingFields(CUSTOMER)).toEqual([]);
  });

  it('signale les champs obligatoires manquants', () => {
    expect(missingFields(EMPTY_CUSTOMER)).toEqual(['name', 'phone', 'address', 'city']);
  });

  it('ne rend pas l’e-mail obligatoire', () => {
    // Le checkout invité ne doit pas exiger une adresse pour livrer.
    expect(missingFields({ ...CUSTOMER, email: '' })).toEqual([]);
  });

  it('traite un champ rempli d’espaces comme vide', () => {
    expect(missingFields({ ...CUSTOMER, city: '   ' })).toEqual(['city']);
  });
});

describe('checkout — charge utile', () => {
  it('omet l’e-mail vide plutôt que d’envoyer une chaîne vide', () => {
    const payload = buildOrderPayload(GROUP, CUSTOMER, 'cash', 'key-1');

    expect(payload.customer).toEqual({ name: 'Amina B.', phone: '+213 555 01 02' });
    expect('email' in payload.customer).toBe(false);
  });

  it('inclut l’e-mail quand il est renseigné', () => {
    const payload = buildOrderPayload(
      GROUP,
      { ...CUSTOMER, email: 'amina@example.dz' },
      'cash',
      'key-1',
    );

    expect(payload.customer.email).toBe('amina@example.dz');
  });

  it('omet les notes vides', () => {
    const payload = buildOrderPayload(GROUP, CUSTOMER, 'cash', 'key-1');

    expect('notes' in payload.delivery).toBe(false);
  });

  it('transmet la clé d’idempotence telle quelle', () => {
    expect(buildOrderPayload(GROUP, CUSTOMER, 'online', 'abc-123').idempotency_key).toBe('abc-123');
  });

  it('mappe les lignes du panier sur le contrat API', () => {
    const payload = buildOrderPayload(GROUP, CUSTOMER, 'cash', 'key-1');

    expect(payload.items).toEqual([{ product_id: 7, quantity: 2 }]);
    expect(payload.seller).toBe('boulangerie-didouche');
  });
});

describe('checkout — résultat', () => {
  const order = (reference: string, checkoutUrl: string | null): CreatedOrder => ({
    reference,
    trackingToken: 'tok',
    totalMinor: 1000,
    currency: 'EUR',
    sellerName: 'Boutique',
    sellerSlug: 'boutique',
    createdAt: '2026-11-19T00:00:00Z',
    paymentMethod: 'online',
    checkoutUrl,
  });

  it('considère un succès partiel comme incomplet', () => {
    expect(isComplete({ created: [order('A', null)], failed: [{ sellerName: 'B', message: 'x' }] })).toBe(false);
  });

  it('considère un envoi sans échec comme complet', () => {
    expect(isComplete({ created: [order('A', null)], failed: [] })).toBe(true);
  });

  it('ne redirige pas vers le PSP en paiement à la livraison', () => {
    expect(immediateCheckoutUrl({ created: [order('A', 'https://psp')], failed: [] }, 'cash')).toBeNull();
  });

  it('redirige quand une seule commande est payable', () => {
    expect(
      immediateCheckoutUrl({ created: [order('A', 'https://psp')], failed: [] }, 'online'),
    ).toBe('https://psp');
  });

  it('ne redirige pas quand plusieurs commandes sont payables', () => {
    // Rediriger vers la première ferait croire que les autres sont payées.
    expect(
      immediateCheckoutUrl(
        { created: [order('A', 'https://psp-a'), order('B', 'https://psp-b')], failed: [] },
        'online',
      ),
    ).toBeNull();
  });
});

describe('compte acheteur', () => {
  it('reconnaît un profil exploitable', () => {
    expect(isValidBuyer({ name: 'Amina', email: 'a@b.dz' })).toBe(true);
  });

  it('rejette un profil sans e-mail', () => {
    expect(isValidBuyer({ name: 'Amina' })).toBe(false);
  });

  it('rejette une valeur non-objet', () => {
    expect(isValidBuyer('amina')).toBe(false);
  });

  it('rend explicites les champs optionnels absents', () => {
    expect(normalizeBuyer({ name: 'A', email: 'a@b.dz' } as never)).toEqual({
      name: 'A',
      email: 'a@b.dz',
      phone: null,
      createdAt: null,
    });
  });

  it('n’autorise un avis qu’après livraison', () => {
    expect(canReview('delivered')).toBe(true);
    expect(canReview('shipped')).toBe(false);
    expect(canReview('pending')).toBe(false);
  });

  it('borne la note entre 1 et 5 entiers', () => {
    expect(isValidRating(5)).toBe(true);
    expect(isValidRating(0)).toBe(false);
    expect(isValidRating(6)).toBe(false);
    expect(isValidRating(3.5)).toBe(false);
  });
});

describe('formatage monétaire', () => {
  it('divise par 100 une devise à décimales', () => {
    expect(formatPrice(1250, 'EUR')).toContain('12,50');
  });

  it('ne divise pas une devise sans décimales', () => {
    // 1 250 FCFA ne doit pas s'afficher « 12,50 FCFA ».
    expect(hasZeroDecimals('XOF')).toBe(true);
    expect(formatPrice(1250, 'XOF')).toContain('1');
    expect(formatPrice(1250, 'XOF')).not.toContain('12,50');
  });

  it('rend null pour un montant inconnu', () => {
    expect(formatPrice(null)).toBeNull();
    expect(formatPrice(undefined)).toBeNull();
  });

  it('reconnaît les devises sans décimales en minuscules', () => {
    expect(hasZeroDecimals('jpy')).toBe(true);
  });
});
