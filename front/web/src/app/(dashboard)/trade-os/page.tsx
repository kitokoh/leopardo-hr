'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { AlertTriangle, ClipboardList, FileText, Globe2, Megaphone, Package, RefreshCw, ShoppingCart, Store, Truck, Users } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { getStoredUser } from '@/lib/auth-session';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { CounterpartyForm } from './components/CounterpartyForm';
import { CounterpartyList } from './components/CounterpartyList';
import type { TradeCounterparty } from './components/types';
import { isTradeAdmin } from './components/types';

/**
 * BC-36 TRADE (TRADE-009/#8484) — dashboard `/trade` de la verticale
 * International Trade OS.
 *
 * ARCHITECTURE : les vues « acheteur » (sourcing) et « vendeur » (ventes) sont
 * deux LECTURES du MÊME noyau commercial partagé — pas deux applications. Une
 * entreprise peut importer ET exporter : le rôle métier n'est jamais
 * irréversible, on ne fait que changer d'onglet.
 *
 * Sources (endpoints réels, aucune donnée mock) :
 *   - `GET /trade/rfqs`              → demandes de prix (vue acheteur)
 *   - `GET /trade/quotes`            → offres commerciales (vue vendeur)
 *   - `GET /trade/cost-estimates`    → estimations de coût rendu
 *   - `GET /trade/meta/integrations` → état RÉEL des adaptateurs externes
 *
 * SÉCURITÉ : la page est réservée aux tenants dont le flag `trade` est actif —
 * un 403 `MODULE_DISABLED` (gate serveur fail-closed) affiche l'état dédié au
 * lieu d'une erreur générique.
 *
 * HONNÊTETÉ PRODUIT : une intégration non configurée est affichée comme
 * INDISPONIBLE. Aucune donnée n'est simulée pour combler un adaptateur absent.
 */

type TradeTab =
  | 'overview'
  | 'sourcing'
  | 'sales'
  | 'catalog'
  | 'orders'
  | 'counterparties'
  | 'costs'
  // Les trois surfaces du "PROMOUVOIR" (#9831 #9832 #9833) : le backend les
  // servait deja, aucun ecran ne les appelait.
  | 'profile'
  | 'documents'
  | 'promotion';

/**
 * Commande B2B (#9779).
 *
 * `direction` distingue ce que l'entreprise ACHETE (`purchase`) de ce qu'elle
 * VEND (`sale`) : meme objet, deux flux — c'est la meme entreprise.
 *
 * `total_minor` vient de l'offre acceptee, jamais d'une saisie : le montant
 * engage est celui que le serveur avait fige.
 */
type TradeOrderRow = {
  id: number | string;
  reference?: string | null;
  direction?: 'purchase' | 'sale' | string;
  status?: string | null;
  currency?: string | null;
  incoterm?: string | null;
  total_minor?: number | null;
};

/**
 * Contrepartie B2B (#9779) — annuaire clients / fournisseurs / transporteurs.
 *
 * `verification_status` est un etat INTERNE : `verified` signifie « verifie par
 * un membre de l'equipe », jamais « verifie automatiquement » — aucun service
 * de verification d'entreprise n'est souscrit.
 */
type TradeCounterpartyRow = {
  id: number | string;
  name?: string | null;
  country?: string | null;
  roles?: string[] | null;
  verification_status?: string | null;
};

/**
 * Produit du catalogue B2B (#9767).
 *
 * `indicative_unit_price_minor` est un entier en minor units : le catalogue
 * n'est pas une offre commerciale, il ne porte ni remise ni total. Un prix
 * ferme n'existe que dans une offre (`TradeQuote`), où le serveur recalcule
 * les totaux depuis les lignes.
 */
type TradeProduct = {
  id: number | string;
  name: string;
  description?: string | null;
  category?: string | null;
  hs_code?: string | null;
  origin_country?: string | null;
  unit?: string | null;
  moq_quantity?: string | number | null;
  indicative_unit_price_minor?: number | null;
  currency?: string | null;
  status?: string;
};

/** Brouillon du formulaire de création — tout est texte, converti à l'envoi. */
type ProductDraft = {
  name: string;
  category: string;
  unit: string;
  moq: string;
  price: string;
  origin: string;
  hsCode: string;
};

const EMPTY_DRAFT: ProductDraft = {
  name: '',
  category: '',
  unit: '',
  moq: '',
  price: '',
  origin: '',
  hsCode: '',
};

type TradeRow = {
  id?: string | number;
  reference?: string;
  title?: string;
  status?: string;
  destination?: string;
  incoterm?: string;
  created_at?: string;
};

type TradeIntegration = {
  key: string;
  label: string;
  purpose: string;
  mode: string;
  configured: boolean;
  available: boolean;
  reason?: string | null;
};

/**
 * Profil commercial du tenant (#9831).
 *
 * `is_public` est un etat SERVEUR : on ne le deduit jamais de la presence d'un
 * slug. Un profil peut avoir un slug et rester depublie — c'est exactement ce
 * que `PUT /trade/profile/publication` distingue.
 */
type TradeProfile = {
  import_enabled: boolean;
  export_enabled: boolean;
  domestic_scope: boolean;
  hq_country?: string | null;
  markets_served?: string[] | null;
  product_categories?: string[] | null;
  default_currency?: string | null;
  default_incoterm?: string | null;
  notes?: string | null;
  is_public: boolean;
  public_slug?: string | null;
  public_name?: string | null;
  public_description?: string | null;
  public_city?: string | null;
};

/** Brouillon du formulaire de profil — tout est texte, converti a l'envoi. */
type ProfileDraft = {
  importEnabled: boolean;
  exportEnabled: boolean;
  domesticScope: boolean;
  hqCountry: string;
  marketsServed: string;
  productCategories: string;
  defaultCurrency: string;
  defaultIncoterm: string;
  notes: string;
  publicSlug: string;
  publicName: string;
  publicDescription: string;
  publicCity: string;
};

const EMPTY_PROFILE_DRAFT: ProfileDraft = {
  importEnabled: false,
  exportEnabled: false,
  domesticScope: false,
  hqCountry: '',
  marketsServed: '',
  productCategories: '',
  defaultCurrency: '',
  defaultIncoterm: '',
  notes: '',
  publicSlug: '',
  publicName: '',
  publicDescription: '',
  publicCity: '',
};

/**
 * Document commercial (#9832).
 *
 * `download_url` est une ROUTE autorisee, jamais une URL de stockage : la
 * ressource serveur ne sort ni `disk` ni `path`. On l'utilise telle quelle.
 */
type TradeDocumentRow = {
  id: number | string;
  type: string;
  type_label?: string | null;
  title?: string | null;
  original_name?: string | null;
  mime_type?: string | null;
  size_bytes?: number | null;
  status?: string | null;
  trade_order_id?: number | null;
  trade_quote_id?: number | null;
  trade_rfq_id?: number | null;
  download_url?: string | null;
  created_at?: string | null;
};

/** Kit de promotion (#9833) — lecture seule, il ne publie rien. */
type PromotionKit = {
  publication: { is_public: boolean; slug: string | null; url: string | null };
  checklist: { key: string; done: boolean; hint: string }[];
  draft: { headline: string | null; summary: string | null; highlights: string[] };
  disclaimer: string;
};

const TABS: { key: TradeTab; labelKey: string; icon: typeof Globe2 }[] = [
  { key: 'overview', labelKey: 'trade.overview.title', icon: Globe2 },
  { key: 'sourcing', labelKey: 'trade.sourcing.title', icon: ShoppingCart },
  { key: 'sales', labelKey: 'trade.sales.title', icon: Store },
  // Le répertoire des contreparties est l'outil du VENDEUR (et de l'acheteur) :
  // sans lui, une offre ne peut désigner ni client ni fournisseur (#9780).
  { key: 'counterparties', labelKey: 'trade.counterparties.title', icon: Users },
  // Le catalogue est l'outil du VENDEUR : sans lui, il ne peut pas alimenter
  // les lignes de ses offres (`trade_product_id` restait vide).
  { key: 'catalog', labelKey: 'trade.catalog.title', icon: Package },
  // Les commandes sont l'outil qui manquait au "GERER" : sans lui, une offre
  // acceptee ne produisait rien de visible.
  //
  // #9859 — `counterparties` etait declare une SECONDE fois ici (avec l'icone
  // `Handshake`), alors qu'il figure deja plus haut (`Users`). Consequence
  // reelle, pas theorique : deux onglets identiques a l'ecran, deux cles
  // React egales dans `TABS.map()` (avertissement de reconciliation), et une
  // requete `getByRole('tab', { name: /contreparties/i })` qui trouve deux
  // elements donc echoue — c'est ce qui rendait `counterparties.test.tsx`
  // rouge. Le repertoire etant deja accessible, l'entree en double est
  // supprimee ; `orders` et `costs` restent.
  { key: 'orders', labelKey: 'trade.orders.title', icon: ClipboardList },
  { key: 'costs', labelKey: 'trade.costs.title', icon: Truck },
  // ── PROMOUVOIR (#9831 #9832 #9833) ────────────────────────────────────────
  // `GET|PUT /trade/profile`, `PUT /trade/profile/publication`,
  // `GET|POST|DELETE /trade/documents` et `GET /trade/promotion-kit`
  // existaient cote serveur sans AUCUN point d'entree : le vendeur B2B ne
  // pouvait ni se decrire, ni publier sa vitrine, ni deposer la facture que la
  // checklist douaniere lui demande. Une route sans interface n'est pas un
  // outil, c'est une capacite latente.
  { key: 'profile', labelKey: 'trade.profile.title', icon: Store },
  { key: 'documents', labelKey: 'trade.documents.title', icon: FileText },
  { key: 'promotion', labelKey: 'trade.promotion.title', icon: Megaphone },
];

