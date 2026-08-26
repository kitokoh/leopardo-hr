// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for French (`fr`).
class AppLocalizationsFr extends AppLocalizations {
  AppLocalizationsFr([String locale = 'fr']) : super(locale);

  @override
  String get appTitle => 'Leopardo RH';

  @override
  String get accountingportalTitle => 'Espace document sécurisé';

  @override
  String get accountingportalSubtitle =>
      'Accès limité au document partagé par l\'émetteur';

  @override
  String get accountingportalNumber => 'N° de document';

  @override
  String get accountingportalType => 'Type de document';

  @override
  String get accountingportalStatus => 'Statut';

  @override
  String get accountingportalIssuedate => 'Date d\'émission';

  @override
  String get accountingportalTotal => 'Montant total TTC';

  @override
  String get accountingportalCurrency => 'Devise';

  @override
  String get accountingportalExpiresat => 'Lien valide jusqu\'au :date';

  @override
  String get accountingportalDownload => 'Télécharger le PDF';

  @override
  String get accountingportalDownloadhint => 'Télécharger le document en PDF';

  @override
  String get accountingportalNotfoundtitle => 'Lien invalide ou expiré';

  @override
  String get accountingportalNotfoundbody =>
      'Ce lien n\'est plus valide ou a expiré. Contactez l\'émetteur pour recevoir un nouveau lien.';

  @override
  String get accountingportalErrortitle => 'Impossible de charger le document';

  @override
  String get accountingportalErrorbody =>
      'Une erreur est survenue. Merci de réessayer dans quelques instants.';

  @override
  String get accountingportalRetry => 'Réessayer';

  @override
  String get accountingportalBacktosite => 'Retour au site';

  @override
  String get accountingportalSecuritynote =>
      'Partage sécurisé — ne transmettez pas ce lien';

  @override
  String get accountingportalStatusdraft => 'Brouillon';

  @override
  String get accountingportalStatussent => 'Envoyé';

  @override
  String get accountingportalStatuspartiallypaid => 'Partiellement payé';

  @override
  String get accountingportalStatuspaid => 'Payé';

  @override
  String get accountingportalStatuscancelled => 'Annulé';

  @override
  String get accountingportalStatusoverdue => 'En retard';

  @override
  String get accountingportalLoading => 'Chargement…';

  @override
  String get accountingportalDownloaderror =>
      'Le téléchargement a échoué. Merci de réessayer.';

  @override
  String get welcomeBrandSubtitle =>
      'Conversationnelle, mobile-first, modulaire.';

  @override
  String get welcomeHeroTitle =>
      'Votre journée commence ici, pas dans un back-office.';

  @override
  String get welcomeHeroDescription =>
      'Pointage, suivi personnel et modules RH actifs s ouvrent d abord sur le telephone, avec une experience simple et lisible.';

  @override
  String get welcomeStoryClarityTitle =>
      'Une home qui vous parle avant de vous noyer';

  @override
  String get welcomeStoryClarityBody =>
      'Leopardo RH commence par quelques actions claires: pointer, suivre le mois et retrouver les informations qui comptent.';

  @override
  String get welcomeStoryFieldTitle => 'Mobile-first pour le terrain';

  @override
  String get welcomeStoryFieldBody =>
      'Le telephone est la surface principale de l employe. Votre pointage, vos absences et vos documents vivent ici.';

  @override
  String get welcomeStoryModulesTitle =>
      'Modules actifs, feuille de route visible';

  @override
  String get welcomeStoryModulesBody =>
      'Le produit ouvre d abord ce qui est utile aujourd hui, puis garde Finance, Securite et Leo dans un cap lisible.';

  @override
  String get welcomeLeaves => 'Congés';

  @override
  String get welcomeMyTeam => 'Mon équipe';

  @override
  String get welcomePresences => 'Présences';

  @override
  String get welcomeTasks => 'Tâches';

  @override
  String get login => 'Se connecter';

  @override
  String get employeeInvitationAccess => 'Acces employe (invitation)';

  @override
  String get createPersonalAccount => 'Créer un compte personnel';

  @override
  String get personalAccountExplanation =>
      'Compte personnel : organisez vos documents, puis creez ou rejoignez une entreprise depuis votre espace.';

  @override
  String get authBackTooltip => 'Retour';

  @override
  String get authEmployeeLoginSubtitle => 'Connexion employe';

  @override
  String get authManagerLoginSubtitle => 'Connexion Manager / RH';

  @override
  String get authEmailProfessionalLabel => 'Email professionnel';

  @override
  String get authEmailLabel => 'Email';

  @override
  String get authEmailRequired => 'Email obligatoire';

  @override
  String get authEmailInvalid => 'Email invalide';

  @override
  String get authPasswordLabel => 'Mot de passe';

  @override
  String get authPasswordRequired => 'Mot de passe obligatoire';

  @override
  String get authPasswordTooShort => 'Mot de passe trop court';

  @override
  String get authContinueWithGoogle => 'Continuer avec Google';

  @override
  String get authActivateInvitation => 'Activer mon invitation';

  @override
  String get authPersonalAccountLink => 'Compte perso';

  @override
  String get authActivateManagerAccess => 'Activer mon acces manager';

  @override
  String get authTryDemoAccount => 'Tester avec un compte demo';

  @override
  String get authTwoFactorRequired => 'Le code 2FA est requis.';

  @override
  String get twoFaChallengeTitle => 'Vérification à deux facteurs';

  @override
  String get twoFaChallengeSubtitle =>
      'Entrez le code à 6 chiffres de votre application d\'authentification.';

  @override
  String get twoFaChallengeCodeHint => 'Code TOTP (6 chiffres)';

  @override
  String get twoFaChallengeVerifyBtn => 'Vérifier';

  @override
  String get twoFaChallengeRecoveryToggle => 'Utiliser un code de récupération';

  @override
  String get twoFaChallengeRecoveryHint => 'Code de récupération';

  @override
  String get twoFaChallengeInvalidError =>
      'Code invalide ou expiré. Réessayez.';

  @override
  String get authDemoAccess => 'Acces Demo';

  @override
  String get authTogglePasswordVisibility =>
      'Afficher ou masquer le mot de passe';

  @override
  String get commonLanguageLabel => 'Langue';

  @override
  String get commonLanguageVariantsFrFr => 'Français (France)';

  @override
  String get commonLanguageVariantsFrBe => 'Français (Belgique)';

  @override
  String get commonLanguageVariantsFrCa => 'Français (Canada)';

  @override
  String get commonLanguageVariantsArSa => 'Arabe (Arabie saoudite)';

  @override
  String get commonLanguageVariantsArMa => 'Arabe (Maroc)';

  @override
  String get commonLanguageVariantsTrTr => 'Turc (Turquie)';

  @override
  String get commonLanguageVariantsEnUs => 'Anglais (Etats-Unis)';

  @override
  String get commonLanguageVariantsEnGb => 'Anglais (Royaume-Uni)';

  @override
  String get commonOr => 'ou';

  @override
  String get commonCountriesBf => 'Burkina Faso';

  @override
  String get commonCountriesBj => 'Bénin';

  @override
  String get commonCountriesCa => 'Canada';

  @override
  String get commonCountriesCf => 'République centrafricaine';

  @override
  String get commonCountriesCg => 'Congo';

  @override
  String get commonCountriesCi => 'Côte d\'Ivoire';

  @override
  String get commonCountriesCm => 'Cameroun';

  @override
  String get commonCountriesDz => 'Algérie';

  @override
  String get commonCountriesFr => 'France';

  @override
  String get commonCountriesGa => 'Gabon';

  @override
  String get commonCountriesGb => 'Royaume-Uni';

  @override
  String get commonCountriesGq => 'Guinée équatoriale';

  @override
  String get commonCountriesMa => 'Maroc';

  @override
  String get commonCountriesMl => 'Mali';

  @override
  String get commonCountriesNe => 'Niger';

  @override
  String get commonCountriesSn => 'Sénégal';

  @override
  String get commonCountriesTd => 'Tchad';

  @override
  String get commonCountriesTg => 'Togo';

  @override
  String get commonCountriesTn => 'Tunisie';

  @override
  String get commonCountriesTr => 'Turquie';

  @override
  String get commonCountriesUs => 'États-Unis';

  @override
  String get commonRequired => 'Requis';

  @override
  String get modulesAttendance => 'Pointage';

  @override
  String get modulesPayroll => 'Paie';

  @override
  String get modulesCabinet => 'Coffre documentaire';

  @override
  String get modulesNotifications => 'Notifications';

  @override
  String get modulesEvaluations => 'Evaluations';

  @override
  String get emailsInvitationSubject =>
      'Vous etes invite(e) a rejoindre :company sur Leopardo RH';

  @override
  String get emailsInvitationGreeting => 'Bonjour :name,';

  @override
  String get emailsInvitationBody =>
      'Vous avez ete invite(e) a rejoindre :company. Cliquez sur le lien ci-dessous pour activer votre compte.';

  @override
  String get emailsInvitationAction => 'Activer mon compte';

  @override
  String get emailsInvitationFooter =>
      'Si vous n avez pas demande cette action, ignorez cet email.';

  @override
  String get emailsResetPasswordSubject =>
      'Reinitialisation de votre mot de passe';

  @override
  String get emailsResetPasswordGreeting => 'Bonjour :name,';

  @override
  String get emailsResetPasswordBody =>
      'Cliquez sur le lien ci-dessous pour reinitialiser votre mot de passe.';

  @override
  String get emailsResetPasswordAction => 'Reinitialiser le mot de passe';

  @override
  String get emailsResetPasswordFooter =>
      'Si vous n avez pas demande cette action, ignorez cet email.';

  @override
  String get emailsPayrollReadySubject =>
      'Votre bulletin de paie est disponible';

  @override
  String get emailsPayrollReadyGreeting => 'Bonjour :name,';

  @override
  String get emailsPayrollReadyBody =>
      'Votre bulletin de paie pour :period est pret. Vous pouvez le consulter dans Leopardo RH.';

  @override
  String get emailsPayrollReadyAction => 'Voir mon bulletin';

  @override
  String get emailsPayrollReadyFooter =>
      'Merci de verifier vos informations avant export comptable.';

  @override
  String get emailsAbsenceApprovedSubject => 'Votre absence a ete approuvee';

  @override
  String get emailsAbsenceApprovedGreeting => 'Bonjour :name,';

  @override
  String get emailsAbsenceApprovedBody =>
      'Votre demande d absence pour :period a ete approuvee.';

  @override
  String get emailsAbsenceApprovedAction => 'Voir la demande';

  @override
  String get emailsAbsenceApprovedFooter =>
      'Le planning equipe a ete mis a jour.';

  @override
  String get emailsAbsenceRejectedSubject => 'Votre absence a ete refusee';

  @override
  String get emailsAbsenceRejectedGreeting => 'Bonjour :name,';

  @override
  String get emailsAbsenceRejectedBody =>
      'Votre demande d absence pour :period a ete refusee.';

  @override
  String get emailsAbsenceRejectedAction => 'Voir la demande';

  @override
  String get emailsAbsenceRejectedFooter =>
      'Consultez votre manager si vous avez besoin d un complement.';

  @override
  String get profileTitle => 'Mon profil';

  @override
  String get profileSubtitle => 'Informations personnelles et langue';

  @override
  String get profileLoading => 'Chargement du profil...';

  @override
  String get profileBackTooltip => 'Retour';

  @override
  String get profileJobTitleUnset => 'Poste non renseigne';

  @override
  String get profileDetailsTitle => 'Informations';

  @override
  String get profileEmailLabel => 'Email';

  @override
  String get profileDepartmentLabel => 'Departement';

  @override
  String get profileJobTitleLabel => 'Poste';

  @override
  String get profileMatriculeLabel => 'Matricule';

  @override
  String get profileValueUnset => 'Non renseigne';

  @override
  String get profileOpenSettings => 'Ouvrir les paramètres du compte';

  @override
  String get profileLanguageUpdated => 'Langue mise a jour.';

  @override
  String get profileLanguageSaving => 'Mise a jour...';

  @override
  String get profileLanguageSave => 'Mettre a jour la langue';

  @override
  String get aiChatTitle => 'Assistant IA';

  @override
  String get aiChatBackTooltip => 'Retour';

  @override
  String get aiChatSendTooltip => 'Envoyer';

  @override
  String get aiChatInputHint => 'Tapez votre message...';

  @override
  String get aiChatEmptyStateTitle => 'Posez vos questions RH';

  @override
  String get aiChatErrorMessage =>
      'Erreur : impossible de contacter l assistant.';

  @override
  String get platformLoginTitle => 'Leopardo Platform';

  @override
  String get platformLoginSubtitle =>
      'Cockpit mobile reserve a l administration de la plateforme.';

  @override
  String get platformLogin2faNotice =>
      'Ce compte protege la plateforme : saisir le code 2FA de l application authenticator.';

  @override
  String get platformLoginEmailLabel => 'Email super-admin';

  @override
  String get platformLoginEmailRequired => 'Email requis';

  @override
  String get platformLoginPasswordRequired => 'Mot de passe requis';

  @override
  String get platformLogin2faLabel => 'Code 2FA si active';

  @override
  String get platformLoginSubmitting => 'Connexion...';

  @override
  String get platformLoginUseDemoAccount => 'Utiliser le compte demo';

  @override
  String get usersPageTitle => 'Gestion des Utilisateurs';

  @override
  String get usersPageSummary =>
      ':count utilisateur(s) - :active actif(s) - :newToday nouveau(x) aujourd\'hui';

  @override
  String get usersActionsBulk => 'Actions (:count)';

  @override
  String get usersActionsExport => 'Exporter';

  @override
  String get usersActionsNew => 'Nouveau';

  @override
  String get usersFiltersSearchLabel => 'Rechercher';

  @override
  String get usersFiltersSearchPlaceholder => 'Nom, email, entreprise...';

  @override
  String get usersFiltersStatusLabel => 'Statut';

  @override
  String get usersFiltersStatusAll => 'Tous les statuts';

  @override
  String get usersFiltersStatusActive => 'Actif';

  @override
  String get usersFiltersStatusInactive => 'Inactif';

  @override
  String get usersFiltersStatusSuspended => 'Suspendu';

  @override
  String get usersFiltersStatusPending => 'En attente';

  @override
  String get usersFiltersRoleLabel => 'Role';

  @override
  String get usersFiltersRoleAll => 'Tous les roles';

  @override
  String get usersFiltersRoleAdmin => 'Administrateur';

  @override
  String get usersFiltersRoleManager => 'Manager';

  @override
  String get usersFiltersRoleEmployee => 'Employe';

  @override
  String get usersFiltersRoleHr => 'RH';

  @override
  String get usersFiltersCompanyLabel => 'Entreprise';

  @override
  String get usersFiltersCompanyAll => 'Toutes les entreprises';

  @override
  String get usersFiltersRegistrationdateLabel => 'Date d\'inscription';

  @override
  String get usersFiltersRegistrationdateAll => 'Toutes les dates';

  @override
  String get usersFiltersRegistrationdateToday => 'Aujourd\'hui';

  @override
  String get usersFiltersRegistrationdateWeek => 'Cette semaine';

  @override
  String get usersFiltersRegistrationdateMonth => 'Ce mois';

  @override
  String get usersFiltersRegistrationdateQuarter => 'Ce trimestre';

  @override
  String get usersFiltersLastloginLabel => 'Derniere connexion';

  @override
  String get usersFiltersLastloginAll => 'Toutes';

  @override
  String get usersFiltersLastloginToday => 'Aujourd\'hui';

  @override
  String get usersFiltersLastloginWeek => 'Cette semaine';

  @override
  String get usersFiltersLastloginMonth => 'Ce mois';

  @override
  String get usersFiltersLastloginNever => 'Jamais connecte';

  @override
  String get usersFiltersSegmentLabel => 'Segment';

  @override
  String get usersFiltersSegmentAll => 'Tous les segments';

  @override
  String get usersFiltersSegmentChampions => 'Champions';

  @override
  String get usersFiltersSegmentLoyal => 'Loyaux';

  @override
  String get usersFiltersSegmentPotential => 'Potentiels';

  @override
  String get usersFiltersSegmentNew => 'Nouveaux';

  @override
  String get usersFiltersSegmentAtrisk => 'A risque';

  @override
  String get usersFiltersAdvancedShow => 'Afficher les filtres avances';

  @override
  String get usersFiltersAdvancedHide => 'Masquer les filtres avances';

  @override
  String get usersBulkpanelSelectedcount => ':count selectionne(s)';

  @override
  String get usersBulkpanelActivate => 'Activer';

  @override
  String get usersBulkpanelDeactivate => 'Desactiver';

  @override
  String get usersBulkpanelSuspend => 'Suspendre';

  @override
  String get usersBulkpanelExport => 'Exporter';

  @override
  String get usersBulkpanelCancel => 'Annuler';

  @override
  String get usersToastLoaderror =>
      'Erreur lors du chargement des utilisateurs';

  @override
  String get usersToastBulkactivated => ':count utilisateur(s) active(s)';

  @override
  String get usersToastBulkdeactivated => ':count utilisateur(s) desactive(s)';

  @override
  String get usersToastBulksuspended => ':count utilisateur(s) suspendu(s)';

  @override
  String get usersToastBulkerror => 'Erreur lors de l\'action groupee';

  @override
  String get usersToastDeleted => 'Utilisateur supprime';

  @override
  String get usersToastDeleteerror => 'Erreur lors de la suppression';

  @override
  String get usersToastImpersonating => 'Connexion en tant que :name';

  @override
  String get usersToastCreated => 'Utilisateur créé avec succès';

  @override
  String get usersToastUpdated => 'Utilisateur mis a jour';

  @override
  String get usersToastExportinprogress => 'Export en cours...';

  @override
  String get usersToastExportdone => 'Export termine';

  @override
  String get usersToastExporterror => 'Erreur lors de l\'export';

  @override
  String get usersToastSelectionexportdone => 'Export de la selection termine';

  @override
  String get usersToastBulkdone => 'Mise à jour effectuée';

  @override
  String get usersConfirmDelete => 'Etes-vous sur de vouloir supprimer :name ?';

  @override
  String get usersErrorsNameRequired => 'Le nom complet est requis.';

  @override
  String get usersErrorsPasswordRequired => 'Le mot de passe est requis.';

  @override
  String get usersErrorsFixFields => 'Veuillez corriger les champs en rouge';

  @override
  String get usersErrorsPasswordMin =>
      'Le mot de passe doit contenir au moins 8 caractères';

  @override
  String get usersErrorsSearchNoMatch =>
      'Aucune page ne correspond à votre recherche';

  @override
  String get usersErrorsUpdateFailed =>
      'Erreur lors de la mise à jour de l\'utilisateur';

  @override
  String get usersImpersonationTitle => 'Impersonner un employé';

  @override
  String get usersImpersonationSubtitle => 'Ouvrir une session au nom de :name';

  @override
  String get usersImpersonationReason =>
      'Motif (obligatoire, 5 caractères minimum)';

  @override
  String get usersImpersonationReasonmin =>
      'Motif obligatoire (5 caractères minimum).';

  @override
  String get usersImpersonationNolink =>
      'Aucun employé lié à ce compte — impersonation impossible.';

  @override
  String get usersImpersonationStart => 'Créer la session';

  @override
  String get usersImpersonationCancel => 'Annuler';

  @override
  String get usersImpersonationTokentitle =>
      'Jeton d\'impersonation (usage unique)';

  @override
  String get usersImpersonationExpires => 'Expire le :date';

  @override
  String get usersImpersonationCopy => 'Copier le jeton';

  @override
  String get usersImpersonationCopied => 'Jeton copié';

  @override
  String get usersImpersonationCreated => 'Session d\'impersonation créée';

  @override
  String get usersImpersonationError =>
      'Erreur lors de la création de la session d\'impersonation';

  @override
  String get usersImpersonationDone => 'Terminé';

  @override
  String get usersImpersonationEmployee => 'Employé #:id';

  @override
  String get usersEditTitle => 'Modifier l\'utilisateur';

  @override
  String get usersEditStatus => 'Statut';

  @override
  String get usersEditSave => 'Mettre à jour';

  @override
  String get dashboardTitle => 'Tableau de bord';

  @override
  String get dashboardCompany => 'Entreprise';

  @override
  String get dashboardActiveEmployees => 'Actifs';

  @override
  String get dashboardUpgrade => 'Upgrade';

  @override
  String get dashboardPriorityActions => 'Actions prioritaires';

  @override
  String get dashboardLaunchReadiness => 'Readiness lancement';

  @override
  String get dashboardRecentActivity => 'Activité récente';

  @override
  String get dashboardRecentActivityHint => 'Dernieres actions de votre equipe';

  @override
  String get dashboardLeoIaAnnouncementTitle => 'Felicitations equipe';

  @override
  String get dashboardLeoIaAnnouncementBody =>
      'Felicitations a toute l\'equipe pour votre engagement de cette semaine. Continuez sur cette dynamique !';

  @override
  String get dashboardLeoIaAnnouncementError =>
      'Impossible d\'envoyer le message. Reessayez dans quelques instants.';

  @override
  String dashboardLeoPresenceInsight(Object active, Object today) {
    return 'Aujourd\'hui, $today presence(s) sur $active employe(s) actif(s). Souhaitez-vous envoyer un message de felicitations a l\'equipe ?';
  }

  @override
  String get dashboardLeoPresenceEmpty =>
      'Aucune donnee de presence disponible pour le moment. Souhaitez-vous quand meme envoyer un message de felicitations a l\'equipe ?';

  @override
  String dashboardLeoAnnouncementsCount(Object count) {
    return '$count annonce(s) publiee(s) dans votre entreprise.';
  }

  @override
  String get dashboardPresenceTodayTitle => 'Présence aujourd\'hui';

  @override
  String dashboardPresenceTodaySummary(Object active, Object present) {
    return '$present presence(s) sur $active employe(s) actif(s) aujourd hui';
  }

  @override
  String get dashboardPresenceTodayEmpty =>
      'Aucune donnee de presence disponible.';

  @override
  String get dashboardPortfoliopriorities => 'Priorites Portefeuille';

  @override
  String get dashboardClient => 'Client';

  @override
  String get dashboardHealth => 'Sante';

  @override
  String get dashboardRisk => 'Risque';

  @override
  String get dashboardActions => 'Actions';

  @override
  String get dashboardSeeall => 'Voir tout';

  @override
  String get dashboardNoprioritycompanies =>
      'Aucune entreprise prioritaire pour le moment.';

  @override
  String get dashboardPendingregistrations => 'Inscriptions en attente';

  @override
  String get dashboardNopendingrequests => 'Aucune demande en attente.';

  @override
  String get dashboardAdoption => 'Adoption';

  @override
  String get dashboardCheckins30d => 'Pointages 30j';

  @override
  String get dashboardActiveemployees => 'Employés actifs';

  @override
  String get dashboardClientsatrisk => 'Clients a risque';

  @override
  String get dashboardRevenue => 'Revenus';

  @override
  String get dashboardCollected30d => 'Encaisse 30j';

  @override
  String get dashboardOverdue => 'Impayes';

  @override
  String get dashboardActivesubscriptions => 'Abonnements actifs';

  @override
  String get dashboardShortcuts => 'Raccourcis';

  @override
  String get dashboardClientportfolio => 'Portefeuille clients';

  @override
  String get dashboardSubscriptions => 'Abonnements';

  @override
  String get dashboardClientrequests => 'Demandes clients';

  @override
  String get dashboardCreateactivateclient => 'Creer ou activer un client';

  @override
  String get dashboardOpenclientportfolio => 'Ouvrir le portefeuille clients';

  @override
  String get dashboardProcessincomingrequests =>
      'Traiter les demandes entrantes';

  @override
  String get dashboardViewclientrequests => 'Voir les demandes clients';

  @override
  String get dashboardMonitoratriskclients => 'Surveiller les clients a risque';

  @override
  String get dashboardAnalyzepriorities => 'Analyser les priorites';

  @override
  String get dashboardManagesubscriptionsrevenue =>
      'Piloter abonnements et revenus';

  @override
  String get dashboardOpensubscriptions => 'Ouvrir abonnements';

  @override
  String get dashboardChecksystemsecurity => 'Vérifier système et sécurité';

  @override
  String get dashboardOpensystem => 'Ouvrir systeme';

  @override
  String get dashboardPreparepartnerintegrations =>
      'Preparer integrations partenaires';

  @override
  String get dashboardOpenwebhooks => 'Ouvrir les webhooks';

  @override
  String get dashboardLoaderror =>
      'Impossible de charger le cockpit plateforme.';

  @override
  String get dashboardRiskhigh => 'Risque eleve';

  @override
  String get dashboardRiskmedium => 'Risque moyen';

  @override
  String get dashboardRisklow => 'Risque faible';

  @override
  String get dashboardNotprovided => 'Non renseigne';

  @override
  String get dashboardPendingAbsences => 'Absences en attente';

  @override
  String get dashboardDepartments => 'Départements';

  @override
  String get dashboardKpiTotal => 'total';

  @override
  String get dashboardKpiToProcess => 'à traiter';

  @override
  String get dashboardKpiActive => 'actifs';

  @override
  String get dashboardPriorityProcessAbsences =>
      'Traiter les absences en attente';

  @override
  String get dashboardPriorityCheckPresences =>
      'Vérifier les présences du jour';

  @override
  String get dashboardRecentActivityEmpty =>
      'Aucune activité récente à afficher pour ce tenant.';

  @override
  String get dashboardDashboardLoadError =>
      'Impossible de charger les données du dashboard.';

