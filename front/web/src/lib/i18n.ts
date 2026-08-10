export type AppLocale = 'fr' | 'ar' | 'tr' | 'en';

export type StoredAuthUser = {
  id?: number | string;
  first_name?: string | null;
  last_name?: string | null;
  name?: string | null;
  email?: string | null;
  language?: string | null;
  is_rtl?: boolean;
  role?: string | null;
  manager_role?: string | null;
  capabilities?: Record<string, unknown> | null;
  company?: {
    id?: number | string | null;
    name?: string | null;
    language?: string | null;
    timezone?: string | null;
    currency?: string | null;
    features?: Record<string, unknown> | null;
    metadata?: Record<string, unknown> | null;
  } | null;
  plan?: {
    name?: string | null;
    features?: Record<string, unknown> | null;
  } | null;
};

export const SUPPORTED_LOCALES: AppLocale[] = ['fr', 'ar', 'tr', 'en'];
export const AUTH_TOKEN_KEY = 'auth_token';
export const AUTH_USER_KEY = 'auth_user';
export const PREFERRED_LOCALE_KEY = 'preferred_locale';

type CopyTree = {
  login: {
    title: string;
    subtitle: string;
    clientSpace: string;
    heroTitle: string;
    heroCopy: string;
    secureBadge: string;
    trustPoints: string[];
    back: string;
    email: string;
    password: string;
    showPassword: string;
    hidePassword: string;
    remember: string;
    forgot: string;
    submit: string;
    loading: string;
    demoAccess: string;
    demoTitle: string;
    demoSubtitle: string;
    close: string;
    supportCopy: string;
    supportLink: string;
    errors: {
      generic: string;
      missingToken: string;
      missingUser: string;
    };
  };
  dashboard: {
    heading: string;
    employees: string;
    present: string;
    late: string;
    activity: string;
    team: string;
    attendance: string;
    payroll: string;
    settings: string;
    logout: string;
    language: string;
    presentBadge: string;
    employeeLabel: string;
    checkInAt: string;
  };
  payrollPage: {
    title: string;
    subtitle: string;
    statTotalGross: string;
    statTotalNet: string;
    statPayslips: string;
    tabSlips: string;
    tabRuns: string;
    searchPlaceholder: string;
    columnEmployee: string;
    columnPeriod: string;
    columnGross: string;
    columnNet: string;
    columnStatus: string;
    columnActions: string;
    columnEmployees: string;
    columnTotalGross: string;
    columnTotalNet: string;
    loading: string;
    noPayslips: string;
    noRuns: string;
    statusValidated: string;
    statusDraft: string;
    statusCompleted: string;
    downloadPdf: string;
    viewDetail: string;
    resultsCount: string;
  };
  smartAttendancePage: {
    title: string;
    subtitle: string;
    allSessions: string;
    settings: string;
    pendingSessionsTitle: string;
    noPendingSessions: string;
    columnEmployee: string;
    columnCheckIn: string;
    columnCheckOut: string;
    columnDuration: string;
    columnStatus: string;
    columnActions: string;
    approve: string;
    reject: string;
    employeeFallback: string;
    dashboardLoadError: string;
    approveError: string;
    rejectError: string;
    statusDetected: string;
    statusPendingValidation: string;
    statusApproved: string;
    statusRejected: string;
    statusCancelled: string;
    statTotal: string;
    statDetected: string;
    statPending: string;
    statApproved: string;
    statRejected: string;
    approveModalTitle: string;
    approveModalBody: string;
    approveModalNoteLabel: string;
    approveModalNotePlaceholder: string;
    approveModalConfirm: string;
    approveModalInProgress: string;
    rejectModalTitle: string;
    rejectModalBody: string;
    rejectModalReasonLabel: string;
    rejectModalReasonPlaceholder: string;
    rejectModalReasonRequired: string;
    rejectModalConfirm: string;
    rejectModalInProgress: string;
    cancel: string;
  };
  smartAttendanceSessionsPage: {
    title: string;
    subtitle: string;
    backToDashboard: string;
    loadError: string;
    filtersTitle: string;
    filterStatus: string;
    filterStatusAll: string;
    filterEmployee: string;
    filterEmployeePlaceholder: string;
    filterDateFrom: string;
    filterDateTo: string;
    apply: string;
    reset: string;
    exportCsv: string;
    sessionsTitle: string;
    sessionCountSingular: string;
    sessionCountPlural: string;
    columnEmployee: string;
    columnCheckIn: string;
    columnCheckOut: string;
    columnDuration: string;
    columnStatus: string;
    columnDetail: string;
    noSessions: string;
    viewDetail: string;
    employeeFallback: string;
    pageLabel: string;
    previous: string;
    next: string;
    csvHeaderId: string;
    csvHeaderEmployee: string;
    csvHeaderMatricule: string;
    csvHeaderCheckIn: string;
    csvHeaderCheckOut: string;
    csvHeaderDuration: string;
    csvHeaderStatus: string;
  };
  smartAttendanceSessionDetailPage: {
    title: string;
    subtitle: string;
    backToSessions: string;
    loadError: string;
    notFound: string;
    employeeFallback: string;
    noteLabel: string;
    rejectionReasonLabel: string;
    timelineTitle: string;
    checkInDetected: string;
    departure: string;
    durationLabel: string;
    gpsCoordinatesTitle: string;
    checkInLabel: string;
    checkOutLabel: string;
    viewOnMaps: string;
    gpsHistoryTitle: string;
    columnType: string;
    columnTime: string;
    columnLatitude: string;
    columnLongitude: string;
    columnAccuracy: string;
    pendingValidationNotice: string;
    approve: string;
    reject: string;
    approveErrorGeneric: string;
    rejectErrorGeneric: string;
  };
  smartAttendanceSettingsPage: {
    title: string;
    subtitle: string;
    backToDashboard: string;
    loadError: string;
    saveError: string;
    saveSuccess: string;
    currentModeLabel: string;
    modeFree: string;
    modeGpsAuto: string;
    modeQrCode: string;
    modeManual: string;
    gpsLabel: string;
    gpsEnabled: string;
    gpsDisabled: string;
    radiusLabel: string;
    configurationTitle: string;
    modeFieldLabel: string;
    modeFreeHint: string;
    gpsToggleTitle: string;
    gpsToggleSubtitle: string;
    geofenceConfigTitle: string;
    latitudeLabel: string;
    longitudeLabel: string;
    radiusFieldLabel: string;
    radiusHint: string;
    save: string;
    saving: string;
    cancel: string;
  };
  edgeNodesPage: {
    title: string;
    subtitle: string;
    loadError: string;
    syncError: string;
    createError: string;
    newNode: string;
    nodeCreatedTitle: string;
    copy: string;
    statTotalNodes: string;
    statOnline: string;
    statOffline: string;
    statValidLicenses: string;
    configuredNodesTitle: string;
    loadingNodes: string;
    noNodes: string;
    licenseExpired: string;
    addressMissing: string;
    lastSyncLabel: string;
    lastSyncNever: string;
    statusOnline: string;
    statusOffline: string;
    syncing: string;
    sync: string;
    modeCloud: string;
    modeHybrid: string;
    modeOffline: string;
    modalTitle: string;
    siteNameLabel: string;
    siteNamePlaceholder: string;
    siteAddressLabel: string;
    siteAddressPlaceholder: string;
    modeLabel: string;
    modeHybridOption: string;
    modeOfflineOption: string;
    modeCloudOption: string;
    cancel: string;
    createNode: string;
    syncCompleteMessage: string;
  };
  developerSettingsPage: {
    title: string;
    subtitle: string;
    loadTokensError: string;
    loadWebhooksError: string;
    createTokenError: string;
    deleteTokenError: string;
    createWebhookError: string;
    deleteWebhookError: string;
    updateWebhookError: string;
    revokeTokenConfirm: string;
    deleteWebhookConfirm: string;
    revealedTokenNotice: string;
    revealedTokenDismiss: string;
    apiKeysTitle: string;
    loading: string;
    noTokens: string;
    createdOn: string;
    unknownDate: string;
    lastUsedOn: string;
    neverUsed: string;
    revoke: string;
    tokenNamePlaceholder: string;
    webhooksTitle: string;
    noWebhooks: string;
    eventsCount: string;
    failuresCount: string;
    noFailures: string;
    active: string;
    inactive: string;
    triggeredOn: string;
    neverTriggered: string;
    delete: string;
    addEndpoint: string;
    apiDocsTitle: string;
    apiDocsBody: string;
    openExplorer: string;
    newWebhookModalTitle: string;
    destinationUrlLabel: string;
    eventsToListenLabel: string;
    cancel: string;
    creating: string;
    create: string;
  };
  offlinePage: {
    title: string;
    body: string;
    edgeModeTitle: string;
    edgeModeBody: string;
    retry: string;
  };
};

