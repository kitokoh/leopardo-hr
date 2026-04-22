@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-3 rounded-xl border border-slate-800 bg-slate-900/40 p-8 text-center']) }}>
    <div class="text-3xl">📭</div>
    <div class="text-base font-semibold text-slate-200">{{ $title }}</div>
    @if ($description)
        <div class="max-w-sm text-sm text-slate-400">{{ $description }}</div>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-2">{{ $slot }}</div>
    @endif
</div>
