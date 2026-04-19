<div style="font-family: Arial, sans-serif; color: #0f172a;">
    <h2>Bienvenue sur Leopardo RH</h2>
    <p>Bonjour {{ trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) }},</p>
    <p>Votre compte a ete prepare pour la societe <strong>{{ $company->name }}</strong>.</p>
    <p>Role: <strong>{{ $employee->role }}</strong>@if($employee->manager_role) ({{ $employee->manager_role }}) @endif</p>
    <p>Email de connexion: <strong>{{ $employee->email }}</strong></p>
    <p>Invitation envoyee par: <strong>{{ $invitedByEmail }}</strong></p>
    <p>Ville / pays: <strong>{{ $company->city }}</strong>, <strong>{{ $company->country }}</strong></p>
    <p>Langue de base: <strong>{{ strtoupper($company->language ?? 'fr') }}</strong> - Fuseau: <strong>{{ $company->timezone ?? 'Africa/Algiers' }}</strong></p>
    <p>Prochaine etape recommandee: <strong>telecharger l application mobile Leopardo RH</strong>, vous connecter avec cet email, puis completer votre profil et votre demande de biometrie si votre entreprise utilise le pointage modernise.</p>
    <p>
        Activez votre compte et definissez votre mot de passe en cliquant ici:
        <a href="{{ $activationUrl }}">{{ $activationUrl }}</a>
    </p>
    <p>Ce lien expire dans 7 jours.</p>
    <p>Une fois le compte active, vous pourrez completer vos informations personnelles, vos contacts d urgence et, si besoin, soumettre vos informations biometrie. Leur activation effective restera soumise a l accord du manager ou du RH.</p>
</div>
