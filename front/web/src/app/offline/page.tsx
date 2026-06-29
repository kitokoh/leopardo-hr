// ============================================================
// Offline Page — Shown by service worker when offline
// ============================================================
export default function OfflinePage() {
  return (
    <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50 p-6">
      <div className="text-center max-w-md">
        <div className="text-6xl mb-4">📡</div>
        <h1 className="text-2xl font-bold text-gray-900 mb-2">
          Pas de connexion Internet
        </h1>
        <p className="text-gray-500 mb-6">
          Vous êtes actuellement hors ligne. Si un Edge node Leopardo est disponible
          sur votre réseau local, l&apos;application continue de fonctionner normalement.
        </p>
        <div className="bg-orange-50 border border-orange-200 rounded-lg p-4 text-left text-sm mb-6">
          <p className="font-medium text-orange-800 mb-2">💡 Mode Edge actif ?</p>
          <p className="text-orange-700">
            Accédez à l&apos;interface locale via :{' '}
            <a href="http://leopardo.local" className="underline font-mono">
              http://leopardo.local
            </a>
          </p>
        </div>
        <button
          onClick={() => window.location.reload()}
          className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm"
        >
          Réessayer
        </button>
      </div>
    </div>
  );
}
