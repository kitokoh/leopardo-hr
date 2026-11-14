'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import Link from 'next/link';
import { ArrowLeft, Download, Plus, Trash2 } from 'lucide-react';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { getCandidateToolsCopy } from '@/modules/vitrine/data/candidate-tools';

/**
 * Éditeur de CV du portail carrières (ATS #9461).
 *
 * ## Pourquoi côté navigateur uniquement
 *
 * Un candidat qui n'a pas de compte ne peut pas avoir de CV stocké côté
 * serveur sans qu'on lui demande de s'inscrire — ce qui est exactement le
 * frictions qu'on veut éviter sur un portail public. Le CV est donc construit
 * et conservé dans le navigateur (`localStorage`), et le candidat l'exporte en
 * PDF pour le joindre lui-même à sa candidature.
 *
 * Conséquence assumée, et dite à l'écran : le CV ne suit pas le candidat d'un
 * appareil à l'autre, et il n'est envoyé à aucune entreprise tant qu'il ne
 * joint pas le fichier. On ne prétend pas le contraire.
 *
 * ## Export PDF
 *
 * On utilise l'impression du navigateur (`window.print`) avec une feuille de
 * style `@media print` dédiée, plutôt qu'une bibliothèque de génération PDF :
 * aucune dépendance ajoutée, un rendu identique à l'aperçu, et le candidat
 * peut choisir « Enregistrer au format PDF » dans la boîte d'impression. Une
 * bibliothèque côté client aurait ajouté plusieurs centaines de kilo-octets
 * pour un résultat moins fidèle que l'aperçu lui-même.
 */

const STORAGE_KEY = 'leopardo.candidate.cv.v1';

interface ExperienceEntry {
  id: string;
  role: string;
  company: string;
  period: string;
  description: string;
}

interface EducationEntry {
  id: string;
  degree: string;
  school: string;
  period: string;
}

interface ResumeData {
  fullName: string;
  headline: string;
  email: string;
  phone: string;
  location: string;
  summary: string;
  skills: string;
  experience: ExperienceEntry[];
  education: EducationEntry[];
}

const EMPTY_RESUME: ResumeData = {
  fullName: '',
  headline: '',
  email: '',
  phone: '',
  location: '',
  summary: '',
  skills: '',
  experience: [],
  education: [],
};

function newId(): string {
  return Math.random().toString(36).slice(2, 10);
}

/**
 * Relit le CV enregistré, en tolérant un contenu corrompu.
 *
 * `localStorage` peut contenir n'importe quoi : une version antérieure du
 * schéma, une écriture interrompue, ou le contenu d'un autre site sur la même
 * origine. Un `JSON.parse` non protégé ferait planter la page entière pour un
 * candidat qui revient — on préfère repartir d'un CV vide.
 */
function loadResume(): ResumeData {
  if (typeof window === 'undefined') return EMPTY_RESUME;

  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return EMPTY_RESUME;

    const parsed = JSON.parse(raw) as Partial<ResumeData>;

    return {
      ...EMPTY_RESUME,
      ...parsed,
      experience: Array.isArray(parsed.experience) ? parsed.experience : [],
      education: Array.isArray(parsed.education) ? parsed.education : [],
    };
  } catch {
    return EMPTY_RESUME;
  }
}

