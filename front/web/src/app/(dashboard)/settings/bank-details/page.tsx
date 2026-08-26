'use client';

import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
import { Building2, CreditCard, Save, Loader2, CheckCircle2 } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

/**
 * #5613 — Page de paramétrage des coordonnées bancaires entreprise.
 *
 * Permet au manager principal de configurer l'IBAN et le BIC de la société,
 * nécessaires pour les exports bancaires SEPA (pain.001.001.03).
 *
 * Endpoints :
 *   GET  /api/v1/company/bank-details → {data: {company_iban, company_bic}}
 *   PATCH /api/v1/company/bank-details → mise à jour
 */
export default function BankDetailsSettingsPage() {
  const locale = useSyncExternalStore<AppLocale>(() => () => {}, getPreferredLocale, () => 'fr');

  const [iban, setIban]       = useState('');
  const [bic, setBic]         = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving]   = useState(false);
  const [saved, setSaved]     = useState(false);
  const [error, setError]     = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/company/bank-details');
      const payload = (await res.json()) as { data?: { company_iban?: string | null; company_bic?: string | null } };
      setIban(payload.data?.company_iban ?? '');
      setBic(payload.data?.company_bic ?? '');
    } catch {
      setError(i18nT(locale, 'bankDetails.loadError', 'Impossible de charger les coordonnées bancaires.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => { void load(); }, [load]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setSaved(false);
    setError(null);

    // Normaliser : supprimer espaces/tirets de l'IBAN, majuscules pour BIC.
    const normalizedIban = iban.replace(/[\s-]/g, '').toUpperCase() || null;
    const normalizedBic  = bic.trim().toUpperCase() || null;

    try {
      await apiFetch('/company/bank-details', {
        method: 'PATCH',
        body: JSON.stringify({ company_iban: normalizedIban, company_bic: normalizedBic }),
      });
      setIban(normalizedIban ?? '');
      setBic(normalizedBic ?? '');
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError(i18nT(locale, 'bankDetails.saveError', 'Erreur lors de la sauvegarde.'));
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <section className="space-y-6">
      {/* En-tête de page */}
      <div className="flex items-start gap-4">
        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
          <CreditCard className="h-6 w-6" aria-hidden="true" />
        </div>
        <div>
          <h1 className="text-2xl font-black text-slate-900 dark:text-white">
            {i18nT(locale, 'bankDetails.title', 'Coordonnées bancaires')}
          </h1>
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {i18nT(locale, 'bankDetails.description', 'IBAN et BIC de l\'entreprise pour les exports de virements bancaires (SEPA, CCP DZ…).')}
          </p>
        </div>
      </div>

      <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      {loading ? (
        <div className="flex items-center gap-2 py-8 text-slate-500">
          <Loader2 className="h-5 w-5 animate-spin" aria-hidden="true" />
          <span className="text-sm">{i18nT(locale, 'common.loading', 'Chargement…')}</span>
        </div>
      ) : (
        <form onSubmit={(e) => void handleSubmit(e)} className="space-y-6">
          {/* Alerte SEPA si IBAN non configuré */}
          {!iban && (
            <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
              <Building2 className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" aria-hidden="true" />
              <div>
                <p className="text-sm font-semibold text-amber-800">
                  {i18nT(locale, 'bankDetails.missingIbanWarning', 'IBAN non configuré')}
                </p>
                <p className="mt-0.5 text-xs text-amber-700">
                  {i18nT(locale, 'bankDetails.missingIbanHint', 'L\'export SEPA retournera une erreur 422 tant que l\'IBAN n\'est pas renseigné.')}
                </p>
              </div>
            </div>
          )}

          {/* IBAN */}
          <div>
            <label htmlFor="bank-iban" className="block text-sm font-semibold text-slate-700 dark:text-slate-200">
              {i18nT(locale, 'bankDetails.ibanLabel', 'IBAN (compte débiteur)')}
              <span className="ml-1 text-xs font-normal text-slate-400">— {i18nT(locale, 'bankDetails.ibanNote', 'Supprimer les espaces lors de la saisie')}</span>
            </label>
            <input
              id="bank-iban"
              name="company_iban"
              type="text"
              value={iban}
              onChange={(e) => setIban(e.target.value)}
              placeholder={i18nT(locale, 'bankDetails.ibanPlaceholder', 'DZ21 0001 5000 0000 0000 0000 0')}
              className="mt-1.5 h-12 w-full rounded-xl border border-slate-200 bg-white px-4 font-mono text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
              autoComplete="off"
              spellCheck={false}
            />
            <p className="mt-1 text-xs text-slate-500">
              {i18nT(locale, 'bankDetails.ibanHelp', 'Format ISO 13616. Pour DZ : RIB 20 chiffres accepté.')}
            </p>
          </div>

          {/* BIC */}
          <div>
            <label htmlFor="bank-bic" className="block text-sm font-semibold text-slate-700 dark:text-slate-200">
              {i18nT(locale, 'bankDetails.bicLabel', 'BIC / SWIFT (optionnel)')}
            </label>
            <input
              id="bank-bic"
              name="company_bic"
              type="text"
              value={bic}
              onChange={(e) => setBic(e.target.value)}
              placeholder="BNPADZDZ"
              maxLength={11}
              className="mt-1.5 h-12 w-full rounded-xl border border-slate-200 bg-white px-4 font-mono text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
              autoComplete="off"
              spellCheck={false}
            />
            <p className="mt-1 text-xs text-slate-500">
              {i18nT(locale, 'bankDetails.bicHelp', '8 ou 11 caractères alphanumériques. Optionnel pour les exports CCP/BNA DZ.')}
            </p>
          </div>

          {/* Error */}
          {error && (
            <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700 dark:bg-red-950/50 dark:text-red-300">
              {error}
            </p>
          )}

          {/* Actions */}
          <div className="flex items-center gap-4">
            <button
              type="submit"
              disabled={saving}
              className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-500 disabled:opacity-60"
            >
              {saving ? (
                <><Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />{i18nT(locale, 'common.saving', 'Enregistrement…')}</>
              ) : (
                <><Save className="h-4 w-4" aria-hidden="true" />{i18nT(locale, 'bankDetails.save', 'Enregistrer')}</>
              )}
            </button>
            {saved && (
              <span className="flex items-center gap-1.5 text-sm font-semibold text-emerald-600">
                <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
                {i18nT(locale, 'bankDetails.savedConfirm', 'Coordonnées bancaires enregistrées.')}
              </span>
            )}
          </div>
        </form>
      )}
      </div>
    </section>
  );
}
