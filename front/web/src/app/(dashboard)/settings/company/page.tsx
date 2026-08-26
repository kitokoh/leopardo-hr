'use client';

/**
 * Paramètres entreprise — Coordonnées bancaires (IBAN / BIC).
 *
 * Issue #5613 — L'export SEPA pain.001.001.03 retournait 422
 * MISSING_COMPANY_IBAN faute de cette interface de saisie.
 */

import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
import { Building2, AlertTriangle, CheckCircle2, Loader2, Save } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

const emptySubscribe = () => () => {};

type BankDetails = {
  company_iban: string | null;
  company_bic: string | null;
};

/** Formate un IBAN brut en groupes de 4 caractères (lisibilité). */
function formatIban(raw: string): string {
  return raw.replace(/\s/g, '').replace(/(.{4})/g, '$1 ').trim();
}

/** Normalise un IBAN pour l'envoi API (sans espaces, majuscules). */
function normalizeIban(raw: string): string {
  return raw.replace(/\s/g, '').toUpperCase();
}

/** Validation côté client — le backend revalide avec ValidIban. */
function isIbanLike(value: string): boolean {
  const normalized = normalizeIban(value);
  return /^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/.test(normalized);
}

/** Validation BIC/SWIFT : 8 ou 11 caractères alphanumériques. */
function isBicLike(value: string): boolean {
  if (!value) return true; // BIC optionnel
  return /^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/.test(value.toUpperCase());
}

