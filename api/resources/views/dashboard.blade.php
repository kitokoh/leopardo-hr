@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
    <div class="grid gap-6 lg:grid-cols-[1fr_300px]">
        <div class="flex flex-col gap-6">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ __('dashboard.manager_dashboard') }}</h1>
                    <div class="mt-1 text-sm text-slate-400">{{ __('dashboard.today') }} : {{ $today }}</div>
                </div>
                <div class="flex flex-wrap gap-3">
                    @php($me = auth('web')->user())
                    @if ($me?->hasManagerRole('principal', 'superviseur'))
                        <a href="{{ route('biometrics.index') }}" class="rounded-lg border border-slate-700 px-4 py-3 text-sm font-medium">
                            {{ __('dashboard.biometrics_kiosks') }}
                        </a>
                    @endif
                    @if ($me?->hasManagerRole('principal', 'rh'))
                        <a href="{{ route('hr.invitations.index') }}" class="rounded-lg border border-slate-700 px-4 py-3 text-sm font-medium">
                            {{ __('dashboard.invitations') }}
                        </a>
                        <a href="{{ route('employees.create') }}" class="rounded-lg bg-rh px-4 py-3 text-sm font-semibold text-slate-950 hover:bg-rh-dark">
                            {{ __('dashboard.create_hr_employee') }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <x-stat-card :label="__('dashboard.present_employees')" :value="sprintf('%d / %d', $presentCount, $employeesTotal)" />
                <x-stat-card :label="__('dashboard.estimated_total_day')" :value="number_format((float) $totalEstimated, 2).' '.$currency" />
                <x-stat-card :label="__('dashboard.late_employees')" :value="$lateCount.' '.__('employees.employees')" />
            </div>

            <x-attendance-table :rows="$rows" />

            <div class="mt-4">
                {{ $paginator->links() }}
            </div>
        </div>

        <div class="hidden lg:block">
            <x-leo-sidebar class="sticky top-6 h-[calc(100vh-8rem)]" />
        </div>
    </div>
@endsection
