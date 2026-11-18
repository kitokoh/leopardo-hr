'use client';

/**
 * PHARMA-PUB (#9621) — Visibilité publique de l'officine.
 *
 * Écran de l'espace professionnel qui manquait : le backend exposait déjà
 * `GET/PUT /pharmacy/public-profile` (contrat #9617) et
 * `PharmacyDiscoverableProvider` lisait `pharmacy_public_profiles.enabled`,
 * mais **aucune interface ne permettait au pharmacien d'écrire cette ligne**.
 * Résultat : `enabled` restait `false` pour toujours et `/pharmacies` affichait
 * « bientôt disponible » en permanence, sans qu'aucune erreur ne le signale.
 *
 * ## Ce que l'écran fait
 *
 * - charge le profil existant (`data: null` = jamais publié, état normal) ;
 * - enregistre en create-or-update (le backend gère les deux cas) ;
 * - **refuse d'activer la visibilité sans adresse publique ni nom affiché**,
 *   exactement comme le contrôleur (fail-closed) : sans ce garde-fou côté
 *   client, le pharmacien verrait un 422 après avoir coché la case, sans
 *   comprendre pourquoi ;
 * - affiche l'URL publique renvoyée par le serveur (`public_url`), pour qu'il
 *   voie où son officine est visible sans reconstruire le chemin.
 *
 * ## Ce que l'écran ne fait pas
 *
 * Aucune vente en ligne : `PharmacyDiscoverableProvider` ne déclare que
 * l'action `contact` (dispensation réglementée). L'écran ne promet donc ni
 * commande ni paiement.
 */
import { useCallback, useEffect, useState } from 'react';
import { Store } from 'lucide-react';

import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type PublicProfile = {
  enabled: boolean;
  public_slug: string | null;
  public_name: string | null;
  public_city: string | null;
  public_description: string | null;
  public_phone: string | null;
  on_duty: boolean;
  services: string[];
  version: number;
  public_url: string | null;
  updated_at: string | null;
};

type ProfileForm = {
  enabled: boolean;
  public_slug: string;
  public_name: string;
  public_city: string;
  public_description: string;
  public_phone: string;
  on_duty: boolean;
  services: string;
};

const EMPTY_FORM: ProfileForm = {
  enabled: false,
  public_slug: '',
  public_name: '',
  public_city: '',
  public_description: '',
  public_phone: '',
  on_duty: false,
  services: '',
};

function profileToForm(profile: PublicProfile): ProfileForm {
  return {
    enabled: Boolean(profile.enabled),
    public_slug: profile.public_slug ?? '',
    public_name: profile.public_name ?? '',
    public_city: profile.public_city ?? '',
    public_description: profile.public_description ?? '',
    public_phone: profile.public_phone ?? '',
    on_duty: Boolean(profile.on_duty),
    services: Array.isArray(profile.services) ? profile.services.join(', ') : '',
  };
}