  @override
  String get dashboardTenantLoading => 'Chargement des données tenant...';

  @override
  String get dashboardWelcomeToday =>
      'Bienvenue ! Voici ce qui se passe aujourd\'hui.';

  @override
  String get dashboardGoLiveReady => 'Votre espace est prêt pour le go-live';

  @override
  String get dashboardGoLiveRequired => 'Actions requises avant le go-live';

  @override
  String dashboardGoLiveScore(Object score) {
    return 'Score $score/100 basé sur les données tenant, la communication, la paie, le pointage et l\'instrumentation client.';
  }

  @override
  String get dashboardTabToday => 'Aujourd\'hui';

  @override
  String get dashboardTabWeek => 'Cette semaine';

  @override
  String get dashboardSystemFallback => 'Système';

  @override
  String get dashboardShortcutEmployees => 'Employés';

  @override
  String get dashboardShortcutLeave => 'Congés';

  @override
  String get dashboardShortcutAttendance => 'Pointage';

  @override
  String get dashboardShortcutAttendanceHint => 'Voir votre état du jour.';

  @override
  String get dashboardShortcutAbsences => 'Absences';

  @override
  String get dashboardShortcutPayrollHint => 'Consulter vos documents de paie.';

  @override
  String get dashboardShortcutPreferencesHint =>
      'Votre interface suit vos préférences.';

  @override
  String get dashboardJournal => 'Journal';

  @override
  String get dashboardSeeAllActivity => 'Voir toute l\'activité';

  @override
  String get dashboardAiAssistant => 'Assistant intelligent';

  @override
  String get dashboardMessageSent => 'Message envoyé à l\'équipe';

  @override
  String get dashboardSending => 'Envoi...';

  @override
  String get dashboardSendYes => 'Oui, envoyer';

  @override
  String get dashboardLater => 'Plus tard';

  @override
  String get dashboardQuickActions => 'Actions rapides';

  @override
  String get dashboardQuickReports => 'Rapports';

  @override
  String get dashboardQuickExport => 'Export';

  @override
  String get dashboardEmpCheckin => 'Pointage';

  @override
  String get dashboardEmpCheckinHint => 'Voir votre état du jour.';

  @override
  String get dashboardEmpAbsences => 'Absences';

  @override
  String get dashboardEmpAbsencesHint => 'Suivre vos demandes et soldes.';

  @override
  String get dashboardEmpPaystubs => 'Bulletins';

  @override
  String get dashboardEmpPaystubsHint => 'Consulter vos documents de paie.';

  @override
  String get dashboardEmpLanguage => 'Langue';

  @override
  String get dashboardEmpLanguageHint =>
      'Votre interface suit vos préférences.';

  @override
  String get dashboardEmployeeSpace => 'Espace employé';

  @override
  String dashboardHello(Object name) {
    return 'Bonjour $name';
  }

  @override
  String get dashboardEmployeeIntro =>
      'Retrouvez vos actions utiles sans passer par les vues manager : pointage, absences, bulletins et langue.';

  @override
  String get dashboardSuperadminIntro =>
      'Cette surface est optimisée pour les espaces clients. L\'administration plateforme se fait depuis le dashboard admin dédié.';

  @override
  String get dashboardOpenAdminDashboard => 'Ouvrir le dashboard admin';

  @override
  String get dashboardAdminUrlHint =>
      'Configurez NEXT_PUBLIC_ADMIN_URL pour ajouter le lien direct vers l\'administration plateforme.';

  @override
  String get dashboardPlatformHealth => 'Santé plateforme';

  @override
  String get dashboardClientRequests => 'Demandes clients';

  @override
  String get dashboardTenantsAtRisk => 'Tenants à risque';

  @override
  String get dashboardPlatformDashboardHint =>
      'Disponible dans le dashboard plateforme.';

  @override
  String get dashboardSearchplaceholder => 'Rechercher...';

  @override
  String dashboardModulesactivesentence(Object active, Object locked) {
    return '$active modules actifs, $locked a activer selon votre plan.';
  }

  @override
  String get dashboardYourcompany => 'Votre entreprise';

  @override
  String get marketingOauthNavTitle => 'Marketing OAuth';

  @override
  String get marketingOauthTitle => 'Paramètres OAuth Marketing';

  @override
  String get marketingOauthSubtitle =>
      'Connectez vos comptes réseaux sociaux via Ayrshare';

  @override
  String get marketingOauthAyrshareInfo =>
      'Ces paramètres sont utilisés par Ayrshare pour publier sur vos réseaux sociaux.';

  @override
  String get marketingOauthSave => 'Enregistrer';

  @override
  String marketingOauthSavedOk(Object provider) {
    return 'Configuration $provider enregistrée';
  }

  @override
  String get marketingOauthProvidersLinkedinLabel => 'LinkedIn';

  @override
  String get marketingOauthProvidersLinkedinDescription =>
      'Connexion à l\'API LinkedIn Marketing';

  @override
  String get marketingOauthProvidersFacebookLabel => 'Facebook / Meta';

  @override
  String get marketingOauthProvidersFacebookDescription =>
      'Connexion à l\'API Facebook Graph';

  @override
  String get marketingOauthProvidersTwitterLabel => 'X (Twitter)';

  @override
  String get marketingOauthProvidersTwitterDescription =>
      'Connexion à l\'API Twitter v2';

  @override
  String get marketingOauthFieldsClientId => 'Client ID';

  @override
  String get marketingOauthFieldsClientSecret => 'Client Secret';

  @override
  String get marketingOauthFieldsRedirectUri => 'Redirect URI';

  @override
  String get marketingOauthFieldsSecretHint => '(laissez vide pour conserver)';

  @override
  String get marketingOauthFieldsPlaceholderId => 'Votre Client ID';

  @override
  String get marketingOauthFieldsPlaceholderSecret =>
      'Nouveau secret (optionnel)';

  @override
  String get marketingOauthFieldsPlaceholderUri =>
      'https://example.com/oauth/callback';

  @override
  String get marketingSocialexampleplaceholder =>
      'Ex: Leopardo RH — Reseaux sociaux';

  @override
  String get marketingPostcontentplaceholder => 'Contenu de la publication...';

  @override
  String attendanceSendingToServer(Object label) {
    return '$label vers le serveur...';
  }

  @override
  String attendanceRetryAfterFailure(Object label) {
    return '$label. Reessayez.';
  }

  @override
  String get attendanceAbsent => 'absent';

  @override
  String attendanceDaySummary(
      Object date, Object hours, Object range, Object status) {
    return 'Journée du $date, statut $status, $range, $hours.';
  }

  @override
  String get attendanceDayToday => 'Aujourd\'hui';

  @override
  String get attendanceDayYesterday => 'Hier';

  @override
  String get attendanceHourWorked => 'heure travaillée';

  @override
  String get attendanceHoursWorked => 'heures travaillées';

  @override
  String get attendanceInProgress => 'en cours';

  @override
  String get attendanceLate => 'en retard';

  @override
  String get attendanceNoClock => 'pas de pointage';

  @override
  String get attendanceOnTime => 'à l\'heure';

  @override
  String get attendanceOvertime => 'Heures supplémentaires';

  @override
  String attendanceTimeRange(Object from, Object to) {
    return 'de $from à $to';
  }

  @override
  String get attendanceFutureTimeError =>
      'Impossible de saisir une heure future';

  @override
  String get attendanceBreakFailure => 'Pause non confirmée';

  @override
  String get attendanceBreakHint => 'Ferme la session et marque une pause';

  @override
  String get attendanceBreakLoading => 'Envoi de la pause';

  @override
  String get attendanceBreakSuccess => 'Pause confirmée.';

  @override
  String get attendanceBreakTitle => 'Partir en pause';

  @override
  String get attendanceCheckinFailure => 'Arrivée non confirmée. Réessayez.';

  @override
  String get attendanceCheckinLabel => 'Arrivée';

  @override
  String get attendanceCheckinSending =>
      'Envoi de l\'arrivée vers le serveur...';

  @override
  String get attendanceCheckinSuccess => 'Arrivée confirmée.';

  @override
  String get attendanceCheckoutFailure => 'Départ non confirmé. Réessayez.';

  @override
  String get attendanceCheckoutLabel => 'Départ';

  @override
  String get attendanceCheckoutSending => 'Envoi du départ vers le serveur...';

  @override
  String get attendanceCheckoutSuccess => 'Départ confirmé.';

  @override
  String get attendanceCorrectionCheckinLabel => 'Arrivée réelle *';

  @override
  String get attendanceCorrectionCheckoutLabel => 'Départ réel';

  @override
  String get attendanceCorrectionDirectHint =>
      'La correction sera appliquée au dossier de pointage.';

  @override
  String get attendanceCorrectionNoLogWarning =>
      'Aucune ligne de pointage existante à modifier pour ce jour.';

  @override
  String get attendanceCorrectionReasonHint =>
      'Motif (ex: oubli de pointage à 8h)';

  @override
  String get attendanceCorrectionReasonRequired => 'Motif obligatoire';

  @override
  String get attendanceCorrectionRequestHint =>
      'La demande sera transmise au RH pour validation.';

  @override
  String get attendanceCorrectionSubmitDirect => 'Modifier';

  @override
  String get attendanceCorrectionSubmitRequest => 'Demander une modification';

  @override
  String get attendanceCorrectionTitle => 'Modifier le pointage';

  @override
  String get attendanceCorrectionCheckinTime => 'Heure d\'arrivée réelle';

  @override
  String get attendanceCorrectionCheckoutTime => 'Heure de départ réelle';

  @override
  String get attendanceCorrectionCheckinRequired =>
      'Saisir l\'heure d\'arrivée réelle';

  @override
  String get attendanceCorrectionSubmitError =>
      'Impossible d\'envoyer la modification pour le moment.';

  @override
  String get attendanceDailyEstimate => 'Gain estimé du jour';

  @override
  String get attendanceFingerprintEnable => 'Activer l\'empreinte (optionnel)';

  @override
  String get attendanceFingerprintEnabled => 'Empreinte activée (optionnel)';

  @override
  String get attendanceHistoryTitle => 'Historique';

  @override
  String get attendanceMenuEdit => 'Modifier';

  @override
  String get attendanceMenuMonthly => 'Mon mois complet';

  @override
  String get attendanceMenuProfile => 'Mon profil';

  @override
  String get attendanceMissionFailure => 'Mission non confirmée';

  @override
  String get attendanceMissionHint => 'Temps de travail hors site habituel';

  @override
  String get attendanceMissionLoading => 'Envoi mission';

  @override
  String get attendanceMissionSuccess => 'Mission démarrée.';

  @override
  String get attendanceMissionTitle => 'Mission';

  @override
  String get attendanceNone => 'Aucun';

  @override
  String get attendanceOtherLabel => 'Autre';

  @override
  String get attendanceOvertimeFailure =>
      'Heures supplémentaires non confirmées';

  @override
  String get attendanceOvertimeHint => 'Démarrer une session d\'heures supp';

  @override
  String get attendanceOvertimeLoading => 'Envoi heures supplémentaires';

  @override
  String get attendanceOvertimeShort => 'Heure supp';

  @override
  String get attendanceOvertimeSuccess => 'Heures supplémentaires démarrées.';

  @override
  String get attendanceOvertimeTitle => 'Heures supplémentaires';

  @override
  String get attendancePauseLabel => 'Pause';

  @override
  String get attendancePreferencesTitle => 'Préférences';

  @override
  String get attendancePressToCheckin =>
      'Appuyez pour enregistrer votre arrivée';

  @override
  String get attendancePressToCheckout =>
      'Appuyez pour enregistrer votre départ';

  @override
  String get attendanceResumeFailure => 'Reprise non confirmée';

  @override
  String get attendanceResumeHint => 'Reprendre après une pause ou une sortie';

  @override
  String get attendanceResumeLoading => 'Envoi reprise';

  @override
  String get attendanceResumeSuccess => 'Reprise confirmée.';

  @override
  String get attendanceResumeTitle => 'Reprise';

  @override
  String get attendanceRoleEmployee => 'Employé';

  @override
  String get attendanceRoleEmployee2 => 'Employé';

  @override
  String get attendanceRoleFinance => 'Finance';

  @override
  String get attendanceRoleHr => 'Responsable RH';

  @override
  String get attendanceRoleManager => 'Manager';

  @override
  String get attendanceRolePrincipal => 'Manager principal';

  @override
  String get attendanceSaving => 'Enregistrement en cours...';

  @override
  String get attendanceSettingsTitle => 'Paramètres';

  @override
  String get attendanceStatusComplete => 'Complet';

  @override
  String get attendanceStatusInProgress => 'En cours';

  @override
  String get attendanceStatusLate => 'Retard';

  @override
  String get attendanceStatusPointer => 'À pointer';

  @override
  String get attendanceSyncTitle => 'Synchronisation';

  @override
  String get attendanceTasksTitle => 'Tâches du jour';

  @override
  String get attendanceTasksLoading => 'Chargement des tâches du jour...';

  @override
  String get attendanceTasksUnavailable => 'Tâches indisponibles';

  @override
  String get attendanceTasksUnavailableHint =>
      'Le pointage reste utilisable. Réessayez après synchronisation.';

  @override
  String get attendanceTasksEmpty => 'Aucune tâche aujourd\'hui';

  @override
  String get attendanceTasksEmptyHint =>
      'Vous pourrez pointer normalement. Les tâches assignées apparaîtront ici.';

  @override
  String get attendanceTasksCloseHint =>
      'Clôturez ce qui est réalisé avant votre départ.';

  @override
  String get attendanceThisWeek => 'CETTE SEMAINE';

  @override
  String get attendanceToday => 'AUJOURD\'HUI';

  @override
  String get attendanceTrainingLabel => 'Formation';

  @override
  String get attendanceTravelFailure => 'Déplacement non confirmé';

  @override
  String get attendanceTravelHint => 'Temps de déplacement professionnel';

  @override
  String get attendanceTravelLoading => 'Envoi déplacement';

  @override
  String get attendanceTravelSuccess => 'Déplacement démarré.';

  @override
  String get attendanceTravelTitle => 'Déplacement';

  @override
  String get attendanceWeekEarnings => 'Gain estimé';

  @override
  String get attendanceWeekHours => 'Heures semaine';

  @override
  String get attendanceWeekLate => 'Retard cumulé';

  @override
  String get attendanceWeekUnavailable =>
      'Semaine indisponible pour l\'instant. Le pointage reste utilisable.';

  @override
  String get attendanceWorkTypeTitle => 'Type de pointage';

  @override
  String get attendanceSessions => 'Sessions';

  @override
  String attendanceBreakMinutes(Object minutes) {
    return '$minutes min';
  }

  @override
  String get holidaysPageTitle => 'Jours fériés par pays';

  @override
  String get holidaysPageSubtitle =>
      'Calendrier des jours fériés utilisés par le moteur de paie pour calculer les jours ouvrés réels. Les fériés fixes (issue #1811) et les fêtes islamiques mobiles (issue #1812) alimentent automatiquement les bulletins de paie de tous les pays concernés.';

  @override
  String get holidaysCountry => 'Pays';

  @override
  String get holidaysYear => 'Année';

  @override
  String get holidaysAdd => 'Ajouter un jour férié';

  @override
  String get holidaysThDate => 'Date';

  @override
  String get holidaysThName => 'Nom';

  @override
  String get holidaysThType => 'Type';

  @override
  String get holidaysThScope => 'Portée';

  @override
  String get holidaysThActions => 'Actions';

  @override
  String get holidaysScopeNational => 'National';

  @override
  String get holidaysScopeCompany => 'Entreprise';

  @override
  String get holidaysEdit => 'Modifier';

  @override
  String get holidaysDelete => 'Supprimer';

  @override
  String holidaysEmpty(Object country, Object year) {
    return 'Aucun jour férié pour $country / $year.';
  }

  @override
  String get holidaysModalEdittitle => 'Modifier le jour férié';

  @override
  String get holidaysModalNewtitle => 'Nouveau jour férié';

  @override
  String get holidaysNameLabel => 'Nom';

  @override
  String get holidaysDateLabel => 'Date';

  @override
  String get holidaysTypeLabel => 'Type';

  @override
  String get holidaysTypeFixed => 'Fixe';

  @override
  String get holidaysTypeIslamic => 'Islamique';

  @override
  String get holidaysTypeChristian => 'Chrétien';

  @override
  String get holidaysTypeCustom => 'Personnalisé';

  @override
  String get holidaysRecurring => 'Récurent chaque année';

  @override
  String get holidaysCancel => 'Annuler';

  @override
  String get holidaysSaving => 'Enregistrement…';

  @override
  String get holidaysSave => 'Enregistrer';

  @override
  String get holidaysErrorsLoad => 'Impossible de charger les jours fériés.';

  @override
  String get holidaysErrorsSave => 'Impossible d\'enregistrer le jour férié.';

  @override
  String get holidaysErrorsDelete => 'Impossible de supprimer.';

  @override
  String get holidaysSuccessSaved => 'Jour férié enregistré.';

  @override
  String get holidaysSuccessDeleted => 'Jour férié supprimé.';

  @override
  String get holidaysConfirmDelete => 'Supprimer « :name » ?';

  @override
  String get holidaysNavTitle => 'Jours fériés';

  @override
  String get holidaysEditTitle => 'Modifier le jour férié';

  @override
  String get holidaysAddTitle => 'Nouveau jour férié';

  @override
  String get holidaysLoadError => 'Impossible de charger les jours fériés.';

  @override
  String get holidaysSaved => 'Jour férié enregistré.';

  @override
  String get holidaysSaveError => 'Impossible d\'enregistrer le jour férié.';

  @override
  String get holidaysDeleted => 'Jour férié supprimé.';

  @override
  String get holidaysDeleteError => 'Impossible de supprimer.';

  @override
  String get holidaysCountriesDz => 'Algérie';

  @override
  String get holidaysCountriesCm => 'Cameroun';

  @override
  String get holidaysCountriesCi => 'Côte d\'Ivoire';

  @override
  String get holidaysCountriesSn => 'Sénégal';

  @override
  String get holidaysCountriesMa => 'Maroc';

  @override
  String get holidaysCountriesTn => 'Tunisie';

  @override
  String get holidaysIslamicTitle => 'Fêtes islamiques';

  @override
  String get holidaysIslamicSubtitle =>
      'Dates mobiles du calendrier hégirien (Aïd, Maouloud, Tamkharit…) saisies par année. Elles s\'appliquent automatiquement aux pays CEMAC/CEDEAO + DZ/MA/TN.';

  @override
  String holidaysIslamicConfirm(Object year) {
    return 'Confirmer $year';
  }

  @override
  String holidaysIslamicBannerUnconfirmed(Object count, Object year) {
    return '$count fête(s) islamique(s) non confirmée(s) pour $year — vérifiez les dates avant la clôture de paie.';
  }

  @override
  String get holidaysIslamicThName => 'Fête';

  @override
  String get holidaysIslamicThDate => 'Date grégorienne';

  @override
  String get holidaysIslamicThDuration => 'Durée';

  @override
  String get holidaysIslamicThCountries => 'Pays';

  @override
  String get holidaysIslamicThStatus => 'Statut';

  @override
  String get holidaysIslamicThActions => 'Actions';

  @override
  String holidaysIslamicDurationDays(Object count) {
    return '$count jour(s)';
  }

  @override
  String get holidaysIslamicStatusConfirmed => 'Confirmé';

  @override
  String get holidaysIslamicStatusApproximate => 'Approximatif';

  @override
  String get holidaysIslamicEdit => 'Modifier';

  @override
  String holidaysIslamicEmpty(Object year) {
    return 'Aucune fête islamique enregistrée pour $year.';
  }

  @override
  String holidaysIslamicModalTitle(Object name) {
    return 'Modifier $name';
  }

  @override
  String get holidaysIslamicLabelDate => 'Date grégorienne';

  @override
  String get holidaysIslamicLabelDuration => 'Durée (jours)';

  @override
  String get holidaysIslamicLabelConfirmed => 'Date confirmée (officielle)';

  @override
  String get holidaysIslamicLoadError =>
      'Erreur lors du chargement du calendrier islamique.';

  @override
  String holidaysIslamicConfirmDialog(Object year) {
    return 'Confirmer toutes les dates islamiques de $year ?';
  }

  @override
  String get holidaysIslamicSaved => 'Fête islamique enregistrée.';

  @override
  String get holidaysIslamicSaveError => 'Erreur lors de l\'enregistrement.';

  @override
  String get holidaysIslamicConfirmError =>
      'Erreur lors de la confirmation des dates.';

  @override
  String holidaysIslamicConfirmYearSuccess(Object count) {
    return '$count date(s) confirmée(s).';
  }

  @override
  String holidaysIslamicDeleteConfirmDialog(Object name) {
    return 'Supprimer « $name » ?';
  }

  @override
  String get taxRatesTitle => 'Taux légaux — validation';

  @override
  String get taxRatesSubtitle =>
      'Barèmes fiscaux et cotisations sociales utilisés par le moteur de paie. Toute modification passe par un workflow de validation à double signature (comptable → platform admin) avec audit trail immuable.';

  @override
  String get taxRatesPendingTitle => 'En attente de validation';

  @override
  String get taxRatesPendingEmpty =>
      'Aucune modification en attente de validation.';

  @override
  String get taxRatesRatesTitle => 'Taux en vigueur';

  @override
  String get taxRatesRatesSubtitle =>
      'Seules les lignes actives sont utilisées dans les bulletins.';

  @override
  String get taxRatesRatesEmpty => 'Aucun taux enregistré.';

  @override
  String get taxRatesPropose => 'Proposer une modification';

  @override
  String get taxRatesThType => 'Type';

  @override
  String get taxRatesThName => 'Intitulé';

  @override
  String get taxRatesThCountry => 'Pays';

  @override
  String get taxRatesThRate => 'Taux';

  @override
  String get taxRatesThEffective => 'Effet au';

  @override
  String get taxRatesThStatus => 'Statut';

  @override
  String get taxRatesThActions => 'Actions';

  @override
  String get taxRatesThAction => 'Action';

  @override
  String get taxRatesThActor => 'Acteur';

  @override
  String get taxRatesThReason => 'Motif';

  @override
  String get taxRatesThDate => 'Date';

  @override
  String get taxRatesTypeSlab => 'Barème';

  @override
  String get taxRatesTypeContribution => 'Cotisation';

  @override
  String get taxRatesStatusActive => '🟢 Active';

  @override
  String get taxRatesStatusPending => '🟡 En attente';

  @override
  String get taxRatesStatusDraft => '⚪ Brouillon';

  @override
  String get taxRatesStatusSuperseded => '🔴 Remplacée';

  @override
  String get taxRatesSubmit => 'Soumettre';

  @override
  String get taxRatesHistory => 'Historique';

  @override
  String get taxRatesApprove => 'Approuver';

  @override
  String get taxRatesReject => 'Rejeter';

  @override
  String get taxRatesModalTitle => 'Proposer une modification de taux';

  @override
  String get taxRatesLegalRef => 'Référence légale';

  @override
  String get taxRatesLegalRefRequired =>
      'La référence légale est obligatoire (elle est tracée dans l\'historique).';

  @override
  String get taxRatesCancel => 'Annuler';

  @override
  String get taxRatesSaving => 'Enregistrement…';

  @override
  String get taxRatesSave => 'Enregistrer';

  @override
  String get taxRatesRejectModalTitle => 'Rejeter la modification';

  @override
  String get taxRatesRejectReason => 'Motif du rejet (obligatoire)';

  @override
  String get taxRatesHistoryTitle => 'Historique des modifications';

  @override
  String get taxRatesHistoryEmpty => 'Aucune entrée d\'historique.';

  @override
  String get taxRatesHistoryCreated => 'Créé';

  @override
  String get taxRatesHistorySubmitted => 'Soumis';

  @override
  String get taxRatesHistoryApproved => 'Approuvé';

  @override
  String get taxRatesHistoryRejected => 'Rejeté';

  @override
  String get taxRatesHistorySuperseded => 'Remplacé';

  @override
  String get taxRatesClose => 'Fermer';

  @override
  String get taxRatesLoadError => 'Impossible de charger les taux.';

  @override
  String get taxRatesSaved => 'Proposition enregistrée.';

  @override
  String get taxRatesSaveError => 'Impossible d\'enregistrer la proposition.';

  @override
  String get taxRatesSubmitted => 'Modification soumise pour validation.';

  @override
  String get taxRatesSubmitError => 'Impossible de soumettre la modification.';

  @override
  String get taxRatesApproved => 'Modification approuvée et active.';

  @override
  String get taxRatesApproveError => 'Impossible d\'approuver la modification.';

  @override
  String get taxRatesRejected => 'Modification rejetée (retour en brouillon).';

  @override
  String get taxRatesRejectError => 'Impossible de rejeter la modification.';

  @override
  String get taxRatesHistoryError => 'Impossible de charger l\'historique.';

  @override
  String get taxRatesStatusOnly => 'Lecture seule (action tenant)';

  @override
  String get taxSlabsTitle => 'Barèmes fiscaux par pays';

  @override
  String get taxSlabsSubtitle =>
      'Barèmes IRG/IRPP/ITSAS utilisés par le moteur de paie. Gestion nationale (platform admin), simulateur d\'impact en temps réel, sans persistance.';

  @override
  String get taxSlabsThCountry => 'Pays';

  @override
  String get taxSlabsScope => 'Portée';

  @override
  String get taxSlabsScopeNational => 'National';

