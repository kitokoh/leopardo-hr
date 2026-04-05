@props([
    'label',
    'value',
    'hint' => null,
])

<div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4 shadow">
    <div class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $label }}</div>
    <div class="mt-2 text-2xl font-semibold">{{ $value }}</div>
    @if ($hint)
        <div class="mt-1 text-xs text-slate-500">{{ $hint }}</div>
    @endif
</div>
