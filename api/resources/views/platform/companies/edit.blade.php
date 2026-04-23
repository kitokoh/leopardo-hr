@extends('platform.layouts.app')

@section('title', 'Editer ' . $company->name)

@php
    // Libelles + descriptions des modules pour l affichage. Source de verite :
    // Company::KNOWN_MODULES. Tout module ajoute dans la constante doit avoir
    // son entree ici (sinon label = slug brut).
    $moduleCatalog = [
        'rh' => [
            'label' => 'RH (base)',
            'description' => 'Pointage, gestion employes, paie simple, invitations. Toujours actif.',
            'locked' => true,
        ],
        'finance' => [
            'label' => 'Finance',
            'description' => 'Devis, factures, TVA, rapprochement bancaire (Phase 2).',
            'locked' => false,
        ],
        'cameras' => [
            'label' => 'Securite (cameras)',
            'description' => 'Flux video IP, tokens JWT TTL 4h, MediaMTX (Phase 2).',
            'locked' => false,
        ],
        'muhasebe' => [
            'label' => 'Muhasebe (comptabilite TR)',
            'description' => 'Comptabilite aux normes turques (Phase 2).',
            'locked' => false,
        ],
        'leo_ai' => [
            'label' => 'Leo IA',
            'description' => 'Assistant conversationnel (OpenAI / Anthropic, Phase 2).',
            'locked' => false,
        ],
    ];
    $currentFeatures = $company->features ?? [];
@endphp

@section('content')
    <div class="mb-4">
        <a href="{{ route('platform.companies.index') }}" class="text-sm text-slate-400 hover:text-slate-200">&larr; Retour aux societes</a>
    </div>

    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ $company->name }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $company->email }} &middot; {{ $company->city }}, {{ $company->country }} &middot; schema <code>{{ $company->schema_name }}</code></p>
        </div>
        <form method="POST" action="{{ route('platform.companies.resend', ['company' => $company->id]) }}">
            @csrf
            <button type="submit" class="rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm hover:bg-slate-800">Renvoyer l'invitation manager</button>
        </form>
    </div>

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-200">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('platform.companies.update', ['company' => $company->id]) }}" class="mt-6 space-y-8">
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <div class="text-sm font-medium text-slate-200">Modules actifs</div>
            <p class="mt-1 text-xs text-slate-400">Cochez les modules commandes par le client. Les modules decoches sont inaccessibles (API retourne 403, UI masquee cote client).</p>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($known_modules as $module)
                    @php
                        $meta = $moduleCatalog[$module] ?? ['label' => $module, 'description' => '', 'locked' => false];
                        $isChecked = $meta['locked'] || (bool) ($currentFeatures[$module] ?? false);
                    @endphp
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-800 bg-slate-950 p-4 hover:border-slate-700">
                        <input
                            type="checkbox"
                            name="features[{{ $module }}]"
                            value="1"
                            @checked($isChecked)
                            @disabled($meta['locked'])
                            data-testid="feature-{{ $module }}"
                            class="mt-1 h-4 w-4 accent-emerald-500"
                        >
                        <span class="flex-1">
                            <span class="block font-medium text-slate-100">{{ $meta['label'] }}</span>
                            <span class="mt-1 block text-xs text-slate-400">{{ $meta['description'] }}</span>
                            @if ($meta['locked'])
                                <span class="mt-2 inline-block rounded-md bg-emerald-500/10 px-2 py-0.5 text-[11px] text-emerald-300">Toujours actif</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid gap-6 rounded-2xl border border-slate-800 bg-slate-900 p-6 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm text-slate-300">Statut</label>
                <select name="status" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">
                    @foreach (['active' => 'Active', 'suspended' => 'Suspendue', 'expired' => 'Expiree'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $company->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">"Suspendue" ou "Expiree" revoque les tokens existants des employes.</p>
            </div>

            <div>
                <label class="mb-2 block text-sm text-slate-300">Plan</label>
                <select name="plan_id" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected(old('plan_id', $company->plan_id) == $plan->id)>{{ $plan->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="mb-2 block text-sm text-slate-300">Notes internes</label>
                <textarea name="notes" rows="4" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">{{ old('notes', $company->notes) }}</textarea>
                <p class="mt-1 text-xs text-slate-500">Notes visibles uniquement des super-admins (historique contrat, debrief deploiement, etc.).</p>
            </div>
        </div>

        <button type="submit" class="rounded-lg bg-emerald-500 px-5 py-3 font-medium text-slate-950">Enregistrer</button>
    </form>
@endsection
