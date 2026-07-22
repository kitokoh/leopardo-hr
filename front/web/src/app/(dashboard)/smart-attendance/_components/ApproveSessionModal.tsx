'use client';

import { useState } from 'react';

export type ApproveSessionModalLabels = {
  approveModalTitle: string;
  approveModalBody: string;
  approveModalNoteLabel: string;
  approveModalNotePlaceholder: string;
  approveModalConfirm: string;
  approveModalInProgress: string;
  cancel: string;
};

type Props = {
  employeeName: string;
  onConfirm: (note: string) => void;
  onCancel: () => void;
  loading?: boolean;
  labels: ApproveSessionModalLabels;
};

export function ApproveSessionModal({ employeeName, onConfirm, onCancel, loading = false, labels }: Props) {
  const [note, setNote] = useState('');
  const [beforeName, afterName] = labels.approveModalBody.split('{name}');

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
      <div className="w-full max-w-md rounded-2xl border border-app-border bg-white p-6 shadow-xl">
        <h2 className="text-lg font-black text-slate-950">{labels.approveModalTitle}</h2>
        <p className="mt-2 text-sm text-slate-600">
          {beforeName}
          <span className="font-bold text-slate-900">{employeeName}</span>
          {afterName}
        </p>

        <div className="mt-4">
          <label className="block text-xs font-bold uppercase tracking-wider text-slate-500">
            {labels.approveModalNoteLabel}
          </label>
          <textarea
            className="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:opacity-50"
            rows={3}
            placeholder={labels.approveModalNotePlaceholder}
            value={note}
            onChange={(e) => setNote(e.target.value)}
            disabled={loading}
          />
        </div>

        <div className="mt-5 flex gap-3 justify-end">
          <button
            type="button"
            onClick={onCancel}
            disabled={loading}
            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
          >
            {labels.cancel}
          </button>
          <button
            type="button"
            onClick={() => onConfirm(note)}
            disabled={loading}
            className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-500 disabled:opacity-50"
          >
            {loading ? labels.approveModalInProgress : labels.approveModalConfirm}
          </button>
        </div>
      </div>
    </div>
  );
}
