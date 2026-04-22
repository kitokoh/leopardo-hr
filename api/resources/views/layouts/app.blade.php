<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', 'Leopardo RH')</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    </head>
    <body class="min-h-screen bg-slate-900 text-slate-100">
        @php
            /** @var \App\Models\Employee|null $me */
            $me = auth('web')->user();
            $homeRoute = $me?->homeRoute() ?? 'login';
        @endphp
        <header class="border-b border-slate-800 bg-slate-950/60">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
                <div class="flex flex-col">
                    <a href="{{ $me ? route($homeRoute) : url('/') }}" class="text-lg font-semibold tracking-tight">Leopardo RH</a>
                    @isset($company)
                        <div class="text-xs text-slate-400">{{ $company->name }}</div>
                    @endisset
                </div>

                <div class="flex items-center gap-3">
                    @auth('web')
                        @if ($me && $me->isManager())
                            <a href="{{ route('dashboard') }}" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-slate-100 hover:bg-slate-700">
                                Dashboard
                            </a>
                            @if ($me->hasManagerRole('principal', 'rh'))
                                <a href="{{ route('employees.create') }}" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-slate-100 hover:bg-slate-700">
                                    Creer un employe
                                </a>
                                <a href="{{ route('hr.invitations.index') }}" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-slate-100 hover:bg-slate-700">
                                    Invitations
                                </a>
                            @endif
                            @if ($me->hasManagerRole('principal', 'superviseur'))
                                <a href="{{ route('biometrics.index') }}" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-slate-100 hover:bg-slate-700">
                                    Biometrie
                                </a>
                            @endif
                        @else
                            <a href="{{ route('me.dashboard') }}" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-slate-100 hover:bg-slate-700">
                                Mon espace
                            </a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-slate-100 hover:bg-slate-700">
                                Deconnexion
                            </button>
                        </form>
                    @endauth
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-6">
            @if (session('status'))
                <x-alert-banner level="success" class="mb-4">{{ session('status') }}</x-alert-banner>
            @endif
            @yield('content')
        </main>
    </body>
</html>
