<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion Super Admin - Leopardo RH</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <div class="mx-auto flex min-h-screen max-w-md items-center px-6">
        <form method="POST" action="{{ route('platform.login.store') }}" class="w-full rounded-2xl border border-slate-800 bg-slate-900 p-8 shadow-xl">
            @csrf
            <h1 class="text-2xl font-semibold">Connexion plateforme</h1>
            <p class="mt-2 text-sm text-slate-400">Acces reserve au super administrateur.</p>
            <div class="mt-6 space-y-4">
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Email</label>
                    <input name="email" type="email" value="{{ old('email') }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" required>
                    @error('email')<div class="mt-1 text-sm text-rose-400">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Mot de passe</label>
                    <input name="password" type="password" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" required>
                </div>
            </div>
            <button type="submit" class="mt-6 w-full rounded-lg bg-emerald-500 px-4 py-3 font-medium text-slate-950">Se connecter</button>
        </form>
    </div>
</body>
</html>
