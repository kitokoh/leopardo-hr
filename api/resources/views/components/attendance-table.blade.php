@props([
    'rows',
])

<div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-950/50 shadow">
    <table class="min-w-full divide-y divide-slate-800 text-sm">
        <thead class="bg-slate-950/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
                <th class="px-4 py-3 text-left">{{ __('dashboard.column_name') }}</th>
                <th class="px-4 py-3 text-left">{{ __('dashboard.column_arrival') }}</th>
                <th class="px-4 py-3 text-left">{{ __('dashboard.column_departure') }}</th>
                <th class="px-4 py-3 text-left">{{ __('dashboard.column_hours') }}</th>
                <th class="px-4 py-3 text-left">{{ __('dashboard.column_due') }}</th>
                <th class="px-4 py-3 text-right"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800">
            @foreach ($rows as $row)
                @php
                    $status = $row['attendance_status'] ?? 'absent';
                @endphp

                <tr class="hover:bg-slate-900/40">
                    <td class="px-4 py-3 font-medium">
                        <div class="flex items-center gap-2">
                            <span>{{ $row['employee']->first_name }} {{ $row['employee']->last_name }}</span>
                            <x-attendance-badge :status="$status" />
                        </div>
                        <div class="text-xs text-slate-500">{{ $row['employee']->email }}</div>
                    </td>
                    <td class="px-4 py-3 text-slate-200">{{ $row['check_in'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-200">{{ $row['check_out'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-200">{{ is_numeric($row['hours']) ? number_format((float) $row['hours'], 2) : $row['hours'] }}</td>
                    <td class="px-4 py-3 text-slate-200">{{ number_format((float) ($row['due'] ?? 0), 2) }} {{ $row['currency'] ?? '' }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('employees.show', $row['employee']) }}" class="text-emerald-400 hover:text-emerald-300">
                            {{ __('dashboard.column_details') }}
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
