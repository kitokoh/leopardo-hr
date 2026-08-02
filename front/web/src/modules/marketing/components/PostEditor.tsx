'use client';

import { useState } from 'react';
import { Plus, X } from 'lucide-react';
import { SUPPORTED_PLATFORMS } from '@/modules/marketing/types';

export type PostEditorSubmitPayload = {
  content: string;
  targetPlatforms: string[];
  scheduledAt: string | null;
};

type PostEditorProps = {
  /** Pre-filled date (yyyy-MM-ddTHH:mm, `datetime-local` format), e.g. when
   *  the editor is opened from a specific calendar day. */
  initialScheduledAt?: string | null;
  submitting?: boolean;
  submitLabel?: string;
  onSubmit: (payload: PostEditorSubmitPayload) => void | Promise<void>;
  onCancel?: () => void;
};

/**
 * Module Marketing â€” Phase 4 (PA2-MKT-011).
 *
 * Standalone post composer: content + target platforms + optional
 * scheduling date. Used by the `/social` calendar page (new post from a
 * given day, or "new post" from the toolbar) so the compose form isn't
 * duplicated inline in the page component.
 */
export function PostEditor({
  initialScheduledAt = null,
  submitting = false,
  submitLabel,
  onSubmit,
  onCancel,
}: PostEditorProps) {
  const [content, setContent] = useState('');
  const [selectedPlatforms, setSelectedPlatforms] = useState<string[]>([]);
  const [scheduledAt, setScheduledAt] = useState(initialScheduledAt ?? '');

  const togglePlatform = (value: string) => {
    setSelectedPlatforms((prev) => (
      prev.includes(value) ? prev.filter((p) => p !== value) : [...prev, value]
    ));
  };

  const canSubmit = content.trim().length > 0 && selectedPlatforms.length > 0 && !submitting;

  const handleSubmit = async () => {
    if (!canSubmit) {
      return;
    }

    await onSubmit({
      content: content.trim(),
      targetPlatforms: selectedPlatforms,
      scheduledAt: scheduledAt ? new Date(scheduledAt).toISOString() : null,
    });

    setContent('');
    setSelectedPlatforms([]);
    setScheduledAt('');
  };

  return (
    <div data-testid="post-editor" className="space-y-4">
      <textarea
        data-testid="post-editor-content"
        placeholder="Contenu de la publication..."
        value={content}
        onChange={(e) => setContent(e.target.value)}
        rows={4}
        maxLength={5000}
        className="w-full rounded-xl border border-app-border bg-transparent px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
      />

      <div>
        <p className="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">Plateformes cibles</p>
        <div className="flex flex-wrap gap-2">
          {SUPPORTED_PLATFORMS.map((platform) => {
            const active = selectedPlatforms.includes(platform.value);
            return (
              <button
                key={platform.value}
                type="button"
                data-testid={`post-editor-platform-${platform.value}`}
                onClick={() => togglePlatform(platform.value)}
                className={`rounded-full border px-3 py-1.5 text-xs font-bold transition ${
                  active
                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                    : 'border-app-border text-slate-600 hover:bg-transparent'
                }`}
              >
                {platform.label}
              </button>
            );
          })}
        </div>
      </div>

      <div className="flex flex-col gap-3 md:flex-row md:items-end">
        <div className="flex-1">
          <label htmlFor="post-editor-scheduled-at" className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">
            Planifier (optionnel)
          </label>
          <input
            id="post-editor-scheduled-at"
            data-testid="post-editor-scheduled-at"
            type="datetime-local"
            value={scheduledAt}
            onChange={(e) => setScheduledAt(e.target.value)}
            className="w-full rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
        </div>

        <div className="flex items-center gap-2">
          {onCancel ? (
            <button
              type="button"
              onClick={onCancel}
              disabled={submitting}
              className="inline-flex items-center justify-center gap-2 rounded-xl border border-app-border px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-transparent disabled:opacity-50"
            >
              <X className="h-4 w-4" /> Annuler
            </button>
          ) : null}
          <button
            type="button"
            data-testid="post-editor-submit"
            onClick={() => void handleSubmit()}
            disabled={!canSubmit}
            className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
          >
            <Plus className="h-4 w-4" />
            {submitting ? 'Creation...' : submitLabel ?? (scheduledAt ? 'Planifier' : 'Enregistrer en brouillon')}
          </button>
        </div>
      </div>
    </div>
  );
}

