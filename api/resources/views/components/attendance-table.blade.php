@props([
    'rows',
])

<div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-950/50 shadow">
    <table class="min-w-full divide-y divide-slate-800 text-sm">
        <thead class="bg-slate-950/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
                <th class="px-4 py-3 text-left">Nom</th>
                <th class="px-4 py-3 text-left">Arrivée</th>
                <th class="px-4 py-3 text-left">Départ</th>
                <th class="px-4 py-3 text-left">Heures</th>
                <th class="px-4 py-3 text-left">Dû</th>
                <th class="px-4 py-3 text-right"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800">
            @foreach ($rows as $row)
                @php
                    $status = $row['attendance_status'] ?? 'absent';
                    $badgeClasses = match ($status) {
                        'ontime' => 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/20',
                        'late' => 'bg-orange-500/15 text-orange-300 ring-orange-500/20',
                        default => 'bg-rose-500/15 text-rose-300 ring-rose-500/20',
                    };
                @endphp

                <tr class="hover:bg-slate-900/40">
                    <td class="px-4 py-3 font-medium">
                        <div class="flex items-center gap-2">
                            <span>{{ $row['employee']->first_name }} {{ $row['employee']->last_name }}</span>
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs ring-1 {{ $badgeClasses }}">
                                {{ $status }}
                            </span>
                        </div>
                        <div class="text-xs text-slate-500">{{ $row['employee']->email }}</div>
                    </td>
                    <td class="px-4 py-3 text-slate-200">{{ $row['check_in'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-200">{{ $row['check_out'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-200">{{ is_numeric($row['hours']) ? number_format((float) $row['hours'], 2) : $row['hours'] }}</td>
                    <td class="px-4 py-3 text-slate-200">{{ number_format((float) ($row['due'] ?? 0), 2) }} {{ $row['currency'] ?? '' }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('employees.show', $row['employee']) }}" class="text-emerald-400 hover:text-emerald-300">
                            Voir détail →
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
