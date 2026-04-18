# SCENARIOS DE TEST API POUR GITHUB ACTIONS

## Objectif

Definir une couverture backend exhaustive pour la CI GitHub Actions, alignee sur les roles reels de l'application, les domaines metier critiques et les risques multitenant.

## Perimetre

- API publique
- API authentifiee tenant
- RBAC et isolation multitenant
- Parcours critiques RH
- Endpoints techniques et resilients
- Contrats JSON consommes par le mobile

## Roles a couvrir

1. Super Admin
2. Owner / Company Admin
3. HR Manager
4. Manager
5. Employee
6. Finance / Payroll
7. Utilisateur inactif / bloque
8. Utilisateur hors tenant / tenant etranger

## Strategie CI recommandee

1. Tests `Unit`
2. Tests `Feature`
3. Tests critiques par domaine metier
4. Tests de securite / isolation
5. Rapport CI lisible avec mapping vers les scenarios

## Matrice complete des scenarios backend

### 1. Sante technique et bootstrap

- `GET /api/health` retourne 200 avec structure attendue
- Application demarre avec migrations `public` puis `tenant`
- Redis / cache / queue sync ne cassent pas les endpoints critiques
- Une erreur de bootstrap ne fuit pas d'informations sensibles

### 2. Auth publique et onboarding

- Register public succes avec creation tenant
- Register refuse si email deja utilise globalement
- Register refuse si payload invalide
- Login succes pour chaque role autorise
- Login refuse pour mot de passe invalide
- Login refuse pour compte inactif ou bloque
- `me` retourne le bon role, tenant, permissions et contexte
- Logout invalide le token en cours

### 3. RBAC par role

- Super Admin peut acceder aux ressources globales seulement
- Owner/Admin peut administrer son tenant sans acceder au global
- HR peut gerer employes et conges selon permissions
- Manager peut consulter/valider seulement son equipe
- Employee ne peut acceder qu'a ses propres donnees
- Finance peut consulter paie si activee
- Toute elevation de privilege est refusee en `403`

### 4. Isolation multitenant

- Un token du tenant A ne voit jamais les ressources du tenant B
- Les recherches par identifiant refusent les objets externes au tenant
- Les ecritures inter-tenant sont refusees
- Les user lookups / shared tables restent coherents
- Les migrations tenant ne polluent pas `public`

### 5. Employes et organisation

- Liste employees avec pagination, tri, filtre
- Creation employee avec validations metier
- Mise a jour employee avec verifications unicite/global email
- Desactivation / reactivation employee
- Consultation detail employee selon role
- Refus d'acces pour employee sur dossier d'un autre employee

### 6. Presence / attendance

- Check-in succes
- Check-out succes
- Double check-in interdit
- Check-out sans check-in interdit
- Historique presence retourne des donnees coherentes
- Resume du jour correct selon fuseau et etat
- Conflits ou doublons geres sans corruption des donnees

### 7. Conges / absences

- Creation demande de conge par employee
- Validation / refus par manager ou HR
- Solde mis a jour correctement
- Chevauchement de periodes refuse
- Consultation historique des demandes par role
- Employee ne peut pas valider sa propre demande sans permission speciale

### 8. Paie / finance

- Acces bulletins par employee
- Acces synthese payroll par finance / HR
- Refus d'acces payroll pour roles non autorises
- Calculs exposes sans fuite inter-tenant
- Etats de paie invalides rejetes proprement

### 9. Estimation / PDF / documents

- Quick estimate retourne structure et montants attendus
- Daily summary respecte les donnees filtrees
- PDF recu genere un fichier telechargeable valide
- Erreurs de generation PDF geres sans crash global

### 10. Notifications / evenements / audit

- Evenement metier declenche la notification attendue
- Endpoint de lecture marque lu / non lu correctement
- Journalisation des actions sensibles disponible si prevue

### 11. Resilience et erreurs

- `401` si token manquant / invalide
- `403` si role insuffisant
- `404` sur ressource absente avec payload standard
- `422` sur validation metier
- `429` si rate limit active
- `500` ne fuit ni stack ni secrets en production

### 12. Contrats API pour mobile

- Les endpoints auth renvoient les champs attendus par Flutter
- Les endpoints attendance renvoient un shape stable
- Les listes paginees gardent une structure constante
- Les enums / statuts attendus par le mobile restent stables

## Mapping attendu vers les suites GitHub Actions

### Suite `Unit`

- Services d'authentification
- Services de presence
- Services d'estimation / calcul
- Toute logique metier pure et deterministe

### Suite `Feature`

- Auth login / me / logout
- Auth guardrails: employee archive, company suspended
- RBAC employees
- Isolation tenant
- Attendance check-in / check-out / history
- Estimation daily summary / quick estimate / PDF
- Contrats JSON critiques pour le mobile
- Health endpoint

### Suites a ajouter ou durcir progressivement

- `tests/Feature/PublicRegisterTest.php`
- `tests/Feature/Leave/LeaveApprovalTest.php`
- `tests/Feature/Payroll/PayrollAccessTest.php`
- `tests/Feature/Security/BlockedUserTest.php`

## Sortie attendue dans GitHub Actions

- Rapport JUnit Unit
- Rapport JUnit Feature
- Logs applicatifs en artefact
- Rapport CI central mentionnant:
- couverture backend executee
- scenarios backend de reference
- gaps connus restant a fermer

## Critere GO / NO GO

- GO: tous les tests Unit + Feature passent, aucun test critique securite/isolation en echec
- NO GO: echec auth, RBAC, multitenant, attendance critique, payload contrat mobile ou payroll securite

## Gaps actuels a fermer en priorite

- Register public complet en CI
- Conges / approbations en CI
- Payroll access control en CI
- Utilisateur bloque distinct de l'etat archive en CI
