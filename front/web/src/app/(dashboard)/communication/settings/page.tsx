'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { AlertTriangle, BellRing, MailX, Plus, Settings, Trash2 } from 'lucide-react';

import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale } from '@/lib/i18n';
import {
  policyErrorKey,
  tc,
  type CommunicationCategory,
  type CommunicationFollowUpOptOut,
  type CommunicationFollowUpRule,
  type CommunicationIntegration,
  type CommunicationReplyPolicy,
  type CommunicationReplyPolicyLevel,
} from '@/lib/communication';
import { CommunicationTabs } from '../communication-tabs';

type PageError = 'forbidden' | 'network' | null;

const POLICY_LEVELS: CommunicationReplyPolicyLevel[] = ['off', 'draft', 'confirm', 'auto'];

const POLICY_LABEL_KEYS: Record<CommunicationReplyPolicyLevel, string> = {
  off: 'policies.off',
  draft: 'policies.draft',
  confirm: 'policies.confirmPolicy',
  auto: 'policies.auto',
};

/**
 * BC-29 COMMUNICATION — R6 (#7691) : réglages du module pour l'espace
 * client. Politiques de réponse par boîte × catégorie (off/draft/confirm/auto,
 * R5 #7690 — l'option auto est grisée quand `auto_blocked` est vrai), règles
 * de relance R4 (#7689) et exclusions de relance du tenant.
 */
