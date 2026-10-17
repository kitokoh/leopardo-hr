'use client';

/**
 * #8447 — « Mon Logiciel » : le hub d'installation des apps du tenant.
 *
 * Un tenant qui vient de configurer son espace trouve ici TOUTES les
 * applications de son métier — filtrées STRICTEMENT par ses solutions
 * activées (`getClientModuleAccess`) — avec, pour chacune, ses canaux
 * d'installation réels (lien direct, build testeur, accès pilote) et un QR
 * code pour équiper un appareil mobile en un scan.
 *
 * États honnêtes (jamais de faux bouton, règle #6697) : une app sans canal
 * de distribution public est marquée « bientôt », un client desktop passe
 * par la demande d'accès pilote, seules les cibles réelles sont cliquables.
 *
 * La constellation 3D (chargée à la demande, jamais en SSR) donne la vue
 * d'ensemble : le noyau Leopardo et les apps du tenant en orbite. Un clic
 * sur un nœud sélectionne la carte correspondante.
 */

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import dynamic from 'next/dynamic';
import { AnimatePresence, motion } from 'framer-motion';
import {
  AppWindow,
  CheckCircle2,
  ChevronDown,
  ExternalLink,
  Loader2,
  Monitor,
  QrCode,
  ShieldCheck,
  Smartphone,
  Sparkles,
  WifiOff,
  X,
} from 'lucide-react';
import QRCode from 'qrcode';
import { ModulePageShell } from '@/components/module-page-shell';
import { getClientModuleAccess } from '@/lib/client-features';
import { getPreferredLocale, getStoredUser } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import {
  absoluteTargetHref,
  isOfflineClaimed,
  softwareAvailability,
  visibleSoftware,
  type SoftwareApp,
  type SoftwareAvailability,
  type SoftwareTarget,
} from '@/lib/software-catalog';
import type { ConstellationNode } from '@/components/software/SoftwareConstellation3D';

// La scène three.js ne charge jamais côté serveur (WebGL = navigateur) et
// n'arrive qu'après le premier rendu utile de la liste des apps.
const SoftwareConstellation3D = dynamic(
  () => import('@/components/software/SoftwareConstellation3D'),
  { ssr: false },
);

/** Couleur hex par app (reprise du dégradé de sa tuile) pour la 3D. */
const NODE_COLORS: Record<string, string> = {
  manager: '#10b981',
  employee: '#0ea5e9',
  'desktop-attendance': '#64748b',
  'restaurant-pos': '#f97316',
  'restaurant-kds': '#f43f5e',
  'restaurant-server': '#8b5cf6',
  'restaurant-kiosk': '#06b6d4',
  'fuel-console': '#eab308',
  'commerce-pos': '#14b8a6',
  'travel-agent': '#6366f1',
  'edu-portal': '#3b82f6',
  'health-portal': '#ef4444',
  'hr-mobile': '#059669',
  'accounting-mobile': '#475569',
  'marketing-mobile': '#d946ef',
  'cameras-mobile': '#6b7280',
};

const PLATFORM_LABELS: Record<SoftwareTarget['platform'], string> = {
  android: 'Android',
  ios: 'iOS',
  web: 'Web',
  desktop: 'Desktop',
};

/**
 * #8597 (BOS-045) — couleurs du badge de disponibilité. Le statut est DÉRIVÉ
 * des cibles réelles (`softwareAvailability`), jamais déclaré à la main : une
 * app sans canal public ne peut pas s'afficher comme publiée.
 */
const AVAILABILITY_BADGE: Record<SoftwareAvailability, string> = {
  published: 'bg-emerald-50 text-emerald-700',
  pilot: 'bg-amber-50 text-amber-700',
  not_distributed: 'bg-slate-100 text-slate-600',
};