export function ResumeBuilder({ companySlug }: { companySlug: string }) {
  const { locale } = useVitrineLocale();
  const copy = getCandidateToolsCopy(locale).cv;

  const [resume, setResume] = useState<ResumeData>(EMPTY_RESUME);
  const [hydrated, setHydrated] = useState(false);
  const [savedAt, setSavedAt] = useState<string | null>(null);
  const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  // L'hydratation se fait après le montage : lire `localStorage` pendant le
  // rendu serveur produirait un HTML différent du HTML client, et React
  // signalerait une erreur d'hydratation.
  useEffect(() => {
    setResume(loadResume());
    setHydrated(true);
  }, []);

  // Sauvegarde différée : écrire à chaque frappe ferait un accès disque par
  // caractère, et `localStorage` est synchrone — sur un CV long, la saisie
  // deviendrait saccadée.
  useEffect(() => {
    if (!hydrated) return;

    if (saveTimer.current) clearTimeout(saveTimer.current);

    saveTimer.current = setTimeout(() => {
      try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(resume));
        setSavedAt(new Date().toISOString());
      } catch {
        // Quota dépassé ou stockage désactivé (navigation privée stricte) :
        // on n'empêche pas la saisie, on cesse simplement de promettre une
        // sauvegarde.
        setSavedAt(null);
      }
    }, 400);

    return () => {
      if (saveTimer.current) clearTimeout(saveTimer.current);
    };
  }, [resume, hydrated]);

  const update = useCallback(<K extends keyof ResumeData>(key: K, value: ResumeData[K]) => {
    setResume((prev) => ({ ...prev, [key]: value }));
  }, []);

  const addExperience = () => {
    setResume((prev) => ({
      ...prev,
      experience: [
        ...prev.experience,
        { id: newId(), role: '', company: '', period: '', description: '' },
      ],
    }));
  };

  const addEducation = () => {
    setResume((prev) => ({
      ...prev,
      education: [...prev.education, { id: newId(), degree: '', school: '', period: '' }],
    }));
  };

  const skillList = useMemo(
    () =>
      resume.skills
        .split(',')
        .map((skill) => skill.trim())
        .filter(Boolean),
    [resume.skills]
  );

  const hasContent = resume.fullName.trim() !== '';

  return (
    <div className="space-y-8">
      <div className="grid gap-8 lg:grid-cols-2">
        {/* ── Formulaire ─────────────────────────────────────────────── */}
        <form className="space-y-6 print:hidden" onSubmit={(e) => e.preventDefault()}>
          <fieldset className="space-y-4">
            <legend className="mb-2 text-sm font-bold uppercase tracking-wider text-slate-800">
              {copy.fullName}
            </legend>

            <Field label={copy.fullName} value={resume.fullName} onChange={(v) => update('fullName', v)} />
            <Field label={copy.headline} value={resume.headline} onChange={(v) => update('headline', v)} />

            <div className="grid gap-4 sm:grid-cols-2">
              <Field label={copy.email} type="email" value={resume.email} onChange={(v) => update('email', v)} />
              <Field label={copy.phone} type="tel" value={resume.phone} onChange={(v) => update('phone', v)} />
            </div>

            <Field label={copy.location} value={resume.location} onChange={(v) => update('location', v)} />
          </fieldset>

          <fieldset>
            <legend className="mb-2 text-sm font-bold uppercase tracking-wider text-slate-800">
              {copy.summary}
            </legend>
            <textarea
              value={resume.summary}
              onChange={(e) => update('summary', e.target.value)}
              rows={4}
              className="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
          </fieldset>

          <fieldset>
            <legend className="mb-2 text-sm font-bold uppercase tracking-wider text-slate-800">
              {copy.skills}
            </legend>
            <input
              value={resume.skills}
              onChange={(e) => update('skills', e.target.value)}
              placeholder={copy.skillsPlaceholder}
              aria-label={copy.skills}
              className="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
            <p className="mt-1 text-xs text-slate-500">{copy.skillsHint}</p>
          </fieldset>

          <fieldset className="space-y-3">
            <legend className="mb-2 text-sm font-bold uppercase tracking-wider text-slate-800">
              {copy.experience}
            </legend>

            {resume.experience.map((entry, index) => (
              <div key={entry.id} className="space-y-3 rounded-xl border border-slate-200 p-4">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-bold text-slate-500">#{index + 1}</span>
                  <button
                    type="button"
                    onClick={() =>
                      update(
                        'experience',
                        resume.experience.filter((item) => item.id !== entry.id)
                      )
                    }
                    className="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 hover:text-rose-700"
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                    {copy.remove}
                  </button>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                  <Field
                    label={copy.role}
                    value={entry.role}
                    onChange={(v) =>
                      update(
                        'experience',
                        resume.experience.map((item) =>
                          item.id === entry.id ? { ...item, role: v } : item
                        )
                      )
                    }
                  />
                  <Field
                    label={copy.company}
                    value={entry.company}
                    onChange={(v) =>
                      update(
                        'experience',
                        resume.experience.map((item) =>
                          item.id === entry.id ? { ...item, company: v } : item
                        )
                      )
                    }
                  />
                </div>

                <Field
                  label={copy.period}
                  value={entry.period}
                  onChange={(v) =>
                    update(
                      'experience',
                      resume.experience.map((item) =>
                        item.id === entry.id ? { ...item, period: v } : item
                      )
                    )
                  }
                />

                <textarea
                  value={entry.description}
                  onChange={(e) =>
                    update(
                      'experience',
                      resume.experience.map((item) =>
                        item.id === entry.id ? { ...item, description: e.target.value } : item
                      )
                    )
                  }
                  rows={3}
                  placeholder={copy.description}
                  aria-label={copy.description}
                  className="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>
            ))}

            <button
              type="button"
              onClick={addExperience}
              className="inline-flex items-center gap-2 rounded-xl border border-dashed border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-emerald-400 hover:text-emerald-700"
            >
              <Plus className="h-4 w-4" />
              {copy.addExperience}
            </button>
          </fieldset>

          <fieldset className="space-y-3">
            <legend className="mb-2 text-sm font-bold uppercase tracking-wider text-slate-800">
              {copy.education}
            </legend>

            {resume.education.map((entry, index) => (
              <div key={entry.id} className="space-y-3 rounded-xl border border-slate-200 p-4">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-bold text-slate-500">#{index + 1}</span>
                  <button
                    type="button"
                    onClick={() =>
                      update(
                        'education',
                        resume.education.filter((item) => item.id !== entry.id)
                      )
                    }
                    className="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 hover:text-rose-700"
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                    {copy.remove}
                  </button>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                  <Field
                    label={copy.degree}
                    value={entry.degree}
                    onChange={(v) =>
                      update(
                        'education',
                        resume.education.map((item) =>
                          item.id === entry.id ? { ...item, degree: v } : item
                        )
                      )
                    }
                  />
                  <Field
                    label={copy.school}
                    value={entry.school}
                    onChange={(v) =>
                      update(
                        'education',
                        resume.education.map((item) =>
                          item.id === entry.id ? { ...item, school: v } : item
                        )
                      )
                    }
                  />
                </div>

                <Field
                  label={copy.period}
                  value={entry.period}
                  onChange={(v) =>
                    update(
                      'education',
                      resume.education.map((item) =>
                        item.id === entry.id ? { ...item, period: v } : item
                      )
                    )
                  }
                />
              </div>
            ))}

            <button
              type="button"
              onClick={addEducation}
              className="inline-flex items-center gap-2 rounded-xl border border-dashed border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-emerald-400 hover:text-emerald-700"
            >
              <Plus className="h-4 w-4" />
              {copy.addEducation}
            </button>
          </fieldset>

          <p className="text-xs text-slate-500">
            {savedAt ? copy.saved : copy.saveHint}
          </p>
        </form>

        {/* ── Aperçu ─────────────────────────────────────────────────── */}
        <div className="lg:sticky lg:top-8 lg:self-start">
          <div className="mb-3 flex items-center justify-between print:hidden">
            <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">
              {copy.preview}
            </h2>
            <button
              type="button"
              onClick={() => window.print()}
              disabled={!hasContent}
              className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
            >
              <Download className="h-4 w-4" />
              {copy.download}
            </button>
          </div>

          <article
            id="resume-print-area"
            className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm print:border-0 print:shadow-none"
          >
            {!hasContent ? (
              <p className="text-sm text-slate-500">{copy.emptyPreview}</p>
            ) : (
              <>
                <header className="border-b border-slate-200 pb-4">
                  <h1 className="text-2xl font-black tracking-tight text-slate-950">
                    {resume.fullName}
                  </h1>
                  {resume.headline && (
                    <p className="mt-1 text-sm font-semibold text-emerald-700">{resume.headline}</p>
                  )}
                  <p className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-600">
                    {resume.email && <span>{resume.email}</span>}
                    {resume.phone && <span>{resume.phone}</span>}
                    {resume.location && <span>{resume.location}</span>}
                  </p>
                </header>

                {resume.summary && (
                  <section className="mt-4">
                    <h2 className="text-xs font-bold uppercase tracking-widest text-slate-500">
                      {copy.summary}
                    </h2>
                    <p className="mt-1 whitespace-pre-line text-sm text-slate-800">
                      {resume.summary}
                    </p>
                  </section>
                )}

                {resume.experience.length > 0 && (
                  <section className="mt-4">
                    <h2 className="text-xs font-bold uppercase tracking-widest text-slate-500">
                      {copy.experience}
                    </h2>
                    <ul className="mt-2 space-y-3">
                      {resume.experience.map((entry) => (
                        <li key={entry.id}>
                          <p className="text-sm font-bold text-slate-900">
                            {entry.role || '—'}
                            {entry.company ? ` · ${entry.company}` : ''}
                          </p>
                          {entry.period && (
                            <p className="text-xs text-slate-500">{entry.period}</p>
                          )}
                          {entry.description && (
                            <p className="mt-1 whitespace-pre-line text-sm text-slate-700">
                              {entry.description}
                            </p>
                          )}
                        </li>
                      ))}
                    </ul>
                  </section>
                )}

                {resume.education.length > 0 && (
                  <section className="mt-4">
                    <h2 className="text-xs font-bold uppercase tracking-widest text-slate-500">
                      {copy.education}
                    </h2>
                    <ul className="mt-2 space-y-2">
                      {resume.education.map((entry) => (
                        <li key={entry.id}>
                          <p className="text-sm font-bold text-slate-900">{entry.degree || '—'}</p>
                          <p className="text-xs text-slate-600">
                            {[entry.school, entry.period].filter(Boolean).join(' · ')}
                          </p>
                        </li>
                      ))}
                    </ul>
                  </section>
                )}

                {skillList.length > 0 && (
                  <section className="mt-4">
                    <h2 className="text-xs font-bold uppercase tracking-widest text-slate-500">
                      {copy.skills}
                    </h2>
                    <ul className="mt-2 flex flex-wrap gap-1.5">
                      {skillList.map((skill) => (
                        <li
                          key={skill}
                          className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                        >
                          {skill}
                        </li>
                      ))}
                    </ul>
                  </section>
                )}
              </>
            )}
          </article>
        </div>
      </div>

      <p className="text-xs text-slate-500 print:hidden">{copy.privacyNote}</p>

      <Link
        href={`/${companySlug}/careers`}
        className="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:text-emerald-800 print:hidden"
      >
        <ArrowLeft className="h-4 w-4" />
        {copy.backToJobs}
      </Link>
    </div>
  );
}

function Field({
  label,
  value,
  onChange,
  type = 'text',
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  type?: string;
}) {
  return (
    <label className="block">
      <span className="mb-1 block text-xs font-semibold text-slate-600">{label}</span>
      <input
        type={type}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
      />
    </label>
  );
}
