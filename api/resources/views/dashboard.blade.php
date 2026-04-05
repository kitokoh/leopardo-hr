@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Dashboard manager</h1>
                <div class="mt-1 text-sm text-slate-400">Aujourd’hui : {{ $today }}</div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <x-stat-card label="Employés présents" :value="sprintf('%d / %d', $presentCount, $employeesTotal)" />
            <x-stat-card label="Total estimé (jour)" :value="number_format((float) $totalEstimated, 2).' '.$currency" />
            <x-stat-card label="En retard" :value="$lateCount.' employés'" />
        </div>

        <x-attendance-table :rows="$rows" />
    </div>
@endsection