export default function CommunicationSettingsPage() {
  const locale = getPreferredLocale();

  const [integrations, setIntegrations] = useState<CommunicationIntegration[]>([]);
  const [categories, setCategories] = useState<CommunicationCategory[]>([]);
  const [policies, setPolicies] = useState<CommunicationReplyPolicy[]>([]);
  const [rules, setRules] = useState<CommunicationFollowUpRule[]>([]);
  const [optOuts, setOptOuts] = useState<CommunicationFollowUpOptOut[]>([]);
  const [selectedIntegration, setSelectedIntegration] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<PageError>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const [policyBusy, setPolicyBusy] = useState<string | null>(null);
  const [ruleName, setRuleName] = useState('');
  const [ruleSteps, setRuleSteps] = useState<number[]>([3]);
  const [creatingRule, setCreatingRule] = useState(false);
  const [ruleBusy, setRuleBusy] = useState<string | null>(null);
  const [optOutEmail, setOptOutEmail] = useState('');
  const [optOutBusy, setOptOutBusy] = useState(false);

  const activeIntegrations = useMemo(
    () => integrations.filter((integration) => integration.status === 'active'),
    [integrations],
  );

  const activeCategories = useMemo(
    () => categories.filter((category) => category.active),
    [categories],
  );

  const selectedRules = useMemo(
    () => rules.filter((rule) => rule.integration_id === selectedIntegration),
    [rules, selectedIntegration],
  );

  const policyFor = useCallback(
    (categoryKey: string): CommunicationReplyPolicy | undefined =>
      policies.find(
        (policy) =>
          policy.integration_id === selectedIntegration && policy.category_key === categoryKey,
      ),
    [policies, selectedIntegration],
  );

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [integrationsRes, categoriesRes, policiesRes, rulesRes, optOutsRes] =
        await Promise.all([
          apiFetch('/communication/integrations'),
          apiFetch('/communication/categories'),
          apiFetch('/communication/reply-policies'),
          apiFetch('/communication/follow-up-rules'),
          apiFetch('/communication/follow-up-opt-outs'),
        ]);
      if (integrationsRes.status === 403 || integrationsRes.status === 404) {
        setError('forbidden');

        return;
      }
      const integrationsBody = (await integrationsRes.json()) as {
        data: CommunicationIntegration[];
      };
      setIntegrations(integrationsBody.data ?? []);
      setCategories(
        categoriesRes.ok
          ? (((await categoriesRes.json()) as { data: CommunicationCategory[] }).data ?? [])
          : [],
      );
      setPolicies(
        policiesRes.ok
          ? (((await policiesRes.json()) as { data: CommunicationReplyPolicy[] }).data ?? [])
          : [],
      );
      setRules(
        rulesRes.ok
          ? (((await rulesRes.json()) as { data: CommunicationFollowUpRule[] }).data ?? [])
          : [],
      );
      setOptOuts(
        optOutsRes.ok
          ? (((await optOutsRes.json()) as { data: CommunicationFollowUpOptOut[] }).data ?? [])
          : [],
      );

      const firstActive = (integrationsBody.data ?? []).find(
        (integration) => integration.status === 'active',
      );
      setSelectedIntegration((current) => current ?? firstActive?.id ?? null);
    } catch {
      setError('network');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const savePolicy = useCallback(
    async (categoryKey: string, level: CommunicationReplyPolicyLevel) => {
      if (!selectedIntegration) {
        return;
      }
      setPolicyBusy(categoryKey);
      setActionError(null);
      setNotice(null);
      try {
        const res = await apiFetch('/communication/reply-policies', {
          method: 'POST',
          body: JSON.stringify({
            integration_id: selectedIntegration,
            category_key: categoryKey,
            policy: level,
          }),
        });
        if (res.status === 422) {
          const body = (await res.json()) as { code?: string; error?: string; message?: string };
          setActionError(tc(locale, policyErrorKey(body.code ?? body.error)));

          return;
        }
        if (!res.ok) {
          setActionError(tc(locale, 'policies.saveError'));

          return;
        }
        const body = (await res.json()) as { data: CommunicationReplyPolicy };
        setPolicies((current) => {
          const rest = current.filter(
            (policy) =>
              !(
                policy.integration_id === selectedIntegration &&
                policy.category_key === categoryKey
              ),
          );

          return [...rest, body.data];
        });
        setNotice(tc(locale, 'policies.saved'));
      } catch {
        setActionError(tc(locale, 'policies.saveError'));
      } finally {
        setPolicyBusy(null);
      }
    },
    [locale, selectedIntegration],
  );

  const createRule = useCallback(async () => {
    if (!selectedIntegration || ruleName.trim() === '') {
      return;
    }
    setCreatingRule(true);
    setActionError(null);
    try {
      const res = await apiFetch('/communication/follow-up-rules', {
        method: 'POST',
        body: JSON.stringify({
          integration_id: selectedIntegration,
          name: ruleName.trim(),
          active: true,
          steps: ruleSteps.map((delayDays) => ({ delay_days: delayDays })),
        }),
      });
      if (!res.ok) {
        setActionError(tc(locale, 'followUps.createError'));

        return;
      }
      const body = (await res.json()) as { data: CommunicationFollowUpRule };
      setRules((current) => [...current, body.data]);
      setRuleName('');
      setRuleSteps([3]);
      setNotice(tc(locale, 'followUps.created'));
    } catch {
      setActionError(tc(locale, 'followUps.createError'));
    } finally {
      setCreatingRule(false);
    }
  }, [locale, ruleName, ruleSteps, selectedIntegration]);

  const toggleRule = useCallback(
    async (rule: CommunicationFollowUpRule) => {
      setRuleBusy(rule.id);
      setActionError(null);
      try {
        const res = await apiFetch(`/communication/follow-up-rules/${rule.id}`, {
          method: 'PATCH',
          body: JSON.stringify({ active: !rule.active }),
        });
        if (!res.ok) {
          setActionError(tc(locale, 'followUps.updateError'));

          return;
        }
        const body = (await res.json()) as { data: CommunicationFollowUpRule };
        setRules((current) => current.map((item) => (item.id === rule.id ? body.data : item)));
        setNotice(tc(locale, 'followUps.updated'));
      } catch {
        setActionError(tc(locale, 'followUps.updateError'));
      } finally {
        setRuleBusy(null);
      }
    },
    [locale],
  );

  const deleteRule = useCallback(
    async (ruleId: string) => {
      if (!window.confirm(tc(locale, 'followUps.deleteConfirm'))) {
        return;
      }
      setRuleBusy(ruleId);
      setActionError(null);
      try {
        const res = await apiFetch(`/communication/follow-up-rules/${ruleId}`, {
          method: 'DELETE',
        });
        if (!res.ok) {
          setActionError(tc(locale, 'followUps.updateError'));

          return;
        }
        setRules((current) => current.filter((rule) => rule.id !== ruleId));
        setNotice(tc(locale, 'followUps.deleted'));
      } catch {
        setActionError(tc(locale, 'followUps.updateError'));
      } finally {
        setRuleBusy(null);
      }
    },
    [locale],
  );

  const addOptOut = useCallback(async () => {
    if (optOutEmail.trim() === '') {
      return;
    }
    setOptOutBusy(true);
    setActionError(null);
    try {
      const res = await apiFetch('/communication/follow-up-opt-outs', {
        method: 'POST',
        body: JSON.stringify({ email: optOutEmail.trim() }),
      });
      if (!res.ok) {
        setActionError(tc(locale, 'optOuts.addError'));

        return;
      }
      const body = (await res.json()) as { data: CommunicationFollowUpOptOut };
      setOptOuts((current) => [...current, body.data]);
      setOptOutEmail('');
      setNotice(tc(locale, 'optOuts.added'));
    } catch {
      setActionError(tc(locale, 'optOuts.addError'));
    } finally {
      setOptOutBusy(false);
    }
  }, [locale, optOutEmail]);

  const removeOptOut = useCallback(
    async (optOutId: string) => {
      setActionError(null);
      try {
        const res = await apiFetch(`/communication/follow-up-opt-outs/${optOutId}`, {
          method: 'DELETE',
        });
        if (!res.ok) {
          setActionError(tc(locale, 'optOuts.addError'));

          return;
        }
        setOptOuts((current) => current.filter((optOut) => optOut.id !== optOutId));
        setNotice(tc(locale, 'optOuts.removed'));
      } catch {
        setActionError(tc(locale, 'optOuts.addError'));
      }
    },
    [locale],
  );

  return (
    <ModulePageShell
      title={tc(locale, 'tabs.settings')}
      subtitle={tc(locale, 'policies.subtitle')}
      icon={Settings}
      accentClassName="from-emerald-500/10 via-white/40 to-cyan-500/10"
    >
      <CommunicationTabs />

      {loading && (
        <div className="flex items-center justify-center gap-3 py-16 text-slate-400">
          <div className="h-5 w-5 animate-spin rounded-full border-2 border-cyan-500 border-t-transparent" />
          <span className="text-sm">{tc(locale, 'common.loading')}</span>
        </div>
      )}

      {!loading && error === 'forbidden' && (
        <div className="flex items-center justify-center gap-3 rounded-3xl border border-amber-200 bg-amber-50 py-16 text-amber-700">
          <AlertTriangle className="h-6 w-6" />
          <p className="text-sm font-medium">{tc(locale, 'common.featureLocked')}</p>
        </div>
      )}

      {!loading && error === 'network' && (
        <div className="flex flex-col items-center gap-3 rounded-3xl border border-red-100 bg-red-50 py-16 text-red-600">
          <AlertTriangle className="h-6 w-6" />
          <p className="text-sm font-medium">{tc(locale, 'common.error')}</p>
          <button
            type="button"
            onClick={() => void load()}
            className="rounded-full bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
          >
            {tc(locale, 'common.retry')}
          </button>
        </div>
      )}

      {!loading && !error && (
        <div className="space-y-6">
          {notice && (
            <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
              {notice}
            </div>
          )}
          {actionError && (
            <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
              {actionError}
            </div>
          )}

          {activeIntegrations.length === 0 ? (
            <div className="flex flex-col items-center gap-2 rounded-3xl border border-dashed border-slate-200 bg-white/70 py-12 text-slate-400">
              <MailX className="h-8 w-8" />
              <p className="text-sm font-medium">{tc(locale, 'mailbox.empty')}</p>
              <p className="text-xs">{tc(locale, 'mailbox.emptyHint')}</p>
            </div>
          ) : (
            <>
              {activeIntegrations.length > 1 && (
                <label className="flex items-center gap-2 text-sm text-slate-600">
                  {tc(locale, 'policies.mailbox')}
                  <select
                    value={selectedIntegration ?? ''}
                    onChange={(event) => setSelectedIntegration(event.target.value || null)}
                    className="rounded-xl border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500"
                  >
                    {activeIntegrations.map((integration) => (
                      <option key={integration.id} value={integration.id}>
                        {integration.email ?? integration.provider}
                      </option>
                    ))}
                  </select>
                </label>
              )}

              {/* Politiques de réponse (R5) */}
              <section className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
                <h2 className="mb-1 text-lg font-bold text-slate-900">
                  {tc(locale, 'policies.title')}
                </h2>
                <p className="mb-4 text-sm text-slate-500">{tc(locale, 'policies.subtitle')}</p>
                <ul className="space-y-3">
                  {activeCategories.map((category) => {
                    const policy = policyFor(category.key);
                    const level = policy?.policy ?? 'off';
                    const autoBlocked = policy?.auto_blocked ?? false;

                    return (
                      <li
                        key={category.id}
                        className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"
                      >
                        <div>
                          <p className="font-semibold text-slate-900">{category.label}</p>
                          <p className="text-xs text-slate-400">
                            {tc(locale, `${POLICY_LABEL_KEYS[level]}`)} —{' '}
                            {tc(
                              locale,
                              level === 'off'
                                ? 'policies.offHint'
                                : level === 'draft'
                                  ? 'policies.draftHint'
                                  : level === 'confirm'
                                    ? 'policies.confirmHint'
                                    : 'policies.autoHint',
                            )}
                          </p>
                          {autoBlocked && (
                            <p className="mt-1 text-xs font-medium text-amber-600">
                              {tc(locale, 'policies.autoBlocked')}
                            </p>
                          )}
                        </div>
                        <select
                          value={level}
                          disabled={policyBusy === category.key}
                          aria-label={`${tc(locale, 'policies.level')} — ${category.label}`}
                          onChange={(event) =>
                            void savePolicy(
                              category.key,
                              event.target.value as CommunicationReplyPolicyLevel,
                            )
                          }
                          className="rounded-xl border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500"
                        >
                          {POLICY_LEVELS.map((value) => (
                            <option
                              key={value}
                              value={value}
                              disabled={value === 'auto' && autoBlocked}
                            >
                              {tc(locale, POLICY_LABEL_KEYS[value])}
                            </option>
                          ))}
                        </select>
                      </li>
                    );
                  })}
                </ul>
              </section>

              {/* Règles de relance (R4) */}
              <section className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
                <h2 className="mb-1 flex items-center gap-2 text-lg font-bold text-slate-900">
                  <BellRing className="h-5 w-5 text-cyan-600" />
                  {tc(locale, 'followUps.title')}
                </h2>
                <p className="mb-4 text-sm text-slate-500">{tc(locale, 'followUps.subtitle')}</p>

                {selectedRules.length === 0 ? (
                  <p className="mb-4 rounded-2xl border border-dashed border-slate-200 py-6 text-center text-sm text-slate-400">
                    {tc(locale, 'followUps.empty')} — {tc(locale, 'followUps.emptyHint')}
                  </p>
                ) : (
                  <ul className="mb-5 space-y-3">
                    {selectedRules.map((rule) => (
                      <li
                        key={rule.id}
                        className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"
                      >
                        <div>
                          <p className="font-semibold text-slate-900">{rule.name}</p>
                          <p className="text-xs text-slate-400">
                            {rule.steps
                              .map((step, index) =>
                                tc(locale, 'followUps.stepLabel', {
                                  position: step.position ?? index + 1,
                                  days: step.delay_days,
                                }),
                              )
                              .join(' · ')}
                          </p>
                        </div>
                        <div className="flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => void toggleRule(rule)}
                            disabled={ruleBusy === rule.id}
                            className={`rounded-full px-4 py-1.5 text-xs font-semibold disabled:opacity-50 ${
                              rule.active
                                ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'
                                : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
                            }`}
                          >
                            {tc(locale, rule.active ? 'followUps.active' : 'followUps.inactive')}
                          </button>
                          <button
                            type="button"
                            onClick={() => void deleteRule(rule.id)}
                            disabled={ruleBusy === rule.id}
                            className="inline-flex items-center gap-1 rounded-full border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 disabled:opacity-50"
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                            {tc(locale, 'followUps.deleteRule')}
                          </button>
                        </div>
                      </li>
                    ))}
                  </ul>
                )}

                <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                  <div className="grid gap-3 sm:grid-cols-2">
                    <label className="block text-sm">
                      <span className="mb-1 block font-semibold text-slate-600">
                        {tc(locale, 'followUps.ruleName')}
                      </span>
                      <input
                        type="text"
                        value={ruleName}
                        maxLength={128}
                        placeholder={tc(locale, 'followUps.ruleNamePlaceholder')}
                        onChange={(event) => setRuleName(event.target.value)}
                        className="w-full rounded-xl border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500"
                      />
                    </label>
                    <div className="text-sm">
                      <span className="mb-1 block font-semibold text-slate-600">
                        {tc(locale, 'followUps.steps')}
                      </span>
                      <div className="flex flex-wrap items-center gap-2">
                        {ruleSteps.map((delayDays, index) => (
                          <label
                            key={index}
                            className="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2 py-1"
                          >
                            <span className="text-xs text-slate-500">
                              {tc(locale, 'followUps.delayDays')}
                            </span>
                            <input
                              type="number"
                              min={1}
                              max={90}
                              value={delayDays}
                              onChange={(event) =>
                                setRuleSteps((current) =>
                                  current.map((value, i) =>
                                    i === index ? Number(event.target.value) : value,
                                  ),
                                )
                              }
                              className="w-16 rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500"
                            />
                            {ruleSteps.length > 1 && (
                              <button
                                type="button"
                                aria-label={tc(locale, 'followUps.removeStep')}
                                onClick={() =>
                                  setRuleSteps((current) => current.filter((_, i) => i !== index))
                                }
                                className="text-slate-400 hover:text-red-500"
                              >
                                <Trash2 className="h-3.5 w-3.5" />
                              </button>
                            )}
                          </label>
                        ))}
                        {ruleSteps.length < 3 && (
                          <button
                            type="button"
                            onClick={() => setRuleSteps((current) => [...current, 7])}
                            className="inline-flex items-center gap-1 rounded-full border border-cyan-200 px-3 py-1 text-xs font-semibold text-cyan-700 hover:bg-cyan-50"
                          >
                            <Plus className="h-3.5 w-3.5" />
                            {tc(locale, 'followUps.addStep')}
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                  <button
                    type="button"
                    onClick={() => void createRule()}
                    disabled={creatingRule || ruleName.trim() === ''}
                    className="mt-4 rounded-full bg-cyan-600 px-5 py-2 text-xs font-bold text-white hover:bg-cyan-700 disabled:opacity-50"
                  >
                    {tc(locale, 'followUps.create')}
                  </button>
                </div>
              </section>

              {/* Exclusions de relance (R4) */}
              <section className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
                <h2 className="mb-1 flex items-center gap-2 text-lg font-bold text-slate-900">
                  <MailX className="h-5 w-5 text-red-500" />
                  {tc(locale, 'optOuts.title')}
                </h2>
                <p className="mb-4 text-sm text-slate-500">{tc(locale, 'optOuts.subtitle')}</p>

                <div className="mb-4 flex flex-wrap items-end gap-3">
                  <label className="block text-sm">
                    <span className="mb-1 block font-semibold text-slate-600">
                      {tc(locale, 'optOuts.email')}
                    </span>
                    <input
                      type="email"
                      value={optOutEmail}
                      placeholder={tc(locale, 'optOuts.emailPlaceholder')}
                      onChange={(event) => setOptOutEmail(event.target.value)}
                      className="w-64 rounded-xl border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500"
                    />
                  </label>
                  <button
                    type="button"
                    onClick={() => void addOptOut()}
                    disabled={optOutBusy || optOutEmail.trim() === ''}
                    className="rounded-full bg-slate-800 px-5 py-2 text-xs font-bold text-white hover:bg-slate-900 disabled:opacity-50"
                  >
                    {tc(locale, 'optOuts.add')}
                  </button>
                </div>

                {optOuts.length === 0 ? (
                  <p className="rounded-2xl border border-dashed border-slate-200 py-6 text-center text-sm text-slate-400">
                    {tc(locale, 'optOuts.empty')}
                  </p>
                ) : (
                  <ul className="space-y-2">
                    {optOuts.map((optOut) => (
                      <li
                        key={optOut.id}
                        className="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-white px-4 py-2 text-sm shadow-sm"
                      >
                        <span className="font-medium text-slate-800">{optOut.email}</span>
                        <span className="flex items-center gap-2">
                          <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500">
                            {tc(locale, `optOuts.source.${optOut.source}`)}
                          </span>
                          <button
                            type="button"
                            onClick={() => void removeOptOut(optOut.id)}
                            className="rounded-full border border-red-200 px-3 py-0.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                          >
                            {tc(locale, 'optOuts.remove')}
                          </button>
                        </span>
                      </li>
                    ))}
                  </ul>
                )}
              </section>
            </>
          )}
        </div>
      )}
    </ModulePageShell>
  );
}
