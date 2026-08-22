'use client';

import { useState } from 'react';
import { BriefcaseBusiness, Check, GraduationCap, Search, Store } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import type { StoredAuthUser } from '@/lib/i18n';

type PersonalStatus = 'student' | 'employee' | 'entrepreneur' | 'job_seeker';

const OPTIONS: Array<{
  key: PersonalStatus;
  title: string;
  description: string;
  Icon: typeof GraduationCap;
}> = [
  { key: 'student', title: 'Je suis étudiant(e)', description: 'Organiser mes diplômes, documents et mon CV.', Icon: GraduationCap },
  { key: 'employee', title: 'Je travaille', description: 'Rejoindre une entreprise et conserver mon espace professionnel.', Icon: BriefcaseBusiness },
  { key: 'entrepreneur', title: 'Je dirige une entreprise', description: 'Créer ou administrer un espace entreprise.', Icon: Store },
  { key: 'job_seeker', title: 'Je recherche un emploi', description: 'Préparer mon profil pour les opportunités à venir.', Icon: Search },
];

export function PersonalOnboardingWizard({
  user,
  onComplete,
}: {
  user: StoredAuthUser;
  onComplete: () => void;
}) {
  const [selected, setSelected] = useState<PersonalStatus[]>(
    (user.personal_statuses ?? []).filter((status): status is PersonalStatus =>
      OPTIONS.some((option) => option.key === status),
    ),
  );
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const toggle = (status: PersonalStatus) => {
    setSelected((current) => current.includes(status)
      ? current.filter((item) => item !== status)
      : [...current, status]);
  };

  const save = async () => {
    if (selected.length === 0) {
      setError('Sélectionnez au moins une situation.');
      return;
    }
    setSaving(true);
    setError(null);
    try {
      const response = await apiFetch('/user/personal-onboarding', {
        method: 'PUT',
        body: JSON.stringify({ statuses: selected }),
        headers: { 'Content-Type': 'application/json' },
      });
      if (!response.ok) throw new Error('Impossible d’enregistrer votre profil.');
      onComplete();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Une erreur est survenue.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm">
      <section className="w-full max-w-xl rounded-3xl bg-white p-7 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="personal-onboarding-title">
        <p className="text-xs font-black uppercase tracking-[0.18em] text-emerald-600">Votre espace personnel</p>
        <h2 id="personal-onboarding-title" className="mt-2 text-2xl font-black text-slate-950">Comment souhaitez-vous utiliser Leopardo ?</h2>
        <p className="mt-2 text-sm leading-6 text-slate-600">Vous pouvez sélectionner plusieurs réponses et modifier ces choix plus tard dans vos paramètres.</p>

        <div className="mt-6 grid gap-3 sm:grid-cols-2">
          {OPTIONS.map(({ key, title, description, Icon }) => {
            const active = selected.includes(key);
            return (
              <button
                key={key}
                type="button"
                onClick={() => toggle(key)}
                className={`flex min-h-32 flex-col items-start rounded-2xl border p-4 text-left transition ${active ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-200' : 'border-slate-200 bg-white hover:border-emerald-300'}`}
                aria-pressed={active}
              >
                <span className={`flex h-10 w-10 items-center justify-center rounded-xl ${active ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'}`}>
                  <Icon className="h-5 w-5" aria-hidden="true" />
                </span>
                <span className="mt-3 flex w-full items-center justify-between gap-2 font-bold text-slate-900">
                  {title}
                  {active && <Check className="h-4 w-4 shrink-0 text-emerald-600" aria-hidden="true" />}
                </span>
                <span className="mt-1 text-xs leading-5 text-slate-500">{description}</span>
              </button>
            );
          })}
        </div>

        <p className="mt-5 rounded-2xl bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-900">Le pointage et les fonctions d’entreprise seront disponibles uniquement après acceptation de votre rattachement par une entreprise.</p>
        {error && <p className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700" role="alert">{error}</p>}
        <button type="button" onClick={() => void save()} disabled={saving} className="mt-6 w-full rounded-xl bg-slate-950 px-5 py-3 font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
          {saving ? 'Enregistrement…' : 'Continuer'}
        </button>
      </section>
    </div>
  );
}
