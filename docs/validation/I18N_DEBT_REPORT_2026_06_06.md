# Rapport dette i18n - 2026-06-06

Ce rapport mesure les textes probablement hardcodes sur les surfaces critiques. Il ne bloque pas encore la CI en mode non strict : il sert de backlog de migration vers shared/i18n et ront/mobile_apps/leopardo_core/lib/l10n.

## Synthese

- Total signaux : 11642
- Priorite P1 : 4758
- Priorite P2 : 6884

## Par surface

### admin_dashboard

- Signaux : 2973
- P1 : 2973
- P2 : 0

- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\App.vue; Line=2; Text="min-h-screen bg-gray-50 dark:bg-gray-900 transition-colors duration-300"}.File):2 "min-h-screen bg-gray-50 dark:bg-gray-900 transition-colors duration-300"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\App.vue; Line=4; Text="fixed inset-0 bg-white dark:bg-gray-900 z-50 flex items-center justify-center"}.File):4 "fixed inset-0 bg-white dark:bg-gray-900 z-50 flex items-center justify-center"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\App.vue; Line=6; Text="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"}.File):6 "animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\App.vue; Line=7; Text="mt-4 text-gray-600 dark:text-gray-400"}.File):7 "mt-4 text-gray-600 dark:text-gray-400"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\App.vue; Line=42; Text='Erreur lors de l\'initialisation:'}.File):42 'Erreur lors de l\'initialisation:'
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\App.vue; Line=74; Text='Segoe UI'}.File):74 'Segoe UI'
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=5; Text="fixed inset-x-0 top-0 z-50"}.File):5 "fixed inset-x-0 top-0 z-50"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=8; Text="mx-auto max-w-7xl py-3 px-3 sm:px-6 lg:px-8"}.File):8 "mx-auto max-w-7xl py-3 px-3 sm:px-6 lg:px-8"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=9; Text="flex flex-wrap items-center justify-between"}.File):9 "flex flex-wrap items-center justify-between"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=10; Text="flex w-0 flex-1 items-center"}.File):10 "flex w-0 flex-1 items-center"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=11; Text="flex rounded-lg bg-red-800 p-2"}.File):11 "flex rounded-lg bg-red-800 p-2"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=12; Text="h-6 w-6 text-white"}.File):12 "h-6 w-6 text-white"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=14; Text="ml-3 truncate font-medium text-white"}.File):14 "ml-3 truncate font-medium text-white"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=16; Text="hidden md:inline"}.File):16 "hidden md:inline"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=17; Text='Alerte critique systÃ¨me dÃ©tectÃ©e'}.File):17 'Alerte critique systÃ¨me dÃ©tectÃ©e'
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=21; Text="order-3 mt-2 w-full flex-shrink-0 sm:order-2 sm:mt-0 sm:w-auto"}.File):21 "order-3 mt-2 w-full flex-shrink-0 sm:order-2 sm:mt-0 sm:w-auto"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=24; Text="flex items-center justify-center rounded-md border border-transparent bg-white px-4 py-2 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50"}.File):24 "flex items-center justify-center rounded-md border border-transparent bg-white px-4 py...
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=29; Text="order-2 flex-shrink-0 sm:order-3 sm:ml-3"}.File):29 "order-2 flex-shrink-0 sm:order-3 sm:ml-3"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=32; Text="-mr-1 flex rounded-md p-2 hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-white sm:-mr-2"}.File):32 "-mr-1 flex rounded-md p-2 hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-...
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=34; Text="h-6 w-6 text-white"}.File):34 "h-6 w-6 text-white"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=45; Text="fixed inset-x-0 top-0 z-40"}.File):45 "fixed inset-x-0 top-0 z-40"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=46; Text="{ 'mt-16': showCriticalAlert }"}.File):46 "{ 'mt-16': showCriticalAlert }"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=49; Text="mx-auto max-w-7xl py-2 px-3 sm:px-6 lg:px-8"}.File):49 "mx-auto max-w-7xl py-2 px-3 sm:px-6 lg:px-8"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=50; Text="flex flex-wrap items-center justify-between"}.File):50 "flex flex-wrap items-center justify-between"
- [P1] $(@{Surface=admin_dashboard; Severity=P1; File=.\front\admin-dashboard\src\components\alerts\SystemAlertsOverlay.vue; Line=51; Text="flex w-0 flex-1 items-center"}.File):51 "flex w-0 flex-1 items-center"
- ... 2948 autres signaux

