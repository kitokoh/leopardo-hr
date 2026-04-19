@extends('layouts.app')

@section('title', 'Nouveau collaborateur')

@section('content')
    <div class="max-w-5xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Creer un compte collaborateur</h1>
                <p class="mt-1 text-sm text-slate-400">Le manager peut creer un RH ou un employe, avec invitation email immediate.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm">Retour dashboard</a>
        </div>

        <form method="POST" action="{{ route('employees.store') }}" class="space-y-6">
            @csrf
            <div class="grid gap-4 rounded-2xl border border-slate-800 bg-slate-900 p-6 md:grid-cols-2">
                <x-platform.input name="first_name" label="Prenom" :value="old('first_name')" />
                <x-platform.input name="middle_name" label="Deuxieme prenom" :value="old('middle_name')" />
                <x-platform.input name="last_name" label="Nom" :value="old('last_name')" />
                <x-platform.input name="preferred_name" label="Nom prefere" :value="old('preferred_name')" />
                <x-platform.input name="email" label="Email pro" type="email" :value="old('email')" />
                <x-platform.input name="personal_email" label="Email perso" type="email" :value="old('personal_email')" />
                <x-platform.input name="phone" label="Telephone" :value="old('phone')" />
                <x-platform.input name="matricule" label="Matricule" :value="old('matricule')" />
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Role</label>
                    <select name="role" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">
                        <option value="employee">Employe</option>
                        <option value="manager">Manager / RH</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Type manager</label>
                    <select name="manager_role" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">
                        <option value="">Aucun</option>
                        <option value="rh">RH</option>
                        <option value="dept">Responsable departement</option>
                        <option value="comptable">Comptable</option>
                        <option value="superviseur">Superviseur</option>
                    </select>
                </div>
                <x-platform.input name="date_of_birth" label="Date de naissance" type="date" :value="old('date_of_birth')" />
                <x-platform.input name="place_of_birth" label="Lieu de naissance" :value="old('place_of_birth')" />
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Genre</label>
                    <select name="gender" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">
                        <option value="">Non renseigne</option>
                        <option value="M" @selected(old('gender') === 'M')>Masculin</option>
                        <option value="F" @selected(old('gender') === 'F')>Feminin</option>
                    </select>
                </div>
                <x-platform.input name="nationality" label="Nationalite ISO2" :value="old('nationality', 'DZ')" />
                <x-platform.input name="marital_status" label="Situation familiale" :value="old('marital_status')" />
                <x-platform.input name="address_line" label="Adresse" :value="old('address_line')" />
                <x-platform.input name="postal_code" label="Code postal" :value="old('postal_code')" />
                <x-platform.input name="emergency_contact_name" label="Contact urgence" :value="old('emergency_contact_name')" />
                <x-platform.input name="emergency_contact_phone" label="Telephone urgence" :value="old('emergency_contact_phone')" />
                <x-platform.input name="emergency_contact_relation" label="Lien urgence" :value="old('emergency_contact_relation')" />
                <x-platform.input name="extra_data[department]" label="Departement" :value="old('extra_data.department')" />
                <x-platform.input name="extra_data[job_title]" label="Poste" :value="old('extra_data.job_title')" />
                <x-platform.input name="extra_data[work_location]" label="Site / lieu de travail" :value="old('extra_data.work_location')" />
                <x-platform.input name="extra_data[national_id]" label="Piece identite / CIN" :value="old('extra_data.national_id')" />
                <x-platform.input name="extra_data[tax_identifier]" label="Numero fiscal / CNAS" :value="old('extra_data.tax_identifier')" />
                <x-platform.input name="extra_data[blood_group]" label="Groupe sanguin" :value="old('extra_data.blood_group')" />
                <x-platform.input name="extra_data[education_level]" label="Niveau d etudes" :value="old('extra_data.education_level')" />
                <x-platform.input name="zkteco_id" label="Identifiant biometrie / doigt" :value="old('zkteco_id')" />
                <x-platform.input name="photo_path" label="Reference photo / visage" :value="old('photo_path')" />
                <x-platform.input name="biometric_face_reference_path" label="Reference reconnaissance faciale" :value="old('biometric_face_reference_path')" />
                <x-platform.input name="biometric_fingerprint_reference_path" label="Reference empreinte" :value="old('biometric_fingerprint_reference_path')" />
                <div class="md:col-span-2 flex flex-wrap gap-6">
                    <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="biometric_face_enabled" value="1" @checked(old('biometric_face_enabled'))> Activer donnees faciales</label>
                    <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="biometric_fingerprint_enabled" value="1" @checked(old('biometric_fingerprint_enabled'))> Activer donnees empreinte</label>
                    <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="send_invitation" value="1" checked> Envoyer invitation email</label>
                </div>
            </div>
            <button type="submit" class="rounded-lg bg-emerald-500 px-5 py-3 font-medium text-slate-950">Creer et inviter</button>
        </form>
    </div>
@endsection
