'use client';

/**
 * RESTO-904 (#7749) — Présence en ligne du restaurateur.
 *
 * Réglages du profil public par succursale (`GET/PUT
 * /restaurant/branches/{branch}/public-profile`, contrat RESTO-901/#7746) :
 * opt-in annuaire (is_public), slug public, type d'établissement, cuisines,
 * description, image de couverture et géolocalisation (bouton
 * navigator.geolocation). Section « plats publiés en ligne » : bascule
 * `is_published_online` par produit (`PATCH
 * /restaurant/products/{product}/publication`, optimiste + rollback).
 *
 * Modération des avis (RESTO-902) : les endpoints `/restaurant/reviews*`
 * n'existent pas encore côté backend — la section est rendue derrière une
 * garde d'erreur réseau (état « module à venir ») et s'activera toute seule
 * quand le lot RESTO-902 sera livré.
 */
import { useCallback, useEffect, useState } from 'react';
import { Globe, MapPin } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { BranchSelect, useRestaurantBranches } from '@/components/restaurant/BranchSelect';
import { ESTABLISHMENT_TYPES } from '@/components/restaurant/establishment-types';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type PublicProfile = {
  id: number;
  name: string;
  is_public: boolean;
  public_slug: string | null;
  establishment_type: string | null;
  cuisine_types: string[] | null;
  public_description: string | null;
  cover_image_url: string | null;
  latitude: number | null;
  longitude: number | null;
};

type ProfileForm = {
  is_public: boolean;
  public_slug: string;
  establishment_type: string;
  cuisine_types: string;
  public_description: string;
  cover_image_url: string;
  latitude: string;
  longitude: string;
};

type OnlineProduct = {
  id: number;
  code: string;
  name: string;
  branch_id: number | null;
  price_minor: number;
  is_available: boolean;
  is_published_online?: boolean;
};

type PendingReview = {
  id: number;
  author_name?: string | null;
  rating?: number | null;
  comment?: string | null;
  created_at?: string | null;
};

const EMPTY_FORM: ProfileForm = {
  is_public: false,
  public_slug: '',
  establishment_type: '',
  cuisine_types: '',
  public_description: '',
  cover_image_url: '',
  latitude: '',
  longitude: '',
};

function profileToForm(profile: PublicProfile): ProfileForm {
  return {
    is_public: Boolean(profile.is_public),
    public_slug: profile.public_slug ?? '',
    establishment_type: profile.establishment_type ?? '',
    cuisine_types: Array.isArray(profile.cuisine_types) ? profile.cuisine_types.join(', ') : '',
    public_description: profile.public_description ?? '',
    cover_image_url: profile.cover_image_url ?? '',
    latitude: profile.latitude === null || profile.latitude === undefined ? '' : String(profile.latitude),
    longitude: profile.longitude === null || profile.longitude === undefined ? '' : String(profile.longitude),
  };
}

