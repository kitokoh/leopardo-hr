@extends('layouts.app')

@section('title', 'Invitations')

@section('content')
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Invitations envoyees</h1>
            <div class="mt-1 text-sm text-slate-400">Gerez les invitations de votre entreprise (renvoyer un lien si l'email a ete perdu).</div>
        </div>

        @if ($errors->any())
            <x-alert-banner level="danger">{{ $errors->first() }}</x-alert-banner>
        @endif

        @php
            $invitationStatus = function ($invitation) {
                if ($invitation->accepted_at) return 'accepted';
                if ($invitation->expires_at && $invitation->expires_at->isPast()) return 'expired';
                return 'pending';
            };
        @endphp

        @if ($invitations->isEmpty())
            <x-empty-state
                title="Aucune invitation pour cette societe"
                description="Creez un employe pour declencher une invitation. Elle apparaitra ici avec son statut et le bouton pour la renvoyer." />
        @else
            <div class="rounded-lg border border-slate-800 bg-slate-900/40 p-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-slate-400">
                        <tr>
                            <th class="py-2 pr-4">Email</th>
                            <th class="py-2 pr-4">Role</th>
                            <th class="py-2 pr-4">Statut</th>
                            <th class="py-2 pr-4">Expire</th>
                            <th class="py-2 pr-4">Envoye</th>
                            <th class="py-2 pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($invitations as $invitation)
                            <tr>
                                <td class="py-2 pr-4">{{ $invitation->email }}</td>
                                <td class="py-2 pr-4">
                                    {{ $invitation->role }}@if ($invitation->manager_role) / {{ $invitation->manager_role }}@endif
                                </td>
                                <td class="py-2 pr-4">
                                    <x-attendance-badge :status="$invitationStatus($invitation)" />
                                </td>
                                <td class="py-2 pr-4">{{ optional($invitation->expires_at)->format('Y-m-d') }}</td>
                                <td class="py-2 pr-4">{{ optional($invitation->last_sent_at)->format('Y-m-d H:i') ?? '-' }}</td>
                                <td class="py-2 pr-4">
                                    @if (! $invitation->accepted_at)
                                        <form method="POST" action="{{ route('hr.invitations.resend', $invitation->id) }}">
                                            @csrf
                                            <button type="submit" class="rounded-md bg-rh px-3 py-1 text-xs font-semibold text-slate-950 hover:bg-rh-dark">
                                                Renvoyer
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