  @override
  String get taxSlabsScopeCompany => 'Spécifique entreprise';

  @override
  String get taxSlabsNationalNote =>
      'portée nationale — les overrides entreprise restent côté tenant';

  @override
  String get taxSlabsThMin => 'Tranche min';

  @override
  String get taxSlabsThMax => 'Tranche max';

  @override
  String get taxSlabsThRate => 'Taux';

  @override
  String get taxSlabsThDeduction => 'Déduction fixe';

  @override
  String get taxSlabsThEffective => 'Effet au';

  @override
  String get taxSlabsThActions => 'Actions';

  @override
  String get taxSlabsEdit => 'Modifier';

  @override
  String get taxSlabsDelete => 'Supprimer';

  @override
  String get taxSlabsAdd => 'Ajouter une tranche';

  @override
  String get taxSlabsReset => 'Réinitialiser aux valeurs légales';

  @override
  String get taxSlabsEmpty => 'Aucune tranche enregistrée pour ce pays.';

  @override
  String get taxSlabsEditTitle => 'Modifier la tranche';

  @override
  String get taxSlabsAddTitle => 'Ajouter une tranche';

  @override
  String get taxSlabsLegalRef => 'Référence légale';

  @override
  String get taxSlabsCancel => 'Annuler';

  @override
  String get taxSlabsSaving => 'Enregistrement…';

  @override
  String get taxSlabsSave => 'Enregistrer';

  @override
  String get taxSlabsSimulatorTitle => 'Simulateur d\'impact';

  @override
  String get taxSlabsSimulatorSubtitle =>
      'Saisissez un salaire brut : le calcul (cotisations, assiette, impôt par tranche, net, coût employeur) est exécuté par le moteur de paie réel, sans rien persister.';

  @override
  String get taxSlabsSimGross => 'Salaire brut';

  @override
  String get taxSlabsSimRun => 'Simuler';

  @override
  String get taxSlabsSimRunning => 'Calcul…';

  @override
  String get taxSlabsSimSocial => 'Cotisations (salariales)';

  @override
  String get taxSlabsSimTax => 'Impôt';

  @override
  String get taxSlabsSimNet => 'Net';

  @override
  String get taxSlabsSimBase => 'Assiette';

  @override
  String get taxSlabsSimSlabTax => 'Impôt tranche';

  @override
  String get taxSlabsLoadError => 'Impossible de charger le barème.';

  @override
  String get taxSlabsSaveError => 'Impossible d\'enregistrer la tranche.';

  @override
  String get taxSlabsSaved => 'Tranche mise à jour.';

  @override
  String get taxSlabsCreated => 'Tranche créée.';

  @override
  String get taxSlabsDeleted => 'Tranche supprimée.';

  @override
  String get taxSlabsDeleteError => 'Impossible de supprimer.';

  @override
  String taxSlabsDeleteConfirm(Object name) {
    return 'Supprimer la tranche « $name » ?';
  }

  @override
  String get taxSlabsResetConfirm =>
      'Réinitialiser aux valeurs légales par défaut ? Les tranches actuelles seront remplacées.';

  @override
  String get taxSlabsResetDone => 'Barème réinitialisé.';

  @override
  String get taxSlabsResetError => 'Réinitialisation impossible.';

  @override
  String taxSlabsDefaultName(Object country) {
    return '$country tranche légale';
  }

  @override
  String get taxSlabsSimError => 'Simulation impossible.';

  @override
  String get socialContribTitle => 'Cotisations sociales par pays';

  @override
  String get socialContribSubtitle =>
      'CNPS, CNSS, IPRES, CNAS… — taux, plafonds et types (salariale/patronale) par pays. Gestion nationale + simulateur avec/sans plafond et comparateur 2 pays.';

  @override
  String get socialContribThCountry => 'Pays';

  @override
  String get socialContribThOrg => 'Organisme';

  @override
  String get socialContribThCode => 'Code';

  @override
  String get socialContribThType => 'Type';

  @override
  String get socialContribThRate => 'Taux';

  @override
  String get socialContribThCap => 'Plafond';

  @override
  String get socialContribThEffective => 'Effet au';

  @override
  String get socialContribThActions => 'Actions';

  @override
  String get socialContribTypeAll => 'Tous';

  @override
  String get socialContribTypeEmployee => 'Salariale';

  @override
  String get socialContribTypeEmployer => 'Patronale';

  @override
  String get socialContribAdd => 'Ajouter une cotisation';

  @override
  String get socialContribEdit => 'Modifier';

  @override
  String get socialContribDelete => 'Supprimer';

  @override
  String get socialContribEmpty =>
      'Aucune cotisation enregistrée pour ce pays.';

  @override
  String get socialContribAddTitle => 'Ajouter une cotisation';

  @override
  String get socialContribEditTitle => 'Modifier la cotisation';

  @override
  String get socialContribCancel => 'Annuler';

  @override
  String get socialContribSaving => 'Enregistrement…';

  @override
  String get socialContribSave => 'Enregistrer';

  @override
  String get socialContribSimTitle => 'Simulateur & comparateur';

  @override
  String get socialContribSimSubtitle =>
      'Saisissez un brut : décomposition salariale/patronale, impôt et coût total employeur, pour deux pays côte à côte (avec ou sans plafond légal).';

  @override
  String get socialContribSimGross => 'Salaire brut';

  @override
  String get socialContribCompareCountry => 'Pays comparé';

  @override
  String get socialContribIgnoreCaps => 'Sans plafond légal';

  @override
  String get socialContribSimEmployee => 'Cotisations salariales';

  @override
  String get socialContribSimEmployer => 'Cotisations patronales';

  @override
  String get socialContribSimTax => 'Impôt';

  @override
  String get socialContribTotalCost => 'Coût total employeur';

  @override
  String get socialContribLoadError => 'Impossible de charger les cotisations.';

  @override
  String get socialContribSaveError =>
      'Impossible d\'enregistrer la cotisation.';

  @override
  String get socialContribSaved => 'Cotisation mise à jour.';

  @override
  String get socialContribCreated => 'Cotisation créée.';

  @override
  String get socialContribDeleted => 'Cotisation supprimée.';

  @override
  String get socialContribDeleteError => 'Suppression impossible.';

  @override
  String socialContribDeleteConfirm(Object name) {
    return 'Supprimer « $name » ?';
  }

  @override
  String get payrollConfidenceLabel => 'Confiance des règles de paie';

  @override
  String get payrollConfidenceLevelProduction => 'Production';

  @override
  String get payrollConfidenceLevelPilot => 'Pilote';

  @override
  String get payrollConfidenceLevelPlaceholder => 'Maquette';

  @override
  String get payrollConfidenceLevelUnknown => 'Inconnu';

  @override
  String payrollConfidenceProductionMessage(Object country) {
    return 'Règles validées et utilisées en production pour $country. Confirmez toujours les taux en vigueur auprès d\'un conseil local avant de vous appuyer sur ces montants pour des déclarations obligatoires.';
  }

  @override
  String payrollConfidencePilotMessage(Object country) {
    return 'Règles pilotes pour $country : montants issus de références publiques générales (code du travail) mais non encore validés juridiquement sur place. Confirmez avec un conseil juridique ou fiscal local avant de vous appuyer sur ces chiffres (tranches d\'impôt, cotisations sociales, seuils d\'heures supplémentaires) pour vos obligations légales.';
  }

  @override
  String payrollConfidencePlaceholderMessage(Object country) {
    return 'Maquette sans valeurs pour $country : les montants d\'impôt et de cotisations sociales ne sont pas encore documentés et ne doivent pas être utilisés pour de vrais cycles de paie tant qu\'ils n\'ont pas été remplacés.';
  }

  @override
  String payrollConfidenceUnknownMessage(Object country) {
    return 'Aucune règle de paie n\'est disponible pour $country : le calcul de paie n\'est pas disponible pour ce pays.';
  }

  @override
  String get pricingPageHeroBadge => 'Tarification transparente';

  @override
  String get pricingPageHeroHeadline =>
      'Des tarifs pensés pour les équipes terrain';

  @override
  String get pricingPageHeroSubheadline =>
      'Commencez gratuitement — sans carte bancaire — et passez à un plan payant quand vous êtes prêt.';

  @override
  String get pricingPageHeroPrimary => 'Commencer gratuitement';

  @override
  String get pricingPageHeroSecondary => 'Parler à un expert';

  @override
  String get pricingPagePlansBadge => 'Nos plans';

  @override
  String get pricingPagePlansTitle =>
      'Un plan pour chaque étape de votre croissance';

  @override
  String get pricingPagePlansSubtitle =>
      'Commencez petit, montez en puissance sans changer de plateforme.';

  @override
  String get pricingPagePlansMonthly => 'Mensuel';

  @override
  String get pricingPagePlansAnnual => 'Annuel';

  @override
  String get pricingPagePlansCustomprice => 'Sur devis';

  @override
  String get pricingPagePlansPeriodmonthly => '/mois';

  @override
  String get pricingPagePlansPeriodannual => '/mois facturé annuellement';

  @override
  String get pricingPagePlansTrialnote => 'Essai gratuit · Aucune CB requise';

  @override
  String get pricingPageCurrencyLabel => 'Afficher les prix en';

  @override
  String get pricingPageCurrencyApprox =>
      'Conversion approximative depuis le prix de référence en EUR ; le tarif contractuel reste fixé en EUR.';

  @override
  String get pricingPageTrustItems0 => 'Plan gratuit sans CB';

  @override
  String get pricingPageTrustItems1 => 'Support inclus dès le premier jour';

  @override
  String get pricingPageTrustItems2 => 'Données hébergées en Europe';

  @override
  String get pricingPageTrustItems3 => 'Résiliation à tout moment';

  @override
  String get pricingPageComparisonBadge => 'Comparaison complète';

  @override
  String get pricingPageComparisonTitle => 'Tout ce qui est inclus';

  @override
  String get pricingPageComparisonSubtitle => 'par plan';

  @override
  String get pricingPageComparisonFeaturecolumn => 'Fonctionnalité';

  @override
  String get pricingPageComparisonCategories0Name => 'Gestion RH';

  @override
  String get pricingPageComparisonCategories0Features0Name =>
      'Pointage web & mobile';

  @override
  String get pricingPageComparisonCategories0Features0Free => 'Web seulement';

  @override
  String get pricingPageComparisonCategories0Features1Name =>
      'Absences & congés';

  @override
  String get pricingPageComparisonCategories0Features2Name =>
      'Calendrier partagé';

  @override
  String get pricingPageComparisonCategories0Features3Name =>
      'Onboarding guidé';

  @override
  String get pricingPageComparisonCategories0Features4Name =>
      'Évaluations & performance';

  @override
  String get pricingPageComparisonCategories0Features5Name =>
      'Organigramme dynamique';

  @override
  String get pricingPageComparisonCategories1Name => 'Paie & finance';

  @override
  String get pricingPageComparisonCategories1Features0Name =>
      'Calcul automatisé de la paie';

  @override
  String get pricingPageComparisonCategories1Features1Name =>
      'Bulletins de paie PDF';

  @override
  String get pricingPageComparisonCategories1Features2Name =>
      'Exports comptables';

  @override
  String get pricingPageComparisonCategories1Features3Name =>
      'Avances sur salaire';

  @override
  String get pricingPageComparisonCategories1Features4Name =>
      'Multi-pays & multi-devises';

  @override
  String get pricingPageComparisonCategories1Features5Name =>
      'Conformité légale avancée';

  @override
  String get pricingPageComparisonCategories2Name => 'Terrain & mobile';

  @override
  String get pricingPageComparisonCategories2Features0Name =>
      'App mobile Employee';

  @override
  String get pricingPageComparisonCategories2Features1Name =>
      'App mobile Manager';

  @override
  String get pricingPageComparisonCategories2Features2Name => 'Mode hors-ligne';

  @override
  String get pricingPageComparisonCategories2Features3Name =>
      'Intégration ZKTeco biométrie';

  @override
  String get pricingPageComparisonCategories2Features4Name =>
      'Kiosque RH dédié';

  @override
  String get pricingPageComparisonCategories2Features5Name =>
      'GPS & géofencing';

  @override
  String get pricingPageComparisonCategories3Name => 'Sécurité & intégrations';

  @override
  String get pricingPageComparisonCategories3Features0Name =>
      'Coffre-fort documentaire';

  @override
  String get pricingPageComparisonCategories3Features1Name =>
      'API REST & Webhooks';

  @override
  String get pricingPageComparisonCategories3Features2Name => 'SSO SAML / OIDC';

  @override
  String get pricingPageComparisonCategories3Features3Name =>
      'Audit trail immuable';

  @override
  String get pricingPageComparisonCategories3Features4Name =>
      'Schéma PostgreSQL isolé';

  @override
  String get pricingPageComparisonCategories3Features5Name =>
      'SLA dédié & support prioritaire';

  @override
  String get pricingPageFaqBadge => 'FAQ tarifs';

  @override
  String get pricingPageFaqTitle => 'Questions fréquentes';

  @override
  String get pricingPageFaqSubtitle =>
      'Les points à vérifier avant de démarrer';

  @override
  String get pricingPageFaqAll => 'Tous';

  @override
  String get pricingPageFaqCategories0 => 'Facturation';

  @override
  String get pricingPageFaqCategories1 => 'Essai';

  @override
  String get pricingPageFaqCategories2 => 'Support';

  @override
  String get pricingPageFaqCategories3 => 'Sécurité';

  @override
  String get pricingPageFaqCategories4 => 'Technique';

  @override
  String get pricingPageFaqItems0Question => 'Que comprend le plan Pilot ?';

  @override
  String get pricingPageFaqItems0Answer =>
      'Le plan Pilot à 29 €/mois inclut jusqu\'à 30 employés, le pointage web et mobile, les absences et congés, les dossiers employés et les bulletins de paie PDF. Essai gratuit de 14 jours, sans carte bancaire.';

  @override
  String get pricingPageFaqItems0Category => 'Essai';

  @override
  String get pricingPageFaqItems1Question => 'Puis-je changer de plan ?';

  @override
  String get pricingPageFaqItems1Answer =>
      'Oui, à tout moment. Upgrade immédiat, downgrade au prochain cycle. Aucun frais caché.';

  @override
  String get pricingPageFaqItems1Category => 'Facturation';

  @override
  String get pricingPageFaqItems2Question =>
      'Comment fonctionne la facturation ?';

  @override
  String get pricingPageFaqItems2Answer =>
      'Chaque plan inclut un prix fixe par mois avec un plafond d\'employés inclus (30 pour Pilot, 200 pour Operations, illimité pour Enterprise). Pas de supplément par employé actif.';

  @override
  String get pricingPageFaqItems2Category => 'Facturation';

  @override
  String get pricingPageFaqItems3Question =>
      'L\'essai est-il vraiment gratuit ?';

  @override
  String get pricingPageFaqItems3Answer =>
      'Oui. Essai gratuit complet — 30 jours sur le plan Free, 14 jours sur les plans payants. Aucune carte bancaire requise pour s\'inscrire.';

  @override
  String get pricingPageFaqItems3Category => 'Essai';

  @override
  String get pricingPageFaqItems4Question =>
      'Que se passe-t-il à la fin de l\'essai ?';

  @override
  String get pricingPageFaqItems4Answer =>
      'Vous choisissez un plan ou vos données restent archivées 14 jours supplémentaires. Aucune facturation automatique sans votre accord.';

  @override
  String get pricingPageFaqItems4Category => 'Essai';

  @override
  String get pricingPageFaqItems5Question => 'Quel support est disponible ?';

  @override
  String get pricingPageFaqItems5Answer =>
      'Pilot : support email sous 48h. Operations : support prioritaire sous 24h. Enterprise : account manager dédié + SLA contractuel.';

  @override
  String get pricingPageFaqItems5Category => 'Support';

  @override
  String get pricingPageFaqItems6Question => 'Où sont hébergées mes données ?';

  @override
  String get pricingPageFaqItems6Answer =>
      'En Europe (Render EU / Supabase EU). Chiffrement AES-256 au repos, TLS 1.3 en transit. Isolation par tenant garantie.';

  @override
  String get pricingPageFaqItems6Category => 'Sécurité';

  @override
  String get pricingPageFaqItems7Question => 'Êtes-vous conformes RGPD ?';

  @override
  String get pricingPageFaqItems7Answer =>
      'Oui. DPA disponible, données exclusivement en Europe, droit à l\'effacement implémenté, exports de données sur demande.';

  @override
  String get pricingPageFaqItems7Category => 'Sécurité';

  @override
  String get pricingPageFaqItems8Question => 'L\'API est-elle disponible ?';

  @override
  String get pricingPageFaqItems8Answer =>
      'L\'API REST et les webhooks sont disponibles à partir du plan Operations. Sur Pilot, vous pouvez exporter vos données en CSV/Excel.';

  @override
  String get pricingPageFaqItems8Category => 'Technique';

  @override
  String get pricingPageFaqMoretitle => 'Une autre question ?';

  @override
  String get pricingPageFaqContactsupport => 'Contacter le support';

  @override
  String get pricingPageCtaBadge => 'Prêt à démarrer';

  @override
  String get pricingPageCtaHeadline => 'Lancez vos RH terrain dès aujourd\'hui';

  @override
  String get pricingPageCtaSubheadline =>
      'Rejoignez les équipes qui ont réduit leur temps de paie de 2h à 8 minutes.';

  @override
  String get pricingPageCtaPrimary => 'Démarrer gratuitement';

  @override
  String get pricingPageCtaSecondary => 'Contacter les ventes';

  @override
  String get pricingPageBadgesPopular => 'Le plus populaire';

  @override
  String get pricingPageBadgesFree => '100% Gratuit';

  @override
  String get pricingPageBadgesFreeprice => 'Gratuit';

  @override
  String get pricingPageBadgesFreenote => 'Sans carte bancaire · Pour toujours';

  @override
  String get pricingPageBadgesFreetag => 'gratuit';

  @override
  String get pricingPlansCustomprice => 'Sur devis';

  @override
  String get pricingPlansFreeDescription =>
      'Pour démarrer sans engagement — idéal pour les équipes de 5 personnes';

  @override
  String get pricingPlansFreePricenote =>
      '14 jours d\'essai gratuits. Jusqu\'à 5 employés.';

  @override
  String get pricingPlansFreeEmployeelimit => 'Jusqu\'à 5 employés';

  @override
  String get pricingPlansFreeCta => 'Commencer gratuitement';

  @override
  String get pricingPlansFreeFeatures0 => 'Pointage web et mobile basique';

  @override
  String get pricingPlansFreeFeatures1 => 'Absences et congés (consultation)';

  @override
  String get pricingPlansFreeFeatures2 => 'Dossiers employés essentiels';

  @override
  String get pricingPlansFreeFeatures3 => 'Bulletins de paie PDF';

  @override
  String get pricingPlansFreeFeatures4 => 'App Employee incluse';

  @override
  String get pricingPlansFreeFeatures5 => 'Support communautaire';

  @override
  String get pricingPlansFreePeriod => '/mois';

  @override
  String get pricingPlansFreeAnnualperiod => '/mois';

  @override
  String get pricingPlansPilotDescription =>
      'Pour piloter Leopardo sur un site, une équipe ou une agence';

  @override
  String get pricingPlansPilotPricenote =>
      '14 jours offerts. Jusqu\'à 30 employés.';

  @override
  String get pricingPlansPilotEmployeelimit => 'Jusqu\'à 30 employés';

  @override
  String get pricingPlansPilotCta => 'Lancer un essai gratuit';

  @override
  String get pricingPlansPilotFeatures0 => 'Pointage web et mobile';

  @override
  String get pricingPlansPilotFeatures1 => 'Absences, congés et soldes';

  @override
  String get pricingPlansPilotFeatures2 => 'Dossiers employés et documents RH';

  @override
  String get pricingPlansPilotFeatures3 =>
      'Bulletins de paie PDF et exports essentiels';

  @override
  String get pricingPlansPilotFeatures4 => 'Portail client et espace manager';

  @override
  String get pricingPlansPilotFeatures5 => 'Apps Employee et Manager incluses';

  @override
  String get pricingPlansPilotFeatures6 => 'Support email sous 48h';

  @override
  String get pricingPlansPilotPeriod => '/mois';

  @override
  String get pricingPlansPilotAnnualperiod =>
      '/mois · 290 €/an facturé annuellement';

  @override
  String get pricingPlansOperationsDescription =>
      'Pour les PME multi-équipes qui pilotent terrain, RH et paie';

  @override
  String get pricingPlansOperationsPricenote =>
      '14 jours offerts. Jusqu\'à 200 employés.';

  @override
  String get pricingPlansOperationsEmployeelimit => 'Jusqu\'à 200 employés';

  @override
  String get pricingPlansOperationsCta => 'Essayer Operations';

  @override
  String get pricingPlansOperationsFeatures0 => 'Tout Pilot, plus :';

  @override
  String get pricingPlansOperationsFeatures1 =>
      'Paie multi-pays et validations RH';

  @override
  String get pricingPlansOperationsFeatures2 =>
      'Managers, équipes et workflows d\'approbation';

  @override
  String get pricingPlansOperationsFeatures3 =>
      'Pointage ZKTeco, kiosque et mobile';

  @override
  String get pricingPlansOperationsFeatures4 =>
      'Analytics RH, readiness et exports avancés';

  @override
  String get pricingPlansOperationsFeatures5 => 'API, webhooks et intégrations';

  @override
  String get pricingPlansOperationsFeatures6 => 'Support prioritaire sous 24h';

  @override
  String get pricingPlansOperationsPeriod => '/mois';

  @override
  String get pricingPlansOperationsAnnualperiod =>
      '/mois · 790 €/an facturé annuellement';

  @override
  String get pricingPlansEnterpriseDescription =>
      'Pour groupes multi-pays, franchises, réseaux de sites et exigences fortes';

  @override
  String get pricingPlansEnterprisePricenote =>
      '14 jours offerts. Employés illimités.';

  @override
  String get pricingPlansEnterpriseEmployeelimit => 'Employés illimités';

  @override
  String get pricingPlansEnterpriseCta => 'Contacter les ventes';

  @override
  String get pricingPlansEnterpriseFeatures0 => 'Tout Operations, plus :';

  @override
  String get pricingPlansEnterpriseFeatures1 =>
      'SSO SAML/OIDC et politiques avancées';

  @override
  String get pricingPlansEnterpriseFeatures2 =>
      'SLA, accompagnement migration et formation';

  @override
  String get pricingPlansEnterpriseFeatures3 =>
      'Environnements dédiés ou région cloud choisie';

  @override
  String get pricingPlansEnterpriseFeatures4 =>
      'Audit trail, exports compliance et support prioritaire';

  @override
  String get pricingPlansEnterpriseFeatures5 =>
      'Options IA, connecteurs et gouvernance sur mesure';

  @override
  String get pricingFaqItems0Question => 'Que comprend le plan Pilot ?';

  @override
  String get pricingFaqItems0Answer =>
      'Le plan Pilot à 29 €/mois inclut jusqu\'à 30 employés, le pointage web et mobile, les absences et congés, les dossiers employés et les bulletins de paie PDF. Essai gratuit de 14 jours, sans carte bancaire.';

  @override
  String get pricingFaqItems0Category => 'Essai';

  @override
  String get pricingFaqItems1Question => 'Puis-je changer de plan ?';

  @override
  String get pricingFaqItems1Answer =>
      'Oui, à tout moment. Upgrade immédiat, downgrade au prochain cycle. Aucun frais caché.';

  @override
  String get pricingFaqItems1Category => 'Facturation';

  @override
  String get pricingFaqItems2Question => 'Comment fonctionne la facturation ?';

  @override
  String get pricingFaqItems2Answer =>
      'Chaque plan inclut un prix fixe par mois avec un plafond d\'employés inclus (5 pour Free, 30 pour Pilot, 200 pour Operations, illimité pour Enterprise). Pas de supplément par employé actif.';

  @override
  String get pricingFaqItems2Category => 'Facturation';

  @override
  String get pricingFaqItems3Question => 'L\'essai est-il vraiment gratuit ?';

  @override
  String get pricingFaqItems3Answer =>
      'Oui. 14 jours complets avec toutes les fonctionnalités payantes. Aucune carte bancaire requise pour s\'inscrire.';

  @override
  String get pricingFaqItems3Category => 'Essai';

  @override
  String get pricingFaqItems4Question =>
      'Le plan Free est-il vraiment gratuit ?';

  @override
  String get pricingFaqItems4Answer =>
      'Oui. Le plan Free (0 €/mois) inclut jusqu\'à 5 employés : pointage web, absences et congés, dossiers employés et l\'app mobile Employee. Aucune carte bancaire.';

  @override
  String get pricingFaqItems4Category => 'Essai';

  @override
  String get pricingFaqItems5Question =>
      'Que se passe-t-il à la fin de l\'essai ?';

  @override
  String get pricingFaqItems5Answer =>
      'Vous choisissez un plan ou vos données restent archivées 14 jours supplémentaires. Aucune facturation automatique sans votre accord.';

  @override
  String get pricingFaqItems5Category => 'Essai';

  @override
  String get pricingFaqItems6Question => 'Quel support est disponible ?';

  @override
  String get pricingFaqItems6Answer =>
      'Pilot : support email sous 48h. Operations : support prioritaire sous 24h. Enterprise : account manager dédié + SLA contractuel.';

  @override
  String get pricingFaqItems6Category => 'Support';

  @override
  String get pricingFaqItems7Question => 'Où sont hébergées mes données ?';

