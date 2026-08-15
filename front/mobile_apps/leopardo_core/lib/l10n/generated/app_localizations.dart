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

  /// No description provided for @authTwoFactorRequired.
  ///
  /// In fr, this message translates to:
  /// **'Le code 2FA est requis.'**
  String get authTwoFactorRequired;

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

  /// No description provided for @commonCountriesDz.
  ///
  /// In fr, this message translates to:
  /// **'Algérie'**
  String get commonCountriesDz;

  /// No description provided for @commonCountriesCm.
  ///
  /// In fr, this message translates to:
  /// **'Cameroun'**
  String get commonCountriesCm;

  /// No description provided for @commonCountriesCi.
  ///
  /// In fr, this message translates to:
  /// **'Côte d\'Ivoire'**
  String get commonCountriesCi;

  /// No description provided for @commonCountriesSn.
  ///
  /// In fr, this message translates to:
  /// **'Sénégal'**
  String get commonCountriesSn;

  /// No description provided for @commonCountriesMa.
  ///
  /// In fr, this message translates to:
  /// **'Maroc'**
  String get commonCountriesMa;

  /// No description provided for @commonCountriesTn.
  ///
  /// In fr, this message translates to:
  /// **'Tunisie'**
  String get commonCountriesTn;

  /// No description provided for @commonCountriesFr.
  ///
  /// In fr, this message translates to:
  /// **'France'**
  String get commonCountriesFr;

  /// No description provided for @commonCountriesTr.
  ///
  /// In fr, this message translates to:
  /// **'Turquie'**
  String get commonCountriesTr;

  /// No description provided for @commonCountriesCg.
  ///
  /// In fr, this message translates to:
  /// **'Congo'**
  String get commonCountriesCg;

  /// No description provided for @commonCountriesGa.
  ///
  /// In fr, this message translates to:
  /// **'Gabon'**
  String get commonCountriesGa;

  /// No description provided for @commonCountriesBf.
  ///
  /// In fr, this message translates to:
  /// **'Burkina Faso'**
  String get commonCountriesBf;

  /// No description provided for @commonCountriesMl.
  ///
  /// In fr, this message translates to:
  /// **'Mali'**
  String get commonCountriesMl;

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

  /// No description provided for @usersErrorsNameRequired.
  ///
  /// In fr, this message translates to:
  /// **'Le nom complet est requis.'**
  String get usersErrorsNameRequired;

  /// No description provided for @usersErrorsPasswordRequired.
  ///
  /// In fr, this message translates to:
  /// **'Le mot de passe est requis.'**
  String get usersErrorsPasswordRequired;

  /// No description provided for @usersErrorsFixFields.
  ///
  /// In fr, this message translates to:
  /// **'Veuillez corriger les champs en rouge'**
  String get usersErrorsFixFields;

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

  /// No description provided for @dashboardLeoIaAnnouncementTitle.
  ///
  /// In fr, this message translates to:
  /// **'Felicitations equipe'**
  String get dashboardLeoIaAnnouncementTitle;

  /// No description provided for @dashboardLeoIaAnnouncementBody.
  ///
  /// In fr, this message translates to:
  /// **'Felicitations a toute l\'equipe : les retards sont en baisse de 15% cette semaine. Continuez sur cette dynamique !'**
  String get dashboardLeoIaAnnouncementBody;

  /// No description provided for @dashboardLeoIaAnnouncementError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible d\'envoyer le message. Reessayez dans quelques instants.'**
  String get dashboardLeoIaAnnouncementError;

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

  /// No description provided for @holidaysPageTitle.
  ///
  /// In fr, this message translates to:
  /// **'Jours fériés par pays'**
  String get holidaysPageTitle;

  /// No description provided for @holidaysPageSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Calendrier des jours fériés utilisés par le moteur de paie pour calculer les jours ouvrés réels. Les fériés fixes (issue #1811) et les fêtes islamiques mobiles (issue #1812) alimentent automatiquement les bulletins de paie de tous les pays concernés.'**
  String get holidaysPageSubtitle;

  /// No description provided for @holidaysCountry.
  ///
  /// In fr, this message translates to:
  /// **'Pays'**
  String get holidaysCountry;

  /// No description provided for @holidaysYear.
  ///
  /// In fr, this message translates to:
  /// **'Année'**
  String get holidaysYear;

  /// No description provided for @holidaysAdd.
  ///
  /// In fr, this message translates to:
  /// **'Ajouter un jour férié'**
  String get holidaysAdd;

  /// No description provided for @holidaysThDate.
  ///
  /// In fr, this message translates to:
  /// **'Date'**
  String get holidaysThDate;

  /// No description provided for @holidaysThName.
  ///
  /// In fr, this message translates to:
  /// **'Nom'**
  String get holidaysThName;

  /// No description provided for @holidaysThType.
  ///
  /// In fr, this message translates to:
  /// **'Type'**
  String get holidaysThType;

  /// No description provided for @holidaysThScope.
  ///
  /// In fr, this message translates to:
  /// **'Portée'**
  String get holidaysThScope;

  /// No description provided for @holidaysThActions.
  ///
  /// In fr, this message translates to:
  /// **'Actions'**
  String get holidaysThActions;

  /// No description provided for @holidaysScopeNational.
  ///
  /// In fr, this message translates to:
  /// **'National'**
  String get holidaysScopeNational;

  /// No description provided for @holidaysScopeCompany.
  ///
  /// In fr, this message translates to:
  /// **'Entreprise'**
  String get holidaysScopeCompany;

  /// No description provided for @holidaysEdit.
  ///
  /// In fr, this message translates to:
  /// **'Modifier'**
  String get holidaysEdit;

  /// No description provided for @holidaysDelete.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer'**
  String get holidaysDelete;

  /// No description provided for @holidaysEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucun jour férié pour {country} / {year}.'**
  String holidaysEmpty(Object country, Object year);

  /// No description provided for @holidaysModalEdittitle.
  ///
  /// In fr, this message translates to:
  /// **'Modifier le jour férié'**
  String get holidaysModalEdittitle;

  /// No description provided for @holidaysModalNewtitle.
  ///
  /// In fr, this message translates to:
  /// **'Nouveau jour férié'**
  String get holidaysModalNewtitle;

  /// No description provided for @holidaysNameLabel.
  ///
  /// In fr, this message translates to:
  /// **'Nom'**
  String get holidaysNameLabel;

  /// No description provided for @holidaysDateLabel.
  ///
  /// In fr, this message translates to:
  /// **'Date'**
  String get holidaysDateLabel;

  /// No description provided for @holidaysTypeLabel.
  ///
  /// In fr, this message translates to:
  /// **'Type'**
  String get holidaysTypeLabel;

  /// No description provided for @holidaysTypeFixed.
  ///
  /// In fr, this message translates to:
  /// **'Fixe'**
  String get holidaysTypeFixed;

  /// No description provided for @holidaysTypeIslamic.
  ///
  /// In fr, this message translates to:
  /// **'Islamique'**
  String get holidaysTypeIslamic;

  /// No description provided for @holidaysTypeChristian.
  ///
  /// In fr, this message translates to:
  /// **'Chrétien'**
  String get holidaysTypeChristian;

  /// No description provided for @holidaysTypeCustom.
  ///
  /// In fr, this message translates to:
  /// **'Personnalisé'**
  String get holidaysTypeCustom;

  /// No description provided for @holidaysRecurring.
  ///
  /// In fr, this message translates to:
  /// **'Récurent chaque année'**
  String get holidaysRecurring;

  /// No description provided for @holidaysCancel.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get holidaysCancel;

  /// No description provided for @holidaysSaving.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrement…'**
  String get holidaysSaving;

  /// No description provided for @holidaysSave.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer'**
  String get holidaysSave;

  /// No description provided for @holidaysErrorsLoad.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger les jours fériés.'**
  String get holidaysErrorsLoad;

  /// No description provided for @holidaysErrorsSave.
  ///
  /// In fr, this message translates to:
  /// **'Impossible d\'enregistrer le jour férié.'**
  String get holidaysErrorsSave;

  /// No description provided for @holidaysErrorsDelete.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de supprimer.'**
  String get holidaysErrorsDelete;

  /// No description provided for @holidaysSuccessSaved.
  ///
  /// In fr, this message translates to:
  /// **'Jour férié enregistré.'**
  String get holidaysSuccessSaved;

  /// No description provided for @holidaysSuccessDeleted.
  ///
  /// In fr, this message translates to:
  /// **'Jour férié supprimé.'**
  String get holidaysSuccessDeleted;

  /// No description provided for @holidaysConfirmDelete.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer « :name » ?'**
  String get holidaysConfirmDelete;

  /// No description provided for @holidaysNavTitle.
  ///
  /// In fr, this message translates to:
  /// **'Jours fériés'**
  String get holidaysNavTitle;

  /// No description provided for @holidaysEditTitle.
  ///
  /// In fr, this message translates to:
  /// **'Modifier le jour férié'**
  String get holidaysEditTitle;

  /// No description provided for @holidaysAddTitle.
  ///
  /// In fr, this message translates to:
  /// **'Nouveau jour férié'**
  String get holidaysAddTitle;

  /// No description provided for @holidaysLoadError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger les jours fériés.'**
  String get holidaysLoadError;

  /// No description provided for @holidaysSaved.
  ///
  /// In fr, this message translates to:
  /// **'Jour férié enregistré.'**
  String get holidaysSaved;

  /// No description provided for @holidaysSaveError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible d\'enregistrer le jour férié.'**
  String get holidaysSaveError;

  /// No description provided for @holidaysDeleted.
  ///
  /// In fr, this message translates to:
  /// **'Jour férié supprimé.'**
  String get holidaysDeleted;

  /// No description provided for @holidaysDeleteError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de supprimer.'**
  String get holidaysDeleteError;

  /// No description provided for @holidaysCountriesDz.
  ///
  /// In fr, this message translates to:
  /// **'Algérie'**
  String get holidaysCountriesDz;

  /// No description provided for @holidaysCountriesCm.
  ///
  /// In fr, this message translates to:
  /// **'Cameroun'**
  String get holidaysCountriesCm;

  /// No description provided for @holidaysCountriesCi.
  ///
  /// In fr, this message translates to:
  /// **'Côte d\'Ivoire'**
  String get holidaysCountriesCi;

  /// No description provided for @holidaysCountriesSn.
  ///
  /// In fr, this message translates to:
  /// **'Sénégal'**
  String get holidaysCountriesSn;

  /// No description provided for @holidaysCountriesMa.
  ///
  /// In fr, this message translates to:
  /// **'Maroc'**
  String get holidaysCountriesMa;

  /// No description provided for @holidaysCountriesTn.
  ///
  /// In fr, this message translates to:
  /// **'Tunisie'**
  String get holidaysCountriesTn;

  /// No description provided for @holidaysIslamicTitle.
  ///
  /// In fr, this message translates to:
  /// **'Fêtes islamiques'**
  String get holidaysIslamicTitle;

  /// No description provided for @holidaysIslamicSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Dates mobiles du calendrier hégirien (Aïd, Maouloud, Tamkharit…) saisies par année. Elles s\'appliquent automatiquement aux pays CEMAC/CEDEAO + DZ/MA/TN.'**
  String get holidaysIslamicSubtitle;

  /// No description provided for @holidaysIslamicConfirm.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer {year}'**
  String holidaysIslamicConfirm(Object year);

  /// No description provided for @holidaysIslamicBannerUnconfirmed.
  ///
  /// In fr, this message translates to:
  /// **'{count} fête(s) islamique(s) non confirmée(s) pour {year} — vérifiez les dates avant la clôture de paie.'**
  String holidaysIslamicBannerUnconfirmed(Object count, Object year);

  /// No description provided for @holidaysIslamicThName.
  ///
  /// In fr, this message translates to:
  /// **'Fête'**
  String get holidaysIslamicThName;

  /// No description provided for @holidaysIslamicThDate.
  ///
  /// In fr, this message translates to:
  /// **'Date grégorienne'**
  String get holidaysIslamicThDate;

  /// No description provided for @holidaysIslamicThDuration.
  ///
  /// In fr, this message translates to:
  /// **'Durée'**
  String get holidaysIslamicThDuration;

  /// No description provided for @holidaysIslamicThCountries.
  ///
  /// In fr, this message translates to:
  /// **'Pays'**
  String get holidaysIslamicThCountries;

  /// No description provided for @holidaysIslamicThStatus.
  ///
  /// In fr, this message translates to:
  /// **'Statut'**
  String get holidaysIslamicThStatus;

  /// No description provided for @holidaysIslamicThActions.
  ///
  /// In fr, this message translates to:
  /// **'Actions'**
  String get holidaysIslamicThActions;

  /// No description provided for @holidaysIslamicDurationDays.
  ///
  /// In fr, this message translates to:
  /// **'{count} jour(s)'**
  String holidaysIslamicDurationDays(Object count);

  /// No description provided for @holidaysIslamicStatusConfirmed.
  ///
  /// In fr, this message translates to:
  /// **'Confirmé'**
  String get holidaysIslamicStatusConfirmed;

  /// No description provided for @holidaysIslamicStatusApproximate.
  ///
  /// In fr, this message translates to:
  /// **'Approximatif'**
  String get holidaysIslamicStatusApproximate;

  /// No description provided for @holidaysIslamicEdit.
  ///
  /// In fr, this message translates to:
  /// **'Modifier'**
  String get holidaysIslamicEdit;

  /// No description provided for @holidaysIslamicEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune fête islamique enregistrée pour {year}.'**
  String holidaysIslamicEmpty(Object year);

  /// No description provided for @holidaysIslamicModalTitle.
  ///
  /// In fr, this message translates to:
  /// **'Modifier {name}'**
  String holidaysIslamicModalTitle(Object name);

  /// No description provided for @holidaysIslamicLabelDate.
  ///
  /// In fr, this message translates to:
  /// **'Date grégorienne'**
  String get holidaysIslamicLabelDate;

  /// No description provided for @holidaysIslamicLabelDuration.
  ///
  /// In fr, this message translates to:
  /// **'Durée (jours)'**
  String get holidaysIslamicLabelDuration;

  /// No description provided for @holidaysIslamicLabelConfirmed.
  ///
  /// In fr, this message translates to:
  /// **'Date confirmée (officielle)'**
  String get holidaysIslamicLabelConfirmed;

  /// No description provided for @holidaysIslamicLoadError.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors du chargement du calendrier islamique.'**
  String get holidaysIslamicLoadError;

  /// No description provided for @holidaysIslamicConfirmDialog.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer toutes les dates islamiques de {year} ?'**
  String holidaysIslamicConfirmDialog(Object year);

  /// No description provided for @holidaysIslamicSaved.
  ///
  /// In fr, this message translates to:
  /// **'Fête islamique enregistrée.'**
  String get holidaysIslamicSaved;

  /// No description provided for @holidaysIslamicSaveError.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors de l\'enregistrement.'**
  String get holidaysIslamicSaveError;

  /// No description provided for @holidaysIslamicConfirmError.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors de la confirmation des dates.'**
  String get holidaysIslamicConfirmError;

  /// No description provided for @holidaysIslamicConfirmYearSuccess.
  ///
  /// In fr, this message translates to:
  /// **'{count} date(s) confirmée(s).'**
  String holidaysIslamicConfirmYearSuccess(Object count);

  /// No description provided for @holidaysIslamicDeleteConfirmDialog.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer « {name} » ?'**
  String holidaysIslamicDeleteConfirmDialog(Object name);

  /// No description provided for @taxRatesTitle.
  ///
  /// In fr, this message translates to:
  /// **'Taux légaux — validation'**
  String get taxRatesTitle;

  /// No description provided for @taxRatesSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Barèmes fiscaux et cotisations sociales utilisés par le moteur de paie. Toute modification passe par un workflow de validation à double signature (comptable → platform admin) avec audit trail immuable.'**
  String get taxRatesSubtitle;

  /// No description provided for @taxRatesPendingTitle.
  ///
  /// In fr, this message translates to:
  /// **'En attente de validation'**
  String get taxRatesPendingTitle;

  /// No description provided for @taxRatesPendingEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune modification en attente de validation.'**
  String get taxRatesPendingEmpty;

  /// No description provided for @taxRatesRatesTitle.
  ///
  /// In fr, this message translates to:
  /// **'Taux en vigueur'**
  String get taxRatesRatesTitle;

  /// No description provided for @taxRatesRatesSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Seules les lignes actives sont utilisées dans les bulletins.'**
  String get taxRatesRatesSubtitle;

  /// No description provided for @taxRatesRatesEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucun taux enregistré.'**
  String get taxRatesRatesEmpty;

  /// No description provided for @taxRatesPropose.
  ///
  /// In fr, this message translates to:
  /// **'Proposer une modification'**
  String get taxRatesPropose;

  /// No description provided for @taxRatesThType.
  ///
  /// In fr, this message translates to:
  /// **'Type'**
  String get taxRatesThType;

  /// No description provided for @taxRatesThName.
  ///
  /// In fr, this message translates to:
  /// **'Intitulé'**
  String get taxRatesThName;

  /// No description provided for @taxRatesThCountry.
  ///
  /// In fr, this message translates to:
  /// **'Pays'**
  String get taxRatesThCountry;

  /// No description provided for @taxRatesThRate.
  ///
  /// In fr, this message translates to:
  /// **'Taux'**
  String get taxRatesThRate;

  /// No description provided for @taxRatesThEffective.
  ///
  /// In fr, this message translates to:
  /// **'Effet au'**
  String get taxRatesThEffective;

  /// No description provided for @taxRatesThStatus.
  ///
  /// In fr, this message translates to:
  /// **'Statut'**
  String get taxRatesThStatus;

  /// No description provided for @taxRatesThActions.
  ///
  /// In fr, this message translates to:
  /// **'Actions'**
  String get taxRatesThActions;

  /// No description provided for @taxRatesThAction.
  ///
  /// In fr, this message translates to:
  /// **'Action'**
  String get taxRatesThAction;

  /// No description provided for @taxRatesThActor.
  ///
  /// In fr, this message translates to:
  /// **'Acteur'**
  String get taxRatesThActor;

  /// No description provided for @taxRatesThReason.
  ///
  /// In fr, this message translates to:
  /// **'Motif'**
  String get taxRatesThReason;

  /// No description provided for @taxRatesThDate.
  ///
  /// In fr, this message translates to:
  /// **'Date'**
  String get taxRatesThDate;

  /// No description provided for @taxRatesTypeSlab.
  ///
  /// In fr, this message translates to:
  /// **'Barème'**
  String get taxRatesTypeSlab;

  /// No description provided for @taxRatesTypeContribution.
  ///
  /// In fr, this message translates to:
  /// **'Cotisation'**
  String get taxRatesTypeContribution;

  /// No description provided for @taxRatesStatusActive.
  ///
  /// In fr, this message translates to:
  /// **'🟢 Active'**
  String get taxRatesStatusActive;

  /// No description provided for @taxRatesStatusPending.
  ///
  /// In fr, this message translates to:
  /// **'🟡 En attente'**
  String get taxRatesStatusPending;

  /// No description provided for @taxRatesStatusDraft.
  ///
  /// In fr, this message translates to:
  /// **'⚪ Brouillon'**
  String get taxRatesStatusDraft;

  /// No description provided for @taxRatesStatusSuperseded.
  ///
  /// In fr, this message translates to:
  /// **'🔴 Remplacée'**
  String get taxRatesStatusSuperseded;

  /// No description provided for @taxRatesSubmit.
  ///
  /// In fr, this message translates to:
  /// **'Soumettre'**
  String get taxRatesSubmit;

  /// No description provided for @taxRatesHistory.
  ///
  /// In fr, this message translates to:
  /// **'Historique'**
  String get taxRatesHistory;

  /// No description provided for @taxRatesApprove.
  ///
  /// In fr, this message translates to:
  /// **'Approuver'**
  String get taxRatesApprove;

  /// No description provided for @taxRatesReject.
  ///
  /// In fr, this message translates to:
  /// **'Rejeter'**
  String get taxRatesReject;

  /// No description provided for @taxRatesModalTitle.
  ///
  /// In fr, this message translates to:
  /// **'Proposer une modification de taux'**
  String get taxRatesModalTitle;

  /// No description provided for @taxRatesLegalRef.
  ///
  /// In fr, this message translates to:
  /// **'Référence légale'**
  String get taxRatesLegalRef;

  /// No description provided for @taxRatesLegalRefRequired.
  ///
  /// In fr, this message translates to:
  /// **'La référence légale est obligatoire (elle est tracée dans l\'historique).'**
  String get taxRatesLegalRefRequired;

  /// No description provided for @taxRatesCancel.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get taxRatesCancel;

  /// No description provided for @taxRatesSaving.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrement…'**
  String get taxRatesSaving;

  /// No description provided for @taxRatesSave.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer'**
  String get taxRatesSave;

  /// No description provided for @taxRatesRejectModalTitle.
  ///
  /// In fr, this message translates to:
  /// **'Rejeter la modification'**
  String get taxRatesRejectModalTitle;

  /// No description provided for @taxRatesRejectReason.
  ///
  /// In fr, this message translates to:
  /// **'Motif du rejet (obligatoire)'**
  String get taxRatesRejectReason;

  /// No description provided for @taxRatesHistoryTitle.
  ///
  /// In fr, this message translates to:
  /// **'Historique des modifications'**
  String get taxRatesHistoryTitle;

  /// No description provided for @taxRatesHistoryEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune entrée d\'historique.'**
  String get taxRatesHistoryEmpty;

  /// No description provided for @taxRatesHistoryCreated.
  ///
  /// In fr, this message translates to:
  /// **'Créé'**
  String get taxRatesHistoryCreated;

  /// No description provided for @taxRatesHistorySubmitted.
  ///
  /// In fr, this message translates to:
  /// **'Soumis'**
  String get taxRatesHistorySubmitted;

  /// No description provided for @taxRatesHistoryApproved.
  ///
  /// In fr, this message translates to:
  /// **'Approuvé'**
  String get taxRatesHistoryApproved;

  /// No description provided for @taxRatesHistoryRejected.
  ///
  /// In fr, this message translates to:
  /// **'Rejeté'**
  String get taxRatesHistoryRejected;

  /// No description provided for @taxRatesHistorySuperseded.
  ///
  /// In fr, this message translates to:
  /// **'Remplacé'**
  String get taxRatesHistorySuperseded;

  /// No description provided for @taxRatesClose.
  ///
  /// In fr, this message translates to:
  /// **'Fermer'**
  String get taxRatesClose;

  /// No description provided for @taxRatesLoadError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger les taux.'**
  String get taxRatesLoadError;

  /// No description provided for @taxRatesSaved.
  ///
  /// In fr, this message translates to:
  /// **'Proposition enregistrée.'**
  String get taxRatesSaved;

  /// No description provided for @taxRatesSaveError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible d\'enregistrer la proposition.'**
  String get taxRatesSaveError;

  /// No description provided for @taxRatesSubmitted.
  ///
  /// In fr, this message translates to:
  /// **'Modification soumise pour validation.'**
  String get taxRatesSubmitted;

  /// No description provided for @taxRatesSubmitError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de soumettre la modification.'**
  String get taxRatesSubmitError;

  /// No description provided for @taxRatesApproved.
  ///
  /// In fr, this message translates to:
  /// **'Modification approuvée et active.'**
  String get taxRatesApproved;

  /// No description provided for @taxRatesApproveError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible d\'approuver la modification.'**
  String get taxRatesApproveError;

  /// No description provided for @taxRatesRejected.
  ///
  /// In fr, this message translates to:
  /// **'Modification rejetée (retour en brouillon).'**
  String get taxRatesRejected;

  /// No description provided for @taxRatesRejectError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de rejeter la modification.'**
  String get taxRatesRejectError;

  /// No description provided for @taxRatesHistoryError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger l\'historique.'**
  String get taxRatesHistoryError;

  /// No description provided for @taxRatesStatusOnly.
  ///
  /// In fr, this message translates to:
  /// **'Lecture seule (action tenant)'**
  String get taxRatesStatusOnly;

  /// No description provided for @taxSlabsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Barèmes fiscaux par pays'**
  String get taxSlabsTitle;

  /// No description provided for @taxSlabsSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Barèmes IRG/IRPP/ITSAS utilisés par le moteur de paie. Gestion nationale (platform admin), simulateur d\'impact en temps réel, sans persistance.'**
  String get taxSlabsSubtitle;

  /// No description provided for @taxSlabsThCountry.
  ///
  /// In fr, this message translates to:
  /// **'Pays'**
  String get taxSlabsThCountry;

  /// No description provided for @taxSlabsScope.
  ///
  /// In fr, this message translates to:
  /// **'Portée'**
  String get taxSlabsScope;

  /// No description provided for @taxSlabsScopeNational.
  ///
  /// In fr, this message translates to:
  /// **'National'**
  String get taxSlabsScopeNational;

  /// No description provided for @taxSlabsScopeCompany.
  ///
  /// In fr, this message translates to:
  /// **'Spécifique entreprise'**
  String get taxSlabsScopeCompany;

  /// No description provided for @taxSlabsNationalNote.
  ///
  /// In fr, this message translates to:
  /// **'portée nationale — les overrides entreprise restent côté tenant'**
  String get taxSlabsNationalNote;

  /// No description provided for @taxSlabsThMin.
  ///
  /// In fr, this message translates to:
  /// **'Tranche min'**
  String get taxSlabsThMin;

  /// No description provided for @taxSlabsThMax.
  ///
  /// In fr, this message translates to:
  /// **'Tranche max'**
  String get taxSlabsThMax;

  /// No description provided for @taxSlabsThRate.
  ///
  /// In fr, this message translates to:
  /// **'Taux'**
  String get taxSlabsThRate;

  /// No description provided for @taxSlabsThDeduction.
  ///
  /// In fr, this message translates to:
  /// **'Déduction fixe'**
  String get taxSlabsThDeduction;

  /// No description provided for @taxSlabsThEffective.
  ///
  /// In fr, this message translates to:
  /// **'Effet au'**
  String get taxSlabsThEffective;

  /// No description provided for @taxSlabsThActions.
  ///
  /// In fr, this message translates to:
  /// **'Actions'**
  String get taxSlabsThActions;

  /// No description provided for @taxSlabsEdit.
  ///
  /// In fr, this message translates to:
  /// **'Modifier'**
  String get taxSlabsEdit;

  /// No description provided for @taxSlabsDelete.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer'**
  String get taxSlabsDelete;

  /// No description provided for @taxSlabsAdd.
  ///
  /// In fr, this message translates to:
  /// **'Ajouter une tranche'**
  String get taxSlabsAdd;

  /// No description provided for @taxSlabsReset.
  ///
  /// In fr, this message translates to:
  /// **'Réinitialiser aux valeurs légales'**
  String get taxSlabsReset;

  /// No description provided for @taxSlabsEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune tranche enregistrée pour ce pays.'**
  String get taxSlabsEmpty;

  /// No description provided for @taxSlabsEditTitle.
  ///
  /// In fr, this message translates to:
  /// **'Modifier la tranche'**
  String get taxSlabsEditTitle;

  /// No description provided for @taxSlabsAddTitle.
  ///
  /// In fr, this message translates to:
  /// **'Ajouter une tranche'**
  String get taxSlabsAddTitle;

  /// No description provided for @taxSlabsLegalRef.
  ///
  /// In fr, this message translates to:
  /// **'Référence légale'**
  String get taxSlabsLegalRef;

  /// No description provided for @taxSlabsCancel.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get taxSlabsCancel;

  /// No description provided for @taxSlabsSaving.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrement…'**
  String get taxSlabsSaving;

  /// No description provided for @taxSlabsSave.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer'**
  String get taxSlabsSave;

  /// No description provided for @taxSlabsSimulatorTitle.
  ///
  /// In fr, this message translates to:
  /// **'Simulateur d\'impact'**
  String get taxSlabsSimulatorTitle;

  /// No description provided for @taxSlabsSimulatorSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Saisissez un salaire brut : le calcul (cotisations, assiette, impôt par tranche, net, coût employeur) est exécuté par le moteur de paie réel, sans rien persister.'**
  String get taxSlabsSimulatorSubtitle;

  /// No description provided for @taxSlabsSimGross.
  ///
  /// In fr, this message translates to:
  /// **'Salaire brut'**
  String get taxSlabsSimGross;

  /// No description provided for @taxSlabsSimCompare.
  ///
  /// In fr, this message translates to:
  /// **'Salaire à comparer'**
  String get taxSlabsSimCompare;

  /// No description provided for @taxSlabsSimRun.
  ///
  /// In fr, this message translates to:
  /// **'Simuler'**
  String get taxSlabsSimRun;

  /// No description provided for @taxSlabsSimRunning.
  ///
  /// In fr, this message translates to:
  /// **'Calcul…'**
  String get taxSlabsSimRunning;

  /// No description provided for @taxSlabsSimSocial.
  ///
  /// In fr, this message translates to:
  /// **'Cotisations (salariales)'**
  String get taxSlabsSimSocial;

  /// No description provided for @taxSlabsSimTax.
  ///
  /// In fr, this message translates to:
  /// **'Impôt'**
  String get taxSlabsSimTax;

  /// No description provided for @taxSlabsSimNet.
  ///
  /// In fr, this message translates to:
  /// **'Net'**
  String get taxSlabsSimNet;

  /// No description provided for @taxSlabsSimBase.
  ///
  /// In fr, this message translates to:
  /// **'Assiette'**
  String get taxSlabsSimBase;

  /// No description provided for @taxSlabsSimSlabTax.
  ///
  /// In fr, this message translates to:
  /// **'Impôt tranche'**
  String get taxSlabsSimSlabTax;

  /// No description provided for @taxSlabsLoadError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger le barème.'**
  String get taxSlabsLoadError;

  /// No description provided for @taxSlabsSaveError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible d\'enregistrer la tranche.'**
  String get taxSlabsSaveError;

  /// No description provided for @taxSlabsSaved.
  ///
  /// In fr, this message translates to:
  /// **'Tranche mise à jour.'**
  String get taxSlabsSaved;

  /// No description provided for @taxSlabsCreated.
  ///
  /// In fr, this message translates to:
  /// **'Tranche créée.'**
  String get taxSlabsCreated;

  /// No description provided for @taxSlabsDeleted.
  ///
  /// In fr, this message translates to:
  /// **'Tranche supprimée.'**
  String get taxSlabsDeleted;

  /// No description provided for @taxSlabsDeleteError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de supprimer.'**
  String get taxSlabsDeleteError;

  /// No description provided for @taxSlabsDeleteConfirm.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer la tranche « {name} » ?'**
  String taxSlabsDeleteConfirm(Object name);

  /// No description provided for @taxSlabsResetConfirm.
  ///
  /// In fr, this message translates to:
  /// **'Réinitialiser aux valeurs légales par défaut ? Les tranches actuelles seront remplacées.'**
  String get taxSlabsResetConfirm;

  /// No description provided for @taxSlabsResetDone.
  ///
  /// In fr, this message translates to:
  /// **'Barème réinitialisé.'**
  String get taxSlabsResetDone;

  /// No description provided for @taxSlabsResetError.
  ///
  /// In fr, this message translates to:
  /// **'Réinitialisation impossible.'**
  String get taxSlabsResetError;

  /// No description provided for @taxSlabsDefaultName.
  ///
  /// In fr, this message translates to:
  /// **'{country} tranche légale'**
  String taxSlabsDefaultName(Object country);

  /// No description provided for @taxSlabsSimError.
  ///
  /// In fr, this message translates to:
  /// **'Simulation impossible.'**
  String get taxSlabsSimError;

  /// No description provided for @socialContribTitle.
  ///
  /// In fr, this message translates to:
  /// **'Cotisations sociales par pays'**
  String get socialContribTitle;

  /// No description provided for @socialContribSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'CNPS, CNSS, IPRES, CNAS… — taux, plafonds et types (salariale/patronale) par pays. Gestion nationale + simulateur avec/sans plafond et comparateur 2 pays.'**
  String get socialContribSubtitle;

  /// No description provided for @socialContribThCountry.
  ///
  /// In fr, this message translates to:
  /// **'Pays'**
  String get socialContribThCountry;

  /// No description provided for @socialContribThOrg.
  ///
  /// In fr, this message translates to:
  /// **'Organisme'**
  String get socialContribThOrg;

  /// No description provided for @socialContribThCode.
  ///
  /// In fr, this message translates to:
  /// **'Code'**
  String get socialContribThCode;

  /// No description provided for @socialContribThType.
  ///
  /// In fr, this message translates to:
  /// **'Type'**
  String get socialContribThType;

  /// No description provided for @socialContribThRate.
  ///
  /// In fr, this message translates to:
  /// **'Taux'**
  String get socialContribThRate;

  /// No description provided for @socialContribThCap.
  ///
  /// In fr, this message translates to:
  /// **'Plafond'**
  String get socialContribThCap;

  /// No description provided for @socialContribThEffective.
  ///
  /// In fr, this message translates to:
  /// **'Effet au'**
  String get socialContribThEffective;

  /// No description provided for @socialContribThActions.
  ///
  /// In fr, this message translates to:
  /// **'Actions'**
  String get socialContribThActions;

  /// No description provided for @socialContribTypeAll.
  ///
  /// In fr, this message translates to:
  /// **'Tous'**
  String get socialContribTypeAll;

  /// No description provided for @socialContribTypeEmployee.
  ///
  /// In fr, this message translates to:
  /// **'Salariale'**
  String get socialContribTypeEmployee;

  /// No description provided for @socialContribTypeEmployer.
  ///
  /// In fr, this message translates to:
  /// **'Patronale'**
  String get socialContribTypeEmployer;

  /// No description provided for @socialContribAdd.
  ///
  /// In fr, this message translates to:
  /// **'Ajouter une cotisation'**
  String get socialContribAdd;

  /// No description provided for @socialContribEdit.
  ///
  /// In fr, this message translates to:
  /// **'Modifier'**
  String get socialContribEdit;

  /// No description provided for @socialContribDelete.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer'**
  String get socialContribDelete;

  /// No description provided for @socialContribEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune cotisation enregistrée pour ce pays.'**
  String get socialContribEmpty;

  /// No description provided for @socialContribAddTitle.
  ///
  /// In fr, this message translates to:
  /// **'Ajouter une cotisation'**
  String get socialContribAddTitle;

  /// No description provided for @socialContribEditTitle.
  ///
  /// In fr, this message translates to:
  /// **'Modifier la cotisation'**
  String get socialContribEditTitle;

  /// No description provided for @socialContribCancel.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get socialContribCancel;

  /// No description provided for @socialContribSaving.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrement…'**
  String get socialContribSaving;

  /// No description provided for @socialContribSave.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer'**
  String get socialContribSave;

  /// No description provided for @socialContribSimTitle.
  ///
  /// In fr, this message translates to:
  /// **'Simulateur & comparateur'**
  String get socialContribSimTitle;

  /// No description provided for @socialContribSimSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Saisissez un brut : décomposition salariale/patronale, impôt et coût total employeur, pour deux pays côte à côte (avec ou sans plafond légal).'**
  String get socialContribSimSubtitle;

  /// No description provided for @socialContribSimGross.
  ///
  /// In fr, this message translates to:
  /// **'Salaire brut'**
  String get socialContribSimGross;

  /// No description provided for @socialContribCompareCountry.
  ///
  /// In fr, this message translates to:
  /// **'Pays comparé'**
  String get socialContribCompareCountry;

  /// No description provided for @socialContribIgnoreCaps.
  ///
  /// In fr, this message translates to:
  /// **'Sans plafond légal'**
  String get socialContribIgnoreCaps;

  /// No description provided for @socialContribSimEmployee.
  ///
  /// In fr, this message translates to:
  /// **'Cotisations salariales'**
  String get socialContribSimEmployee;

  /// No description provided for @socialContribSimEmployer.
  ///
  /// In fr, this message translates to:
  /// **'Cotisations patronales'**
  String get socialContribSimEmployer;

  /// No description provided for @socialContribSimTax.
  ///
  /// In fr, this message translates to:
  /// **'Impôt'**
  String get socialContribSimTax;

  /// No description provided for @socialContribTotalCost.
  ///
  /// In fr, this message translates to:
  /// **'Coût total employeur'**
  String get socialContribTotalCost;

  /// No description provided for @socialContribLoadError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger les cotisations.'**
  String get socialContribLoadError;

  /// No description provided for @socialContribSaveError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible d\'enregistrer la cotisation.'**
  String get socialContribSaveError;

  /// No description provided for @socialContribSaved.
  ///
  /// In fr, this message translates to:
  /// **'Cotisation mise à jour.'**
  String get socialContribSaved;

  /// No description provided for @socialContribCreated.
  ///
  /// In fr, this message translates to:
  /// **'Cotisation créée.'**
  String get socialContribCreated;

  /// No description provided for @socialContribDeleted.
  ///
  /// In fr, this message translates to:
  /// **'Cotisation supprimée.'**
  String get socialContribDeleted;

  /// No description provided for @socialContribDeleteError.
  ///
  /// In fr, this message translates to:
  /// **'Suppression impossible.'**
  String get socialContribDeleteError;

  /// No description provided for @socialContribDeleteConfirm.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer « {name} » ?'**
  String socialContribDeleteConfirm(Object name);

  /// No description provided for @payrollConfidenceLabel.
  ///
  /// In fr, this message translates to:
  /// **'Confiance des règles de paie'**
  String get payrollConfidenceLabel;

  /// No description provided for @payrollConfidenceLevelProduction.
  ///
  /// In fr, this message translates to:
  /// **'Production'**
  String get payrollConfidenceLevelProduction;

  /// No description provided for @payrollConfidenceLevelPilot.
  ///
  /// In fr, this message translates to:
  /// **'Pilote'**
  String get payrollConfidenceLevelPilot;

  /// No description provided for @payrollConfidenceLevelPlaceholder.
  ///
  /// In fr, this message translates to:
  /// **'Maquette'**
  String get payrollConfidenceLevelPlaceholder;

  /// No description provided for @payrollConfidenceLevelUnknown.
  ///
  /// In fr, this message translates to:
  /// **'Inconnu'**
  String get payrollConfidenceLevelUnknown;

  /// No description provided for @payrollConfidenceProductionMessage.
  ///
  /// In fr, this message translates to:
  /// **'Règles validées et utilisées en production pour {country}. Confirmez toujours les taux en vigueur auprès d\'un conseil local avant de vous appuyer sur ces montants pour des déclarations obligatoires.'**
  String payrollConfidenceProductionMessage(Object country);

  /// No description provided for @payrollConfidencePilotMessage.
  ///
  /// In fr, this message translates to:
  /// **'Règles pilotes pour {country} : montants issus de références publiques générales (code du travail) mais non encore validés juridiquement sur place. Confirmez avec un conseil juridique ou fiscal local avant de vous appuyer sur ces chiffres (tranches d\'impôt, cotisations sociales, seuils d\'heures supplémentaires) pour vos obligations légales.'**
  String payrollConfidencePilotMessage(Object country);

  /// No description provided for @payrollConfidencePlaceholderMessage.
  ///
  /// In fr, this message translates to:
  /// **'Maquette sans valeurs pour {country} : les montants d\'impôt et de cotisations sociales ne sont pas encore documentés et ne doivent pas être utilisés pour de vrais cycles de paie tant qu\'ils n\'ont pas été remplacés.'**
  String payrollConfidencePlaceholderMessage(Object country);

  /// No description provided for @payrollConfidenceUnknownMessage.
  ///
  /// In fr, this message translates to:
  /// **'Aucune règle de paie n\'est disponible pour {country} : le calcul de paie n\'est pas disponible pour ce pays.'**
  String payrollConfidenceUnknownMessage(Object country);

  /// No description provided for @signupBadge.
  ///
  /// In fr, this message translates to:
  /// **'Essai gratuit 30 jours'**
  String get signupBadge;

  /// No description provided for @signupTitle.
  ///
  /// In fr, this message translates to:
  /// **'Tester Leopardo avec votre entreprise'**
  String get signupTitle;

  /// No description provided for @signupSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Créez votre espace d\'essai en 2 minutes. Aucune carte bancaire requise.'**
  String get signupSubtitle;

  /// No description provided for @signupLabelemail.
  ///
  /// In fr, this message translates to:
  /// **'Email professionnel'**
  String get signupLabelemail;

  /// No description provided for @signupPlaceholderemail.
  ///
  /// In fr, this message translates to:
  /// **'vous@entreprise.com'**
  String get signupPlaceholderemail;

  /// No description provided for @signupLabelcompany.
  ///
  /// In fr, this message translates to:
  /// **'Entreprise'**
  String get signupLabelcompany;

  /// No description provided for @signupPlaceholdercompany.
  ///
  /// In fr, this message translates to:
  /// **'Nom de votre entreprise'**
  String get signupPlaceholdercompany;

  /// No description provided for @signupLabelrole.
  ///
  /// In fr, this message translates to:
  /// **'Votre rôle'**
  String get signupLabelrole;

  /// No description provided for @signupRoleplaceholder.
  ///
  /// In fr, this message translates to:
  /// **'Choisir'**
  String get signupRoleplaceholder;

  /// No description provided for @signupRolefounder.
  ///
  /// In fr, this message translates to:
  /// **'Fondateur / dirigeant'**
  String get signupRolefounder;

  /// No description provided for @signupRolemanager.
  ///
  /// In fr, this message translates to:
  /// **'Manager'**
  String get signupRolemanager;

  /// No description provided for @signupRolehr.
  ///
  /// In fr, this message translates to:
  /// **'RH'**
  String get signupRolehr;

  /// No description provided for @signupRoleoperations.
  ///
  /// In fr, this message translates to:
  /// **'Opérations terrain'**
  String get signupRoleoperations;

  /// No description provided for @signupRoleother.
  ///
  /// In fr, this message translates to:
  /// **'Autre'**
  String get signupRoleother;

  /// No description provided for @signupLabelteamsize.
  ///
  /// In fr, this message translates to:
  /// **'Taille équipe'**
  String get signupLabelteamsize;

  /// No description provided for @signupTeamplaceholder.
  ///
  /// In fr, this message translates to:
  /// **'Choisir'**
  String get signupTeamplaceholder;

  /// No description provided for @signupLabelphone.
  ///
  /// In fr, this message translates to:
  /// **'Téléphone (optionnel)'**
  String get signupLabelphone;

  /// No description provided for @signupPlaceholderphone.
  ///
  /// In fr, this message translates to:
  /// **'+213 555 000 000'**
  String get signupPlaceholderphone;

  /// No description provided for @signupOperationsnote.
  ///
  /// In fr, this message translates to:
  /// **'Nous préparerons un parcours axé terrain : pointage, tâches, kiosk et suivi d\'équipe.'**
  String get signupOperationsnote;

  /// No description provided for @signupAgreeprefix.
  ///
  /// In fr, this message translates to:
  /// **'J\'accepte les'**
  String get signupAgreeprefix;

  /// No description provided for @signupTermslink.
  ///
  /// In fr, this message translates to:
  /// **'conditions d\'utilisation'**
  String get signupTermslink;

  /// No description provided for @signupPrivacylink.
  ///
  /// In fr, this message translates to:
  /// **'politique de confidentialité'**
  String get signupPrivacylink;

  /// No description provided for @signupAgreesuffix.
  ///
  /// In fr, this message translates to:
  /// **'et la'**
  String get signupAgreesuffix;

  /// No description provided for @signupSubmitlabel.
  ///
  /// In fr, this message translates to:
  /// **'Recevoir mon code de vérification'**
  String get signupSubmitlabel;

  /// No description provided for @signupSubmittinglabel.
  ///
  /// In fr, this message translates to:
  /// **'Envoi du code...'**
  String get signupSubmittinglabel;

  /// No description provided for @signupCodehint.
  ///
  /// In fr, this message translates to:
  /// **'Un code à 6 chiffres sera envoyé à votre email pour confirmer votre identité.'**
  String get signupCodehint;

  /// No description provided for @signupHaveaccount.
  ///
  /// In fr, this message translates to:
  /// **'Vous avez déjà un compte ?'**
  String get signupHaveaccount;

  /// No description provided for @signupLogincta.
  ///
  /// In fr, this message translates to:
  /// **'Se connecter'**
  String get signupLogincta;

  /// No description provided for @signupBack.
  ///
  /// In fr, this message translates to:
  /// **'Retour'**
  String get signupBack;

  /// No description provided for @signupOtptitle.
  ///
  /// In fr, this message translates to:
  /// **'Vérifiez votre email'**
  String get signupOtptitle;

  /// No description provided for @signupOtpsentto.
  ///
  /// In fr, this message translates to:
  /// **'Nous avons envoyé un code de vérification à 6 chiffres à :'**
  String get signupOtpsentto;

  /// No description provided for @signupOtpinvalidlength.
  ///
  /// In fr, this message translates to:
  /// **'Veuillez entrer les 6 chiffres du code.'**
  String get signupOtpinvalidlength;

  /// No description provided for @signupOtpinvalidcode.
  ///
  /// In fr, this message translates to:
  /// **'Code invalide ou expiré.'**
  String get signupOtpinvalidcode;

  /// No description provided for @signupOtpverifyerror.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors de la vérification. Veuillez réessayer.'**
  String get signupOtpverifyerror;

  /// No description provided for @signupVerifylabel.
  ///
  /// In fr, this message translates to:
  /// **'Vérifier et créer mon espace'**
  String get signupVerifylabel;

  /// No description provided for @signupVerifyinglabel.
  ///
  /// In fr, this message translates to:
  /// **'Vérification en cours...'**
  String get signupVerifyinglabel;

  /// No description provided for @signupCodevalidity.
  ///
  /// In fr, this message translates to:
  /// **'Le code est valide pendant 30 minutes. Vérifiez vos spams si vous ne le trouvez pas.'**
  String get signupCodevalidity;

  /// No description provided for @signupTrackstatus.
  ///
  /// In fr, this message translates to:
  /// **'Suivre l\'état de mon espace'**
  String get signupTrackstatus;

  /// No description provided for @signupPendingtitle.
  ///
  /// In fr, this message translates to:
  /// **'Demande d\'essai reçue'**
  String get signupPendingtitle;

  /// No description provided for @signupPendingfallback.
  ///
  /// In fr, this message translates to:
  /// **'Demande d\'essai reçue. Notre équipe vous contacte sous 24h ouvrables.'**
  String get signupPendingfallback;

  /// No description provided for @signupPendingnote.
  ///
  /// In fr, this message translates to:
  /// **'Notre système de création d\'espace instantané est momentanément indisponible (redémarrage serveur). Votre demande est bien enregistrée : une personne de l\'équipe Leopardo vous contactera par email sous 24h ouvrables avec un accès adapté à votre contexte.'**
  String get signupPendingnote;

  /// No description provided for @signupReadytitle.
  ///
  /// In fr, this message translates to:
  /// **'Votre espace est prêt !'**
  String get signupReadytitle;

  /// No description provided for @signupReadysubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Le sandbox de démonstration est provisionné. Accédez-y directement :'**
  String get signupReadysubtitle;

  /// No description provided for @signupAccesscta.
  ///
  /// In fr, this message translates to:
  /// **'Accéder à mon espace'**
  String get signupAccesscta;

  /// No description provided for @signupCopylink.
  ///
  /// In fr, this message translates to:
  /// **'Copier le lien'**
  String get signupCopylink;

  /// No description provided for @signupLinkcopied.
  ///
  /// In fr, this message translates to:
  /// **'Lien copié !'**
  String get signupLinkcopied;

  /// No description provided for @signupLinkemailed.
  ///
  /// In fr, this message translates to:
  /// **'Votre lien d\'accès a également été envoyé par email.'**
  String get signupLinkemailed;

  /// No description provided for @signupFailedtitle.
  ///
  /// In fr, this message translates to:
  /// **'Création interrompue'**
  String get signupFailedtitle;

  /// No description provided for @signupFailedbody.
  ///
  /// In fr, this message translates to:
  /// **'Une erreur est survenue lors de la création de votre espace. Notre équipe vous contactera par email sous 24h ouvrables avec un accès adapté.'**
  String get signupFailedbody;

  /// No description provided for @signupTimeouttitle.
  ///
  /// In fr, this message translates to:
  /// **'Création toujours en cours'**
  String get signupTimeouttitle;

  /// No description provided for @signupTimeoutbody.
  ///
  /// In fr, this message translates to:
  /// **'Votre espace est en cours de préparation. Nous vous enverrons le lien d\'accès par email dès qu\'il sera prêt.'**
  String get signupTimeoutbody;

  /// No description provided for @signupRefreshstatus.
  ///
  /// In fr, this message translates to:
  /// **'Rafraîchir le statut'**
  String get signupRefreshstatus;

  /// No description provided for @signupPreparingtitle.
  ///
  /// In fr, this message translates to:
  /// **'Préparation de votre espace'**
  String get signupPreparingtitle;

  /// No description provided for @signupPreparingbody.
  ///
  /// In fr, this message translates to:
  /// **'Nous provisionnons votre sandbox de démonstration. Cela prend généralement moins de 30 secondes.'**
  String get signupPreparingbody;

  /// No description provided for @signupStatusfor.
  ///
  /// In fr, this message translates to:
  /// **'Pour :'**
  String get signupStatusfor;

  /// No description provided for @signupStatusevery5s.
  ///
  /// In fr, this message translates to:
  /// **'Statut vérifié toutes les 5 secondes.'**
  String get signupStatusevery5s;

  /// No description provided for @signupSuccesstitle.
  ///
  /// In fr, this message translates to:
  /// **'Votre espace est prêt !'**
  String get signupSuccesstitle;

  /// No description provided for @signupEmailverified.
  ///
  /// In fr, this message translates to:
  /// **'Votre adresse email a bien été vérifiée.'**
  String get signupEmailverified;

  /// No description provided for @signupCredslabel.
  ///
  /// In fr, this message translates to:
  /// **'Identifiants de connexion'**
  String get signupCredslabel;

  /// No description provided for @signupFieldemail.
  ///
  /// In fr, this message translates to:
  /// **'Email'**
  String get signupFieldemail;

  /// No description provided for @signupFieldpassword.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe'**
  String get signupFieldpassword;

  /// No description provided for @signupCopypasswordtitle.
  ///
  /// In fr, this message translates to:
  /// **'Copier le mot de passe'**
  String get signupCopypasswordtitle;

  /// No description provided for @signupCopied.
  ///
  /// In fr, this message translates to:
  /// **'Copié !'**
  String get signupCopied;

  /// No description provided for @signupCredssentbyemail.
  ///
  /// In fr, this message translates to:
  /// **'Ces identifiants ont aussi été envoyés par email à'**
  String get signupCredssentbyemail;

  /// No description provided for @signupCredsemailed.
  ///
  /// In fr, this message translates to:
  /// **'Vos identifiants de connexion viennent de vous être envoyés par email.'**
  String get signupCredsemailed;

  /// No description provided for @signupTrialnote.
  ///
  /// In fr, this message translates to:
  /// **'Essai gratuit de'**
  String get signupTrialnote;

  /// No description provided for @signupTrialnotesuffix.
  ///
  /// In fr, this message translates to:
  /// **'aucune carte bancaire requise'**
  String get signupTrialnotesuffix;

  /// No description provided for @signupDownloadapp.
  ///
  /// In fr, this message translates to:
  /// **'Télécharger l\'app'**
  String get signupDownloadapp;

  /// No description provided for @signupChangepasswordnote.
  ///
  /// In fr, this message translates to:
  /// **'Changez votre mot de passe dès la première connexion.'**
  String get signupChangepasswordnote;

  /// No description provided for @signupDefaulterror.
  ///
  /// In fr, this message translates to:
  /// **'Une erreur est survenue'**
  String get signupDefaulterror;

  /// No description provided for @signupValidationEmailinvalid.
  ///
  /// In fr, this message translates to:
  /// **'Email invalide'**
  String get signupValidationEmailinvalid;

  /// No description provided for @signupValidationEmailtooshort.
  ///
  /// In fr, this message translates to:
  /// **'Email trop court'**
  String get signupValidationEmailtooshort;

  /// No description provided for @signupValidationEmailtoolong.
  ///
  /// In fr, this message translates to:
  /// **'Email trop long'**
  String get signupValidationEmailtoolong;

  /// No description provided for @signupValidationCompanytooshort.
  ///
  /// In fr, this message translates to:
  /// **'Le nom de l\'entreprise doit contenir au moins 2 caractères'**
  String get signupValidationCompanytooshort;

  /// No description provided for @signupValidationCompanytoolong.
  ///
  /// In fr, this message translates to:
  /// **'Le nom de l\'entreprise est trop long'**
  String get signupValidationCompanytoolong;

  /// No description provided for @signupValidationRolerequired.
  ///
  /// In fr, this message translates to:
  /// **'Sélectionnez votre rôle'**
  String get signupValidationRolerequired;

  /// No description provided for @signupValidationEmployeesrequired.
  ///
  /// In fr, this message translates to:
  /// **'Sélectionnez une taille d\'équipe'**
  String get signupValidationEmployeesrequired;

  /// No description provided for @signupValidationPhoneinvalid.
  ///
  /// In fr, this message translates to:
  /// **'Numéro de téléphone invalide'**
  String get signupValidationPhoneinvalid;

  /// No description provided for @signupValidationAgreeterms.
  ///
  /// In fr, this message translates to:
  /// **'Vous devez accepter les conditions d\'utilisation'**
  String get signupValidationAgreeterms;
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
