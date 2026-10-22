'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { AlertTriangle, LocateFixed, MapPin, MessageSquare, Search } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import type { AppLocale } from '@/lib/i18n';
import { catalogDirection, t } from '@/lib/i18n/locale-catalog';
import {
  buildDiscoveryQuery,
  type PublicDiscoveryItem,
  type PublicDiscoveryMeta,
} from '@/lib/public-discovery-api';
import { startConversation } from '@/lib/public-conversation-api';
import ContactDialog from './contact-dialog';

/**
 * SUPERAPP-P0 (#8611) — explorateur client de la passerelle publique.
 *
 * Le PREMIER rendu (sans `near`) vient du Server Component `/decouvrir`
 * (données SSR passées en props → HTML indexable). Les interactions
 * (recherche, catégorie, « Autour de moi », pagination en mode proximité)
 * raffinent ensuite côté client via le proxy same-origin `/api/v1`.
 *
 * Aucune logique métier n'est dupliquée ici : les catégories proviennent des
 * types canoniques renvoyés par l'API (`meta.types`) et chaque résultat pointe
 * vers la fiche publique verticale fournie par l'API (`item.url`).
 */

interface DiscoveryExplorerProps {
  locale: AppLocale;
  initialItems: PublicDiscoveryItem[];
  initialMeta: PublicDiscoveryMeta | null;
  /** true si le fetch SSR initial a échoué (API indisponible). */
  initialError: boolean;
  initialQuery: { q: string; city: string; type: string; page: number };
}

type GeoState = 'idle' | 'locating' | 'active' | 'denied' | 'unsupported';

/**
 * Actions publiques par type, déclarées par le BACKEND (#8761).
 *
 * Remplace la liste `BOOKING_TYPES` codée en dur : elle dérivait dès qu'une
 * verticale changeait de capacité et affichait potentiellement un bouton
 * inopérant. La source de vérité unique est `meta.actions`
 * (`PublicDiscoverableProvider::supportedActions()`).
 *
 * Fail-closed : sans `meta.actions`, aucune action n'est supposée disponible.
 */
function supportsAction(
  actions: Record<string, string[]> | undefined,
  type: string,
  action: string,
): boolean {
  if (!actions) return false;
  const list = actions[type];
  return Array.isArray(list) && list.includes(action);
}

const TYPE_LABEL_KEYS: Record<string, string> = {
  showcase: 'discovery.typeShowcase',
  restaurant: 'discovery.typeRestaurant',
  hospitality_property: 'discovery.typeHospitalityProperty',
  // P1-02 — les deux verticales dont la fiche publique a été livrée avec ce
  // lot. Sans libellé, la carte affichait le type canonique brut
  // (`retail_shop`, `vtc_operator`) au lieu d'un mot lisible.
  retail_shop: 'discovery.typeRetailShop',
  vtc_operator: 'discovery.typeVtcOperator',
};

function typeLabel(locale: AppLocale, type: string): string {
  const key = TYPE_LABEL_KEYS[type];
  return key ? t(locale, key) : type;
}

function directoryHref(query: { q: string; city: string; type: string; page: number }): string {
  const qs = buildDiscoveryQuery(query);
  return qs ? `/decouvrir?${qs}` : '/decouvrir';
}

