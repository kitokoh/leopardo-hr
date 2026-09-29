'use client';

import { Send } from 'lucide-react';

import { Button } from '@/components/ui/Button';
import type { AppLocale } from '@/lib/i18n';
import { ASSISTANT_MESSAGE_MAX_LENGTH } from '../lib/api';
import { ta } from '../lib/i18n';

type ChatComposerProps = {
  value: string;
  locale: AppLocale;
  sending: boolean;
  disabled?: boolean;
  onChange: (value: string) => void;
  onSubmit: () => void;
};

/**
 * BOS-035 (#8224) — zone de saisie du panneau Assistant : Envoyer au clic ou
 * à la touche Entrée (Maj+Entrée = saut de ligne). Bornée à la limite
 * serveur de 2000 caractères.
 */
export function ChatComposer({ value, locale, sending, disabled = false, onChange, onSubmit }: ChatComposerProps) {
  const canSend = value.trim().length > 0 && !sending && !disabled;

  return (
    <div className="border-t border-slate-200 p-3 md:p-4">
      <div className="flex items-end gap-2">
        <textarea
          data-testid="assistant-input"
          aria-label={ta(locale, 'input_placeholder')}
          placeholder={ta(locale, 'input_placeholder')}
          value={value}
          rows={2}
          maxLength={ASSISTANT_MESSAGE_MAX_LENGTH}
          disabled={disabled}
          onChange={(event) => onChange(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
              event.preventDefault();
              if (canSend) {
                onSubmit();
              }
            }
          }}
          className="min-h-11 flex-1 resize-none rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-ia focus:ring-2 focus:ring-ia/30 disabled:cursor-not-allowed disabled:bg-slate-50"
        />
        <Button
          variant="primary"
          size="md"
          data-testid="assistant-send"
          aria-label={ta(locale, 'send')}
          loading={sending}
          disabled={!canSend}
          icon={<Send className="h-4 w-4" aria-hidden="true" />}
          onClick={onSubmit}
          className="bg-gradient-to-r from-ia to-ia-dark hover:opacity-90"
        >
          {sending ? ta(locale, 'sending') : ta(locale, 'send')}
        </Button>
      </div>
    </div>
  );
}