export default function CompanyBankDetailsPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [iban, setIban] = useState('');
  const [bic, setBic] = useState('');
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  // Chargement initial
  useEffect(() => {
    let active = true;
    setLoading(true);

    apiFetch('/company/bank-details')
      .then((r) => r.json() as Promise<{ data?: BankDetails }>)
      .then((payload) => {
        if (!active) return;
        setIban(payload.data?.company_iban ? formatIban(payload.data.company_iban) : '');
        setBic(payload.data?.company_bic ?? '');
      })
      .catch(() => {
        if (active) setErrorMsg(i18nT(locale, 'bankDetails.loadError', 'Impossible de charger les coordonnées bancaires.'));
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => { active = false; };
  }, [locale]);

  const handleSave = useCallback(async () => {
    setSuccessMsg(null);
    setErrorMsg(null);

    const normalizedIban = normalizeIban(iban);
    const normalizedBic = bic.trim().toUpperCase() || null;

    if (!isIbanLike(normalizedIban)) {
      setErrorMsg(i18nT(locale, 'bankDetails.invalidIban', 'IBAN invalide. Vérifiez le format (ex : FR76 3000 6000 0112 3456 7890 189).'));
      return;
    }

    if (bic && !isBicLike(bic)) {
      setErrorMsg(i18nT(locale, 'bankDetails.invalidBic', 'BIC invalide. Format attendu : 8 ou 11 caractères (ex : BNPAFRPPXXX).'));
      return;
    }

    setSaving(true);

    try {
      await apiFetch('/company/bank-details', {
        method: 'PATCH',
        body: JSON.stringify({ company_iban: normalizedIban, company_bic: normalizedBic }),
      });
      setSuccessMsg(i18nT(locale, 'bankDetails.saveSuccess', 'Coordonnées bancaires enregistrées. L\'export SEPA est désormais disponible.'));
    } catch (err) {
      if (err instanceof ApiError) {
        setErrorMsg(err.message);
      } else {
        setErrorMsg(i18nT(locale, 'bankDetails.saveError', 'Une erreur est survenue lors de la sauvegarde.'));
      }
    } finally {
      setSaving(false);
    }
  }, [iban, bic, locale]);

  const ibanValid = isIbanLike(normalizeIban(iban));
  const bicValid = !bic || isBicLike(bic);

  return (
    <ModulePageShell
      title={i18nT(locale, 'bankDetails.pageTitle', 'Coordonnées bancaires')}
      subtitle={i18nT(locale, 'bankDetails.pageSubtitle', 'IBAN et BIC de votre entreprise, requis pour l\'export des virements SEPA.')}
      accentClassName="border-emerald-200/50"
    >
      {loading ? (
        <div className="flex items-center justify-center py-16">
          <Loader2 className="h-8 w-8 animate-spin text-emerald-500" aria-hidden="true" />
        </div>
      ) : (
        <div className="max-w-2xl space-y-6">
          {/* Bannière d'information SEPA */}
          {!iban && (
            <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
              <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-500" aria-hidden="true" />
              <div>
                <p className="font-bold">
                  {i18nT(locale, 'bankDetails.warningTitle', 'Export SEPA indisponible')}
                </p>
                <p className="mt-1">
                  {i18nT(locale, 'bankDetails.warningBody', 'Aucun IBAN d\'entreprise n\'est configuré. L\'export des virements salariaux retournera une erreur tant que ce champ n\'est pas renseigné.')}
                </p>
              </div>
            </div>
          )}

          {/* Messages de retour */}
          {successMsg && (
            <div className="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800">
              <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-500" aria-hidden="true" />
              {successMsg}
            </div>
          )}
          {errorMsg && (
            <div role="alert" className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-800">
              {errorMsg}
            </div>
          )}

          {/* Formulaire */}
          <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-6 flex items-center gap-3">
              <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-950 text-white">
                <Building2 className="h-5 w-5" aria-hidden="true" />
              </div>
              <div>
                <h2 className="text-lg font-black text-slate-950">
                  {i18nT(locale, 'bankDetails.sectionTitle', 'Compte débiteur SEPA')}
                </h2>
                <p className="text-xs text-slate-500">
                  {i18nT(locale, 'bankDetails.sectionSubtitle', 'Ces informations apparaissent dans le fichier pain.001.001.03 envoyé à votre banque.')}
                </p>
              </div>
            </div>

            <div className="space-y-5">
              {/* IBAN */}
              <div>
                <label
                  htmlFor="company-iban"
                  className="block text-sm font-bold text-slate-700 mb-1.5"
                >
                  {i18nT(locale, 'bankDetails.ibanLabel', 'IBAN')}
                  {' '}
                  <span className="text-red-500" aria-label="obligatoire">*</span>
                </label>
                <input
                  id="company-iban"
                  type="text"
                  inputMode="text"
                  autoComplete="off"
                  spellCheck={false}
                  placeholder="FR76 3000 6000 0112 3456 7890 189"
                  className={[
                    'block h-12 w-full rounded-2xl border bg-transparent/50 px-4 text-slate-950 shadow-sm outline-none transition',
                    'placeholder:text-slate-400 focus:ring-2 focus:ring-emerald-500/20 font-mono text-sm',
                    iban && !ibanValid
                      ? 'border-red-300 focus:border-red-500'
                      : 'border-slate-200 focus:border-emerald-500',
                  ].join(' ')}
                  value={iban}
                  onChange={(e) => {
                    setIban(formatIban(e.target.value));
                    setSuccessMsg(null);
                    setErrorMsg(null);
                  }}
                />
                <p className="mt-1.5 text-xs text-slate-500">
                  {i18nT(locale, 'bankDetails.ibanHint', 'Pour DZ : saisissez votre RIB à 20 chiffres. Pour MA : RIB 24 caractères.')}
                </p>
              </div>

              {/* BIC */}
              <div>
                <label
                  htmlFor="company-bic"
                  className="block text-sm font-bold text-slate-700 mb-1.5"
                >
                  {i18nT(locale, 'bankDetails.bicLabel', 'BIC / SWIFT')}
                  {' '}
                  <span className="text-slate-400 text-xs font-normal">
                    {i18nT(locale, 'bankDetails.bicOptional', '(optionnel)')}
                  </span>
                </label>
                <input
                  id="company-bic"
                  type="text"
                  inputMode="text"
                  autoComplete="off"
                  spellCheck={false}
                  placeholder="BNPAFRPPXXX"
                  className={[
                    'block h-12 w-full rounded-2xl border bg-transparent/50 px-4 text-slate-950 shadow-sm outline-none transition',
                    'placeholder:text-slate-400 focus:ring-2 focus:ring-emerald-500/20 font-mono text-sm',
                    bic && !bicValid
                      ? 'border-red-300 focus:border-red-500'
                      : 'border-slate-200 focus:border-emerald-500',
                  ].join(' ')}
                  value={bic}
                  onChange={(e) => {
                    setBic(e.target.value.toUpperCase());
                    setSuccessMsg(null);
                    setErrorMsg(null);
                  }}
                />
              </div>

              {/* Bouton sauvegarde */}
              <div className="flex justify-end pt-2">
                <Button
                  type="button"
                  loading={saving}
                  disabled={saving || !ibanValid}
                  className="inline-flex h-11 items-center gap-2 rounded-2xl bg-emerald-600 px-6 text-xs font-black uppercase tracking-widest text-white shadow-lg hover:bg-emerald-500 disabled:opacity-50"
                  onClick={() => void handleSave()}
                >
                  {!saving && <Save className="h-4 w-4" aria-hidden="true" />}
                  {saving
                    ? i18nT(locale, 'bankDetails.saving', 'Enregistrement...')
                    : i18nT(locale, 'bankDetails.save', 'Enregistrer')}
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}
    </ModulePageShell>
  );
}