const copy: Record<AppLocale, CopyTree> = {
  fr: {
    login: {
      title: 'Connexion a Leopardo RH',
      subtitle: 'Accedez a votre espace RH, suivez vos equipes et pilotez les modules actifs de votre entreprise.',
      clientSpace: 'Espace client',
      heroTitle: 'Un acces RH clair pour chaque manager, chaque pays et chaque equipe.',
      heroCopy: 'Votre portail client reste connecte a l API Leopardo RH, avec permissions, langue et contexte tenant appliques des la connexion.',
      secureBadge: 'Connexion securisee',
      trustPoints: [
        'Session liee a votre tenant',
        'Permissions appliquees par role',
        'Interface prete pour manager, RH et employe',
      ],
      back: 'Retour au site',
      email: 'Adresse email',
      password: 'Mot de passe',
      showPassword: 'Afficher le mot de passe',
      hidePassword: 'Masquer le mot de passe',
      remember: 'Se souvenir de moi',
      forgot: 'Mot de passe oublie ?',
      submit: 'Se connecter',
      loading: 'Connexion...',
      demoAccess: 'Tester avec un compte demo',
      demoTitle: 'Choisir un compte demo',
      demoSubtitle: 'Selectionnez un role pour pre-remplir le formulaire, puis lancez la connexion.',
      close: 'Fermer',
      supportCopy: 'Besoin d aide pour recuperer un acces ?',
      supportLink: 'Contacter le support',
      errors: {
        generic: 'Une erreur est survenue.',
        missingToken: 'Le jeton de connexion est absent de la reponse API.',
        missingUser: 'Le profil utilisateur est absent de la reponse API.',
      },
    },
    dashboard: {
      heading: 'Tableau de bord',
      employees: 'Employes actifs',
      present: 'presents',
      late: 'Retards',
      activity: 'Activite recente',
      team: 'Employes',
      attendance: 'Pointages',
      payroll: 'Paie',
      settings: 'Parametres',
      logout: 'Deconnexion',
      language: 'Langue',
      presentBadge: 'Present',
      employeeLabel: 'Employe',
      checkInAt: 'Check-in a',
    },
    payrollPage: {
      title: 'Paie',
      subtitle: "Bulletins de paie et cycles de paie, avec export PDF direct, connecte a l'API RH pour chaque tenant.",
      statTotalGross: 'Total Brut',
      statTotalNet: 'Total Net',
      statPayslips: 'Bulletins',
      tabSlips: 'Bulletins de paie',
      tabRuns: 'Cycles de paie',
      searchPlaceholder: 'Rechercher par nom ou periode...',
      columnEmployee: 'Employe',
      columnPeriod: 'Periode',
      columnGross: 'Brut',
      columnNet: 'Net',
      columnStatus: 'Statut',
      columnActions: 'Actions',
      columnEmployees: 'Employes',
      columnTotalGross: 'Total Brut',
      columnTotalNet: 'Total Net',
      loading: 'Chargement...',
      noPayslips: 'Aucun bulletin trouve.',
      noRuns: 'Aucun cycle de paie.',
      statusValidated: 'Valide',
      statusDraft: 'Brouillon',
      statusCompleted: 'Termine',
      downloadPdf: 'Telecharger PDF',
      viewDetail: 'Voir detail',
      resultsCount: 'resultats',
    },
    smartAttendancePage: {
      title: 'Smart Attendance',
      subtitle: 'Suivi intelligent de presence par geolocalisation — validation des sessions en attente et statistiques du jour.',
      allSessions: 'Toutes les sessions →',
      settings: 'Parametres',
      pendingSessionsTitle: 'Sessions en attente de validation',
      noPendingSessions: 'Aucune session en attente de validation.',
      columnEmployee: 'Employe',
      columnCheckIn: 'Arrivee',
      columnCheckOut: 'Depart',
      columnDuration: 'Duree',
      columnStatus: 'Statut',
      columnActions: 'Actions',
      approve: 'Approuver',
      reject: 'Refuser',
      employeeFallback: 'Employe',
      dashboardLoadError: 'Impossible de charger le tableau de bord.',
      approveError: "Erreur lors de l'approbation.",
      rejectError: 'Erreur lors du refus.',
      statusDetected: 'Detecte',
      statusPendingValidation: 'En attente',
      statusApproved: 'Approuve',
      statusRejected: 'Refuse',
      statusCancelled: 'Annule',
      statTotal: 'Total',
      statDetected: 'Detectes',
      statPending: 'En attente',
      statApproved: 'Approuves',
      statRejected: 'Refuses',
      approveModalTitle: 'Approuver la session',
      approveModalBody: 'Vous allez approuver la session de {name}. Cette action est definitive.',
      approveModalNoteLabel: 'Note (optionnel)',
      approveModalNotePlaceholder: 'Ajouter une note…',
      approveModalConfirm: 'Approuver',
      approveModalInProgress: 'En cours…',
      rejectModalTitle: 'Refuser la session',
      rejectModalBody: 'Vous allez refuser la session de {name}. Veuillez indiquer une raison.',
      rejectModalReasonLabel: 'Raison du refus',
      rejectModalReasonPlaceholder: 'Raison obligatoire…',
      rejectModalReasonRequired: 'La raison est obligatoire.',
      rejectModalConfirm: 'Refuser',
      rejectModalInProgress: 'En cours…',
      cancel: 'Annuler',
    },
    smartAttendanceSessionsPage: {
      title: 'Sessions de presence',
      subtitle: 'Liste complete des sessions Smart Attendance avec filtres avances et pagination.',
      backToDashboard: '← Tableau de bord',
      loadError: 'Impossible de charger les sessions.',
      filtersTitle: 'Filtres',
      filterStatus: 'Statut',
      filterStatusAll: 'Tous les statuts',
      filterEmployee: 'Employe (ID ou nom)',
      filterEmployeePlaceholder: 'Rechercher…',
      filterDateFrom: 'Date debut',
      filterDateTo: 'Date fin',
      apply: 'Appliquer',
      reset: 'Reinitialiser',
      exportCsv: '⬇ Export CSV',
      sessionsTitle: 'Sessions',
      sessionCountSingular: 'session',
      sessionCountPlural: 'sessions',
      columnEmployee: 'Employe',
      columnCheckIn: 'Arrivee',
      columnCheckOut: 'Depart',
      columnDuration: 'Duree',
      columnStatus: 'Statut',
      columnDetail: 'Detail',
      noSessions: 'Aucune session trouvee pour ces criteres.',
      viewDetail: 'Voir →',
      employeeFallback: 'Employe',
      pageLabel: 'Page',
      previous: '← Precedent',
      next: 'Suivant →',
      csvHeaderId: 'ID',
      csvHeaderEmployee: 'Employe',
      csvHeaderMatricule: 'Matricule',
      csvHeaderCheckIn: 'Arrivee',
      csvHeaderCheckOut: 'Depart',
      csvHeaderDuration: 'Duree (min)',
      csvHeaderStatus: 'Statut',
    },
    smartAttendanceSessionDetailPage: {
      title: 'Detail de session',
      subtitle: 'Informations completes de la session de presence geolocalisee.',
      backToSessions: '← Retour aux sessions',
      loadError: 'Impossible de charger la session.',
      notFound: 'Session introuvable.',
      employeeFallback: 'Employe',
      noteLabel: 'Note : ',
      rejectionReasonLabel: 'Raison du refus : ',
      timelineTitle: 'Timeline',
      checkInDetected: 'Arrivee detectee',
      departure: 'Depart',
      durationLabel: 'Duree : ',
      gpsCoordinatesTitle: 'Coordonnees GPS',
      checkInLabel: 'Check-in',
      checkOutLabel: 'Check-out',
      viewOnMaps: 'Voir sur Maps →',
      gpsHistoryTitle: 'Historique des evenements GPS',
      columnType: 'Type',
      columnTime: 'Heure',
      columnLatitude: 'Latitude',
      columnLongitude: 'Longitude',
      columnAccuracy: 'Precision (m)',
      pendingValidationNotice: 'Cette session est en attente de validation.',
      approve: 'Approuver',
      reject: 'Refuser',
      approveErrorGeneric: "Erreur lors de l'approbation.",
      rejectErrorGeneric: 'Erreur lors du refus.',
    },
    smartAttendanceSettingsPage: {
      title: 'Paramètres Smart Attendance',
      subtitle: 'Configuration du mode de pointage et du géofence entreprise.',
      backToDashboard: '← Tableau de bord',
      loadError: 'Impossible de charger les paramètres.',
      saveError: "Erreur lors de l'enregistrement.",
      saveSuccess: 'Paramètres enregistrés avec succès.',
      currentModeLabel: 'Mode actuel',
      modeFree: 'Libre (pas de mode forcé)',
      modeGpsAuto: 'GPS automatique',
      modeQrCode: 'QR Code',
      modeManual: 'Manuel',
      gpsLabel: 'GPS : ',
      gpsEnabled: 'Activé',
      gpsDisabled: 'Désactivé',
      radiusLabel: 'Rayon : ',
      configurationTitle: 'Configuration',
      modeFieldLabel: 'Mode de pointage',
      modeFreeHint: '“Libre” laisse l’employé choisir la méthode disponible.',
      gpsToggleTitle: 'Géolocalisation GPS',
      gpsToggleSubtitle: 'Activer la vérification de position',
      geofenceConfigTitle: 'Configuration du géofence',
      latitudeLabel: 'Latitude',
      longitudeLabel: 'Longitude',
      radiusFieldLabel: 'Rayon (mètres)',
      radiusHint: 'Distance maximale autorisée depuis le lieu de travail.',
      save: 'Enregistrer',
      saving: 'Enregistrement…',
      cancel: 'Annuler',
    },
    edgeNodesPage: {
      title: 'Edge Nodes',
      subtitle: 'Gerez les noeuds locaux pour le mode offline-first : etat de connexion, licences et synchronisation.',
      loadError: 'Impossible de charger les Edge nodes.',
      syncError: 'Erreur lors de la synchronisation',
      createError: 'Erreur lors de la creation du node',
      newNode: 'Nouveau Node',
      nodeCreatedTitle: 'Node cree ! Copiez et executez cette commande sur votre serveur local :',
      copy: 'Copier',
      statTotalNodes: 'Total nodes',
      statOnline: 'En ligne',
      statOffline: 'Hors ligne',
      statValidLicenses: 'Licences valides',
      configuredNodesTitle: 'Noeuds configures',
      loadingNodes: 'Chargement des Edge nodes...',
      noNodes: 'Aucun Edge node configure. Creez un nouveau node pour activer le mode offline-first.',
      licenseExpired: 'Licence expiree',
      addressMissing: 'Adresse non renseignee',
      lastSyncLabel: 'Derniere sync : ',
      lastSyncNever: 'jamais',
      statusOnline: 'En ligne',
      statusOffline: 'Hors ligne',
      syncing: 'Sync...',
      sync: 'Sync',
      modeCloud: 'Cloud',
      modeHybrid: 'Hybride',
      modeOffline: 'Offline',
      modalTitle: 'Nouveau Edge Node',
      siteNameLabel: 'Nom du site',
      siteNamePlaceholder: 'ex: Entrepot Nord',
      siteAddressLabel: 'Adresse du site',
      siteAddressPlaceholder: 'ex: Zone Industrielle, Batiment A',
      modeLabel: 'Mode',
      modeHybridOption: 'Hybride (recommande)',
      modeOfflineOption: 'Offline total',
      modeCloudOption: 'Cloud uniquement',
      cancel: 'Annuler',
      createNode: 'Creer le node',
      syncCompleteMessage: 'Sync termine — envoyes: {sent}, conflits: {conflicts}',
    },
    developerSettingsPage: {
      title: 'Espace Developpeur',
      subtitle: 'Gerez vos cles API et vos webhooks pour integrer Leopardo RH a vos outils.',
      loadTokensError: 'Impossible de charger les cles API.',
      loadWebhooksError: 'Impossible de charger les webhooks.',
      createTokenError: 'Impossible de creer la cle API.',
      deleteTokenError: 'Impossible de revoquer la cle API.',
      createWebhookError: 'Impossible de creer le webhook.',
      deleteWebhookError: 'Impossible de supprimer le webhook.',
      updateWebhookError: 'Impossible de mettre a jour le webhook.',
      revokeTokenConfirm: "Revoquer cette cle API ? Les integrations qui l'utilisent cesseront de fonctionner.",
      deleteWebhookConfirm: 'Supprimer cet endpoint webhook ?',
      revealedTokenNotice: 'Cle "{name}" creee — copiez-la maintenant, elle ne sera plus jamais affichee :',
      revealedTokenDismiss: "J'ai copie la cle, masquer",
      apiKeysTitle: 'Cles API',
      loading: 'Chargement...',
      noTokens: 'Aucune cle API creee pour le moment.',
      createdOn: 'Creee le {date}',
      unknownDate: 'Date inconnue',
      lastUsedOn: ' · derniere utilisation le {date}',
      neverUsed: ' · jamais utilisee',
      revoke: 'Revoquer',
      tokenNamePlaceholder: 'Nom de la cle (ex: Production)',
      webhooksTitle: 'Webhooks',
      noWebhooks: 'Aucun endpoint webhook configure.',
      eventsCount: '{count} evenement(s)',
      failuresCount: '{count} echec(s)',
      noFailures: 'aucun echec',
      active: 'Actif',
      inactive: 'Inactif',
      triggeredOn: 'Declenche le {date}',
      neverTriggered: 'Jamais declenche',
      delete: 'Supprimer',
      addEndpoint: 'Ajouter un endpoint',
      apiDocsTitle: 'Documentation API',
      apiDocsBody: 'Decouvrez comment integrer nos webhooks signes (format Svix) et nos endpoints REST.',
      openExplorer: "Ouvrir l'Explorer",
      newWebhookModalTitle: 'Nouvel endpoint webhook',
      destinationUrlLabel: 'URL de destination',
      eventsToListenLabel: 'Evenements a ecouter',
      cancel: 'Annuler',
      creating: 'Creation...',
      create: 'Creer',
    },
    offlinePage: {
      title: 'Pas de connexion Internet',
      body: "Vous etes actuellement hors ligne. Si un Edge node Leopardo est disponible sur votre reseau local, l'application continue de fonctionner normalement.",
      edgeModeTitle: 'Mode Edge actif ?',
      edgeModeBody: "Accedez a l'interface locale via :",
      retry: 'Reessayer',
    },
  },
  ar: {
    login: {
      title: 'تسجيل الدخول إلى Leopardo RH',
      subtitle: 'ادخل إلى مساحة الموارد البشرية مع اللغة والدور والصلاحيات المناسبة.',
      clientSpace: 'مساحة العميل',
      heroTitle: 'دخول واضح وآمن للمديرين وفرق الموارد البشرية والموظفين.',
      heroCopy: 'تطبق البوابة سياق الشركة واللغة والصلاحيات مباشرة بعد تسجيل الدخول.',
      secureBadge: 'تسجيل دخول آمن',
      trustPoints: [
        'جلسة مرتبطة بالشركة',
        'صلاحيات حسب الدور',
        'واجهة تدعم العربية و RTL',
      ],
      back: 'العودة إلى الموقع',
      email: 'البريد الإلكتروني',
      password: 'كلمة المرور',
      showPassword: 'إظهار كلمة المرور',
      hidePassword: 'إخفاء كلمة المرور',
      remember: 'تذكرني',
      forgot: 'نسيت كلمة المرور؟',
      submit: 'تسجيل الدخول',
      loading: 'جار تسجيل الدخول...',
      demoAccess: 'تجربة حساب تجريبي',
      demoTitle: 'اختيار حساب تجريبي',
      demoSubtitle: 'اختر دورا لملء النموذج ثم سجل الدخول.',
      close: 'إغلاق',
      supportCopy: 'تحتاج مساعدة لاسترجاع الدخول؟',
      supportLink: 'اتصل بالدعم',
      errors: {
        generic: 'حدث خطأ.',
        missingToken: 'رمز تسجيل الدخول غير موجود في رد ال API.',
        missingUser: 'ملف المستخدم غير موجود في رد ال API.',
      },
    },
    dashboard: {
      heading: 'لوحة التحكم',
      employees: 'الموظفون النشطون',
      present: 'حاضرون',
      late: 'التأخيرات',
      activity: 'النشاط الأخير',
      team: 'الموظفون',
      attendance: 'الحضور',
      payroll: 'الرواتب',
      settings: 'الإعدادات',
      logout: 'تسجيل الخروج',
      language: 'اللغة',
      presentBadge: 'حاضر',
      employeeLabel: 'موظف',
      checkInAt: 'تسجيل الدخول في',
    },
    payrollPage: {
      title: 'الرواتب',
      subtitle: 'كشوف الرواتب ودورات الرواتب، مع تصدير PDF مباشر، متصلة بواجهة برمجة الموارد البشرية لكل شركة.',
      statTotalGross: 'إجمالي الإجمالي',
      statTotalNet: 'إجمالي الصافي',
      statPayslips: 'كشوف الرواتب',
      tabSlips: 'كشوف الرواتب',
      tabRuns: 'دورات الرواتب',
      searchPlaceholder: 'البحث بالاسم أو الفترة...',
      columnEmployee: 'الموظف',
      columnPeriod: 'الفترة',
      columnGross: 'الإجمالي',
      columnNet: 'الصافي',
      columnStatus: 'الحالة',
      columnActions: 'الإجراءات',
      columnEmployees: 'الموظفون',
      columnTotalGross: 'إجمالي الإجمالي',
      columnTotalNet: 'إجمالي الصافي',
      loading: 'جار التحميل...',
      noPayslips: 'لا توجد كشوف رواتب.',
      noRuns: 'لا توجد دورة رواتب.',
      statusValidated: 'معتمد',
      statusDraft: 'مسودة',
      statusCompleted: 'مكتمل',
      downloadPdf: 'تحميل PDF',
      viewDetail: 'عرض التفاصيل',
      resultsCount: 'نتائج',
    },
    smartAttendancePage: {
      title: 'الحضور الذكي',
      subtitle: 'تتبع ذكي للحضور بالموقع الجغرافي — اعتماد الجلسات المعلقة وإحصائيات اليوم.',
      allSessions: 'جميع الجلسات ←',
      settings: 'الإعدادات',
      pendingSessionsTitle: 'جلسات في انتظار الاعتماد',
      noPendingSessions: 'لا توجد جلسات في انتظار الاعتماد.',
      columnEmployee: 'الموظف',
      columnCheckIn: 'الوصول',
      columnCheckOut: 'المغادرة',
      columnDuration: 'المدة',
      columnStatus: 'الحالة',
      columnActions: 'الإجراءات',
      approve: 'اعتماد',
      reject: 'رفض',
      employeeFallback: 'موظف',
      dashboardLoadError: 'تعذر تحميل لوحة التحكم.',
      approveError: 'خطأ أثناء الاعتماد.',
      rejectError: 'خطأ أثناء الرفض.',
      statusDetected: 'مكتشف',
      statusPendingValidation: 'قيد الانتظار',
      statusApproved: 'معتمد',
      statusRejected: 'مرفوض',
      statusCancelled: 'ملغى',
      statTotal: 'الإجمالي',
      statDetected: 'مكتشف',
      statPending: 'قيد الانتظار',
      statApproved: 'معتمد',
      statRejected: 'مرفوض',
      approveModalTitle: 'اعتماد الجلسة',
      approveModalBody: 'ستقوم باعتماد جلسة {name}. هذا الإجراء نهائي.',
      approveModalNoteLabel: 'ملاحظة (اختياري)',
      approveModalNotePlaceholder: 'إضافة ملاحظة…',
      approveModalConfirm: 'اعتماد',
      approveModalInProgress: 'جار التنفيذ…',
      rejectModalTitle: 'رفض الجلسة',
      rejectModalBody: 'ستقوم برفض جلسة {name}. يرجى تحديد السبب.',
      rejectModalReasonLabel: 'سبب الرفض',
      rejectModalReasonPlaceholder: 'السبب مطلوب…',
      rejectModalReasonRequired: 'السبب مطلوب.',
      rejectModalConfirm: 'رفض',
      rejectModalInProgress: 'جار التنفيذ…',
      cancel: 'إلغاء',
    },
    smartAttendanceSessionsPage: {
      title: 'جلسات الحضور',
      subtitle: 'قائمة كاملة بجلسات Smart Attendance مع مرشحات متقدمة وترقيم صفحات.',
      backToDashboard: '← لوحة التحكم',
      loadError: 'تعذر تحميل الجلسات.',
      filtersTitle: 'المرشحات',
      filterStatus: 'الحالة',
      filterStatusAll: 'جميع الحالات',
      filterEmployee: 'الموظف (رقم أو اسم)',
      filterEmployeePlaceholder: 'بحث…',
      filterDateFrom: 'تاريخ البداية',
      filterDateTo: 'تاريخ النهاية',
      apply: 'تطبيق',
      reset: 'إعادة التعيين',
      exportCsv: '⬇ تصدير CSV',
      sessionsTitle: 'الجلسات',
      sessionCountSingular: 'جلسة',
      sessionCountPlural: 'جلسات',
      columnEmployee: 'الموظف',
      columnCheckIn: 'الوصول',
      columnCheckOut: 'المغادرة',
      columnDuration: 'المدة',
      columnStatus: 'الحالة',
      columnDetail: 'التفاصيل',
      noSessions: 'لم يتم العثور على جلسات لهذه المعايير.',
      viewDetail: 'عرض ←',
      employeeFallback: 'موظف',
      pageLabel: 'صفحة',
      previous: '← السابق',
      next: 'التالي →',
      csvHeaderId: 'الرقم',
      csvHeaderEmployee: 'الموظف',
      csvHeaderMatricule: 'رقم الموظف',
      csvHeaderCheckIn: 'الوصول',
      csvHeaderCheckOut: 'المغادرة',
      csvHeaderDuration: 'المدة (دقيقة)',
      csvHeaderStatus: 'الحالة',
    },
    smartAttendanceSessionDetailPage: {
      title: 'تفاصيل الجلسة',
      subtitle: 'معلومات كاملة عن جلسة الحضور بالموقع الجغرافي.',
      backToSessions: '← الرجوع إلى الجلسات',
      loadError: 'تعذر تحميل الجلسة.',
      notFound: 'الجلسة غير موجودة.',
      employeeFallback: 'موظف',
      noteLabel: 'ملاحظة: ',
      rejectionReasonLabel: 'سبب الرفض: ',
      timelineTitle: 'المخطط الزمني',
      checkInDetected: 'الوصول المكتشف',
      departure: 'المغادرة',
      durationLabel: 'المدة: ',
      gpsCoordinatesTitle: 'إحداثيات GPS',
      checkInLabel: 'الوصول',
      checkOutLabel: 'المغادرة',
      viewOnMaps: 'عرض على الخريطة ←',
      gpsHistoryTitle: 'سجل أحداث GPS',
      columnType: 'النوع',
      columnTime: 'الوقت',
      columnLatitude: 'خط العرض',
      columnLongitude: 'خط الطول',
      columnAccuracy: 'الدقة (م)',
      pendingValidationNotice: 'هذه الجلسة في انتظار الاعتماد.',
      approve: 'اعتماد',
      reject: 'رفض',
      approveErrorGeneric: 'خطأ أثناء الاعتماد.',
      rejectErrorGeneric: 'خطأ أثناء الرفض.',
    },
    smartAttendanceSettingsPage: {
      title: 'إعدادات Smart Attendance',
      subtitle: 'إعداد وضع التسجيل والنطاق الجمارافي للشركة.',
      backToDashboard: '← لوحة التحكم',
      loadError: 'تعذر تحميل الإعدادات.',
      saveError: 'خطأ أثناء الحفظ.',
      saveSuccess: 'تم حفظ الإعدادات بنجاح.',
      currentModeLabel: 'الوضع الحالي',
      modeFree: 'حر (بدون وضع مفروض)',
      modeGpsAuto: 'GPS تلقائي',
      modeQrCode: 'رمز QR',
      modeManual: 'يدوي',
      gpsLabel: 'GPS: ',
      gpsEnabled: 'مفعّل',
      gpsDisabled: 'معطّل',
      radiusLabel: 'النطاق: ',
      configurationTitle: 'التكوين',
      modeFieldLabel: 'وضع التسجيل',
      modeFreeHint: 'يسمح الوضع الحر للموظف باختيار الطريقة المتاحة.',
      gpsToggleTitle: 'تحديد الموقع GPS',
      gpsToggleSubtitle: 'تفعيل التحقق من الموقع',
      geofenceConfigTitle: 'إعداد النطاق الجمارافي',
      latitudeLabel: 'خط العرض',
      longitudeLabel: 'خط الطول',
      radiusFieldLabel: 'النطاق (متر)',
      radiusHint: 'المسافة القصوى المسموح بها من مكان العمل.',
      save: 'حفظ',
      saving: 'جار التنفيذ…',
      cancel: 'إلغاء',
    },
    edgeNodesPage: {
      title: 'عقد Edge',
      subtitle: 'إدارة العقد المحلية لوضع offline-first: حالة الاتصال، التراخيص والمزامنة.',
      loadError: 'تعذر تحميل عقد Edge.',
      syncError: 'خطأ أثناء المزامنة',
      createError: 'خطأ أثناء إنشاء العقدة',
      newNode: 'عقدة جديدة',
      nodeCreatedTitle: 'تم إنشاء العقدة! انسخ ونفّذ هذا الأمر على خادمك المحلي:',
      copy: 'نسخ',
      statTotalNodes: 'إجمالي العقد',
      statOnline: 'متصل',
      statOffline: 'منقطع',
      statValidLicenses: 'تراخيص سارية',
      configuredNodesTitle: 'العقد المكوّنة',
      loadingNodes: 'تحميل عقد Edge...',
      noNodes: 'لا توجد عقدة Edge مكوّنة. أنشئ عقدة جديدة لتفعيل وضع offline-first.',
      licenseExpired: 'الترخيص منتهٍ',
      addressMissing: 'العنوان لم يرد',
      lastSyncLabel: 'آخر مزامنة: ',
      lastSyncNever: 'لم يجرِ',
      statusOnline: 'متصل',
      statusOffline: 'منقطع',
      syncing: 'جارٍ المزامنة...',
      sync: 'مزامنة',
      modeCloud: 'سحابي',
      modeHybrid: 'هجين',
      modeOffline: 'دون اتصال',
      modalTitle: 'عقدة Edge جديدة',
      siteNameLabel: 'اسم الموقع',
      siteNamePlaceholder: 'مثلاً: مستودع الشمال',
      siteAddressLabel: 'عنوان الموقع',
      siteAddressPlaceholder: 'مثلاً: المنطقة الصناعية، المبنى A',
      modeLabel: 'الوضع',
      modeHybridOption: 'هجين (موصى به)',
      modeOfflineOption: 'دون اتصال كلي',
      modeCloudOption: 'سحابي فقط',
      cancel: 'إلغاء',
      createNode: 'إنشاء العقدة',
      syncCompleteMessage: 'اكتملت المزامنة — مرسل: {sent}، التعارضات: {conflicts}',
    },
    developerSettingsPage: {
      title: 'مساد المطور',
      subtitle: 'أدر مفاتيح API والردود الويب لدمج Leopardo RH مع أدواتك.',
      loadTokensError: 'تعذر تحميل مفاتيح API.',
      loadWebhooksError: 'تعذر تحميل الردود.',
      createTokenError: 'تعذر إنشاء مفتاح API.',
      deleteTokenError: 'تعذر إلغاء مفتاح API.',
      createWebhookError: 'تعذر إنشاء الرد الويب.',
      deleteWebhookError: 'تعذر حذف الرد الويب.',
      updateWebhookError: 'تعذر تحديث الرد الويب.',
      revokeTokenConfirm: 'هل تريد إلعاء مفتاح API هذا؟ ستوقف التكاملات المستخدمة له عن العمل.',
      deleteWebhookConfirm: 'حذف نقطة الرد الويب هذه؟',
      revealedTokenNotice: 'مفتاح "{name}" تم إنشاؤه — انسخه الآن، لن يعرض مرة أخرى:',
      revealedTokenDismiss: 'لقد نسخت المفتاح، إخفاء',
      apiKeysTitle: 'مفاتيح API',
      loading: 'تحميل...',
      noTokens: 'لم يتم إنشاء أي مفتاح API حتى الآن.',
      createdOn: 'أنشئ في {date}',
      unknownDate: 'تاريخ مفقود',
      lastUsedOn: ' · آخر استعمال في {date}',
      neverUsed: ' · لم يستخدم قط',
      revoke: 'إلعاء',
      tokenNamePlaceholder: 'اسم المفتاح (مثلاً: الإنتاج)',
      webhooksTitle: 'الردود الويب',
      noWebhooks: 'لا توجد نقاط رد ويب مكوّنة.',
      eventsCount: '{count} حدث(ان)',
      failuresCount: '{count} فشل(ات)',
      noFailures: 'لا يوجد فشل',
      active: 'نشط',
      inactive: 'معطل',
      triggeredOn: 'تم التفعيل في {date}',
      neverTriggered: 'لم يتم التفعيل قط',
      delete: 'حذف',
      addEndpoint: 'إضافة نقطة',
      apiDocsTitle: 'وثائق API',
      apiDocsBody: 'اكتشف كيفية دمج ردودنا الموقعة (بتنسيق Svix) ونقاط REST.',
      openExplorer: 'فتح المستكشف',
      newWebhookModalTitle: 'نقطة رد ويب جديدة',
      destinationUrlLabel: 'رابط الوجهة',
      eventsToListenLabel: 'الأحداث المراد الاستماع لها',
      cancel: 'إلغاء',
      creating: 'الإنشاء...',
      create: 'إنشاء',
    },
    offlinePage: {
      title: 'لا يوجد اتصال بالإنترنت',
      body: 'أنت غير متصل حاليا بالإنترنت. في حال توفر عقدة Leopardo Edge على شبكتك المحلية، يستمر التطبيق في العمل بشكل طبيعي.',
      edgeModeTitle: 'وضع Edge نشط؟',
      edgeModeBody: 'ادخل إلى الواجهة المحلية عبر:',
      retry: 'إعادة المحاولة',
    },
  },
  tr: {
    login: {
      title: 'Leopardo IK girisi',
      subtitle: 'Sirket alaniniza, ekiplerinize ve aktif IK modullerinize guvenli sekilde erisin.',
      clientSpace: 'Musteri alani',
      heroTitle: 'Her yonetici, ulke ve ekip icin net bir IK girisi.',
      heroCopy: 'Leopardo RH portali giristen itibaren tenant, rol, dil ve izin baglaminizi uygular.',
      secureBadge: 'Guvenli giris',
      trustPoints: [
        'Tenant bazli oturum',
        'Rol bazli izinler',
        'Yonetici, IK ve calisan icin hazir arayuz',
      ],
      back: 'Siteye don',
      email: 'E-posta',
      password: 'Sifre',
      showPassword: 'Sifreyi goster',
      hidePassword: 'Sifreyi gizle',
      remember: 'Beni hatirla',
      forgot: 'Sifremi unuttum?',
      submit: 'Giris yap',
      loading: 'Giris yapiliyor...',
      demoAccess: 'Demo hesapla dene',
      demoTitle: 'Demo hesabi sec',
      demoSubtitle: 'Formu doldurmak icin bir rol secin, sonra girisi baslatin.',
      close: 'Kapat',
      supportCopy: 'Erisim kurtarma icin yardim mi gerekiyor?',
      supportLink: 'Destekle iletisime gec',
      errors: {
        generic: 'Bir hata olustu.',
        missingToken: 'API yanitinda giris tokeni yok.',
        missingUser: 'API yanitinda kullanici profili yok.',
      },
    },
    dashboard: {
      heading: 'Kontrol paneli',
      employees: 'Aktif calisanlar',
      present: 'mevcut',
      late: 'Gecikmeler',
      activity: 'Son etkinlik',
      team: 'Calisanlar',
      attendance: 'Devam',
      payroll: 'Bordro',
      settings: 'Ayarlar',
      logout: 'Cikis yap',
      language: 'Dil',
      presentBadge: 'Burada',
      employeeLabel: 'Calisan',
      checkInAt: 'Giris saati',
    },
    payrollPage: {
      title: 'Bordro',
      subtitle: 'Bordrolar ve bordro donemleri, her kiraci icin IK API sine bagli dogrudan PDF disa aktarimi ile.',
      statTotalGross: 'Toplam Brut',
      statTotalNet: 'Toplam Net',
      statPayslips: 'Bordrolar',
      tabSlips: 'Bordrolar',
      tabRuns: 'Bordro donemleri',
      searchPlaceholder: 'Ad veya donem ile ara...',
      columnEmployee: 'Calisan',
      columnPeriod: 'Donem',
      columnGross: 'Brut',
      columnNet: 'Net',
      columnStatus: 'Durum',
      columnActions: 'Islemler',
      columnEmployees: 'Calisanlar',
      columnTotalGross: 'Toplam Brut',
      columnTotalNet: 'Toplam Net',
      loading: 'Yukleniyor...',
      noPayslips: 'Bordro bulunamadi.',
      noRuns: 'Bordro donemi bulunamadi.',
      statusValidated: 'Onaylandi',
      statusDraft: 'Taslak',
      statusCompleted: 'Tamamlandi',
      downloadPdf: 'PDF indir',
      viewDetail: 'Detayi gor',
      resultsCount: 'sonuc',
    },
    smartAttendancePage: {
      title: 'Akilli Devam',
      subtitle: 'Konum tabanli akilli devam takibi — bekleyen oturumlarin onayi ve gunun istatistikleri.',
      allSessions: 'Tum oturumlar →',
      settings: 'Ayarlar',
      pendingSessionsTitle: 'Onay bekleyen oturumlar',
      noPendingSessions: 'Onay bekleyen oturum yok.',
      columnEmployee: 'Calisan',
      columnCheckIn: 'Giris',
      columnCheckOut: 'Cikis',
      columnDuration: 'Sure',
      columnStatus: 'Durum',
      columnActions: 'Islemler',
      approve: 'Onayla',
      reject: 'Reddet',
      employeeFallback: 'Calisan',
      dashboardLoadError: 'Panel yuklenemedi.',
      approveError: 'Onaylama sirasinda hata olustu.',
      rejectError: 'Reddetme sirasinda hata olustu.',
      statusDetected: 'Tespit edildi',
      statusPendingValidation: 'Beklemede',
      statusApproved: 'Onaylandi',
      statusRejected: 'Reddedildi',
      statusCancelled: 'Iptal edildi',
      statTotal: 'Toplam',
      statDetected: 'Tespit edilen',
      statPending: 'Beklemede',
      statApproved: 'Onaylanan',
      statRejected: 'Reddedilen',
      approveModalTitle: 'Oturumu onayla',
      approveModalBody: '{name} oturumunu onaylayacaksiniz. Bu islem kesindir.',
      approveModalNoteLabel: 'Not (opsiyonel)',
      approveModalNotePlaceholder: 'Bir not ekleyin…',
      approveModalConfirm: 'Onayla',
      approveModalInProgress: 'Isleniyor…',
      rejectModalTitle: 'Oturumu reddet',
      rejectModalBody: '{name} oturumunu reddedeceksiniz. Lutfen bir neden belirtin.',
      rejectModalReasonLabel: 'Reddetme nedeni',
      rejectModalReasonPlaceholder: 'Neden zorunludur…',
      rejectModalReasonRequired: 'Neden zorunludur.',
      rejectModalConfirm: 'Reddet',
      rejectModalInProgress: 'Isleniyor…',
      cancel: 'Vazgec',
    },
    smartAttendanceSessionsPage: {
      title: 'Devam oturumlari',
      subtitle: 'Gelismis filtreler ve sayfalama ile Akilli Devam oturumlarinin tam listesi.',
      backToDashboard: '← Panel',
      loadError: 'Oturumlar yuklenemedi.',
      filtersTitle: 'Filtreler',
      filterStatus: 'Durum',
      filterStatusAll: 'Tum durumlar',
      filterEmployee: 'Calisan (ID veya ad)',
      filterEmployeePlaceholder: 'Ara…',
      filterDateFrom: 'Baslangic tarihi',
      filterDateTo: 'Bitis tarihi',
      apply: 'Uygula',
      reset: 'Sifirla',
      exportCsv: '⬇ CSV disa aktar',
      sessionsTitle: 'Oturumlar',
      sessionCountSingular: 'oturum',
      sessionCountPlural: 'oturum',
      columnEmployee: 'Calisan',
      columnCheckIn: 'Giris',
      columnCheckOut: 'Cikis',
      columnDuration: 'Sure',
      columnStatus: 'Durum',
      columnDetail: 'Detay',
      noSessions: 'Bu kriterlere uygun oturum bulunamadi.',
      viewDetail: 'Goruntule →',
      employeeFallback: 'Calisan',
      pageLabel: 'Sayfa',
      previous: '← Onceki',
      next: 'Sonraki →',
      csvHeaderId: 'ID',
      csvHeaderEmployee: 'Calisan',
      csvHeaderMatricule: 'Sicil no',
      csvHeaderCheckIn: 'Giris',
      csvHeaderCheckOut: 'Cikis',
      csvHeaderDuration: 'Sure (dk)',
      csvHeaderStatus: 'Durum',
    },
    smartAttendanceSessionDetailPage: {
      title: 'Oturum detayi',
      subtitle: 'Konum tabanli devam oturumunun tam bilgileri.',
      backToSessions: '← Oturumlara don',
      loadError: 'Oturum yuklenemedi.',
      notFound: 'Oturum bulunamadi.',
      employeeFallback: 'Calisan',
      noteLabel: 'Not: ',
      rejectionReasonLabel: 'Reddetme nedeni: ',
      timelineTitle: 'Zaman cizelgesi',
      checkInDetected: 'Tespit edilen giris',
      departure: 'Cikis',
      durationLabel: 'Sure: ',
      gpsCoordinatesTitle: 'GPS koordinatlari',
      checkInLabel: 'Giris',
      checkOutLabel: 'Cikis',
      viewOnMaps: 'Haritada goster →',
      gpsHistoryTitle: 'GPS olay gecmisi',
      columnType: 'Tur',
      columnTime: 'Saat',
      columnLatitude: 'Enlem',
      columnLongitude: 'Boylam',
      columnAccuracy: 'Hassasiyet (m)',
      pendingValidationNotice: 'Bu oturum onay beklemektedir.',
      approve: 'Onayla',
      reject: 'Reddet',
      approveErrorGeneric: 'Onaylama sirasinda hata olustu.',
      rejectErrorGeneric: 'Reddetme sirasinda hata olustu.',
    },
    smartAttendanceSettingsPage: {
      title: 'Akilli Devam Ayarlari',
      subtitle: 'Yoklama modu ve sirket cografi sinirinin yapilandirmasi.',
      backToDashboard: '← Panel',
      loadError: 'Ayarlar yuklenemedi.',
      saveError: 'Kaydetme sirasinda hata olustu.',
      saveSuccess: 'Ayarlar basariyla kaydedildi.',
      currentModeLabel: 'Mevcut mod',
      modeFree: 'Serbest (zorunlu mod yok)',
      modeGpsAuto: 'Otomatik GPS',
      modeQrCode: 'QR Kod',
      modeManual: 'Manuel',
      gpsLabel: 'GPS: ',
      gpsEnabled: 'Etkin',
      gpsDisabled: 'Devre disi',
      radiusLabel: 'Yaricap: ',
      configurationTitle: 'Yapilandirma',
      modeFieldLabel: 'Yoklama modu',
      modeFreeHint: '"Serbest" calisanin mevcut yontemi secmesine izin verir.',
      gpsToggleTitle: 'GPS konum belirleme',
      gpsToggleSubtitle: 'Konum dogrulamasini etkinlestir',
      geofenceConfigTitle: 'Cografi sinir yapilandirmasi',
      latitudeLabel: 'Enlem',
      longitudeLabel: 'Boylam',
      radiusFieldLabel: 'Yaricap (metre)',
      radiusHint: 'Isyerinden izin verilen maksimum uzaklik.',
      save: 'Kaydet',
      saving: 'Kaydediliyor…',
      cancel: 'Vazgec',
    },
    edgeNodesPage: {
      title: 'Edge Node\'lari',
      subtitle: 'Offline-first modu icin yerel node\'lari yonetin: baglanti durumu, lisanslar ve senkronizasyon.',
      loadError: 'Edge node\'lari yuklenemedi.',
      syncError: 'Senkronizasyon sirasinda hata olustu',
      createError: 'Node olusturma sirasinda hata olustu',
      newNode: 'Yeni Node',
      nodeCreatedTitle: 'Node olusturuldu! Bu komutu yerel sunucunuzda kopyalayip calistirin:',
      copy: 'Kopyala',
      statTotalNodes: 'Toplam node',
      statOnline: 'Cevrimici',
      statOffline: 'Cevrimdisi',
      statValidLicenses: 'Gecerli lisanslar',
      configuredNodesTitle: 'Yapilandirilmis node\'lar',
      loadingNodes: 'Edge node\'lari yukleniyor...',
      noNodes: 'Yapilandirilmis Edge node yok. Offline-first modunu etkinlestirmek icin yeni bir node olusturun.',
      licenseExpired: 'Lisans suresi doldu',
      addressMissing: 'Adres belirtilmedi',
      lastSyncLabel: 'Son senkronizasyon: ',
      lastSyncNever: 'hicbir zaman',
      statusOnline: 'Cevrimici',
      statusOffline: 'Cevrimdisi',
      syncing: 'Senkronize ediliyor...',
      sync: 'Senkronize et',
      modeCloud: 'Bulut',
      modeHybrid: 'Hibrit',
      modeOffline: 'Cevrimdisi',
      modalTitle: 'Yeni Edge Node',
      siteNameLabel: 'Site adi',
      siteNamePlaceholder: 'orn: Kuzey Deposu',
      siteAddressLabel: 'Site adresi',
      siteAddressPlaceholder: 'orn: Sanayi Bolgesi, A Blok',
      modeLabel: 'Mod',
      modeHybridOption: 'Hibrit (onerilir)',
      modeOfflineOption: 'Tam cevrimdisi',
      modeCloudOption: 'Sadece bulut',
      cancel: 'Vazgec',
      createNode: 'Node olustur',
      syncCompleteMessage: 'Senkronizasyon tamamlandi — gonderilen: {sent}, catisma: {conflicts}',
    },
    developerSettingsPage: {
      title: 'Gelistirici Alani',
      subtitle: 'Leopardo HR\'yi araclarinizla entegre etmek icin API anahtarlarinizi ve webhook\'larinizi yonetin.',
      loadTokensError: 'API anahtarlari yuklenemedi.',
      loadWebhooksError: 'Webhook\'lar yuklenemedi.',
      createTokenError: 'API anahtari olusturulamadi.',
      deleteTokenError: 'API anahtari iptal edilemedi.',
      createWebhookError: 'Webhook olusturulamadi.',
      deleteWebhookError: 'Webhook silinemedi.',
      updateWebhookError: 'Webhook guncellenemedi.',
      revokeTokenConfirm: 'Bu API anahtari iptal edilsin mi? Bunu kullanan entegrasyonlar calismayi durduracak.',
      deleteWebhookConfirm: 'Bu webhook endpoint\'i silinsin mi?',
      revealedTokenNotice: '"{name}" anahtari olusturuldu — simdi kopyalayin, bir daha gosterilmeyecek:',
      revealedTokenDismiss: 'Anahtari kopyaladim, gizle',
      apiKeysTitle: 'API Anahtarlari',
      loading: 'Yukleniyor...',
      noTokens: 'Henuz olusturulmus bir API anahtari yok.',
      createdOn: '{date} tarihinde olusturuldu',
      unknownDate: 'Tarih bilinmiyor',
      lastUsedOn: ' · son kullanim {date}',
      neverUsed: ' · hic kullanilmadi',
      revoke: 'Iptal et',
      tokenNamePlaceholder: 'Anahtar adi (orn: Uretim)',
      webhooksTitle: 'Webhook\'lar',
      noWebhooks: 'Yapilandirilmis webhook endpoint\'i yok.',
      eventsCount: '{count} olay',
      failuresCount: '{count} hata',
      noFailures: 'hata yok',
      active: 'Aktif',
      inactive: 'Devre disi',
      triggeredOn: '{date} tarihinde tetiklendi',
      neverTriggered: 'Hic tetiklenmedi',
      delete: 'Sil',
      addEndpoint: 'Endpoint ekle',
      apiDocsTitle: 'API Dokumantasyonu',
      apiDocsBody: 'Imzali webhook\'larimizi (Svix formati) ve REST endpoint\'lerimizi nasil entegre edeceginizi ogrenin.',
      openExplorer: "Explorer'i ac",
      newWebhookModalTitle: 'Yeni webhook endpoint\'i',
      destinationUrlLabel: 'Hedef URL',
      eventsToListenLabel: 'Dinlenecek olaylar',
      cancel: 'Vazgec',
      creating: 'Olusturuluyor...',
      create: 'Olustur',
    },
    offlinePage: {
      title: 'Internet baglantisi yok',
      body: 'Su anda cevrimdisisiniz. Yerel agnizda bir Leopardo Edge node mevcutsa, uygulama normal sekilde calismaya devam eder.',
      edgeModeTitle: 'Edge modu aktif mi?',
      edgeModeBody: 'Yerel arayuze su adresten erisin:',
      retry: 'Yeniden dene',
    },
  },
  en: {
    login: {
      title: 'Sign in to Leopardo RH',
      subtitle: 'Access your HR workspace, follow your teams, and manage the modules enabled for your company.',
      clientSpace: 'Client workspace',
      heroTitle: 'A clear HR access point for every manager, country, and team.',
      heroCopy: 'Your client portal stays connected to the Leopardo RH API with tenant context, language, and permissions applied after sign-in.',
      secureBadge: 'Secure sign-in',
      trustPoints: [
        'Session bound to your tenant',
        'Permissions applied by role',
        'Ready for managers, HR and employees',
      ],
      back: 'Back to site',
      email: 'Email address',
      password: 'Password',
      showPassword: 'Show password',
      hidePassword: 'Hide password',
      remember: 'Remember me',
      forgot: 'Forgot password?',
      submit: 'Sign in',
      loading: 'Signing in...',
      demoAccess: 'Try a demo account',
      demoTitle: 'Choose a demo account',
      demoSubtitle: 'Select a role to prefill the form, then sign in.',
      close: 'Close',
      supportCopy: 'Need help recovering access?',
      supportLink: 'Contact support',
      errors: {
        generic: 'Something went wrong.',
        missingToken: 'The login token is missing from the API response.',
        missingUser: 'The authenticated user profile is missing from the API response.',
      },
    },
    dashboard: {
      heading: 'Dashboard',
      employees: 'Active employees',
      present: 'present',
      late: 'Late arrivals',
      activity: 'Recent activity',
      team: 'Employees',
      attendance: 'Attendance',
      payroll: 'Payroll',
      settings: 'Settings',
      logout: 'Sign out',
      language: 'Language',
      presentBadge: 'Present',
      employeeLabel: 'Employee',
      checkInAt: 'Check-in at',
    },
    payrollPage: {
      title: 'Payroll',
      subtitle: 'Pay slips and payroll runs, with direct PDF export, connected to the HR API for each tenant.',
      statTotalGross: 'Total Gross',
      statTotalNet: 'Total Net',
      statPayslips: 'Pay slips',
      tabSlips: 'Pay slips',
      tabRuns: 'Payroll runs',
      searchPlaceholder: 'Search by name or period...',
      columnEmployee: 'Employee',
      columnPeriod: 'Period',
      columnGross: 'Gross',
      columnNet: 'Net',
      columnStatus: 'Status',
      columnActions: 'Actions',
      columnEmployees: 'Employees',
      columnTotalGross: 'Total Gross',
      columnTotalNet: 'Total Net',
      loading: 'Loading...',
      noPayslips: 'No pay slip found.',
      noRuns: 'No payroll run found.',
      statusValidated: 'Validated',
      statusDraft: 'Draft',
      statusCompleted: 'Completed',
      downloadPdf: 'Download PDF',
      viewDetail: 'View detail',
      resultsCount: 'results',
    },
    smartAttendancePage: {
      title: 'Smart Attendance',
      subtitle: 'Smart geolocation-based attendance tracking — pending session validation and daily statistics.',
      allSessions: 'All sessions →',
      settings: 'Settings',
      pendingSessionsTitle: 'Sessions pending validation',
      noPendingSessions: 'No session pending validation.',
      columnEmployee: 'Employee',
      columnCheckIn: 'Check-in',
      columnCheckOut: 'Check-out',
      columnDuration: 'Duration',
      columnStatus: 'Status',
      columnActions: 'Actions',
      approve: 'Approve',
      reject: 'Reject',
      employeeFallback: 'Employee',
      dashboardLoadError: 'Unable to load the dashboard.',
      approveError: 'Error while approving.',
      rejectError: 'Error while rejecting.',
      statusDetected: 'Detected',
      statusPendingValidation: 'Pending',
      statusApproved: 'Approved',
      statusRejected: 'Rejected',
      statusCancelled: 'Cancelled',
      statTotal: 'Total',
      statDetected: 'Detected',
      statPending: 'Pending',
      statApproved: 'Approved',
      statRejected: 'Rejected',
      approveModalTitle: 'Approve session',
      approveModalBody: "You are about to approve {name}'s session. This action is final.",
      approveModalNoteLabel: 'Note (optional)',
      approveModalNotePlaceholder: 'Add a note…',
      approveModalConfirm: 'Approve',
      approveModalInProgress: 'In progress…',
      rejectModalTitle: 'Reject session',
      rejectModalBody: "You are about to reject {name}'s session. Please provide a reason.",
      rejectModalReasonLabel: 'Reason for rejection',
      rejectModalReasonPlaceholder: 'Reason is required…',
      rejectModalReasonRequired: 'The reason is required.',
      rejectModalConfirm: 'Reject',
      rejectModalInProgress: 'In progress…',
      cancel: 'Cancel',
    },
    smartAttendanceSessionsPage: {
      title: 'Attendance sessions',
      subtitle: 'Full list of Smart Attendance sessions with advanced filters and pagination.',
      backToDashboard: '← Dashboard',
      loadError: 'Unable to load sessions.',
      filtersTitle: 'Filters',
      filterStatus: 'Status',
      filterStatusAll: 'All statuses',
      filterEmployee: 'Employee (ID or name)',
      filterEmployeePlaceholder: 'Search…',
      filterDateFrom: 'Start date',
      filterDateTo: 'End date',
      apply: 'Apply',
      reset: 'Reset',
      exportCsv: '⬇ Export CSV',
      sessionsTitle: 'Sessions',
      sessionCountSingular: 'session',
      sessionCountPlural: 'sessions',
      columnEmployee: 'Employee',
      columnCheckIn: 'Check-in',
      columnCheckOut: 'Check-out',
      columnDuration: 'Duration',
      columnStatus: 'Status',
      columnDetail: 'Detail',
      noSessions: 'No session found for these criteria.',
      viewDetail: 'View →',
      employeeFallback: 'Employee',
      pageLabel: 'Page',
      previous: '← Previous',
      next: 'Next →',
      csvHeaderId: 'ID',
      csvHeaderEmployee: 'Employee',
      csvHeaderMatricule: 'Employee number',
      csvHeaderCheckIn: 'Check-in',
      csvHeaderCheckOut: 'Check-out',
      csvHeaderDuration: 'Duration (min)',
      csvHeaderStatus: 'Status',
    },
    smartAttendanceSessionDetailPage: {
      title: 'Session detail',
      subtitle: 'Complete information for the geolocated attendance session.',
      backToSessions: '← Back to sessions',
      loadError: 'Unable to load the session.',
      notFound: 'Session not found.',
      employeeFallback: 'Employee',
      noteLabel: 'Note: ',
      rejectionReasonLabel: 'Rejection reason: ',
      timelineTitle: 'Timeline',
      checkInDetected: 'Arrival detected',
      departure: 'Departure',
      durationLabel: 'Duration: ',
      gpsCoordinatesTitle: 'GPS coordinates',
      checkInLabel: 'Check-in',
      checkOutLabel: 'Check-out',
      viewOnMaps: 'View on Maps →',
      gpsHistoryTitle: 'GPS event history',
      columnType: 'Type',
      columnTime: 'Time',
      columnLatitude: 'Latitude',
      columnLongitude: 'Longitude',
      columnAccuracy: 'Accuracy (m)',
      pendingValidationNotice: 'This session is pending validation.',
      approve: 'Approve',
      reject: 'Reject',
      approveErrorGeneric: 'Error while approving.',
      rejectErrorGeneric: 'Error while rejecting.',
    },
    smartAttendanceSettingsPage: {
      title: 'Smart Attendance settings',
      subtitle: 'Configuration of the check-in mode and the company geofence.',
      backToDashboard: '← Dashboard',
      loadError: 'Unable to load settings.',
      saveError: 'Error while saving.',
      saveSuccess: 'Settings saved successfully.',
      currentModeLabel: 'Current mode',
      modeFree: 'Free (no forced mode)',
      modeGpsAuto: 'Automatic GPS',
      modeQrCode: 'QR Code',
      modeManual: 'Manual',
      gpsLabel: 'GPS: ',
      gpsEnabled: 'Enabled',
      gpsDisabled: 'Disabled',
      radiusLabel: 'Radius: ',
      configurationTitle: 'Configuration',
      modeFieldLabel: 'Check-in mode',
      modeFreeHint: '"Free" lets the employee choose the available method.',
      gpsToggleTitle: 'GPS geolocation',
      gpsToggleSubtitle: 'Enable position verification',
      geofenceConfigTitle: 'Geofence configuration',
      latitudeLabel: 'Latitude',
      longitudeLabel: 'Longitude',
      radiusFieldLabel: 'Radius (meters)',
      radiusHint: 'Maximum allowed distance from the workplace.',
      save: 'Save',
      saving: 'Saving…',
      cancel: 'Cancel',
    },
    edgeNodesPage: {
      title: 'Edge Nodes',
      subtitle: 'Manage local nodes for offline-first mode: connection status, licenses and synchronization.',
      loadError: 'Unable to load Edge nodes.',
      syncError: 'Error during synchronization',
      createError: 'Error while creating the node',
      newNode: 'New Node',
      nodeCreatedTitle: 'Node created! Copy and run this command on your local server:',
      copy: 'Copy',
      statTotalNodes: 'Total nodes',
      statOnline: 'Online',
      statOffline: 'Offline',
      statValidLicenses: 'Valid licenses',
      configuredNodesTitle: 'Configured nodes',
      loadingNodes: 'Loading Edge nodes...',
      noNodes: 'No Edge node configured. Create a new node to enable offline-first mode.',
      licenseExpired: 'License expired',
      addressMissing: 'No address provided',
      lastSyncLabel: 'Last sync: ',
      lastSyncNever: 'never',
      statusOnline: 'Online',
      statusOffline: 'Offline',
      syncing: 'Syncing...',
      sync: 'Sync',
      modeCloud: 'Cloud',
      modeHybrid: 'Hybrid',
      modeOffline: 'Offline',
      modalTitle: 'New Edge Node',
      siteNameLabel: 'Site name',
      siteNamePlaceholder: 'e.g. North Warehouse',
      siteAddressLabel: 'Site address',
      siteAddressPlaceholder: 'e.g. Industrial Zone, Building A',
      modeLabel: 'Mode',
      modeHybridOption: 'Hybrid (recommended)',
      modeOfflineOption: 'Fully offline',
      modeCloudOption: 'Cloud only',
      cancel: 'Cancel',
      createNode: 'Create node',
      syncCompleteMessage: 'Sync complete — sent: {sent}, conflicts: {conflicts}',
    },
    developerSettingsPage: {
      title: 'Developer Area',
      subtitle: 'Manage your API keys and webhooks to integrate Leopardo HR with your tools.',
      loadTokensError: 'Unable to load API keys.',
      loadWebhooksError: 'Unable to load webhooks.',
      createTokenError: 'Unable to create the API key.',
      deleteTokenError: 'Unable to revoke the API key.',
      createWebhookError: 'Unable to create the webhook.',
      deleteWebhookError: 'Unable to delete the webhook.',
      updateWebhookError: 'Unable to update the webhook.',
      revokeTokenConfirm: 'Revoke this API key? Integrations using it will stop working.',
      deleteWebhookConfirm: 'Delete this webhook endpoint?',
      revealedTokenNotice: 'Key "{name}" created — copy it now, it will never be shown again:',
      revealedTokenDismiss: "I've copied the key, hide it",
      apiKeysTitle: 'API Keys',
      loading: 'Loading...',
      noTokens: 'No API key created yet.',
      createdOn: 'Created on {date}',
      unknownDate: 'Unknown date',
      lastUsedOn: ' · last used on {date}',
      neverUsed: ' · never used',
      revoke: 'Revoke',
      tokenNamePlaceholder: 'Key name (e.g. Production)',
      webhooksTitle: 'Webhooks',
      noWebhooks: 'No webhook endpoint configured.',
      eventsCount: '{count} event(s)',
      failuresCount: '{count} failure(s)',
      noFailures: 'no failures',
      active: 'Active',
      inactive: 'Inactive',
      triggeredOn: 'Triggered on {date}',
      neverTriggered: 'Never triggered',
      delete: 'Delete',
      addEndpoint: 'Add an endpoint',
      apiDocsTitle: 'API Documentation',
      apiDocsBody: 'Learn how to integrate our signed webhooks (Svix format) and our REST endpoints.',
      openExplorer: 'Open the Explorer',
      newWebhookModalTitle: 'New webhook endpoint',
      destinationUrlLabel: 'Destination URL',
      eventsToListenLabel: 'Events to listen for',
      cancel: 'Cancel',
      creating: 'Creating...',
      create: 'Create',
    },
    offlinePage: {
      title: 'No internet connection',
      body: 'You are currently offline. If a Leopardo Edge node is available on your local network, the application keeps working normally.',
      edgeModeTitle: 'Edge mode active?',
      edgeModeBody: 'Access the local interface via:',
      retry: 'Retry',
    },
  },
};

