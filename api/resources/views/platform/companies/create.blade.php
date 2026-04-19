@extends('platform.layouts.app')

@section('title', 'Nouvelle societe')

@section('content')
    <div class="max-w-4xl">
        <h1 class="text-2xl font-semibold">Creer une societe et son manager principal</h1>
        <p class="mt-1 text-sm text-slate-400">Une invitation email sera envoyee automatiquement au manager avec son lien d activation.</p>

        <form method="POST" action="{{ route('platform.companies.store') }}" class="mt-6 space-y-8">
            @csrf
            <div class="grid gap-4 rounded-2xl border border-slate-800 bg-slate-900 p-6 md:grid-cols-2">
                <div class="md:col-span-2 text-sm font-medium text-slate-200">Societe</div>
                <x-platform.input name="name" label="Nom" :value="old('name')" />
                <x-platform.input name="slug" label="Slug" :value="old('slug')" />
                <x-platform.input name="sector" label="Secteur" :value="old('sector')" />
                <x-platform.input name="country" label="Pays (ISO 2)" :value="old('country', 'DZ')" />
                <x-platform.input name="city" label="Ville" :value="old('city')" />
                <x-platform.input name="phone" label="Telephone" :value="old('phone')" />
                <x-platform.input name="email" label="Email societe" type="email" :value="old('email')" />
                <x-platform.input name="address" label="Adresse" :value="old('address')" />
                <x-platform.input name="language" label="Langue ISO2" :value="old('language', 'fr')" />
                <x-platform.input name="timezone" label="Fuseau horaire" :value="old('timezone', 'Africa/Algiers')" />
                <x-platform.input name="currency" label="Devise ISO3" :value="old('currency', 'DZD')" />
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Plan</label>
                    <select name="plan_id" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm text-slate-300">Notes de provisioning</label>
                    <textarea name="notes" rows="4" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="mt-1 text-sm text-rose-400">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="grid gap-4 rounded-2xl border border-slate-800 bg-slate-900 p-6 md:grid-cols-2">
                <div class="md:col-span-2 text-sm font-medium text-slate-200">Manager principal</div>
                <x-platform.input name="manager_first_name" label="Prenom" :value="old('manager_first_name')" />
                <x-platform.input name="manager_last_name" label="Nom" :value="old('manager_last_name')" />
                <x-platform.input name="manager_email" label="Email" type="email" :value="old('manager_email')" />
                <x-platform.input name="manager_phone" label="Telephone" :value="old('manager_phone')" />
            </div>

            <button type="submit" class="rounded-lg bg-emerald-500 px-5 py-3 font-medium text-slate-950">Creer la societe</button>
        </form>
    </div>
@endsection
