@extends('layouts.app')

@section('title', __('attendance.corrections_title'))

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ __('attendance.corrections_title') }}</h1>
                <p class="mt-1 text-sm text-slate-400">{{ __('attendance.corrections_subtitle') }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach (['pending' => __('attendance.correction_filter_pending'), 'applied' => __('attendance.correction_filter_applied'), 'rejected' => __('attendance.correction_filter_rejected'), 'all' => __('attendance.correction_filter_all')] as $value => $label)
                    <a
                        href="{{ route('attendance-corrections.index', ['status' => $value]) }}"
                        class="rounded-md px-3 py-1.5 text-sm font-medium {{ $status === $value ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-200 hover:bg-slate-700' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <div class="space-y-4">
                @forelse ($corrections as $correction)
                    <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="font-medium">
                                    {{ $correction->employee?->first_name }} {{ $correction->employee?->last_name }}
                                    @if ($correction->employee?->matricule)
                                        <span class="text-slate-500">({{ $correction->employee->matricule }})</span>
                                    @endif
                                    <x-attendance-badge :status="$correction->status === 'applied' ? 'accepted' : ($correction->status === 'rejected' ? 'expired' : 'pending')" :label="match($correction->status) { 'applied' => __('attendance.correction_status_applied'), 'rejected' => __('attendance.correction_status_rejected'), default => __('attendance.correction_status_pending') }" />
                                </div>
                                <div class="mt-1 text-sm text-slate-400">{{ $correction->date?->format('Y-m-d') }}</div>
                                <div class="mt-2 text-sm text-slate-300">
                                    {{ __('attendance.correction_requested_check_in') }}: {{ $correction->requested_check_in?->format('H:i') }}
                                    @if ($correction->requested_check_out)
                                        &middot; {{ __('attendance.correction_requested_check_out') }}: {{ $correction->requested_check_out->format('H:i') }}
                                    @endif
                                </div>
                                <div class="mt-1 text-sm text-slate-400">{{ __('attendance.correction_reason_label') }}: {{ $correction->reason }}</div>
                            </div>

                            @if ($correction->status === 'pending')
                                <div class="flex min-w-48 flex-col gap-2">
                                    <form method="POST" action="{{ route('attendance-corrections.approve', $correction) }}">
                                        @csrf
                                        <button type="submit" class="w-full rounded-lg bg-emerald-500 px-4 py-2 text-sm font-medium text-slate-950">
                                            {{ __('attendance.correction_approve') }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('attendance-corrections.reject', $correction) }}">
                                        @csrf
                                        <button type="submit" class="w-full rounded-lg bg-rose-500 px-4 py-2 text-sm font-medium text-white">
                                            {{ __('attendance.correction_reject') }}
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-800 px-4 py-8 text-center text-slate-400">
                        {{ __('attendance.corrections_empty') }}
                    </div>
                @endforelse
            </div>

            <div class="mt-6">
                {{ $corrections->links() }}
            </div>
        </div>
    </div>
@endsection