### kiosk

- Signaux : 57
- P1 : 57
- P2 : 0

- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\admin.js; Line=13; Text='Erreur locale'}.File):13 'Erreur locale'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\admin.js; Line=35; Text='Synchronisation en cours...'}.File):35 'Synchronisation en cours...'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\admin.js; Line=41; Text='Erreur de synchronisation'}.File):41 'Erreur de synchronisation'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=107; Text='Leopardo RH Client'}.File):107 'Leopardo RH Client'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=108; Text='Entree principale'}.File):108 'Entree principale'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=113; Text='Aucune synchronisation confirmee'}.File):113 'Aucune synchronisation confirmee'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=120; Text='Connexion OK - synchronisation auto active'}.File):120 'Connexion OK - synchronisation auto active'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=121; Text='reseau indisponible'}.File):121 'reseau indisponible'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=140; Text='Bridge local indisponible.'}.File):140 'Bridge local indisponible.'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=142; Text='Bridge local indisponible'}.File):142 'Bridge local indisponible'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=150; Text='Veuillez saisir ou scanner un identifiant employe.'}.File):150 'Veuillez saisir ou scanner un identifiant employe.'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=153; Text='Enregistrement local du pointage...'}.File):153 'Enregistrement local du pointage...'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=163; Text='stocke hors ligne'}.File):163 'stocke hors ligne'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=169; Text='Echec de pointage.'}.File):169 'Echec de pointage.'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=177; Text='Veuillez saisir un identifiant.'}.File):177 'Veuillez saisir un identifiant.'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=180; Text='Recherche en cours...'}.File):180 'Recherche en cours...'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=196; Text="${emp.name}"}.File):196 "${emp.name}"
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=196; Text="${emp.photo_url}"}.File):196 "${emp.photo_url}"
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=210; Text="att-badge att-in"}.File):210 "att-badge att-in"
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=211; Text="att-badge att-out"}.File):211 "att-badge att-out"
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=212; Text="att-badge att-pending"}.File):212 "att-badge att-pending"
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=215; Text="att-badge att-pending"}.File):215 "att-badge att-pending"
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=229; Text='<p style="color:var(--muted);font-size:13px;">Aucun solde disponible</p>'}.File):229 '<p style="color:var(--muted);font-size:13px;">Aucun solde disponible</p>'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=242; Text='<p style="text-align:center;padding:20px;color:var(--muted);">Chargement...</p>'}.File):242 '<p style="text-align:center;padding:20px;color:var(--muted);">Chargement...</p>'
- [P1] $(@{Surface=kiosk; Severity=P1; File=.\front\zkteco-kiosk\app.js; Line=249; Text='<p style="text-align:center;padding:40px 0;color:var(--muted);">Aucune annonce active pour le moment.</p>'}.File):249 '<p style="text-align:center;padding:40px 0;color:var(--muted);">Aucune annonce active ...
- ... 32 autres signaux

### mobile_employee

- Signaux : 629
- P1 : 349
- P2 : 280

- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=89; Text='/attendance/$logId'}.File):89 '/attendance/$logId'
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=107; Text='${date.year.toString().padLeft(4, '}.File):107 '${date.year.toString().padLeft(4, '
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=107; Text=')}-${date.month.toString().padLeft(2, '}.File):107 ')}-${date.month.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=107; Text=')}-${date.day.toString().padLeft(2, '}.File):107 ')}-${date.day.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=127; Text='/employees/$employeeId/daily-summary'}.File):127 '/employees/$employeeId/daily-summary'
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=138; Text=')}-${date.day.toString().padLeft(2, '}.File):138 ')}-${date.day.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=138; Text=')}-${date.month.toString().padLeft(2, '}.File):138 ')}-${date.month.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=138; Text='${date.year.toString().padLeft(4, '}.File):138 '${date.year.toString().padLeft(4, '
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=167; Text=')}-${d.day.toString().padLeft(2, '}.File):167 ')}-${d.day.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=167; Text=')}-${d.month.toString().padLeft(2, '}.File):167 ')}-${d.month.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=167; Text='${d.year.toString().padLeft(4, '}.File):167 '${d.year.toString().padLeft(4, '
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=187; Text=')}-${from.day.toString().padLeft(2, '}.File):187 ')}-${from.day.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=187; Text=')}-${from.month.toString().padLeft(2, '}.File):187 ')}-${from.month.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=187; Text='${from.year.toString().padLeft(4, '}.File):187 '${from.year.toString().padLeft(4, '
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=189; Text=')}-${to.month.toString().padLeft(2, '}.File):189 ')}-${to.month.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=189; Text=')}-${to.day.toString().padLeft(2, '}.File):189 ')}-${to.day.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=189; Text='${to.year.toString().padLeft(4, '}.File):189 '${to.year.toString().padLeft(4, '
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=216; Text='/tasks/$taskId'}.File):216 '/tasks/$taskId'
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=244; Text='Invalid attendance/today payload'}.File):244 'Invalid attendance/today payload'
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\data\attendance_repository.dart; Line=358; Text='UTC$sign$hours:$minutes; local=${now.timeZoneName}'}.File):358 'UTC$sign$hours:$minutes; local=${now.timeZoneName}'
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\providers\attendance_provider.dart; Line=105; Text='Les donnees du jour prennent plus de temps que prevu. L\'ecran reste utilisable, vous pouvez actualiser.'}.File):105 'Les donnees du jour prennent plus de temps que prevu. L\'ecran reste utilisable, vous ...
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\providers\attendance_provider.dart; Line=151; Text='Heures supplementaires demarrees.'}.File):151 'Heures supplementaires demarrees.'
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\providers\attendance_provider.dart; Line=152; Text='Arrivee enregistree a l instant.'}.File):152 'Arrivee enregistree a l instant.'
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\providers\attendance_provider.dart; Line=195; Text='Pause enregistree.'}.File):195 'Pause enregistree.'
- [P1] $(@{Surface=mobile_employee; Severity=P1; File=.\front\mobile_apps\leopardo_employee\lib\features\attendance\providers\attendance_provider.dart; Line=196; Text='Depart enregistre a l instant.'}.File):196 'Depart enregistre a l instant.'
- ... 604 autres signaux

### mobile_manager

- Signaux : 944
- P1 : 345
- P2 : 599

- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\approvals\data\approval_repository.dart; Line=25; Text='/approvals/$id/approve'}.File):25 '/approvals/$id/approve'
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\approvals\data\approval_repository.dart; Line=35; Text='/approvals/$id/reject'}.File):35 '/approvals/$id/reject'
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\approvals\screens\approval_screen.dart; Line=34; Text='Erreur : $e'}.File):34 'Erreur : $e'
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\approvals\screens\approval_screen.dart; Line=47; Text='Motif du refus'}.File):47 'Motif du refus'
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\approvals\screens\approval_screen.dart; Line=55; Text='Expliquez la raison...'}.File):55 'Expliquez la raison...'
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\approvals\screens\approval_screen.dart; Line=87; Text='Erreur : $e'}.File):87 'Erreur : $e'
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\approvals\screens\approval_screen.dart; Line=125; Text='Tout est a jour'}.File):125 'Tout est a jour'
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\approvals\screens\approval_screen.dart; Line=126; Text='Aucune approbation en attente.'}.File):126 'Aucune approbation en attente.'
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\approvals\screens\approval_screen.dart; Line=229; Text='Chargement des approbations...'}.File):229 'Chargement des approbations...'
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=79; Text='/attendance/$logId'}.File):79 '/attendance/$logId'
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=97; Text=')}-${date.month.toString().padLeft(2, '}.File):97 ')}-${date.month.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=97; Text='${date.year.toString().padLeft(4, '}.File):97 '${date.year.toString().padLeft(4, '
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=97; Text=')}-${date.day.toString().padLeft(2, '}.File):97 ')}-${date.day.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=117; Text='/employees/$employeeId/daily-summary'}.File):117 '/employees/$employeeId/daily-summary'
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=128; Text='${date.year.toString().padLeft(4, '}.File):128 '${date.year.toString().padLeft(4, '
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=128; Text=')}-${date.day.toString().padLeft(2, '}.File):128 ')}-${date.day.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=128; Text=')}-${date.month.toString().padLeft(2, '}.File):128 ')}-${date.month.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=157; Text=')}-${d.day.toString().padLeft(2, '}.File):157 ')}-${d.day.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=157; Text=')}-${d.month.toString().padLeft(2, '}.File):157 ')}-${d.month.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=157; Text='${d.year.toString().padLeft(4, '}.File):157 '${d.year.toString().padLeft(4, '
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=177; Text=')}-${from.day.toString().padLeft(2, '}.File):177 ')}-${from.day.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=177; Text=')}-${from.month.toString().padLeft(2, '}.File):177 ')}-${from.month.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=177; Text='${from.year.toString().padLeft(4, '}.File):177 '${from.year.toString().padLeft(4, '
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=179; Text=')}-${to.day.toString().padLeft(2, '}.File):179 ')}-${to.day.toString().padLeft(2, '
- [P1] $(@{Surface=mobile_manager; Severity=P1; File=.\front\mobile_apps\leopardo_manager\lib\features\attendance\data\attendance_repository.dart; Line=179; Text=')}-${to.month.toString().padLeft(2, '}.File):179 ')}-${to.month.toString().padLeft(2, '
- ... 919 autres signaux

