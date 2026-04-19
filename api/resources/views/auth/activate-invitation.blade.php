<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activation compte - Leopardo RH</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <div class="mx-auto flex min-h-screen max-w-lg items-center px-6">
        <form method="POST" action="{{ route('invitation.activate.store', ['token' => $token]) }}" class="w-full rounded-2xl border border-slate-800 bg-slate-900 p-8">
            @csrf
            <h1 class="text-2xl font-semibold">Activez votre compte</h1>
            <p class="mt-2 text-sm text-slate-400">Invitation pour {{ $invitation->email }}. Definissez maintenant votre mot de passe.</p>
            <div class="mt-6 space-y-4">
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Mot de passe</label>
                    <input name="password" type="password" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" required>
                </div>
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Confirmation</label>
                    <input name="password_confirmation" type="password" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" required>
                </div>
            </div>
            <button type="submit" class="mt-6 w-full rounded-lg bg-emerald-500 px-4 py-3 font-medium text-slate-950">Activer le compte</button>
        </form>
    </div>
</body>
</html>