  @override
  String get pricingFaqItems7Answer =>
      'En Europe (Render EU / Supabase EU). Chiffrement AES-256 au repos, TLS 1.3 en transit. Isolation par tenant garantie.';

  @override
  String get pricingFaqItems7Category => 'Sécurité';

  @override
  String get pricingFaqItems8Question => 'Êtes-vous conformes RGPD ?';

  @override
  String get pricingFaqItems8Answer =>
      'Oui. DPA disponible, données exclusivement en Europe, droit à l\'effacement implémenté, exports de données sur demande.';

  @override
  String get pricingFaqItems8Category => 'Sécurité';

  @override
  String get pricingFaqItems9Question => 'L\'API est-elle disponible ?';

  @override
  String get pricingFaqItems9Answer =>
      'L\'API REST et les webhooks sont disponibles à partir du plan Operations. Sur Pilot, vous pouvez exporter vos données en CSV/Excel.';

  @override
  String get pricingFaqItems9Category => 'Technique';

  @override
  String get pricingSectionFreelabel => 'Gratuit';

  @override
  String get pricingSectionTogglemonthly => 'Mensuel';

  @override
  String get pricingSectionToggleannual => 'Annuel';

  @override
  String get pricingSectionTogglearia => 'Changer la période de facturation';

  @override
  String get pricingSectionFullcomparison => 'Voir la comparaison complete';

  @override
  String get pricingCardPerioddefault => '/mois';

  @override
  String get pricingCardCustompricedefault => 'Sur devis';

  @override
  String get signupBadge => 'Essai gratuit 14 jours';

  @override
  String get signupTitle => 'Tester Leopardo avec votre entreprise';

  @override
  String get signupSubtitle =>
      'Créez votre espace d\'essai en 2 minutes. Aucune carte bancaire requise.';

  @override
  String get signupLabelemail => 'Email professionnel';

  @override
  String get signupPlaceholderemail => 'vous@entreprise.com';

  @override
  String get signupLabelcompany => 'Entreprise';

  @override
  String get signupPlaceholdercompany => 'Nom de votre entreprise';

  @override
  String get signupLabelrole => 'Votre rôle';

  @override
  String get signupRoleplaceholder => 'Choisir';

  @override
  String get signupRolefounder => 'Fondateur / dirigeant';

  @override
  String get signupRolemanager => 'Manager';

  @override
  String get signupRolehr => 'RH';

  @override
  String get signupRoleoperations => 'Opérations terrain';

  @override
  String get signupRoleother => 'Autre';

  @override
  String get signupLabelteamsize => 'Taille équipe';

  @override
  String get signupTeamplaceholder => 'Choisir';

  @override
  String get signupLabelphone => 'Téléphone (optionnel)';

  @override
  String get signupPlaceholderphone => '+213 555 000 000';

  @override
  String get signupOperationsnote =>
      'Nous préparerons un parcours axé terrain : pointage, tâches, kiosk et suivi d\'équipe.';

  @override
  String get signupAgreeprefix => 'J\'accepte les';

  @override
  String get signupTermslink => 'conditions d\'utilisation';

  @override
  String get signupPrivacylink => 'politique de confidentialité';

  @override
  String get signupAgreesuffix => 'et la';

  @override
  String get signupSubmitlabel => 'Recevoir mon code de vérification';

  @override
  String get signupSubmittinglabel => 'Envoi du code...';

  @override
  String get signupCodehint =>
      'Un code à 6 chiffres sera envoyé à votre email pour confirmer votre identité.';

  @override
  String get signupHaveaccount => 'Vous avez déjà un compte ?';

  @override
  String get signupLogincta => 'Se connecter';

  @override
  String get signupBack => 'Retour';

  @override
  String get signupOtptitle => 'Vérifiez votre email';

  @override
  String get signupOtpsentto =>
      'Nous avons envoyé un code de vérification à 6 chiffres à :';

  @override
  String get signupOtpinvalidlength =>
      'Veuillez entrer les 6 chiffres du code.';

  @override
  String get signupOtpinvalidcode => 'Code invalide ou expiré.';

  @override
  String get signupOtpverifyerror =>
      'Erreur lors de la vérification. Veuillez réessayer.';

  @override
  String get signupVerifylabel => 'Vérifier et créer mon espace';

  @override
  String get signupVerifyinglabel => 'Vérification en cours...';

  @override
  String get signupCodevalidity =>
      'Le code est valide pendant 30 minutes. Vérifiez vos spams si vous ne le trouvez pas.';

  @override
  String get signupTrackstatus => 'Suivre l\'état de mon espace';

  @override
  String get signupPendingtitle => 'Demande d\'essai reçue';

  @override
  String get signupPendingfallback =>
      'Demande d\'essai reçue. Notre équipe vous contacte sous 24h ouvrables.';

  @override
  String get signupPendingnote =>
      'Notre système de création d\'espace instantané est momentanément indisponible (redémarrage serveur). Votre demande est bien enregistrée : une personne de l\'équipe Leopardo vous contactera par email sous 24h ouvrables avec un accès adapté à votre contexte.';

  @override
  String get signupReadytitle => 'Votre espace est prêt !';

  @override
  String get signupReadysubtitle =>
      'Le sandbox de démonstration est provisionné. Accédez-y directement :';

  @override
  String get signupAccesscta => 'Accéder à mon espace';

  @override
  String get signupCopylink => 'Copier le lien';

  @override
  String get signupLinkcopied => 'Lien copié !';

  @override
  String get signupLinkemailed =>
      'Votre lien d\'accès a également été envoyé par email.';

  @override
  String get signupFailedtitle => 'Création interrompue';

  @override
  String get signupFailedbody =>
      'Une erreur est survenue lors de la création de votre espace. Notre équipe vous contactera par email sous 24h ouvrables avec un accès adapté.';

  @override
  String get signupTimeouttitle => 'Création toujours en cours';

  @override
  String get signupTimeoutbody =>
      'Votre espace est en cours de préparation. Nous vous enverrons le lien d\'accès par email dès qu\'il sera prêt.';

  @override
  String get signupRefreshstatus => 'Rafraîchir le statut';

  @override
  String get signupPreparingtitle => 'Préparation de votre espace';

  @override
  String get signupPreparingbody =>
      'Nous provisionnons votre sandbox de démonstration. Cela prend généralement moins de 30 secondes.';

  @override
  String get signupStatusfor => 'Pour :';

  @override
  String get signupStatusevery5s => 'Statut vérifié toutes les 5 secondes.';

  @override
  String get signupSuccesstitle => 'Votre espace est prêt !';

  @override
  String get signupEmailverified => 'Votre adresse email a bien été vérifiée.';

  @override
  String get signupCredslabel => 'Identifiants de connexion';

  @override
  String get signupFieldemail => 'Email';

  @override
  String get signupFieldpassword => 'Mot de passe';

  @override
  String get signupCopypasswordtitle => 'Copier le mot de passe';

  @override
  String get signupCopied => 'Copié !';

  @override
  String get signupCredssentbyemail =>
      'Ces identifiants ont aussi été envoyés par email à';

  @override
  String get signupCredsemailed =>
      'Vos identifiants de connexion viennent de vous être envoyés par email.';

  @override
  String get signupTrialnote => 'Essai gratuit de';

  @override
  String get signupTrialdaysunit => 'jours';

  @override
  String get signupTrialnotesuffix => 'aucune carte bancaire requise';

  @override
  String get signupDownloadapp => 'Télécharger l\'app';

  @override
  String get signupChangepasswordnote =>
      'Changez votre mot de passe dès la première connexion.';

  @override
  String get signupDefaulterror => 'Une erreur est survenue';

  @override
  String get signupValidationEmailinvalid => 'Email invalide';

  @override
  String get signupValidationEmailtooshort => 'Email trop court';

  @override
  String get signupValidationEmailtoolong => 'Email trop long';

  @override
  String get signupValidationCompanytooshort =>
      'Le nom de l\'entreprise doit contenir au moins 2 caractères';

  @override
  String get signupValidationCompanytoolong =>
      'Le nom de l\'entreprise est trop long';

  @override
  String get signupValidationRolerequired => 'Sélectionnez votre rôle';

  @override
  String get signupValidationEmployeesrequired =>
      'Sélectionnez une taille d\'équipe';

  @override
  String get signupValidationPhoneinvalid => 'Numéro de téléphone invalide';

  @override
  String get signupValidationAgreeterms =>
      'Vous devez accepter les conditions d\'utilisation';

  @override
  String get signupValidationCountryrequired => 'Le pays est requis.';

  @override
  String get signupLabelcountry => 'Pays';

  @override
  String get signupCountryplaceholder => 'Sélectionnez votre pays';

  @override
  String get companiesToastLoadFailed =>
      'Impossible de charger la fiche entreprise.';

  @override
  String get companiesToastTicketsFailed =>
      'Impossible de charger les tickets support.';

  @override
  String get companiesToastSubscriptionFailed =>
      'Échec de la mise à jour de l\'abonnement.';

  @override
  String get companiesToastFeaturesFailed =>
      'Échec de la configuration des modules.';

  @override
  String get companiesPortfolio => 'Portefeuille Clients';

  @override
  String get companiesDirectory => 'Repertoire des Entreprises';

  @override
  String get companiesDirectorysub =>
      'Liste classee par score de sante et priorite commerciale.';

  @override
  String get companiesSyncing => 'Synchronisation du portefeuille...';

  @override
  String get companiesRetry => 'Réessayer';

  @override
  String get companiesCompany => 'Entreprise';

  @override
  String get companiesPlanmrr => 'Plan & MRR';

  @override
  String get companiesHealthop => 'Sante Oper.';

  @override
  String get companiesCheckins30d => 'Pointage (30j)';

  @override
  String get companiesRecommendedaction => 'Action Recommandee';

  @override
  String get companiesManagement => 'Gestion';

  @override
  String get companiesSystem => 'Systeme';

  @override
  String get companiesCompanyname => 'Nom entreprise *';

  @override
  String get companiesContactemail => 'Email contact *';

  @override
  String get companiesCountry => 'Pays *';

  @override
  String get companiesCity => 'Ville de deploiement *';

  @override
  String get companiesCurrency => 'Devise';

  @override
  String get companiesTimezone => 'Fuseau Horaire';

  @override
  String get companiesDefaultlang => 'Langue Defaut';

  @override
  String get companiesManagerfirst => 'Prenom Manager *';

  @override
  String get companiesManagerlast => 'Nom Manager *';

  @override
  String get companiesManageremail => 'Email Manager Principal *';

  @override
  String get companiesActivatenow => 'Activer immediatement';

  @override
  String get companiesActivateclientnow => 'Activer le client immediatement';

  @override
  String get companiesActivateclienthint =>
      'Sinon le client reste en essai (trial).';

  @override
  String get seoPricingDescription =>
      'Tarification transparente : plan Free, Pilot 29 €/mois, Operations 99 €/mois, Enterprise sur devis. Essai gratuit 14 jours.';

  @override
  String get adminchatConversation => 'Conversation';

  @override
  String get adminchatError =>
      'Désolé, une erreur est survenue. Veuillez réessayer.';

  @override
  String get adminchatHistoryempty => 'Aucune conversation.';

  @override
  String get adminchatNew => 'Nouvelle conversation';

  @override
  String get adminchatPlaceholder => 'Tapez votre message...';

  @override
  String get adminchatSend => 'Envoyer';

  @override
  String get adminchatStart =>
      'Commencez une conversation avec l’assistant IA.';

  @override
  String get adminchatSubtitle =>
      'Posez vos questions RH, paie, recrutement...';

  @override
  String get adminchatThinking => 'Réflexion en cours...';

  @override
  String get adminchatTitle => 'Assistant IA Leopardo';

  @override
  String get adminchatUnavailablebadge => 'Indisponible';

  @override
  String get adminchatUnavailablebody =>
      'Le chat IA n’est pas activé pour la console super-admin. Utilisez un espace entreprise pris en charge pour accéder à cet assistant.';

  @override
  String get adminchatUnavailabletitle =>
      'Assistant IA indisponible au niveau plateforme';

  @override
  String get navigationAnalytics => 'Analytique';

  @override
  String get navigationAudit => 'Journal d\'audit';

  @override
  String get navigationChat => 'Chat IA';

  @override
  String get navigationCompanies => 'Entreprises';

  @override
  String get navigationContracts => 'Contrats';

  @override
  String get navigationCrm => 'Pipeline CRM';

  @override
  String get navigationDashboard => 'Tableau de bord';

  @override
  String get navigationEdge => 'Nœuds Edge';

  @override
  String get navigationExports => 'Exports & Rapports';

  @override
  String get navigationFleet => 'Flotte véhicules';

  @override
  String get navigationGlobe => 'Globe Temps Réel';

  @override
  String get navigationGrowth => 'Administration Growth';

  @override
  String get navigationLeaves => 'Congés & Absences';

  @override
  String get navigationMainmenu => 'Menu principal';

  @override
  String get navigationMarketing => 'Marketing';

  @override
  String get navigationPayroll => 'Paie';

  @override
  String get navigationPredictions => 'Dashboard Prédictif IA';

  @override
  String get navigationRecruitment => 'Recrutement';

  @override
  String get navigationReports => 'Rapports RH';

  @override
  String get navigationSubscriptions => 'Abonnements';

  @override
  String get navigationSupport => 'Support';

  @override
  String get navigationSupporttickets => 'Centre support client';

  @override
  String get navigationSystem => 'Système';

  @override
  String get navigationTraining => 'Formations';

  @override
  String get navigationUsers => 'Utilisateurs';

  @override
  String get navigationWebhooks => 'Webhooks';

  @override
  String get navigationLogin => 'Connexion';

  @override
  String get navigationLogout => 'Déconnexion';

  @override
  String get navigationCompanydetail => 'Detail Entreprise';

  @override
  String get navigationContributions => 'Cotisations sociales';

  @override
  String get navigationTaxbrackets => 'Baremes fiscaux';

  @override
  String get navigationLegalrates => 'Taux legaux';

  @override
  String get navigationAccount => 'Mon compte';

  @override
  String get navigationNotfound => 'Page non trouvee';

  @override
  String get webhooksConfirmDelete => 'Supprimer ce webhook ?';

  @override
  String get a11ySkipToContent => 'Aller au contenu principal';

  @override
  String get a11yClose => 'Fermer';

  @override
  String get a11yPreviousMonth => 'Mois précédent';

  @override
  String get a11yNextMonth => 'Mois suivant';

  @override
  String get shellConnected => 'Connecte';

  @override
  String get shellFallbackpolling => 'Mode secours (polling)';

  @override
  String get shellPushunconfigured => 'Push non configure';

  @override
  String get shellDisconnected => 'Deconnecte';

  @override
  String get shellSearch => 'Rechercher';

  @override
  String get shellNotifications => 'Notifications';

  @override
  String get shellNonotifications => 'Aucune notification';

  @override
  String get shellCriticalalerts => 'Alertes critiques';

  @override
  String get shellLevel => 'Niveau :';

  @override
  String get shellFallbackpollingtitle =>
      'Notifications via polling de secours (push indisponible)';

  @override
  String get shellTenantonly =>
      'Fonctionnalite entreprise — reservee aux espaces client';

  @override
  String get shellSettings => 'Réglages';

  @override
  String get shellTeam => 'Équipe';

  @override
  String get shellHome => 'Accueil';

  @override
  String get shellAttendance => 'Pointage';

  @override
  String get shellAbsences => 'Absences';

  @override
  String get shellApprovals => 'Validations';

  @override
  String get exportsReportemployees => 'Employes';

  @override
  String get exportsReportemployeesdesc =>
      'Liste complete avec postes, contrats, departements.';

  @override
  String get exportsReportattendance => 'Pointage';

  @override
  String get exportsReportattendancedesc =>
      'Registre de presence avec heures et anomalies.';

  @override
  String get exportsReportpayslips => 'Bulletins de paie';

  @override
  String get exportsReportpayslipsdesc =>
      'Export mensuel bulletins avec details salaire.';

  @override
  String get exportsReportabsences => 'Absences & conges';

  @override
  String get exportsReportabsencesdesc =>
      'Historique demandes et soldes par employe.';

  @override
  String get exportsReporttraining => 'Formations';

  @override
  String get exportsReporttrainingdesc =>
      'Catalogue, sessions, inscriptions et progression.';

  @override
  String get exportsReportvehicles => 'Vehicules';

  @override
  String get exportsReportvehiclesdesc => 'Flotte, kilometrage, maintenances.';

  @override
  String get exportsHrreportstitle => 'Rapports RH personnalises';

  @override
  String get exportsHrreportssub =>
      'Générez des rapports avancés avec filtres de période et département.';

  @override
  String get exportsReporttype => 'Type de rapport';

  @override
  String get exportsTypeheadcount => 'Effectifs';

  @override
  String get exportsTypeturnover => 'Turnover';

  @override
  String get exportsTypeabsenteeism => 'Absenteisme';

  @override
  String get exportsTypepayrollsummary => 'Resume paie';

  @override
  String get exportsTypetrainingprogress => 'Formations';

  @override
  String get exportsStartdate => 'Date debut';

  @override
  String get exportsEnddate => 'Date fin';

  @override
  String get exportsGenerate => 'Generer';

  @override
  String get exportsGenerating => 'Generation...';

  @override
  String get exportsStatusdone => 'Termine';

  @override
  String get exportsStatusinprogress => 'En cours';

  @override
  String get exportsStatusfailed => 'Echec';

  @override
  String get exportsDownload => 'Telecharger';

  @override
  String get exportsDownloading => 'Telechargement...';

  @override
  String get companydetailAnalyzing => 'Analyse des données client...';

  @override
  String get companydetailRetry => 'Réessayer';

  @override
  String get companydetailFieldadoption => 'Adoption Terrain';

  @override
  String get companydetailOnboarding => 'Onboarding';

  @override
  String get companydetailAnomalies30d => 'Anomalies 30j';

  @override
  String get companydetailPayrollready => 'Paie Prete';

  @override
  String get companydetailActiveemployees30d => 'Employes Actifs (30j)';

  @override
  String get companydetailNopriorityblockers =>
      'Aucun blocage prioritaire detecte.';

  @override
  String get companydetailModulesconfig => 'Configuration des Modules';

  @override
  String get companydetailServiceplan => 'Plan de services';

  @override
  String get companydetailCommercialstatus => 'Statut Commercial';

  @override
  String get companydetailStatustrial => 'Essai (Trial)';

  @override
  String get companydetailStatusactive => 'Actif';

  @override
  String get companydetailStatussuspended => 'Suspendu';

  @override
  String get companydetailStatusexpired => 'Expire';

  @override
  String get companydetailStartdate => 'Debut';

  @override
  String get companydetailInternalnotes => 'Notes Internes';

  @override
  String get companydetailNosupporttickets =>
      'Aucun ticket de support pour ce client.';

  @override
  String get companydetailTechnicalidentity => 'Identite Technique';

  @override
  String get companydetailPlatformid => 'ID Plateforme';

  @override
  String get companydetailSlug => 'Slug';

  @override
  String get companydetailCountrycurrency => 'Pays / Devise';

  @override
  String get companydetailRegisteredon => 'Inscrit le';

  @override
  String get companydetailLastactivity => 'Derniere Activite';

  @override
  String get reportsAttendanceTitle => 'Résumé Présences';

  @override
  String get reportsAttendanceDesc =>
      'Rapport mensuel des présences, retards et absences par employé.';

  @override
  String get reportsMonthLabel => 'Mois';

  @override
  String get reportsPayrollTitle => 'Résumé Paie';

  @override
  String get reportsPayrollDesc =>
      'Total brut/net, cotisations et charges par période de paie.';

  @override
  String get reportsPeriodLabel => 'Période';

  @override
  String get reportsLeaveTitle => 'Soldes Congés';

  @override
  String get reportsLeaveDesc =>
      'État des soldes de congés pour tous les employés.';

  @override
  String get reportsYearLabel => 'Année';

  @override
  String get reportsHeadcountTitle => 'Effectifs';

  @override
  String get reportsHeadcountDesc =>
      'Répartition des effectifs actifs par département, type de contrat et genre.';

  @override
  String get reportsTrainingTitle => 'Suivi Formations';

  @override
  String get reportsTrainingDesc =>
      'Taux de participation et complétion des formations.';

  @override
  String get reportsContractTitle => 'Échéances Contrats';

  @override
  String get reportsContractDesc =>
      'Contrats arrivant à échéance dans les 30, 60, 90 prochains jours.';

  @override
  String get reportsDaysLabel => 'Jours';

  @override
  String get reportsGenerate => 'Générer';

  @override
  String get reportsSuccess => 'Rapport téléchargé avec succès.';

  @override
  String get reportsError => 'Erreur lors de la génération du rapport.';

  @override
  String get reportsSubtitle =>
      'Générez et téléchargez vos rapports RH : présences, paie, congés, effectifs, formations et contrats.';

  @override
  String get notificationsChannelInapp => 'Dans l\'app';

  @override
  String get notificationsChannelEmailDesc =>
      'Messages importants et confirmations.';

  @override
  String get notificationsChannelPushDesc =>
      'Alertes rapides sur les appareils enregistrés.';

  @override
  String get notificationsChannelSmsDesc =>
      'Canal court pour urgences, activé après opt-in.';

  @override
  String get notificationsChannelWhatsappDesc =>
      'Canal conversationnel futur, avec opt-in explicite.';

  @override
  String get notificationsCategoryPayroll => 'Paie';

  @override
  String get notificationsCategorySecurity => 'Sécurité';

  @override
  String get notificationsCategorySystem => 'Système';

  @override
  String get notificationsCategoryProductTips => 'Conseils produit';

  @override
  String get notificationsCategoriesTitle => 'Catégories';

  @override
  String get notificationsSaveError =>
      'Impossible d\'enregistrer les préférences pour le moment.';

  @override
  String get notificationsChannelInappDesc =>
      'Centre de notifications web et mobile.';

  @override
  String get notificationsMarkAllReadError =>
      'Impossible de marquer les notifications comme lues.';

  @override
  String get notificationsMarkReadError =>
      'Impossible de marquer la notification comme lue.';

  @override
  String get notificationsDeleteError =>
      'Impossible de supprimer la notification.';

  @override
  String get notificationsDeleted => 'Notification supprimée.';

  @override
  String get employeesLoadError => 'Impossible de charger les employés.';

  @override
  String get employeesTitle => 'Équipe';

  @override
  String get employeesSubtitle =>
      'Vue manager branchée à l\'API RH : liste des collaborateurs, statut et points d\'entrée essentiels.';

  @override
  String get employeesTotalTeam => 'Total équipe';

  @override
  String get employeesSource => 'Source';

  @override
  String get employeesState => 'État';

  @override
  String get employeesLoadingShort => 'Chargement';

  @override
  String get employeesConnectedApi => 'Connecté à l\'API';

  @override
  String get employeesRecentCollaborators => 'Collaborateurs récents';

  @override
  String get employeesListLoading => 'Chargement de la liste équipe...';

  @override
  String get employeesEmptyList => 'Aucun employé visible pour ce compte.';

  @override
  String get userAuthCompanyRequestTitle => 'Demande soumise !';

  @override
  String get userAuthCompanyRequestBody =>
      'Un administrateur examinera votre demande. Vous recevrez une notification dès qu\'elle sera traitée.';

  @override
  String get userAuthCompanyRequestInfo =>
      'Remplissez les informations de votre entreprise. Un administrateur validera votre demande.';

  @override
  String get userAuthBackToHome => 'À l\'accueil';

  @override
  String get userAuthCreateCompany => 'Créer une entreprise';

  @override
  String get userAuthCompanyName => 'Nom de l\'entreprise';

  @override
  String get userAuthCompanyEmail => 'Email entreprise';

  @override
  String get userAuthSector => 'Secteur d\'activité';

  @override
  String get userAuthCountry => 'Pays';

  @override
  String get userAuthCity => 'Ville';

  @override
  String get userAuthPhone => 'Téléphone';

  @override
  String get userAuthDescription => 'Description';

  @override
  String get userAuthSubmitRequest => 'Soumettre la demande';

  @override
  String get userAuthSubmitError => 'Erreur lors de la soumission';

  @override
  String get userAuthAlreadyAccount => 'Deja un compte ? Se connecter';

  @override
  String get userAuthFirstName => 'Prenom';

  @override
  String userAuthGoogleError(Object error) {
    return 'Erreur Google : $error';
  }

  @override
  String get userAuthLastName => 'Nom';

  @override
  String get userAuthLoginSubtitle =>
      'Retrouvez votre espace, vos documents et vos demandes.';

  @override
  String get userAuthNoAccount => 'Pas encore de compte ? S\'inscrire';

  @override
  String get userAuthPersonalLogin => 'Connexion personnelle';

  @override
  String get userAuthPhoneOptional => 'Telephone (optionnel)';

  @override
  String get userAuthRegisterButton => 'Creer mon compte';

  @override
  String get userAuthRegisterSubtitleAlt =>
      'Accedez a votre espace personnel et organisez vos documents.';

  @override
  String get userAuthRegisterTitle => 'Creer mon compte';

  @override
  String get partnerpageLoading => 'Chargement de votre espace...';

  @override
  String get partnerpageApplyerrorprefix => 'Erreur lors de la candidature : ';

  @override
  String get partnerpageNotappliedTitle => 'Devenir Partenaire';

  @override
  String get partnerpageNotappliedSubtitle =>
      'Rejoignez l\'écosystème Leopardo RH et gagnez des commissions sur chaque entreprise que vous parrainez. Jusqu\'à 20 % de commission récurrente.';

