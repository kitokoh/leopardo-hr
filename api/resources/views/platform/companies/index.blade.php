@extends('platform.layouts.app')

@section('title', 'Societes')

@php
    $moduleLabels = [
        'rh' => 'RH',
        'finance' => 'Finance',
        'cameras' => 'Cameras',
        'muhasebe' => 'Muhasebe',
        'leo_ai' => 'Leo IA',
    ];
@endphp

@section('content')
    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Societes clientes</h1>
            <p class="mt-1 text-sm text-slate-400">Creer une societe, activer / desactiver ses modules, renvoyer une invitation manager.</p>
        </div>
        <a href="{{ route('platform.companies.create') }}" class="rounded-lg bg-emerald-500 px-4 py-3 font-medium text-slate-950">Nouvelle societe</a>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <table class="min-w-full divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-950/60 text-left text-slate-400">
                <tr>
                    <th class="px-4 py-3">Societe</th>
                    <th class="px-4 py-3">Ville</th>
                    <th class="px-4 py-3">Statut</th>
                    <th class="px-4 py-3">Modules actifs</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($companies as $company)
                    @php
                        $features = $company->features ?? [];
                        $activeModules = [];
                        foreach ($moduleLabels as $key => $label) {
                            $isActive = $key === 'rh'
                                ? (bool) ($features['rh'] ?? true)
                                : (bool) ($features[$key] ?? false);
                            if ($isActive) {
                                $activeModules[] = $label;
                            }
                        }
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $company->name }}</div>
                            <div class="text-xs text-slate-500">{{ $company->email }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $company->city }}</td>
                        <td class="px-4 py-3">
                            @php
                                $statusClass = match ($company->status) {
                                    'active' => 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30',
                                    'suspended' => 'bg-amber-500/10 text-amber-300 border-amber-500/30',
                                    'expired' => 'bg-rose-500/10 text-rose-300 border-rose-500/30',
                                    default => 'bg-slate-500/10 text-slate-300 border-slate-500/30',
                                };
                            @endphp
                            <span class="inline-block rounded-md border px-2 py-0.5 text-xs {{ $statusClass }}">{{ $company->status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @forelse ($activeModules as $label)
                                    <span class="rounded-md bg-slate-800 px-2 py-0.5 text-xs text-slate-200">{{ $label }}</span>
                                @empty
                                    <span class="text-xs text-slate-500">aucun</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('platform.companies.edit', ['company' => $company->id]) }}" class="rounded-md border border-slate-700 px-3 py-1 text-xs hover:bg-slate-800">Editer</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-400">Aucune societe pour le moment.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
