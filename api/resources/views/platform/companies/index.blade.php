@extends('platform.layouts.app')

@section('title', 'Societes')

@section('content')
    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Societes clientes</h1>
            <p class="mt-1 text-sm text-slate-400">Creer une societe, son manager principal, puis laisser le manager onboarder RH et employes.</p>
        </div>
        <a href="{{ route('platform.companies.create') }}" class="rounded-lg bg-emerald-500 px-4 py-3 font-medium text-slate-950">Nouvelle societe</a>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <table class="min-w-full divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-950/60 text-left text-slate-400">
                <tr>
                    <th class="px-4 py-3">Societe</th>
                    <th class="px-4 py-3">Ville</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Statut</th>
                    <th class="px-4 py-3">Schema</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($companies as $company)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $company->name }}</td>
                        <td class="px-4 py-3">{{ $company->city }}</td>
                        <td class="px-4 py-3">{{ $company->email }}</td>
                        <td class="px-4 py-3">{{ $company->status }}</td>
                        <td class="px-4 py-3">{{ $company->schema_name }}</td>
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
