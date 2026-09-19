'use client';

import { useEffect, useMemo, useRef, useState, useSyncExternalStore } from 'react';
import { Image as ImageIcon, Palette, Paintbrush, ShieldAlert } from 'lucide-react';

import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

const inputClassName =
  'w-full rounded-xl border border-app-border bg-white px-4 py-3 text-sm font-medium text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10';

/** Contrat renvoyé par GET/PATCH /company/branding (contrôleur HR existant). */
type CompanyBranding = {
  display_name: string | null;
  logo_url: string | null;
  logo_path: string | null;
  logo_disk: string | null;
  primary_color: string;
  accent_color: string;
  brand_mode: 'default' | 'light' | 'dark' | 'auto';
};

const BRAND_MODES = ['default', 'light', 'dark', 'auto'] as const;
const HEX_PATTERN = /^#[0-9A-Fa-f]{6}$/;
const MAX_LOGO_BYTES = 2 * 1024 * 1024; // garde-fou client aligné sur max:2048 (Ko) côté API.
const ACCEPTED_LOGO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Image de marque du tenant (issue #7713).
 *
 * L'API `GET/PATCH /company/branding` existait (miroir de l'écran mobile
 * `company_branding`) mais aucune surface web ne l'exposait. Le PATCH est
 * réservé aux managers `principal|rh` : un 403 est mis en mots plutôt que
 * laissé en erreur générique. L'upload de logo passe par un POST multipart
 * avec `_method=PATCH` (method spoofing Laravel — PHP ne peuple pas `$_FILES`
 * sur un PATCH multipart natif).
 */
