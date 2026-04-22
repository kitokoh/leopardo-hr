@extends('layouts.app')

@section('title', 'Mon espace')

@section('content')
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Bonjour {{ $employee->first_name ?? $employee->email }}</h1>
            <div class="mt-1 text-sm text-slate-400">
                {{ $company?->name }} &middot; {{ $today }}
                @if ($employee->matricule)
                    &middot; Matricule : {{ $employee->matricule }}
                @endif
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                <div class="text-xs uppercase tracking-wider text-slate-400">Statut du jour</div>
                <div class="mt-2">
                    <x-attendance-badge :status="$todayLog?->status ?? 'absent'" />
                </div>
            </div>
            <x-stat-card label="Jours presents (mois)" :value="$presentDays.' j'" />
            <x-stat-card label="Heures travaillees (mois)" :value="number_format($hoursMonth, 2).' h'" />
        </div>

        <div class="rounded-lg border border-slate-800 bg-slate-900/40 p-4">
            <h2 class="text-lg font-semibold">Pointage du jour</h2>
            @if ($todayLog)
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                    <div><dt class="text-slate-400">Check-in</dt><dd>{{ $todayLog->check_in?->setTimezone($company?->timezone ?? 'Africa/Algiers')->format('H:i') ?? '-' }}</dd></div>
                    <div><dt class="text-slate-400">Check-out</dt><dd>{{ $todayLog->check_out?->setTimezone($company?->timezone ?? 'Africa/Algiers')->format('H:i') ?? '-' }}</dd></div>
                    <div><dt class="text-slate-400">Heures</dt><dd>{{ number_format((float) ($todayLog->hours_worked ?? 0), 2) }}</dd></div>
                    <div><dt class="text-slate-400">Methode</dt><dd>{{ $todayLog->method }}</dd></div>
                </dl>
            @else
                <p class="mt-2 text-sm text-slate-400">Aucun pointage enregistre aujourd'hui. Ouvrez l'app mobile Leopardo RH pour pointer.</p>
            @endif
        </div>

        <div class="rounded-lg border border-slate-800 bg-slate-900/40 p-4">
            <h2 class="text-lg font-semibold">Historique du mois</h2>
            @if ($monthLogs->isEmpty())
                <div class="mt-3">
                    <x-empty-state
                        title="Aucun pointage ce mois-ci"
                        description="Vos pointages du mois apparaitront ici automatiquement." />
                </div>
            @else
                <table class="mt-3 min-w-full text-sm">
                    <thead class="text-left text-slate-400">
                        <tr>
                            <th class="py-2 pr-4">Date</th>
                            <th class="py-2 pr-4">Statut</th>
                            <th class="py-2 pr-4">Check-in</th>
                            <th class="py-2 pr-4">Check-out</th>
                            <th class="py-2 pr-4">Heures</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($monthLogs as $log)
                            <tr>
                                <td class="py-2 pr-4">{{ $log->date }}</td>
                                <td class="py-2 pr-4"><x-attendance-badge :status="$log->status" /></td>
                                <td class="py-2 pr-4">{{ $log->check_in?->setTimezone($company?->timezone ?? 'Africa/Algiers')->format('H:i') ?? '-' }}</td>
                                <td class="py-2 pr-4">{{ $log->check_out?->setTimezone($company?->timezone ?? 'Africa/Algiers')->format('H:i') ?? '-' }}</td>
                                <td class="py-2 pr-4">{{ number_format((float) ($log->hours_worked ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
