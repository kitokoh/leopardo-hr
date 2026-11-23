'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Plug, Plus, Trash2, Loader2, RefreshCw, CheckCircle2, AlertTriangle, ExternalLink, X } from 'lucide-react';
import Link from 'next/link';
import { ApiError, apiFetch } from '@/lib/api-client';

/**
 * #9850 (BC-14 INTEGRATION) — onglet « Connecteurs », surface TENANT.
 *
 * Ce panneau est monté DANS l'onglet « Intégrations » existant
 * (`/settings/developer`) : la mission demande d'étendre l'existant plutôt que
 * de créer un doublon. Les clés API et les webhooks restent dans leurs sections
 * actuelles ; ce panneau ajoute ce qui manquait :
 *
 *   1. les SURFACES EXTERNES du client — site WordPress, page produit Facebook,
 *      boutique Shopify/Instagram/TikTok Shop, fiche Google Merchant… ;
 *   2. un formulaire GÉNÉRÉ depuis `field_schema` du catalogue : aucun champ
 *      n'est codé ici, donc ajouter un type de connecteur ne demande aucune
 *      modification de ce fichier ;
 *   3. l'index des intégrations déjà configurées (encaissements, webhooks,
 *      calendrier, pointeuses) avec un lien vers leur écran natif — c'est lui
 *      qui reste la source de vérité, on ne duplique pas la saisie.
 *
 * Secrets : write-only. L'API ne renvoie qu'un masque ; laisser un champ vide
 * en édition conserve la valeur en place.
 */

type ConnectorField = {
  key: string;
  label: string;
  type: string;
  required?: boolean;
  secret?: boolean;
  help?: string | null;
  placeholder?: string | null;
  options?: Array<{ value: string; label?: string }>;
};

type CatalogEntry = {
  key: string;
  label: string;
  category: string;
  description?: string | null;
  fields: ConnectorField[];
};

type SecretInfo = { configured: boolean; mask: string | null };

type Connection = {
  id: number;
  catalog_key: string;
  label: string;
  status: string;
  config: Record<string, unknown>;
  secrets: Record<string, SecretInfo>;
  last_check_status?: string | null;
  last_checked_at?: string | null;
};

type Integration = {
  key: string;
  label: string;
  category: string;
  href: string;
  count: number;
  configured: boolean;
  detail: string;
};

type CheckResult = {
  status: string;
  message: string;
  checks?: Array<{ key: string; ok: boolean; detail: string }>;
};

const STATUS_STYLES: Record<string, string> = {
  active: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  draft: 'bg-amber-50 text-amber-700 border-amber-200',
  error: 'bg-red-50 text-red-700 border-red-200',
  revoked: 'bg-slate-100 text-slate-600 border-slate-200',
};

const STATUS_LABELS: Record<string, string> = {
  active: 'Actif',
  draft: 'Brouillon',
  error: 'En erreur',
  revoked: 'Révoqué',
};

