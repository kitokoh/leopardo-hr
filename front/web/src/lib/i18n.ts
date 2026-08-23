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
  account_type?: string | null;
  personal_statuses?: string[] | null;
  personal_onboarding_completed?: boolean;
  job_search_preferences?: {
    resume_name?: string | null;
    resume_url?: string | null;
    resume_path?: string | null;
    [key: string]: unknown;
  } | null;
  manager_role?: string | null;
  capabilities?: Record<string, unknown> | null;
  // Features tenant (FeatureFlag::for) renvoyées au niveau racine par
  // /auth/me (EmployeeResource) : {rh, finance, cameras, muhasebe, leo_ai}.
  features?: Record<string, unknown> | null;
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

export type CopyTree = {
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
    accountCreatedFree: string;
    accountCreatedPaid: string;
    demoTitle: string;
    demoSubtitle: string;
    close: string;
    supportCopy: string;
    supportLink: string;
    errors: {
      generic: string;
      missingToken: string;
      missingUser: string;
      google: string;
      googleNetwork: string;
      googleAuthFailed: string;
      googleNoAccount: string;
    };
  };
  dashboard: {
    heading: string;
    employees: string;
    present: string;
    live: string;
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
    featureLockedRole: string;
    featureLockedPlan: string;
    featureLockedBadge: string;
    featureLockedExplanation: string;
    featureLockedAdminHint: string;
    featureLockedPlanRoleTitle: string;
    featureLockedPlanRoleBody: string;
    featureLockedCta: string;
    recent_activity: string;
    noNotifications: string;
    managePreferences: string;
  };
  passwordReset: {
    title: string;
    subtitle: string;
    emailLabel: string;
    emailPlaceholder: string;
    submit: string;
    submitting: string;
    successTitle: string;
    successBody: string;
    backToLogin: string;
    newPasswordLabel: string;
    newPasswordPlaceholder: string;
    confirmPasswordLabel: string;
    confirmPasswordPlaceholder: string;
    submitReset: string;
    submittingReset: string;
    resetSuccessTitle: string;
    resetSuccessBody: string;
    invalidEmail: string;
    invalidPassword: string;
    passwordMismatch: string;
    missingTokenTitle: string;
    missingTokenBody: string;
    genericError: string;
    showPassword: string;
    hidePassword: string;
  };
  accountActivation: {
    title: string;
    subtitle: string;
    passwordLabel: string;
    passwordPlaceholder: string;
    confirmPasswordLabel: string;
    confirmPasswordPlaceholder: string;
    submit: string;
    submitting: string;
    successTitle: string;
    successBody: string;
    backToLogin: string;
    invalidPassword: string;
    passwordMismatch: string;
    missingTokenTitle: string;
    missingTokenBody: string;
    alreadyAccepted: string;
    expired: string;
    genericError: string;
    showPassword: string;
    hidePassword: string;
  };
  onboarding: {
    stepBadge: string;
    next: string;
    finish: string;
    validating: string;
    skip: string;
    close: string;
    retry: string;
    errorGeneric: string;
    allStepsDone: string;
    quickStart: string;
    later: string;
    firstCheckinHint: string;
    qrShow: string;
    qrHide: string;
    qrHint: string;
    qrError: string;
    qrLoading: string;
    steps: Record<string, { title: string; desc: string }>;
  };
  absences: {
    title: string;
    approve: string;
    reject: string;
    cancel: string;
    rejectTitle: string;
    rejectBody: string;
    reasonLabel: string;
    reasonPlaceholder: string;
    reasonRequired: string;
    rejectConfirm: string;
    rejectInProgress: string;
    statusPending: string;
    statusApproved: string;
    statusRejected: string;
    statusCancelled: string;
    loadError: string;
    empty: string;
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
    columnCompliance: string;
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
    runDraft: string;
    runCalculated: string;
    runValidated: string;
    runLocked: string;
    runCalculate: string;
    runValidate: string;
    runLock: string;
    runUnlock: string;
    runConfirmLock: string;
    runConfirmUnlock: string;
    runActionError: string;
    runCancel: string;
    downloadPdf: string;
    viewDetail: string;
    resultsCount: string;
    detailTitle: string;
    detailClose: string;
    detailLoading: string;
    detailError: string;
    detailDeductions: string;
    detailEmployerContributions: string;
    detailTotalCost: string;
    detailWorkingDays: string;
    detailDaysWorked: string;
    detailOvertimeHours: string;
    detailSalaryBreakdown: string;
    salaryDecompTitle: string;
    salaryMonthly: string;
    salaryDailyRate: string;
    salaryHourlyRate: string;
    salaryCompositionDays: string;
    salaryCompositionHours: string;
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
  partnerPage: {
    loading: string;
    applyErrorPrefix: string;
    notApplied: {
      title: string;
      subtitle: string;
      individual: string;
      agency: string;
    };
    pending: {
      title: string;
      body: string;
    };
    dashboard: {
      title: string;
      subtitle: string;
    };
    metrics: {
      conversions: string;
      totalEarned: string;
      pending: string;
      withdrawable: string;
    };
    commissions: {
      title: string;
      empty: string;
    };
    table: {
      tenantId: string;
      date: string;
      status: string;
      amount: string;
      statusPaid: string;
      statusPending: string;
    };
    payout: {
      title: string;
      body: string;
      request: string;
      sending: string;
      insufficient: string;
      success: string;
      errorPrefix: string;
    };
    referral: {
      title: string;
      unavailable: string;
      copy: string;
      copied: string;
      copyError: string;
    };
  };
  offlinePage: {
    title: string;
    body: string;
    edgeModeTitle: string;
    edgeModeBody: string;
    retry: string;
  };
  // #4574 Lot 2 — portail client : sections restantes localisées ×4.
  billing: {
    title: string;
    subtitle: string;
    statusActive: string;
    statusCancelled: string;
    statusPastDue: string;
    statusPaid: string;
    statusPending: string;
    cancelConfirm: string;
    cancelError: string;
    renewError: string;
    noPaymentAccount: string;
    downloadError: string;
    noActivePeriod: string;
    periodRange: string;
    cancelLabel: string;
    loadError: string;
  };
  contracts: {
    title: string;
    subtitle: string;
    statusAll: string;
    statusActive: string;
    statusSuspended: string;
    statusActives: string;
    statusSuspendeds: string;
    statusTerminated: string;
    statusDraft: string;
  };
  absencesPage: {
    loadError: string;
    approve: string;
    reject: string;
    rejectTitle: string;
    rejectReasonPlaceholder: string;
    reasonRequired: string;
    cancel: string;
    confirmReject: string;
    approveSuccess: string;
    rejectSuccess: string;
    actionError: string;
  };
  attendancePage: {
    loadError: string;
  };
  socialPage: {
    title: string;
    subtitle: string;
    loadError: string;
    createError: string;
  };
  socialMarketingPage: {
    title: string;
    subtitle: string;
    loadAccountError: string;
    loadPostsError: string;
    connectError: string;
    disconnectError: string;
    createError: string;
    publishError: string;
    deleteError: string;
    statusActive: string;
  };
  trainingPage: {
    loadError: string;
    createError: string;
  };
  notificationsPage: {
    statusEnabled: string;
    statusDisabled: string;
  };
};

