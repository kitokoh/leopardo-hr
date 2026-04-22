@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="grid gap-6 lg:grid-cols-[1fr_300px]">
        <div class="flex flex-col gap-6">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Dashboard manager</h1>
                    <div class="mt-1 text-sm text-slate-400">Aujourd'hui : {{ $today }}</div>
                </div>
                <div class="flex flex-wrap gap-3">
                    @php($me = auth('web')->user())
                    @if ($me?->hasManagerRole('principal', 'superviseur'))
                        <a href="{{ route('biometrics.index') }}" class="rounded-lg border border-slate-700 px-4 py-3 text-sm font-medium">
                            Biometrie / bornes
                        </a>
                    @endif
                    @if ($me?->hasManagerRole('principal', 'rh'))
                        <a href="{{ route('hr.invitations.index') }}" class="rounded-lg border border-slate-700 px-4 py-3 text-sm font-medium">
                            Invitations
                        </a>
                        <a href="{{ route('employees.create') }}" class="rounded-lg bg-rh px-4 py-3 text-sm font-semibold text-slate-950 hover:bg-rh-dark">
                            Creer RH / employe
                        </a>
                    @endif
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <x-stat-card label="Employes presents" :value="sprintf('%d / %d', $presentCount, $employeesTotal)" />
                <x-stat-card label="Total estime (jour)" :value="number_format((float) $totalEstimated, 2).' '.$currency" />
                <x-stat-card label="En retard" :value="$lateCount.' employes'" />
            </div>

            <x-attendance-table :rows="$rows" />
        </div>

        <div class="hidden lg:block">
            <x-leo-sidebar class="sticky top-6 h-[calc(100vh-8rem)]" />
        </div>
    </div>
@endsection
