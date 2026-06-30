'use client';

import { useState } from 'react';

type Props = {
  employeeName: string;
  onConfirm: (reason: string) => void;
  onCancel: () => void;
  loading?: boolean;
};

export function RejectSessionModal({ employeeName, onConfirm, onCancel, loading = false }: Props) {
  const [reason, setReason] = useState('');

  const isValid = reason.trim().length > 0;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
      <div className="w-full max-w-md rounded-2xl border border-app-border bg-white p-6 shadow-xl">
        <h2 className="text-lg font-black text-slate-950">Refuser la session</h2>
        <p className="mt-2 text-sm text-slate-600">
          Vous allez refuser la session de{' '}
          <span className="font-bold text-slate-900">{employeeName}</span>. Veuillez
          indiquer une raison.
        </p>

        <div className="mt-4">
          <label className="block text-xs font-bold uppercase tracking-wider text-slate-500">
            Raison du refus <span className="text-red-500">*</span>
          </label>
          <textarea
            className="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-200 disabled:opacity-50"
            rows={3}
            placeholder="Raison obligatoire…"
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            disabled={loading}
          />
          {!isValid && reason.length > 0 ? (
            <p className="mt-1 text-xs text-red-500">La raison est obligatoire.</p>
          ) : null}
        </div>

        <div className="mt-5 flex gap-3 justify-end">
          <button
            type="button"
            onClick={onCancel}
            disabled={loading}
            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
          >
            Annuler
          </button>
          <button
            type="button"
            onClick={() => { if (isValid) onConfirm(reason.trim()); }}
            disabled={loading || !isValid}
            className="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {loading ? 'En cours…' : 'Refuser'}
          </button>
        </div>
      </div>
    </div>
  );
}
