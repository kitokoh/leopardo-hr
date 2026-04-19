<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Plateforme Leopardo RH')</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <header class="border-b border-slate-800 bg-slate-900">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <div>
                <a href="{{ route('platform.companies.index') }}" class="text-lg font-semibold">Leopardo RH - Plateforme</a>
                <div class="text-xs text-slate-400">Onboarding societes et managers</div>
            </div>
            <form method="POST" action="{{ route('platform.logout') }}">
                @csrf
                <button type="submit" class="rounded-md bg-slate-800 px-3 py-2 text-sm">Deconnexion</button>
            </form>
        </div>
    </header>
    <main class="mx-auto max-w-6xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