export function isSupportedLocale(value: unknown): value is AppLocale {
  return typeof value === 'string' && SUPPORTED_LOCALES.includes(value as AppLocale);
}

export function normalizeLocale(value: unknown): AppLocale {
  if (typeof value !== 'string' || value.trim() === '') {
    return 'fr';
  }

  const normalized = value.toLowerCase().slice(0, 2);
  return isSupportedLocale(normalized) ? normalized : 'fr';
}

export function getLocaleDirection(locale: AppLocale, isRtl?: boolean): 'ltr' | 'rtl' {
  return isRtl === true || locale === 'ar' ? 'rtl' : 'ltr';
}

const INTL_LOCALE_MAP: Record<AppLocale, string> = {
  fr: 'fr-FR',
  // S-5 (#1665) : ar-SA force le calendrier HIJRI et les chiffres
  // arabes orientaux pour les dates — en pratique les clients attendent
  // le calendrier grégorien (ar-EG, défaut grégorien).
  ar: 'ar-EG',
  tr: 'tr-TR',
  en: 'en-US',
};

/**
 * Resout un code de locale applicatif (fr/ar/tr/en) vers un tag BCP-47
 * pret pour `Intl.NumberFormat`/`Intl.DateTimeFormat` (ex: 'fr' -> 'fr-FR').
 * Utiliser cette fonction plutot que de coder 'fr-FR' en dur dans les pages.
 */