export function ConnectorsPanel() {
  const [catalog, setCatalog] = useState<CatalogEntry[]>([]);
  const [connections, setConnections] = useState<Connection[]>([]);
  const [integrations, setIntegrations] = useState<Integration[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [formOpen, setFormOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [catalogKey, setCatalogKey] = useState('');
  const [label, setLabel] = useState('');
  const [config, setConfig] = useState<Record<string, string>>({});
  const [secrets, setSecrets] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const [testingId, setTestingId] = useState<number | null>(null);
  const [checks, setChecks] = useState<Record<number, CheckResult>>({});

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [catalogRes, connectionsRes, overviewRes] = await Promise.all([
        apiFetch('/connectors/catalog'),
        apiFetch('/connectors/connections'),
        apiFetch('/connectors/overview'),
      ]);

      const catalogData = (await catalogRes.json()) as { data?: { items?: CatalogEntry[] } };
      const connectionsData = (await connectionsRes.json()) as { data?: { items?: Connection[] } };
      const overviewData = (await overviewRes.json()) as { data?: { integrations?: Integration[] } };

      setCatalog(catalogData.data?.items ?? []);
      setConnections(connectionsData.data?.items ?? []);
      setIntegrations(overviewData.data?.integrations ?? []);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de charger les connecteurs.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const activeEntry = useMemo(
    () => catalog.find((entry) => entry.key === catalogKey) ?? null,
    [catalog, catalogKey]
  );

  const editingConnection = useMemo(
    () => connections.find((connection) => connection.id === editingId) ?? null,
    [connections, editingId]
  );

  const resetForm = () => {
    setCatalogKey('');
    setLabel('');
    setConfig({});
    setSecrets({});
    setFormError(null);
  };

  const openCreate = () => {
    resetForm();
    setEditingId(null);
    setFormOpen(true);
  };

  const openEdit = (connection: Connection) => {
    resetForm();
    setEditingId(connection.id);
    setCatalogKey(connection.catalog_key);
    setLabel(connection.label);
    setConfig(
      Object.fromEntries(
        Object.entries(connection.config ?? {}).map(([key, value]) => [key, value == null ? '' : String(value)])
      )
    );
    // Secrets write-only : champs vides = « conserver la valeur en place ».
    setSecrets(
      Object.fromEntries(
        (catalog.find((entry) => entry.key === connection.catalog_key)?.fields ?? [])
          .filter((field) => field.secret)
          .map((field) => [field.key, ''])
      )
    );
    setFormOpen(true);
  };

  const closeForm = () => {
    setFormOpen(false);
    setEditingId(null);
    resetForm();
  };

  const save = async () => {
    setSaving(true);
    setFormError(null);

    try {
      // Write-only : seuls les secrets réellement saisis partent.
      const providedSecrets = Object.fromEntries(
        Object.entries(secrets).filter(([, value]) => (value ?? '').trim() !== '')
      );

      const payload: Record<string, unknown> = {
        label,
        config,
        ...(Object.keys(providedSecrets).length ? { secrets: providedSecrets } : {}),
      };

      if (editingId) {
        await apiFetch(`/connectors/connections/${editingId}`, {
          method: 'PATCH',
          body: JSON.stringify(payload),
        });
      } else {
        await apiFetch('/connectors/connections', {
          method: 'POST',
          body: JSON.stringify({ ...payload, catalog_key: catalogKey }),
        });
      }

      closeForm();
      await load();
    } catch (err) {
      if (err instanceof ApiError) {
        // Le détail par champ rend l'erreur actionnable.
        const body = err.payload as { violations?: string[]; localized_message?: string } | undefined;
        setFormError(body?.violations?.length ? body.violations.join(' ') : body?.localized_message ?? err.message);
      } else {
        setFormError("L'enregistrement a échoué.");
      }
    } finally {
      setSaving(false);
    }
  };

  const testConnection = async (connection: Connection) => {
    setTestingId(connection.id);
    try {
      const res = await apiFetch(`/connectors/connections/${connection.id}/test`, { method: 'POST' });
      const data = (await res.json()) as { data?: { check?: CheckResult } };
      if (data.data?.check) {
        setChecks((current) => ({ ...current, [connection.id]: data.data!.check! }));
      }
      await load();
    } catch (err) {
      setChecks((current) => ({
        ...current,
        [connection.id]: {
          status: 'unreachable',
          message: err instanceof ApiError ? err.message : 'Le test a échoué.',
          checks: [],
        },
      }));
    } finally {
      setTestingId(null);
    }
  };

  const remove = async (connection: Connection) => {
    if (!window.confirm(`Supprimer le connecteur « ${connection.label} » ?`)) {
      return;
    }

    try {
      await apiFetch(`/connectors/connections/${connection.id}`, { method: 'DELETE' });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'La suppression a échoué.');
    }
  };

  const fieldLabel = (connection: Connection, fieldKey: string) =>
    catalog.find((entry) => entry.key === connection.catalog_key)?.fields.find((field) => field.key === fieldKey)?.label ??
    fieldKey;

  return (
    <section className="rounded-2xl border border-app-border bg-white p-6 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-3 border-b border-app-border pb-4">
        <div className="flex items-center gap-3">
          <div className="rounded-xl bg-indigo-50 p-2 text-indigo-700">
            <Plug className="h-5 w-5" />
          </div>
          <div>
            <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">Connecteurs</h2>
            <p className="mt-1 text-xs text-slate-500">
              Branchez votre site, vos pages produits et vos boutiques — et retrouvez ici tout ce qui est connecté à
              l&apos;extérieur.
            </p>
          </div>
        </div>
        <button
          onClick={openCreate}
          className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-slate-800"
        >
          <Plus className="h-4 w-4" />
          Ajouter
        </button>
      </div>

      {error ? (
        <div className="mt-4 flex items-center justify-between rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          <span>{error}</span>
          <button onClick={() => setError(null)} className="text-red-400 hover:text-red-600">
            <X className="h-4 w-4" />
          </button>
        </div>
      ) : null}

      {loading ? (
        <div className="mt-6 flex items-center gap-2 text-sm text-slate-500">
          <Loader2 className="h-4 w-4 animate-spin" />
          Chargement des connecteurs…
        </div>
      ) : (
        <>
          {/* ── Connexions du tenant ─────────────────────────────────────── */}
          <div className="mt-5 space-y-3">
            {connections.length === 0 ? (
              <p className="rounded-xl border border-dashed border-app-border px-4 py-6 text-center text-sm text-slate-500">
                Aucun connecteur pour l&apos;instant. Ajoutez votre site ou votre page produit pour commencer.
              </p>
            ) : (
              connections.map((connection) => (
                <article key={connection.id} className="rounded-xl border border-app-border p-4">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                      <div className="flex flex-wrap items-center gap-2">
                        <h3 className="truncate text-sm font-bold text-slate-900">{connection.label}</h3>
                        <span
                          className={`rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ${
                            STATUS_STYLES[connection.status] ?? STATUS_STYLES.draft
                          }`}
                        >
                          {STATUS_LABELS[connection.status] ?? connection.status}
                        </span>
                      </div>
                      <p className="mt-1 text-xs text-slate-500">
                        {catalog.find((entry) => entry.key === connection.catalog_key)?.label ?? connection.catalog_key}
                      </p>
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                      <button
                        onClick={() => void testConnection(connection)}
                        disabled={testingId === connection.id}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-app-border px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                      >
                        {testingId === connection.id ? (
                          <Loader2 className="h-3.5 w-3.5 animate-spin" />
                        ) : (
                          <RefreshCw className="h-3.5 w-3.5" />
                        )}
                        Tester
                      </button>
                      <button
                        onClick={() => openEdit(connection)}
                        className="rounded-lg border border-app-border px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                      >
                        Modifier
                      </button>
                      <button
                        onClick={() => void remove(connection)}
                        className="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </div>

                  {/* Configuration non secrète réellement enregistrée. */}
                  <dl className="mt-3 grid gap-x-6 gap-y-1 sm:grid-cols-2">
                    {Object.entries(connection.config ?? {}).map(([key, value]) => (
                      <div key={key} className="flex items-baseline justify-between gap-3 text-xs">
                        <dt className="text-slate-500">{fieldLabel(connection, key)}</dt>
                        <dd className="truncate font-medium text-slate-800">{String(value)}</dd>
                      </div>
                    ))}
                  </dl>

                  {/* Secrets : masques uniquement. */}
                  {Object.keys(connection.secrets ?? {}).length > 0 ? (
                    <div className="mt-2 flex flex-wrap gap-2">
                      {Object.entries(connection.secrets).map(([key, info]) => (
                        <span
                          key={key}
                          className="rounded-md bg-slate-100 px-2 py-1 font-mono text-[11px] text-slate-600"
                        >
                          {fieldLabel(connection, key)} : {info.configured ? info.mask : 'non configuré'}
                        </span>
                      ))}
                    </div>
                  ) : null}

                  {/* Verdict du dernier test : le détail des contrôles exécutés. */}
                  {checks[connection.id] ? (
                    <div
                      className={`mt-3 rounded-lg border p-3 text-xs ${
                        checks[connection.id].status === 'validated'
                          ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                          : 'border-amber-200 bg-amber-50 text-amber-800'
                      }`}
                    >
                      <p className="flex items-center gap-1.5 font-semibold">
                        {checks[connection.id].status === 'validated' ? (
                          <CheckCircle2 className="h-3.5 w-3.5" />
                        ) : (
                          <AlertTriangle className="h-3.5 w-3.5" />
                        )}
                        {checks[connection.id].message}
                      </p>
                      {checks[connection.id].checks?.length ? (
                        <ul className="mt-1.5 space-y-0.5">
                          {checks[connection.id].checks!.map((check) => (
                            <li key={check.key} className="flex items-start gap-1.5">
                              <span>{check.ok ? '✓' : '✕'}</span>
                              <span>{check.detail}</span>
                            </li>
                          ))}
                        </ul>
                      ) : null}
                    </div>
                  ) : null}
                </article>
              ))
            )}
          </div>

          {/* ── Intégrations existantes (lecture seule) ──────────────────── */}
          {integrations.length > 0 ? (
            <div className="mt-6">
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                Intégrations déjà configurées
              </h3>
              <p className="mt-1 text-xs text-slate-500">
                Chaque intégration garde son écran dédié : c&apos;est lui qui fait foi. Ce tableau les regroupe.
              </p>
              <ul className="mt-3 divide-y divide-app-border rounded-xl border border-app-border">
                {integrations.map((integration) => (
                  <li key={integration.key} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div className="min-w-0">
                      <p className="text-sm font-semibold text-slate-900">{integration.label}</p>
                      <p className="text-xs text-slate-500">{integration.detail}</p>
                    </div>
                    <div className="flex shrink-0 items-center gap-3">
                      <span
                        className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ${
                          integration.configured ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'
                        }`}
                      >
                        {integration.configured ? `${integration.count} configuré(s)` : 'Non configuré'}
                      </span>
                      <Link
                        href={integration.href}
                        className="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:underline"
                      >
                        Ouvrir
                        <ExternalLink className="h-3 w-3" />
                      </Link>
                    </div>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
        </>
      )}

      {/* ── Formulaire GÉNÉRÉ depuis le schéma du catalogue ─────────────── */}
      {formOpen ? (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4">
          <div className="my-8 w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl">
            <div className="flex items-start justify-between gap-4">
              <h2 className="text-base font-bold text-slate-900">
                {editingId ? 'Modifier le connecteur' : 'Nouveau connecteur'}
              </h2>
              <button onClick={closeForm} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="mt-5 space-y-4">
              <div>
                <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-600">Type</label>
                <select
                  value={catalogKey}
                  disabled={Boolean(editingId)}
                  onChange={(event) => {
                    // Le schéma change avec le type : on repart d'un formulaire
                    // vierge pour ne pas envoyer les champs d'un autre connecteur.
                    setCatalogKey(event.target.value);
                    setConfig({});
                    setSecrets({});
                    setFormError(null);
                  }}
                  className="w-full rounded-xl border border-app-border px-3 py-2 text-sm disabled:bg-slate-50"
                >
                  <option value="">Choisir un type…</option>
                  {catalog.map((entry) => (
                    <option key={entry.key} value={entry.key}>
                      {entry.label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-600">Nom</label>
                <input
                  value={label}
                  maxLength={120}
                  onChange={(event) => setLabel(event.target.value)}
                  placeholder="Ex. Site vitrine, Page produit Facebook…"
                  className="w-full rounded-xl border border-app-border px-3 py-2 text-sm"
                />
              </div>

              {/* Champs dynamiques : rien n'est codé en dur ici. */}
              {(activeEntry?.fields ?? []).map((field) => {
                const value = field.secret ? secrets[field.key] ?? '' : config[field.key] ?? '';
                const existingMask = field.secret ? editingConnection?.secrets?.[field.key]?.mask : null;

                const onChange = (next: string) => {
                  if (field.secret) {
                    setSecrets((current) => ({ ...current, [field.key]: next }));
                  } else {
                    setConfig((current) => ({ ...current, [field.key]: next }));
                  }
                };

                return (
                  <div key={field.key}>
                    <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-600">
                      {field.label}
                      {field.required ? <span className="ml-1 text-red-500">*</span> : null}
                    </label>

                    {field.type === 'select' ? (
                      <select
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                        className="w-full rounded-xl border border-app-border px-3 py-2 text-sm"
                      >
                        <option value="">Choisir…</option>
                        {(field.options ?? []).map((option) => (
                          <option key={option.value} value={option.value}>
                            {option.label ?? option.value}
                          </option>
                        ))}
                      </select>
                    ) : field.type === 'textarea' ? (
                      <textarea
                        value={value}
                        rows={3}
                        onChange={(event) => onChange(event.target.value)}
                        placeholder={field.placeholder ?? ''}
                        className="w-full rounded-xl border border-app-border px-3 py-2 font-mono text-xs"
                      />
                    ) : (
                      <input
                        type={field.secret ? 'password' : field.type === 'number' ? 'number' : 'text'}
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                        placeholder={existingMask ?? field.placeholder ?? ''}
                        autoComplete="off"
                        className="w-full rounded-xl border border-app-border px-3 py-2 text-sm"
                      />
                    )}

                    {field.help ? <p className="mt-1 text-xs text-slate-500">{field.help}</p> : null}
                    {field.secret ? (
                      <p className="mt-1 text-xs text-slate-500">
                        Chiffré au repos. Laissez vide pour conserver la valeur enregistrée.
                      </p>
                    ) : null}
                  </div>
                );
              })}

              {formError ? <p className="text-sm text-red-600">{formError}</p> : null}
            </div>

            <div className="mt-6 flex justify-end gap-3">
              <button
                onClick={closeForm}
                className="rounded-xl border border-app-border px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-700 hover:bg-slate-50"
              >
                Annuler
              </button>
              <button
                onClick={() => void save()}
                disabled={saving || !catalogKey || !label.trim()}
                className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-slate-800 disabled:opacity-50"
              >
                {saving ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : null}
                Enregistrer
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </section>
  );
}