/**
 * Champs qu'un client n'envoie JAMAIS pour une contrepartie (#9780).
 *
 * - `verification_status` / `verified_at` / `verified_by_employee_id` : la
 *   vérification est un acte distinct et tracé (`POST .../verify`). Les
 *   accepter ici permettrait de s'auto-déclarer « vérifié » en une requête.
 * - `company_id` : posé par le serveur depuis le contexte tenant (anti-spoof
 *   #7646) — un client ne choisit pas le tenant d'écriture.
 *
 * Le formulaire ne produit déjà aucun de ces champs ; ce filtre est une
 * seconde barrière, appliquée en création comme en édition.
 */
const FORBIDDEN_COUNTERPARTY_FIELDS = [
  'verification_status',
  'verified_at',
  'verified_by_employee_id',
  'company_id',
] as const;

function sanitizeCounterpartyPayload(payload: Record<string, unknown>): Record<string, unknown> {
  const forbidden = new Set<string>(FORBIDDEN_COUNTERPARTY_FIELDS);

  return Object.fromEntries(Object.entries(payload).filter(([key]) => !forbidden.has(key)));
}

export default function TradeDashboardPage() {
  const locale = getPreferredLocale();

  const [tab, setTab] = useState<TradeTab>('overview');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [moduleDisabled, setModuleDisabled] = useState(false);

  const [rfqs, setRfqs] = useState<TradeRow[]>([]);
  const [quotes, setQuotes] = useState<TradeRow[]>([]);
  const [costEstimates, setCostEstimates] = useState<TradeRow[]>([]);
  const [integrations, setIntegrations] = useState<TradeIntegration[]>([]);
  const [degraded, setDegraded] = useState(false);

  // ── Transitions de cycle de vie (#9779) ────────────────────────────────
  //
  // Les routes `POST /trade/rfqs/{id}/transition` et
  // `POST /trade/quotes/{id}/transition` existaient sans AUCUN point d'entree
  // dans l'ecran : le vendeur ne pouvait donc pas s'en servir. Une route sans
  // interface n'est pas un outil, c'est une capacite latente.
  const [transitioning, setTransitioning] = useState<string | null>(null);
  const [transitionError, setTransitionError] = useState<string | null>(null);

  // ── Commandes (#9779) ──────────────────────────────────────────────────
  //
  // Les contreparties viennent de #9780 (bloc plus bas) : on ne garde ici que
  // les commandes, pour ne pas dupliquer un etat qui a deja son ecran complet.
  const [orders, setOrders] = useState<TradeOrderRow[]>([]);
  const [orderError, setOrderError] = useState<string | null>(null);

  // ── Catalogue produits (#9767) ───────────────────────────────────────────
  const [products, setProducts] = useState<TradeProduct[]>([]);
  const [draft, setDraft] = useState<ProductDraft>(EMPTY_DRAFT);
  const [formOpen, setFormOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // ── Contreparties (#9780) ────────────────────────────────────────────────
  const [counterparties, setCounterparties] = useState<TradeCounterparty[]>([]);
  const [counterpartyFormOpen, setCounterpartyFormOpen] = useState(false);
  // Contrepartie en cours d'édition : `null` = le formulaire est en création.
  const [counterpartyEditing, setCounterpartyEditing] = useState<TradeCounterparty | null>(null);
  const [counterpartySaving, setCounterpartySaving] = useState(false);
  const [counterpartyError, setCounterpartyError] = useState<string | null>(null);
  const [counterpartyNotice, setCounterpartyNotice] = useState<string | null>(null);

  // ── Profil commercial & publication (#9831) ───────────────────────────────
  const [profile, setProfile] = useState<TradeProfile | null>(null);
  const [profileDraft, setProfileDraft] = useState<ProfileDraft>(EMPTY_PROFILE_DRAFT);
  const [profileSaving, setProfileSaving] = useState(false);
  const [profileError, setProfileError] = useState<string | null>(null);
  const [profileNotice, setProfileNotice] = useState<string | null>(null);
  const [publishing, setPublishing] = useState(false);

  // ── Documents commerciaux (#9832) ─────────────────────────────────────────
  const [documents, setDocuments] = useState<TradeDocumentRow[]>([]);
  const [documentError, setDocumentError] = useState<string | null>(null);
  const [documentNotice, setDocumentNotice] = useState<string | null>(null);
  const [documentFormOpen, setDocumentFormOpen] = useState(false);
  const [documentSaving, setDocumentSaving] = useState(false);
  const [documentDraft, setDocumentDraft] = useState({
    type: 'commercial_invoice',
    title: '',
    linkKind: 'order' as 'order' | 'quote' | 'rfq',
    linkId: '',
  });
  const [documentFile, setDocumentFile] = useState<File | null>(null);

  // ── Kit de promotion (#9833) ──────────────────────────────────────────────
  const [promotionKit, setPromotionKit] = useState<PromotionKit | null>(null);
  const [promotionError, setPromotionError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    setLoading(true);

    try {
      // Chaque appel est indépendant : un module partiellement déployé ne doit
      // pas vider tout l'écran. On tolère l'absence d'une source (404/403) et
      // on affiche « aucune donnée » plutôt qu'une erreur globale.
      const [rfqRes, quoteRes, costRes, metaRes, productRes, orderRes, counterpartyRes, profileRes, documentRes, promotionRes] = await Promise.allSettled([
        apiFetch('/trade/rfqs'),
        apiFetch('/trade/quotes'),
        apiFetch('/trade/cost-estimates'),
        apiFetch('/trade/meta/integrations'),
        apiFetch('/trade/products'),
        // #9779 : deux sources de plus, elles aussi INDEPENDANTES — un 403 sur
        // l'une ne doit pas vider les autres onglets.
        apiFetch('/trade/orders'),
        apiFetch('/trade/counterparties?per_page=100'),
        // #9831 #9832 #9833 : les trois surfaces du « promouvoir ». Elles sont
        // chargees ici pour que l'onglet s'ouvre deja rempli, mais chacune
        // reste INDEPENDANTE : un 403 sur le kit de promotion (reserve a
        // l'admin de la verticale) ne doit pas vider le catalogue.
        apiFetch('/trade/profile'),
        apiFetch('/trade/documents'),
        apiFetch('/trade/promotion-kit'),
      ]);

      const firstRejection = [rfqRes, quoteRes, costRes, metaRes].find(
        (r) => r.status === 'rejected',
      );

      if (firstRejection && firstRejection.status === 'rejected') {
        const reason = firstRejection.reason;
        if (reason instanceof ApiError && reason.status === 403) {
          setModuleDisabled(true);
          return;
        }
      }

      const readList = async (settled: PromiseSettledResult<Response>): Promise<TradeRow[]> => {
        if (settled.status !== 'fulfilled') return [];
        try {
          const payload = await settled.value.json();
          return Array.isArray(payload?.data) ? payload.data : [];
        } catch {
          return [];
        }
      };

      setRfqs(await readList(rfqRes));
      setQuotes(await readList(quoteRes));
      setCostEstimates(await readList(costRes));
      setOrders((await readList(orderRes)) as unknown as TradeOrderRow[]);

      // #9779 — une erreur PROPRE aux commandes est affichee dans son onglet :
      // l'ecran ne doit pas laisser croire que le vendeur n'a aucune commande
      // alors que c'est la lecture qui a echoue.
      if (orderRes.status === 'rejected') {
        setOrderError(t(locale, 'trade.orders.loadError'));
      }

      // Le catalogue est une source INDÉPENDANTE : un 403/404 sur les autres
      // listes ne doit pas vider l'onglet Catalogue, et inversement.
      if (productRes.status === 'fulfilled') {
        try {
          const payload = await productRes.value.json();
          setProducts(Array.isArray(payload?.data) ? (payload.data as TradeProduct[]) : []);
        } catch {
          setProducts([]);
        }
      } else {
        setProducts([]);
      }

      // Les contreparties sont une source INDÉPENDANTE, comme le catalogue :
      // un 403 sur les RFQ ne doit pas vider le répertoire.
      if (counterpartyRes.status === 'fulfilled') {
        try {
          const payload = await counterpartyRes.value.json();
          setCounterparties(
            Array.isArray(payload?.data) ? (payload.data as TradeCounterparty[]) : [],
          );
        } catch {
          setCounterparties([]);
        }
      } else {
        setCounterparties([]);
        setCounterpartyError(t(locale, 'trade.counterparties.loadError'));
      }

      if (metaRes.status === 'fulfilled') {
        try {
          const payload = await metaRes.value.json();
          const data = payload?.data ?? {};
          setIntegrations(Array.isArray(data.integrations) ? data.integrations : []);
          setDegraded(Boolean(data.degraded));
        } catch {
          setIntegrations([]);
        }
      }

      // ── Profil commercial (#9831) ─────────────────────────────────────────
      // `data` vaut `null` quand le tenant n'a jamais cree de profil : ce n'est
      // PAS une erreur, c'est l'etat initial. On garde `profile = null` et le
      // formulaire reste vide, pret a la creation.
      if (profileRes.status === 'fulfilled') {
        try {
          const payload = await profileRes.value.json();
          const data = (payload?.data ?? null) as TradeProfile | null;
          setProfile(data);
          setProfileDraft(
            data
              ? {
                  importEnabled: Boolean(data.import_enabled),
                  exportEnabled: Boolean(data.export_enabled),
                  domesticScope: Boolean(data.domestic_scope),
                  hqCountry: data.hq_country ?? '',
                  marketsServed: (data.markets_served ?? []).join(', '),
                  productCategories: (data.product_categories ?? []).join(', '),
                  defaultCurrency: data.default_currency ?? '',
                  defaultIncoterm: data.default_incoterm ?? '',
                  notes: data.notes ?? '',
                  publicSlug: data.public_slug ?? '',
                  publicName: data.public_name ?? '',
                  publicDescription: data.public_description ?? '',
                  publicCity: data.public_city ?? '',
                }
              : EMPTY_PROFILE_DRAFT,
          );
        } catch {
          setProfile(null);
        }
      } else {
        setProfileError(t(locale, 'trade.profile.error'));
      }

      // ── Documents commerciaux (#9832) ─────────────────────────────────────
      if (documentRes.status === 'fulfilled') {
        try {
          const payload = await documentRes.value.json();
          setDocuments(Array.isArray(payload?.data) ? (payload.data as TradeDocumentRow[]) : []);
        } catch {
          setDocuments([]);
        }
      } else {
        setDocuments([]);
        setDocumentError(t(locale, 'trade.documents.error'));
      }

      // ── Kit de promotion (#9833) ──────────────────────────────────────────
      // Reserve au role `admin` de la verticale : un 403 est un etat ATTENDU
      // pour un membre en lecture seule, pas une panne. On affiche un message
      // propre a l'onglet plutot qu'une erreur globale.
      if (promotionRes.status === 'fulfilled') {
        try {
          const payload = await promotionRes.value.json();
          setPromotionKit((payload?.data ?? null) as PromotionKit | null);
        } catch {
          setPromotionKit(null);
        }
      } else {
        setPromotionKit(null);
        setPromotionError(t(locale, 'trade.promotion.loadError'));
      }
    } catch (err) {
      if (err instanceof ApiError && err.status === 403) {
        setModuleDisabled(true);
      } else {
        setError(t(locale, 'trade.loadError'));
      }
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const unconfigured = useMemo(
    () => integrations.filter((i) => !(i.configured && i.available)),
    [integrations],
  );

  /**
   * Création d'un produit (#9767).
   *
   * ## Ce que le formulaire refuse de faire
   *
   * Il n'envoie **jamais** de `hs_code` inventé : vide = `null`, et le serveur
   * ne le déduit pas non plus. Un code douanier faux ferait croire à une
   * conformité qui n'existe pas.
   *
   * Le prix saisi est converti en **minor units entiers** (`Math.round`) :
   * aucun flottant ne part vers l'API pour représenter de l'argent.
   */
  const submitProduct = async () => {
    setFormError(null);
    setNotice(null);

    const name = draft.name.trim();
    if (!name) {
      setFormError(t(locale, 'trade.catalog.nameRequired'));
      return;
    }

    const hsCode = draft.hsCode.trim();
    if (hsCode && !/^[0-9]{6,10}$/.test(hsCode)) {
      setFormError(t(locale, 'trade.catalog.hsInvalid'));
      return;
    }

    const price = draft.price.trim();
    const moq = draft.moq.trim();

    setSaving(true);
    try {
      const response = await apiFetch('/trade/products', {
        method: 'POST',
        body: JSON.stringify({
          name,
          category: draft.category.trim() || null,
          unit: draft.unit.trim() || null,
          origin_country: draft.origin.trim().toUpperCase() || null,
          moq_quantity: moq ? moq : null,
          // Saisie en unité majeure → minor units entiers.
          indicative_unit_price_minor: price ? Math.round(Number(price) * 100) : null,
          hs_code: hsCode || null,
        }),
      });

      const payload = await response.json().catch(() => null);
      const created = payload?.data as TradeProduct | undefined;

      if (created) {
        setProducts((current) => [created, ...current]);
      }
      setDraft(EMPTY_DRAFT);
      setFormOpen(false);
      setNotice(t(locale, 'trade.catalog.saved'));
    } catch {
      setFormError(t(locale, 'trade.catalog.error'));
    } finally {
      setSaving(false);
    }
  };

  /**
   * Archivage logique — jamais de suppression : les offres passées référencent
   * le produit, un `DELETE` casserait leur historique.
   */
  const archiveProduct = async (product: TradeProduct) => {
    setFormError(null);
    setNotice(null);

    try {
      const response = await apiFetch(`/trade/products/${product.id}/archive`, {
        method: 'POST',
      });
      const payload = await response.json().catch(() => null);
      const updated = payload?.data as TradeProduct | undefined;

      setProducts((current) =>
        current.map((item) =>
          item.id === product.id ? { ...item, status: updated?.status ?? 'archived' } : item,
        ),
      );
    } catch {
      setFormError(t(locale, 'trade.catalog.error'));
    }
  };

  /**
   * Création (#9780) ou mise à jour (#9780, édition) d'une contrepartie.
   *
   * Le payload vient du formulaire, qui n'envoie **jamais**
   * `verification_status` ni `company_id` : le premier est un acte distinct
   * (`POST .../verify`), le second est posé par le serveur depuis le contexte
   * tenant. Les envoyer ici serait au mieux ignoré, au pire une faille —
   * `sanitizeCounterpartyPayload` les retire de toute façon, en création comme
   * en édition.
   *
   * En édition, la liste est mise à jour **en place** (remplacement de
   * l'élément) plutôt que rechargée : un `GET` complet ferait perdre le filtre
   * et la recherche en cours, et afficherait un état intermédiaire.
   */
  const submitCounterparty = async (payload: Record<string, unknown>) => {
    setCounterpartyError(null);
    setCounterpartyNotice(null);
    setCounterpartySaving(true);

    const editing = counterpartyEditing;
    const body = sanitizeCounterpartyPayload(payload);

    try {
      const response = await apiFetch(
        editing ? `/trade/counterparties/${editing.id}` : '/trade/counterparties',
        {
          method: editing ? 'PUT' : 'POST',
          body: JSON.stringify(body),
        },
      );

      const parsed = await response.json().catch(() => null);
      const saved = parsed?.data as TradeCounterparty | undefined;

      // `data` peut être un tableau vide (réponse inattendue) : on n'insère
      // dans la liste qu'un objet réellement identifié. Sans ce garde, une
      // réponse vide insérait `[]` dans la liste et faisait planter le rendu.
      if (saved && saved.id !== undefined && saved.id !== null) {
        setCounterparties((current) =>
          editing
            ? current.map((item) => (item.id === editing.id ? saved : item))
            : [saved, ...current],
        );
      }

      setCounterpartyFormOpen(false);
      setCounterpartyEditing(null);
      setCounterpartyNotice(t(locale, 'trade.counterparties.saved'));
    } catch {
      // Le formulaire reste ouvert et garde la saisie : une erreur réseau ne
      // doit pas faire perdre ce que l'utilisateur vient de taper.
      setCounterpartyError(t(locale, 'trade.counterparties.error'));
    } finally {
      setCounterpartySaving(false);
    }
  };

  /**
   * Ouvre le formulaire en mode édition sur une contrepartie existante.
   *
   * On ne modifie pas l'objet de la liste : le formulaire travaille sur un
   * brouillon, et la liste n'est remplacée qu'après un `PUT` réussi.
   */
  const startEditCounterparty = (counterparty: TradeCounterparty) => {
    setCounterpartyEditing(counterparty);
    setCounterpartyFormOpen(true);
    setCounterpartyError(null);
    setCounterpartyNotice(null);
  };

  /**
   * Archivage d'une contrepartie — `DELETE /trade/counterparties/{id}`.
   *
   * Le serveur pose `deleted_at` (suppression douce) : la fiche quitte le
   * répertoire mais reste dans l'historique des dossiers, RFQ et offres qui la
   * référencent. Aucune suppression physique n'est possible, et l'écran ne le
   * promet pas non plus.
   *
   * La confirmation est demandée par la liste, AVANT l'appel : ce handler ne
   * s'exécute que si l'utilisateur a accepté.
   */
  const archiveCounterparty = async (counterparty: TradeCounterparty) => {
    setCounterpartyError(null);
    setCounterpartyNotice(null);

    try {
      await apiFetch(`/trade/counterparties/${counterparty.id}`, { method: 'DELETE' });
      setCounterparties((current) => current.filter((item) => item.id !== counterparty.id));
      setCounterpartyNotice(t(locale, 'trade.counterparties.archived'));
    } catch {
      setCounterpartyError(t(locale, 'trade.counterparties.error'));
    }
  };

  /**
   * Vérification — acte réservé à l'admin de la verticale.
   *
   * Le motif est OBLIGATOIRE côté serveur pour `verified`/`rejected` : on le
   * demande ici plutôt que de laisser l'API refuser après coup. Un refus
   * silencieux (annulation du prompt) n'envoie rien.
   */
  const verifyCounterparty = async (counterparty: TradeCounterparty) => {
    setCounterpartyError(null);
    setCounterpartyNotice(null);

    const note = window.prompt(t(locale, 'trade.counterparties.verifyPrompt'));
    if (note === null) return;

    const trimmed = note.trim();
    if (!trimmed) {
      setCounterpartyError(t(locale, 'trade.counterparties.verifyNoteRequired'));
      return;
    }

    try {
      const response = await apiFetch(`/trade/counterparties/${counterparty.id}/verify`, {
        method: 'POST',
        body: JSON.stringify({ status: 'verified', note: trimmed }),
      });

      const body = await response.json().catch(() => null);
      const updated = body?.data as TradeCounterparty | undefined;

      if (updated) {
        setCounterparties((current) =>
          current.map((item) => (item.id === counterparty.id ? updated : item)),
        );
      }
      setCounterpartyNotice(t(locale, 'trade.counterparties.verified'));
    } catch {
      setCounterpartyError(t(locale, 'trade.counterparties.error'));
    }
  };

  const formatPrice = (product: TradeProduct): string => {
    if (product.indicative_unit_price_minor === null || product.indicative_unit_price_minor === undefined) {
      return t(locale, 'trade.catalog.priceOnRequest');
    }
    const amount = (product.indicative_unit_price_minor / 100).toLocaleString(locale);
    return `${t(locale, 'trade.catalog.priceIndicative')} — ${amount}${product.currency ? ` ${product.currency}` : ''}`;
  };

  /**
   * Traduit une valeur d'enum venue de l'API.
   *
   * POURQUOI CE N'EST PAS COSMETIQUE
   * --------------------------------
   * `renderTable` affichait `row.status` brut : un vendeur B2B lisait
   * `under_review`, `in_transit` ou `incomplete` dans une interface francaise.
   * Il devait traduire mentalement l'etat de sa propre affaire — ce n'est pas
   * un outil, c'est un dump de base de donnees.
   *
   * Le repli est la valeur BRUTE, jamais une chaine vide : une valeur d'enum
   * inconnue (nouvel etat cote API, donnee historique) doit rester lisible
   * plutot que disparaitre et laisser croire a une absence de donnee.
   */
  const statusLabel = (namespace: string, value?: string | null): string => {
    if (!value) return '—';
    return t(locale, `trade.${namespace}.status.${value}`, value);
  };

  /**
   * Fait avancer une RFQ ou une offre (#9779).
   *
   * Le serveur reste seul juge : une transition interdite revient en 422
   * `INVALID_STATUS_TRANSITION` et on affiche le refus tel quel. On ne
   * duplique PAS la table de transitions ici — deux verites divergeraient au
   * premier changement de regle metier.
   *
   * On recharge apres succes : le statut affiche doit venir du serveur, pas
   * d'une mise a jour optimiste qui pourrait mentir si la transition a ete
   * refusee par une regle qu'on ne connait pas.
   */
  const runTransition = async (
    kind: 'rfqs' | 'quotes',
    id: number | string,
    status: string,
  ) => {
    const key = `${kind}:${id}:${status}`;
    setTransitioning(key);
    setTransitionError(null);

    try {
      await apiFetch(`/trade/${kind}/${id}/transition`, {
        method: 'POST',
        body: JSON.stringify({ status }),
      });
      await load();
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) {
        setTransitionError(t(locale, 'trade.transition.refused'));
      } else {
        setTransitionError(t(locale, 'trade.transition.failed'));
      }
    } finally {
      setTransitioning(null);
    }
  };

  /**
   * Actions proposees pour une ligne, selon son statut.
   *
   * On n'affiche QUE les cibles plausibles : proposer « attribuer » sur une
   * RFQ en brouillon produirait un 422 a chaque clic. Le serveur reste
   * l'autorite, mais l'ecran ne doit pas inviter a l'erreur.
   */
  const actionsFor = (kind: 'rfqs' | 'quotes', status?: string | null): string[] => {
    if (kind === 'rfqs') {
      if (status === 'open') return ['awarded', 'closed', 'cancelled'];
      if (status === 'draft') return ['cancelled'];
      return [];
    }

    if (status === 'submitted' || status === 'under_review' || status === 'negotiating') {
      return ['rejected', 'withdrawn'];
    }
    if (status === 'draft') return ['withdrawn'];

    return [];
  };

  const renderTable = (rows: TradeRow[], emptyKey: string, statusNamespace: string) => {
    if (!rows.length) {
      return (
        <p className="px-1 py-6 text-sm text-slate-500">{t(locale, 'trade.noData')}</p>
      );
    }

    return (
      <div className="overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead>
            <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
              <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.reference')}</th>
              <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.title')}</th>
              <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.status')}</th>
              <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.destination')}</th>
              <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.incoterm')}</th>
              <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row, index) => (
              <tr key={String(row.id ?? index)} className="border-t border-slate-100">
                <td className="px-3 py-3 font-mono text-xs text-slate-600">
                  {row.reference ?? '—'}
                </td>
                <td className="px-3 py-3 text-slate-900">{row.title ?? '—'}</td>
                <td className="px-3 py-3 text-slate-600">{statusLabel(statusNamespace, row.status)}</td>
                <td className="px-3 py-3 text-slate-600">{row.destination ?? '—'}</td>
                <td className="px-3 py-3 text-slate-600">{row.incoterm ?? '—'}</td>
                <td className="px-3 py-3">
                  <div className="flex flex-wrap gap-1.5">
                    {actionsFor(statusNamespace as 'rfqs' | 'quotes', row.status).map((target) => {
                      const rowId = row.id;
                      if (rowId === undefined) return null;
                      const key = `${statusNamespace}:${rowId}:${target}`;
                      return (
                        <button
                          key={target}
                          type="button"
                          disabled={transitioning === key}
                          onClick={() => void runTransition(statusNamespace as 'rfqs' | 'quotes', rowId, target)}
                          className="rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        >
                          {t(locale, `trade.transition.${target}`, target)}
                        </button>
                      );
                    })}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        <span className="sr-only">{emptyKey}</span>
      </div>
    );
  };

  /**
   * Enregistre le profil commercial (#9831).
   *
   * `PUT /trade/profile` est un create-or-update cote serveur : on ne decide
   * pas ici s'il faut creer ou modifier, le serveur le sait mieux que nous.
   *
   * Les listes sont envoyees en TABLEAU, pas en chaine : le serveur normalise
   * (`normalizeList`) et dedoublonne. Envoyer `"a, b"` produirait une seule
   * categorie nommee « a, b » — un filtre de vitrine qui ne matcherait jamais.
   */
  const saveProfile = async () => {
    setProfileSaving(true);
    setProfileError(null);
    setProfileNotice(null);

    const toList = (value: string): string[] =>
      value
        .split(',')
        .map((part) => part.trim())
        .filter((part) => part !== '');

    try {
      const response = await apiFetch('/trade/profile', {
        method: 'PUT',
        body: JSON.stringify({
          import_enabled: profileDraft.importEnabled,
          export_enabled: profileDraft.exportEnabled,
          domestic_scope: profileDraft.domesticScope,
          hq_country: profileDraft.hqCountry || null,
          markets_served: toList(profileDraft.marketsServed),
          product_categories: toList(profileDraft.productCategories),
          default_currency: profileDraft.defaultCurrency || null,
          default_incoterm: profileDraft.defaultIncoterm || null,
          notes: profileDraft.notes || null,
        }),
      });

      const payload = await response.json();
      setProfile((payload?.data ?? null) as TradeProfile | null);
      setProfileNotice(t(locale, 'trade.profile.saved'));
    } catch (err) {
      // 422 = le serveur refuse (aucune direction activee). On affiche SA
      // raison plutot qu'un message generique : c'est une regle metier, pas
      // une panne, et le vendeur doit savoir quoi corriger.
      if (err instanceof ApiError && err.status === 422) {
        setProfileError(t(locale, 'trade.profile.directionRequired'));
      } else {
        setProfileError(t(locale, 'trade.profile.error'));
      }
    } finally {
      setProfileSaving(false);
    }
  };

  /**
   * Publie ou depublie la vitrine (#9831).
   *
   * Acte DISTINCT de l'enregistrement du profil : `PUT /trade/profile` ne
   * touche jamais `is_public`. Un enregistrement de champ commercial ne doit
   * pas faire apparaitre l'entreprise dans l'annuaire par inadvertance.
   *
   * On envoie l'etat CIBLE, pas un « toggle » : deux clics rapides sur un
   * toggle produiraient un etat dependant de l'ordre d'arrivee des reponses.
   */
  const setPublication = async (publish: boolean) => {
    setPublishing(true);
    setProfileError(null);
    setProfileNotice(null);

    try {
      const response = await apiFetch('/trade/profile/publication', {
        method: 'PUT',
        body: JSON.stringify({
          is_public: publish,
          public_slug: profileDraft.publicSlug || null,
          public_name: profileDraft.publicName || null,
          public_description: profileDraft.publicDescription || null,
          public_city: profileDraft.publicCity || null,
        }),
      });

      const payload = await response.json();
      setProfile((payload?.data ?? null) as TradeProfile | null);
      setProfileNotice(
        t(locale, publish ? 'trade.profile.published' : 'trade.profile.unpublished'),
      );
      // Le kit de promotion lit `is_public` : on le recharge pour que l'etat
      // affiche dans l'onglet Promotion ne mente pas apres une publication.
      try {
        const kitRes = await apiFetch('/trade/promotion-kit');
        const kitPayload = await kitRes.json();
        setPromotionKit((kitPayload?.data ?? null) as PromotionKit | null);
      } catch {
        // Le kit est reserve a l'admin : un echec ici ne remet pas en cause la
        // publication, qui a deja reussi.
      }
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) {
        setProfileError(t(locale, 'trade.profile.slugRequired'));
      } else {
        setProfileError(t(locale, 'trade.profile.publishError'));
      }
    } finally {
      setPublishing(false);
    }
  };

  /**
   * Depose un document commercial (#9832).
   *
   * Multipart : `apiFetch` ne pose PAS de `Content-Type` sur un `FormData`,
   * c'est `fetch` qui genere la frontiere. La poser a la main casserait
   * l'upload.
   *
   * Le rattachement est OBLIGATOIRE cote serveur : un document orphelin serait
   * introuvable dans l'ecran du vendeur, donc inutile.
   */
  const uploadDocument = async () => {
    if (!documentFile) {
      setDocumentError(t(locale, 'trade.documents.fileRequired'));
      return;
    }
    if (!documentDraft.title.trim()) {
      setDocumentError(t(locale, 'trade.documents.titleRequired'));
      return;
    }
    if (!documentDraft.linkId.trim()) {
      setDocumentError(t(locale, 'trade.documents.linkRequired'));
      return;
    }

    setDocumentSaving(true);
    setDocumentError(null);
    setDocumentNotice(null);

    const form = new FormData();
    form.append('file', documentFile);
    form.append('type', documentDraft.type);
    form.append('title', documentDraft.title.trim());
    form.append(
      documentDraft.linkKind === 'order'
        ? 'trade_order_id'
        : documentDraft.linkKind === 'quote'
          ? 'trade_quote_id'
          : 'trade_rfq_id',
      documentDraft.linkId.trim(),
    );

    try {
      await apiFetch('/trade/documents', { method: 'POST', body: form });
      setDocumentNotice(t(locale, 'trade.documents.saved'));
      setDocumentFormOpen(false);
      setDocumentDraft({ type: 'commercial_invoice', title: '', linkKind: 'order', linkId: '' });
      setDocumentFile(null);
      await load();
    } catch {
      setDocumentError(t(locale, 'trade.documents.error'));
    } finally {
      setDocumentSaving(false);
    }
  };

  /** Supprime un document (#9832). */
  const deleteDocument = async (id: number | string) => {
    setDocumentError(null);
    setDocumentNotice(null);

    try {
      await apiFetch(`/trade/documents/${id}`, { method: 'DELETE' });
      setDocumentNotice(t(locale, 'trade.documents.deleted'));
      await load();
    } catch {
      setDocumentError(t(locale, 'trade.documents.error'));
    }
  };

  if (moduleDisabled) {
    return (
      <ModulePageShell
        title={t(locale, 'trade.title')}
        subtitle={t(locale, 'trade.subtitle')}
        icon={Globe2}
      >
        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5 text-sm text-amber-800">
          <p className="flex items-center gap-2 font-medium">
            <AlertTriangle className="h-4 w-4" />
            {t(locale, 'trade.moduleDisabled')}
          </p>
        </div>
      </ModulePageShell>
    );
  }

  return (
    <ModulePageShell
      title={t(locale, 'trade.title')}
      subtitle={t(locale, 'trade.subtitle')}
      icon={Globe2}
      actions={
        <Button variant="outline" size="sm" onClick={() => void load()} disabled={loading}>
          <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
          {t(locale, 'trade.retry')}
        </Button>
      }
    >
      {/* Onglets : deux vues métier sur le MÊME noyau partagé. */}
      <div className="flex flex-wrap gap-2" role="tablist">
        {TABS.map(({ key, labelKey, icon: Icon }) => (
          <button
            key={key}
            type="button"
            role="tab"
            aria-selected={tab === key}
            onClick={() => setTab(key)}
            className={`inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition ${
              tab === key
                ? 'bg-slate-900 text-white'
                : 'bg-white/70 text-slate-700 ring-1 ring-slate-200 hover:bg-white'
            }`}
          >
            <Icon className="h-4 w-4" />
            {t(locale, labelKey)}
          </button>
        ))}
      </div>

      {error ? (
        <div className="rounded-2xl border border-rose-200 bg-rose-50 px-6 py-5 text-sm text-rose-700">
          <p className="font-medium">{error}</p>
          <Button variant="outline" size="sm" className="mt-3" onClick={() => void load()}>
            {t(locale, 'trade.retry')}
          </Button>
        </div>
      ) : null}

      {transitionError ? (
        <div className="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 text-sm text-rose-800" role="alert">
          <p className="font-medium">{transitionError}</p>
        </div>
      ) : null}

      {loading ? (
        <p className="py-10 text-center text-sm text-slate-500">{t(locale, 'trade.loading')}</p>
      ) : (
        <>
          {tab === 'overview' ? (
            <div className="space-y-4">
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                  { label: t(locale, 'trade.overview.rfqs'), value: rfqs.length },
                  { label: t(locale, 'trade.overview.quotes'), value: quotes.length },
                  { label: t(locale, 'trade.overview.orders'), value: costEstimates.length },
                  { label: t(locale, 'trade.overview.integrations'), value: integrations.length },
                ].map((card) => (
                  <div
                    key={card.label}
                    className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200"
                  >
                    <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                      {card.label}
                    </p>
                    <p className="mt-2 text-3xl font-bold text-slate-900">{card.value}</p>
                  </div>
                ))}
              </div>

              {degraded ? (
                <div className="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
                  <p className="flex items-center gap-2 font-medium">
                    <AlertTriangle className="h-4 w-4" />
                    {t(locale, 'trade.overview.integrationsDegraded')}
                  </p>
                </div>
              ) : null}

              <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
                <h2 className="text-sm font-semibold text-slate-900">
                  {t(locale, 'trade.integrations.title')}
                </h2>
                <p className="mt-1 text-xs text-slate-500">
                  {t(locale, 'trade.integrations.notice')}
                </p>
                <ul className="mt-3 space-y-2">
                  {integrations.map((integration) => {
                    const operational = integration.configured && integration.available;
                    return (
                      <li
                        key={integration.key}
                        className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-2 text-sm"
                      >
                        <span className="text-slate-700">{integration.label}</span>
                        <span
                          className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                            operational
                              ? 'bg-emerald-100 text-emerald-800'
                              : 'bg-slate-200 text-slate-700'
                          }`}
                        >
                          {operational
                            ? t(locale, 'trade.integrations.stateOperational')
                            : t(locale, 'trade.integrations.stateUnconfigured')}
                        </span>
                      </li>
                    );
                  })}
                  {!integrations.length ? (
                    <li className="text-sm text-slate-500">{t(locale, 'trade.noData')}</li>
                  ) : null}
                </ul>
                {unconfigured.length ? (
                  <p className="mt-3 text-xs text-slate-500">
                    {unconfigured.map((i) => i.reason).filter(Boolean).join(' ')}
                  </p>
                ) : null}
              </div>
            </div>
          ) : null}

          {tab === 'sourcing' ? (
            <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
              <h2 className="text-sm font-semibold text-slate-900">
                {t(locale, 'trade.sourcing.title')}
              </h2>
              <p className="mt-1 text-xs text-slate-500">
                {t(locale, 'trade.sourcing.subtitle')}
              </p>
              <div className="mt-3">{renderTable(rfqs, 'trade-rfqs-empty', 'rfqs')}</div>
            </div>
          ) : null}

          {tab === 'sales' ? (
            <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
              <h2 className="text-sm font-semibold text-slate-900">
                {t(locale, 'trade.sales.title')}
              </h2>
              <p className="mt-1 text-xs text-slate-500">{t(locale, 'trade.sales.subtitle')}</p>
              <div className="mt-3">{renderTable(quotes, 'trade-quotes-empty', 'quotes')}</div>
            </div>
          ) : null}

          {tab === 'orders' ? (
            <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
              <h2 className="text-sm font-semibold text-slate-900">
                {t(locale, 'trade.orders.title')}
              </h2>
              <p className="mt-1 text-xs text-slate-500">{t(locale, 'trade.orders.subtitle')}</p>

              {orderError ? (
                <p className="mt-3 rounded-xl bg-rose-50 px-4 py-2 text-xs text-rose-700">
                  {orderError}
                </p>
              ) : null}

              {!orders.length ? (
                <p className="px-1 py-6 text-sm text-slate-500">
                  {t(locale, 'trade.orders.empty')}
                </p>
              ) : (
                <div className="mt-3 overflow-x-auto">
                  <table className="min-w-full text-sm">
                    <thead>
                      <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.reference')}</th>
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.orders.direction')}</th>
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.status')}</th>
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.orders.total')}</th>
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.incoterm')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {orders.map((order) => (
                        <tr key={String(order.id)} className="border-t border-slate-100">
                          <td className="px-3 py-3 font-mono text-xs text-slate-600">
                            {order.reference ?? `#${order.id}`}
                          </td>
                          <td className="px-3 py-3 text-slate-600">
                            {order.direction === 'purchase'
                              ? t(locale, 'trade.orders.purchase')
                              : order.direction === 'sale'
                                ? t(locale, 'trade.orders.sale')
                                : '—'}
                          </td>
                          <td className="px-3 py-3 text-slate-600">
                            {/* Le statut est un enum serveur (`confirmed`,
                                `in_transit`…) : on affiche son libellé traduit,
                                jamais la valeur brute de la base. Une valeur
                                inconnue retombe sur elle-même plutôt que sur un
                                vide qui laisserait croire à une absence. */}
                            {order.status
                              ? t(locale, `trade.orders.status.${order.status}`, order.status)
                              : '—'}
                          </td>
                          <td className="px-3 py-3 text-slate-900">
                            {order.total_minor === null || order.total_minor === undefined
                              ? '—'
                              : `${(order.total_minor / 100).toLocaleString(locale)}${order.currency ? ` ${order.currency}` : ''}`}
                          </td>
                          <td className="px-3 py-3 text-slate-600">{order.incoterm ?? '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          ) : null}

          {tab === 'counterparties' ? (
            <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
              <h2 className="text-sm font-semibold text-slate-900">
                {t(locale, 'trade.counterparties.title')}
              </h2>
              <p className="mt-1 text-xs text-slate-500">
                {t(locale, 'trade.counterparties.subtitle')}
              </p>

              {counterpartyError ? (
                <p className="mt-3 rounded-xl bg-rose-50 px-4 py-2 text-xs text-rose-700">
                  {counterpartyError}
                </p>
              ) : null}

              {!counterparties.length ? (
                <p className="px-1 py-6 text-sm text-slate-500">
                  {t(locale, 'trade.counterparties.empty')}
                </p>
              ) : (
                <div className="mt-3 overflow-x-auto">
                  <table className="min-w-full text-sm">
                    <thead>
                      <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.counterparties.name')}</th>
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.counterparties.roles')}</th>
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.counterparties.country')}</th>
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.counterparties.verification')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {counterparties.map((party) => (
                        <tr key={String(party.id)} className="border-t border-slate-100">
                          <td className="px-3 py-3 text-slate-900">{party.name ?? '—'}</td>
                          <td className="px-3 py-3 text-slate-600">
                            {/* `roles` est un tableau d'enums serveur
                                (`buyer`, `supplier`, `carrier`…) : chaque rôle
                                est traduit, une valeur inconnue reste lisible. */}
                            {party.roles?.length
                              ? party.roles
                                  .map((role) => t(locale, `trade.counterparties.role.${role}`, role))
                                  .join(' · ')
                              : '—'}
                          </td>
                          <td className="px-3 py-3 text-slate-600">{party.country ?? '—'}</td>
                          <td className="px-3 py-3">
                            {/* `verified` veut dire « verifie par un membre de
                                l'equipe ». On ne l'affiche jamais comme un
                                controle automatique : il n'en existe pas. */}
                            <span
                              className={
                                party.verification_status === 'verified'
                                  ? 'rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700'
                                  : 'rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600'
                              }
                            >
                              {party.verification_status === 'verified'
                                ? t(locale, 'trade.counterparties.verifiedByTeam')
                                : t(locale, 'trade.counterparties.notVerified')}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          ) : null}

          {tab === 'counterparties' ? (
            <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="text-sm font-semibold text-slate-900">
                    {t(locale, 'trade.counterparties.title')}
                  </h2>
                  <p className="mt-1 text-xs text-slate-500">
                    {t(locale, 'trade.counterparties.subtitle')}
                  </p>
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    // « Nouvelle contrepartie » repasse TOUJOURS en création :
                    // sans cette remise à zéro, ouvrir le formulaire après une
                    // édition aurait envoyé un `PUT` sur la fiche précédente.
                    setCounterpartyEditing(null);
                    setCounterpartyFormOpen(true);
                    setCounterpartyError(null);
                    setCounterpartyNotice(null);
                  }}
                >
                  <Users className="h-4 w-4" />
                  {t(locale, 'trade.counterparties.new')}
                </Button>
              </div>

              {counterpartyNotice ? (
                <p className="mt-3 rounded-xl bg-emerald-50 px-4 py-2 text-xs text-emerald-800">
                  {counterpartyNotice}
                </p>
              ) : null}

              {counterpartyError ? (
                <p className="mt-3 rounded-xl bg-rose-50 px-4 py-2 text-xs text-rose-700">
                  {counterpartyError}
                </p>
              ) : null}

              {counterpartyFormOpen ? (
                <CounterpartyForm
                  // La clé force un remontage quand on passe de la création à
                  // une édition (ou d'une fiche à une autre) : le brouillon
                  // repart des valeurs de la fiche visée, jamais des restes de
                  // la précédente.
                  key={counterpartyEditing ? `edit-${counterpartyEditing.id}` : 'create'}
                  locale={locale}
                  initial={counterpartyEditing}
                  saving={counterpartySaving}
                  error={null}
                  onSubmit={(payload) => void submitCounterparty(payload)}
                  onCancel={() => {
                    setCounterpartyFormOpen(false);
                    setCounterpartyEditing(null);
                    setCounterpartyError(null);
                  }}
                />
              ) : null}

              <div className="mt-4">
                <CounterpartyList
                  locale={locale}
                  counterparties={counterparties}
                  loading={false}
                  onArchive={(item) => void archiveCounterparty(item)}
                  onVerify={(item) => void verifyCounterparty(item)}
                  onEdit={startEditCounterparty}
                  // La vérification est réservée à l'admin de la verticale
                  // (`trade.role:admin`). On n'affiche pas un bouton qui
                  // renverrait 403 : le serveur reste l'autorité, l'UI ne
                  // promet pas une action qu'elle ne peut pas tenir.
                  canVerify={isTradeAdmin(getStoredUser())}
                />
              </div>
            </div>
          ) : null}

          {tab === 'catalog' ? (
            <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="text-sm font-semibold text-slate-900">
                    {t(locale, 'trade.catalog.title')}
                  </h2>
                  <p className="mt-1 text-xs text-slate-500">
                    {t(locale, 'trade.catalog.subtitle')}
                  </p>
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    setFormOpen((open) => !open);
                    setFormError(null);
                    setNotice(null);
                  }}
                >
                  <Package className="h-4 w-4" />
                  {t(locale, 'trade.catalog.new')}
                </Button>
              </div>

              {notice ? (
                <p className="mt-3 rounded-xl bg-emerald-50 px-4 py-2 text-xs text-emerald-800">
                  {notice}
                </p>
              ) : null}

              {formError ? (
                <p className="mt-3 rounded-xl bg-rose-50 px-4 py-2 text-xs text-rose-700">
                  {formError}
                </p>
              ) : null}

              {formOpen ? (
                <form
                  className="mt-4 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2"
                  onSubmit={(event) => {
                    event.preventDefault();
                    void submitProduct();
                  }}
                >
                  <label className="text-xs font-medium text-slate-700 sm:col-span-2">
                    {t(locale, 'trade.catalog.name')}
                    <input
                      required
                      value={draft.name}
                      onChange={(e) => setDraft({ ...draft, name: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.catalog.category')}
                    <input
                      value={draft.category}
                      onChange={(e) => setDraft({ ...draft, category: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.catalog.unit')}
                    <input
                      value={draft.unit}
                      onChange={(e) => setDraft({ ...draft, unit: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.catalog.moq')}
                    <input
                      type="number"
                      min="0"
                      step="any"
                      value={draft.moq}
                      onChange={(e) => setDraft({ ...draft, moq: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.catalog.price')}
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      value={draft.price}
                      onChange={(e) => setDraft({ ...draft, price: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.catalog.origin')}
                    <input
                      maxLength={2}
                      value={draft.origin}
                      onChange={(e) => setDraft({ ...draft, origin: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.catalog.hsCode')}
                    <input
                      inputMode="numeric"
                      value={draft.hsCode}
                      onChange={(e) => setDraft({ ...draft, hsCode: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                    {/* Le code n'est JAMAIS deviné : on le dit à l'écran. */}
                    <span className="mt-1 block text-[11px] font-normal text-slate-500">
                      {t(locale, 'trade.catalog.hsHint')}
                    </span>
                  </label>

                  <div className="flex items-end gap-2 sm:col-span-2">
                    <Button type="submit" size="sm" disabled={saving}>
                      {t(locale, 'trade.catalog.save')}
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        setFormOpen(false);
                        setDraft(EMPTY_DRAFT);
                        setFormError(null);
                      }}
                    >
                      {t(locale, 'trade.catalog.cancel')}
                    </Button>
                  </div>
                </form>
              ) : null}

              {products.length === 0 ? (
                <p className="px-1 py-6 text-sm text-slate-500">
                  {t(locale, 'trade.catalog.empty')}
                </p>
              ) : (
                <ul className="mt-4 space-y-2">
                  {products.map((product) => {
                    const archived = product.status === 'archived';
                    const meta = [
                      product.category,
                      product.origin_country,
                      product.moq_quantity
                        ? `${t(locale, 'trade.catalog.moq')}: ${product.moq_quantity}${product.unit ? ` ${product.unit}` : ''}`
                        : null,
                    ].filter((part): part is string => typeof part === 'string' && part !== '');

                    return (
                      <li
                        key={String(product.id)}
                        className="flex flex-wrap items-start justify-between gap-3 border-t border-slate-100 pt-3"
                      >
                        <div className="min-w-0">
                          <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-slate-900">
                            {product.name}
                            <span
                              className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${
                                archived
                                  ? 'bg-slate-200 text-slate-700'
                                  : 'bg-emerald-100 text-emerald-800'
                              }`}
                            >
                              {archived
                                ? t(locale, 'trade.catalog.archived')
                                : t(locale, 'trade.catalog.active')}
                            </span>
                          </p>
                          {meta.length ? (
                            <p className="mt-1 text-xs text-slate-500">{meta.join(' · ')}</p>
                          ) : null}
                          <p className="mt-1 text-xs font-medium text-sky-700">
                            {formatPrice(product)}
                          </p>
                        </div>

                        {!archived ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                              if (window.confirm(t(locale, 'trade.catalog.archiveConfirm'))) {
                                void archiveProduct(product);
                              }
                            }}
                          >
                            {t(locale, 'trade.catalog.archive')}
                          </Button>
                        ) : null}
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>
          ) : null}

          {tab === 'profile' ? (
            <div className="space-y-4">
              <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
                <h2 className="text-sm font-semibold text-slate-900">
                  {t(locale, 'trade.profile.title')}
                </h2>
                <p className="mt-1 text-xs text-slate-500">
                  {t(locale, 'trade.profile.subtitle')}
                </p>

                {profileNotice ? (
                  <p className="mt-3 rounded-xl bg-emerald-50 px-4 py-2 text-xs text-emerald-800">
                    {profileNotice}
                  </p>
                ) : null}

                {profileError ? (
                  <p className="mt-3 rounded-xl bg-rose-50 px-4 py-2 text-xs text-rose-700" role="alert">
                    {profileError}
                  </p>
                ) : null}

                {!profile ? (
                  <p className="mt-3 rounded-xl bg-slate-50 px-4 py-2 text-xs text-slate-600">
                    {t(locale, 'trade.profile.empty')}
                  </p>
                ) : null}

                <form
                  className="mt-4 grid gap-3 sm:grid-cols-2"
                  onSubmit={(event) => {
                    event.preventDefault();
                    void saveProfile();
                  }}
                >
                  {/* Directions : le serveur refuse un profil sans aucune
                      direction — on le dit a l'ecran plutot que de laisser
                      l'utilisateur decouvrir le 422. */}
                  <fieldset className="sm:col-span-2">
                    <legend className="text-xs font-medium text-slate-700">
                      {t(locale, 'trade.profile.directionRequired')}
                    </legend>
                    <div className="mt-2 flex flex-wrap gap-4">
                      {(
                        [
                          ['importEnabled', 'trade.profile.importEnabled'],
                          ['exportEnabled', 'trade.profile.exportEnabled'],
                          ['domesticScope', 'trade.profile.domesticScope'],
                        ] as const
                      ).map(([field, labelKey]) => (
                        <label key={field} className="flex items-center gap-2 text-sm text-slate-700">
                          <input
                            type="checkbox"
                            checked={profileDraft[field]}
                            onChange={(e) =>
                              setProfileDraft({ ...profileDraft, [field]: e.target.checked })
                            }
                            className="h-4 w-4 rounded border-slate-300"
                          />
                          {t(locale, labelKey)}
                        </label>
                      ))}
                    </div>
                  </fieldset>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.profile.hqCountry')}
                    <input
                      maxLength={2}
                      value={profileDraft.hqCountry}
                      onChange={(e) => setProfileDraft({ ...profileDraft, hqCountry: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.profile.defaultCurrency')}
                    <input
                      maxLength={3}
                      value={profileDraft.defaultCurrency}
                      onChange={(e) =>
                        setProfileDraft({ ...profileDraft, defaultCurrency: e.target.value })
                      }
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.profile.defaultIncoterm')}
                    <input
                      maxLength={3}
                      value={profileDraft.defaultIncoterm}
                      onChange={(e) =>
                        setProfileDraft({ ...profileDraft, defaultIncoterm: e.target.value })
                      }
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.profile.marketsServed')}
                    <input
                      value={profileDraft.marketsServed}
                      onChange={(e) =>
                        setProfileDraft({ ...profileDraft, marketsServed: e.target.value })
                      }
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                    <span className="mt-1 block text-[11px] font-normal text-slate-500">
                      {t(locale, 'trade.profile.listHint')}
                    </span>
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.profile.productCategories')}
                    <input
                      value={profileDraft.productCategories}
                      onChange={(e) =>
                        setProfileDraft({ ...profileDraft, productCategories: e.target.value })
                      }
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                    <span className="mt-1 block text-[11px] font-normal text-slate-500">
                      {t(locale, 'trade.profile.listHint')}
                    </span>
                  </label>

                  <label className="text-xs font-medium text-slate-700 sm:col-span-2">
                    {t(locale, 'trade.profile.notes')}
                    <textarea
                      rows={2}
                      value={profileDraft.notes}
                      onChange={(e) => setProfileDraft({ ...profileDraft, notes: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>

                  <div className="sm:col-span-2">
                    <Button type="submit" size="sm" disabled={profileSaving}>
                      {t(locale, 'trade.profile.save')}
                    </Button>
                  </div>
                </form>
              </div>

              {/* ── Publication : acte DISTINCT de l'enregistrement ────────── */}
              <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
                <h2 className="text-sm font-semibold text-slate-900">
                  {t(locale, 'trade.profile.publicationTitle')}
                </h2>
                <p className="mt-1 text-xs text-slate-500">
                  {t(locale, 'trade.profile.publicationSubtitle')}
                </p>

                <p className="mt-3 flex flex-wrap items-center gap-2 text-xs">
                  <span
                    className={`rounded-full px-2 py-0.5 font-medium ${
                      profile?.is_public
                        ? 'bg-emerald-100 text-emerald-800'
                        : 'bg-slate-200 text-slate-700'
                    }`}
                  >
                    {profile?.is_public
                      ? t(locale, 'trade.profile.published')
                      : t(locale, 'trade.profile.notPublished')}
                  </span>
                  {/* Le lien n'est rendu QUE si le serveur dit la fiche
                      joignable : un lien vers une fiche depubliee repondrait
                      404 et passerait pour une reussite. */}
                  {profile?.is_public && profile.public_slug ? (
                    <a
                      href={`/entreprises/${profile.public_slug}`}
                      className="font-medium text-sky-700 underline"
                    >
                      {t(locale, 'trade.profile.publicUrl')}
                    </a>
                  ) : null}
                </p>

                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.profile.publicSlug')}
                    <input
                      value={profileDraft.publicSlug}
                      onChange={(e) =>
                        setProfileDraft({ ...profileDraft, publicSlug: e.target.value })
                      }
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                    <span className="mt-1 block text-[11px] font-normal text-slate-500">
                      {t(locale, 'trade.profile.slugHint')}
                    </span>
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.profile.publicName')}
                    <input
                      value={profileDraft.publicName}
                      onChange={(e) =>
                        setProfileDraft({ ...profileDraft, publicName: e.target.value })
                      }
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.profile.publicCity')}
                    <input
                      value={profileDraft.publicCity}
                      onChange={(e) =>
                        setProfileDraft({ ...profileDraft, publicCity: e.target.value })
                      }
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700 sm:col-span-2">
                    {t(locale, 'trade.profile.publicDescription')}
                    <textarea
                      rows={3}
                      value={profileDraft.publicDescription}
                      onChange={(e) =>
                        setProfileDraft({ ...profileDraft, publicDescription: e.target.value })
                      }
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>
                </div>

                <div className="mt-4 flex flex-wrap gap-2">
                  <Button
                    size="sm"
                    disabled={publishing || !profile}
                    onClick={() => void setPublication(true)}
                  >
                    {t(locale, 'trade.profile.publish')}
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={publishing || !profile?.is_public}
                    onClick={() => void setPublication(false)}
                  >
                    {t(locale, 'trade.profile.unpublish')}
                  </Button>
                </div>
              </div>
            </div>
          ) : null}

          {tab === 'documents' ? (
            <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="text-sm font-semibold text-slate-900">
                    {t(locale, 'trade.documents.title')}
                  </h2>
                  <p className="mt-1 text-xs text-slate-500">
                    {t(locale, 'trade.documents.subtitle')}
                  </p>
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    setDocumentFormOpen((open) => !open);
                    setDocumentError(null);
                    setDocumentNotice(null);
                  }}
                >
                  <FileText className="h-4 w-4" />
                  {t(locale, 'trade.documents.new')}
                </Button>
              </div>

              {documentNotice ? (
                <p className="mt-3 rounded-xl bg-emerald-50 px-4 py-2 text-xs text-emerald-800">
                  {documentNotice}
                </p>
              ) : null}

              {documentError ? (
                <p className="mt-3 rounded-xl bg-rose-50 px-4 py-2 text-xs text-rose-700" role="alert">
                  {documentError}
                </p>
              ) : null}

              {documentFormOpen ? (
                <form
                  className="mt-4 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2"
                  onSubmit={(event) => {
                    event.preventDefault();
                    void uploadDocument();
                  }}
                >
                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.documents.type')}
                    <select
                      value={documentDraft.type}
                      onChange={(e) => setDocumentDraft({ ...documentDraft, type: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    >
                      {[
                        'proforma_invoice',
                        'commercial_invoice',
                        'packing_list',
                        'bill_of_lading',
                        'airway_bill',
                        'certificate_of_origin',
                        'certificate_of_conformity',
                        'insurance_certificate',
                        'customs_declaration',
                        'contract',
                        'other',
                      ].map((type) => (
                        <option key={type} value={type}>
                          {t(locale, `trade.documents.types.${type}`, type)}
                        </option>
                      ))}
                    </select>
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.documents.titleField')}
                    <input
                      required
                      value={documentDraft.title}
                      onChange={(e) => setDocumentDraft({ ...documentDraft, title: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>

                  {/* Rattachement OBLIGATOIRE : un document orphelin serait
                      introuvable dans l'ecran du vendeur. */}
                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.documents.linkTo')}
                    <div className="mt-1 flex gap-2">
                      <select
                        value={documentDraft.linkKind}
                        onChange={(e) =>
                          setDocumentDraft({
                            ...documentDraft,
                            linkKind: e.target.value as 'order' | 'quote' | 'rfq',
                          })
                        }
                        className="rounded-lg border border-slate-300 px-2 py-2 text-sm"
                      >
                        <option value="order">{t(locale, 'trade.orders.title')}</option>
                        <option value="quote">{t(locale, 'trade.sales.title')}</option>
                        <option value="rfq">{t(locale, 'trade.sourcing.title')}</option>
                      </select>
                      <input
                        required
                        inputMode="numeric"
                        value={documentDraft.linkId}
                        onChange={(e) =>
                          setDocumentDraft({ ...documentDraft, linkId: e.target.value })
                        }
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                      />
                    </div>
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.documents.file')}
                    <input
                      type="file"
                      onChange={(e) => setDocumentFile(e.target.files?.[0] ?? null)}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                    <span className="mt-1 block text-[11px] font-normal text-slate-500">
                      {t(locale, 'trade.documents.maxSize')}
                    </span>
                  </label>

                  <div className="flex items-end gap-2 sm:col-span-2">
                    <Button type="submit" size="sm" disabled={documentSaving}>
                      {t(locale, 'trade.documents.upload')}
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        setDocumentFormOpen(false);
                        setDocumentFile(null);
                        setDocumentError(null);
                      }}
                    >
                      {t(locale, 'trade.documents.cancel')}
                    </Button>
                  </div>
                </form>
              ) : null}

              {documents.length === 0 ? (
                <p className="px-1 py-6 text-sm text-slate-500">
                  {t(locale, 'trade.documents.empty')}
                </p>
              ) : (
                <ul className="mt-4 space-y-2">
                  {documents.map((document) => (
                    <li
                      key={String(document.id)}
                      className="flex flex-wrap items-start justify-between gap-3 border-t border-slate-100 pt-3"
                    >
                      <div className="min-w-0">
                        <p className="text-sm font-medium text-slate-900">
                          {document.title ?? document.original_name ?? '—'}
                        </p>
                        <p className="mt-1 text-xs text-slate-500">
                          {/* Le libelle vient de l'enum serveur : un document ne
                              peut pas s'auto-nommer « Facture commerciale ». */}
                          {document.type_label ?? t(locale, `trade.documents.types.${document.type}`, document.type)}
                          {document.size_bytes
                            ? ` · ${Math.round(document.size_bytes / 1024)} Ko`
                            : ''}
                        </p>
                      </div>
                      <div className="flex flex-wrap gap-1.5">
                        {document.download_url ? (
                          <a
                            href={document.download_url}
                            className="rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                          >
                            {t(locale, 'trade.documents.download')}
                          </a>
                        ) : null}
                        <button
                          type="button"
                          onClick={() => {
                            if (window.confirm(t(locale, 'trade.documents.deleteConfirm'))) {
                              void deleteDocument(document.id);
                            }
                          }}
                          className="rounded-lg border border-rose-200 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50"
                        >
                          {t(locale, 'trade.documents.delete')}
                        </button>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          ) : null}

          {tab === 'promotion' ? (
            <div className="space-y-4">
              {promotionError ? (
                <div className="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
                  <p className="flex items-center gap-2 font-medium">
                    <AlertTriangle className="h-4 w-4" />
                    {promotionError}
                  </p>
                </div>
              ) : null}

              {!promotionKit && !promotionError ? (
                <p className="rounded-2xl bg-white/70 px-5 py-6 text-sm text-slate-500 ring-1 ring-slate-200">
                  {t(locale, 'trade.promotion.empty')}
                </p>
              ) : null}

              {promotionKit ? (
                <>
                  <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
                    <h2 className="text-sm font-semibold text-slate-900">
                      {t(locale, 'trade.promotion.publication')}
                    </h2>
                    <p className="mt-2 flex flex-wrap items-center gap-2 text-xs">
                      <span
                        className={`rounded-full px-2 py-0.5 font-medium ${
                          promotionKit.publication.is_public
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-slate-200 text-slate-700'
                        }`}
                      >
                        {promotionKit.publication.is_public
                          ? t(locale, 'trade.promotion.published')
                          : t(locale, 'trade.promotion.notPublished')}
                      </span>
                      {/* `url` vaut `null` quand la fiche n'est pas joignable :
                          le serveur ne rend jamais un lien qui mene a un 404. */}
                      {promotionKit.publication.url ? (
                        <a
                          href={promotionKit.publication.url}
                          className="font-medium text-sky-700 underline"
                        >
                          {t(locale, 'trade.promotion.openPublicPage')}
                        </a>
                      ) : null}
                    </p>
                  </div>

                  <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
                    <h2 className="text-sm font-semibold text-slate-900">
                      {t(locale, 'trade.promotion.checklist')}
                    </h2>
                    <ul className="mt-3 space-y-2">
                      {promotionKit.checklist.map((item) => (
                        <li key={item.key} className="flex items-start gap-2 text-sm">
                          <span
                            className={`mt-0.5 rounded-full px-2 py-0.5 text-[11px] font-medium ${
                              item.done
                                ? 'bg-emerald-100 text-emerald-800'
                                : 'bg-amber-100 text-amber-800'
                            }`}
                          >
                            {item.done
                              ? t(locale, 'trade.promotion.done')
                              : t(locale, 'trade.promotion.todo')}
                          </span>
                          <span className="text-slate-700">{item.hint}</span>
                        </li>
                      ))}
                    </ul>
                  </div>

                  <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
                    <h2 className="text-sm font-semibold text-slate-900">
                      {t(locale, 'trade.promotion.draft')}
                    </h2>
                    {promotionKit.draft.headline ? (
                      <p className="mt-3 text-sm font-medium text-slate-900">
                        {promotionKit.draft.headline}
                      </p>
                    ) : null}
                    {promotionKit.draft.summary ? (
                      <p className="mt-2 text-sm text-slate-700">{promotionKit.draft.summary}</p>
                    ) : null}
                    {promotionKit.draft.highlights.length ? (
                      <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
                        {promotionKit.draft.highlights.map((highlight) => (
                          <li key={highlight}>{highlight}</li>
                        ))}
                      </ul>
                    ) : null}
                    {/* Le kit ne publie RIEN : on le dit a l'ecran, pas
                        seulement dans le code. */}
                    <p className="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">
                      {promotionKit.disclaimer || t(locale, 'trade.promotion.disclaimer')}
                    </p>
                  </div>
                </>
              ) : null}
            </div>
          ) : null}

          {tab === 'costs' ? (
            <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
              <h2 className="text-sm font-semibold text-slate-900">
                {t(locale, 'trade.costs.title')}
              </h2>
              <p className="mt-1 text-xs text-slate-500">{t(locale, 'trade.costs.subtitle')}</p>
              <div className="mt-3">{renderTable(costEstimates, 'trade-costs-empty', 'costs')}</div>
              {/* Cadre légal : jamais présenté comme une décision douanière. */}
              <p className="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">
                {t(locale, 'trade.costs.disclaimer')}
              </p>
            </div>
          ) : null}
        </>
      )}
    </ModulePageShell>
  );
}