  @override
  String get partnerpageNotappliedIndividual =>
      'Postuler en tant qu\'Individuel';

  @override
  String get partnerpageNotappliedAgency => 'Postuler en tant qu\'Agence';

  @override
  String get partnerpagePendingTitle => 'Candidature en cours';

  @override
  String get partnerpagePendingBody =>
      'Votre demande est en cours de validation par notre équipe commerciale. Vous recevrez un email dès que votre accès sera activé.';

  @override
  String get partnerpageDashboardTitle => 'Dashboard Partenaire';

  @override
  String get partnerpageDashboardSubtitle =>
      'Suivez vos conversions et vos commissions Leopardo RH — statut partenaire actif.';

  @override
  String get partnerpageMetricsConversions => 'Conversions';

  @override
  String get partnerpageMetricsTotalearned => 'Gains totaux';

  @override
  String get partnerpageMetricsPending => 'En attente';

  @override
  String get partnerpageMetricsWithdrawable => 'Solde retirable';

  @override
  String get partnerpageCommissionsTitle => 'Dernières commissions';

  @override
  String get partnerpageCommissionsEmpty => 'Aucune commission enregistrée.';

  @override
  String get partnerpageTableTenantid => 'Tenant ID';

  @override
  String get partnerpageTableDate => 'Date';

  @override
  String get partnerpageTableStatus => 'Statut';

  @override
  String get partnerpageTableAmount => 'Montant';

  @override
  String get partnerpageTableStatuspaid => 'Payée';

  @override
  String get partnerpageTableStatuspending => 'En attente';

  @override
  String get partnerpagePayoutTitle => 'Paiement';

  @override
  String get partnerpagePayoutBody =>
      'Vos commissions sont payées une fois le seuil atteint. Vérifiez que vos coordonnées bancaires sont à jour.';

  @override
  String get partnerpagePayoutRequest => 'Demander un virement';

  @override
  String get partnerpagePayoutSending => 'Envoi...';

  @override
  String get partnerpagePayoutInsufficient =>
      'Solde insuffisant pour demander un virement (minimum 100,00 €).';

  @override
  String get partnerpagePayoutSuccess =>
      'Demande de virement envoyée avec succès.';

  @override
  String get partnerpagePayoutErrorprefix =>
      'Erreur lors de la demande de virement : ';

  @override
  String get partnerpageReferralTitle => 'Lien de parrainage';

  @override
  String get partnerpageReferralUnavailable => 'Lien indisponible';

  @override
  String get partnerpageReferralCopy => 'Copier mon lien';

  @override
  String get partnerpageReferralCopied => 'Copié !';

  @override
  String get partnerpageReferralCopyerror =>
      'Impossible de copier le lien. Copiez-le manuellement.';

  @override
  String get apiSessionexpired => 'Session expirée. Reconnexion en cours...';

  @override
  String get apiAccessdenied =>
      'Accès refusé sur :endpoint. Permissions insuffisantes.';

  @override
  String get apiNotfound => 'Ressource introuvable : :endpoint';

  @override
  String get apiToomanyrequests =>
      'Trop de requêtes. Veuillez patienter quelques secondes.';

  @override
  String get apiServererror => 'Erreur serveur sur :endpoint. :detail';

  @override
  String get apiServererrorretry => 'Réessayez plus tard.';

  @override
  String get apiServerunavailable =>
      'Le serveur est temporairement indisponible (:status). Réessayez dans quelques instants.';

  @override
  String get apiGenericerror => 'Erreur :status sur :endpoint.';

  @override
  String get apiInvaliddata => 'Données invalides.';

  @override
  String get apiConnectionerror =>
      'Erreur de connexion. Vérifiez votre connexion internet.';

  @override
  String get apiLoginInvalidJson => 'Corps de requête invalide.';

  @override
  String get apiLoginTimeout =>
      'Le serveur met trop de temps à répondre. Réessayez dans quelques instants.';

  @override
  String get apiLoginNetworkError => 'Impossible de contacter le serveur.';

  @override
  String get apiLoginBackendError => 'Réponse serveur inattendue.';

  @override
  String get settingspageCancel => 'Annuler';

  @override
  String get settingspageConfirmpassword => 'Confirmer le mot de passe';

  @override
  String get settingspageCurrentpassword => 'Mot de passe actuel';

  @override
  String get settingspageDisable2fa => 'Désactiver le 2FA';

  @override
  String get settingspageDisabled => 'Désactivé';

  @override
  String get settingspageEmail => 'Adresse email';

  @override
  String get settingspageEnable2fa => 'Activer le 2FA';

  @override
  String get settingspageEnabled => 'Activé';

  @override
  String get settingspageEntercodestep =>
      '2. Entrez le code à 6 chiffres généré';

  @override
  String get settingspageFullname => 'Nom complet';

  @override
  String get settingspageGeneratesecret => 'Générer un secret 2FA';

  @override
  String get settingspageGeneratesecrethint =>
      'Générez un secret et scannez-le avec une application d\'authentification (Google Authenticator, Authy, 1Password...).';

  @override
  String get settingspageManualsecret => 'Secret manuel :';

  @override
  String get settingspageMinlengthhint => 'Minimum 8 caractères.';

  @override
  String get settingspageNewpassword => 'Nouveau mot de passe';

  @override
  String get settingspagePassword => 'Mot de passe';

  @override
  String get settingspagePasswordsubtitle =>
      'Changer votre mot de passe déconnectera automatiquement toutes vos autres sessions actives.';

  @override
  String get settingspagePasswordtitle => 'Mot de passe';

  @override
  String get settingspagePasswordupdated =>
      'Mot de passe mis à jour avec succès.';

  @override
  String get settingspagePasswordsmismatch =>
      'Les mots de passe ne correspondent pas.';

  @override
  String get settingspageProfilesubtitle =>
      'Nom et adresse email utilisés pour vous connecter.';

  @override
  String get settingspageProfiletitle => 'Informations du profil';

  @override
  String get settingspageProfileupdated => 'Profil mis à jour avec succès.';

  @override
  String get settingspageSavechanges => 'Enregistrer les modifications';

  @override
  String get settingspageScanstep =>
      '1. Scannez ce lien / secret dans votre application 2FA :';

  @override
  String get settingspageSubtitle =>
      'Gérez vos informations, votre mot de passe et la sécurité de votre compte super-administrateur.';

  @override
  String get settingspageTitle => 'Mon compte';

  @override
  String get settingspageTwofactoractivehint =>
      'Le 2FA est actif. Pour le désactiver, confirmez votre mot de passe.';

  @override
  String get settingspageTwofactordisabled => '2FA désactivé.';

  @override
  String get settingspageTwofactorenabled => '2FA activé avec succès.';

  @override
  String get settingspageTwofactorsubtitle =>
      'Ajoutez une couche de sécurité supplémentaire à votre compte de super-administrateur.';

  @override
  String get settingspageTwofactortitle =>
      'Authentification à deux facteurs (2FA)';

  @override
  String get settingspageUpdatepassword => 'Mettre à jour le mot de passe';

  @override
  String get systempageApierror => 'Erreur : :error';

  @override
  String get systempageApioperational => 'API opérationnelle';

  @override
  String get systempageApioperationaldb => 'API opérationnelle — DB :ms ms';

  @override
  String get systempageApiservices => 'Services API';

  @override
  String get systempageApiunavailable => 'Non disponible — GET /health/live';

  @override
  String get systempageDatabase => 'Base de Données';

  @override
  String get systempageDberror => 'Erreur : :error';

  @override
  String get systempageDblatency => 'Latence : :ms ms';

  @override
  String get systempageDbunavailable =>
      'Non disponible — lancez un Health Check.';

  @override
  String get systempageDbunreachable => 'base injoignable';

  @override
  String get systempageGlobalerror =>
      'Sonde agrégée : base de données injoignable.';

  @override
  String get systempageGlobalhealthy =>
      'Sonde agrégée DB + Redis opérationnelle.';

  @override
  String get systempageGlobalstatus => 'Statut Global';

  @override
  String get systempageGlobalunavailable =>
      'Non disponible — GET /admin/dashboard/stats';

  @override
  String get systempageGlobalwarning => 'Sonde agrégée : dégradation détectée.';

  @override
  String get systempageHealthcheck => 'Health Check';

  @override
  String get systempageHealthcheckrunning => 'Analyse...';

  @override
  String get systempageHealtherror =>
      'Health check terminé — base de données en erreur';

  @override
  String get systempageHealthliveunreachable =>
      'Sonde /health/live injoignable.';

  @override
  String get systempageHealthok =>
      'Health check terminé — base de données opérationnelle';

  @override
  String get systempageHealthunreachable =>
      'Health check terminé — base de données injoignable';

  @override
  String get systempageInfradetails =>
      ':active compagnies actives · PHP :php · queue :queue';

  @override
  String get systempageInfraunavailable =>
      'Non disponible — GET /platform/metrics/overview';

  @override
  String get systempageInfrastructure => 'Infrastructure';

  @override
  String get systempageMetricsloaderror =>
      'Erreur lors du chargement des métriques plateforme';

  @override
  String get systempageNotifobsloaderror =>
      'Erreur lors du chargement de l\'observabilité des notifications';

  @override
  String get systempageQueueobsloaderror =>
      'Erreur lors du chargement de l\'observabilité des jobs';

  @override
  String get systempageRetry => 'Réessayer';

  @override
  String get systempageServiceunreachable => 'service injoignable';

  @override
  String get systempageStatsloaderror =>
      'Erreur lors du chargement des stats système';

  @override
  String get systempageSubtitle =>
      'Monitoring, configuration et automatisation de la plateforme Leopardo RH.';

  @override
  String get systempageTitle => 'Administration Système';

  @override
  String get billingCancelSubscriptionConfirm =>
      'Annuler votre abonnement ? Vous perdrez l\'accès aux modules premium à la fin de la période en cours.';

  @override
  String get billingNoActivePeriod => 'Aucune période active';

  @override
  String get billingNoActiveSubscription => 'Aucun abonnement active';

  @override
  String get billingPeriodLabel => 'Période';

  @override
  String get billingCheckoutSandboxMessage =>
      'Paiement simulé (mode sandbox). Aucune carte débitée.';

  @override
  String get billingCheckoutUnavailable =>
      'Le paiement en ligne est temporairement indisponible. Contactez le support à support@leopardo-rh.com.';

  @override
  String get billingCheckoutFailed =>
      'Impossible de créer la session de paiement.';

  @override
  String get billingRedirectUrlInvalid =>
      'Les URLs de redirection doivent appartenir au site autorisé.';

  @override
  String get contractsListSubtitle =>
      'Gestion des contrats employés : suivi des statuts, échéances et export PDF, branchée directement sur l\'API RH.';

  @override
  String get contractsSearchplaceholder =>
      'Rechercher un employe ou un type de contrat...';

  @override
  String get contractsAllstatuses => 'Tous les statuts';

  @override
  String get contractsMobileTitle => 'Mon Contrat';

  @override
  String get contractsBackTooltip => 'Retour';

  @override
  String get contractsEmptyTitle => 'Aucun contrat';

  @override
  String get contractsEmptyDescription =>
      'Votre contrat apparaîtra ici une fois configuré.';

  @override
  String get contractsLabelType => 'Type';

  @override
  String get contractsLabelStartDate => 'Début';

  @override
  String get contractsLabelEndDate => 'Fin';

  @override
  String get contractsLabelBaseSalary => 'Salaire de base';

  @override
  String get contractsLoading => 'Chargement des contrats...';

  @override
  String get contractsStatusActive => 'Actif';

  @override
  String get contractsStatusExpired => 'Expiré';

  @override
  String get contractsStatusDraft => 'Brouillon';

  @override
  String get contractsStatusCdi => 'CDI';

  @override
  String get trainingTitleplaceholder => 'Titre *';

  @override
  String get trainingDurationplaceholder => 'Duree (h)';

  @override
  String get trainingMaxparticipantsplaceholder => 'Participants max';

  @override
  String get trainingOnline => 'En ligne';

  @override
  String get accessDeniedBody =>
      'Votre compte n\'a pas le rôle Manager requis pour cette application. Utilisez l\'application correspondant à votre rôle (Employee, RH…) ou contactez votre administrateur.';

  @override
  String get accessDeniedLogout => 'Se déconnecter';

  @override
  String get accessDeniedTitle => 'Accès refusé';

  @override
  String get accessDeniedBodyHr =>
      'Votre compte n\'a pas le rôle RH requis pour cette application. Utilisez l\'application correspondant à votre rôle (Employee, Manager…) ou contactez votre administrateur.';

  @override
  String get ampAutoDetectDesc =>
      'Votre présence est détectée automatiquement dès que vous entrez dans la zone de l\'entreprise. Aucune action requise de votre part.';

  @override
  String get ampManualDesc =>
      'Pointez manuellement en appuyant sur les boutons Arrivée et Départ dans l\'écran de présence.';

  @override
  String get ampModeTitle => 'Mode de pointage';

  @override
  String get ampQrScanDesc =>
      'Scannez le QR Code affiché à l\'entrée de l\'entreprise pour pointer votre arrivée et votre départ.';

  @override
  String get ampRecommended => 'Recommandé';

  @override
  String get ampSaveError =>
      'Impossible de sauvegarder votre préférence. Vérifiez votre connexion.';

  @override
  String get ampTitle =>
      'Choisissez comment vous souhaitez pointer votre présence chaque jour.';

  @override
  String get approvalApproved => 'Demande approuvée';

  @override
  String get approvalRejected => 'Demande refusée';

  @override
  String get approvalsEmpty => 'Aucune approbation en attente.';

  @override
  String get approvalsUpToDate => 'Tout est à jour';

  @override
  String get approvalsLoading => 'Chargement des approbations...';

  @override
  String get approvalsRejectReasonHint => 'Expliquez la raison...';

  @override
  String get approvalsRejectReasonLabel => 'Motif du refus';

  @override
  String get approvalsTitle => 'Approbations';

  @override
  String get back => 'Retour';

  @override
  String employeeNumber(Object id) {
    return 'Employé #$id';
  }

  @override
  String errorPrefix(Object message) {
    return 'Erreur : $message';
  }

  @override
  String get errorUnexpected => 'Une erreur est survenue';

  @override
  String evaluationPeriod(Object period) {
    return 'Période : $period';
  }

  @override
  String get evaluationsEmpty => 'Aucune évaluation';

  @override
  String get evaluationsTitle => 'Mes Évaluations';

  @override
  String get evaluationsEmptyHint =>
      'Vous n\'avez pas encore d\'évaluation enregistrée.';

  @override
  String get featureComingSoon => 'Fonction bientôt disponible';

  @override
  String get homeCompleteOnboarding => 'Compléter mon onboarding';

  @override
  String get homeOnboardingHint =>
      'Configurez votre espace en quelques étapes.';

  @override
  String get monthlySummaryLoading => 'Chargement du résumé mensuel...';

  @override
  String get orgChartCollapse => 'Réduire';

  @override
  String get orgChartEmpty =>
      'L\'organigramme sera disponible une fois les employés configurés.';

  @override
  String get orgChartExpand => 'Développer';

  @override
  String get pageNotFound =>
      'La page demandée est introuvable ou la navigation a échoué.';

  @override
  String get pendingSessionsEmpty =>
      'Aucune session GPS en attente de validation.';

  @override
  String get pendingSessionsToValidate => 'À valider';

  @override
  String get pendingSessionsUpToDate => 'Tout est à jour';

  @override
  String get refresh => 'Actualiser';

  @override
  String get registerCreateAccount => 'Créer votre compte';

  @override
  String get registerCreating => 'Création de compte en cours...';

  @override
  String get registerFirstName => 'Prénom';

  @override
  String get registerMinChars => '8 caractères minimum';

  @override
  String get registerPassword => 'Mot de passe';

  @override
  String get registerRequired => 'Obligatoire';

  @override
  String get registerSubmit => 'Créer mon compte';

  @override
  String get retry => 'Réessayer';

  @override
  String get saApproved => 'Approuvées';

  @override
  String saConfigLoadError(Object error) {
    return 'Impossible de charger la configuration.\n$error';
  }

  @override
  String get saDashboardTitle => 'Pointage GPS — tableau de bord équipe';

  @override
  String get saDetected => 'Détectées';

  @override
  String get saDisableAutoGps => 'Désactiver le GPS automatique';

  @override
  String get saEnableAutoGps => 'Activer le GPS automatique';

  @override
  String get saForced => 'Imposé';

  @override
  String get saGpsZoneNotConfigured =>
      'La zone GPS de votre entreprise n\'est pas encore configurée.';

  @override
  String get saPermissionDenied =>
      'Autorisation de localisation refusée. Activez le GPS dans les réglages pour activer la surveillance.';

  @override
  String saPresenceInProgress(Object time) {
    return 'Présence en cours depuis $time';
  }

  @override
  String get saRecentSessions => 'Sessions récentes';

  @override
  String get saRejected => 'Rejetées';

  @override
  String get saSessionsLoadError =>
      'Impossible de charger les sessions GPS. Vérifiez votre connexion.';

  @override
  String get saStartMonitoringError =>
      'Impossible de démarrer la surveillance GPS. Vérifiez les permissions de localisation et réessayez.';

  @override
  String get saStatusApproved => 'Approuvée';

  @override
  String get saStatusCancelled => 'Annulée';

  @override
  String get saStatusDetected => 'Détectée';

  @override
  String get saStatusPending => 'En validation';

  @override
  String get saStatusRejected => 'Rejetée';

  @override
  String get sessionApproved => 'Session approuvée ✓';

  @override
  String sessionEntryAt(Object time) {
    return 'Entrée : $time';
  }

  @override
  String get sessionRejected => 'Session rejetée';

  @override
  String get sessionsToValidate => 'Sessions à valider';

  @override
  String get backToHome => 'Retour à l\'accueil';

  @override
  String get absencesTitle => 'Mes absences';

  @override
  String get absencesSubtitle => 'Demandes, soldes et décisions RH';

  @override
  String get absencesRequest => 'Demander';

  @override
  String get absencesEmptyTitle => 'Aucune absence';

  @override
  String get absencesEmptyHint =>
      'Demandez une absence depuis le bouton principal, puis suivez la décision RH ici.';

  @override
  String get absencesEmployeeLabel => 'Employé';

  @override
  String get absencesTypeFallback => 'Absence';

  @override
  String get absencesLoading => 'Chargement des absences';

  @override
  String get absencesApprove => 'Approuver';

  @override
  String get absencesReject => 'Refuser';

  @override
  String get absencesCancelRequest => 'Annuler la demande';

  @override
  String get absencesViewProof => 'Voir le justificatif';

  @override
  String get absencesProofDownloaded => 'Justificatif téléchargé : ';

  @override
  String get absencesFailure => 'Échec : ';

  @override
  String get absencesReasonMissing => 'Motif non renseigné';

  @override
  String get absencesDateMissing => 'Date de demande non renseignée';

  @override
  String get absencesCurrentCompany => 'Entreprise courante';

  @override
  String get absencesRequesterLabel => 'Demandeur : ';

  @override
  String get absencesCompanyLabel => 'Entreprise : ';

  @override
  String get absencesRequestLabel => 'Demande : ';

  @override
  String get absencesReasonLabel => 'Motif : ';

  @override
  String get absencesApproveTitle => 'Approuver cette absence ?';

  @override
  String get absencesReasonNotProvided => 'non renseigné';

  @override
  String get absencesApproveBody =>
      'La demande passera en statut approuvé et l\'employé sera notifié.';

  @override
  String get absencesApprovedSnack => 'Absence approuvée.';

  @override
  String get absencesRejectTitle => 'Refuser l\'absence';

  @override
  String get absencesRejectHelper => 'Le motif sera visible par l\'employé.';

  @override
  String get absencesRejectedSnack => 'Absence refusée.';

  @override
  String get absencesCancelTitle => 'Annuler cette demande ?';

  @override
  String get absencesCancelBody =>
      'La demande en attente sera retirée et le RH verra le statut annulé.';

  @override
  String get absencesKeep => 'Garder';

  @override
  String get absencesCancel => 'Annuler';

  @override
  String get absencesCancelledSnack => 'Demande d\'absence annulée.';

  @override
  String get absencesStatusApproved => 'approuvée';

  @override
  String get absencesStatusPending => 'en attente';

  @override
  String get absencesStatusRejected => 'rejetée';

  @override
  String get absencesStatusCancelled => 'annulée';

  @override
  String get absencesNewAbsence => 'Nouvelle absence';

  @override
  String get absencesNewAbsenceHint =>
      'Choisissez le type de solde et la période à transmettre au RH.';

  @override
  String get absencesNoTypeAvailable =>
      'Aucun type d\'absence disponible pour ce compte. Contactez le RH pour configurer les soldes.';

  @override
  String get absencesType => 'Type';

  @override
  String get absencesTypeRequired => 'Type d\'absence requis';

  @override
  String get absencesBalancesLoading => 'Chargement des soldes';

  @override
  String get absencesStart => 'Début';

  @override
  String get absencesEnd => 'Fin';

  @override
  String get absencesReason => 'Motif';

  @override
  String get absencesReasonhint => 'Ex : rendez-vous médical, congé familial…';

  @override
  String get absencesReasonrequired => 'Motif obligatoire';

  @override
  String get absencesAttachProof => 'Joindre un justificatif (optionnel)';

  @override
  String get absencesProofAttached => 'Justificatif joint';

  @override
  String get absencesSubmitToHr => 'Soumettre au RH';

  @override
  String get absencesSubmittedSnack => 'Demande d\'absence transmise au RH.';

  @override
  String get absencesDaysAvailable => ' j disponibles';

  @override
  String get absencesDaysShort => ' j';

  @override
  String get absencesEmpty => 'Aucune absence';

  @override
  String get absencesListSubtitle => 'Demandes, soldes et décisions RH';

  @override
  String get absencesListTitle => 'Mes Absences';

  @override
  String get settingsJourneyLoadError =>
      'Impossible de charger votre parcours.';

  @override
  String get settingsJourneyInProgress => 'En cours';

  @override
  String get settingsJourneyTitle => 'Parcours professionnel';

  @override
  String get settingsJourneyUnknownCompany => 'Entreprise';

  @override
  String get settingsJourneyUnknownDate => 'Date inconnue';

  @override
  String get settingsJourneyUnknownPosition => 'Poste non renseigné';

  @override
  String get settingsStatsLoadError =>
      'Impossible de charger les statistiques.';

  @override
  String get settingsAccountPortableHint =>
      'Votre compte reste utile même quand vous changez d\'entreprise.';

  @override
  String get settingsAccountSubtitle => 'Profil, langue et sécurité';

  @override
  String get settingsAccountTitle => 'Compte';

  @override
  String get settingsBiometricApproved => 'Approuvé';

  @override
  String get settingsBiometricConsent =>
      'Je consens au traitement de mes données biométriques.';

  @override
  String get settingsBiometricDevice => 'Appareil de référence (optionnel)';

  @override
  String get settingsBiometricFace => 'Visage';

  @override
  String get settingsBiometricFingerprint => 'Empreinte digitale';

  @override
  String get settingsBiometricManagerHint =>
      'Réservée aux profils employés dans cette app manager.';

  @override
  String get settingsBiometricNone => 'Aucun enrôlement';

  @override
  String get settingsBiometricNote => 'Note (optionnel)';

  @override
  String get settingsBiometricPending => 'En attente';

  @override
  String get settingsBiometricRejected => 'Rejeté';

  @override
  String get settingsBiometricSaved => 'Préparation biométrique enregistrée.';

  @override
  String get settingsBiometricTerminalHint =>
      'Préparation doigt et visage pour les bornes terrain.';

  @override
  String get settingsConfirmPassword => 'Confirmer le mot de passe';

  @override
  String get settingsCurrentPassword => 'Mot de passe actuel';

  @override
  String get settingsEdgeSaved => 'Paramètres Edge enregistrés.';

  @override
  String get settingsEmailInvalid => 'Email invalide';

  @override
  String get settingsEmailLabel => 'Email';

  @override
  String get settingsEmailRequired => 'Email requis';

  @override
  String get settingsEmployeeProfileHint =>
      'Profil employé : accès au pointage, à l\'historique personnel et aux paramètres de préparation biométrie.';

  @override
  String get settingsFirstName => 'Prénom';

  @override
  String get settingsFirstNameRequired => 'Prénom requis';

  @override
  String get settingsKioskBiometricTitle => 'Biométrie kiosk';

  @override
  String get settingsLanguageSaved => 'Langue enregistrée.';

  @override
  String get settingsLanguageSubtitle =>
      'La langue choisie pilote aussi les notifications et textes futurs.';

  @override
  String get settingsLanguageTitle => 'Langue';

  @override
  String get settingsLastNameLabel => 'Nom';

  @override
  String get settingsLastNameRequired => 'Nom requis';

  @override
  String get settingsLogout => 'Déconnexion';

  @override
  String get settingsManagerAccountHint =>
      'Un compte manager doit rester clair, sécurisé et prêt pour les décisions terrain.';

  @override
  String get settingsMobileAccess => 'Accès mobile';

  @override
  String get settingsMyProfile => 'Mon profil';

  @override
  String get settingsMyQrEmployee => 'Mon QR code';

  @override
  String get settingsMyQrManager => 'Mon QR manager';

  @override
  String get settingsNewPassword => 'Nouveau mot de passe';

  @override
  String get settingsNoCompanyQr =>
      'Aucun QR entreprise dans le presse-papiers.';