export default function BrandingPage() {
  const locale = useSyncExternalStore<AppLocale>(() => () => {}, getPreferredLocale, () => 'fr');
  const [branding, setBranding] = useState<CompanyBranding | null>(null);
  const [loadError, setLoadError] = useState(false);
  const [forbidden, setForbidden] = useState(false);
  const [displayName, setDisplayName] = useState('');
  const [primaryColor, setPrimaryColor] = useState('#10B981');
  const [accentColor, setAccentColor] = useState('#2563EB');
  const [brandMode, setBrandMode] = useState<CompanyBranding['brand_mode']>('default');
  const [logoFile, setLogoFile] = useState<File | null>(null);
  const [logoObjectUrl, setLogoObjectUrl] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement | null>(null);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const response = await apiFetch('/company/branding');
        const payload = await response.json() as { data?: { branding?: CompanyBranding } };
        if (cancelled || !payload.data?.branding) {
          return;
        }
        const next = payload.data.branding;
        setBranding(next);
        setDisplayName(next.display_name ?? '');
        setPrimaryColor(next.primary_color ?? '#10B981');
        setAccentColor(next.accent_color ?? '#2563EB');
        setBrandMode(BRAND_MODES.includes(next.brand_mode) ? next.brand_mode : 'default');
      } catch {
        if (!cancelled) {
          setLoadError(true);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  // L'aperçu du fichier sélectionné vit dans un object URL, révoqué au remplacement.
  useEffect(() => {
    return () => {
      if (logoObjectUrl) {
        URL.revokeObjectURL(logoObjectUrl);
      }
    };
  }, [logoObjectUrl]);

  const previewLogoUrl = logoObjectUrl ?? branding?.logo_url ?? null;
  const previewPrimary = HEX_PATTERN.test(primaryColor) ? primaryColor : '#10B981';
  const previewAccent = HEX_PATTERN.test(accentColor) ? accentColor : '#2563EB';
  const modeLabels = useMemo(() => ({
    default: i18nT(locale, 'brandingPage.modeDefault'),
    light: i18nT(locale, 'brandingPage.modeLight'),
    dark: i18nT(locale, 'brandingPage.modeDark'),
    auto: i18nT(locale, 'brandingPage.modeAuto'),
  }), [locale]);

  const onLogoChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    setError(null);
    setNotice(null);
    const file = event.target.files?.[0] ?? null;
    if (!file) {
      return;
    }
    if (!ACCEPTED_LOGO_TYPES.includes(file.type)) {
      setError(i18nT(locale, 'brandingPage.logoInvalidType'));
      event.target.value = '';
      return;
    }
    if (file.size > MAX_LOGO_BYTES) {
      setError(i18nT(locale, 'brandingPage.logoTooLarge'));
      event.target.value = '';
      return;
    }
    setLogoFile(file);
    setLogoObjectUrl(URL.createObjectURL(file));
  };

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setNotice(null);

    if (!HEX_PATTERN.test(primaryColor) || !HEX_PATTERN.test(accentColor)) {
      setError(i18nT(locale, 'brandingPage.invalidHex'));
      return;
    }

    setSaving(true);

    try {
      let response: Response;

      if (logoFile) {
        // Multipart : POST + `_method=PATCH` (spoofing Laravel) — un PATCH
        // multipart natif n'est pas parsé par PHP. Idempotence désactivée :
        // deux uploads successifs ne doivent pas rejouer la même réponse.
        const form = new FormData();
        form.set('_method', 'PATCH');
        form.set('display_name', displayName.trim());
        form.set('primary_color', primaryColor.toUpperCase());
        form.set('accent_color', accentColor.toUpperCase());
        form.set('brand_mode', brandMode);
        form.set('logo', logoFile);
        response = await apiFetch('/company/branding', {
          method: 'POST',
          body: form,
          _idempotent: false,
        });
      } else {
        response = await apiFetch('/company/branding', {
          method: 'PATCH',
          body: JSON.stringify({
            display_name: displayName.trim(),
            primary_color: primaryColor.toUpperCase(),
            accent_color: accentColor.toUpperCase(),
            brand_mode: brandMode,
          }),
          _idempotent: false,
        });
      }

      const payload = await response.json() as { data?: { branding?: CompanyBranding } };
      if (payload.data?.branding) {
        setBranding(payload.data.branding);
        setLogoFile(null);
        setLogoObjectUrl(null);
        if (fileInputRef.current) {
          fileInputRef.current.value = '';
        }
      }
      setNotice(i18nT(locale, 'brandingPage.saved'));
    } catch (err) {
      if (err instanceof ApiError && err.status === 403) {
        setForbidden(true);
      } else {
        setError(err instanceof ApiError ? err.message : i18nT(locale, 'brandingPage.saveError'));
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <ModulePageShell
      title={i18nT(locale, 'brandingPage.title')}
      subtitle={i18nT(locale, 'brandingPage.subtitle')}
      icon={Paintbrush}
      accentClassName="bg-gradient-to-br from-slate-50 via-white to-white"
    >
      {notice ? (
        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status">
          {notice}
        </div>
      ) : null}

      {loadError ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
          {i18nT(locale, 'brandingPage.loadError')}
        </div>
      ) : null}

      {forbidden ? (
        <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
          <ShieldAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
          {i18nT(locale, 'brandingPage.forbidden')}
        </div>
      ) : null}

      <form onSubmit={submit} className="grid gap-6 lg:grid-cols-2">
        <div className="space-y-6">
          {/* Identité : nom d'affichage + logo. */}
          <div className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
            <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-slate-800">
              <ImageIcon className="h-4 w-4 text-slate-400" aria-hidden="true" />
              {i18nT(locale, 'brandingPage.identityTitle')}
            </h2>

            <label className="mt-5 block">
              <span className="mb-2 block text-sm font-semibold text-slate-700">{i18nT(locale, 'brandingPage.displayName')}</span>
              <input
                type="text"
                value={displayName}
                onChange={(event) => setDisplayName(event.target.value)}
                minLength={2}
                maxLength={120}
                className={inputClassName}
                data-testid="branding-display-name"
              />
              <span className="mt-1 block text-xs text-slate-400">{i18nT(locale, 'brandingPage.displayNameHint')}</span>
            </label>

            <div className="mt-5">
              <span className="mb-2 block text-sm font-semibold text-slate-700">{i18nT(locale, 'brandingPage.logoTitle')}</span>
              <label className="flex cursor-pointer items-center gap-4 rounded-2xl border border-dashed border-slate-300 p-4 transition hover:border-emerald-300">
                {previewLogoUrl ? (
                  // eslint-disable-next-line @next/next/no-img-element -- logo du tenant servi par l'API (URL par entreprise, hors allowlist next/image)
                  <img src={previewLogoUrl} alt={i18nT(locale, 'brandingPage.logoPreviewAlt')} className="h-14 w-14 rounded-xl border border-slate-200 bg-white object-contain" />
                ) : (
                  <span className="flex h-14 w-14 items-center justify-center rounded-xl border border-slate-200 bg-slate-50">
                    <ImageIcon className="h-6 w-6 text-slate-300" aria-hidden="true" />
                  </span>
                )}
                <span className="min-w-0">
                  <span className="block text-sm font-bold text-emerald-700">{i18nT(locale, 'brandingPage.logoChoose')}</span>
                  <span className="mt-1 block text-xs text-slate-400">{i18nT(locale, 'brandingPage.logoHint')}</span>
                </span>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  onChange={onLogoChange}
                  className="sr-only"
                  data-testid="branding-logo-input"
                />
              </label>
            </div>
          </div>

          {/* Couleurs + mode de marque. */}
          <div className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
            <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-slate-800">
              <Palette className="h-4 w-4 text-slate-400" aria-hidden="true" />
              {i18nT(locale, 'brandingPage.colorsTitle')}
            </h2>

            <div className="mt-5 grid gap-4 sm:grid-cols-2">
              <ColorField
                label={i18nT(locale, 'brandingPage.primaryColor')}
                value={primaryColor}
                onChange={setPrimaryColor}
                testId="branding-primary-color"
              />
              <ColorField
                label={i18nT(locale, 'brandingPage.accentColor')}
                value={accentColor}
                onChange={setAccentColor}
                testId="branding-accent-color"
              />
            </div>

            <label className="mt-5 block">
              <span className="mb-2 block text-sm font-semibold text-slate-700">{i18nT(locale, 'brandingPage.brandModeTitle')}</span>
              <select
                value={brandMode}
                onChange={(event) => setBrandMode(event.target.value as CompanyBranding['brand_mode'])}
                className={inputClassName}
                data-testid="branding-brand-mode"
              >
                {BRAND_MODES.map((mode) => (
                  <option key={mode} value={mode}>{modeLabels[mode]}</option>
                ))}
              </select>
            </label>
          </div>

          {error ? (
            <p role="alert" className="text-sm font-medium text-red-600">
              {error}
            </p>
          ) : null}

          <Button type="submit" loading={saving} disabled={saving || forbidden || branding === null} data-testid="branding-save">
            {i18nT(locale, 'brandingPage.save')}
          </Button>
        </div>

        {/* Aperçu en direct : logo + couleurs appliquées. */}
        <div className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">{i18nT(locale, 'brandingPage.previewTitle')}</h2>
          <p className="mt-2 text-sm leading-6 text-slate-500">{i18nT(locale, 'brandingPage.previewHint')}</p>

          <div
            className="mt-5 overflow-hidden rounded-2xl border border-slate-200"
            data-testid="branding-preview-card"
            style={{ borderTopWidth: 4, borderTopColor: previewPrimary }}
          >
            <div className="flex items-center gap-3 border-b border-slate-100 bg-slate-50/60 px-4 py-3">
              {previewLogoUrl ? (
                // eslint-disable-next-line @next/next/no-img-element -- logo du tenant servi par l'API (URL par entreprise, hors allowlist next/image)
                <img src={previewLogoUrl} alt="" aria-hidden="true" className="h-9 w-9 rounded-lg border border-slate-200 bg-white object-contain" />
              ) : (
                <span
                  className="flex h-9 w-9 items-center justify-center rounded-lg text-[11px] font-black text-white"
                  style={{ backgroundColor: previewPrimary }}
                >
                  {(displayName || 'L').trim().charAt(0).toUpperCase()}
                </span>
              )}
              <div className="min-w-0">
                <p className="truncate text-sm font-black text-slate-950">{displayName || i18nT(locale, 'brandingPage.displayName')}</p>
                <p className="truncate text-[10px] font-black uppercase tracking-widest" style={{ color: previewAccent }}>
                  {modeLabels[brandMode]}
                </p>
              </div>
            </div>
            <div className="space-y-3 p-4">
              <div className="flex items-center gap-2">
                <span className="h-6 w-6 rounded-md border border-slate-200" style={{ backgroundColor: previewPrimary }} aria-hidden="true" />
                <span className="text-xs font-semibold text-slate-500">{previewPrimary.toUpperCase()}</span>
                <span className="ms-3 h-6 w-6 rounded-md border border-slate-200" style={{ backgroundColor: previewAccent }} aria-hidden="true" />
                <span className="text-xs font-semibold text-slate-500">{previewAccent.toUpperCase()}</span>
              </div>
              <span
                className="inline-flex items-center rounded-xl px-4 py-2 text-sm font-bold text-white"
                style={{ backgroundColor: previewPrimary }}
              >
                {i18nT(locale, 'brandingPage.previewAction')}
              </span>
            </div>
          </div>
        </div>
      </form>
    </ModulePageShell>
  );
}

function ColorField({
  label,
  value,
  onChange,
  testId,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  testId: string;
}) {
  return (
    <label className="block">
      <span className="mb-2 block text-sm font-semibold text-slate-700">{label}</span>
      <span className="flex items-center gap-2">
        <input
          type="color"
          value={HEX_PATTERN.test(value) ? value : '#000000'}
          onChange={(event) => onChange(event.target.value.toUpperCase())}
          className="h-11 w-12 shrink-0 cursor-pointer rounded-xl border border-app-border bg-white p-1"
          aria-label={label}
        />
        <input
          type="text"
          value={value}
          onChange={(event) => onChange(event.target.value)}
          maxLength={7}
          pattern="#[0-9A-Fa-f]{6}"
          className={inputClassName}
          data-testid={testId}
        />
      </span>
    </label>
  );
}
