@props(['level' => 'info', 'title' => null])

@php
    $classes = match ($level) {
        'success' => 'bg-success/10 text-success border-success/30',
        'warning' => 'bg-warning/10 text-warning border-warning/30',
        'danger' => 'bg-danger/10 text-danger border-danger/30',
        default => 'bg-info/10 text-info border-info/30',
    };
@endphp

<div {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-lg border px-4 py-3 text-sm {$classes}"]) }}>
    <div class="flex-1">
        @if ($title)
            <div class="font-semibold">{{ $title }}</div>
        @endif
        <div class="opacity-90">{{ $slot }}</div>
    </div>
</div>