  @override
  String get settingsNoJourney => 'Aucun parcours enregistré pour le moment.';

  @override
  String get settingsNotificationsSubtitle =>
      'Canaux, heures calmes et alertes manager opérationnelles.';

  @override
  String get settingsNotificationsTitle => 'Notifications';

  @override
  String get settingsPasswordChanged => 'Mot de passe modifié.';

  @override
  String get settingsPasswordMinLength => '8 caractères minimum';

  @override
  String get settingsPasswordMismatch => 'Mots de passe différents';

  @override
  String get settingsPasteQr => 'Coller le QR fourni par le manager ou le RH';

  @override
  String get settingsPersonalContacts => 'Contacts personnels';

  @override
  String get settingsPersonalEmail => 'Email personnel (optionnel)';

  @override
  String get settingsPersonalPhone => 'Téléphone personnel (optionnel)';

  @override
  String get settingsPortableAccountHint =>
      'Vos informations personnelles restent attachées au compte.';

  @override
  String get settingsPreferredLanguage => 'Langue préférée';

  @override
  String get settingsPreferredLanguageLabel => 'Langue préférée';

  @override
  String get settingsProfileSaved => 'Profil enregistré.';

  @override
  String get settingsQrCopyToken => 'Copier aussi le jeton';

  @override
  String get settingsQrManagerHint =>
      'Un collègue ou un RH peut le scanner pour pré-remplir une invitation.';

  @override
  String get settingsRecoveryEmail => 'Email de secours (optionnel)';

  @override
  String get settingsSave => 'Enregistrer';

  @override
  String get settingsSaveEnrollment => 'Enregistrer la préparation';

  @override
  String get settingsSaveProfile => 'Enregistrer le profil';

  @override
  String get settingsSaving => 'Enregistrement...';

  @override
  String get settingsSecurityTitle => 'Sécurité';

  @override
  String get settingsSessionSubtitle =>
      'La déconnexion reste volontairement en bas de page.';

  @override
  String get settingsSessionTitle => 'Session';

  @override
  String get settingsTeamDrive => 'Pilotage équipe';

  @override
  String get settingsTeamDriveHint =>
      'Profil, rôle et permissions restent lisibles pour les actions RH.';

  @override
  String get cabinetScreenTitleRoot => 'Mon placard';

  @override
  String get cabinetScreenEmptyTitle => 'Placard vide';

  @override
  String get cabinetScreenEmptyDescription =>
      'Ajoutez des dossiers et documents pour organiser votre espace.';

  @override
  String get cabinetScreenFolders => 'Dossiers';

  @override
  String get cabinetScreenDocuments => 'Documents';

  @override
  String get cabinetScreenNewFolder => 'Nouveau dossier';

  @override
  String get cabinetScreenAddDocument => 'Ajouter un document';

  @override
  String get cabinetScreenAddDocumentSubtitle =>
      'Depuis vos fichiers ou la camera';

  @override
  String get cabinetScreenFolderNameHint => 'Nom du dossier';

  @override
  String get cabinetScreenCancel => 'Annuler';

  @override
  String get cabinetScreenCreate => 'Créer';

  @override
  String get cabinetScreenUploading => 'Envoi en cours...';

  @override
  String get cabinetScreenDocumentAdded => 'Document ajouté avec succès';

  @override
  String get cabinetScreenUploadFailed =>
      'Échec de l\'envoi du document. Réessayez.';

  @override
  String cabinetScreenShareTitle(Object name) {
    return 'Partager « $name »';
  }

  @override
  String get cabinetScreenCreateShareLink => 'Créer un lien de partage';

  @override
  String cabinetScreenLinkCopied(Object url) {
    return 'Lien copié : $url';
  }

  @override
  String get cabinetScreenShareByEmail => 'Partager par email';

  @override
  String get cabinetScreenEmailHint => 'Email du destinataire';

  @override
  String get cabinetScreenSend => 'Envoyer';

  @override
  String cabinetScreenShareSent(Object email) {
    return 'Partage envoyé à $email';
  }

  @override
  String get cabinetScreenDeleteTitle => 'Supprimer le document ?';

  @override
  String cabinetScreenDeleteBody(Object name) {
    return 'Le document « $name » sera supprimé définitivement.';
  }

  @override
  String get cabinetScreenDelete => 'Supprimer';

