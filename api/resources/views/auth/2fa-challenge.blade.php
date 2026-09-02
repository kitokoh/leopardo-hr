@extends('layouts.app')

@section('content')
    <div class="mx-auto mt-10 w-full max-w-md rounded-xl border border-slate-800 bg-slate-950/50 p-6 shadow">
        <h1 class="text-xl font-semibold">Double authentification</h1>
        <p class="mt-1 text-sm text-slate-400">Saisissez le code de votre application d'authentification ou un code de recuperation.</p>

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.2fa.verify') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="challenge_token" value="{{ $challengeToken }}" />

            <div>
                <label for="code" class="text-sm text-slate-300">Code TOTP</label>
                <input
                    id="code"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="16"
                    placeholder="123456"
                    class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                />
            </div>

            <div>
                <label for="recovery_code" class="text-sm text-slate-300">Code de recuperation (optionnel)</label>
                <input
                    id="recovery_code"
                    name="recovery_code"
                    type="text"
                    autocomplete="off"
                    maxlength="32"
                    class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                />
            </div>

            <button
                type="submit"
                class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
            >
                Verifier
            </button>
        </form>
    </div>
@endsection