### mobile_platform_admin

- Signaux : 112
- P1 : 92
- P2 : 20

- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\auth\platform_login_screen.dart; Line=24; Text='admin@leopardo-rh.com'}.File):24 'admin@leopardo-rh.com'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\auth\platform_login_screen.dart; Line=72; Text='Leopardo Platform'}.File):72 'Leopardo Platform'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\auth\platform_login_screen.dart; Line=81; Text='Cockpit mobile reserve a l administration de la plateforme.'}.File):81 'Cockpit mobile reserve a l administration de la plateforme.'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\auth\platform_login_screen.dart; Line=105; Text='Ce compte protege la plateforme : saisir le code 2FA de l application authenticator.'}.File):105 'Ce compte protege la plateforme : saisir le code 2FA de l application authenticator.'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\auth\platform_login_screen.dart; Line=119; Text='Email super-admin'}.File):119 'Email super-admin'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\auth\platform_login_screen.dart; Line=125; Text='Email requis'}.File):125 'Email requis'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\auth\platform_login_screen.dart; Line=131; Text='Mot de passe'}.File):131 'Mot de passe'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\auth\platform_login_screen.dart; Line=148; Text='Mot de passe requis'}.File):148 'Mot de passe requis'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\auth\platform_login_screen.dart; Line=154; Text='Code 2FA si active'}.File):154 'Code 2FA si active'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\auth\platform_login_screen.dart; Line=162; Text='Se connecter'}.File):162 'Se connecter'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\auth\platform_login_screen.dart; Line=169; Text='Utiliser le compte demo'}.File):169 'Utiliser le compte demo'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\companies\company_create_screen.dart; Line=41; Text='Cote d Ivoire'}.File):41 'Cote d Ivoire'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\companies\company_create_screen.dart; Line=97; Text='Champ requis'}.File):97 'Champ requis'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\companies\company_create_screen.dart; Line=101; Text='Champ requis'}.File):101 'Champ requis'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\companies\company_create_screen.dart; Line=103; Text='Email invalide'}.File):103 'Email invalide'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\companies\company_create_screen.dart; Line=144; Text='Entreprise creee'}.File):144 'Entreprise creee'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\companies\company_create_screen.dart; Line=146; Text='/platform/companies/${Uri.encodeComponent(company.id)}'}.File):146 '/platform/companies/${Uri.encodeComponent(company.id)}'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\companies\company_create_screen.dart; Line=166; Text='Nouveau client'}.File):166 'Nouveau client'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\companies\company_create_screen.dart; Line=167; Text='Provisionnement plateforme'}.File):167 'Provisionnement plateforme'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\companies\company_create_screen.dart; Line=181; Text='Nom entreprise'}.File):181 'Nom entreprise'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\companies\company_create_screen.dart; Line=187; Text='Email entreprise'}.File):187 'Email entreprise'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\companies\company_create_screen.dart; Line=209; Text='Prenom manager principal'}.File):209 'Prenom manager principal'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\companies\company_create_screen.dart; Line=215; Text='Nom manager principal'}.File):215 'Nom manager principal'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\companies\company_create_screen.dart; Line=221; Text='Email manager principal'}.File):221 'Email manager principal'
- [P1] $(@{Surface=mobile_platform_admin; Severity=P1; File=.\front\mobile_apps\leopardo_platform_admin\lib\src\features\companies\company_create_screen.dart; Line=229; Text='Creer le client'}.File):229 'Creer le client'
- ... 87 autres signaux