export default function DiscoveryExplorer({
  locale,
  initialItems,
  initialMeta,
  initialError,
  initialQuery,
}: DiscoveryExplorerProps) {
  const dir = catalogDirection(locale);
  const router = useRouter();

  const [q, setQ] = useState(initialQuery.q);
  const [city, setCity] = useState(initialQuery.city);
  const [type, setType] = useState(initialQuery.type);
  const [items, setItems] = useState(initialItems);
  const [meta, setMeta] = useState(initialMeta);
  const [page, setPage] = useState(initialQuery.page);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(initialError);
  const [geoState, setGeoState] = useState<GeoState>('idle');
  const [near, setNear] = useState<string | null>(null);
  /** Résultat dont le formulaire de contact est ouvert (null = fermé). */
  const [contactTarget, setContactTarget] = useState<PublicDiscoveryItem | null>(null);
  /** Slug du professionnel dont la conversation est en cours d'ouverture. */
  const [startingSlug, setStartingSlug] = useState<string | null>(null);
  /** Slug dont la conversation a été ouverte avec succès (confirmation). */
  const [startedSlug, setStartedSlug] = useState<string | null>(null);
  /** Slug dont l'ouverture de conversation a échoué. */
  const [startErrorSlug, setStartErrorSlug] = useState<string | null>(null);

  /**
   * #8760 — Démarrer une conversation depuis une fiche publique.
   *
   * Le couple (`type`, `slug`) est le SEUL identifiant transmis : le tenant est
   * résolu côté serveur, exactement comme pour le contact. Aucun `company_id`
   * ne transite par le navigateur.
   *
   * États explicites : `401` → l'utilisateur doit se connecter (on l'y envoie
   * avec un retour vers `/conversations`) ; `404` → profil indisponible ;
   * autre → erreur réseau avec possibilité de réessayer. Jamais de faux succès.
   */
  const handleStartConversation = async (item: PublicDiscoveryItem) => {
    if (startingSlug) return;

    setStartingSlug(item.slug);
    setStartErrorSlug(null);
    setStartedSlug(null);

    const result = await startConversation({
      target_type: item.type,
      target_slug: item.slug,
      body: t(locale, 'discovery.conversationStartBody'),
    });

    setStartingSlug(null);

    if (result.status === 'ok') {
      setStartedSlug(item.slug);
      return;
    }

    if (result.status === 'unauthenticated') {
      // Le client doit se connecter : on l'y amène en conservant la destination.
      router.push(`/auth/login?next=${encodeURIComponent('/conversations')}`);
      return;
    }

    setStartErrorSlug(item.slug);
  };

  const knownTypes = meta?.types ?? [];

  const fetchResults = async (params: {
    q: string;
    city: string;
    type: string;
    page: number;
    near: string | null;
  }) => {
    setLoading(true);
    setError(false);
    try {
      const qs = buildDiscoveryQuery(
        {
          q: params.q,
          city: params.city,
          type: params.type,
          page: params.page,
          near: params.near ?? undefined,
        },
        knownTypes,
      );
      const res = await apiFetch(`/public/discover${qs ? `?${qs}` : ''}`, { _cacheBust: true });
      if (!res.ok) {
        throw new Error(String(res.status));
      }
      const json = (await res.json()) as {
        data?: PublicDiscoveryItem[];
        meta?: PublicDiscoveryMeta;
      };
      setItems(Array.isArray(json.data) ? json.data : []);
      setMeta(json.meta ?? null);
      setPage(params.page);
      if (typeof window !== 'undefined' && !params.near) {
        // URL partageable/crawlable — sans near (position = donnée volatile).
        window.history.replaceState(null, '', directoryHref(params));
      }
    } catch {
      setError(true);
    } finally {
      setLoading(false);
    }
  };

  const submitSearch = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    void fetchResults({ q, city, type, page: 1, near });
  };

  const locateMe = () => {
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
      setGeoState('unsupported');
      return;
    }
    setGeoState('locating');
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const coords = `${position.coords.latitude.toFixed(5)},${position.coords.longitude.toFixed(5)}`;
        setNear(coords);
        setGeoState('active');
        void fetchResults({ q, city, type, page: 1, near: coords });
      },
      () => {
        // Refus ou indisponibilité : on retombe sur la saisie manuelle de
        // ville/zone — jamais de collecte en arrière-plan.
        setGeoState('denied');
      },
      { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 },
    );
  };

  const clearNear = () => {
    setNear(null);
    setGeoState('idle');
    void fetchResults({ q, city, type, page: 1, near: null });
  };

  const goToPage = (target: number) => {
    void fetchResults({ q, city, type, page: target, near });
  };

  const lastPage = meta?.last_page ?? 1;

  return (
    <div dir={dir}>
      <form onSubmit={submitSearch} className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label className="flex flex-col gap-1 text-xs font-semibold text-slate-600">
          {t(locale, 'discovery.searchLabel')}
          <input
            type="search"
            value={q}
            onChange={(event) => setQ(event.target.value)}
            placeholder={t(locale, 'discovery.searchPlaceholder')}
            maxLength={120}
            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-900"
          />
        </label>

        <label className="flex flex-col gap-1 text-xs font-semibold text-slate-600">
          {t(locale, 'discovery.cityLabel')}
          <input
            type="text"
            value={city}
            onChange={(event) => setCity(event.target.value)}
            placeholder={t(locale, 'discovery.cityPlaceholder')}
            maxLength={120}
            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-900"
          />
        </label>

        <label className="flex flex-col gap-1 text-xs font-semibold text-slate-600">
          {t(locale, 'discovery.categoryLabel')}
          <select
            value={type}
            onChange={(event) => setType(event.target.value)}
            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-900"
          >
            <option value="">{t(locale, 'discovery.categoryAll')}</option>
            {knownTypes.map((known) => (
              <option key={known} value={known}>
                {typeLabel(locale, known)}
              </option>
            ))}
          </select>
        </label>

        <div className="flex items-end gap-2">
          <button
            type="submit"
            className="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
          >
            <Search className="h-4 w-4" aria-hidden />
            {t(locale, 'discovery.search')}
          </button>
          <button
            type="button"
            onClick={locateMe}
            className="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700"
          >
            <LocateFixed className="h-4 w-4" aria-hidden />
            {geoState === 'locating' ? t(locale, 'discovery.locating') : t(locale, 'discovery.nearMe')}
          </button>
        </div>
      </form>

      {geoState === 'denied' && (
        <p className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
          {t(locale, 'discovery.geoDenied')}
        </p>
      )}
      {geoState === 'unsupported' && (
        <p className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
          {t(locale, 'discovery.geoUnsupported')}
        </p>
      )}
      {geoState === 'active' && (
        <p className="mb-4 flex items-center gap-2 text-sm text-slate-600">
          <MapPin className="h-4 w-4" aria-hidden />
          {t(locale, 'discovery.nearActive')}
          <button type="button" onClick={clearNear} className="font-semibold underline">
            {t(locale, 'discovery.clearNear')}
          </button>
        </p>
      )}

      {loading && <p className="py-8 text-center text-sm text-slate-500">{t(locale, 'discovery.loading')}</p>}

      {!loading && error && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
          <AlertTriangle className="mx-auto mb-2 h-6 w-6 text-red-600" aria-hidden />
          <p className="text-sm text-red-800">{t(locale, 'discovery.loadError')}</p>
          <button
            type="button"
            onClick={() => void fetchResults({ q, city, type, page, near })}
            className="mt-3 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white"
          >
            {t(locale, 'discovery.retry')}
          </button>
        </div>
      )}

      {!loading && !error && items.length === 0 && (
        <p className="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
          {t(locale, 'discovery.noResults')}
        </p>
      )}

      {!loading && !error && items.length > 0 && (
        <>
          <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
            {meta?.total ?? items.length} {t(locale, 'discovery.resultsCount')}
          </p>
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {items.map((item) => (
              <li
                key={`${item.type}-${item.slug}`}
                className="flex flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
              >
                <span className="mb-1 text-[11px] font-bold uppercase tracking-wide text-amber-700">
                  {typeLabel(locale, item.type)}
                </span>
                <h2 className="text-base font-bold text-slate-950">{item.name}</h2>
                {item.category && <p className="text-xs text-slate-500">{item.category}</p>}
                {item.city && (
                  <p className="mt-1 flex items-center gap-1 text-xs text-slate-500">
                    <MapPin className="h-3 w-3" aria-hidden />
                    {item.city}
                  </p>
                )}
                {typeof item.distance_km === 'number' && (
                  <p className="mt-1 text-xs font-semibold text-slate-600">
                    {item.distance_km} {t(locale, 'discovery.distanceSuffix')}
                  </p>
                )}
                {item.description && (
                  <p className="mt-2 line-clamp-3 text-sm text-slate-600">{item.description}</p>
                )}

                <div className="mt-4 flex flex-wrap gap-2">
                  {item.url && (
                    <Link
                      href={item.url}
                      className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white"
                    >
                      {t(locale, 'discovery.viewProfile')}
                    </Link>
                  )}

                  {/*
                    #8760 — Contact : ouvre le formulaire public. Le couple
                    (type, slug) est le SEUL identifiant transmis ; le tenant
                    est résolu côté serveur. Toujours proposé : une demande de
                    contact ne dépend d'aucune capacité de réservation.
                  */}
                  <button
                    type="button"
                    onClick={() => setContactTarget(item)}
                    className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700"
                  >
                    <MessageSquare className="h-3.5 w-3.5" aria-hidden />
                    {t(locale, 'discovery.contact')}
                  </button>

                  {/*
                    #8760 — Démarrer une conversation (messagerie suivie).
                    Distinct du contact ponctuel : le client retrouve l'échange
                    et la réponse du professionnel dans `/conversations`.
                    Nécessite un compte — un `401` redirige vers la connexion.
                  */}
                  <button
                    type="button"
                    onClick={() => void handleStartConversation(item)}
                    disabled={startingSlug === item.slug}
                    className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-50"
                  >
                    <MessageSquare className="h-3.5 w-3.5" aria-hidden />
                    {startingSlug === item.slug
                      ? t(locale, 'discovery.conversationSending')
                      : t(locale, 'discovery.conversationStart')}
                  </button>

                  {startedSlug === item.slug && (
                    <p className="w-full text-xs font-medium text-emerald-700" role="status">
                      {t(locale, 'discovery.conversationStarted')}{' '}
                      <Link href="/conversations" className="underline">
                        {t(locale, 'discovery.conversationsTitle')}
                      </Link>
                    </p>
                  )}

                  {startErrorSlug === item.slug && (
                    <p className="w-full text-xs font-medium text-red-700" role="alert">
                      {t(locale, 'discovery.conversationSendError')}
                    </p>
                  )}

                  {/*
                    #8761 — Réservation : rendue UNIQUEMENT si la verticale
                    dÉCLARE l'action `booking` (source de vérité backend) ET
                    que la fiche publique existe. Sinon, état explicite
                    « indisponible » — jamais un bouton inopérant.
                  */}
                  {supportsAction(meta?.actions, item.type, 'booking') &&
                    (item.url ? (
                      <Link
                        href={item.url}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700"
                      >
                        {t(locale, 'discovery.book')}
                      </Link>
                    ) : (
                      <span className="text-xs text-slate-400">
                        {t(locale, 'discovery.bookingUnavailable')}
                      </span>
                    ))}

                  {/*
                    #8761 — Commande : même règle, action `order`. Aucune
                    verticale sans service métier réel ne peut donc afficher ce
                    CTA.
                  */}
                  {supportsAction(meta?.actions, item.type, 'order') &&
                    (item.url ? (
                      <Link
                        href={item.url}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700"
                      >
                        {t(locale, 'discovery.order')}
                      </Link>
                    ) : (
                      <span className="text-xs text-slate-400">
                        {t(locale, 'discovery.orderUnavailable')}
                      </span>
                    ))}
                </div>
              </li>
            ))}
          </ul>

          {lastPage > 1 && (
            <nav className="mt-6 flex items-center justify-center gap-3 text-sm">
              {near ? (
                <button
                  type="button"
                  disabled={page <= 1}
                  onClick={() => goToPage(page - 1)}
                  className="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold disabled:opacity-40"
                >
                  {t(locale, 'discovery.previous')}
                </button>
              ) : (
                page > 1 && (
                  <Link
                    href={directoryHref({ q, city, type, page: page - 1 })}
                    className="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold"
                  >
                    {t(locale, 'discovery.previous')}
                  </Link>
                )
              )}

              <span className="text-slate-500">
                {t(locale, 'discovery.pageLabel')} {page} / {lastPage}
              </span>

              {near ? (
                <button
                  type="button"
                  disabled={page >= lastPage}
                  onClick={() => goToPage(page + 1)}
                  className="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold disabled:opacity-40"
                >
                  {t(locale, 'discovery.next')}
                </button>
              ) : (
                page < lastPage && (
                  <Link
                    href={directoryHref({ q, city, type, page: page + 1 })}
                    className="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold"
                  >
                    {t(locale, 'discovery.next')}
                  </Link>
                )
              )}
            </nav>
          )}
        </>
      )}

      {contactTarget && (
        <ContactDialog
          locale={locale}
          targetType={contactTarget.type}
          targetSlug={contactTarget.slug}
          targetName={contactTarget.name}
          onClose={() => setContactTarget(null)}
        />
      )}
    </div>
  );
}
