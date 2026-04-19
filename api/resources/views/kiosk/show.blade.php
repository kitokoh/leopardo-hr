<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Borne Leopardo RH</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <main class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-10">
        <div class="w-full rounded-3xl border border-slate-800 bg-slate-900 p-8 shadow-2xl">
            <div class="text-center">
                <div class="text-sm uppercase tracking-[0.3em] text-emerald-300">Borne d entree</div>
                <h1 class="mt-2 text-3xl font-semibold">{{ $company->name }}</h1>
                <p class="mt-2 text-slate-400">{{ $kiosk->name }} - {{ $kiosk->location_label ?? 'Point d acces principal' }}</p>
                <p class="mt-2 text-sm text-slate-500">Mode prevu: {{ $kiosk->biometric_mode }}. Si un lecteur empreinte/visage est branche, il peut renseigner automatiquement le champ identifiant via un bridge JS ou un clavier emulation HID.</p>
            </div>

            @if (session('status'))
                <div class="mt-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-center text-emerald-200">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('kiosk.punch', $kiosk->device_code) }}" class="mt-8 space-y-5">
                @csrf
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Identifiant employe</label>
                    <input id="identifier" name="identifier" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-4 text-lg" placeholder="Empreinte / badge / matricule / email" autofocus>
                    @error('identifier')
                        <div class="mt-2 text-sm text-rose-400">{{ $message }}</div>
                    @enderror
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <button type="submit" name="action" value="check_in" class="rounded-2xl bg-emerald-500 px-5 py-4 text-lg font-semibold text-slate-950">Pointer entree</button>
                    <button type="submit" name="action" value="check_out" class="rounded-2xl bg-slate-700 px-5 py-4 text-lg font-semibold">Pointer sortie</button>
                </div>
            </form>

            <div class="mt-8 rounded-2xl border border-slate-800 bg-slate-950/60 p-4 text-sm text-slate-400">
                <div class="font-medium text-slate-200">Integration lecteur</div>
                <p class="mt-2">Ce code est pret pour une borne tactile. Un lecteur externe peut injecter l identifiant collaborateur dans le champ ci-dessus via clavier HID ou via un bridge javascript expose sous <code class="text-emerald-300">window.LeopardoBiometricBridge.fillIdentifier(value)</code>.</p>
            </div>

            <script>
                window.LeopardoBiometricBridge = {
                    fillIdentifier(value) {
                        const input = document.getElementById('identifier');
                        if (!input) return;
                        input.value = value;
                        input.focus();
                    }
                };
            </script>
        </div>
    </main>
</body>
</html>