### web_client

- Signaux : 6927
- P1 : 942
- P2 : 5985

- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=1; Text='use client'}.File):1 'use client'
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=9; Text='Information gÃ©nÃ©rale'}.File):9 'Information gÃ©nÃ©rale'
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=10; Text='Demande de dÃ©mo'}.File):10 'Demande de dÃ©mo'
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=11; Text='Support technique'}.File):11 'Support technique'
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=13; Text='Presse & MÃ©dias'}.File):13 'Presse & MÃ©dias'
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=47; Text='Erreur lors de l\'envoi'}.File):47 'Erreur lors de l\'envoi'
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=50; Text='Une erreur est survenue. Veuillez rÃ©essayer.'}.File):50 'Une erreur est survenue. Veuillez rÃ©essayer.'
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=57; Text='dark bg-slate-950'}.File):57 'dark bg-slate-950'
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=62; Text="Notre Ã©quipe est lÃ  pour rÃ©pondre Ã  toutes vos questions"}.File):62 "Notre Ã©quipe est lÃ  pour rÃ©pondre Ã  toutes vos questions"
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=63; Text='Envoyer un message'}.File):63 'Envoyer un message'
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=64; Text='Demander une dÃ©mo'}.File):64 'Demander une dÃ©mo'
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=65; Text="w-3 h-3"}.File):65 "w-3 h-3"
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=69; Text="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"}.File):69 "max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=70; Text="grid lg:grid-cols-3 gap-12"}.File):70 "grid lg:grid-cols-3 gap-12"
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=74; Text="text-2xl font-black text-slate-900 dark:text-white mb-6"}.File):74 "text-2xl font-black text-slate-900 dark:text-white mb-6"
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=77; Text='contact@leopardo.com'}.File):77 'contact@leopardo.com'
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=78; Text='TÃ©lÃ©phone'}.File):78 'TÃ©lÃ©phone'
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=79; Text='Alger, AlgÃ©rie'}.File):79 'Alger, AlgÃ©rie'
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=80; Text='Lun-Ven 9h-18h (GMT+1)'}.File):80 'Lun-Ven 9h-18h (GMT+1)'
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=82; Text="flex items-start gap-4"}.File):82 "flex items-start gap-4"
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=83; Text="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0"}.File):83 "w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-c...
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=84; Text="w-5 h-5 text-emerald-600 dark:text-emerald-400"}.File):84 "w-5 h-5 text-emerald-600 dark:text-emerald-400"
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=87; Text="text-sm text-slate-500 dark:text-slate-400"}.File):87 "text-sm text-slate-500 dark:text-slate-400"
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=88; Text="font-medium text-slate-900 dark:text-white"}.File):88 "font-medium text-slate-900 dark:text-white"
- [P1] $(@{Surface=web_client; Severity=P1; File=.\front\web\src\app\(landing)\contact\page.tsx; Line=94; Text="mt-8 p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-200 dark:border-emerald-800"}.File):94 "mt-8 p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-200 dar...
- ... 6902 autres signaux

## Regle d'execution

1. Migrer d'abord les P1 des ecrans login, compte, pointage, creation client, vitrine essai et kiosk.
2. Ajouter les nouvelles cles dans shared/i18n/locales/fr.json, puis traduire EN/AR/TR avec les prompts du guide Jules.
3. Synchroniser vers les cibles frontend/mobile quand le script de sync existe pour la surface.
4. Garder les textes techniques, routes, codes API et logs developpeur hors traduction.