export function toIntlLocale(locale: AppLocale): string {
  return INTL_LOCALE_MAP[locale] ?? INTL_LOCALE_MAP.fr;
}

export function getCopy(locale: AppLocale) {
  return copy[locale];
}

export function getStoredUser(): StoredAuthUser | null {
  if (typeof window === 'undefined') return null;

  const raw = window.localStorage.getItem(AUTH_USER_KEY);
  if (!raw) return null;

  try {
    return JSON.parse(raw) as StoredAuthUser;
  } catch {
    clearAuthSession();
    return null;
  }
}

export function getPreferredLocale(): AppLocale {
  if (typeof window === 'undefined') return 'fr';

  const storedUser = getStoredUser();
  if (storedUser?.language) {
    return normalizeLocale(storedUser.language);
  }

  const raw = window.localStorage.getItem(PREFERRED_LOCALE_KEY);
  if (raw) {
    return normalizeLocale(raw);
  }

  return normalizeLocale(window.navigator.language);
}

export function storePreferredLocale(locale: AppLocale): void {
  if (typeof window === 'undefined') return;
  window.localStorage.setItem(PREFERRED_LOCALE_KEY, locale);
}

// Audit #1699 : le token n'est plus stocké côté JS (cookie httpOnly
// `leopardo_token` géré par les route handlers). Le paramètre token est
// conservé pour la compatibilité d'appel mais jamais écrit au repos.
export function storeAuthSession(_token: string | null | undefined, user: StoredAuthUser): void {
  if (typeof window === 'undefined') return;
  window.localStorage.removeItem(AUTH_TOKEN_KEY);
  window.localStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
  storePreferredLocale(normalizeLocale(user.language));
}

export function clearAuthSession(): void {
  if (typeof window === 'undefined') return;
  window.localStorage.removeItem(AUTH_TOKEN_KEY);
  window.localStorage.removeItem(AUTH_USER_KEY);
  window.localStorage.removeItem(PREFERRED_LOCALE_KEY);
}

export function applyDocumentLocale(locale: AppLocale, isRtl?: boolean): void {
  if (typeof document === 'undefined') return;
  document.documentElement.lang = locale;
  document.documentElement.dir = getLocaleDirection(locale, isRtl);
}

export function getDisplayName(user?: StoredAuthUser | null): string {
  if (!user) return 'Leopardo RH';

  const fullName = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
  return fullName || user.name || user.email || 'Leopardo RH';
}

export function getApiErrorMessage(payload: unknown, fallback: string): string {
  if (!payload || typeof payload !== 'object') {
    return fallback;
  }

  const data = payload as Record<string, unknown>;

  if (typeof data.localized_message === 'string' && data.localized_message.trim() !== '') {
    return data.localized_message;
  }

  if (typeof data.message === 'string' && data.message.trim() !== '') {
    return data.message;
  }

  if (typeof data.error === 'string' && data.error.trim() !== '') {
    return data.error;
  }

  return fallback;
}
