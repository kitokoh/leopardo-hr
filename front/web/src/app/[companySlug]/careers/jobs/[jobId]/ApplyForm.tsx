'use client';

import { useRef, useState } from 'react';
import { CheckCircle, AlertCircle, Upload, FileText } from 'lucide-react';
import { Input } from '@/modules/vitrine/components/common/Input';
import { Textarea } from '@/modules/vitrine/components/common/Textarea';
import { Button } from '@/modules/vitrine/components/common/Button';
import { submitPublicApplication } from '@/lib/careers-api';

interface ApplyFormProps {
  companySlug: string;
  jobId: number;
}

const MAX_RESUME_BYTES = 5 * 1024 * 1024; // 5 MB, matches the backend `resume` upload rule.

export function ApplyForm({ companySlug, jobId }: ApplyFormProps) {
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [coverLetter, setCoverLetter] = useState('');
  const [resume, setResume] = useState<File | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [status, setStatus] = useState<'idle' | 'submitting' | 'success' | 'error'>('idle');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0] ?? null;

    if (file && file.size > MAX_RESUME_BYTES) {
      setFieldErrors((prev) => ({ ...prev, resume: ['Le fichier ne doit pas depasser 5 Mo.'] }));
      setResume(null);
      if (fileInputRef.current) fileInputRef.current.value = '';
      return;
    }

    setFieldErrors((prev) => {
      const next = { ...prev };
      delete next.resume;
      return next;
    });
    setResume(file);
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setStatus('submitting');
    setErrorMessage(null);
    setFieldErrors({});

    const result = await submitPublicApplication(companySlug, jobId, {
      first_name: firstName,
      last_name: lastName,
      email,
      phone: phone || undefined,
      cover_letter: coverLetter || undefined,
      source: 'website',
      resume,
    });

    if (result.success) {
      setStatus('success');
      setFirstName('');
      setLastName('');
      setEmail('');
      setPhone('');
      setCoverLetter('');
      setResume(null);
      if (fileInputRef.current) fileInputRef.current.value = '';
      return;
    }

    setStatus('error');
    if (result.errors) {
      setFieldErrors(result.errors);
    }
    setErrorMessage(result.message ?? 'Une erreur est survenue. Merci de reessayer.');
  };

  if (status === 'success') {
    return (
      <div className="p-6 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-2xl flex items-start gap-3">
        <CheckCircle className="w-5 h-5 text-emerald-600 dark:text-emerald-400 flex-shrink-0 mt-0.5" />
        <div>
          <p className="font-semibold text-emerald-900 dark:text-emerald-100">Candidature envoyee !</p>
          <p className="text-sm text-emerald-800 dark:text-emerald-200 mt-1">
            Merci pour votre interet. Notre equipe recrutement va etudier votre profil et vous recontactera rapidement.
          </p>
        </div>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {status === 'error' && errorMessage && (
        <div className="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex items-start gap-3">
          <AlertCircle className="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
          <p className="text-sm font-semibold text-red-900 dark:text-red-100">{errorMessage}</p>
        </div>
      )}

      <div className="grid sm:grid-cols-2 gap-4">
        <Input
          label="Prenom"
          required
          value={firstName}
          onChange={(e) => setFirstName(e.target.value)}
          error={fieldErrors.first_name?.[0]}
        />
        <Input
          label="Nom"
          required
          value={lastName}
          onChange={(e) => setLastName(e.target.value)}
          error={fieldErrors.last_name?.[0]}
        />
      </div>

      <Input
        label="Email"
        type="email"
        required
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        error={fieldErrors.email?.[0]}
      />

      <Input
        label="Telephone (optionnel)"
        type="tel"
        value={phone}
        onChange={(e) => setPhone(e.target.value)}
        error={fieldErrors.phone?.[0]}
      />

      <Textarea
        label="Lettre de motivation (optionnel)"
        value={coverLetter}
        onChange={(e) => setCoverLetter(e.target.value)}
        error={fieldErrors.cover_letter?.[0]}
      />

      <div>
        <label className="block text-sm font-semibold text-slate-900 dark:text-white mb-2">
          CV (PDF, optionnel)
        </label>
        <label className="flex items-center gap-3 px-4 py-3 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 cursor-pointer hover:border-emerald-500/50 transition-colors">
          {resume ? (
            <FileText className="w-4 h-4 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
          ) : (
            <Upload className="w-4 h-4 text-slate-400 flex-shrink-0" />
          )}
          <span className="text-sm text-slate-600 dark:text-slate-400 truncate">
            {resume ? resume.name : 'Choisir un fichier (max 5 Mo)'}
          </span>
          <input
            ref={fileInputRef}
            type="file"
            accept=".pdf,.doc,.docx"
            className="sr-only"
            onChange={handleFileChange}
          />
        </label>
        {fieldErrors.resume?.[0] && (
          <p className="mt-1.5 text-xs text-red-600 dark:text-red-400">{fieldErrors.resume[0]}</p>
        )}
      </div>

      <Button type="submit" variant="primary" size="lg" fullWidth loading={status === 'submitting'} disabled={status === 'submitting'}>
        {status === 'submitting' ? 'Envoi en cours...' : 'Envoyer ma candidature'}
      </Button>
    </form>
  );
}
