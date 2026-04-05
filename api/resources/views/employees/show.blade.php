@extends('layouts.app')

@section('title', 'Employe')

@section('content')
    <div
        x-data="{
            from: '{{ $defaultFrom }}',
            to: '{{ $defaultTo }}',
            loading: false,
            result: null,
            async run() {
                this.loading = true;
                this.result = null;
                try {
                    const url = new URL('{{ route('employees.quickEstimate', $employee) }}', window.location.origin);
                    url.searchParams.set('from', this.from);
                    url.searchParams.set('to', this.to);
                    const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                    const json = await res.json();
                    this.result = json.data;
                } finally {
                    this.loading = false;
                }
            },
            receiptUrl() {
                const url = new URL('{{ route('employees.receipt', $employee) }}', window.location.origin);
                url.searchParams.set('from', this.from);
                url.searchParams.set('to', this.to);
                return url.toString();
            }
        }"
        class="space-y-6"
    >
        <div class="flex items-center justify-between gap-4">
            <div class="space-y-1">
                <a href="{{ route('dashboard') }}" class="text-sm text-slate-400 hover:text-slate-200"><- Retour</a>
                <h1 class="text-2xl font-semibold tracking-tight">{{ $employee->first_name }} {{ $employee->last_name }}</h1>
                <div class="text-sm text-slate-400">{{ $employee->email }}</div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <x-stat-card label="Role" :value="$employee->role" />
            <x-stat-card label="Statut" :value="$employee->status" />
            <x-stat-card label="Type salaire" :value="$employee->salary_type ?? '-'" />
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4 shadow">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <div class="text-sm font-semibold">Quick estimate</div>
                    <div class="text-xs text-slate-500">Periode (jours ouvres) + estimation net/gross.</div>
                </div>

                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="text-xs text-slate-400">Debut</label>
                        <input x-model="from" type="date" class="mt-1 rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-400">Fin</label>
                        <input x-model="to" type="date" class="mt-1 rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30" />
                    </div>
                    <button @click="run()" type="button" class="rounded-md bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400" :disabled="loading">
                        <span x-show="!loading">Calculer</span>
                        <span x-show="loading">...</span>
                    </button>
                    <a :href="receiptUrl()" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-700">
                        Telecharger PDF
                    </a>
                </div>
            </div>

            <div class="mt-4" x-show="result">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4 shadow">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Jours presents</div>
                        <div class="mt-2 text-2xl font-semibold" x-text="result?.period?.days_present ?? 0"></div>
                    </div>
                    <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4 shadow">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Brut</div>
                        <div class="mt-2 text-2xl font-semibold" x-text="`${(result?.totals?.gross ?? 0).toFixed(2)} ${result?.currency ?? ''}`"></div>
                    </div>
                    <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4 shadow">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Net</div>
                        <div class="mt-2 text-2xl font-semibold" x-text="`${(result?.totals?.net ?? 0).toFixed(2)} ${result?.currency ?? ''}`"></div>
                    </div>
                </div>
                <div class="mt-2 text-xs text-slate-500" x-text="result?.disclaimer"></div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4 shadow">
            <div class="text-sm font-semibold">Historique (30 derniers logs)</div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Arrivee</th>
                            <th class="px-3 py-2 text-left">Depart</th>
                            <th class="px-3 py-2 text-left">Heures</th>
                            <th class="px-3 py-2 text-left">Du</th>
                            <th class="px-3 py-2 text-left">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($history as $row)
                            @php
                                $status = $row['status'] ?? 'absent';
                                $statusClass = match ($status) {
                                    'ontime' => 'text-emerald-300',
                                    'late' => 'text-orange-300',
                                    default => 'text-rose-300',
                                };
                            @endphp
                            <tr class="hover:bg-slate-900/40">
                                <td class="px-3 py-2">{{ $row['date'] }}</td>
                                <td class="px-3 py-2">{{ $row['check_in'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['check_out'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ number_format((float) ($row['hours_worked'] ?? 0), 2) }}</td>
                                <td class="px-3 py-2">{{ number_format((float) ($row['total_estimated'] ?? 0), 2) }} {{ $row['currency'] ?? '' }}</td>
                                <td class="px-3 py-2"><span class="{{ $statusClass }}">{{ $status }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