export default function PharmacyPublicationPage() {
  const locale = getPreferredLocale();
  const [form, setForm] = useState<ProfileForm>(EMPTY_FORM);
  const [publicUrl, setPublicUrl] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState('');
  const [solutionInactive, setSolutionInactive] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    setSaved(false);
    try {
      const res = await apiFetch('/pharmacy/public-profile');
      if (res.status === 403) {
        // Solution PharmaManager inactive : ce n'est pas une panne, c'est un
        // état de l'espace. On l'annonce comme tel plutôt qu'en erreur rouge.
        setSolutionInactive(true);
        return;
      }
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: PublicProfile | null };
      setSolutionInactive(false);
      if (payload.data) {
        setForm(profileToForm(payload.data));
        setPublicUrl(payload.data.public_url);
      } else {
        setForm(EMPTY_FORM);
        setPublicUrl(null);
      }
    } catch {
      setError(t(locale, 'pharmacy.publication.loadError'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const update = (patch: Partial<ProfileForm>) => {
    setSaved(false);
    setForm((prev) => ({ ...prev, ...patch }));
  };

  const slug = form.public_slug.trim();
  const name = form.public_name.trim();
  // Même règle que le contrôleur : activer exige un slug ET un nom.
  const canPublish = slug !== '' && name !== '';

  const save = async () => {
    setSaving(true);
    setError('');
    setSaved(false);
    try {
      const body = {
        enabled: form.enabled,
        public_slug: slug === '' ? null : slug,
        public_name: name === '' ? null : name,
        public_city: form.public_city.trim() === '' ? null : form.public_city.trim(),
        public_description:
          form.public_description.trim() === '' ? null : form.public_description.trim(),
        public_phone: form.public_phone.trim() === '' ? null : form.public_phone.trim(),
        on_duty: form.on_duty,
        services: form.services
          .split(',')
          .map((s) => s.trim())
          .filter((s) => s !== ''),
      };
      const res = await apiFetch('/pharmacy/public-profile', {
        method: 'PUT',
        body: JSON.stringify(body),
      });
      if (!res.ok) {
        const payload = await res.json().catch(() => ({}));
        throw new Error((payload as { message?: string }).message ?? `HTTP ${res.status}`);
      }
      const payload = (await res.json()) as { data?: PublicProfile };
      if (payload.data) {
        setForm(profileToForm(payload.data));
        setPublicUrl(payload.data.public_url);
      }
      setSaved(true);
    } catch (e) {
      setError(
        e instanceof Error && e.message ? e.message : t(locale, 'pharmacy.publication.saveError'),
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <ModulePageShell
      icon={Store}
      title={t(locale, 'pharmacy.publication.title')}
      description={t(locale, 'pharmacy.publication.subtitle')}
    >
      {solutionInactive ? (
        <p className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
          {t(locale, 'pharmacy.publication.solutionInactive')}
        </p>
      ) : null}

      {error ? (
        <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>
      ) : null}

      {saved ? (
        <p className="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
          {t(locale, 'pharmacy.publication.saved')}
        </p>
      ) : null}

      {!solutionInactive ? (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h3 className="font-bold text-slate-900">{t(locale, 'pharmacy.publication.profileTitle')}</h3>

          {loading ? (
            <p className="mt-3 text-sm text-slate-500">{t(locale, 'pharmacy.publication.loading')}</p>
          ) : (
            <div className="mt-4 space-y-4">
              <label className="flex items-start gap-3">
                <input
                  type="checkbox"
                  checked={form.enabled}
                  disabled={!canPublish}
                  onChange={(e) => update({ enabled: e.target.checked })}
                  className="mt-1 h-5 w-5 rounded border-slate-300 text-emerald-600 disabled:opacity-50"
                />
                <span>
                  <span className="block text-sm font-semibold text-slate-800">
                    {t(locale, 'pharmacy.publication.enabled')}
                  </span>
                  <span className="block text-xs text-slate-500">
                    {canPublish
                      ? t(locale, 'pharmacy.publication.enabledHint')
                      : t(locale, 'pharmacy.publication.requiredForPublication')}
                  </span>
                </span>
              </label>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label
                    className="block text-sm font-semibold text-slate-700"
                    htmlFor="pharmacy-slug"
                  >
                    {t(locale, 'pharmacy.publication.slug')}
                  </label>
                  <input
                    id="pharmacy-slug"
                    type="text"
                    value={form.public_slug}
                    onChange={(e) => update({ public_slug: e.target.value })}
                    placeholder={t(locale, 'pharmacy.publication.slugHint')}
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                  />
                  <p className="mt-1 text-xs text-slate-500">
                    {t(locale, 'pharmacy.publication.publicUrl')}{' '}
                    <span className="font-mono text-emerald-700">
                      /pharmacies/{slug === '' ? '…' : slug}
                    </span>
                  </p>
                </div>

                <div>
                  <label
                    className="block text-sm font-semibold text-slate-700"
                    htmlFor="pharmacy-name"
                  >
                    {t(locale, 'pharmacy.publication.name')}
                  </label>
                  <input
                    id="pharmacy-name"
                    type="text"
                    value={form.public_name}
                    onChange={(e) => update({ public_name: e.target.value })}
                    placeholder={t(locale, 'pharmacy.publication.nameHint')}
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                  />
                </div>

                <div>
                  <label
                    className="block text-sm font-semibold text-slate-700"
                    htmlFor="pharmacy-city"
                  >
                    {t(locale, 'pharmacy.publication.city')}
                  </label>
                  <input
                    id="pharmacy-city"
                    type="text"
                    value={form.public_city}
                    onChange={(e) => update({ public_city: e.target.value })}
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                  />
                </div>

                <div>
                  <label
                    className="block text-sm font-semibold text-slate-700"
                    htmlFor="pharmacy-phone"
                  >
                    {t(locale, 'pharmacy.publication.phone')}
                  </label>
                  <input
                    id="pharmacy-phone"
                    type="tel"
                    value={form.public_phone}
                    onChange={(e) => update({ public_phone: e.target.value })}
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                  />
                </div>
              </div>

              <div>
                <label
                  className="block text-sm font-semibold text-slate-700"
                  htmlFor="pharmacy-description"
                >
                  {t(locale, 'pharmacy.publication.description')}
                </label>
                <textarea
                  id="pharmacy-description"
                  rows={3}
                  value={form.public_description}
                  onChange={(e) => update({ public_description: e.target.value })}
                  placeholder={t(locale, 'pharmacy.publication.descriptionHint')}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                />
              </div>

              <div>
                <label
                  className="block text-sm font-semibold text-slate-700"
                  htmlFor="pharmacy-services"
                >
                  {t(locale, 'pharmacy.publication.services')}
                </label>
                <input
                  id="pharmacy-services"
                  type="text"
                  value={form.services}
                  onChange={(e) => update({ services: e.target.value })}
                  placeholder={t(locale, 'pharmacy.publication.servicesHint')}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                />
              </div>

              <label className="flex items-start gap-3">
                <input
                  type="checkbox"
                  checked={form.on_duty}
                  onChange={(e) => update({ on_duty: e.target.checked })}
                  className="mt-1 h-5 w-5 rounded border-slate-300 text-emerald-600"
                />
                <span>
                  <span className="block text-sm font-semibold text-slate-800">
                    {t(locale, 'pharmacy.publication.onDuty')}
                  </span>
                  <span className="block text-xs text-slate-500">
                    {t(locale, 'pharmacy.publication.onDutyHint')}
                  </span>
                </span>
              </label>

              <div className="flex flex-wrap items-center gap-3">
                <button
                  type="button"
                  onClick={() => void save()}
                  disabled={saving}
                  className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                >
                  {saving
                    ? t(locale, 'pharmacy.publication.saving')
                    : t(locale, 'pharmacy.publication.save')}
                </button>

                {publicUrl ? (
                  <a
                    href={publicUrl}
                    className="text-sm font-semibold text-emerald-700 underline"
                  >
                    {t(locale, 'pharmacy.publication.viewPublicPage')}
                  </a>
                ) : (
                  <span className="text-xs text-slate-500">
                    {t(locale, 'pharmacy.publication.notPublished')}
                  </span>
                )}
              </div>
            </div>
          )}
        </section>
      ) : null}
    </ModulePageShell>
  );
}
