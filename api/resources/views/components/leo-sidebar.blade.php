@props(['compact' => false])

{{--
    APV Design v3 — Placeholder de la sidebar Leo IA.
    La sidebar conversationnelle ne devient active qu avec le flag `leo_ai`
    (cf APV L.08). Pour l instant on pose la coquille visuelle : le jour ou
    Leo est branche, on remplace ce composant par le vrai chat.
--}}
<aside {{ $attributes->merge(['class' => 'flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900/60 p-4']) }}>
    <div class="flex items-center gap-2">
        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-ia/15 text-ia">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4">
                <path d="M12 2l2.39 5.77L20 8.76l-4 3.89.94 5.47L12 15.77l-4.94 2.35L8 12.65 4 8.76l5.61-.99L12 2z"/>
            </svg>
        </span>
        <div>
            <div class="text-sm font-semibold text-slate-100">Leo</div>
            <div class="text-xs text-slate-400">Assistant IA (bientot disponible)</div>
        </div>
    </div>

    @unless ($compact)
        <div class="flex flex-col gap-2 text-xs text-slate-300">
            <div class="rounded-lg bg-slate-800/60 p-2">Posez une question sur la paie, les pointages, la TVA.</div>
            <div class="rounded-lg bg-slate-800/60 p-2">Ex : "Combien me doit-on ce mois ?"</div>
            <div class="rounded-lg bg-slate-800/60 p-2">Ex : "Liste les retards de cette semaine."</div>
        </div>

        <div class="mt-auto flex items-center gap-2 rounded-full border border-slate-700 bg-slate-800/40 px-3 py-2 text-xs text-slate-500">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19v3m-4-3h8M12 4a4 4 0 00-4 4v4a4 4 0 008 0V8a4 4 0 00-4-4z"/>
            </svg>
            <span>Leo arrive bientot...</span>
        </div>
    @endunless
</aside>