export default function RestaurantOnlinePage() {
  const locale = getPreferredLocale();
  const { branches, error: branchesError } = useRestaurantBranches();
  const [branchId, setBranchId] = useState<number | null>(null);
  const [form, setForm] = useState<ProfileForm>(EMPTY_FORM);
  const [profileLoading, setProfileLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState('');
  const [geoError, setGeoError] = useState('');
  const [products, setProducts] = useState<OnlineProduct[]>([]);
  const [productsError, setProductsError] = useState('');
  const [publishError, setPublishError] = useState('');
  const [reviews, setReviews] = useState<PendingReview[]>([]);
  const [reviewsAvailable, setReviewsAvailable] = useState(false);
  const [reviewError, setReviewError] = useState('');

  useEffect(() => {
    if (branchId === null && branches.length > 0) {
      setBranchId(branches[0].id);
    }
  }, [branches, branchId]);

  const loadProfile = useCallback(async () => {
    if (branchId === null) return;
    setProfileLoading(true);
    setError('');
    setSaved(false);
    try {
      const res = await apiFetch(`/restaurant/branches/${branchId}/public-profile`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: PublicProfile };
      setForm(payload.data ? profileToForm(payload.data) : EMPTY_FORM);
    } catch {
      setError(t(locale, 'restaurant.online.loadError'));
    } finally {
      setProfileLoading(false);
    }
  }, [branchId, locale]);

  const loadProducts = useCallback(async () => {
    if (branchId === null) return;
    setProductsError('');
    try {
      const res = await apiFetch('/restaurant/products?per_page=1000');
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: OnlineProduct[] };
      const list = Array.isArray(payload?.data) ? payload.data : [];
      // branch_id null = produit disponible dans toutes les branches.
      setProducts(list.filter((p) => p.branch_id === null || p.branch_id === branchId));
    } catch {
      setProductsError(t(locale, 'restaurant.online.productsError'));
    }
  }, [branchId, locale]);

  // RESTO-902 pas encore livré : toute erreur (réseau, 404…) laisse la
  // section en état « module à venir » sans polluer la page.
  const loadReviews = useCallback(async () => {
    try {
      const res = await apiFetch('/restaurant/reviews?status=pending');
      if (!res.ok) {
        setReviewsAvailable(false);
        return;
      }
      const payload = (await res.json()) as { data?: PendingReview[] };
      setReviews(Array.isArray(payload?.data) ? payload.data : []);
      setReviewsAvailable(true);
    } catch {
      setReviewsAvailable(false);
    }
  }, []);

  useEffect(() => {
    void loadProfile();
    void loadProducts();
    void loadReviews();
  }, [loadProfile, loadProducts, loadReviews]);

  const update = (patch: Partial<ProfileForm>) => {
    setSaved(false);
    setForm((prev) => ({ ...prev, ...patch }));
  };

  const save = async () => {
    if (branchId === null) return;
    setSaving(true);
    setError('');
    setSaved(false);
    try {
      const body = {
        is_public: form.is_public,
        public_slug: form.public_slug.trim() === '' ? null : form.public_slug.trim(),
        establishment_type: form.establishment_type === '' ? null : form.establishment_type,
        cuisine_types: form.cuisine_types
          .split(',')
          .map((s) => s.trim())
          .filter((s) => s !== ''),
        public_description: form.public_description.trim() === '' ? null : form.public_description,
        cover_image_url: form.cover_image_url.trim() === '' ? null : form.cover_image_url.trim(),
        latitude: form.latitude.trim() === '' ? null : Number(form.latitude),
        longitude: form.longitude.trim() === '' ? null : Number(form.longitude),
      };
      const res = await apiFetch(`/restaurant/branches/${branchId}/public-profile`, {
        method: 'PUT',
        body: JSON.stringify(body),
      });
      if (!res.ok) {
        const payload = await res.json().catch(() => ({}));
        throw new Error((payload as { message?: string }).message ?? `HTTP ${res.status}`);
      }
      const payload = (await res.json()) as { data?: PublicProfile };
      if (payload.data) setForm(profileToForm(payload.data));
      setSaved(true);
    } catch (e) {
      setError(e instanceof Error && e.message ? e.message : t(locale, 'restaurant.online.saveError'));
    } finally {
      setSaving(false);
    }
  };

  const useMyLocation = () => {
    setGeoError('');
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
      setGeoError(t(locale, 'restaurant.online.geoUnavailable'));
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (position) => {
        update({
          latitude: position.coords.latitude.toFixed(6),
          longitude: position.coords.longitude.toFixed(6),
        });
      },
      () => setGeoError(t(locale, 'restaurant.online.geoError')),
    );
  };

  // Bascule optimiste de publication d'un plat, rollback en cas d'échec.
  const togglePublication = async (product: OnlineProduct) => {
    const next = !(product.is_published_online ?? false);
    setPublishError('');
    setProducts((prev) => prev.map((p) => (p.id === product.id ? { ...p, is_published_online: next } : p)));
    try {
      const res = await apiFetch(`/restaurant/products/${product.id}/publication`, {
        method: 'PATCH',
        body: JSON.stringify({ is_published_online: next }),
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
    } catch {
      setProducts((prev) => prev.map((p) => (p.id === product.id ? { ...p, is_published_online: !next } : p)));
      setPublishError(t(locale, 'restaurant.online.publishError'));
    }
  };

  const moderateReview = async (id: number, action: 'publish' | 'reject') => {
    setReviewError('');
    try {
      const res = await apiFetch(`/restaurant/reviews/${id}/${action}`, { method: 'POST' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      setReviews((prev) => prev.filter((r) => r.id !== id));
    } catch {
      setReviewError(t(locale, 'restaurant.online.reviewActionError'));
    }
  };

  const slug = form.public_slug.trim();

  return (
    <ModulePageShell
      icon={Globe}
      title={t(locale, 'restaurant.online.title')}
      description={t(locale, 'restaurant.online.subtitle')}
    >
      {branchesError ? <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{branchesError}</p> : null}
      {error ? <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p> : null}

      <div className="flex flex-wrap items-center gap-3">
        <label className="text-sm font-semibold text-slate-700" htmlFor="online-branch">
          {t(locale, 'restaurant.branch')}
        </label>
        <BranchSelect branches={branches} value={branchId} onChange={setBranchId} />
      </div>

      {branches.length === 0 ? (
        <p className="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">{t(locale, 'restaurant.online.noBranch')}</p>
      ) : (
        <>
          <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 className="font-bold text-slate-900">{t(locale, 'restaurant.online.profileTitle')}</h3>
            {profileLoading ? (
              <p className="mt-3 text-sm text-slate-500">{t(locale, 'restaurant.online.loading')}</p>
            ) : (
              <div className="mt-4 space-y-4">
                <label className="flex items-center gap-3">
                  <input
                    type="checkbox"
                    checked={form.is_public}
                    onChange={(e) => update({ is_public: e.target.checked })}
                    className="h-5 w-5 rounded border-slate-300 text-emerald-600"
                  />
                  <span>
                    <span className="block text-sm font-semibold text-slate-800">{t(locale, 'restaurant.online.isPublic')}</span>
                    <span className="block text-xs text-slate-500">{t(locale, 'restaurant.online.isPublicHint')}</span>
                  </span>
                </label>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div>
                    <label className="block text-sm font-semibold text-slate-700" htmlFor="online-slug">
                      {t(locale, 'restaurant.online.slug')}
                    </label>
                    <input
                      id="online-slug"
                      type="text"
                      value={form.public_slug}
                      onChange={(e) => update({ public_slug: e.target.value })}
                      placeholder={t(locale, 'restaurant.online.slugHint')}
                      className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    />
                    <p className="mt-1 text-xs text-slate-500">
                      {t(locale, 'restaurant.online.publicUrl')}{' '}
                      <span className="font-mono text-emerald-700">/restaurants/{slug === '' ? '…' : slug}</span>
                    </p>
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-slate-700" htmlFor="online-type">
                      {t(locale, 'restaurant.establishmentType.label')}
                    </label>
                    <select
                      id="online-type"
                      value={form.establishment_type}
                      onChange={(e) => update({ establishment_type: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                    >
                      <option value="">—</option>
                      {ESTABLISHMENT_TYPES.map((type) => (
                        <option key={type} value={type}>
                          {t(locale, `restaurant.establishmentType.${type}`)}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-slate-700" htmlFor="online-cuisines">
                      {t(locale, 'restaurant.online.cuisineTypes')}
                    </label>
                    <input
                      id="online-cuisines"
                      type="text"
                      value={form.cuisine_types}
                      onChange={(e) => update({ cuisine_types: e.target.value })}
                      placeholder={t(locale, 'restaurant.online.cuisineTypesHint')}
                      className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-slate-700" htmlFor="online-cover">
                      {t(locale, 'restaurant.online.coverImage')}
                    </label>
                    <input
                      id="online-cover"
                      type="url"
                      value={form.cover_image_url}
                      onChange={(e) => update({ cover_image_url: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-semibold text-slate-700" htmlFor="online-description">
                    {t(locale, 'restaurant.online.description')}
                  </label>
                  <textarea
                    id="online-description"
                    value={form.public_description}
                    onChange={(e) => update({ public_description: e.target.value })}
                    rows={4}
                    maxLength={2000}
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                  />
                </div>

                <div className="grid grid-cols-1 items-end gap-4 sm:grid-cols-3">
                  <div>
                    <label className="block text-sm font-semibold text-slate-700" htmlFor="online-lat">
                      {t(locale, 'restaurant.online.latitude')}
                    </label>
                    <input
                      id="online-lat"
                      type="number"
                      step="0.000001"
                      min={-90}
                      max={90}
                      value={form.latitude}
                      onChange={(e) => update({ latitude: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-slate-700" htmlFor="online-lng">
                      {t(locale, 'restaurant.online.longitude')}
                    </label>
                    <input
                      id="online-lng"
                      type="number"
                      step="0.000001"
                      min={-180}
                      max={180}
                      value={form.longitude}
                      onChange={(e) => update({ longitude: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    />
                  </div>
                  <button
                    type="button"
                    onClick={useMyLocation}
                    className="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                  >
                    <MapPin className="h-4 w-4" /> {t(locale, 'restaurant.online.useMyLocation')}
                  </button>
                </div>
                {geoError ? <p className="text-sm text-amber-700">{geoError}</p> : null}

                <div className="flex items-center gap-3">
                  <button
                    type="button"
                    disabled={saving}
                    onClick={() => void save()}
                    className="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                  >
                    {saving ? t(locale, 'restaurant.online.saving') : t(locale, 'restaurant.online.save')}
                  </button>
                  {saved ? <p className="text-sm font-medium text-emerald-700">{t(locale, 'restaurant.online.saved')}</p> : null}
                </div>
              </div>
            )}
          </section>

          <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 className="font-bold text-slate-900">{t(locale, 'restaurant.online.productsTitle')}</h3>
            <p className="mt-1 text-sm text-slate-500">{t(locale, 'restaurant.online.productsHint')}</p>
            {productsError ? <p className="mt-3 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{productsError}</p> : null}
            {publishError ? <p className="mt-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">{publishError}</p> : null}
            {products.length === 0 ? (
              <p className="mt-4 text-sm text-slate-500">{t(locale, 'restaurant.online.productsEmpty')}</p>
            ) : (
              <ul className="mt-4 divide-y divide-slate-100">
                {products.map((product) => {
                  const published = product.is_published_online ?? false;
                  return (
                    <li key={product.id} className="flex items-center justify-between gap-4 py-3">
                      <div>
                        <p className="text-sm font-semibold text-slate-900">{product.name}</p>
                        <p className="text-xs text-slate-500">
                          {product.code} · {product.price_minor}
                        </p>
                      </div>
                      <button
                        type="button"
                        role="switch"
                        aria-checked={published}
                        aria-label={`${t(locale, 'restaurant.online.publishToggle')} ${product.name}`}
                        onClick={() => void togglePublication(product)}
                        className={`rounded-full px-3 py-1 text-xs font-bold ${
                          published ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'
                        }`}
                      >
                        {published ? t(locale, 'restaurant.online.published') : t(locale, 'restaurant.online.unpublished')}
                      </button>
                    </li>
                  );
                })}
              </ul>
            )}
          </section>

          <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 className="font-bold text-slate-900">{t(locale, 'restaurant.online.reviewsTitle')}</h3>
            {reviewError ? <p className="mt-3 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{reviewError}</p> : null}
            {!reviewsAvailable ? (
              <p className="mt-3 text-sm text-slate-500">{t(locale, 'restaurant.online.reviewsComingSoon')}</p>
            ) : reviews.length === 0 ? (
              <p className="mt-3 text-sm text-slate-500">{t(locale, 'restaurant.online.reviewsEmpty')}</p>
            ) : (
              <ul className="mt-4 divide-y divide-slate-100">
                {reviews.map((review) => (
                  <li key={review.id} className="flex items-start justify-between gap-4 py-3">
                    <div>
                      <p className="text-sm font-semibold text-slate-900">
                        {review.author_name ?? '—'}
                        {typeof review.rating === 'number' ? ` · ${review.rating}/5` : ''}
                      </p>
                      {review.comment ? <p className="mt-1 text-sm text-slate-600">{review.comment}</p> : null}
                    </div>
                    <div className="flex shrink-0 gap-2 text-xs font-medium">
                      <button className="text-emerald-700 hover:underline" onClick={() => void moderateReview(review.id, 'publish')}>
                        {t(locale, 'restaurant.online.reviewPublish')}
                      </button>
                      <button className="text-red-600 hover:underline" onClick={() => void moderateReview(review.id, 'reject')}>
                        {t(locale, 'restaurant.online.reviewReject')}
                      </button>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </>
      )}
    </ModulePageShell>
  );
}