const copy: Record<AppLocale, CopyTree> = {
  fr: {
    login: {
      title: 'Connexion a Leopardo RH',
      subtitle: 'Accédez à votre espace RH, suivez vos équipes et pilotez les modules actifs de votre entreprise.',
      clientSpace: 'Espace client',
      heroTitle: 'Un acces RH clair pour chaque manager, chaque pays et chaque équipe.',
      heroCopy: 'Votre portail client reste connecte a l API Leopardo RH, avec permissions, langue et contexte tenant appliques des la connexion.',
      secureBadge: 'Connexion sécurisée',
      trustPoints: [
        'Session liee a votre tenant',
        'Permissions appliquees par role',
        'Interface prete pour manager, RH et employé',
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
    accountCreatedFree: 'Compte créé ! Connectez-vous pour accéder à votre espace gratuit.',
    accountCreatedPaid: 'Inscription reçue ! Connectez-vous pour continuer.',
      demoTitle: 'Choisir un compte démo',
      demoSubtitle: 'Selectionnez un role pour pre-remplir le formulaire, puis lancez la connexion.',
      close: 'Fermer',
      supportCopy: 'Besoin d aide pour recuperer un accès ?',
      supportLink: 'Contacter le support',
      errors: {
        generic: 'Une erreur est survenue.',
        missingToken: 'Le jeton de connexion est absent de la réponse API.',
        missingUser: 'Le profil utilisateur est absent de la réponse API.',
        // Issue #5173 — erreurs Google propagées par le callback vitrine
        // (`/auth/login?error=...`). Afficher un message clair au lieu d'un
        // formulaire muet après un échec OAuth.
        google: 'La connexion avec Google a échoué. Veuillez réessayer.',
        googleNetwork: 'Impossible de contacter Google. Vérifiez votre connexion et réessayez.',
        googleAuthFailed: 'Google a refusé la connexion. Veuillez réessayer.',
        googleNoAccount: 'Aucun compte Leopardo RH n’est associé à cet email Google. Demandez une invitation à votre administrateur.',
      },
    },
    dashboard: {
      heading: 'Tableau de bord',
      employees: 'Employés actifs',
      present: 'Présents',
      live: 'En direct',
      late: 'Retards',
      activity: 'Activité récente',
      team: 'Employés',
      attendance: 'Pointages',
      payroll: 'Paie',
      settings: 'Paramètres',
      logout: 'Déconnexion',
      language: 'Langue',
      presentBadge: 'Présent',
      employeeLabel: 'Employé',
      checkInAt: 'Check-in à',
      featureLockedRole: "Votre rôle actuel ne permet pas d'accéder à ce module.",
      featureLockedPlan: "Ce module n'est pas inclus dans votre plan actuel.",
      featureLockedBadge: 'Module non inclus',
      featureLockedExplanation: "Leopardo RH garde l'interface explicite afin d'éviter les 404 confuses et les erreurs API inutiles.",
      featureLockedAdminHint: "Demandez l'activation au super administrateur de la plateforme ou passez sur un plan incluant ce module.",
      featureLockedPlanRoleTitle: 'Plan & rôle',
      featureLockedPlanRoleBody: "Les modules visibles dans cet espace sont calculés depuis les droits, le plan de l'entreprise et le rôle utilisateur.",
      featureLockedCta: "Demander l'activation",
      recent_activity: 'Activité récente',
      noNotifications: 'Aucune notification récente.',
      managePreferences: 'Gérer mes préférences',
    },
    passwordReset: {
      title: 'Mot de passe oublié',
      subtitle: "Saisissez l'adresse email de votre compte : nous vous enverrons un lien de réinitialisation sécurisé.",
      emailLabel: 'Adresse email',
      emailPlaceholder: 'vous@entreprise.com',
      submit: 'Envoyer le lien',
      submitting: 'Envoi...',
      successTitle: 'Email envoyé',
      successBody: "Si un compte existe avec cette adresse, un lien de réinitialisation vient d'être envoyé. Pensez à vérifier vos spams.",
      backToLogin: 'Retour à la connexion',
      newPasswordLabel: 'Nouveau mot de passe',
      newPasswordPlaceholder: '8 caractères minimum',
      confirmPasswordLabel: 'Confirmer le mot de passe',
      confirmPasswordPlaceholder: 'Répétez le mot de passe',
      submitReset: 'Réinitialiser le mot de passe',
      submittingReset: 'Réinitialisation...',
      resetSuccessTitle: 'Mot de passe réinitialisé',
      resetSuccessBody: 'Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.',
      invalidEmail: 'Adresse email invalide.',
      invalidPassword: 'Le mot de passe doit contenir au moins 8 caractères.',
      passwordMismatch: 'Les deux mots de passe ne correspondent pas.',
      missingTokenTitle: 'Lien invalide ou expiré',
      missingTokenBody: 'Le lien de réinitialisation est invalide ou a expiré. Relancez une demande de réinitialisation.',
      genericError: 'Une erreur est survenue. Réessayez dans quelques instants.',
      showPassword: 'Afficher le mot de passe',
      hidePassword: 'Masquer le mot de passe',
    },
    accountActivation: {
      title: 'Activez votre compte',
      subtitle: 'Définissez un mot de passe pour activer votre compte Leopardo RH.',
      passwordLabel: 'Mot de passe',
      passwordPlaceholder: '8 caractères minimum',
      confirmPasswordLabel: 'Confirmer le mot de passe',
      confirmPasswordPlaceholder: 'Répétez le mot de passe',
      submit: 'Activer mon compte',
      submitting: 'Activation…',
      successTitle: 'Compte activé !',
      successBody: 'Votre compte est actif. Vous pouvez maintenant vous connecter.',
      backToLogin: 'Aller à la connexion',
      invalidPassword: 'Le mot de passe doit contenir au moins 8 caractères.',
      passwordMismatch: 'Les deux mots de passe ne correspondent pas.',
      missingTokenTitle: 'Lien invalide ou expiré',
      missingTokenBody: 'Ce lien d\'activation est invalide ou a expiré. Contactez votre manager pour recevoir une nouvelle invitation.',
      alreadyAccepted: 'Ce lien a déjà été utilisé. Connectez-vous directement.',
      expired: 'Ce lien a expiré. Demandez une nouvelle invitation.',
      genericError: 'Une erreur est survenue. Réessayez dans quelques instants.',
      showPassword: 'Afficher le mot de passe',
      hidePassword: 'Masquer le mot de passe',
    },
    onboarding: {
      stepBadge: 'Étape {current} sur {total}',
      next: 'Suivant',
      finish: 'Terminer',
      validating: 'Validation...',
      skip: 'Passer cette étape',
      close: "Fermer l'assistant de configuration",
      retry: 'Réessayer',
      errorGeneric: 'Impossible de charger les étapes de configuration.',
      allStepsDone: 'Configuration terminée !',
      quickStart: 'Quick Start',
      later: 'Recommandé plus tard',
      firstCheckinHint: "Effectuez un premier pointage depuis l'app mobile ou le kiosque, puis marquez cette étape comme terminée.",
      qrShow: "Afficher le QR de l'entreprise",
      qrHide: 'Masquer le QR',
      qrHint: "Scannez ce QR avec l'app mobile Leopardo pour rejoindre l'entreprise.",
      qrError: 'Impossible de charger le QR.',
      qrLoading: 'Chargement du QR...',
      steps: {
        add_employees: {
          title: 'Ajoutez vos équipes',
          desc: 'Créez ou importez vos employés (CSV) pour commencer à pointer.',
        },
        configure_payroll: {
          title: 'Configurez la paie',
          desc: 'Renseignez la structure salariale et les paramètres de paie de votre entreprise.',
        },
        setup_schedules: {
          title: 'Définissez les horaires',
          desc: 'Créez vos plannings et règles de présence.',
        },
        setup_geofence: {
          title: 'Configurez la géolocalisation',
          desc: 'Délimitez les zones de pointage autorisées (optionnel).',
        },
        setup_kiosk: {
          title: 'Activez le kiosque biométrique',
          desc: 'Branchez votre kiosque ZKTeco pour le pointage sur site (optionnel).',
        },
        first_checkin: {
          title: 'Premier pointage',
          desc: "Effectuez un premier pointage de test pour valider le dispositif.",
        },
      },
    },
    absences: {
      title: 'Absences',
      approve: 'Approuver',
      reject: 'Refuser',
      cancel: 'Annuler',
      rejectTitle: 'Refuser la demande',
      rejectBody: "Précisez le motif du refus (obligatoire). L'employé sera notifié.",
      reasonLabel: 'Motif du refus',
      reasonPlaceholder: 'Motif obligatoire…',
      reasonRequired: 'Le motif est obligatoire pour refuser.',
      rejectConfirm: 'Confirmer le refus',
      rejectInProgress: 'Refus en cours...',
      statusPending: 'En attente',
      statusApproved: 'Approuvée',
      statusRejected: 'Refusée',
      statusCancelled: 'Annulée',
      loadError: 'Impossible de charger les absences.',
      empty: 'Aucune absence.',
    },
    payrollPage: {
      title: 'Paie',
      subtitle: "Bulletins de paie et cycles de paie, avec export PDF direct, connecte a l'API RH pour chaque tenant.",
      statTotalGross: 'Total Brut',
      statTotalNet: 'Total Net',
      statPayslips: 'Bulletins',
      tabSlips: 'Bulletins de paie',
      tabRuns: 'Cycles de paie',
      searchPlaceholder: 'Rechercher par nom ou période…',
      columnEmployee: 'Employé',
      columnPeriod: 'Période',
      columnGross: 'Brut',
      columnNet: 'Net',
      columnStatus: 'Statut',
      columnCompliance: 'Conformité',
      columnActions: 'Actions',
      columnEmployees: 'Employés',
      columnTotalGross: 'Total Brut',
      columnTotalNet: 'Total Net',
      loading: 'Chargement...',
      noPayslips: 'Aucun bulletin trouvé.',
      noRuns: 'Aucun cycle de paie.',
      statusValidated: 'Valide',
      statusDraft: 'Brouillon',
      statusCompleted: 'Termine',
      runDraft: 'Brouillon',
      runCalculated: 'Calculé',
      runValidated: 'Validé',
      runLocked: 'Verrouillé',
      runCalculate: 'Calculer',
      runValidate: 'Valider (RH)',
      runLock: 'Verrouiller',
      runUnlock: 'Déverrouiller',
      runConfirmLock: 'Verrouiller la clôture de ce cycle ?',
      runConfirmUnlock: 'Déverrouiller la clôture de ce cycle ?',
      runActionError: "Impossible d'exécuter l'action sur ce cycle de paie.",
      runCancel: 'Annuler',
      downloadPdf: 'Télécharger PDF',
      viewDetail: 'Voir detail',
      resultsCount: 'resultats',
      detailTitle: 'Detail du bulletin',
      detailClose: 'Fermer',
      detailLoading: 'Chargement du detail...',
      detailError: 'Detail indisponible pour le moment — affichage des données de la liste.',
      detailDeductions: 'Deductions',
      detailEmployerContributions: 'Charges patronales',
      detailTotalCost: 'Coût total employeur',
      detailWorkingDays: 'Jours ouvres',
      detailDaysWorked: 'Jours travailles',
      detailOvertimeHours: 'Heures supplementaires',
      detailSalaryBreakdown: 'Composition du salaire',
      salaryDecompTitle: 'Décomposition du salaire',
      salaryMonthly: 'Salaire mensuel',
      salaryDailyRate: 'Taux journalier',
      salaryHourlyRate: 'Taux horaire',
      salaryCompositionDays: 'Ce mois : {days} jours × {rate} = {total}',
      salaryCompositionHours: 'Ce mois : {hours} h × {rate} = {total}',
    },
    smartAttendancePage: {
      title: 'Smart Attendance',
      subtitle: 'Suivi intelligent de présence par geolocalisation — validation des sessions en attente et statistiques du jour.',
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
      employeeFallback: 'Employé',
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
      title: 'Sessions de présence',
      subtitle: 'Liste complete des sessions Smart Attendance avec filtres avances et pagination.',
      backToDashboard: '← Tableau de bord',
      loadError: 'Impossible de charger les sessions.',
      filtersTitle: 'Filtres',
      filterStatus: 'Statut',
      filterStatusAll: 'Tous les statuts',
      filterEmployee: 'Employé (ID ou nom)',
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
      noSessions: 'Aucune session trouvee pour ces critères.',
      viewDetail: 'Voir →',
      employeeFallback: 'Employe',
      pageLabel: 'Page',
      previous: '← Precedent',
      next: 'Suivant →',
      csvHeaderId: 'ID',
      csvHeaderEmployee: 'Employé',
      csvHeaderMatricule: 'Matricule',
      csvHeaderCheckIn: 'Arrivee',
      csvHeaderCheckOut: 'Depart',
      csvHeaderDuration: 'Duree (min)',
      csvHeaderStatus: 'Statut',
    },
    smartAttendanceSessionDetailPage: {
      title: 'Detail de session',
      subtitle: 'Informations completes de la session de présence geolocalisee.',
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
      gpsHistoryTitle: 'Historique des événements GPS',
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
    developerSettingsPage: {
      title: 'Espace Développeur',
      subtitle: 'Gerez vos cles API et vos webhooks pour integrer Leopardo RH a vos outils.',
      loadTokensError: 'Impossible de charger les cles API.',
      loadWebhooksError: 'Impossible de charger les webhooks.',
      createTokenError: 'Impossible de créer la clé API.',
      deleteTokenError: 'Impossible de revoquer la cle API.',
      createWebhookError: 'Impossible de créer le webhook.',
      deleteWebhookError: 'Impossible de supprimer le webhook.',
      updateWebhookError: 'Impossible de mettre à jour le webhook.',
      revokeTokenConfirm: "Revoquer cette cle API ? Les integrations qui l'utilisent cesseront de fonctionner.",
      deleteWebhookConfirm: 'Supprimer cet endpoint webhook ?',
      revealedTokenNotice: 'Cle "{name}" creee — copiez-la maintenant, elle ne sera plus jamais affichee :',
      revealedTokenDismiss: "J'ai copie la cle, masquer",
      apiKeysTitle: 'Cles API',
      loading: 'Chargement...',
      noTokens: 'Aucune cle API creee pour le moment.',
      createdOn: 'Creee le {date}',
      unknownDate: 'Date inconnue',
      lastUsedOn: ' · dernière utilisation le {date}',
      neverUsed: ' · jamais utilisee',
      revoke: 'Revoquer',
      tokenNamePlaceholder: 'Nom de la cle (ex: Production)',
      webhooksTitle: 'Webhooks',
      noWebhooks: 'Aucun endpoint webhook configuré.',
      eventsCount: '{count} evenement(s)',
      failuresCount: '{count} échec(s)',
      noFailures: 'aucun échec',
      active: 'Actif',
      inactive: 'Inactif',
      triggeredOn: 'Déclenché le {date}',
      neverTriggered: 'Jamais déclenché',
      delete: 'Supprimer',
      addEndpoint: 'Ajouter un endpoint',
      apiDocsTitle: 'Documentation API',
      apiDocsBody: 'Découvrez comment integrer nos webhooks signes (format Svix) et nos endpoints REST.',
      openExplorer: "Ouvrir l'Explorer",
      newWebhookModalTitle: 'Nouvel endpoint webhook',
      destinationUrlLabel: 'URL de destination',
      eventsToListenLabel: 'Événements a ecouter',
      cancel: 'Annuler',
      creating: 'Creation...',
      create: 'Créer',
    },
    partnerPage: {
      loading: 'Chargement de votre espace...',
      applyErrorPrefix: 'Erreur lors de la candidature : ',
      notApplied: {
        title: 'Devenir Partenaire',
        subtitle: "Rejoignez l'écosystème Leopardo RH et gagnez des commissions sur chaque entreprise que vous parrainez. Jusqu'à 20 % de commission récurrente.",
        individual: "Postuler en tant qu'Individuel",
        agency: "Postuler en tant qu'Agence",
      },
      pending: {
        title: 'Candidature en cours',
        body: "Votre demande est en cours de validation par notre équipe commerciale. Vous recevrez un email dès que votre accès sera activé.",
      },
      dashboard: {
        title: 'Dashboard Partenaire',
        subtitle: 'Suivez vos conversions et vos commissions Leopardo RH — statut partenaire actif.',
      },
      metrics: {
        conversions: 'Conversions',
        totalEarned: 'Gains totaux',
        pending: 'En attente',
        withdrawable: 'Solde retirable',
      },
      commissions: {
        title: 'Dernières commissions',
        empty: 'Aucune commission enregistrée.',
      },
      table: {
        tenantId: 'Tenant ID',
        date: 'Date',
        status: 'Statut',
        amount: 'Montant',
        statusPaid: 'Payée',
        statusPending: 'En attente',
      },
      payout: {
        title: 'Paiement',
        body: 'Vos commissions sont payées une fois le seuil atteint. Vérifiez que vos coordonnées bancaires sont à jour.',
        request: 'Demander un virement',
        sending: 'Envoi...',
        insufficient: 'Solde insuffisant pour demander un virement (minimum 100,00 €).',
        success: 'Demande de virement envoyée avec succès.',
        errorPrefix: 'Erreur lors de la demande de virement : ',
      },
      referral: {
        title: 'Lien de parrainage',
        unavailable: 'Lien indisponible',
        copy: 'Copier mon lien',
        copied: 'Copié !',
        copyError: 'Impossible de copier le lien. Copiez-le manuellement.',
      },
    },
    offlinePage: {
      title: 'Pas de connexion Internet',
      body: "Vous êtes actuellement hors ligne. Si un Edge node Leopardo est disponible sur votre reseau local, l'application continue de fonctionner normalement.",
      edgeModeTitle: 'Mode Edge actif ?',
      edgeModeBody: "Accédez à l'interface locale via :",
      retry: 'Reessayer',
    },

  billing: {
    title: 'Facturation',
    subtitle: 'Gérez votre abonnement, vos factures et vos informations de paiement.',
    statusActive: 'Actif',
    statusCancelled: 'Annulé',
    statusPastDue: 'Impayé',
    statusPaid: 'Payée',
    statusPending: 'En attente',
    cancelConfirm: 'Annuler votre abonnement ? Vous perdrez l\'accès aux modules premium à la fin de la période en cours.',
    cancelError: 'Impossible d\'annuler l\'abonnement.',
    renewError: 'Impossible de réactiver l\'abonnement.',
    noPaymentAccount: 'Aucun compte de paiement associé. Souscrivez d\'abord à un plan.',
    downloadError: 'Impossible de télécharger la facture.',
    noActivePeriod: 'Aucune période active',
    periodRange: 'Période : {start} au {end}',
    cancelLabel: 'Annuler l\'abonnement',
    loadError: 'Impossible de charger les informations de facturation.',
  },
  contracts: {
    title: 'Contrats',
    subtitle: 'Gestion des contrats de votre équipe',
    statusAll: 'Tous les statuts',
    statusActive: 'Actif',
    statusSuspended: 'Suspendu',
    statusActives: 'Actifs',
    statusSuspendeds: 'Suspendus',
    statusTerminated: 'Terminé',
    statusDraft: 'Brouillon',
  },
  absencesPage: {
    loadError: 'Impossible de charger les absences.',
    approve: 'Approuver',
    reject: 'Refuser',
    rejectTitle: 'Refuser la demande',
    rejectReasonPlaceholder: 'Motif du refus (obligatoire)',
    reasonRequired: 'Le motif du refus est obligatoire.',
    cancel: 'Annuler',
    confirmReject: 'Confirmer le refus',
    approveSuccess: 'Demande approuvée.',
    rejectSuccess: 'Demande refusée.',
    actionError: "Impossible d'effectuer l'action.",
  },
  attendancePage: { loadError: 'Impossible de charger le pointage.' },
  socialPage: {
    title: 'Réseaux sociaux',
    subtitle: 'Publiez et planifiez vos contenus',
    loadError: 'Impossible de charger les publications.',
    createError: 'Impossible de créer la publication.',
  },
  socialMarketingPage: {
    title: 'Marketing',
    subtitle: 'Connectez votre compte social et gérez vos publications',
    loadAccountError: 'Impossible de charger le compte social.',
    loadPostsError: 'Impossible de charger les publications.',
    connectError: 'Impossible de connecter le compte social.',
    disconnectError: 'Impossible de déconnecter le compte social.',
    createError: 'Impossible de créer la publication.',
    publishError: 'Impossible de publier la publication.',
    deleteError: 'Impossible de supprimer la publication.',
    statusActive: 'Actif',
  },
  trainingPage: {
    loadError: 'Impossible de charger les formations.',
    createError: 'Impossible de créer la formation.',
  },
  notificationsPage: {
    statusEnabled: 'Activé',
    statusDisabled: 'Désactivé',
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
    accountCreatedFree: 'تم إنشاء الحساب! سجّل الدخول للوصول إلى مساحتك المجانية.',
    accountCreatedPaid: 'تم استلام التسجيل! سجّل الدخول للمتابعة.',
      demoTitle: 'اختيار حساب تجريبي',
      demoSubtitle: 'اختر دورا لملء النموذج ثم سجل الدخول.',
      close: 'إغلاق',
      supportCopy: 'تحتاج مساعدة لاسترجاع الدخول؟',
      supportLink: 'اتصل بالدعم',
      errors: {
        generic: 'حدث خطأ.',
        missingToken: 'رمز تسجيل الدخول غير موجود في رد ال API.',
        missingUser: 'ملف المستخدم غير موجود في رد ال API.',
        google: 'فشل تسجيل الدخول عبر Google. حاول مرة أخرى.',
        googleNetwork: 'تعذر الوصول إلى Google. تحقق من اتصالك وحاول مرة أخرى.',
        googleAuthFailed: 'رفض Google تسجيل الدخول. حاول مرة أخرى.',
        googleNoAccount: 'لا يوجد حساب Leopardo RH مرتبط ببريد Google هذا. اطلب دعوة من المسؤول.',
      },
    },
    dashboard: {
      heading: 'لوحة التحكم',
      employees: 'الموظفون النشطون',
      present: 'حاضرون',
      live: 'مباشر',
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
      featureLockedRole: 'دورك الحالي لا يسمح بالوصول إلى هذه الوحدة.',
      featureLockedPlan: 'هذه الوحدة غير مشمولة في خطتك الحالية.',
      featureLockedBadge: 'الوحدة غير مشمولة',
      featureLockedExplanation: 'يحافظ Leopardo RH على واجهة واضحة لتجنب أخطاء 404 المربكة وأخطاء API غير الضرورية.',
      featureLockedAdminHint: 'اطلب تفعيل الوحدة من مدير المنصة أو انتقل إلى خطة تتضمن هذه الوحدة.',
      featureLockedPlanRoleTitle: 'الخطة والدور',
      featureLockedPlanRoleBody: 'تُحسب الوحدات الظاهرة في هذه المساحة بناءً على الصلاحيات وخطة الشركة ودور المستخدم.',
      featureLockedCta: 'طلب التفعيل',
      recent_activity: 'النشاط الأخير',
      noNotifications: 'لا توجد إشعارات حديثة.',
      managePreferences: 'إدارة تفضيلاتي',
    },
    passwordReset: {
      title: 'نسيت كلمة المرور؟',
      subtitle: 'أدخل البريد الإلكتروني لحسابك وسنرسل لك رابط إعادة تعيين آمنًا.',
      emailLabel: 'البريد الإلكتروني',
      emailPlaceholder: 'you@company.com',
      submit: 'إرسال الرابط',
      submitting: 'جارٍ الإرسال...',
      successTitle: 'تم إرسال البريد الإلكتروني',
      successBody: 'إذا كان هناك حساب بهذا العنوان، فقد تم إرسال رابط إعادة التعيين للتو. لا تنسَ التحقق من مجلد الرسائل غير المرغوب فيها.',
      backToLogin: 'العودة إلى تسجيل الدخول',
      newPasswordLabel: 'كلمة المرور الجديدة',
      newPasswordPlaceholder: '8 أحرف على الأقل',
      confirmPasswordLabel: 'تأكيد كلمة المرور',
      confirmPasswordPlaceholder: 'أعد إدخال كلمة المرور',
      submitReset: 'إعادة تعيين كلمة المرور',
      submittingReset: 'جارٍ إعادة التعيين...',
      resetSuccessTitle: 'تمت إعادة تعيين كلمة المرور',
      resetSuccessBody: 'يمكنك الآن تسجيل الدخول بكلمة المرور الجديدة.',
      invalidEmail: 'عنوان بريد إلكتروني غير صالح.',
      invalidPassword: 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.',
      passwordMismatch: 'كلمتا المرور غير متطابقتين.',
      missingTokenTitle: 'رابط غير صالح أو منتهي الصلاحية',
      missingTokenBody: 'رابط إعادة التعيين هذا غير صالح أو انتهت صلاحيته. يرجى طلب رابط جديد.',
      genericError: 'حدث خطأ ما. يرجى المحاولة مرة أخرى بعد قليل.',
      showPassword: 'إظهار كلمة المرور',
      hidePassword: 'إخفاء كلمة المرور',
    },
    accountActivation: {
      title: 'Activate your account',
      subtitle: 'Set a password to activate your Leopardo RH account.',
      passwordLabel: 'Password',
      passwordPlaceholder: '8 characters minimum',
      confirmPasswordLabel: 'Confirm password',
      confirmPasswordPlaceholder: 'Repeat your password',
      submit: 'Activate my account',
      submitting: 'Activating…',
      successTitle: 'Account activated!',
      successBody: 'Your account is now active. You can sign in.',
      backToLogin: 'Go to sign in',
      invalidPassword: 'Password must be at least 8 characters.',
      passwordMismatch: 'The two passwords do not match.',
      missingTokenTitle: 'Invalid or expired link',
      missingTokenBody: 'This activation link is invalid or has expired. Contact your manager to get a new invitation.',
      alreadyAccepted: 'This link has already been used. Sign in directly.',
      expired: 'This link has expired. Request a new invitation.',
      genericError: 'Something went wrong. Please try again in a moment.',
      showPassword: 'Show password',
      hidePassword: 'Hide password',
    },
    onboarding: {
      stepBadge: 'الخطوة {current} من {total}',
      next: 'التالي',
      finish: 'إنهاء',
      validating: 'جارٍ التحقق...',
      skip: 'تخطي هذه الخطوة',
      close: 'إغلاق مساعد الإعداد',
      retry: 'إعادة المحاولة',
      errorGeneric: 'تعذر تحميل خطوات الإعداد.',
      allStepsDone: 'اكتمل الإعداد!',
      quickStart: 'بداية سريعة',
      later: 'موصى به لاحقًا',
      firstCheckinHint: 'قم بأول تسجيل حضور من تطبيق الجوال أو الكشك، ثم ضع علامة على هذه الخطوة كمكتملة.',
      qrShow: 'إظهار رمز QR للشركة',
      qrHide: 'إخفاء رمز QR',
      qrHint: 'امسح رمز QR هذا بتطبيق Leopardo للجوال للانضمام إلى الشركة.',
      qrError: 'تعذر تحميل رمز QR.',
      qrLoading: 'جارٍ تحميل رمز QR...',
      steps: {
        add_employees: {
          title: 'أضف فرقك',
          desc: 'أنشئ موظفيك أو استوردهم (CSV) لبدء تسجيل الحضور.',
        },
        configure_payroll: {
          title: 'اضبط إعدادات الرواتب',
          desc: 'أدخل هيكل الرواتب وإعدادات الدفع لشركتك.',
        },
        setup_schedules: {
          title: 'حدد الجداول الزمنية',
          desc: 'أنشئ جداولك وقواعد الحضور.',
        },
        setup_geofence: {
          title: 'اضبط تحديد المواقع',
          desc: 'حدد مناطق تسجيل الحضور المسموحة (اختياري).',
        },
        setup_kiosk: {
          title: 'فعّل كشك القياسات الحيوية',
          desc: 'اربط كشك ZKTeco لتسجيل الحضور في الموقع (اختياري).',
        },
        first_checkin: {
          title: 'أول تسجيل حضور',
          desc: 'قم بتسجيل حضور تجريبي للتحقق من الإعداد.',
        },
      },
    },
    absences: {
      title: 'الإجازات',
      approve: 'موافقة',
      reject: 'رفض',
      cancel: 'إلغاء',
      rejectTitle: 'رفض الطلب',
      rejectBody: 'يرجى تحديد سبب الرفض (إلزامي). سيتم إشعار الموظف.',
      reasonLabel: 'سبب الرفض',
      reasonPlaceholder: 'السبب إلزامي…',
      reasonRequired: 'السبب إلزامي للرفض.',
      rejectConfirm: 'تأكيد الرفض',
      rejectInProgress: 'جارٍ الرفض...',
      statusPending: 'قيد الانتظار',
      statusApproved: 'تمت الموافقة',
      statusRejected: 'مرفوض',
      statusCancelled: 'ملغى',
      loadError: 'تعذر تحميل الإجازات.',
      empty: 'لا توجد إجازات.',
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
      columnCompliance: 'المطابقة',
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
      runDraft: 'مسودة',
      runCalculated: 'تم الحساب',
      runValidated: 'تم الاعتماد',
      runLocked: 'مقفل',
      runCalculate: 'احسب',
      runValidate: 'اعتماد (موارد بشرية)',
      runLock: 'قفل',
      runUnlock: 'فتح القفل',
      runConfirmLock: 'قفل إقفال دورة الرواتب هذه؟',
      runConfirmUnlock: 'فتح إقفال دورة الرواتب هذه؟',
      runActionError: 'تعذر تنفيذ الإجراء على دورة الرواتب.',
      runCancel: 'إلغاء',
      downloadPdf: 'تحميل PDF',
      viewDetail: 'عرض التفاصيل',
      resultsCount: 'نتائج',
      detailTitle: 'تفاصيل كشف الراتب',
      detailClose: 'إغلاق',
      detailLoading: 'جار تحميل التفاصيل...',
      detailError: 'التفاصيل غير متوفرة حاليا — عرض بيانات القائمة.',
      detailDeductions: 'الخصومات',
      detailEmployerContributions: 'اشتراكات صاحب العمل',
      detailTotalCost: 'التكلفة الإجمالية لصاحب العمل',
      detailWorkingDays: 'أيام العمل',
      detailDaysWorked: 'أيام العمل الفعلية',
      detailOvertimeHours: 'ساعات العمل الإضافي',
      detailSalaryBreakdown: 'تفاصيل الراتب',
      salaryDecompTitle: 'تفاصيل الراتب',
      salaryMonthly: 'الراتب الشهري',
      salaryDailyRate: 'الأجر اليومي',
      salaryHourlyRate: 'الأجر بالساعة',
      salaryCompositionDays: 'هذا الشهر: {days} يومًا × {rate} = {total}',
      salaryCompositionHours: 'هذا الشهر: {hours} ساعة × {rate} = {total}',
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
      subtitle: 'إعداد وضع التسجيل والنطاق الجغرافي للشركة.',
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
      geofenceConfigTitle: 'إعداد النطاق الجغرافي',
      latitudeLabel: 'خط العرض',
      longitudeLabel: 'خط الطول',
      radiusFieldLabel: 'النطاق (متر)',
      radiusHint: 'المسافة القصوى المسموح بها من مكان العمل.',
      save: 'حفظ',
      saving: 'جار التنفيذ…',
      cancel: 'إلغاء',
    },
    developerSettingsPage: {
      title: 'إعدادات المطور',
      subtitle: 'أدر مفاتيح API والويب هوكس لدمج Leopardo RH مع أدواتك.',
      loadTokensError: 'تعذر تحميل مفاتيح API.',
      loadWebhooksError: 'تعذر تحميل الردود.',
      createTokenError: 'تعذر إنشاء مفتاح API.',
      deleteTokenError: 'تعذر إلغاء مفتاح API.',
      createWebhookError: 'تعذر إنشاء الويب هوك.',
      deleteWebhookError: 'تعذر حذف الويب هوك.',
      updateWebhookError: 'تعذر تحديث الويب هوك.',
      revokeTokenConfirm: 'هل تريد إلغاء مفتاح API هذا؟ ستوقف التكاملات المستخدمة له عن العمل.',
      deleteWebhookConfirm: 'حذف نقطة الويب هوك هذه؟',
      revealedTokenNotice: 'مفتاح "{name}" تم إنشاؤه — انسخه الآن، لن يعرض مرة أخرى:',
      revealedTokenDismiss: 'لقد نسخت المفتاح، إخفاء',
      apiKeysTitle: 'مفاتيح API',
      loading: 'تحميل...',
      noTokens: 'لم يتم إنشاء أي مفتاح API حتى الآن.',
      createdOn: 'أنشئ في {date}',
      unknownDate: 'تاريخ مفقود',
      lastUsedOn: ' · آخر استعمال في {date}',
      neverUsed: ' · لم يستخدم قط',
      revoke: 'إلغاء',
      tokenNamePlaceholder: 'اسم المفتاح (مثلاً: الإنتاج)',
      webhooksTitle: 'الويب هوكس',
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
    partnerPage: {
      loading: 'جارٍ تحميل مساحتك...',
      applyErrorPrefix: 'خطأ أثناء التقديم: ',
      notApplied: {
        title: 'كن شريكاً',
        subtitle: 'انضم إلى منظومة Leopardo RH واربح عمولات عن كل شركة تحيلها. عمولة متكررة تصل إلى 20%.',
        individual: 'التقديم كفرد',
        agency: 'التقديم كوكالة',
      },
      pending: {
        title: 'الطلب قيد المراجعة',
        body: 'يتم حالياً مراجعة طلبك من قبل فريق المبيعات. ستتلقى بريداً إلكترونياً فور تفعيل وصولك.',
      },
      dashboard: {
        title: 'لوحة تحكم الشريك',
        subtitle: 'تابع تحويلاتك وعمولاتك في Leopardo RH — حالة شريك نشط.',
      },
      metrics: {
        conversions: 'التحويلات',
        totalEarned: 'إجمالي الأرباح',
        pending: 'قيد الانتظار',
        withdrawable: 'الرصيد القابل للسحب',
      },
      commissions: {
        title: 'أحدث العمولات',
        empty: 'لا توجد عمولات مسجلة.',
      },
      table: {
        tenantId: 'معرّف المستأجر',
        date: 'التاريخ',
        status: 'الحالة',
        amount: 'المبلغ',
        statusPaid: 'مدفوعة',
        statusPending: 'قيد الانتظار',
      },
      payout: {
        title: 'الدفع',
        body: 'تُدفع عمولاتك بمجرد بلوغ الحد الأدنى. تأكد من أن بياناتك البنكية محدّثة.',
        request: 'طلب تحويل',
        sending: 'جارٍ الإرسال...',
        insufficient: 'الرصيد غير كافٍ لطلب تحويل (الحد الأدنى 100,00 €).',
        success: 'تم إرسال طلب التحويل بنجاح.',
        errorPrefix: 'خطأ أثناء طلب التحويل: ',
      },
      referral: {
        title: 'رابط الإحالة',
        unavailable: 'الرابط غير متاح',
        copy: 'نسخ رابطه',
        copied: 'تم النسخ!',
        copyError: 'تعذر نسخ الرابط. انسخه يدوياً.',
      },
    },
    offlinePage: {
      title: 'لا يوجد اتصال بالإنترنت',
      body: 'أنت غير متصل حاليا بالإنترنت. في حال توفر عقدة Leopardo Edge على شبكتك المحلية، يستمر التطبيق في العمل بشكل طبيعي.',
      edgeModeTitle: 'وضع Edge نشط؟',
      edgeModeBody: 'ادخل إلى الواجهة المحلية عبر:',
      retry: 'إعادة المحاولة',
    },

  billing: {
    title: 'الفواتير',
    subtitle: 'إدارة اشتراكك وفواتيرك ومعلومات الدفع.',
    statusActive: 'نشط',
    statusCancelled: 'ملغى',
    statusPastDue: 'متأخر',
    statusPaid: 'مدفوع',
    statusPending: 'قيد الانتظار',
    cancelConfirm: 'إلغاء اشتراكك؟ ستفقد الوصول إلى الوحدات المميزة في نهاية الفترة الحالية.',
    cancelError: 'تعذر إلغاء الاشتراك.',
    renewError: 'تعذر إعادة تفعيل الاشتراك.',
    noPaymentAccount: 'لا يوجد حساب دفع مرتبط. اشترك في خطة أولاً.',
    downloadError: 'تعذر تنزيل الفاتورة.',
    noActivePeriod: 'لا توجد فترة نشطة',
    periodRange: 'الفترة: {start} إلى {end}',
    cancelLabel: 'إلغاء الاشتراك',
    loadError: 'تعذر تحميل معلومات الفوترة.',
  },
  contracts: {
    title: 'العقود',
    subtitle: 'إدارة عقود فريقك',
    statusAll: 'كل الحالات',
    statusActive: 'نشط',
    statusSuspended: 'موقوف',
    statusActives: 'نشطة',
    statusSuspendeds: 'موقوفة',
    statusTerminated: 'منتهي',
    statusDraft: 'مسودة',
  },
  absencesPage: {
    loadError: 'تعذر تحميل حالات الغياب.',
    approve: 'موافقة',
    reject: 'رفض',
    rejectTitle: 'رفض الطلب',
    rejectReasonPlaceholder: 'سبب الرفض (إلزامي)',
    reasonRequired: 'سبب الرفض إلزامي.',
    cancel: 'إلغاء',
    confirmReject: 'تأكيد الرفض',
    approveSuccess: 'تمت الموافقة على الطلب.',
    rejectSuccess: 'تم رفض الطلب.',
    actionError: 'تعذر تنفيذ الإجراء.',
  },
  attendancePage: { loadError: 'تعذر تحميل الحضور.' },
  socialPage: {
    title: 'وسائل التواصل',
    subtitle: 'انشر محتواك وجدوله',
    loadError: 'تعذر تحميل المنشورات.',
    createError: 'تعذر إنشاء المنشور.',
  },
  socialMarketingPage: {
    title: 'التسويق',
    subtitle: 'اربط حسابك الاجتماعي وأدر منشوراتك',
    loadAccountError: 'تعذر تحميل الحساب الاجتماعي.',
    loadPostsError: 'تعذر تحميل المنشورات.',
    connectError: 'تعذر ربط الحساب الاجتماعي.',
    disconnectError: 'تعذر فصل الحساب الاجتماعي.',
    createError: 'تعذر إنشاء المنشور.',
    publishError: 'تعذر نشر المنشور.',
    deleteError: 'تعذر حذف المنشور.',
    statusActive: 'نشط',
  },
  trainingPage: {
    loadError: 'تعذر تحميل التدريبات.',
    createError: 'تعذر إنشاء التدريب.',
  },
  notificationsPage: {
    statusEnabled: 'مفعل',
    statusDisabled: 'معطل',
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
    accountCreatedFree: 'Hesap oluşturuldu! Ücretsiz alanınıza erişmek için giriş yapın.',
    accountCreatedPaid: 'Kayıt alındı! Devam etmek için giriş yapın.',
      demoTitle: 'Demo hesabi sec',
      demoSubtitle: 'Formu doldurmak icin bir rol secin, sonra girisi baslatin.',
      close: 'Kapat',
      supportCopy: 'Erisim kurtarma icin yardim mi gerekiyor?',
      supportLink: 'Destekle iletisime gec',
      errors: {
        generic: 'Bir hata olustu.',
        missingToken: 'API yanitinda giris tokeni yok.',
        missingUser: 'API yanitinda kullanici profili yok.',
        google: 'Google ile giris basarisiz oldu. Lutfen tekrar deneyin.',
        googleNetwork: 'Google ile baglanti kurulamadi. Baglantinizi kontrol edip tekrar deneyin.',
        googleAuthFailed: 'Google girisini reddetti. Lutfen tekrar deneyin.',
        googleNoAccount: 'Bu Google e-postasiyla iliskili Leopardo RH hesabi yok. Yoneticinizden davet isteyin.',
      },
    },
    dashboard: {
      heading: 'Kontrol paneli',
      employees: 'Aktif calisanlar',
      present: 'mevcut',
      live: 'Canlı',
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
      featureLockedRole: "Mevcut rolunuz bu module erisim izni vermiyor.",
      featureLockedPlan: "Bu modul mevcut planiniza dahil degil.",
      featureLockedBadge: 'Modul dahil degil',
      featureLockedExplanation: "Leopardo RH, kafa karistiran 404'leri ve gereksiz API hatalarini onlemek icin arayuzu acik tutar.",
      featureLockedAdminHint: 'Aktivasyonu platform super yoneticisinden isteyin veya bu modulu iceren bir plana gecin.',
      featureLockedPlanRoleTitle: 'Plan ve rol',
      featureLockedPlanRoleBody: 'Bu alanda gorunen moduller, haklara, sirket planina ve kullanici rolune gore hesaplanir.',
      featureLockedCta: 'Aktivasyon iste',
      recent_activity: 'Son etkinlik',
      noNotifications: 'Yeni bildirim yok.',
      managePreferences: 'Tercihlerimi yönet',
    },
    passwordReset: {
      title: 'Şifrenizi mi unuttunuz?',
      subtitle: 'Hesabınızın e-posta adresini girin, size güvenli bir sıfırlama bağlantısı gönderelim.',
      emailLabel: 'E-posta adresi',
      emailPlaceholder: 'siz@sirket.com',
      submit: 'Bağlantıyı gönder',
      submitting: 'Gönderiliyor...',
      successTitle: 'E-posta gönderildi',
      successBody: 'Bu adresle bir hesap varsa, az önce bir sıfırlama bağlantısı gönderildi. Spam klasörünüzü kontrol etmeyi unutmayın.',
      backToLogin: 'Girişe dön',
      newPasswordLabel: 'Yeni şifre',
      newPasswordPlaceholder: 'En az 8 karakter',
      confirmPasswordLabel: 'Şifreyi onayla',
      confirmPasswordPlaceholder: 'Şifrenizi tekrar girin',
      submitReset: 'Şifreyi sıfırla',
      submittingReset: 'Sıfırlanıyor...',
      resetSuccessTitle: 'Şifre sıfırlandı',
      resetSuccessBody: 'Artık yeni şifrenizle giriş yapabilirsiniz.',
      invalidEmail: 'Geçersiz e-posta adresi.',
      invalidPassword: 'Şifre en az 8 karakter olmalıdır.',
      passwordMismatch: 'İki şifre birbiriyle eşleşmiyor.',
      missingTokenTitle: 'Geçersiz veya süresi dolmuş bağlantı',
      missingTokenBody: 'Bu sıfırlama bağlantısı geçersiz veya süresi dolmuş. Lütfen yeni bir tane isteyin.',
      genericError: 'Bir hata oluştu. Lütfen birkaç dakika sonra tekrar deneyin.',
      showPassword: 'Şifreyi göster',
      hidePassword: 'Şifreyi gizle',
    },
    accountActivation: {
      title: 'تفعيل حسابك',
      subtitle: 'حدد كلمة مرور لتفعيل حسابك في ليوباردو RH.',
      passwordLabel: 'كلمة المرور',
      passwordPlaceholder: '8 أحرف على الأقل',
      confirmPasswordLabel: 'تأكيد كلمة المرور',
      confirmPasswordPlaceholder: 'أعد إدخال كلمة المرور',
      submit: 'تفعيل حسابي',
      submitting: 'جارٍ التفعيل…',
      successTitle: 'تم تفعيل الحساب!',
      successBody: 'حسابك أصبح نشطًا. يمكنك الآن تسجيل الدخول.',
      backToLogin: 'الانتقال إلى تسجيل الدخول',
      invalidPassword: 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.',
      passwordMismatch: 'كلمتا المرور غير متطابقتين.',
      missingTokenTitle: 'رابط غير صالح أو منتهي الصلاحية',
      missingTokenBody: 'رابط التفعيل هذا غير صالح أو انتهت صلاحيته. تواصل مع مديرك للحصول على دعوة جديدة.',
      alreadyAccepted: 'تم استخدام هذا الرابط بالفعل. سجّل الدخول مباشرة.',
      expired: 'انتهت صلاحية هذا الرابط. اطلب دعوة جديدة.',
      genericError: 'حدث خطأ ما. حاول مرة أخرى بعد قليل.',
      showPassword: 'إظهار كلمة المرور',
      hidePassword: 'إخفاء كلمة المرور',
    },
    onboarding: {
      stepBadge: 'Adım {current} / {total}',
      next: 'İleri',
      finish: 'Bitir',
      validating: 'Doğrulanıyor...',
      skip: 'Bu adımı atla',
      close: 'Kurulum asistanını kapat',
      retry: 'Tekrar dene',
      errorGeneric: 'Kurulum adımları yüklenemedi.',
      allStepsDone: 'Kurulum tamamlandı!',
      quickStart: 'Hızlı Başlangıç',
      later: 'Daha sonra önerilir',
      firstCheckinHint: 'Mobil uygulamadan veya kiosktan ilk girişinizi yapın, ardından bu adımı tamamlandı olarak işaretleyin.',
      qrShow: "Şirketin QR kodunu göster",
      qrHide: 'QR kodunu gizle',
      qrHint: "Şirkete katılmak için bu QR kodunu Leopardo mobil uygulamasıyla tarayın.",
      qrError: 'QR kodu yüklenemedi.',
      qrLoading: 'QR kodu yükleniyor...',
      steps: {
        add_employees: {
          title: 'Ekiplerinizi ekleyin',
          desc: 'Puantaja başlamak için çalışanlarınızı oluşturun veya içe aktarın (CSV).',
        },
        configure_payroll: {
          title: 'Maaş yapısını kurun',
          desc: 'Maaş yapınızı ve bordro ayarlarınızı doldurun.',
        },
        setup_schedules: {
          title: 'Çalışma saatlerini tanımlayın',
          desc: 'Programlarınızı ve devam kurallarınızı oluşturun.',
        },
        setup_geofence: {
          title: 'Coğrafi sınırları kurun',
          desc: 'İzin verilen giriş alanlarını belirleyin (isteğe bağlı).',
        },
        setup_kiosk: {
          title: 'Biyometrik kiosku etkinleştirin',
          desc: 'Sahada giriş için ZKTeco kioskunuzu bağlayın (isteğe bağlı).',
        },
        first_checkin: {
          title: 'İlk giriş',
          desc: 'Kurulumu doğrulamak için bir test girişi yapın.',
        },
      },
    },
    absences: {
      title: 'İzinler',
      approve: 'Onayla',
      reject: 'Reddet',
      cancel: 'İptal',
      rejectTitle: 'Talebi reddet',
      rejectBody: 'Reddetme nedenini belirtin (zorunlu). Çalışana bildirim gönderilecektir.',
      reasonLabel: 'Reddetme nedeni',
      reasonPlaceholder: 'Zorunlu neden…',
      reasonRequired: 'Reddetmek için neden zorunludur.',
      rejectConfirm: 'Reddi onayla',
      rejectInProgress: 'Reddediliyor...',
      statusPending: 'Beklemede',
      statusApproved: 'Onaylandı',
      statusRejected: 'Reddedildi',
      statusCancelled: 'İptal edildi',
      loadError: 'İzinler yüklenemedi.',
      empty: 'İzin yok.',
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
      columnCompliance: 'Uyumluluk',
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
      runDraft: 'Taslak',
      runCalculated: 'Hesaplandı',
      runValidated: 'Onaylandı',
      runLocked: 'Kilitli',
      runCalculate: 'Hesapla',
      runValidate: 'Onayla (İK)',
      runLock: 'Kilitle',
      runUnlock: 'Kilidi aç',
      runConfirmLock: 'Bu maaş döneminin kapanışı kilitlensin mi?',
      runConfirmUnlock: 'Bu maaş döneminin kapanış kilidi açılsın mı?',
      runActionError: 'Maaş döneminde işlem gerçekleştirilemedi.',
      runCancel: 'İptal',
      downloadPdf: 'PDF indir',
      viewDetail: 'Detayi gor',
      resultsCount: 'sonuc',
      detailTitle: 'Bordro Detayi',
      detailClose: 'Kapat',
      detailLoading: 'Detay yukleniyor...',
      detailError: 'Detay su anda mevcut degil — liste verileri gosteriliyor.',
      detailDeductions: 'Kesintiler',
      detailEmployerContributions: 'Isveren katkilari',
      detailTotalCost: 'Isveren toplam maliyeti',
      detailWorkingDays: 'Calisma gunleri',
      detailDaysWorked: 'Filii calisilan gunler',
      detailOvertimeHours: 'Fazla mesai saatleri',
      detailSalaryBreakdown: 'Maas detayi',
      salaryDecompTitle: 'Maaş dökümü',
      salaryMonthly: 'Aylık maaş',
      salaryDailyRate: 'Günlük ücret',
      salaryHourlyRate: 'Saatlik ücret',
      salaryCompositionDays: 'Bu ay: {days} gün × {rate} = {total}',
      salaryCompositionHours: 'Bu ay: {hours} saat × {rate} = {total}',
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
    partnerPage: {
      loading: 'Alanınız yükleniyor...',
      applyErrorPrefix: 'Başvuru sırasında hata: ',
      notApplied: {
        title: 'Partner Olun',
        subtitle: "Leopardo RH ekosistemine katılın ve yönlendirdiğiniz her şirket için komisyon kazanın. %20'ye varan düzenli komisyon.",
        individual: 'Bireysel olarak başvurun',
        agency: 'Ajans olarak başvurun',
      },
      pending: {
        title: 'Başvuru inceleniyor',
        body: 'Başvurunuz satış ekibimiz tarafından doğrulanıyor. Erişiminiz etkinleştirilir etkinleştirilmez bir e-posta alacaksınız.',
      },
      dashboard: {
        title: 'Partner Paneli',
        subtitle: 'Leopardo RH dönüşümlerinizi ve komisyonlarınızı takip edin — aktif partner durumu.',
      },
      metrics: {
        conversions: 'Dönüşümler',
        totalEarned: 'Toplam kazanç',
        pending: 'Beklemede',
        withdrawable: 'Çekilebilir bakiye',
      },
      commissions: {
        title: 'Son komisyonlar',
        empty: 'Kayıtlı komisyon yok.',
      },
      table: {
        tenantId: 'Tenant Kimliği',
        date: 'Tarih',
        status: 'Durum',
        amount: 'Tutar',
        statusPaid: 'Ödendi',
        statusPending: 'Beklemede',
      },
      payout: {
        title: 'Ödeme',
        body: 'Komisyonlarınız eşiğe ulaşıldığında ödenir. Banka bilgilerinizin güncel olduğundan emin olun.',
        request: 'Havale talep et',
        sending: 'Gönderiliyor...',
        insufficient: 'Havale talep etmek için bakiye yetersiz (minimum 100,00 €).',
        success: 'Havale talebi başarıyla gönderildi.',
        errorPrefix: 'Havale talebi sırasında hata: ',
      },
      referral: {
        title: 'Referans bağlantısı',
        unavailable: 'Bağlantı kullanılamıyor',
        copy: 'Bağlantımı kopyala',
        copied: 'Kopyalandı!',
        copyError: 'Bağlantı kopyalanamadı. Lütfen elle kopyalayın.',
      },
    },
    offlinePage: {
      title: 'Internet baglantisi yok',
      body: 'Su anda cevrimdisisiniz. Yerel agnizda bir Leopardo Edge node mevcutsa, uygulama normal sekilde calismaya devam eder.',
      edgeModeTitle: 'Edge modu aktif mi?',
      edgeModeBody: 'Yerel arayuze su adresten erisin:',
      retry: 'Yeniden dene',
    },

  billing: {
    title: 'Faturalama',
    subtitle: 'Aboneliğinizi, faturalarınızı ve ödeme bilgilerinizi yönetin.',
    statusActive: 'Aktif',
    statusCancelled: 'İptal edildi',
    statusPastDue: 'Vadesi geçti',
    statusPaid: 'Ödendi',
    statusPending: 'Beklemede',
    cancelConfirm: 'Aboneliğiniz iptal edilsin mi? Premium modüllere erişiminizi geçerli dönemin sonunda kaybedersiniz.',
    cancelError: 'Abonelik iptal edilemedi.',
    renewError: 'Abonelik yeniden etkinleştirilemedi.',
    noPaymentAccount: 'Bağlı ödeme hesabı yok. Önce bir plana abone olun.',
    downloadError: 'Fatura indirilemedi.',
    noActivePeriod: 'Etkin dönem yok',
    periodRange: 'Dönem: {start} - {end}',
    cancelLabel: 'Aboneliği iptal et',
    loadError: 'Faturalama bilgileri yüklenemedi.',
  },
  contracts: {
    title: 'Sözleşmeler',
    subtitle: 'Ekip sözleşmelerinizi yönetin',
    statusAll: 'Tüm durumlar',
    statusActive: 'Aktif',
    statusSuspended: 'Askıya alındı',
    statusActives: 'Aktif',
    statusSuspendeds: 'Askıya alındı',
    statusTerminated: 'Sonlandırıldı',
    statusDraft: 'Taslak',
  },
  absencesPage: {
    loadError: 'Devamsızlıklar yüklenemedi.',
    approve: 'Onayla',
    reject: 'Reddet',
    rejectTitle: 'Talebi reddet',
    rejectReasonPlaceholder: 'Reddetme nedeni (zorunlu)',
    reasonRequired: 'Reddetme nedeni zorunludur.',
    cancel: 'İptal',
    confirmReject: 'Reddi onayla',
    approveSuccess: 'Talep onaylandı.',
    rejectSuccess: 'Talep reddedildi.',
    actionError: 'İşlem gerçekleştirilemedi.',
  },
  attendancePage: { loadError: 'Yoklama yüklenemedi.' },
  socialPage: {
    title: 'Sosyal medya',
    subtitle: 'İçeriklerinizi yayınlayın ve planlayın',
    loadError: 'Yayınlar yüklenemedi.',
    createError: 'Yayın oluşturulamadı.',
  },
  socialMarketingPage: {
    title: 'Pazarlama',
    subtitle: 'Sosyal hesabınızı bağlayın ve yayınlarınızı yönetin',
    loadAccountError: 'Sosyal hesap yüklenemedi.',
    loadPostsError: 'Yayınlar yüklenemedi.',
    connectError: 'Sosyal hesap bağlanamadı.',
    disconnectError: 'Sosyal hesap bağlantısı kesilemedi.',
    createError: 'Yayın oluşturulamadı.',
    publishError: 'Yayın yayınlanamadı.',
    deleteError: 'Yayın silinemedi.',
    statusActive: 'Aktif',
  },
  trainingPage: {
    loadError: 'Eğitimler yüklenemedi.',
    createError: 'Eğitim oluşturulamadı.',
  },
  notificationsPage: {
    statusEnabled: 'Etkin',
    statusDisabled: 'Devre dışı',
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
    accountCreatedFree: 'Account created! Sign in to access your free workspace.',
    accountCreatedPaid: 'Registration received! Sign in to continue.',
      demoTitle: 'Choose a demo account',
      demoSubtitle: 'Select a role to prefill the form, then sign in.',
      close: 'Close',
      supportCopy: 'Need help recovering access?',
      supportLink: 'Contact support',
      errors: {
        generic: 'Something went wrong.',
        missingToken: 'The login token is missing from the API response.',
        missingUser: 'The authenticated user profile is missing from the API response.',
        google: 'Google sign-in failed. Please try again.',
        googleNetwork: 'Could not reach Google. Check your connection and try again.',
        googleAuthFailed: 'Google refused the sign-in. Please try again.',
        googleNoAccount: 'No Leopardo RH account is linked to this Google email. Ask your administrator for an invitation.',
      },
    },
    dashboard: {
      heading: 'Dashboard',
      employees: 'Active employees',
      present: 'present',
      live: 'Live',
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
      featureLockedRole: "Your current role does not allow access to this module.",
      featureLockedPlan: "This module is not included in your current plan.",
      featureLockedBadge: 'Module not included',
      featureLockedExplanation: "Leopardo RH keeps the interface explicit to avoid confusing 404s and unnecessary API errors.",
      featureLockedAdminHint: "Ask the platform super administrator to enable it, or switch to a plan that includes this module.",
      featureLockedPlanRoleTitle: 'Plan & role',
      featureLockedPlanRoleBody: 'The modules visible in this space are computed from permissions, the company plan and the user role.',
      featureLockedCta: 'Request activation',
      recent_activity: 'Recent activity',
      noNotifications: 'No recent notifications.',
      managePreferences: 'Manage my preferences',
    },
    passwordReset: {
      title: 'Forgot your password?',
      subtitle: 'Enter the email address of your account and we will send you a secure reset link.',
      emailLabel: 'Email address',
      emailPlaceholder: 'you@company.com',
      submit: 'Send the link',
      submitting: 'Sending...',
      successTitle: 'Email sent',
      successBody: "If an account exists with this address, a reset link has just been sent. Don't forget to check your spam folder.",
      backToLogin: 'Back to login',
      newPasswordLabel: 'New password',
      newPasswordPlaceholder: 'At least 8 characters',
      confirmPasswordLabel: 'Confirm password',
      confirmPasswordPlaceholder: 'Repeat your password',
      submitReset: 'Reset password',
      submittingReset: 'Resetting...',
      resetSuccessTitle: 'Password reset',
      resetSuccessBody: 'You can now sign in with your new password.',
      invalidEmail: 'Invalid email address.',
      invalidPassword: 'Password must be at least 8 characters.',
      passwordMismatch: 'The two passwords do not match.',
      missingTokenTitle: 'Invalid or expired link',
      missingTokenBody: 'This reset link is invalid or has expired. Please request a new one.',
      genericError: 'Something went wrong. Please try again in a few moments.',
      showPassword: 'Show password',
      hidePassword: 'Hide password',
    },
    accountActivation: {
      title: 'Hesabınızı etkinleştirin',
      subtitle: 'Leopardo RH hesabınızı etkinleştirmek için bir şifre belirleyin.',
      passwordLabel: 'Şifre',
      passwordPlaceholder: 'En az 8 karakter',
      confirmPasswordLabel: 'Şifreyi onayla',
      confirmPasswordPlaceholder: 'Şifrenizi tekrar girin',
      submit: 'Hesabımı etkinleştir',
      submitting: 'Etkinleştiriliyor…',
      successTitle: 'Hesap etkinleştirildi!',
      successBody: 'Hesabınız artık aktif. Giriş yapabilirsiniz.',
      backToLogin: 'Girişe git',
      invalidPassword: 'Şifre en az 8 karakter olmalıdır.',
      passwordMismatch: 'Şifreler eşleşmiyor.',
      missingTokenTitle: 'Geçersiz veya süresi dolmuş bağlantı',
      missingTokenBody: 'Bu etkinleştirme bağlantısı geçersiz veya süresi dolmuş. Yeni bir davet için yöneticinizle iletişime geçin.',
      alreadyAccepted: 'Bu bağlantı zaten kullanıldı. Doğrudan giriş yapın.',
      expired: 'Bu bağlantının süresi doldu. Yeni bir davet isteyin.',
      genericError: 'Bir hata oluştu. Birkaç dakika sonra tekrar deneyin.',
      showPassword: 'Şifreyi göster',
      hidePassword: 'Şifreyi gizle',
    },
    onboarding: {
      stepBadge: 'Step {current} of {total}',
      next: 'Next',
      finish: 'Finish',
      validating: 'Validating...',
      skip: 'Skip this step',
      close: 'Close the setup assistant',
      retry: 'Retry',
      errorGeneric: 'Unable to load the setup steps.',
      allStepsDone: 'Setup complete!',
      quickStart: 'Quick Start',
      later: 'Recommended later',
      firstCheckinHint: "Run a first check-in from the mobile app or the kiosk, then mark this step as done.",
      qrShow: "Show the company QR code",
      qrHide: 'Hide the QR code',
      qrHint: "Scan this QR code with the Leopardo mobile app to join the company.",
      qrError: 'Unable to load the QR code.',
      qrLoading: 'Loading QR code...',
      steps: {
        add_employees: {
          title: 'Add your teams',
          desc: 'Create or import your employees (CSV) to start clocking in.',
        },
        configure_payroll: {
          title: 'Set up payroll',
          desc: 'Fill in your salary structure and payroll settings.',
        },
        setup_schedules: {
          title: 'Define schedules',
          desc: 'Create your schedules and attendance rules.',
        },
        setup_geofence: {
          title: 'Set up geolocation',
          desc: 'Define authorized check-in areas (optional).',
        },
        setup_kiosk: {
          title: 'Enable the biometric kiosk',
          desc: 'Connect your ZKTeco kiosk for on-site clock-in (optional).',
        },
        first_checkin: {
          title: 'First check-in',
          desc: 'Run a test check-in to validate the setup.',
        },
      },
    },
    absences: {
      title: 'Absences',
      approve: 'Approve',
      reject: 'Reject',
      cancel: 'Cancel',
      rejectTitle: 'Reject request',
      rejectBody: 'Please provide the rejection reason (required). The employee will be notified.',
      reasonLabel: 'Rejection reason',
      reasonPlaceholder: 'Required reason…',
      reasonRequired: 'A reason is required to reject.',
      rejectConfirm: 'Confirm rejection',
      rejectInProgress: 'Rejecting...',
      statusPending: 'Pending',
      statusApproved: 'Approved',
      statusRejected: 'Rejected',
      statusCancelled: 'Cancelled',
      loadError: 'Unable to load absences.',
      empty: 'No absences.',
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
      columnCompliance: 'Compliance',
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
      runDraft: 'Draft',
      runCalculated: 'Calculated',
      runValidated: 'Validated',
      runLocked: 'Locked',
      runCalculate: 'Calculate',
      runValidate: 'Validate (HR)',
      runLock: 'Lock',
      runUnlock: 'Unlock',
      runConfirmLock: 'Lock this payroll run closure?',
      runConfirmUnlock: 'Unlock this payroll run closure?',
      runActionError: 'Unable to perform this action on the payroll run.',
      runCancel: 'Cancel',
      downloadPdf: 'Download PDF',
      viewDetail: 'View detail',
      resultsCount: 'results',
      detailTitle: 'Pay slip details',
      detailClose: 'Close',
      detailLoading: 'Loading details...',
      detailError: 'Details temporarily unavailable — showing list data.',
      detailDeductions: 'Deductions',
      detailEmployerContributions: 'Employer contributions',
      detailTotalCost: 'Total employer cost',
      detailWorkingDays: 'Working days',
      detailDaysWorked: 'Actual days worked',
      detailOvertimeHours: 'Overtime hours',
      detailSalaryBreakdown: 'Salary breakdown',
      salaryDecompTitle: 'Salary breakdown',
      salaryMonthly: 'Monthly salary',
      salaryDailyRate: 'Daily rate',
      salaryHourlyRate: 'Hourly rate',
      salaryCompositionDays: 'This month: {days} days × {rate} = {total}',
      salaryCompositionHours: 'This month: {hours} h × {rate} = {total}',
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
      gpsToggleSubtitle: 'Enable position vérification',
      geofenceConfigTitle: 'Geofence configuration',
      latitudeLabel: 'Latitude',
      longitudeLabel: 'Longitude',
      radiusFieldLabel: 'Radius (meters)',
      radiusHint: 'Maximum allowed distance from the workplace.',
      save: 'Save',
      saving: 'Saving…',
      cancel: 'Cancel',
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
    partnerPage: {
      loading: 'Loading your workspace...',
      applyErrorPrefix: 'Error while applying: ',
      notApplied: {
        title: 'Become a Partner',
        subtitle: 'Join the Leopardo RH ecosystem and earn commissions on every company you refer. Up to 20% recurring commission.',
        individual: 'Apply as an Individual',
        agency: 'Apply as an Agency',
      },
      pending: {
        title: 'Application in progress',
        body: 'Your application is being reviewed by our sales team. You will receive an email as soon as your access is activated.',
      },
      dashboard: {
        title: 'Partner Dashboard',
        subtitle: 'Track your Leopardo RH conversions and commissions — active partner status.',
      },
      metrics: {
        conversions: 'Conversions',
        totalEarned: 'Total earned',
        pending: 'Pending',
        withdrawable: 'Withdrawable balance',
      },
      commissions: {
        title: 'Latest commissions',
        empty: 'No commission recorded.',
      },
      table: {
        tenantId: 'Tenant ID',
        date: 'Date',
        status: 'Status',
        amount: 'Amount',
        statusPaid: 'Paid',
        statusPending: 'Pending',
      },
      payout: {
        title: 'Payout',
        body: 'Your commissions are paid once the threshold is reached. Make sure your bank details are up to date.',
        request: 'Request a transfer',
        sending: 'Sending...',
        insufficient: 'Insufficient balance to request a transfer (minimum €100.00).',
        success: 'Transfer request sent successfully.',
        errorPrefix: 'Error while requesting the transfer: ',
      },
      referral: {
        title: 'Referral link',
        unavailable: 'Link unavailable',
        copy: 'Copy my link',
        copied: 'Copied!',
        copyError: 'Unable to copy the link. Copy it manually.',
      },
    },
    offlinePage: {
      title: 'No internet connection',
      body: 'You are currently offline. If a Leopardo Edge node is available on your local network, the application keeps working normally.',
      edgeModeTitle: 'Edge mode active?',
      edgeModeBody: 'Access the local interface via:',
      retry: 'Retry',
    },

  billing: {
    title: 'Billing',
    subtitle: 'Manage your subscription, invoices and payment information.',
    statusActive: 'Active',
    statusCancelled: 'Cancelled',
    statusPastDue: 'Past due',
    statusPaid: 'Paid',
    statusPending: 'Pending',
    cancelConfirm: 'Cancel your subscription? You will lose access to premium modules at the end of the current period.',
    cancelError: 'Unable to cancel the subscription.',
    renewError: 'Unable to reactivate the subscription.',
    noPaymentAccount: 'No payment account linked. Subscribe to a plan first.',
    downloadError: 'Unable to download the invoice.',
    noActivePeriod: 'No active period',
    periodRange: 'Period: {start} to {end}',
    cancelLabel: 'Cancel subscription',
    loadError: 'Unable to load billing information.',
  },
  contracts: {
    title: 'Contracts',
    subtitle: 'Manage your team contracts',
    statusAll: 'All statuses',
    statusActive: 'Active',
    statusSuspended: 'Suspended',
    statusActives: 'Active',
    statusSuspendeds: 'Suspended',
    statusTerminated: 'Terminated',
    statusDraft: 'Draft',
  },
  absencesPage: {
    loadError: 'Unable to load absences.',
    approve: 'Approve',
    reject: 'Reject',
    rejectTitle: 'Reject request',
    rejectReasonPlaceholder: 'Reason for rejection (required)',
    reasonRequired: 'A rejection reason is required.',
    cancel: 'Cancel',
    confirmReject: 'Confirm rejection',
    approveSuccess: 'Request approved.',
    rejectSuccess: 'Request rejected.',
    actionError: 'Unable to perform this action.',
  },
  attendancePage: { loadError: 'Unable to load attendance.' },
  socialPage: {
    title: 'Social media',
    subtitle: 'Publish and schedule your content',
    loadError: 'Unable to load posts.',
    createError: 'Unable to create the post.',
  },
  socialMarketingPage: {
    title: 'Marketing',
    subtitle: 'Connect your social account and manage your posts',
    loadAccountError: 'Unable to load the social account.',
    loadPostsError: 'Unable to load posts.',
    connectError: 'Unable to connect the social account.',
    disconnectError: 'Unable to disconnect the social account.',
    createError: 'Unable to create the post.',
    publishError: 'Unable to publish the post.',
    deleteError: 'Unable to delete the post.',
    statusActive: 'Active',
  },
  trainingPage: {
    loadError: 'Unable to load trainings.',
    createError: 'Unable to create the training.',
  },
  notificationsPage: {
    statusEnabled: 'Enabled',
    statusDisabled: 'Disabled',
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

/**
 * Locale SSR de la vitrine (issue #4393) : `?lang=` (liens hreflang, #4173)
 * prime sur Accept-Language ; sinon le header est normalisé comme le root
 * layout (#2657). Source unique pour le middleware → les ~20 layouts landing
 * servent enfin des metadata (title/description) dans la langue réelle du
 * visiteur au lieu de retomber sur le FR codé en dur.
 */
export function resolveSsrVitrineLang(
  urlLang: string | null | undefined,
  acceptLanguage: string | null | undefined,
): AppLocale {
  if (urlLang && isSupportedLocale(urlLang)) {
    return urlLang;
  }
  return normalizeLocale(acceptLanguage);
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


export type JobRecommendationsCopy = {
  unavailable: string;
  resumeFormatError: string;
  resumeUploadError: string;
  applicationAlreadySent: string;
  applicationError: string;
  activeSearch: string;
  heading: string;
  description: string;
  uploadResume: string;
  uploadingResume: string;
  resumeVersionLabel: string;
  activeResume: string;
  activeResumePrefix: string;
  resumePrefix: string;
  searching: string;
  empty: string;
  applicationsTitle: string;
  applicationFallback: (id: number) => string;
  lastUpdate: string;
  applicationSent: string;
  notificationsTitle: string;
  markAllRead: string;
  companyFallback: string;
  locationFallback: string;
  messagePlaceholder: string;
  contractFallback: string;
  viewOffer: string;
  apply: string;
  sending: string;
};

const jobRecommendationsCopy: Record<AppLocale, JobRecommendationsCopy> = {
  fr: {
    unavailable: 'Recommandations indisponibles.',
    resumeFormatError: 'Le CV doit être un fichier PDF, DOC ou DOCX de 5 Mo maximum.',
    resumeUploadError: 'Téléversement du CV impossible.',
    applicationAlreadySent: 'Vous avez déjà postulé à cette offre.',
    applicationError: 'Candidature impossible.',
    activeSearch: 'Recherche active',
    heading: 'Des offres qui correspondent à votre profil',
    description: 'Les offres sont d’abord filtrées par vos préférences, puis l’IA peut affiner le classement. Les raisons affichées restent liées aux informations du profil et de l’offre.',
    uploadResume: 'Téléverser mon CV',
    uploadingResume: 'Téléversement…',
    resumeVersionLabel: 'Version du CV utilisée pour les candidatures',
    activeResume: 'CV actif',
    activeResumePrefix: 'CV actif :',
    resumePrefix: 'CV :',
    searching: 'Recherche des offres…',
    empty: 'Aucune offre publiée ne correspond encore à vos préférences.',
    applicationsTitle: 'Suivi de mes candidatures',
    applicationFallback: (id) => `Candidature #${id}`,
    lastUpdate: 'Dernière mise à jour du dossier.',
    applicationSent: 'Candidature envoyée.',
    notificationsTitle: 'Nouvelles mises à jour',
    markAllRead: 'Tout marquer comme lu',
    companyFallback: 'Entreprise',
    locationFallback: 'Localisation non précisée',
    messagePlaceholder: 'Ajouter un message (facultatif)',
    contractFallback: 'Contrat',
    viewOffer: 'Voir l’offre',
    apply: 'Postuler',
    sending: 'Envoi…',
  },
  en: {
    unavailable: 'Recommendations are unavailable.',
    resumeFormatError: 'The resume must be a PDF, DOC or DOCX file of up to 5 MB.',
    resumeUploadError: 'Unable to upload the resume.',
    applicationAlreadySent: 'You have already applied for this offer.',
    applicationError: 'Unable to submit the application.',
    activeSearch: 'Active search',
    heading: 'Offers matching your profile',
    description: 'Offers are first filtered by your preferences, then AI can refine the ranking. Displayed reasons remain linked to profile and offer information.',
    uploadResume: 'Upload my resume',
    uploadingResume: 'Uploading…',
    resumeVersionLabel: 'Resume version used for applications',
    activeResume: 'Active resume',
    activeResumePrefix: 'Active resume:',
    resumePrefix: 'Resume:',
    searching: 'Searching for offers…',
    empty: 'No published offer matches your preferences yet.',
    applicationsTitle: 'My applications',
    applicationFallback: (id) => `Application #${id}`,
    lastUpdate: 'Last application update.',
    applicationSent: 'Application sent.',
    notificationsTitle: 'New updates',
    markAllRead: 'Mark all as read',
    companyFallback: 'Company',
    locationFallback: 'Location not specified',
    messagePlaceholder: 'Add a message (optional)',
    contractFallback: 'Contract',
    viewOffer: 'View offer',
    apply: 'Apply',
    sending: 'Sending…',
  },
  ar: {
    unavailable: 'التوصيات غير متاحة.',
    resumeFormatError: 'يجب أن تكون السيرة الذاتية بصيغة PDF أو DOC أو DOCX وبحجم أقصى 5 ميغابايت.',
    resumeUploadError: 'تعذر رفع السيرة الذاتية.',
    applicationAlreadySent: 'لقد تقدمت بالفعل لهذا العرض.',
    applicationError: 'تعذر إرسال الطلب.',
    activeSearch: 'بحث نشط',
    heading: 'عروض تناسب ملفك الشخصي',
    description: 'يتم أولاً تصفية العروض حسب تفضيلاتك، ثم يمكن للذكاء الاصطناعي تحسين الترتيب.',
    uploadResume: 'رفع سيرتي الذاتية',
    uploadingResume: 'جارٍ الرفع…',
    resumeVersionLabel: 'نسخة السيرة الذاتية المستخدمة للطلبات',
    activeResume: 'السيرة الذاتية النشطة',
    activeResumePrefix: 'السيرة الذاتية النشطة:',
    resumePrefix: 'السيرة الذاتية:',
    searching: 'جارٍ البحث عن العروض…',
    empty: 'لا توجد عروض منشورة تطابق تفضيلاتك حالياً.',
    applicationsTitle: 'متابعة طلباتي',
    applicationFallback: (id) => `الطلب رقم ${id}`,
    lastUpdate: 'آخر تحديث للطلب.',
    applicationSent: 'تم إرسال الطلب.',
    notificationsTitle: 'تحديثات جديدة',
    markAllRead: 'وضع علامة مقروء على الكل',
    companyFallback: 'شركة',
    locationFallback: 'الموقع غير محدد',
    messagePlaceholder: 'إضافة رسالة (اختياري)',
    contractFallback: 'العقد',
    viewOffer: 'عرض الوظيفة',
    apply: 'تقدم',
    sending: 'جارٍ الإرسال…',
  },
  tr: {
    unavailable: 'Öneriler kullanılamıyor.',
    resumeFormatError: 'Özgeçmiş PDF, DOC veya DOCX olmalı ve 5 MB’ı geçmemelidir.',
    resumeUploadError: 'Özgeçmiş yüklenemedi.',
    applicationAlreadySent: 'Bu ilana zaten başvurdunuz.',
    applicationError: 'Başvuru gönderilemedi.',
    activeSearch: 'Aktif arama',
    heading: 'Profilinize uygun ilanlar',
    description: 'İlanlar önce tercihlerinize göre filtrelenir, ardından yapay zekâ sıralamayı iyileştirebilir.',
    uploadResume: 'Özgeçmişimi yükle',
    uploadingResume: 'Yükleniyor…',
    resumeVersionLabel: 'Başvurularda kullanılacak özgeçmiş sürümü',
    activeResume: 'Aktif özgeçmiş',
    activeResumePrefix: 'Aktif özgeçmiş:',
    resumePrefix: 'Özgeçmiş:',
    searching: 'İlanlar aranıyor…',
    empty: 'Henüz tercihlerinize uygun yayınlanmış ilan yok.',
    applicationsTitle: 'Başvurularım',
    applicationFallback: (id) => `Başvuru #${id}`,
    lastUpdate: 'Başvurunun son güncellemesi.',
    applicationSent: 'Başvuru gönderildi.',
    notificationsTitle: 'Yeni güncellemeler',
    markAllRead: 'Tümünü okundu işaretle',
    companyFallback: 'Şirket',
    locationFallback: 'Konum belirtilmedi',
    messagePlaceholder: 'Mesaj ekle (isteğe bağlı)',
    contractFallback: 'Sözleşme',
    viewOffer: 'İlanı görüntüle',
    apply: 'Başvur',
    sending: 'Gönderiliyor…',
  },
};

export function getJobRecommendationsCopy(locale: AppLocale): JobRecommendationsCopy {
  return jobRecommendationsCopy[locale] ?? jobRecommendationsCopy.fr;
}

export function getPreferredJobRecommendationsCopy(): JobRecommendationsCopy {
  return getJobRecommendationsCopy(getPreferredLocale());
}
