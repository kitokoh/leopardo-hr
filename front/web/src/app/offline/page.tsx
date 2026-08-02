'use client';
// ============================================================
// Offline Page â€” Shown by service worker when offline
// ============================================================
import { useSyncExternalStore } from 'react';
import { getCopy, getPreferredLocale, type AppLocale } from '@/lib/i18n';

const emptySubscribe = () => () => {};

export default function OfflinePage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const labels = getCopy(locale).offlinePage;

  return (
    <div className="min-h-screen flex flex-col items-center justify-center bg-transparent p-6">
      <div className="text-center max-w-md">
        <div className="text-6xl mb-4">ðŸ“¡</div>
        <h1 className="text-2xl font-bold text-gray-900 mb-2">
          {labels.title}
        </h1>
        <p className="text-gray-500 mb-6">
          {labels.body}
        </p>
        <div className="bg-orange-50 border border-orange-200 rounded-lg p-4 text-left text-sm mb-6">
          <p className="font-medium text-orange-800 mb-2">ðŸ’¡ {labels.edgeModeTitle}</p>
          <p className="text-orange-700">
            {labels.edgeModeBody}{' '}
            <a href="http://leopardo.local" className="underline font-mono">
              http://leopardo.local
            </a>
          </p>
        </div>
        <button
          onClick={() => window.location.reload()}
          className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm"
        >
          {labels.retry}
        </button>
      </div>
    </div>
  );
}

