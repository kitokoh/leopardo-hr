<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verification 2FA - Leopardo RH</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <div class="mx-auto flex min-h-screen max-w-md items-center px-6">
        <form method="POST" action="{{ route('platform.login.2fa.verify') }}" class="w-full rounded-2xl border border-slate-800 bg-slate-900 p-8 shadow-xl">
            @csrf
            <h1 class="text-2xl font-semibold">Verification a deux facteurs</h1>
            <p class="mt-2 text-sm text-slate-400">Saisissez le code a 6 chiffres de votre application d'authentification.</p>
            <input type="hidden" name="email" value="{{ $email }}">
            <div class="mt-6 space-y-4">
                <div>
                    <label for="code" class="mb-2 block text-sm text-slate-300">Code 2FA</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 tracking-[0.5em] text-center" required @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                    @error('code')<div id="code-error" role="alert" class="mt-1 text-sm text-rose-400">{{ $message }}</div>@enderror
                </div>
            </div>
            <button type="submit" class="mt-6 w-full rounded-lg bg-emerald-500 px-4 py-3 font-medium text-slate-950">Verifier</button>
            <p class="mt-4 text-center text-sm text-slate-400">
                <a href="{{ route('platform.login') }}" class="underline hover:text-slate-200">Recommencer la connexion</a>
            </p>
        </form>
    </div>
</body>
</html>