export default function MonLogicielPage() {
  const locale = getPreferredLocale();
  const [apps, setApps] = useState<SoftwareApp[] | null>(null);
  const [selected, setSelected] = useState<string | null>(null);
  const [expanded, setExpanded] = useState<string | null>(null);
  const [qr, setQr] = useState<{ app: SoftwareApp; target: SoftwareTarget; dataUrl: string } | null>(null);
  const cardRefs = useRef<Record<string, HTMLElement | null>>({});

  // Filtrage par solutions RÉELLEMENT activées — recalculé au montage depuis
  // la session (le menu du tenant suit le même catalogue, #7225).
  useEffect(() => {
    setApps(visibleSoftware(getClientModuleAccess(getStoredUser())));
  }, []);

  const translate = useCallback(
    (key: string, fallback = '') => t(locale, key, fallback),
    [locale],
  );

  const appName = useCallback(
    (app: SoftwareApp) => translate(`software.apps.${app.slug}.name`, app.slug),
    [translate],
  );

  const nodes: ConstellationNode[] = useMemo(
    () => (apps ?? []).map((app) => ({
      slug: app.slug,
      label: appName(app),
      color: NODE_COLORS[app.slug] ?? '#10b981',
    })),
    [apps, appName],
  );

  const selectApp = useCallback((slug: string) => {
    setSelected(slug);
    cardRefs.current[slug]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }, []);

  const openQr = useCallback((app: SoftwareApp, target: SoftwareTarget) => {
    if (!target.href) return;
    const absolute = absoluteTargetHref(target.href, window.location.origin);
    void QRCode.toDataURL(absolute, { width: 320, margin: 1 }).then((dataUrl) => {
      setQr({ app, target, dataUrl });
    }).catch(() => {
      // Le QR est un confort : son échec ne bloque jamais l'installation.
    });
  }, []);

  const closeQr = useCallback(() => setQr(null), []);

  return (
    <ModulePageShell
      icon={AppWindow}
      title={translate('software.title', 'Mon Logiciel')}
      description={translate('software.subtitle', 'Toutes les applications de votre espace, prêtes à installer.')}
    >
      {apps === null ? (
        <p className="flex items-center gap-2 text-sm text-slate-500">
          <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
          {translate('software.loading', 'Chargement de vos applications…')}
        </p>
      ) : apps.length === 0 ? (
        <section className="rounded-2xl border border-dashed border-slate-300 bg-white/70 p-8 text-center">
          <Sparkles className="mx-auto h-8 w-8 text-emerald-500" aria-hidden />
          <p className="mt-3 font-semibold text-slate-800">
            {translate('software.empty.title', 'Aucune application à installer pour le moment')}
          </p>
          <p className="mt-1 text-sm text-slate-600">
            {translate('software.empty.body', 'Activez une solution métier depuis la page Modules pour débloquer ses applications.')}
          </p>
        </section>
      ) : (
        <>
          {/* Constellation 3D — vue d'ensemble cliquable des apps du tenant. */}
          <section
            aria-label={translate('software.constellation', 'Constellation de vos applications')}
            className="relative overflow-hidden rounded-2xl border border-white/20 bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950 shadow-premium"
          >
            <SoftwareConstellation3D
              nodes={nodes}
              selectedSlug={selected}
              onSelect={selectApp}
              className="h-[320px] w-full md:h-[380px]"
            />
            <p className="pointer-events-none absolute bottom-3 left-1/2 -translate-x-1/2 text-xs font-medium text-slate-300/90">
              {translate('software.constellationHint', 'Cliquez sur une application pour la retrouver dans la liste')}
            </p>
          </section>

          {/* Cartes d'installation. */}
          <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            {apps.map((app) => {
              const Icon = app.icon;
              const isSelected = selected === app.slug;
              const availability = softwareAvailability(app);
              const isExpanded = expanded === app.slug;
              return (
                <motion.article
                  key={app.slug}
                  ref={(node) => { cardRefs.current[app.slug] = node; }}
                  layout
                  initial={{ opacity: 0, y: 12 }}
                  animate={{ opacity: 1, y: 0 }}
                  className={`relative rounded-2xl border bg-white/80 p-5 shadow-sm backdrop-blur transition-shadow ${
                    isSelected
                      ? 'border-emerald-400 ring-2 ring-emerald-300/60 shadow-lg'
                      : 'border-slate-200/70 hover:shadow-md'
                  }`}
                >
                  <header className="flex items-start gap-3">
                    <span className={`inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ${app.accent} text-white shadow-md`}>
                      <Icon className="h-5 w-5" aria-hidden />
                    </span>
                    <div className="min-w-0 flex-1">
                      <h2 className="truncate text-base font-bold text-slate-900">{appName(app)}</h2>
                      <p className="mt-0.5 line-clamp-2 text-sm text-slate-600">
                        {translate(`software.apps.${app.slug}.description`)}
                      </p>
                    </div>
                  </header>

                  <div className="mt-3 flex flex-wrap items-center gap-2">
                    {isOfflineClaimed(app) ? (
                      <p
                        className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"
                        title={app.offline === 'implemented'
                          ? translate('software.offlineImplementedHint', 'File hors-ligne branchée — scénario de coupure non encore rejoué en CI')
                          : undefined}
                      >
                        <WifiOff className="h-3.5 w-3.5" aria-hidden />
                        {app.offline === 'verified'
                          ? translate('software.offlineBadgeVerified', 'Hors-ligne vérifié')
                          : translate('software.offlineBadge', 'Fonctionne hors-ligne')}
                      </p>
                    ) : null}
                    <p className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ${AVAILABILITY_BADGE[availability]}`}>
                      <ShieldCheck className="h-3.5 w-3.5" aria-hidden />
                      {translate(`software.availability.${availability}`, availability)}
                    </p>
                  </div>

                  {/* Fiche application (#8597) : rôle, profils, prérequis,
                      connexion, permissions et mise en service — le texte vit
                      dans les catalogues i18n, jamais en dur ici. */}
                  <button
                    type="button"
                    onClick={() => setExpanded((current) => (current === app.slug ? null : app.slug))}
                    aria-expanded={isExpanded}
                    aria-controls={`software-details-${app.slug}`}
                    className="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-700 transition hover:text-emerald-700"
                  >
                    <ChevronDown
                      className={`h-4 w-4 transition-transform ${isExpanded ? 'rotate-180' : ''}`}
                      aria-hidden
                    />
                    {translate('software.details.toggle', "Voir la fiche de l'application")}
                  </button>

                  {isExpanded ? (
                    <dl
                      id={`software-details-${app.slug}`}
                      className="mt-3 space-y-2 rounded-xl bg-slate-50/80 p-3"
                    >
                      {app.details.map((key) => {
                        const text = translate(`software.apps.${app.slug}.details.${key}`, '');
                        if (!text) return null;
                        return (
                          <div key={key}>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                              {translate(`software.details.${key}`, key)}
                            </dt>
                            <dd className="mt-0.5 text-sm text-slate-700">{text}</dd>
                          </div>
                        );
                      })}
                    </dl>
                  ) : null}

                  <ul className="mt-4 space-y-2">
                    {app.targets.map((target) => (
                      <li key={target.platform} className="flex items-center gap-2">
                        <span className="inline-flex w-24 shrink-0 items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                          {target.platform === 'desktop' ? (
                            <Monitor className="h-3.5 w-3.5" aria-hidden />
                          ) : (
                            <Smartphone className="h-3.5 w-3.5" aria-hidden />
                          )}
                          {PLATFORM_LABELS[target.platform]}
                        </span>

                        {target.kind === 'link' && target.href ? (
                          <>
                            <a
                              href={target.href}
                              {...(/^https?:\/\//.test(target.href)
                                ? { target: '_blank', rel: 'noopener noreferrer' }
                                : {})}
                              className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                            >
                              {translate('software.install', 'Installer')}
                              <ExternalLink className="h-3.5 w-3.5" aria-hidden />
                            </a>
                            <button
                              type="button"
                              onClick={() => openQr(app, target)}
                              aria-label={translate('software.qrAria', 'Afficher le QR code d’installation')}
                              className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-emerald-300 hover:text-emerald-600"
                            >
                              <QrCode className="h-4 w-4" aria-hidden />
                            </button>
                          </>
                        ) : target.kind === 'pilot' && target.href ? (
                          <a
                            href={target.href}
                            className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100"
                          >
                            {translate('software.pilot', 'Demander l’accès pilote')}
                          </a>
                        ) : (
                          <span className="inline-flex flex-1 cursor-not-allowed items-center justify-center rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-400">
                            {translate('software.soon', 'Bientôt disponible')}
                          </span>
                        )}
                      </li>
                    ))}
                  </ul>
                </motion.article>
              );
            })}
          </section>
        </>
      )}

      {/* Modale QR d'installation. */}
      <AnimatePresence>
        {qr ? (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
            onClick={closeQr}
          >
            <motion.div
              initial={{ scale: 0.92, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0.95, opacity: 0 }}
              role="dialog"
              aria-modal="true"
              aria-label={translate('software.qrTitle', 'Installer sur cet appareil')}
              className="w-full max-w-xs rounded-2xl bg-white p-6 text-center shadow-2xl"
              onClick={(event) => event.stopPropagation()}
            >
              <div className="flex items-center justify-between">
                <h3 className="text-sm font-bold text-slate-900">
                  {appName(qr.app)} · {PLATFORM_LABELS[qr.target.platform]}
                </h3>
                <button
                  type="button"
                  onClick={closeQr}
                  aria-label={translate('software.close', 'Fermer')}
                  className="rounded-full p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                >
                  <X className="h-4 w-4" aria-hidden />
                </button>
              </div>
              {/* eslint-disable-next-line @next/next/no-img-element -- data URL générée localement, pas d'optimisation possible */}
              <img src={qr.dataUrl} alt="" className="mx-auto mt-4 h-48 w-48 rounded-lg border border-slate-100" />
              <p className="mt-4 flex items-start justify-center gap-1.5 text-xs text-slate-500">
                <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-500" aria-hidden />
                {translate('software.qrHint', 'Scannez avec l’appareil à équiper : le lien d’installation s’ouvre directement.')}
              </p>
            </motion.div>
          </motion.div>
        ) : null}
      </AnimatePresence>
    </ModulePageShell>
  );
}
