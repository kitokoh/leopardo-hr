@props(['name', 'label', 'value' => '', 'type' => 'text'])

<div>
    <label class="mb-2 block text-sm text-slate-300">{{ $label }}</label>
    <input
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $value }}"
        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
    >
    @error($name)
        <div class="mt-1 text-sm text-rose-400">{{ $message }}</div>
    @enderror
</div>
