'use client';

/**
 * #7728 (BC-25 / BC-21) — Encaissement du restaurateur.
 *
 * État de configuration du paiement EN LIGNE, relié aux profils de paiement
 * du tenant (#7727) : carte en ligne (profil Stripe actif) et mobile money
 * (sandbox ou production + profil actif), via
 * `GET /restaurant/payments/configuration` (aucun secret exposé — uniquement
 * des indicateurs). Les profils eux-mêmes (clés Stripe, comptes mobile
 * money) se gèrent dans Réglages → Encaissements (`/settings/encaissements`).
 * L'historique paiements/refunds existant (POS, rapports) est inchangé.
 */
import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { Banknote, CheckCircle2, CreditCard, Smartphone, Wallet, XCircle } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type PaymentConfiguration = {
  online_enabled: boolean;
  online_providers: string[];
  card_online: { configured: boolean };
  mobile_money: {
    configured: boolean;
    sandbox: boolean;
    production_enabled: boolean;
    profile_active: boolean;
  };
  pay_on_site: boolean;
};

function StatusBadge({ ok, okLabel, koLabel }: { ok: boolean; okLabel: string; koLabel: string }) {
  return (
    <span
      className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ${
        ok ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'
      }`}
    >
      {ok ? <CheckCircle2 className="h-3.5 w-3.5" /> : <XCircle className="h-3.5 w-3.5" />}
      {ok ? okLabel : koLabel}
    </span>
  );
}

export default function RestaurantPaymentsPage() {
  const locale = getPreferredLocale();
  const [config, setConfig] = useState<PaymentConfiguration | null>(null);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setError('');
    try {
      const res = await apiFetch('/restaurant/payments/configuration');
      if (!res.ok) throw new Error(String(res.status));
      const payload = (await res.json()) as { data?: PaymentConfiguration };
      setConfig(payload.data ?? null);
    } catch {
      setError(t(locale, 'restaurant.payments.loadError', "Impossible de charger l'état de l'encaissement."));
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const configuredLabel = t(locale, 'restaurant.payments.configured', 'Configuré');
  const notConfiguredLabel = t(locale, 'restaurant.payments.notConfigured', 'Non configuré');

  return (
    <ModulePageShell
      icon={Wallet}
      title={t(locale, 'restaurant.payments.title', 'Encaissement')}
      description={t(locale, 'restaurant.payments.desc', "État de la configuration des paiements en ligne (profils d'encaissement)")}
    >
      {error ? <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p> : null}

      {config ? (
        <div className="space-y-4">
          <div
            className={`rounded-2xl border p-5 shadow-sm ${
              config.online_enabled ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'
            }`}
          >
            <p className="text-sm font-bold text-slate-900">
              {config.online_enabled
                ? t(locale, 'restaurant.payments.onlineEnabled', 'Paiement en ligne actif')
                : t(locale, 'restaurant.payments.onlineDisabled', 'Paiement en ligne non configuré')}
            </p>
            {!config.online_enabled ? (
              <p className="mt-1 text-sm text-slate-600">
                {t(
                  locale,
                  'restaurant.payments.onlineDisabledHint',
                  "Vos clients ne peuvent pas payer en ligne : ajoutez un profil d'encaissement (clés Stripe ou mobile money) puis activez-le. Le paiement sur place reste toujours possible.",
                )}
              </p>
            ) : null}
          </div>

          <div className="grid gap-4 md:grid-cols-3">
            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="flex items-center gap-2">
                <CreditCard className="h-5 w-5 text-indigo-600" />
                <p className="text-sm font-bold text-slate-900">
                  {t(locale, 'restaurant.payments.cardOnline', 'Carte en ligne (Stripe)')}
                </p>
              </div>
              <div className="mt-3">
                <StatusBadge ok={config.card_online.configured} okLabel={configuredLabel} koLabel={notConfiguredLabel} />
              </div>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="flex items-center gap-2">
                <Smartphone className="h-5 w-5 text-violet-600" />
                <p className="text-sm font-bold text-slate-900">
                  {t(locale, 'restaurant.payments.mobileMoney', 'Mobile money')}
                </p>
              </div>
              <div className="mt-3 flex flex-wrap gap-2">
                <StatusBadge ok={config.mobile_money.configured} okLabel={configuredLabel} koLabel={notConfiguredLabel} />
                <span className="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                  {config.mobile_money.sandbox
                    ? t(locale, 'restaurant.payments.sandbox', 'Mode sandbox')
                    : t(locale, 'restaurant.payments.production', 'Production')}
                </span>
                {!config.mobile_money.sandbox ? (
                  <StatusBadge
                    ok={config.mobile_money.profile_active}
                    okLabel={t(locale, 'restaurant.payments.profileActive', 'Profil actif')}
                    koLabel={t(locale, 'restaurant.payments.profileMissing', 'Aucun profil actif')}
                  />
                ) : null}
              </div>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="flex items-center gap-2">
                <Banknote className="h-5 w-5 text-emerald-600" />
                <p className="text-sm font-bold text-slate-900">
                  {t(locale, 'restaurant.payments.payOnSite', 'Paiement sur place (caisse)')}
                </p>
              </div>
              <div className="mt-3">
                <StatusBadge
                  ok
                  okLabel={t(locale, 'restaurant.payments.alwaysAvailable', 'Toujours disponible')}
                  koLabel=""
                />
              </div>
            </div>
          </div>

          <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <Link href="/settings/encaissements" className="text-sm font-semibold text-indigo-600 hover:text-indigo-800">
              {t(locale, 'restaurant.payments.manageProfiles', "Gérer les profils d'encaissement")} →
            </Link>
            <p className="mt-1 text-sm text-slate-500">
              {t(
                locale,
                'restaurant.payments.manageProfilesHint',
                'Les clés Stripe et les comptes mobile money se gèrent dans Réglages → Encaissements.',
              )}
            </p>
          </div>
        </div>
      ) : null}
    </ModulePageShell>
  );
}
