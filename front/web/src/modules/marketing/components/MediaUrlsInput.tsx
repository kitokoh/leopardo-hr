'use client';

import { useState } from 'react';
import { ImagePlus, X } from 'lucide-react';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

type MediaUrlsInputProps = {
  value: string[];
  onChange: (urls: string[]) => void;
  disabled?: boolean;
};

/**
 * Module Marketing — Issue #7755.
 *
 * Saisie des URLs de médias (images/vidéos déjà hébergées) attachés à une
 * publication. Le backend accepte `media_paths` (string[]) depuis la
 * Phase 1 et les transmet à Ayrshare (`mediaUrls`) — ce composant expose
 * enfin cette capacité côté web.
 */
export function MediaUrlsInput({ value, onChange, disabled = false }: MediaUrlsInputProps) {
  const [draft, setDraft] = useState('');
  const locale = getPreferredLocale();

  const isValidUrl = (raw: string) => raw.startsWith('http://') || raw.startsWith('https://');

  const addUrl = () => {
    const url = draft.trim();
    if (!isValidUrl(url) || value.includes(url)) {
      return;
    }
    onChange([...value, url]);
    setDraft('');
  };

  const removeUrl = (url: string) => {
    onChange(value.filter((entry) => entry !== url));
  };

  return (
    <div data-testid="media-urls-input">
      <p className="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'marketing.web.mediaUrls.label')}</p>
      <div className="flex gap-2">
        <input
          type="url"
          data-testid="media-urls-input-field"
          placeholder={t(locale, 'marketing.web.mediaUrls.placeholder')}
          value={draft}
          disabled={disabled}
          onChange={(e) => setDraft(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              addUrl();
            }
          }}
          className="w-full rounded-xl border border-app-border bg-transparent px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
        />
        <button
          type="button"
          data-testid="media-urls-input-add"
          onClick={addUrl}
          disabled={disabled || !isValidUrl(draft.trim())}
          className="inline-flex items-center gap-2 rounded-xl border border-app-border px-3 py-2 text-sm font-bold text-slate-600 transition hover:bg-transparent disabled:opacity-50"
        >
          <ImagePlus className="h-4 w-4" /> {t(locale, 'marketing.web.mediaUrls.add')}
        </button>
      </div>
      {value.length > 0 ? (
        <ul className="mt-2 space-y-1">
          {value.map((url) => (
            <li key={url} className="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-1.5 text-xs text-slate-600">
              <span className="truncate" dir="ltr">{url}</span>
              <button
                type="button"
                onClick={() => removeUrl(url)}
                disabled={disabled}
                aria-label={`${t(locale, 'marketing.web.mediaUrls.remove')} ${url}`}
                className="rounded p-1 text-slate-400 transition hover:text-red-600 disabled:opacity-50"
              >
                <X className="h-3.5 w-3.5" />
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
