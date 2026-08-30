'use client';

import { useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { GraduationCap, ShieldCheck, Users } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { StatusBadge } from '../(dashboard)/edu-manager/_components/edu-ui';

/**
 * EDU-013 (#5829) — Portail responsable légal.
 *
 * Route PUBLIQUE (hors dashboard, hors auth employé) : l'accès se fait par
 * lien expirable à usage unique (`?token=`), émis par la direction via
 * POST /edu-manager/guardians/{guardian}/access-links.
 *
 * Consommation : POST /edu-manager/guardian-portal/access-links/{token}/consume
 * — usage unique (replay → 410), expiration (410), consentement RGPD et
 * audit côté serveur. Le token n'est jamais persisté côté client.
 * Confidentialité : uniquement les enfants explicitement liés ; bulletins
 * publiés et conditionnés à can_view_grades.
 */

type GuardianChild = {
  id: number;
  student_number: string;
  display_name: string;
  status: string;
  relationship_code: string;
  can_view_grades: boolean;
  presence: {
    today_status?: string | null;
    last_30_days: Record<string, number>;
    recorded_days: number;
  };
  report_cards: {
    id: number;
    period: string;
    average?: number | null;
    published_at?: string | null;
  }[];
};

type PortalPayload = {
  guardian: { id: number; first_name?: string | null; last_name?: string | null; verified_at?: string | null };
  children: GuardianChild[];
};

type PortalError = 'invalid' | 'expired' | 'used' | 'unknown';

export default function GuardianPortalPage() {
  const searchParams = useSearchParams();
  const locale = getPreferredLocale();
  const token = searchParams.get('token');

  const [payload, setPayload] = useState<PortalPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<PortalError | null>(null);

  const consume = useCallback(async () => {
    if (!token) {
      setError('invalid');
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch(
        `/edu-manager/guardian-portal/access-links/${encodeURIComponent(token)}/consume`,
        { method: 'POST', _idempotent: true },
      );
      const json = (await res.json()) as { data?: PortalPayload };
      if (!json.data) {
        setError('unknown');
        return;
      }
      setPayload(json.data);
    } catch (err) {
      const status = (err as { status?: number })?.status;
      if (status === 410) {
        // Indistinguable côté client : expiré OU déjà utilisé (l'audit serveur
        // garde la trace exacte — aucune fuite d'information côté client).
        setError('used');
      } else if (status === 404) {
        setError('invalid');
      } else {
        setError('unknown');
      }
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    void consume();
  }, [consume]);

  return (
    <main className="min-h-screen bg-gradient-to-br from-slate-50 via-emerald-50/40 to-cyan-50/40 px-4 py-12">
      <div className="mx-auto max-w-3xl">
        <div className="mb-8 flex flex-col items-center gap-3 text-center">
          <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-cyan-600 shadow-lg shadow-emerald-500/20">
            <GraduationCap className="h-7 w-7 text-white" aria-hidden="true" />
          </div>
          <h1 className="text-3xl font-black tracking-tight text-slate-950">{t(locale, 'edu.guardian.title')}</h1>
          <p className="max-w-xl text-sm leading-relaxed text-slate-500">{t(locale, 'edu.guardian.subtitle')}</p>
        </div>

        {loading ? (
          <div className="rounded-3xl border border-white/20 bg-white/70 p-10 text-center shadow-premium backdrop-blur-xl">
            <p className="text-sm font-medium text-slate-500">{t(locale, 'edu.common.loading')}</p>
          </div>
        ) : error ? (
          <div className="rounded-3xl border border-rose-200 bg-rose-50/80 p-10 text-center">
            <p className="text-lg font-black text-rose-700">
              {error === 'invalid' ? t(locale, 'edu.guardian.invalid') : error === 'expired' ? t(locale, 'edu.guardian.expired') : t(locale, 'edu.guardian.used')}
            </p>
            <p className="mt-2 text-sm text-rose-500">{t(locale, 'edu.guardian.accessNote')}</p>
          </div>
        ) : payload ? (
          <div className="space-y-6">
            <div className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
              <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-600">
                    <Users className="h-5 w-5" aria-hidden="true" />
                  </div>
                  <div>
                    <p className="text-lg font-black tracking-tight text-slate-950">
                      {[payload.guardian.first_name, payload.guardian.last_name].filter(Boolean).join(' ') || '—'}
                    </p>
                    <p className="text-xs font-bold uppercase tracking-widest text-slate-400">{t(locale, 'edu.guardian.children')}</p>
                  </div>
                </div>
                <span className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-black uppercase tracking-widest text-emerald-700">
                  <ShieldCheck className="h-3 w-3" aria-hidden="true" />
                  RGPD
                </span>
              </div>
              <p className="mt-4 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-400">{t(locale, 'edu.guardian.accessNote')}</p>
            </div>

            {payload.children.length === 0 ? (
              <div className="rounded-3xl border border-white/20 bg-white/70 p-10 text-center shadow-premium backdrop-blur-xl">
                <p className="text-sm font-medium text-slate-500">{t(locale, 'edu.guardian.emptyChildren')}</p>
              </div>
            ) : (
              payload.children.map((child) => (
                <div key={child.id} className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                      <p className="text-lg font-black tracking-tight text-slate-950">{child.display_name}</p>
                      <p className="font-mono text-xs font-bold text-slate-400">
                        {child.student_number} · {child.relationship_code}
                      </p>
                    </div>
                    <StatusBadge status={child.status} />
                  </div>

                  <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    <div className="rounded-2xl border border-slate-100 bg-slate-50/60 p-4">
                      <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">{t(locale, 'edu.guardian.presence')}</p>
                      <div className="mt-2 space-y-1 text-sm">
                        <p className="flex justify-between">
                          <span className="text-slate-500">{t(locale, 'edu.guardian.today')}</span>
                          <span className="font-bold text-slate-800">
                            {child.presence.today_status ? t(locale, `edu.teacher.${child.presence.today_status}`) : '—'}
                          </span>
                        </p>
                        <p className="flex justify-between">
                          <span className="text-slate-500">{t(locale, 'edu.guardian.last30days')}</span>
                          <span className="font-bold text-slate-800">{child.presence.recorded_days} j</span>
                        </p>
                      </div>
                    </div>

                    <div className="rounded-2xl border border-slate-100 bg-slate-50/60 p-4">
                      <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">
                        {t(locale, 'edu.guardian.reportCards')} <span className="normal-case">({t(locale, 'edu.guardian.publishedOnly')})</span>
                      </p>
                      {child.report_cards.length === 0 ? (
                        <p className="mt-2 text-sm text-slate-400">—</p>
                      ) : (
                        <ul className="mt-2 space-y-1 text-sm">
                          {child.report_cards.map((card) => (
                            <li key={card.id} className="flex justify-between">
                              <span className="font-bold text-slate-800">{card.period}</span>
                              <span className="font-mono font-bold text-emerald-600">{card.average != null ? Number(card.average).toFixed(2) : '—'}</span>
                            </li>
                          ))}
                        </ul>
                      )}
                    </div>
                  </div>
                </div>
              ))
            )}
          </div>
        ) : null}
      </div>
    </main>
  );
}
