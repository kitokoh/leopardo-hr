'use client';

import { useEffect, useRef } from 'react';
import { Sparkles, Wrench } from 'lucide-react';

import type { AppLocale } from '@/lib/i18n';
import { ta } from '../lib/i18n';
import type { AssistantMessage, AssistantPendingItem } from '../lib/storage';
import { ConfirmationCard } from './ConfirmationCard';

type ChatThreadProps = {
  messages: AssistantMessage[];
  locale: AppLocale;
  sending: boolean;
  onConfirm: (messageIndex: number, item: AssistantPendingItem) => Promise<void>;
  onReject: (messageIndex: number, item: AssistantPendingItem) => Promise<void>;
};

/**
 * BOS-035 (#8224) — fil de conversation : bulles utilisateur/assistant,
 * badges `tools_used` sous chaque réponse de l'assistant et cartes de
 * confirmation des actions en attente. Défilement automatique en bas à
 * chaque nouveau message.
 */
export function ChatThread({ messages, locale, sending, onConfirm, onReject }: ChatThreadProps) {
  const bottomRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
  }, [messages.length, sending]);

  if (messages.length === 0) {
    return (
      <div
        data-testid="assistant-thread-empty"
        className="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center"
      >
        <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-ia to-ia-dark shadow-lg shadow-ia/30">
          <Sparkles className="h-7 w-7 text-white" aria-hidden="true" />
        </span>
        <p className="text-lg font-bold text-slate-950">{ta(locale, 'empty_thread_title')}</p>
        <p className="max-w-md text-sm leading-relaxed text-slate-500">{ta(locale, 'empty_thread_body')}</p>
      </div>
    );
  }

  return (
    <div
      data-testid="assistant-thread"
      aria-live="polite"
      className="flex-1 space-y-4 overflow-y-auto p-4 md:p-5"
    >
      {messages.map((message, index) => {
        const key = `${message.timestamp}-${index}`;
        if (message.role === 'user') {
          return (
            <div key={key} className="flex justify-end">
              <div className="max-w-[85%] md:max-w-[75%]">
                <p className="mb-1 text-end text-xs font-bold text-slate-400">{ta(locale, 'you')}</p>
                <div
                  data-testid="assistant-user-bubble"
                  className="whitespace-pre-wrap break-words rounded-2xl rounded-br-sm bg-slate-900 px-4 py-2.5 text-sm text-white shadow-sm"
                >
                  {message.content}
                </div>
              </div>
            </div>
          );
        }

        return (
          <div key={key} className="flex justify-start">
            <div className="max-w-[90%] md:max-w-[80%]">
              <p className="mb-1 flex items-center gap-1.5 text-xs font-bold text-ia">
                <Sparkles className="h-3.5 w-3.5" aria-hidden="true" />
                {ta(locale, 'leo_name')}
              </p>
              <div
                data-testid="assistant-assistant-bubble"
                className="whitespace-pre-wrap break-words rounded-2xl rounded-bl-sm border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm"
              >
                {message.content}
              </div>
              {message.tools_used && message.tools_used.length > 0 ? (
                <div data-testid="assistant-tools-used" className="mt-2 flex flex-wrap items-center gap-1.5">
                  <span className="text-xs font-bold uppercase tracking-wider text-slate-400">
                    {ta(locale, 'tools_used')}
                  </span>
                  {message.tools_used.map((tool) => (
                    <span
                      key={tool}
                      className="inline-flex items-center gap-1 rounded-full bg-ia-light px-2.5 py-0.5 text-xs font-bold text-ia-dark"
                    >
                      <Wrench className="h-3 w-3" aria-hidden="true" />
                      {tool}
                    </span>
                  ))}
                </div>
              ) : null}
              {message.pending?.map((item) => (
                <ConfirmationCard
                  key={item.pending_action_id}
                  item={item}
                  locale={locale}
                  onConfirm={(pending) => onConfirm(index, pending)}
                  onReject={(pending) => onReject(index, pending)}
                />
              ))}
            </div>
          </div>
        );
      })}

      {sending ? (
        <div className="flex justify-start" data-testid="assistant-typing">
          <div className="flex items-center gap-2 rounded-2xl rounded-bl-sm border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <span className="h-2 w-2 animate-bounce rounded-full bg-ia [animation-delay:-0.3s]" />
            <span className="h-2 w-2 animate-bounce rounded-full bg-ia [animation-delay:-0.15s]" />
            <span className="h-2 w-2 animate-bounce rounded-full bg-ia" />
          </div>
        </div>
      ) : null}

      <div ref={bottomRef} />
    </div>
  );
}
