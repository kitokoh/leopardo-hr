import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ar.dart';
import 'app_localizations_en.dart';
import 'app_localizations_fr.dart';
import 'app_localizations_tr.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'generated/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
      : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
    delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
  ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('ar'),
    Locale('en'),
    Locale('fr'),
    Locale('tr')
  ];

  /// No description provided for @appTitle.
  ///
  /// In fr, this message translates to:
  /// **'Leopardo RH'**
  String get appTitle;

  /// No description provided for @welcomeBrandSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Conversationnelle, mobile-first, modulaire.'**
  String get welcomeBrandSubtitle;

  /// No description provided for @welcomeHeroTitle.
  ///
  /// In fr, this message translates to:
  /// **'Votre journee commence ici, pas dans un back-office.'**
  String get welcomeHeroTitle;

  /// No description provided for @welcomeHeroDescription.
  ///
  /// In fr, this message translates to:
  /// **'Pointage, suivi personnel et modules RH actifs s ouvrent d abord sur le telephone, avec une experience simple et lisible.'**
  String get welcomeHeroDescription;

  /// No description provided for @welcomeStoryClarityTitle.
  ///
  /// In fr, this message translates to:
  /// **'Une home qui vous parle avant de vous noyer'**
  String get welcomeStoryClarityTitle;

  /// No description provided for @welcomeStoryClarityBody.
  ///
  /// In fr, this message translates to:
  /// **'Leopardo RH commence par quelques actions claires: pointer, suivre le mois et retrouver les informations qui comptent.'**
  String get welcomeStoryClarityBody;

  /// No description provided for @welcomeStoryFieldTitle.
  ///
  /// In fr, this message translates to:
  /// **'Mobile-first pour le terrain'**
  String get welcomeStoryFieldTitle;

  /// No description provided for @welcomeStoryFieldBody.
  ///
  /// In fr, this message translates to:
  /// **'Le telephone est la surface principale de l employe. Votre pointage, vos absences et vos documents vivent ici.'**
  String get welcomeStoryFieldBody;

  /// No description provided for @welcomeStoryModulesTitle.
  ///
  /// In fr, this message translates to:
  /// **'Modules actifs, feuille de route visible'**
  String get welcomeStoryModulesTitle;

  /// No description provided for @welcomeStoryModulesBody.
  ///
  /// In fr, this message translates to:
  /// **'Le produit ouvre d abord ce qui est utile aujourd hui, puis garde Finance, Securite et Leo dans un cap lisible.'**
  String get welcomeStoryModulesBody;

  /// No description provided for @login.
  ///
  /// In fr, this message translates to:
  /// **'Se connecter'**
  String get login;

  /// No description provided for @employeeInvitationAccess.
  ///
  /// In fr, this message translates to:
  /// **'Acces employe (invitation)'**
  String get employeeInvitationAccess;

  /// No description provided for @createPersonalAccount.
  ///
  /// In fr, this message translates to:
  /// **'Creer un compte personnel'**
  String get createPersonalAccount;

  /// No description provided for @personalAccountExplanation.
  ///
  /// In fr, this message translates to:
  /// **'Compte personnel : organisez vos documents, puis creez ou rejoignez une entreprise depuis votre espace.'**
  String get personalAccountExplanation;

  /// No description provided for @authBackTooltip.
  ///
  /// In fr, this message translates to:
  /// **'Retour'**
  String get authBackTooltip;

  /// No description provided for @authEmployeeLoginSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Connexion employe'**
  String get authEmployeeLoginSubtitle;

  /// No description provided for @authManagerLoginSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Connexion Manager / RH'**
  String get authManagerLoginSubtitle;

  /// No description provided for @authEmailProfessionalLabel.
  ///
  /// In fr, this message translates to:
  /// **'Email professionnel'**
  String get authEmailProfessionalLabel;

  /// No description provided for @authEmailLabel.
  ///
  /// In fr, this message translates to:
  /// **'Email'**
  String get authEmailLabel;

  /// No description provided for @authEmailRequired.
  ///
  /// In fr, this message translates to:
  /// **'Email obligatoire'**
  String get authEmailRequired;

  /// No description provided for @authEmailInvalid.
  ///
  /// In fr, this message translates to:
  /// **'Email invalide'**
  String get authEmailInvalid;

  /// No description provided for @authPasswordLabel.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe'**
  String get authPasswordLabel;

  /// No description provided for @authPasswordRequired.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe obligatoire'**
  String get authPasswordRequired;

  /// No description provided for @authPasswordTooShort.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe trop court'**
  String get authPasswordTooShort;

  /// No description provided for @authContinueWithGoogle.
  ///
  /// In fr, this message translates to:
  /// **'Continuer avec Google'**
  String get authContinueWithGoogle;

  /// No description provided for @authActivateInvitation.
  ///
  /// In fr, this message translates to:
  /// **'Activer mon invitation'**
  String get authActivateInvitation;

  /// No description provided for @authPersonalAccountLink.
  ///
  /// In fr, this message translates to:
  /// **'Compte perso'**
  String get authPersonalAccountLink;

  /// No description provided for @authActivateManagerAccess.
  ///
  /// In fr, this message translates to:
  /// **'Activer mon acces manager'**
  String get authActivateManagerAccess;

  /// No description provided for @authTryDemoAccount.
  ///
  /// In fr, this message translates to:
  /// **'Tester avec un compte demo'**
  String get authTryDemoAccount;

  /// No description provided for @commonLanguageLabel.
  ///
  /// In fr, this message translates to:
  /// **'Langue'**
  String get commonLanguageLabel;

  /// No description provided for @commonLanguageVariantsFrFr.
  ///
  /// In fr, this message translates to:
  /// **'Francais (France)'**
  String get commonLanguageVariantsFrFr;

  /// No description provided for @commonLanguageVariantsFrBe.
  ///
  /// In fr, this message translates to:
  /// **'Francais (Belgique)'**
  String get commonLanguageVariantsFrBe;

  /// No description provided for @commonLanguageVariantsFrCa.
  ///
  /// In fr, this message translates to:
  /// **'Francais (Canada)'**
  String get commonLanguageVariantsFrCa;

  /// No description provided for @commonLanguageVariantsArSa.
  ///
  /// In fr, this message translates to:
  /// **'Arabe (Arabie saoudite)'**
  String get commonLanguageVariantsArSa;

  /// No description provided for @commonLanguageVariantsArMa.
  ///
  /// In fr, this message translates to:
  /// **'Arabe (Maroc)'**
  String get commonLanguageVariantsArMa;

  /// No description provided for @commonLanguageVariantsTrTr.
  ///
  /// In fr, this message translates to:
  /// **'Turc (Turquie)'**
  String get commonLanguageVariantsTrTr;

  /// No description provided for @commonLanguageVariantsEnUs.
  ///
  /// In fr, this message translates to:
  /// **'Anglais (Etats-Unis)'**
  String get commonLanguageVariantsEnUs;

  /// No description provided for @commonLanguageVariantsEnGb.
  ///
  /// In fr, this message translates to:
  /// **'Anglais (Royaume-Uni)'**
  String get commonLanguageVariantsEnGb;

  /// No description provided for @commonOr.
  ///
  /// In fr, this message translates to:
  /// **'ou'**
  String get commonOr;

  /// No description provided for @modulesAttendance.
  ///
  /// In fr, this message translates to:
  /// **'Pointage'**
  String get modulesAttendance;

  /// No description provided for @modulesPayroll.
  ///
  /// In fr, this message translates to:
  /// **'Paie'**
  String get modulesPayroll;

  /// No description provided for @modulesCabinet.
  ///
  /// In fr, this message translates to:
  /// **'Coffre documentaire'**
  String get modulesCabinet;

  /// No description provided for @modulesNotifications.
  ///
  /// In fr, this message translates to:
  /// **'Notifications'**
  String get modulesNotifications;

  /// No description provided for @modulesEvaluations.
  ///
  /// In fr, this message translates to:
  /// **'Evaluations'**
  String get modulesEvaluations;

  /// No description provided for @emailsInvitationSubject.
  ///
  /// In fr, this message translates to:
  /// **'Vous etes invite(e) a rejoindre :company sur Leopardo RH'**
  String get emailsInvitationSubject;

  /// No description provided for @emailsInvitationGreeting.
  ///
  /// In fr, this message translates to:
  /// **'Bonjour :name,'**
  String get emailsInvitationGreeting;

  /// No description provided for @emailsInvitationBody.
  ///
  /// In fr, this message translates to:
  /// **'Vous avez ete invite(e) a rejoindre :company. Cliquez sur le lien ci-dessous pour activer votre compte.'**
  String get emailsInvitationBody;

  /// No description provided for @emailsInvitationAction.
  ///
  /// In fr, this message translates to:
  /// **'Activer mon compte'**
  String get emailsInvitationAction;

  /// No description provided for @emailsInvitationFooter.
  ///
  /// In fr, this message translates to:
  /// **'Si vous n avez pas demande cette action, ignorez cet email.'**
  String get emailsInvitationFooter;

  /// No description provided for @emailsResetPasswordSubject.
  ///
  /// In fr, this message translates to:
  /// **'Reinitialisation de votre mot de passe'**
  String get emailsResetPasswordSubject;

  /// No description provided for @emailsResetPasswordGreeting.
  ///
  /// In fr, this message translates to:
  /// **'Bonjour :name,'**
  String get emailsResetPasswordGreeting;

  /// No description provided for @emailsResetPasswordBody.
  ///
  /// In fr, this message translates to:
  /// **'Cliquez sur le lien ci-dessous pour reinitialiser votre mot de passe.'**
  String get emailsResetPasswordBody;

  /// No description provided for @emailsResetPasswordAction.
  ///
  /// In fr, this message translates to:
  /// **'Reinitialiser le mot de passe'**
  String get emailsResetPasswordAction;

  /// No description provided for @emailsResetPasswordFooter.
  ///
  /// In fr, this message translates to:
  /// **'Si vous n avez pas demande cette action, ignorez cet email.'**
  String get emailsResetPasswordFooter;

  /// No description provided for @emailsPayrollReadySubject.
  ///
  /// In fr, this message translates to:
  /// **'Votre bulletin de paie est disponible'**
  String get emailsPayrollReadySubject;

  /// No description provided for @emailsPayrollReadyGreeting.
  ///
  /// In fr, this message translates to:
  /// **'Bonjour :name,'**
  String get emailsPayrollReadyGreeting;

  /// No description provided for @emailsPayrollReadyBody.
  ///
  /// In fr, this message translates to:
  /// **'Votre bulletin de paie pour :period est pret. Vous pouvez le consulter dans Leopardo RH.'**
  String get emailsPayrollReadyBody;

  /// No description provided for @emailsPayrollReadyAction.
  ///
  /// In fr, this message translates to:
  /// **'Voir mon bulletin'**
  String get emailsPayrollReadyAction;

  /// No description provided for @emailsPayrollReadyFooter.
  ///
  /// In fr, this message translates to:
  /// **'Merci de verifier vos informations avant export comptable.'**
  String get emailsPayrollReadyFooter;

  /// No description provided for @emailsAbsenceApprovedSubject.
  ///
  /// In fr, this message translates to:
  /// **'Votre absence a ete approuvee'**
  String get emailsAbsenceApprovedSubject;

  /// No description provided for @emailsAbsenceApprovedGreeting.
  ///
  /// In fr, this message translates to:
  /// **'Bonjour :name,'**
  String get emailsAbsenceApprovedGreeting;

  /// No description provided for @emailsAbsenceApprovedBody.
  ///
  /// In fr, this message translates to:
  /// **'Votre demande d absence pour :period a ete approuvee.'**
  String get emailsAbsenceApprovedBody;

  /// No description provided for @emailsAbsenceApprovedAction.
  ///
  /// In fr, this message translates to:
  /// **'Voir la demande'**
  String get emailsAbsenceApprovedAction;

  /// No description provided for @emailsAbsenceApprovedFooter.
  ///
  /// In fr, this message translates to:
  /// **'Le planning equipe a ete mis a jour.'**
  String get emailsAbsenceApprovedFooter;

  /// No description provided for @emailsAbsenceRejectedSubject.
  ///
  /// In fr, this message translates to:
  /// **'Votre absence a ete refusee'**
  String get emailsAbsenceRejectedSubject;

  /// No description provided for @emailsAbsenceRejectedGreeting.
  ///
  /// In fr, this message translates to:
  /// **'Bonjour :name,'**
  String get emailsAbsenceRejectedGreeting;

  /// No description provided for @emailsAbsenceRejectedBody.
  ///
  /// In fr, this message translates to:
  /// **'Votre demande d absence pour :period a ete refusee.'**
  String get emailsAbsenceRejectedBody;

  /// No description provided for @emailsAbsenceRejectedAction.
  ///
  /// In fr, this message translates to:
  /// **'Voir la demande'**
  String get emailsAbsenceRejectedAction;

  /// No description provided for @emailsAbsenceRejectedFooter.
  ///
  /// In fr, this message translates to:
  /// **'Consultez votre manager si vous avez besoin d un complement.'**
  String get emailsAbsenceRejectedFooter;

  /// No description provided for @profileTitle.
  ///
  /// In fr, this message translates to:
  /// **'Mon profil'**
  String get profileTitle;

  /// No description provided for @profileSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Informations personnelles et langue'**
  String get profileSubtitle;

  /// No description provided for @profileLoading.
  ///
  /// In fr, this message translates to:
  /// **'Chargement du profil...'**
  String get profileLoading;

  /// No description provided for @profileBackTooltip.
  ///
  /// In fr, this message translates to:
  /// **'Retour'**
  String get profileBackTooltip;

  /// No description provided for @profileJobTitleUnset.
  ///
  /// In fr, this message translates to:
  /// **'Poste non renseigne'**
  String get profileJobTitleUnset;

  /// No description provided for @profileDetailsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Informations'**
  String get profileDetailsTitle;

  /// No description provided for @profileEmailLabel.
  ///
  /// In fr, this message translates to:
  /// **'Email'**
  String get profileEmailLabel;

  /// No description provided for @profileDepartmentLabel.
  ///
  /// In fr, this message translates to:
  /// **'Departement'**
  String get profileDepartmentLabel;

  /// No description provided for @profileJobTitleLabel.
  ///
  /// In fr, this message translates to:
  /// **'Poste'**
  String get profileJobTitleLabel;

  /// No description provided for @profileMatriculeLabel.
  ///
  /// In fr, this message translates to:
  /// **'Matricule'**
  String get profileMatriculeLabel;

  /// No description provided for @profileValueUnset.
  ///
  /// In fr, this message translates to:
  /// **'Non renseigne'**
  String get profileValueUnset;

  /// No description provided for @profileOpenSettings.
  ///
  /// In fr, this message translates to:
  /// **'Ouvrir les parametres du compte'**
  String get profileOpenSettings;

  /// No description provided for @profileLanguageUpdated.
  ///
  /// In fr, this message translates to:
  /// **'Langue mise a jour.'**
  String get profileLanguageUpdated;

  /// No description provided for @profileLanguageSaving.
  ///
  /// In fr, this message translates to:
  /// **'Mise a jour...'**
  String get profileLanguageSaving;

  /// No description provided for @profileLanguageSave.
  ///
  /// In fr, this message translates to:
  /// **'Mettre a jour la langue'**
  String get profileLanguageSave;

  /// No description provided for @aiChatTitle.
  ///
  /// In fr, this message translates to:
  /// **'Assistant IA'**
  String get aiChatTitle;

  /// No description provided for @aiChatBackTooltip.
  ///
  /// In fr, this message translates to:
  /// **'Retour'**
  String get aiChatBackTooltip;

  /// No description provided for @aiChatSendTooltip.
  ///
  /// In fr, this message translates to:
  /// **'Envoyer'**
  String get aiChatSendTooltip;

  /// No description provided for @aiChatInputHint.
  ///
  /// In fr, this message translates to:
  /// **'Tapez votre message...'**
  String get aiChatInputHint;

  /// No description provided for @aiChatEmptyStateTitle.
  ///
  /// In fr, this message translates to:
  /// **'Posez vos questions RH'**
  String get aiChatEmptyStateTitle;

  /// No description provided for @aiChatErrorMessage.
  ///
  /// In fr, this message translates to:
  /// **'Erreur : impossible de contacter l assistant.'**
  String get aiChatErrorMessage;

  /// No description provided for @platformLoginTitle.
  ///
  /// In fr, this message translates to:
  /// **'Leopardo Platform'**
  String get platformLoginTitle;

  /// No description provided for @platformLoginSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Cockpit mobile reserve a l administration de la plateforme.'**
  String get platformLoginSubtitle;

  /// No description provided for @platformLogin2faNotice.
  ///
  /// In fr, this message translates to:
  /// **'Ce compte protege la plateforme : saisir le code 2FA de l application authenticator.'**
  String get platformLogin2faNotice;

  /// No description provided for @platformLoginEmailLabel.
  ///
  /// In fr, this message translates to:
  /// **'Email super-admin'**
  String get platformLoginEmailLabel;

  /// No description provided for @platformLoginEmailRequired.
  ///
  /// In fr, this message translates to:
  /// **'Email requis'**
  String get platformLoginEmailRequired;

  /// No description provided for @platformLoginPasswordRequired.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe requis'**
  String get platformLoginPasswordRequired;

  /// No description provided for @platformLogin2faLabel.
  ///
  /// In fr, this message translates to:
  /// **'Code 2FA si active'**
  String get platformLogin2faLabel;

  /// No description provided for @platformLoginSubmitting.
  ///
  /// In fr, this message translates to:
  /// **'Connexion...'**
  String get platformLoginSubmitting;

  /// No description provided for @platformLoginUseDemoAccount.
  ///
  /// In fr, this message translates to:
  /// **'Utiliser le compte demo'**
  String get platformLoginUseDemoAccount;

  /// No description provided for @usersPageTitle.
  ///
  /// In fr, this message translates to:
  /// **'Gestion des Utilisateurs'**
  String get usersPageTitle;

  /// No description provided for @usersPageSummary.
  ///
  /// In fr, this message translates to:
  /// **':count utilisateur(s) - :active actif(s) - :newToday nouveau(x) aujourd\'hui'**
  String get usersPageSummary;

  /// No description provided for @usersActionsBulk.
  ///
  /// In fr, this message translates to:
  /// **'Actions (:count)'**
  String get usersActionsBulk;

  /// No description provided for @usersActionsExport.
  ///
  /// In fr, this message translates to:
  /// **'Exporter'**
  String get usersActionsExport;

  /// No description provided for @usersActionsNew.
  ///
  /// In fr, this message translates to:
  /// **'Nouveau'**
  String get usersActionsNew;

  /// No description provided for @usersFiltersSearchLabel.
  ///
  /// In fr, this message translates to:
  /// **'Rechercher'**
  String get usersFiltersSearchLabel;

  /// No description provided for @usersFiltersSearchPlaceholder.
  ///
  /// In fr, this message translates to:
  /// **'Nom, email, entreprise...'**
  String get usersFiltersSearchPlaceholder;

  /// No description provided for @usersFiltersStatusLabel.
  ///
  /// In fr, this message translates to:
  /// **'Statut'**
  String get usersFiltersStatusLabel;

  /// No description provided for @usersFiltersStatusAll.
  ///
  /// In fr, this message translates to:
  /// **'Tous les statuts'**
  String get usersFiltersStatusAll;

  /// No description provided for @usersFiltersStatusActive.
  ///
  /// In fr, this message translates to:
  /// **'Actif'**
  String get usersFiltersStatusActive;

  /// No description provided for @usersFiltersStatusInactive.
  ///
  /// In fr, this message translates to:
  /// **'Inactif'**
  String get usersFiltersStatusInactive;

  /// No description provided for @usersFiltersStatusSuspended.
  ///
  /// In fr, this message translates to:
  /// **'Suspendu'**
  String get usersFiltersStatusSuspended;

  /// No description provided for @usersFiltersStatusPending.
  ///
  /// In fr, this message translates to:
  /// **'En attente'**
  String get usersFiltersStatusPending;

  /// No description provided for @usersFiltersRoleLabel.
  ///
  /// In fr, this message translates to:
  /// **'Role'**
  String get usersFiltersRoleLabel;

  /// No description provided for @usersFiltersRoleAll.
  ///
  /// In fr, this message translates to:
  /// **'Tous les roles'**
  String get usersFiltersRoleAll;

  /// No description provided for @usersFiltersRoleAdmin.
  ///
  /// In fr, this message translates to:
  /// **'Administrateur'**
  String get usersFiltersRoleAdmin;

  /// No description provided for @usersFiltersRoleManager.
  ///
  /// In fr, this message translates to:
  /// **'Manager'**
  String get usersFiltersRoleManager;

  /// No description provided for @usersFiltersRoleEmployee.
  ///
  /// In fr, this message translates to:
  /// **'Employe'**
  String get usersFiltersRoleEmployee;

  /// No description provided for @usersFiltersRoleHr.
  ///
  /// In fr, this message translates to:
  /// **'RH'**
  String get usersFiltersRoleHr;

  /// No description provided for @usersFiltersCompanyLabel.
  ///
  /// In fr, this message translates to:
  /// **'Entreprise'**
  String get usersFiltersCompanyLabel;

  /// No description provided for @usersFiltersCompanyAll.
  ///
  /// In fr, this message translates to:
  /// **'Toutes les entreprises'**
  String get usersFiltersCompanyAll;

  /// No description provided for @usersFiltersRegistrationdateLabel.
  ///
  /// In fr, this message translates to:
  /// **'Date d\'inscription'**
  String get usersFiltersRegistrationdateLabel;

  /// No description provided for @usersFiltersRegistrationdateAll.
  ///
  /// In fr, this message translates to:
  /// **'Toutes les dates'**
  String get usersFiltersRegistrationdateAll;

  /// No description provided for @usersFiltersRegistrationdateToday.
  ///
  /// In fr, this message translates to:
  /// **'Aujourd\'hui'**
  String get usersFiltersRegistrationdateToday;

  /// No description provided for @usersFiltersRegistrationdateWeek.
  ///
  /// In fr, this message translates to:
  /// **'Cette semaine'**
  String get usersFiltersRegistrationdateWeek;

  /// No description provided for @usersFiltersRegistrationdateMonth.
  ///
  /// In fr, this message translates to:
  /// **'Ce mois'**
  String get usersFiltersRegistrationdateMonth;

  /// No description provided for @usersFiltersRegistrationdateQuarter.
  ///
  /// In fr, this message translates to:
  /// **'Ce trimestre'**
  String get usersFiltersRegistrationdateQuarter;

  /// No description provided for @usersFiltersLastloginLabel.
  ///
  /// In fr, this message translates to:
  /// **'Derniere connexion'**
  String get usersFiltersLastloginLabel;

  /// No description provided for @usersFiltersLastloginAll.
  ///
  /// In fr, this message translates to:
  /// **'Toutes'**
  String get usersFiltersLastloginAll;

  /// No description provided for @usersFiltersLastloginToday.
  ///
  /// In fr, this message translates to:
  /// **'Aujourd\'hui'**
  String get usersFiltersLastloginToday;

  /// No description provided for @usersFiltersLastloginWeek.
  ///
  /// In fr, this message translates to:
  /// **'Cette semaine'**
  String get usersFiltersLastloginWeek;

  /// No description provided for @usersFiltersLastloginMonth.
  ///
  /// In fr, this message translates to:
  /// **'Ce mois'**
  String get usersFiltersLastloginMonth;

  /// No description provided for @usersFiltersLastloginNever.
  ///
  /// In fr, this message translates to:
  /// **'Jamais connecte'**
  String get usersFiltersLastloginNever;

  /// No description provided for @usersFiltersSegmentLabel.
  ///
  /// In fr, this message translates to:
  /// **'Segment'**
  String get usersFiltersSegmentLabel;

  /// No description provided for @usersFiltersSegmentAll.
  ///
  /// In fr, this message translates to:
  /// **'Tous les segments'**
  String get usersFiltersSegmentAll;

  /// No description provided for @usersFiltersSegmentChampions.
  ///
  /// In fr, this message translates to:
  /// **'Champions'**
  String get usersFiltersSegmentChampions;

  /// No description provided for @usersFiltersSegmentLoyal.
  ///
  /// In fr, this message translates to:
  /// **'Loyaux'**
  String get usersFiltersSegmentLoyal;

  /// No description provided for @usersFiltersSegmentPotential.
  ///
  /// In fr, this message translates to:
  /// **'Potentiels'**
  String get usersFiltersSegmentPotential;

  /// No description provided for @usersFiltersSegmentNew.
  ///
  /// In fr, this message translates to:
  /// **'Nouveaux'**
  String get usersFiltersSegmentNew;

  /// No description provided for @usersFiltersSegmentAtrisk.
  ///
  /// In fr, this message translates to:
  /// **'A risque'**
  String get usersFiltersSegmentAtrisk;

  /// No description provided for @usersFiltersAdvancedShow.
  ///
  /// In fr, this message translates to:
  /// **'Afficher les filtres avances'**
  String get usersFiltersAdvancedShow;

  /// No description provided for @usersFiltersAdvancedHide.
  ///
  /// In fr, this message translates to:
  /// **'Masquer les filtres avances'**
  String get usersFiltersAdvancedHide;

  /// No description provided for @usersBulkpanelSelectedcount.
  ///
  /// In fr, this message translates to:
  /// **':count selectionne(s)'**
  String get usersBulkpanelSelectedcount;

  /// No description provided for @usersBulkpanelActivate.
  ///
  /// In fr, this message translates to:
  /// **'Activer'**
  String get usersBulkpanelActivate;

  /// No description provided for @usersBulkpanelDeactivate.
  ///
  /// In fr, this message translates to:
  /// **'Desactiver'**
  String get usersBulkpanelDeactivate;

  /// No description provided for @usersBulkpanelSuspend.
  ///
  /// In fr, this message translates to:
  /// **'Suspendre'**
  String get usersBulkpanelSuspend;

  /// No description provided for @usersBulkpanelExport.
  ///
  /// In fr, this message translates to:
  /// **'Exporter'**
  String get usersBulkpanelExport;

  /// No description provided for @usersBulkpanelCancel.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get usersBulkpanelCancel;

  /// No description provided for @usersToastLoaderror.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors du chargement des utilisateurs'**
  String get usersToastLoaderror;

  /// No description provided for @usersToastBulkactivated.
  ///
  /// In fr, this message translates to:
  /// **':count utilisateur(s) active(s)'**
  String get usersToastBulkactivated;

  /// No description provided for @usersToastBulkdeactivated.
  ///
  /// In fr, this message translates to:
  /// **':count utilisateur(s) desactive(s)'**
  String get usersToastBulkdeactivated;

  /// No description provided for @usersToastBulksuspended.
  ///
  /// In fr, this message translates to:
  /// **':count utilisateur(s) suspendu(s)'**
  String get usersToastBulksuspended;

  /// No description provided for @usersToastBulkerror.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors de l\'action groupee'**
  String get usersToastBulkerror;

  /// No description provided for @usersToastDeleted.
  ///
  /// In fr, this message translates to:
  /// **'Utilisateur supprime'**
  String get usersToastDeleted;

  /// No description provided for @usersToastDeleteerror.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors de la suppression'**
  String get usersToastDeleteerror;

  /// No description provided for @usersToastImpersonating.
  ///
  /// In fr, this message translates to:
  /// **'Connexion en tant que :name'**
  String get usersToastImpersonating;

  /// No description provided for @usersToastCreated.
  ///
  /// In fr, this message translates to:
  /// **'Utilisateur cree avec succes'**
  String get usersToastCreated;

  /// No description provided for @usersToastUpdated.
  ///
  /// In fr, this message translates to:
  /// **'Utilisateur mis a jour'**
  String get usersToastUpdated;

  /// No description provided for @usersToastExportinprogress.
  ///
  /// In fr, this message translates to:
  /// **'Export en cours...'**
  String get usersToastExportinprogress;

  /// No description provided for @usersToastExportdone.
  ///
  /// In fr, this message translates to:
  /// **'Export termine'**
  String get usersToastExportdone;

  /// No description provided for @usersToastExporterror.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors de l\'export'**
  String get usersToastExporterror;

  /// No description provided for @usersToastSelectionexportdone.
  ///
  /// In fr, this message translates to:
  /// **'Export de la selection termine'**
  String get usersToastSelectionexportdone;

  /// No description provided for @usersConfirmDelete.
  ///
  /// In fr, this message translates to:
  /// **'Etes-vous sur de vouloir supprimer :name ?'**
  String get usersConfirmDelete;

  /// No description provided for @dashboardTitle.
  ///
  /// In fr, this message translates to:
  /// **'Tableau de bord'**
  String get dashboardTitle;

  /// No description provided for @dashboardCompany.
  ///
  /// In fr, this message translates to:
  /// **'Entreprise'**
  String get dashboardCompany;

  /// No description provided for @dashboardActiveEmployees.
  ///
  /// In fr, this message translates to:
  /// **'Actifs'**
  String get dashboardActiveEmployees;

  /// No description provided for @dashboardUpgrade.
  ///
  /// In fr, this message translates to:
  /// **'Upgrade'**
  String get dashboardUpgrade;

  /// No description provided for @dashboardPriorityActions.
  ///
  /// In fr, this message translates to:
  /// **'Actions prioritaires'**
  String get dashboardPriorityActions;

  /// No description provided for @dashboardLaunchReadiness.
  ///
  /// In fr, this message translates to:
  /// **'Readiness lancement'**
  String get dashboardLaunchReadiness;

  /// No description provided for @dashboardRecentActivity.
  ///
  /// In fr, this message translates to:
  /// **'Activite recente'**
  String get dashboardRecentActivity;

  /// No description provided for @dashboardRecentActivityHint.
  ///
  /// In fr, this message translates to:
  /// **'Dernieres actions de votre equipe'**
  String get dashboardRecentActivityHint;

  /// No description provided for @marketingOauthNavTitle.
  ///
  /// In fr, this message translates to:
  /// **'Marketing OAuth'**
  String get marketingOauthNavTitle;

  /// No description provided for @marketingOauthTitle.
  ///
  /// In fr, this message translates to:
  /// **'Paramètres OAuth Marketing'**
  String get marketingOauthTitle;

  /// No description provided for @marketingOauthSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Connectez vos comptes réseaux sociaux via Ayrshare'**
  String get marketingOauthSubtitle;

  /// No description provided for @marketingOauthAyrshareInfo.
  ///
  /// In fr, this message translates to:
  /// **'Ces paramètres sont utilisés par Ayrshare pour publier sur vos réseaux sociaux.'**
  String get marketingOauthAyrshareInfo;

  /// No description provided for @marketingOauthSave.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer'**
  String get marketingOauthSave;

  /// No description provided for @marketingOauthSavedOk.
  ///
  /// In fr, this message translates to:
  /// **'Configuration {provider} enregistrée'**
  String marketingOauthSavedOk(Object provider);

  /// No description provided for @marketingOauthProvidersLinkedinLabel.
  ///
  /// In fr, this message translates to:
  /// **'LinkedIn'**
  String get marketingOauthProvidersLinkedinLabel;

  /// No description provided for @marketingOauthProvidersLinkedinDescription.
  ///
  /// In fr, this message translates to:
  /// **'Connexion à l\'API LinkedIn Marketing'**
  String get marketingOauthProvidersLinkedinDescription;

  /// No description provided for @marketingOauthProvidersFacebookLabel.
  ///
  /// In fr, this message translates to:
  /// **'Facebook / Meta'**
  String get marketingOauthProvidersFacebookLabel;

  /// No description provided for @marketingOauthProvidersFacebookDescription.
  ///
  /// In fr, this message translates to:
  /// **'Connexion à l\'API Facebook Graph'**
  String get marketingOauthProvidersFacebookDescription;

  /// No description provided for @marketingOauthProvidersTwitterLabel.
  ///
  /// In fr, this message translates to:
  /// **'X (Twitter)'**
  String get marketingOauthProvidersTwitterLabel;

  /// No description provided for @marketingOauthProvidersTwitterDescription.
  ///
  /// In fr, this message translates to:
  /// **'Connexion à l\'API Twitter v2'**
  String get marketingOauthProvidersTwitterDescription;

  /// No description provided for @marketingOauthFieldsClientId.
  ///
  /// In fr, this message translates to:
  /// **'Client ID'**
  String get marketingOauthFieldsClientId;

  /// No description provided for @marketingOauthFieldsClientSecret.
  ///
  /// In fr, this message translates to:
  /// **'Client Secret'**
  String get marketingOauthFieldsClientSecret;

  /// No description provided for @marketingOauthFieldsRedirectUri.
  ///
  /// In fr, this message translates to:
  /// **'Redirect URI'**
  String get marketingOauthFieldsRedirectUri;

  /// No description provided for @marketingOauthFieldsSecretHint.
  ///
  /// In fr, this message translates to:
  /// **'(laissez vide pour conserver)'**
  String get marketingOauthFieldsSecretHint;

  /// No description provided for @marketingOauthFieldsPlaceholderId.
  ///
  /// In fr, this message translates to:
  /// **'Votre Client ID'**
  String get marketingOauthFieldsPlaceholderId;

  /// No description provided for @marketingOauthFieldsPlaceholderSecret.
  ///
  /// In fr, this message translates to:
  /// **'Nouveau secret (optionnel)'**
  String get marketingOauthFieldsPlaceholderSecret;

  /// No description provided for @marketingOauthFieldsPlaceholderUri.
  ///
  /// In fr, this message translates to:
  /// **'https://example.com/oauth/callback'**
  String get marketingOauthFieldsPlaceholderUri;

  /// No description provided for @attendanceSendingToServer.
  ///
  /// In fr, this message translates to:
  /// **'{label} vers le serveur...'**
  String attendanceSendingToServer(Object label);

  /// No description provided for @attendanceRetryAfterFailure.
  ///
  /// In fr, this message translates to:
  /// **'{label}. Reessayez.'**
  String attendanceRetryAfterFailure(Object label);
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ar', 'en', 'fr', 'tr'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ar':
      return AppLocalizationsAr();
    case 'en':
      return AppLocalizationsEn();
    case 'fr':
      return AppLocalizationsFr();
    case 'tr':
      return AppLocalizationsTr();
  }

  throw FlutterError(
      'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
      'an issue with the localizations generation tool. Please file an issue '
      'on GitHub with a reproducible sample app and the gen-l10n configuration '
      'that was used.');
}
