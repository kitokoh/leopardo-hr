<template>
  <div class="space-y-6">
    <div class="bg-white shadow rounded-lg p-6">
      <h1 class="text-2xl font-bold text-gray-900">Logs</h1>
      <p class="mt-1 text-sm text-gray-500">
        Evenements systeme recents et traces operationnelles.
      </p>
    </div>

    <div class="overflow-hidden bg-slate-950 shadow rounded-lg">
      <div class="border-b border-slate-800 px-4 py-3 text-sm font-medium text-slate-200">
        Flux systeme
      </div>
      <pre class="max-h-[480px] overflow-auto p-4 text-xs leading-6 text-emerald-300">[info] Admin dashboard initialise
[info] Connexion temps reel en attente
[warn] Aucune source de logs distante configuree</pre>
    </div>
  </div>
</template>