  @override
  String cabinetScreenDocumentsCount(num count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count docs',
      one: '1 doc',
    );
    return '$_temp0';
  }

  @override
  String get absenceCancelBody =>
      'La demande en attente sera retirée et le RH verra le statut annulé.';

  @override
  String get absenceCancelRequest => 'Annuler la demande';

  @override
  String get absenceCancelTitle => 'Annuler cette demande ?';

  @override
  String get absenceCancelled => 'Demande d\'absence annulée.';

  @override
  String get absenceLabel => 'Absence';

  @override
  String get absenceNewHint =>
      'Choisissez le type de solde et la période à transmettre au RH.';

  @override
  String get absenceNewTitle => 'Nouvelle absence';

  @override
  String get absenceNoType =>
      'Aucun type d\'absence disponible pour ce compte. Contactez le RH pour configurer les soldes.';

  @override
  String get absenceRequest => 'Demander';

  @override
  String get absenceViewProof => 'Voir le justificatif';

  @override
  String get actionApprove => 'Approuver';

  @override
  String get actionCancel => 'Annuler';

  @override
  String get actionReject => 'Refuser';

  @override
  String get cancelRequest => 'Annuler la demande';

  @override
  String get confirmReceipt => 'Confirmer la réception';

  @override
  String get emptyAbsences => 'Aucune absence';

  @override
  String get emptyAdvances => 'Aucune avance';

  @override
  String get emptyHistory => 'Aucun historique';

  @override
  String get emptyPayslips => 'Aucune fiche de paie';

  @override
  String get emptySessions => 'Aucune session';

  @override
  String get loadError => 'Erreur de chargement';

  @override
  String get noData => 'Aucune donnée';

  @override
  String get noReason => 'Aucun motif';

  @override
  String get noTasksToday => 'Aucune tâche aujourd\'hui';

  @override
  String get salaryAdvanceAttachHint => 'Joindre une pièce (optionnel)';

  @override
  String get salaryAdvanceAttachmentLabel => 'Pièce jointe';

  @override
  String get salaryAdvanceCancelAction => 'Annuler';

  @override
  String get salaryAdvanceCancelBody =>
      'La demande en attente sera retirée avant décision RH.';

  @override
  String get salaryAdvanceCancelRequest => 'Annuler la demande';

  @override
  String get salaryAdvanceCancelTitle => 'Annuler cette avance ?';

  @override
  String get salaryAdvanceCancelled => 'Demande d\'avance annulée.';

  @override
  String get salaryAdvanceConfirmAction => 'Confirmer';

  @override
  String get salaryAdvanceConfirmReceived => 'Confirmer réception';

  @override
  String get salaryAdvanceConfirmReceivedBody =>
      'Confirmez seulement si le montant est effectivement arrivé. Cette action sera historisée.';

  @override
  String get salaryAdvanceConfirmReceivedTitle => 'Confirmer la réception ?';

  @override
  String get salaryAdvanceKeep => 'Garder';

  @override
  String get salaryAdvanceListSubtitle => 'Demandes, statuts et remboursement';

  @override
  String get salaryAdvanceListTitle => 'Avances';

  @override
  String get salaryAdvanceNoReason => 'Aucun motif';

  @override
  String get salaryAdvancePaymentDeclared =>
      'Le manager a déclaré le paiement. Confirmez uniquement après réception effective.';

  @override
  String get salaryAdvanceRequest => 'Demander';

  @override
  String get salaryAdvanceRequestTitle => 'Demande d\'avance';

  @override
  String get salaryAdvanceSubmitted => 'Demande d\'avance transmise au RH.';

  @override
  String get salaryAdvanceViewProof => 'Voir la pièce jointe';

  @override
  String get salaryAdvancesEmpty => 'Aucune avance';

  @override
  String get salaryAdvancesEmptyHint =>
      'Demandez une avance en quelques secondes, puis suivez la décision RH ici.';

  @override
  String get salaryAdvancesLoading => 'Chargement des avances';

  @override
  String get salaryStatusActive => 'active';

  @override
  String get salaryStatusApproved => 'approuvée';

  @override
  String get salaryStatusCancelled => 'annulée';

  @override
  String get salaryStatusPending => 'en attente';

  @override
  String get salaryStatusReceived => 'reçue';

  @override
  String get salaryStatusRejected => 'rejetée';

  @override
  String get salaryStatusToConfirm => 'à confirmer';

  @override
  String get salaryStatusValidated => 'validée';

  @override
  String get saveProfile => 'Enregistrer le profil';

  @override
  String get savingProfile => 'Enregistrement…';

  @override
  String get taxSlabsSimCompare => 'Salaire à comparer';

  @override
  String get teamAdd => 'Ajouter';

  @override
  String get teamAddCollaborator => 'Ajouter un collaborateur';

  @override
  String get teamAddFromQr => 'Depuis QR employé';

  @override
  String get teamAddFromQrHint => 'Coller le code fourni';

  @override
  String get teamAddManualForm => 'Formulaire classique';

  @override
  String get teamAddManualHint => 'Saisie manuelle complète';

  @override
  String get teamArchive => 'Archiver';

  @override
  String get teamArchiveConfirmAction => 'Archiver';

  @override
  String get teamArchiveConfirmTitle => 'Archiver cet employé ?';

  @override
  String get teamArchiveSuccess => 'Employé archivé.';

  @override
  String get teamConfirmCancel => 'Annuler';

  @override
  String get teamEditProfile => 'Modifier la fiche';

  @override
  String get teamEditProfileHint => 'Mettre à jour les champs RH essentiels';

  @override
  String get teamEmployeeLabel => 'Employé';

  @override
  String get teamEmployeesTab => 'Employés';

  @override
  String get teamEmpty => 'Aucun collaborateur';

  @override
  String get teamEmptyHint =>
      'Commencez par ajouter votre équipe avec le bouton ci-dessous.';

  @override
  String get teamInvitationsTab => 'Invitations';

  @override
  String get teamMakeHr => 'Nommer RH';

  @override
  String get teamMakeHrConfirmAction => 'Nommer RH';

  @override
  String get teamMakeHrConfirmTitle => 'Nommer RH ?';

  @override
  String get teamMakeHrHint => 'Donner les permissions RH à ce collaborateur';

  @override
  String get teamMakeHrSuccess => 'RH nommé.';

  @override
  String get teamManagerLabel => 'Manager';

  @override
  String get teamManagerRequired => 'Accès manager/RH requis';

  @override
  String get teamRevokeHr => 'Révoquer RH';

  @override
  String get teamRevokeHrConfirmAction => 'Révoquer';

  @override
  String get teamRevokeHrConfirmTitle => 'Révoquer RH ?';

  @override
  String get teamRevokeHrHint => 'Retirer les permissions RH de ce compte';

  @override
  String get teamRevokeHrSuccess => 'Permissions RH retirées.';

  @override
  String get teamSubtitle => 'Collaborateurs et invitations';

  @override
  String get teamTitle => 'Équipe';

  @override
  String get teamViewAttendance => 'Statistiques et pointages';

  @override
  String get teamViewAttendanceHint => 'Présence, anomalies, historique';

  @override
  String get teamViewProfile => 'Voir la fiche';

  @override
  String get teamViewProfileHint => 'Coordonnées, poste, salaire, horaire';

  @override
  String get teamViewTasks => 'Tâches';

  @override
  String get teamViewTasksHint => 'Voir ou assigner des tâches terrain';

  @override
  String get settingsManagerProfileHint =>
      'Profil RH/manager : accès au suivi de l\'équipe et à l\'historique.';

  @override
  String get settingsOverview => 'Vue d\'ensemble';

  @override
  String get teamLoading => 'Chargement de l\'équipe';

  @override
  String get teamManagerRequiredHint =>
      'Seuls les managers principaux et RH peuvent gérer l\'équipe depuis le mobile.';

  @override
  String salaryAdvanceError(Object error) {
    return 'Échec : $error';
  }

  @override
  String salaryAdvanceMonths(Object months, Object reason) {
    return '$reason - $months mois';
  }

  @override
  String salaryAdvanceProofDownloaded(Object path) {
    return 'Pièce jointe téléchargée: $path';
  }

  @override
  String salaryAdvanceSemantics(Object amount, Object reason, Object status) {
    return 'Avance de $amount, motif : $reason, statut $status.';
  }

  @override
  String get settingsJourneyToday => 'Aujourd\'hui';

  @override
  String get settingsShareProfile =>
      'Partagez votre profil ou scannez le QR d\'une entreprise.';

  @override
  String teamActionError(Object error) {
    return 'Échec : $error';
  }

  @override
  String get attendanceAccountSuspended => 'Compte suspendu ou accès refusé.';

  @override
  String get attendanceBeforeDeductions => 'Avant déductions légales';

  @override
  String get attendanceBreakRegistered => 'Pause enregistrée.';

  @override
  String get attendanceCheckinConfirmed => 'Arrivée confirmée.';

  @override
  String get attendanceCheckinNormal => 'Arrivée normale';

  @override
  String get attendanceCheckinNotConfirmed => 'Arrivée non confirmée';

  @override
  String get attendanceCheckinRegistered => 'Arrivée enregistrée à l\'instant.';

  @override
  String get attendanceCheckoutConfirmed => 'Départ confirmé.';

  @override
  String get attendanceCheckoutNotConfirmed => 'Départ non confirmé';

  @override
  String get attendanceCheckoutRegistered => 'Départ enregistré à l\'instant.';

  @override
  String get attendanceCloseTask => 'Clôturer la tâche';

  @override
  String get attendanceCorrectionApplied =>
      'La correction sera appliquée au dossier de pointage.';

  @override
  String get attendanceCorrectionDirectBody =>
      'Corriger directement cette ligne de pointage.';

  @override
  String attendanceCorrectionDirectSnack(Object date) {
    return 'Pointage du $date modifié.';
  }

  @override
  String attendanceCorrectionEditDateTitle(Object date) {
    return 'Modifier le $date';
  }

  @override
  String get attendanceCorrectionRequestBody =>
      'Soumettre une correction au RH pour validation.';

  @override
  String attendanceCorrectionRequestSnack(Object date) {
    return 'Demande du $date soumise au RH - vous serez notifié de la décision.';
  }

  @override
  String get attendanceCorrectionSentToHr =>
      'La demande sera transmise au RH pour validation.';

  @override
  String get attendanceCurrentMonth => 'Mois actuel';

  @override
  String get attendanceDayDetail => 'Détail par jour';

  @override
  String get attendanceDayDetails => 'Détails de la journée';

  @override
  String get attendanceDayDetailsBody =>
      'Voir les pointages, pauses, heures supp et temps réel.';

  @override
  String get attendanceDayNoSessionsYet =>
      'Cette journée ne contient pas encore de pointage.';

  @override
  String attendanceDaySessionsSummary(Object hours, Object sessions) {
    return '$sessions session(s) - $hours travaillées.';
  }

  @override
  String get attendanceDayTodayLabel => 'Aujourd\'hui';

  @override
  String get attendanceDayYesterdayLabel => 'Hier';

  @override
  String attendanceDaysAbsentShort(Object count) {
    return '$count abs.';
  }

  @override
  String attendanceDaysPresentRatio(Object present, Object working) {
    return '$present jours présents / $working ouvrés';
  }

  @override
  String get attendanceDeductionsLabel => 'Retenues';

  @override
  String attendanceDeductionsSub(Object amount) {
    return 'Déductions : $amount';
  }

  @override
  String get attendanceEndWork => 'Terminer le travail';

  @override
  String get attendanceEstimateDisclaimer =>
      'Estimation non officielle. Le bulletin de paie fait foi.';

  @override
  String get attendanceEstimatedEarnings => 'Gain estimé';

  @override
  String get attendanceFinish => 'Terminer';

  @override
  String get attendanceGrossEstimate => 'Gain brut estimé';

  @override
  String get attendanceGrossLabel => 'Brut';

  @override
  String get attendanceHistoryEmpty =>
      'Rien ici pour le moment. Vos pointages apparaîtront au fur et à mesure.';

  @override
  String get attendanceHoursLabel => 'Heures';

  @override
  String get attendanceHoursWorkedLabel => 'Heures travaillées';

  @override
  String get attendanceIncludedGross => 'Incluses dans le gain brut';

  @override
  String get attendanceIncludedGrossShort => 'Incluses brut';

  @override
  String get attendanceInvalidDuration => 'Durée invalide';

  @override
  String get attendanceInvalidPayload => 'Données de pointage invalides.';

  @override
  String attendanceLateMinutes(Object minutes) {
    return '$minutes min';
  }

  @override
  String get attendanceLoadDegradedNotice =>
      'Les données du jour prennent plus de temps que prévu. L\'écran reste utilisable, vous pouvez actualiser.';

  @override
  String attendanceLoadFailed(Object error) {
    return 'Impossible de charger les données : $error';
  }

  @override
  String get attendanceMarkDone => 'Marquer terminée';

  @override
  String get attendanceMonthEmptyHint =>
      'Si aucune donnée n\'existe encore, un résumé vide sera affiché.';

  @override
  String get attendanceMonthLoadedHint =>
      'Le mois est bien chargé. Les gains et heures resteront à zéro tant qu\'aucun pointage valide n\'existe.';

  @override
  String get attendanceMonthSyncing => 'Synchronisation du mois...';

  @override
  String get attendanceMyMonth => 'Mon mois';

  @override
  String get attendanceNetEstimate => 'Net estimé';

  @override
  String get attendanceNextMonth => 'Mois suivant';

  @override
  String get attendanceNoHistory => 'Aucun historique';

  @override
  String get attendanceNoLogToEdit =>
      'Aucune ligne de pointage existante à modifier pour ce jour.';

  @override
  String get attendanceNoPunchForDay =>
      'Aucun pointage enregistré pour cette journée.';

  @override
  String get attendanceNoSession => 'Aucune session';

  @override
  String attendanceOutsideZoneManagerNotice(Object fallback) {
    return '$fallback Pointage hors zone détecté; contrôlez le contexte avant validation RH.';
  }

  @override
  String attendanceOutsideZoneNotice(Object fallback) {
    return '$fallback Vous semblez hors de la zone autorisée; votre manager sera notifié si la règle entreprise l\'exige.';
  }

  @override
  String get attendanceOvertimeLabel => 'Heures supplémentaires';

  @override
  String get attendanceOvertimeShortLabel => 'Heures supp';

  @override
  String get attendancePersonalTracking => 'Suivi personnel';

  @override
  String get attendancePresence => 'Présence';

  @override
  String get attendancePreviousMonth => 'Mois précédent';

  @override
  String get attendancePunchFailed =>
      'Le pointage n\'a pas pu être confirmé. Vérifiez la connexion puis réessayez.';

  @override
  String get attendanceRealCheckinRequired => 'Arrivée réelle *';

  @override
  String get attendanceRealCheckout => 'Départ réel';

  @override
  String get attendanceRealTime => 'Temps réel';

  @override
  String get attendanceRealTimeHint =>
      'Indiquez le temps réel et une note courte avant le départ.';

  @override
  String get attendanceRefresh => 'Actualiser';

  @override
  String get attendanceRequestCorrection => 'Demander une modification';

  @override
  String get attendanceRetry => 'Réessayer';

  @override
  String get attendanceRoleForbidden =>
      'Votre rôle ne permet pas cette action de pointage.';

  @override
  String get attendanceSaveDeparture =>
      'Enregistrer le départ de cette session';

  @override
  String get attendanceSeeHistory => 'Voir l\'historique';

  @override
  String get attendanceSendCheckin => 'Envoi de l\'arrivée';

  @override
  String get attendanceSendCheckout => 'Envoi du départ';

  @override
  String get attendanceSendModificationFailed =>
      'Impossible d\'envoyer la modification pour le moment.';

  @override
  String attendanceSessionRange(Object from, Object to) {
    return '$from -> $to';
  }

  @override
  String get attendanceStartDay => 'Démarrer la journée';

  @override
  String get attendanceSummaryUnavailable => 'Résumé indisponible';

  @override
  String get attendanceTask => 'Tâche';

  @override
  String get attendanceTaskDone => 'Tâche terminée.';

  @override
  String attendanceTaskFailed(Object error) {
    return 'Échec : $error';
  }

  @override
  String get attendanceTaskNote => 'Note de réalisation';

  @override
  String get attendanceTasksSectionTitle => 'TACHES DU JOUR';

  @override
  String get attendanceTasksSyncing => 'Synchronisation des tâches du jour...';

  @override
  String get attendanceToPunch => 'À pointer';

  @override
  String get attendanceTotalDays => 'Total jours';

  @override
  String get attendanceTotalHours => 'Total heures';

  @override
  String get attendanceTotalLate => 'Retard cumulé';

  @override
  String get attendanceWorkedTime => 'Temps travaillé';

  @override
  String get payrollAdvancesDeducted => 'Avances déduites';

  @override
  String get payrollAlreadyPaid => 'Déjà payé';

  @override
  String get payrollBalanceUnavailable => 'Solde temporairement indisponible';

  @override
  String get payrollDocsUnavailable => 'Documents temporairement indisponibles';

  @override
  String payrollDocumentDownloaded(Object path) {
    return 'Document téléchargé : $path';
  }

  @override
  String get payrollDownloadPayslip => 'Télécharger le bulletin PDF';

  @override
  String get payrollDownloading => 'Téléchargement en cours';

  @override
  String get payrollEmptyHint =>
      'Vos fiches de paie apparaîtront ici dès qu\'elles seront validées.';

  @override
  String payrollError(Object error) {
    return 'Erreur : $error';
  }

  @override
  String get payrollLoading => 'Chargement des fiches de paie';

  @override
  String payrollMonthLabel(Object month, Object year) {
    return 'Mois $month/$year';
  }

  @override
  String get payrollMyBalance => 'Mon solde';

  @override
  String payrollNextPayment(Object date) {
    return 'Prochaine paie prévue le $date';
  }

  @override
  String get payrollNoCycleDocuments =>
      'Aucun document généré pour ce cycle. Les reçus apparaîtront après paiement.';

  @override
  String get payrollNoPayslips => 'Aucune fiche de paie';

  @override
  String get payrollNoReceipts =>
      'Aucun reçu ou bordereau disponible pour le moment.';

  @override
  String get payrollOvertimeLabel => 'heures supp';

  @override
  String get payrollPaymentDocuments => 'Documents paiement';

  @override
  String get payrollPaymentDocumentsTitle => 'Documents de paiement';

  @override
  String payrollPdfDownloaded(Object path) {
    return 'PDF téléchargé : $path';
  }

  @override
  String payrollPeriodRange(Object end, Object start) {
    return '$start - $end';
  }

  @override
  String get payrollRecentPayslips => 'Bulletins récents';

  @override
  String get payrollRemainingToPay => 'Reste à payer';

  @override
  String get payrollRemainingToReceive => 'Reste à recevoir';

  @override
  String get payrollSubtitle => 'Solde courant, avances et bulletins';

  @override
  String get payrollSummaryUnavailable =>
      'Résumé paie temporairement indisponible';

  @override
  String get payrollTeamBalance => 'Solde équipe';

  @override
  String payrollTeamMembers(Object count) {
    return '$count collaborateur(s)';
  }

  @override
  String get payrollTeamSubtitle => 'Soldes, avances et bulletins';

  @override
  String get payrollTeamTitle => 'Paie équipe';

  @override
  String get payrollTitle => 'Paie et solde';

  @override
  String get payrollValidatedHint =>
      'Les bulletins valides apparaîtront ici après traitement.';

  @override
  String get smartAttendanceActiveMode => 'Mode actif';

  @override
  String get smartAttendanceApprove => 'Approuver';

  @override
  String get smartAttendanceCancel => 'Annuler';

  @override
  String get smartAttendanceChangeMode => 'Changer mon mode de pointage';

  @override
  String get smartAttendanceConfirm => 'Confirmer';

  @override
  String get smartAttendanceDashboard => 'Pointage GPS — tableau de bord';

  @override
  String get smartAttendanceDashboardTitle => 'Pointage GPS — tableau de bord';

  @override
  String smartAttendanceError(Object message) {
    return 'Erreur : $message';
  }

  @override
  String get smartAttendanceForced => 'Imposé';

  @override
  String get smartAttendanceGpsAuto => 'GPS Automatique';

  @override
  String get smartAttendanceGpsTitle => 'Smart Attendance — GPS';

  @override
  String get smartAttendanceManual => 'Manuel';

  @override
  String get smartAttendanceNoGpsSessions =>
      'Aucune session GPS pour le moment.';

  @override
  String get smartAttendanceNoPending => 'Aucune session en attente';

  @override
  String get smartAttendanceNoPendingSessions =>
      'Aucune session en attente de validation';

  @override
  String get smartAttendancePending => 'En attente';

  @override
  String smartAttendancePendingCount(Object count) {
    return '$count en attente';
  }

  @override
  String get smartAttendanceQr => 'QR Code';

  @override
  String get smartAttendanceReject => 'Rejeter';

  @override
  String get smartAttendanceRejectHint => 'Expliquez la raison du rejet...';

  @override
  String get smartAttendanceRejectReason => 'Motif du rejet';

  @override
  String smartAttendanceSessionExit(Object duration, Object time) {
    return 'Sortie : $time · $duration';
  }

  @override
  String get smartAttendanceSessionsTitle => 'Sessions Smart Attendance';

  @override
  String get smartAttendanceSmart => 'Pointage Intelligent';

  @override
  String get smartAttendanceSurveillanceActive => 'Surveillance active';

  @override
  String get smartAttendanceSurveillanceInactive => 'Surveillance inactive';

  @override
  String get smartAttendanceTapToReview => 'Appuyez pour valider ou rejeter';

  @override
  String get smartAttendanceTitle => 'Smart Attendance';

  @override
  String smartAttendanceTodayTitle(Object date) {
    return 'Aujourd\'hui — $date';
  }

  @override
  String get smartAttendanceZoneSurveillance => 'Surveillance de zone';

  @override
  String get attendanceToProcess => 'A traiter';

  @override
  String get settingsBiometryEnableFirst =>
      'Active d abord la preparation biometrie.';

  @override
  String get settingsBiometryEnableAction => 'Activer la preparation biometrie';

  @override
  String get settingsEdgeNodeAddress => 'Adresse du noeud Edge';

  @override
  String get settingsBiometryAddFaceCapture =>
      'Ajoute une capture visage avant soumission.';

  @override
  String get notifTitle => 'Alertes RH, paie et validations';

  @override
  String get settingsPushInApp => 'Alertes dans l application';

  @override
  String get attendanceAnalyzingAnomalies => 'Analyse des anomalies...';

  @override
  String get settingsEdgePairingRemoved => 'Appairage Edge supprime.';

  @override
  String get teamQrNoneInClipboard => 'Aucun code QR dans le presse-papiers.';

  @override
  String get teamNoScheduleYet =>
      'Aucun horaire cree. Vous pourrez en definir dans le module Horaires.';

  @override
  String get attendanceNoPunchToday => 'Aucun pointage aujourd hui';

  @override
  String get attendanceNoRecentAnomalies => 'Aucune anomalie recente';

  @override
  String get teamNoPendingInvites => 'Aucune invitation en cours';

  @override
  String get settingsLockerDocsAdmin =>
      'CV, contrats, diplomes et documents administratifs.';

  @override
  String get settingsLockerDocsVisibility =>
      'CV, contrats, diplomes et documents avec visibilite controlee.';

  @override
  String get settingsNotifChannelChat =>
      'Canal conversationnel, necessite votre opt-in explicite.';

  @override
  String get settingsNotifChannelSms =>
      'Canal court reserve aux urgences, actif apres opt-in.';

  @override
  String get settingsNotifChannelsSummary =>
      'Canaux, heures calmes et alertes operationnelles.';

  @override
  String get settingsBiometryCaptureFace => 'Capturer / choisir mon visage';

  @override
  String get attendanceEmployeeNotPunchedToday =>
      'Cet employe n a pas encore pointe pour la journee en cours.';

  @override
  String get settingsFieldRequired => 'Champ requis';

  @override
  String get attendanceLoadingRequests => 'Chargement des demandes...';

  @override
  String get teamLoadingInvites => 'Chargement des invitations';

  @override
  String get attendanceLoadingEmployeeDetail =>
      'Chargement du detail employe...';

  @override
  String get settingsNotifChannelsHint =>
      'Choisissez les canaux utiles sans perdre les alertes RH importantes.';

  @override
  String get teamEmployeeQrCode => 'Code QR employe';

  @override
  String get settingsPasteCompanyQr => 'Coller le QR entreprise';

  @override
  String get settingsPasteManagerQr => 'Coller le QR fourni par le manager';

  @override
  String get teamPasteScannedQr => 'Coller le QR scanne';

  @override
  String get settingsPasteCompanyQrHint => 'Collez le QR entreprise.';

  @override
  String get teamPasteQrHint => 'Collez le code QR.';

  @override
  String get settingsBiometryConfirmIdentity =>
      'Confirmer votre identite pour soumettre votre demande biometrie';

  @override
  String get settingsEdgeConnectedCloud => 'Connecte au Cloud';

  @override
  String get settingsEdgeConnectedLocal => 'Connecte au noeud Edge local';

  @override
  String get settingsBiometryConsentTitle =>
      'Consentement au futur pointage biometrie';

  @override
  String get attendanceCorrectionAppliedToast => 'Correction appliquee.';

  @override
  String get attendanceCorrectionRejected => 'Correction refusee.';

  @override
  String get teamCreateFromQrAndInvite => 'Creer depuis QR et inviter';

  @override
  String get teamHireDate => 'Date d embauche';

  @override
  String get settingsRequestSent => 'Demande envoyee';

  @override
  String get settingsBiometryRequestSentHint =>
      'Demande envoyee au manager / RH pour validation.';

  @override
  String get settingsRequestJoin => 'Demander l integration';

  @override
  String get attendanceEmployeeRequestsPending =>
      'Demandes employees en attente RH';

  @override
  String get teamDepartmentOptional => 'Departement (optionnel)';

  @override
  String get settingsAvailableForNewCompany =>
      'Disponible pour une nouvelle entreprise';

  @override
  String get settingsRecoveryEmailLabel => 'Email de recuperation';

  @override
  String get settingsPersonalEmailLabel => 'Email personnel';

  @override
  String get teamEmployeeAdded => 'Employe ajoute.';

  @override
  String get settingsBiometryFingerprintDesired =>
      'Empreinte digitale souhaitee';

  @override
  String get settingsBiometrySaveEnrollment => 'Enregistrer la preparation';

  @override
  String get teamSendInvite => 'Envoyer l invitation';

  @override
  String get settingsBiometryFpExample =>
      'Exemple: FP-ENTREE-01 ou matricule biometrie';

  @override
  String get teamEmployeeRecordUpdated => 'Fiche collaborateur mise a jour.';

  @override
  String get attendanceEmptyCorrectionQueue => 'File de correction vide';

  @override
  String get settingsEdgeTokenFromAdmin => 'Fourni par votre administrateur';

  @override
  String get settingsEdgeTokenOneTime =>
      'Fourni une seule fois a l enregistrement';

  @override
  String get settingsQuietHours => 'Heures calmes';

  @override
  String get settingsJourneyHint =>
      'Historique entreprise, poste, statut et disponibilite.';

  @override
  String get teamWorkSchedule => 'Horaire de travail';

  @override
  String get teamDefaultSchedule => 'Horaire par defaut';

  @override
  String get commonOffline => 'Hors ligne';

  @override
  String get settingsBiometrySensorId =>
      'Identifiant capteur empreinte / borne';

  @override
  String get settingsEdgeNodeId => 'Identifiant du noeud (UUID)';

  @override
  String get settingsPortableIdentity => 'Identite portable';

  @override
  String get settingsBiometryFaceSelected => 'Image visage selectionnee';

  @override
  String get teamImportFromQr => 'Importer depuis QR';

  @override
  String get teamInviteResent => 'Invitation renvoyee.';

  @override
  String get settingsEdgeToken => 'Jeton Edge';

  @override
  String get settingsPasswordConfirmationMismatch =>
      'La confirmation ne correspond pas';

  @override
  String get settingsNotifLanguage => 'Langue des notifications';

  @override
  String get settingsBiometryConsentRequired =>
      'Le consentement est requis avant toute soumission.';

  @override
  String get settingsQrManagerScanHint =>
      'Le manager le scanne pour pre-remplir une invitation.';

  @override
  String get teamWorkLocation => 'Lieu de travail';

  @override
  String get teamWorkLocationOptional => 'Lieu de travail (optionnel)';

  @override
  String get settingsQuietHoursHint =>
      'Limiter les canaux externes hors horaires.';

  @override
  String get teamReadAndPrefill => 'Lire et pre-remplir';

  @override
  String get notifMarkAsRead => 'Marquer comme lue';

  @override
  String get teamEmployeeIdOptional => 'Matricule (optionnel)';

  @override
  String get teamMonthlyFixed => 'Mensuel / fixe';

  @override
  String get settingsPasswordUpdateTitle => 'Mettre a jour le mot de passe';

  @override
  String get settingsPasswordMinCharacters => 'Minimum 8 caracteres';

  @override
  String get teamSalaryMode => 'Mode salaire';

  @override
  String get settingsMyEmployeeQr => 'Mon QR employe';

  @override
  String get teamAmountRequired => 'Montant obligatoire';

  @override
  String get settingsPasswordUpdated => 'Mot de passe mis a jour.';

  @override
  String get settingsEdgeNodeLocal => 'Noeud Edge (reseau local)';

  @override
  String get settingsBiometryNotesConsent => 'Notes et consentement';

  @override
  String get settingsPushImmediateHint =>
      'Notifications immediates sur ce telephone.';

  @override
  String get teamNewEmployee => 'Nouvel employe';

  @override
  String get teamNewEmployeeViaQr => 'Nouvel employe via QR';

  @override
  String get settingsRecoveryEmailOptionalHint =>
      'Optionnel pour recuperer l acces';

  @override
  String get settingsPersonalEmailHint =>
      'Optionnel, conserve votre compte hors entreprise';

  @override
  String get settingsPhoneHint => 'Optionnel, visible selon vos choix futurs';

  @override
  String get settingsOpenMyLocker => 'Ouvrir mon placard';

  @override
  String get settingsShareProfileOrScan =>
      'Partager votre profil ou scanner une entreprise.';

  @override
  String get settingsShareProfileOrScanQr =>
      'Partagez votre profil ou scannez le QR d une entreprise.';

  @override
  String get settingsDigitalLocker => 'Placard numerique';

  @override
  String get attendanceTodayPunchesOpenSessions =>
      'Pointages du jour et sessions ouvertes';

  @override
  String get teamPositionOptional => 'Poste (optionnel)';

  @override
  String get settingsNotifPrefsUpdated =>
      'Preferences notifications mises a jour.';

  @override
  String get settingsBiometryEnrollment => 'Preparation biometrie';

  @override
  String get settingsBiometrySavedLocally =>
      'Preparation biometrie enregistree localement.';

  @override
  String get settingsBiometryEnrollHint =>
      'Preparer doigt et visage pour les bornes terrain.';

  @override
  String get attendanceTeamPresence => 'Presences equipe';

  @override
  String get settingsProfileUpdated => 'Profil mis a jour.';

  @override
  String get settingsPushMobile => 'Push mobile';

  @override
  String get commonCompanyQr => 'QR entreprise';

  @override
  String get teamCompanyQrScannable => 'QR entreprise scannable';

  @override
  String get settingsQrUnavailable => 'QR indisponible pour le moment.';

  @override
  String get settingsQrOnboarding => 'QR onboarding';

  @override
  String get settingsQrProfessional => 'QR professionnel';

  @override
  String get settingsEdgeLogoutHint =>
      'Quitter proprement cet espace sur ce telephone.';

  @override
  String get teamReloadSchedules => 'Recharger les horaires';

  @override
  String get settingsBiometryFaceRecognitionDesired =>
      'Reconnaissance faciale souhaitee';

  @override
  String get settingsNotifSummaryHint => 'Resume et confirmations importantes.';

  @override
  String get attendanceLateMissedToCheck =>
      'Retards, oublis et pointages a verifier';

  @override
  String get teamBaseSalary => 'Salaire de base';

  @override
  String get teamDailySalary => 'Salaire journalier';

  @override
  String get teamMonthlyGrossSalary => 'Salaire mensuel brut';

  @override
  String get teamSelectType => 'Selectionnez un type';

  @override
  String get attendanceTodaySessions => 'Sessions du jour';

  @override
  String get settingsBiometrySubmit => 'Soumettre au manager / RH';

  @override
  String get attendanceSyncingPresence => 'Synchronisation des presences...';

  @override
  String get settingsNotifTasksHint =>
      'Taches, decisions RH, pointage et rappels.';

  @override
  String get teamHourlyRate => 'Taux horaire';

  @override
  String get settingsPersonalPhoneLabel => 'Telephone personnel';

  @override
  String get notifMarkAllAsRead => 'Tout marquer comme lu';

  @override
  String get teamManagerType => 'Type de manager';

  @override
  String get teamPayType => 'Type de paie';

  @override
  String get settingsBiometryLocalCheckCancelled =>
      'Verification biometrie locale annulee.';

  @override
  String get settingsViewMyProfile => 'Voir mon profil';

  @override
  String get notifUpToDate =>
      'Vous etes a jour. Cette page se rafraichit automatiquement.';

  @override
  String get teamOperationalView => 'Vue operationnelle';

  @override
  String get commonLanguageArabic => 'العربية';

  @override
  String get settingsPrefSyncAccount =>
      'Cette preference est synchronisee avec votre compte et pilote aussi le mode RTL.';

  @override
  String get settingsPasswordModernizeHint =>
      'Changez votre mot de passe avant les prochaines etapes de modernisation.';

  @override
  String get teamPasteEmployeeQrHint =>
      'Collez le code QR employe. Le formulaire restera modifiable avant invitation.';

  @override
  String get teamInviteSummary =>
      'Invitation, role, date d embauche et base salariale sont envoyes a l API.';

  @override
  String get teamQrEmployeeScanHint =>
      'L employe le scanne depuis son espace compte pour demander son integration.';

  @override
  String get settingsBiometryFaceHint =>
      'Le visage peut etre capture depuis le mobile puis soumis a validation manager / RH. Pour l empreinte, Android/iOS permettent de verifier localement que vous utilisez bien un doigt enregistre, mais ne donnent pas acces au gabarit brut; l activation effective cote pointage restera donc approuvee puis exploitee par la borne entreprise.';

  @override
  String get attendanceAnomaliesHint =>
      'Les alertes de pointage, sorties manquantes et heures supplementaires apparaitront ici.';

  @override
  String get attendanceRequestsHint =>
      'Les demandes envoyees depuis les trois points du pointage seront listees ici.';

  @override
  String get teamInvitesHint =>
      'Les invitations envoyees a vos futurs collaborateurs s afficheront ici.';

  @override
  String get attendanceTeamPunchesHint =>
      'Les pointages equipe apparaitront ici des qu ils arrivent depuis mobile ou kiosque.';

  @override
  String get settingsEdgeOptionalHint =>
      'Optionnel: pointer vers un serveur Edge installe sur site pour pointer sans Internet.';

  @override
  String get settingsPrefsUnavailable =>
      'Preferences indisponibles pour le moment. Tire pour recharger plus tard.';

  @override
  String get teamQrPrefilledHint =>
      'Profil pre-rempli depuis QR. Renseignez l email professionnel unique de cette entreprise.';

  @override
  String get settingsBiometryPendingHint =>
      'Une fois soumises, vos donnees biometrie restent en attente. Toute premiere activation ou modification necessite une approbation manager/RH.';

  @override
  String get accountingactivationTitle => 'Activer la Comptabilité';

  @override
  String get accountingactivationSubtitle =>
      'Paramétrez le module en quelques étapes : la check-list vérifie le paramétrage, le contact de test et la facture d\'exemple.';

  @override
  String get accountingactivationStepstitle => 'Check-list d\'activation';

  @override
  String get accountingactivationStepsettings => 'Paramétrage comptable';

  @override
  String get accountingactivationStepsettingsdone =>
      'Paramétrage comptable — fait';

  @override
  String get accountingactivationStepsettingstodo =>
      'Paramétrage comptable — à faire';

  @override
  String get accountingactivationStepcontact => 'Contact de test';

  @override
  String get accountingactivationStepcontactdone => 'Contact de test — créé';

  @override
  String get accountingactivationStepcontacttodo => 'Contact de test — à créer';

  @override
  String get accountingactivationStepexampleinvoice => 'Facture d\'exemple';

  @override
  String get accountingactivationStepexampleinvoicedone =>
      'Facture d\'exemple — créée';

  @override
  String get accountingactivationStepexampleinvoicetodo =>
      'Facture d\'exemple — à créer';

  @override
  String get accountingactivationDone => 'Fait';

  @override
  String get accountingactivationTodo => 'À faire';

  @override
  String get accountingactivationActivatebutton => 'Terminer l\'activation';

  @override
  String get accountingactivationActivating => 'Activation en cours…';

  @override
  String get accountingactivationCompletedtitle => 'Comptabilité activée';

  @override
  String get accountingactivationCompletedbody =>
      'Le module Comptabilité est prêt : paramétrage, contact et facture d\'exemple en place.';

  @override
  String get accountingactivationGotomodule => 'Accéder au module Comptabilité';

  @override
  String get accountingactivationLoaderror =>
      'Impossible de charger l\'état d\'activation. Réessayez.';

  @override
  String get accountingactivationCompleteerror =>
      'L\'activation a échoué. Réessayez.';

  @override
  String get accountingactivationRetry => 'Réessayer';

  @override
  String get accountingactivationLoading => 'Chargement…';

  @override
  String get accountingmoduleHometitle => 'Comptabilité';

  @override
  String get accountingmoduleHomesubtitle =>
      'Module comptable : plan comptable, grand livre, balance, états financiers, FEC, exercices et lettrage.';

  @override
  String get accountingmoduleNavchart => 'Plan comptable';

  @override
  String get accountingmoduleNavledger => 'Grand livre';

  @override
  String get accountingmoduleNavbalance => 'Balance';

  @override
  String get accountingmoduleNavstatements => 'États financiers';

  @override
  String get accountingmoduleNavfiscalyears => 'Exercices';

  @override
  String get accountingmoduleNavlettering => 'Lettrage';

  @override
  String get accountingmoduleNavfec => 'Export FEC';

  @override
  String get accountingmoduleCharttitle => 'Plan comptable';

  @override
  String get accountingmoduleChartsubtitle =>
      'Comptes des classes PCG/SCF 1 à 8. Les comptes système ne peuvent pas être supprimés (désactivation seule).';

  @override
  String get accountingmoduleChartcode => 'Code';

  @override
  String get accountingmoduleChartlabel => 'Libellé';

  @override
  String get accountingmoduleCharttype => 'Type';

  @override
  String get accountingmoduleChartclass => 'Classe';

  @override
  String get accountingmoduleChartstatus => 'État';

  @override
  String get accountingmoduleChartactive => 'Actif';

  @override
  String get accountingmoduleChartinactive => 'Désactivé';

  @override
  String get accountingmoduleChartadd => 'Ajouter un compte';

  @override
  String get accountingmoduleChartaddtitle => 'Nouveau compte';

  @override
  String get accountingmoduleChartsave => 'Enregistrer';

  @override
  String get accountingmoduleChartcancel => 'Annuler';

  @override
  String get accountingmoduleChartdelete => 'Supprimer';

  @override
  String get accountingmoduleCharttoggle => 'Activer/Désactiver';

  @override
  String get accountingmoduleChartsystemnote =>
      'Compte système — suppression interdite';

  @override
  String get accountingmoduleCharttypeasset => 'Actif';

  @override
  String get accountingmoduleCharttypeliability => 'Passif';

  @override
  String get accountingmoduleCharttypeequity => 'Capitaux';

  @override
  String get accountingmoduleCharttyperevenue => 'Produit';

  @override
  String get accountingmoduleCharttypeexpense => 'Charge';

  @override
  String get accountingmoduleChartempty => 'Aucun compte.';

  @override
  String get accountingmoduleCharterror =>
      'Impossible de charger le plan comptable.';

  @override
  String get accountingmodulePeriodlabel => 'Période';

  @override
  String get accountingmoduleYearlabel => 'Exercice';

  @override
  String get accountingmoduleAlltypes => 'Tous les types';

  @override
  String get accountingmoduleLedgertitle => 'Grand livre';

  @override
  String get accountingmoduleLedgersubtitle =>
      'Écritures par période, solde courant continu.';

  @override
  String get accountingmoduleLedgeropening => 'Solde d\'ouverture';

  @override
  String get accountingmoduleLedgerdebit => 'Débit';

  @override
  String get accountingmoduleLedgercredit => 'Crédit';

  @override
  String get accountingmoduleLedgerbalance => 'Solde';

  @override
  String get accountingmoduleLedgerdate => 'Date';

  @override
  String get accountingmoduleLedgerpiece => 'Pièce';

  @override
  String get accountingmoduleLedgerdesc => 'Libellé';

  @override
  String get accountingmoduleLedgeraccount => 'Compte';

  @override
  String get accountingmoduleLedgerempty => 'Aucune écriture sur la période.';

  @override
  String get accountingmoduleLedgeraccountfilter => 'Filtrer par compte';

  @override
  String get accountingmoduleLedgerexportfec => 'Exporter FEC';

  @override
  String get accountingmoduleBalancetitle => 'Balance de vérification';

  @override
  String get accountingmoduleBalancesubtitle =>
      'Totaux débit/crédit et solde par compte sur la période.';

  @override
  String get accountingmoduleBalancetotals => 'Totaux généraux';

  @override
  String get accountingmoduleBalancedifference => 'Différence';

  @override
  String get accountingmoduleBalancebalanced => 'Balance équilibrée';

  @override
  String get accountingmoduleBalanceunbalanced => 'Balance déséquilibrée';

  @override
  String get accountingmoduleBalanceempty => 'Aucun mouvement sur la période.';

  @override
  String get accountingmoduleStatementstitle => 'États financiers';

  @override
  String get accountingmoduleStatementssubtitle =>
      'Bilan et compte de résultat par période.';

  @override
  String get accountingmoduleTabbalancesheet => 'Bilan';

  @override
  String get accountingmoduleTabincomestatement => 'Compte de résultat';

  @override
  String get accountingmoduleStatementactif => 'Actif';

  @override
  String get accountingmoduleStatementpassif => 'Passif';

  @override
  String get accountingmoduleStatementcapitaux => 'Capitaux propres';

  @override
  String get accountingmoduleStatementtotalactif => 'Total actif';

  @override
  String get accountingmoduleStatementtotalpassif => 'Total passif';

  @override
  String get accountingmoduleStatementtotalcapitaux => 'Total capitaux';

  @override
  String get accountingmoduleStatementresultat => 'Résultat net';

  @override
  String get accountingmoduleStatementbalanced =>
      'Invariant vérifié : actif = passif + capitaux';

  @override
  String get accountingmoduleStatementunbalanced => 'Invariant non vérifié !';

  @override
  String get accountingmoduleStatementempty =>
      'Aucune donnée pour cette période.';

  @override
  String get accountingmoduleFytitle => 'Exercices comptables';

  @override
  String get accountingmoduleFysubtitle =>
      'Ouverture et clôture des exercices (report à nouveau automatique).';

  @override
  String get accountingmoduleFyyear => 'Exercice';

  @override
  String get accountingmoduleFystatus => 'Statut';

  @override
  String get accountingmoduleFyopen => 'Ouvrir';

  @override
  String get accountingmoduleFyopentitle => 'Ouvrir un exercice';

  @override
  String get accountingmoduleFyclose => 'Clôturer';

  @override
  String get accountingmoduleFystatusopen => 'Ouvert';

  @override
  String get accountingmoduleFystatusclosed => 'Clôturé';

  @override
  String get accountingmoduleFyclosedat => 'Clôturé le';

  @override
  String accountingmoduleFycloseconfirmtitle(Object year) {
    return 'Clôturer l\'exercice $year ?';
  }

  @override
  String get accountingmoduleFycloseconfirmbody =>
      'La clôture fige l\'exercice et reporte le résultat. Cette action est irréversible.';

  @override
  String get accountingmoduleFyconfirm => 'Confirmer la clôture';

  @override
  String get accountingmoduleFyempty => 'Aucun exercice ouvert.';

  @override
  String get accountingmoduleFyerror => 'Impossible de charger les exercices.';

  @override
  String get accountingmoduleFyopenerror => 'Impossible d\'ouvrir l\'exercice.';

  @override
  String get accountingmoduleFycloseerror =>
      'Impossible de clôturer l\'exercice.';

  @override
  String get accountingmoduleLgtitle => 'Lettrage';

  @override
  String get accountingmoduleLgsubtitle =>
      'Sélectionnez 2+ écritures d\'un même compte et attribuez une lettre pour les rapprocher.';

  @override
  String get accountingmoduleLgselect => 'Sélectionner';

  @override
  String get accountingmoduleLgletter => 'Lettre';

  @override
  String get accountingmoduleLgapply => 'Lettrer';

  @override
  String get accountingmoduleLgunletter => 'Délettrer';

  @override
  String get accountingmoduleLgunletterplaceholder => 'Lettre à délettrer';

  @override
  String get accountingmoduleLgneedselection =>
      'Sélectionnez au moins 2 écritures.';

  @override
  String get accountingmoduleLgneedletter =>
      'Saisissez une lettre (max 32 caractères).';

  @override
  String get accountingmoduleLgdone => 'Écritures lettrées.';

  @override
  String get accountingmoduleLgunlettered => 'Écritures délettrées.';

  @override
  String accountingmoduleLgerror(Object message) {
    return 'Échec du lettrage : $message';
  }

  @override
  String get accountingmoduleLgempty => 'Aucune écriture sur la période.';

  @override
  String get accountingmoduleLgbalanced => 'Journal équilibré';

  @override
  String get accountingmoduleLgunbalanced => 'Journal déséquilibré';

  @override
  String get accountingmoduleLgclosed => 'Période clôturée';

  @override
  String get accountingmoduleLoading => 'Chargement…';

  @override
  String get accountingmoduleRetry => 'Réessayer';

  @override
  String get accountingmoduleErrorgeneric => 'Une erreur est survenue.';

  @override
  String get accountingmoduleFecerror => 'Export FEC impossible.';

  @override
  String get shareaccessesTitle => 'Historique des accès';

  @override
  String get shareaccessesSubtitle =>
      'Consultation RGPD : qui a consulté ou téléchargé un document partagé sur le portail client, quand et depuis quelle adresse IP.';

  @override
  String get shareaccessesDocumentlabel => 'Document';

  @override
  String get shareaccessesSelectdocument => 'Sélectionner un document…';

  @override
  String get shareaccessesNodocumentselected =>
      'Sélectionnez un document pour afficher son historique d\'accès.';

  @override
  String get shareaccessesActionheader => 'Action';

  @override
  String get shareaccessesDateheader => 'Date';

  @override
  String get shareaccessesIpheader => 'Adresse IP';

  @override
  String get shareaccessesUseragentheader => 'Navigateur';

  @override
  String get shareaccessesRequestidheader => 'ID de corrélation';

  @override
  String get shareaccessesActioninfo => 'Consultation';

  @override
  String get shareaccessesActiondownload => 'Téléchargement';

  @override
  String get shareaccessesEmptytitle => 'Aucun accès enregistré';

  @override
  String get shareaccessesEmptybody =>
      'Ce document n\'a encore jamais été consulté ni téléchargé via le portail client.';

  @override
  String get shareaccessesLoaderror =>
      'Impossible de charger les accès. Réessayez dans un instant.';

  @override
  String get shareaccessesLoaddocumentserror =>
      'Impossible de charger les documents. Réessayez dans un instant.';

  @override
  String get shareaccessesLoading => 'Chargement…';

  @override
  String get shareaccessesPreviouspage => 'Page précédente';

  @override
  String get shareaccessesNextpage => 'Page suivante';

  @override
  String shareaccessesPageof(Object current, Object total) {
    return 'Page $current sur $total';
  }

  @override
  String shareaccessesTotal(Object count) {
    return '$count accès';
  }

  @override
  String get shareaccessesRetry => 'Réessayer';

  @override
  String get adminpaletteApploading => 'Chargement de l\'administration…';

  @override
  String get adminpaletteSearchplaceholder => 'Rechercher pages, actions…';

  @override
  String adminpaletteNoresults(Object query) {
    return 'Aucun résultat pour « $query »';
  }

  @override
  String get adminpaletteNavnavigate => 'naviguer';

  @override
  String get adminpaletteNavselect => 'sélectionner';

  @override
  String get adminpaletteNavclose => 'fermer';

  @override
  String get adminpaletteItemdashboard => 'Tableau de bord';

  @override
  String get adminpaletteItemdashboarddesc => 'Vue principale';

  @override
  String get adminpaletteItemanalytics => 'Analytics';

  @override
  String get adminpaletteItemanalyticsdesc => 'Statistiques et rapports';

  @override
  String get adminpaletteItemusers => 'Utilisateurs';

  @override
  String get adminpaletteItemusersdesc => 'Gestion des utilisateurs';

  @override
  String get adminpaletteItemcompanies => 'Entreprises';

  @override
  String get adminpaletteItemcompaniesdesc => 'Gestion des entreprises';

  @override
  String get adminpaletteItemsubscriptions => 'Abonnements';

  @override
  String get adminpaletteItemsubscriptionsdesc => 'Plans et facturation';

  @override
  String get adminpaletteItemsettings => 'Paramètres';

  @override
  String get adminpaletteItemsettingsdesc => 'Compte et préférences';

  @override
  String get adminpaletteItemgrowth => 'Growth';

  @override
  String get adminpaletteItemgrowthdesc => 'Partenaires et croissance';

  @override
  String get adminpaletteItemedge => 'Edge Nodes';

  @override
  String get adminpaletteItemedgedesc => 'Nœuds edge synchronisés';

  @override
  String get adminpaletteItemglobe => 'Globe';

  @override
  String get adminpaletteItemglobedesc => 'Présence mondiale en temps réel';

  @override
  String get adminpaletteItemfleet => 'Flotte';

  @override
  String get adminpaletteItemfleetdesc => 'Alertes flotte véhicules';

  @override
  String get adminpaletteItemmarketing => 'Marketing OAuth';

  @override
  String get adminpaletteItemmarketingdesc => 'Configuration OAuth marketing';

  @override
  String get adminpaletteItemsupport => 'Support';

  @override
  String get adminpaletteItemsupportdesc => 'Tickets support clients';

  @override
  String get adminpaletteItemcrm => 'CRM';

  @override
  String get adminpaletteItemcrmdesc => 'Pipeline CRM';

  @override
  String get adminpaletteItemtoggledark => 'Basculer mode sombre';

  @override
  String get adminpaletteItemtoggledarkdesc => 'Changer le thème';

  @override
  String get bankreconTitle => 'Rapprochement bancaire';

  @override
  String get bankreconSubtitle =>
      'File de matching manuel : rapprochez les lignes du relevé avec les paiements enregistrés (score de confiance du matching auto).';

  @override
  String get bankreconStatementstitle => 'Relevés';

  @override
  String get bankreconNostatements => 'Aucun relevé importé.';

  @override
  String get bankreconSelectstatement => 'Sélectionner un relevé…';

  @override
  String get bankreconPeriod => 'Période';

  @override
  String get bankreconReference => 'Référence';

  @override
  String get bankreconStatus => 'Statut';

  @override
  String get bankreconStatusimported => 'Importé';

  @override
  String get bankreconStatusreconciled => 'Rapproché';

  @override
  String get bankreconOpening => 'Solde d\'ouverture';

  @override
  String get bankreconClosingexpected => 'Clôture attendue';

  @override
  String get bankreconClosingreported => 'Clôture relevée';

  @override
  String get bankreconGap => 'Écart';

  @override
  String get bankreconTotallines => 'Lignes';

  @override
  String get bankreconMatchedlines => 'Rapprochées';

  @override
  String get bankreconPendinglines => 'En attente';

  @override
  String get bankreconMatchedamount => 'Montant rapproché';

  @override
  String get bankreconPendingamount => 'Montant en attente';

  @override
  String get bankreconLinedate => 'Date';

  @override
  String get bankreconLinelabel => 'Libellé';

  @override
  String get bankreconLineamount => 'Montant';

  @override
  String get bankreconLinestatus => 'État';

  @override
  String get bankreconStatuspending => 'À rapprocher';

  @override
  String get bankreconStatusmatched => 'Rapprochée';

  @override
  String get bankreconConfidence => 'Confiance';

  @override
  String get bankreconProposed => 'Proposé';

  @override
  String get bankreconMatch => 'Matcher';

  @override
  String get bankreconMatchpayment => 'Paiement';

  @override
  String get bankreconNopayments =>
      'Aucun paiement disponible pour cette période.';

  @override
  String get bankreconExportcsv => 'Exporter CSV';

  @override
  String get bankreconLoaderror => 'Impossible de charger le rapprochement.';

  @override
  String get bankreconMatcherror => 'Le rapprochement a échoué.';

  @override
  String get bankreconMatchdone => 'Ligne rapprochée.';

  @override
  String get bankreconLoading => 'Chargement…';

  @override
  String get bankreconRetry => 'Réessayer';

  @override
  String get organigrammeTitle => 'Organigramme';

  @override
  String get organigrammeBackTooltip => 'Retour';

  @override
  String get organigrammeEmptyTitle => 'Aucun organigramme';

  @override
  String get organigrammeLoading => 'Chargement de l\'organigramme...';

  @override
  String get vehicleMapTitle => 'Position véhicule';

  @override
  String get vehicleMapBackTooltip => 'Retour';

  @override
  String get vehicleMapEmptyTitle => 'Aucun véhicule';

  @override
  String get vehicleMapEmptyDescription =>
      'Vos véhicules assignés apparaîtront ici.';

  @override
  String get vehicleMapLoading => 'Chargement des véhicules...';

  @override
  String vehicleMapLastUpdate(Object time) {
    return 'Dernière MAJ : $time';
  }

  @override
  String vehicleMapSpeedKmh(Object speed) {
    return '$speed km/h';
  }

  @override
  String get payrollValidated => 'Validé';

  @override
  String payrollDocumentUnavailable(Object error) {
    return 'Document indisponible : $error';
  }

  @override
  String payrollOvertimeHoursTeam(Object hours) {
    return 'Heures supp (${hours}h)';
  }

  @override
  String payrollOvertimeHoursItem(Object hours) {
    return '+${hours}h supp';
  }

  @override
  String get platformAdminActivationNote =>
      'Activation directe depuis app mobile platform admin.';

  @override
  String get platformAdminCompanyApprovedNote =>
      'Approuvé depuis Leopardo Platform Admin mobile';

  @override
  String get platformAdminCompanyRejectedNote =>
      'Refusé depuis Leopardo Platform Admin mobile';

  @override
  String get edgeNodesTitle => 'Nœuds Edge';

  @override
  String get edgeNodesSubtitle => 'Sites on-premise connectés';

  @override
  String get edgeNodesRefreshTooltip => 'Rafraîchir';

  @override
  String get edgeNodesEmpty => 'Aucun nœud Edge enregistré.';

  @override
  String get edgeNodesOnline => 'En ligne';

  @override
  String get edgeNodesOffline => 'Hors ligne';

  @override
  String get edgeNodesSectionLabel => 'Nœuds';

  @override
  String get edgeNodesLoading => 'Chargement nœuds';

  @override
  String get edgeNodesForceSync => 'Forcer la synchronisation';

  @override
  String get edgeNodesSyncTriggered => 'Sync déclenchée.';

  @override
  String edgeNodesLastSync(Object date) {
    return 'Dernière sync : $date';
  }

  @override
  String edgeNodesNodeId(Object id) {
    return 'ID : $id';
  }

  @override
  String edgeNodesVersionInfo(Object count, Object version) {
    return 'v$version — $count employé(s)';
  }

  @override
  String get supportTicketsTitle => 'Support client';

  @override
  String get supportTicketsSubtitle => 'Tickets tenant';

  @override
  String get supportTicketsFilterAll => 'Tous';

  @override
  String get supportTicketsFilterOpen => 'Ouverts';

  @override
  String get supportTicketsFilterInProgress => 'En cours';

  @override
  String get supportTicketsFilterResolved => 'Résolus';

  @override
  String get supportTicketsFilterClosed => 'Fermés';

  @override
  String get supportTicketsEmpty => 'Aucun ticket pour ce filtre.';

  @override
  String get supportTicketsLoading => 'Chargement tickets';

  @override
  String get supportTicketsReplySent => 'Réponse envoyée.';

  @override
  String get supportTicketsLoadingTicket => 'Chargement ticket';

  @override
  String get settingsThemeTitle => 'Thème de l\'application';

  @override
  String get settingsThemeHint =>
      'Choisissez le thème affiché quelle que soit la configuration système.';

  @override
  String get settingsThemeSystem => 'Automatique (système)';

  @override
  String get settingsThemeLight => 'Clair';

  @override
  String get settingsThemeDark => 'Sombre';

  @override
  String get settingsBiometricCaptureFace => 'Capturer le visage';

  @override
  String get settingsBiometricConfirmIdentity =>
      'Vérifiez votre identité pour continuer.';

  @override
  String get settingsBiometricConsentRequired =>
      'Votre consentement est requis avant d\'activer la biométrie.';

  @override
  String get settingsBiometricEnableFirst =>
      'Activez d\'abord la biométrie dans les réglages.';

  @override
  String get settingsBiometricEnablePreparation =>
      'Préparation de l\'activation biométrique';

  @override
  String get settingsBiometricExplanation =>
      'Utilisez votre empreinte ou votre visage pour vous connecter plus rapidement et sécuriser vos accès.';

  @override
  String get settingsBiometricFaceRequired =>
      'L\'autorisation caméra est requise pour la reconnaissance faciale.';

  @override
  String get settingsBiometricFaceSelected => 'Visage sélectionné';

  @override
  String get settingsBiometricFaceWanted =>
      'Voulez-vous activer la reconnaissance faciale ?';

  @override
  String get settingsBiometricFingerprintWanted =>
      'Voulez-vous activer l\'empreinte digitale ?';

  @override
  String get settingsBiometricFutureConsent =>
      'Consentement pour les prochaines activations';

  @override
  String get settingsBiometricLocalVerifyCancel =>
      'Vérification biométrique annulée.';

  @override
  String get settingsBiometricNotesHint => 'Notes internes (optionnel)';

  @override
  String get settingsBiometricNotesTitle => 'Notes de l\'enrôlement';

  @override
  String get settingsBiometricPendingExplanation =>
      'En attente de confirmation de votre appareil…';

  @override
  String get settingsBiometricPreparationTitle =>
      'Préparation de l\'enrôlement biométrique';

  @override
  String get settingsBiometricSavedLocal =>
      'Réglages biométriques enregistrés sur cet appareil.';

  @override
  String get settingsBiometricSensorHint =>
      'Placez votre doigt sur le capteur…';

  @override
  String get settingsBiometricSensorLabel => 'Capteur';

  @override
  String settingsBiometricSubmitFailed(Object error) {
    return 'Échec de la soumission biométrique : $error';
  }

  @override
  String get settingsBiometricSubmitted =>
      'Enrôlement biométrique soumis pour validation.';

  @override
  String settingsBiometricTodayStatus(Object face, Object fingerprint) {
    return 'Visage : $face · Empreinte : $fingerprint';
  }

  @override
  String get settingsCabinetDocuments => 'Documents';

  @override
  String get settingsCabinetPublic => 'Public';

  @override
  String get settingsCabinetShared => 'Partagés';

  @override
  String get settingsCabinetSubtitle =>
      'Vos justificatifs et pièces partagées.';

  @override
  String get settingsChannelEmailHint =>
      'Recevez les alertes importantes par email.';

  @override
  String get settingsChannelInApp => 'In-app';

  @override
  String get settingsChannelInAppHint =>
      'Notifications affichées dans l\'application.';

  @override
  String get settingsChannelPush => 'Push';

  @override
  String get settingsChannelPushHint => 'Notifications push sur cet appareil.';

  @override
  String get settingsChannelSmsHint =>
      'SMS pour les codes et alertes critiques.';

  @override
  String get settingsChannelWhatsappHint =>
      'WhatsApp si configuré par l\'entreprise.';

  @override
  String get settingsCompanyQrLabel => 'QR entreprise';

  @override
  String get settingsDigitalLockerSubtitle =>
      'Stockez vos justificatifs en toute sécurité.';

  @override
  String get settingsDigitalLockerTitle => 'Casier numérique';

  @override
  String get settingsEdgeAddressLabel => 'Adresse du nœud Edge';

  @override
  String get settingsEdgeCloudStatus => 'Cloud';

  @override
  String settingsEdgeCurrentStatus(Object label) {
    return 'Statut actuel : $label';
  }

  @override
  String get settingsEdgeLocalStatus => 'Edge local';

  @override
  String get settingsEdgeNodeHint =>
      'Connectez l\'application à un nœud Edge local pour le mode hors-ligne.';

  @override
  String get settingsEdgeNodeTitle => 'Nœud Edge';

  @override
  String get settingsEdgeOfflineStatus => 'Hors-ligne';

  @override
  String get settingsEdgeRemoved => 'Nœud Edge déconnecté.';

  @override
  String get settingsEdgeTokenHint => 'Secret généré lors de l\'appairage.';

  @override
  String get settingsEdgeTokenLabel => 'Jeton Edge';

  @override
  String get settingsEdgeUuidHint => 'Identifiant unique du nœud.';

  @override
  String get settingsEdgeUuidLabel => 'UUID du nœud';

  @override
  String settingsJourneyAttachedTo(Object company) {
    return 'Actuellement chez $company';
  }

  @override
  String get settingsJourneyAvailable =>
      'Disponible pour une nouvelle entreprise';

  @override
  String settingsJourneyCompanyPeriod(Object company, Object period) {
    return '$company · $period';
  }

  @override
  String get settingsJourneyYourCompany => 'votre entreprise';

  @override
  String get settingsLanguageSyncHint =>
      'La langue choisie pilote aussi les notifications et les emails.';

  @override
  String get settingsLanguageUpdated => 'Langue enregistrée.';

  @override
  String get settingsNo => 'Non';

  @override
  String get settingsNotificationsIntro =>
      'Choisissez les canaux par lesquels vous recevez vos alertes.';

  @override
  String get settingsNotificationsLanguage => 'Langue des notifications';

  @override
  String get settingsNotificationsSaved =>
      'Préférences de notification enregistrées.';

  @override
  String settingsNotificationsSaveFailed(Object error) {
    return 'Échec de l\'enregistrement : $error';
  }

  @override
  String get settingsNotificationsUnavailable =>
      'Notifications indisponibles sur cet appareil.';

  @override
  String get settingsOpenLocker => 'Ouvrir le casier';

  @override
  String get settingsPasswordModernizationHint =>
      'Activez un mot de passe plus robuste pour sécuriser votre compte.';

  @override
  String get settingsPasteQrButton => 'Coller le QR';

  @override
  String get settingsPersonalPhoneHint => 'Numéro personnel (optionnel).';

  @override
  String get settingsPortableIdentityHint =>
      'Votre identité professionnelle portable.';

  @override
  String get settingsPortableIdentitySubtitle =>
      'QR code et pièces d\'identité de l\'entreprise.';

  @override
  String get settingsPortableIdentityTitle => 'Identité portable';

  @override
  String get settingsProfessionalQr => 'QR professionnel';

  @override
  String get settingsQrEmployeeHint => 'Présentez ce QR à votre employeur.';

  @override
  String get settingsQrOnboardingSubtitle =>
      'Intégrez rapidement un nouveau poste.';

  @override
  String get settingsQrOnboardingTitle => 'Onboarding par QR';

  @override
  String get settingsQrPasted => 'QR code collé.';

  @override
  String settingsQrRejected(Object error) {
    return 'QR rejeté : $error';
  }

  @override
  String get settingsRecoveryEmailHint => 'Email de secours (optionnel).';

  @override
  String get settingsRemove => 'Retirer';

  @override
  String get settingsRequestIntegration => 'Demander l\'intégration';

  @override
  String get settingsSavingShort => 'Enregistrement…';

  @override
  String get settingsSessionLogoutHint => 'Ferme la session sur cet appareil.';

  @override
  String get settingsSubmitBiometric => 'Soumettre l\'enrôlement';

  @override
  String get settingsSubmitting => 'Envoi…';

  @override
  String get settingsUpdateLanguage => 'Mettre à jour la langue';

  @override
  String get settingsUpdatePassword => 'Mettre à jour le mot de passe';

  @override
  String get settingsUpdating => 'Mise à jour…';

  @override
  String get settingsViewProfile => 'Voir le profil';

  @override
  String get settingsYes => 'Oui';

  @override
  String get commonBack => 'Retour';

  @override
  String get commonSave => 'Enregistrer';

  @override
  String get companiesActiveImmediatelyHint =>
      'Le changement est appliqué immédiatement.';

  @override
  String get companiesCompanyCreated => 'Société créée';

  @override
  String get companiesCompanyEmail => 'Email société';

  @override
  String get companiesCreateClient => 'Créer un client';

  @override
  String get companiesCreating => 'Création…';

  @override
  String get companiesEmpty => 'Aucune société';

  @override
  String get companiesNewClient => 'Nouveau client';

  @override
  String get companiesProvisioning => 'Provisionnement';

  @override
  String get companiesRequiredField => 'Champ obligatoire';

  @override
  String get companiesTenantsPlatform => 'Plateforme de tenancy';

  @override
  String get companiesTrialHint => 'Période d\'essai';

  @override
  String get companydetailActivateClient => 'Activer le client';

  @override
  String get companydetailActiveEmployees => 'Employés actifs';

  @override
  String get companydetailActiveModules => 'Modules actifs';

  @override
  String get companydetailAnomaliesCritical => 'Anomalies critiques';

  @override
  String get companydetailChoosePlan => 'Choisir un plan';

  @override
  String get companydetailClientActivated => 'Client activé';

  @override
  String get companydetailClientFile => 'Dossier client';

  @override
  String get companydetailClientReference => 'Référence client';

  @override
  String get companydetailCopyId => 'Copier l\'ID';

  @override
  String get companydetailCoreModuleAlwaysActive =>
      'Module cœur toujours actif';

  @override
  String get companydetailEditModules => 'Modifier les modules';

  @override
  String get companydetailEditSubscription => 'Modifier l\'abonnement';

  @override
  String get companydetailEmployeeLimit => 'Limite d\'employés';

  @override
  String get companydetailLoadingPlans => 'Chargement des plans…';

  @override
  String get companydetailModulesUpdated => 'Modules mis à jour';

  @override
  String get companydetailMonthlyPrice => 'Prix mensuel';

  @override
  String get companydetailNextActions => 'Prochaines actions';

  @override
  String get companydetailNoUrgentActions => 'Aucune action urgente';

  @override
  String get companydetailOptionalInternalNote => 'Note interne facultative';

  @override
  String get companydetailPlan => 'Plan';

  @override
  String get companydetailPlanNotFound => 'Plan introuvable';

  @override
  String get companydetailProductAdoption => 'Adoption produit';

  @override
  String get companydetailRecommendedActionHint => 'Action recommandée';

  @override
  String get companydetailSaveModules => 'Enregistrer les modules';

  @override
  String get companydetailSaving => 'Enregistrement…';

  @override
  String get companydetailStatus => 'Statut';

  @override
  String get companydetailSubscription => 'Abonnement';

  @override
  String get companydetailSubscriptionEnd => 'Fin d\'abonnement';

  @override
  String get companydetailSubscriptionUpdated => 'Abonnement mis à jour';

  @override
  String get companydetailTenantIdHint => 'ID de tenancy';

  @override
  String get companydetailUndefined => 'Non défini';

  @override
  String get companydetailUnlimited => 'Illimité';

  @override
  String get companyrequestsApprovedToast => 'Demande approuvée';

  @override
  String get companyrequestsRejectedToast => 'Demande rejetée';

  @override
  String get companyrequestsValidationSuperadmin => 'Réservé au super-admin';

  @override
  String get dashboardActiveLabel => 'Actifs';

  @override
  String get dashboardClientCompanies => 'Sociétés clientes';

  @override
  String get dashboardClientCompaniesHint =>
      'Sociétés actives sur la plateforme';

  @override
  String get dashboardClientRequestsHint => 'Demandes en attente';

  @override
  String get dashboardCreateCompany => 'Créer une société';

  @override
  String get dashboardCreateCompanyHint => 'Provisionner une nouvelle société';

  @override
  String get dashboardEdgeNodes => 'Nœuds Edge';

  @override
  String get dashboardEdgeNodesHint => 'Nœuds actifs sur le réseau';

  @override
  String get dashboardExecutiveView => 'Vue direction';

  @override
  String get dashboardLoadingCockpit => 'Chargement du cockpit…';

  @override
  String get dashboardPlatformActions => 'Actions plateforme';

  @override
  String get dashboardPlatformAdministration => 'Administration plateforme';

  @override
  String get dashboardSuperAdmin => 'Super-admin';

  @override
  String get dashboardSupportClient => 'Support client';

  @override
  String get dashboardTicketsHint => 'Tickets de support';

  @override
  String get dashboardTrials => 'Essais';

  @override
  String companydetailPlanWithPrice(Object name, Object price) {
    return '$name — $price €/mois';
  }

  @override
  String get twoFaTitle => 'Sécurité 2FA';

  @override
  String get twoFaSubtitle => 'Authentification à double facteur';

  @override
  String get twoFaLoading => 'Chargement…';

  @override
  String get twoFaGenericError =>
      'Une erreur est survenue. Merci de réessayer.';

  @override
  String get twoFaRequiredBanner =>
      'La double authentification est obligatoire pour votre profil. Activez-la dès maintenant.';

  @override
  String get twoFaStatusEnabled => '2FA active';

  @override
  String get twoFaStatusDisabled => '2FA désactivée';

  @override
  String get twoFaStatusEnabledHint =>
      'Un code est demandé à chaque connexion.';

  @override
  String get twoFaStatusDisabledHint =>
      'Protégez votre compte avec un code TOTP.';

  @override
  String get twoFaActivate => 'Activer la 2FA';

  @override
  String get twoFaScanPrompt =>
      'Scannez ce QR code avec Google Authenticator, Authy ou toute application compatible TOTP.';

  @override
  String get twoFaCopySecret => 'Copier le secret';

  @override
  String get twoFaConfirmHint =>
      'Saisissez le premier code généré par votre application pour activer la double authentification.';

  @override
  String get twoFaCodeLabel => 'Code à 6 chiffres';

  @override
  String get twoFaCodeHint => '000000';

  @override
  String get twoFaConfirm => 'Confirmer et activer';

  @override
  String get twoFaRecoveryTitle => 'Codes de récupération';

  @override
  String get twoFaRecoveryHint =>
      'Conservez ces codes en lieu sûr. Chacun ne peut être utilisé qu\'une seule fois.';

  @override
  String get twoFaCopied => 'Code copié.';

  @override
  String get twoFaAllCopied => 'Codes copiés.';

  @override
  String get twoFaCopyAll => 'Tout copier';

  @override
  String get twoFaDoneHint =>
      'Double authentification activée. À chaque connexion, un code vous sera demandé.';

  @override
  String get twoFaRegenerate => 'Régénérer les codes';

  @override
  String get twoFaRegenerateConfirm =>
      'Régénérer les codes de récupération ? Les anciens codes seront invalidés.';

  @override
  String get twoFaCancel => 'Annuler';

  @override
  String get twoFaDisable => 'Désactiver la 2FA';

  @override
  String get twoFaDisableHint => 'Code TOTP ou code de récupération';

  @override
  String get twoFaEnterCode => 'Code';

  @override
  String get twoFaInvalidCode => 'Code invalide. Vérifiez et réessayez.';

  @override
  String get twoFaSettingsTile => 'Double authentification';

  @override
  String get twoFaSettingsTileSubtitle =>
      'Code TOTP demandé à chaque connexion';
}
