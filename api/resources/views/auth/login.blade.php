@extends('layouts.app')

@section('content')
    <div class="mx-auto mt-10 w-full max-w-md rounded-xl border border-slate-800 bg-slate-950/50 p-6 shadow">
        <h1 class="text-xl font-semibold">Connexion manager</h1>
        <p class="mt-1 text-sm text-slate-400">Acces reserve au role <span class="font-medium text-slate-200">manager</span>.</p>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="email" class="text-sm text-slate-300">Email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                    class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                />
                @error('email')
                    <div id="email-error" role="alert" class="mt-1 text-sm text-red-400">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="password" class="text-sm text-slate-300">Mot de passe</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                    class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                />
                @error('password')
                    <div id="password-error" role="alert" class="mt-1 text-sm text-red-400">{{ $message }}</div>
                @enderror
            </div>

            @if (old('two_fa_required'))
            <div>
                <label for="two_fa_code" class="text-sm text-slate-300">Code 2FA</label>
                <input
                    id="two_fa_code"
                    name="two_fa_code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    @error('two_fa_code') aria-invalid="true" aria-describedby="twofa-error" @enderror
                    class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                />
                @error('two_fa_code')
                    <div id="twofa-error" role="alert" class="mt-1 text-sm text-red-400">{{ $message }}</div>
                @enderror
            </div>
            @endif

            <button type="submit" class="w-full rounded-md bg-emerald-500 px-4 py-2 font-semibold text-slate-950 hover:bg-emerald-400">
                Se connecter
            </button>
        </form>
    </div>
@endsection
