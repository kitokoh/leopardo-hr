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

  /// No description provided for @authDemoAccess.
  ///
  /// In fr, this message translates to:
  /// **'Acces Demo'**
  String get authDemoAccess;

  /// No description provided for @authTogglePasswordVisibility.
  ///
  /// In fr, this message translates to:
  /// **'Afficher ou masquer le mot de passe'**
  String get authTogglePasswordVisibility;

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

  /// No description provided for @commonCountriesBf.
  ///
  /// In fr, this message translates to:
  /// **'Burkina Faso'**
  String get commonCountriesBf;

  /// No description provided for @commonCountriesBj.
  ///
  /// In fr, this message translates to:
  /// **'Bénin'**
  String get commonCountriesBj;

  /// No description provided for @commonCountriesCa.
  ///
  /// In fr, this message translates to:
  /// **'Canada'**
  String get commonCountriesCa;

  /// No description provided for @commonCountriesCf.
  ///
  /// In fr, this message translates to:
  /// **'République centrafricaine'**
  String get commonCountriesCf;

  /// No description provided for @commonCountriesCg.
  ///
  /// In fr, this message translates to:
  /// **'Congo'**
  String get commonCountriesCg;

  /// No description provided for @commonCountriesCi.
  ///
  /// In fr, this message translates to:
  /// **'Côte d\'Ivoire'**
  String get commonCountriesCi;

  /// No description provided for @commonCountriesCm.
  ///
  /// In fr, this message translates to:
  /// **'Cameroun'**
  String get commonCountriesCm;

  /// No description provided for @commonCountriesDz.
  ///
  /// In fr, this message translates to:
  /// **'Algérie'**
  String get commonCountriesDz;

  /// No description provided for @commonCountriesFr.
  ///
  /// In fr, this message translates to:
  /// **'France'**
  String get commonCountriesFr;

  /// No description provided for @commonCountriesGa.
  ///
  /// In fr, this message translates to:
  /// **'Gabon'**
  String get commonCountriesGa;

  /// No description provided for @commonCountriesGb.
  ///
  /// In fr, this message translates to:
  /// **'Royaume-Uni'**
  String get commonCountriesGb;

  /// No description provided for @commonCountriesGq.
  ///
  /// In fr, this message translates to:
  /// **'Guinée équatoriale'**
  String get commonCountriesGq;

  /// No description provided for @commonCountriesMa.
  ///
  /// In fr, this message translates to:
  /// **'Maroc'**
  String get commonCountriesMa;

  /// No description provided for @commonCountriesMl.
  ///
  /// In fr, this message translates to:
  /// **'Mali'**
  String get commonCountriesMl;

  /// No description provided for @commonCountriesNe.
  ///
  /// In fr, this message translates to:
  /// **'Niger'**
  String get commonCountriesNe;

  /// No description provided for @commonCountriesSn.
  ///
  /// In fr, this message translates to:
  /// **'Sénégal'**
  String get commonCountriesSn;

  /// No description provided for @commonCountriesTd.
  ///
  /// In fr, this message translates to:
  /// **'Tchad'**
  String get commonCountriesTd;

  /// No description provided for @commonCountriesTg.
  ///
  /// In fr, this message translates to:
  /// **'Togo'**
  String get commonCountriesTg;

  /// No description provided for @commonCountriesTn.
  ///
  /// In fr, this message translates to:
  /// **'Tunisie'**
  String get commonCountriesTn;

  /// No description provided for @commonCountriesTr.
  ///
  /// In fr, this message translates to:
  /// **'Turquie'**
  String get commonCountriesTr;

  /// No description provided for @commonCountriesUs.
  ///
  /// In fr, this message translates to:
  /// **'États-Unis'**
  String get commonCountriesUs;

  /// No description provided for @commonRequired.
  ///
  /// In fr, this message translates to:
  /// **'Requis'**
  String get commonRequired;

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

  /// No description provided for @usersToastBulkdone.
  ///
  /// In fr, this message translates to:
  /// **'Mise à jour effectuée'**
  String get usersToastBulkdone;

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

  /// No description provided for @usersErrorsPasswordMin.
  ///
  /// In fr, this message translates to:
  /// **'Le mot de passe doit contenir au moins 8 caractères'**
  String get usersErrorsPasswordMin;

  /// No description provided for @usersErrorsSearchNoMatch.
  ///
  /// In fr, this message translates to:
  /// **'Aucune page ne correspond à votre recherche'**
  String get usersErrorsSearchNoMatch;

  /// No description provided for @usersErrorsUpdateFailed.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors de la mise à jour de l\'utilisateur'**
  String get usersErrorsUpdateFailed;

  /// No description provided for @usersImpersonationTitle.
  ///
  /// In fr, this message translates to:
  /// **'Impersonner un employé'**
  String get usersImpersonationTitle;

  /// No description provided for @usersImpersonationSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Ouvrir une session au nom de :name'**
  String get usersImpersonationSubtitle;

  /// No description provided for @usersImpersonationReason.
  ///
  /// In fr, this message translates to:
  /// **'Motif (obligatoire, 5 caractères minimum)'**
  String get usersImpersonationReason;

  /// No description provided for @usersImpersonationReasonmin.
  ///
  /// In fr, this message translates to:
  /// **'Motif obligatoire (5 caractères minimum).'**
  String get usersImpersonationReasonmin;

  /// No description provided for @usersImpersonationNolink.
  ///
  /// In fr, this message translates to:
  /// **'Aucun employé lié à ce compte — impersonation impossible.'**
  String get usersImpersonationNolink;

  /// No description provided for @usersImpersonationStart.
  ///
  /// In fr, this message translates to:
  /// **'Créer la session'**
  String get usersImpersonationStart;

  /// No description provided for @usersImpersonationCancel.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get usersImpersonationCancel;

  /// No description provided for @usersImpersonationTokentitle.
  ///
  /// In fr, this message translates to:
  /// **'Jeton d\'impersonation (usage unique)'**
  String get usersImpersonationTokentitle;

  /// No description provided for @usersImpersonationExpires.
  ///
  /// In fr, this message translates to:
  /// **'Expire le :date'**
  String get usersImpersonationExpires;

  /// No description provided for @usersImpersonationCopy.
  ///
  /// In fr, this message translates to:
  /// **'Copier le jeton'**
  String get usersImpersonationCopy;

  /// No description provided for @usersImpersonationCopied.
  ///
  /// In fr, this message translates to:
  /// **'Jeton copié'**
  String get usersImpersonationCopied;

  /// No description provided for @usersImpersonationCreated.
  ///
  /// In fr, this message translates to:
  /// **'Session d\'impersonation créée'**
  String get usersImpersonationCreated;

  /// No description provided for @usersImpersonationError.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors de la création de la session d\'impersonation'**
  String get usersImpersonationError;

  /// No description provided for @usersImpersonationDone.
  ///
  /// In fr, this message translates to:
  /// **'Terminé'**
  String get usersImpersonationDone;

  /// No description provided for @usersImpersonationEmployee.
  ///
  /// In fr, this message translates to:
  /// **'Employé #:id'**
  String get usersImpersonationEmployee;

  /// No description provided for @usersEditTitle.
  ///
  /// In fr, this message translates to:
  /// **'Modifier l\'utilisateur'**
  String get usersEditTitle;

  /// No description provided for @usersEditStatus.
  ///
  /// In fr, this message translates to:
  /// **'Statut'**
  String get usersEditStatus;

  /// No description provided for @usersEditSave.
  ///
  /// In fr, this message translates to:
  /// **'Mettre à jour'**
  String get usersEditSave;

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
  /// **'Activité récente'**
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
  /// **'Felicitations a toute l\'equipe pour votre engagement de cette semaine. Continuez sur cette dynamique !'**
  String get dashboardLeoIaAnnouncementBody;

  /// No description provided for @dashboardLeoIaAnnouncementError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible d\'envoyer le message. Reessayez dans quelques instants.'**
  String get dashboardLeoIaAnnouncementError;

  /// No description provided for @dashboardLeoPresenceInsight.
  ///
  /// In fr, this message translates to:
  /// **'Aujourd\'hui, {today} presence(s) sur {active} employe(s) actif(s). Souhaitez-vous envoyer un message de felicitations a l\'equipe ?'**
  String dashboardLeoPresenceInsight(Object active, Object today);

  /// No description provided for @dashboardLeoPresenceEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune donnee de presence disponible pour le moment. Souhaitez-vous quand meme envoyer un message de felicitations a l\'equipe ?'**
  String get dashboardLeoPresenceEmpty;

  /// No description provided for @dashboardLeoAnnouncementsCount.
  ///
  /// In fr, this message translates to:
  /// **'{count} annonce(s) publiee(s) dans votre entreprise.'**
  String dashboardLeoAnnouncementsCount(Object count);

  /// No description provided for @dashboardPresenceTodayTitle.
  ///
  /// In fr, this message translates to:
  /// **'Présence aujourd\'hui'**
  String get dashboardPresenceTodayTitle;

  /// No description provided for @dashboardPresenceTodaySummary.
  ///
  /// In fr, this message translates to:
  /// **'{present} presence(s) sur {active} employe(s) actif(s) aujourd hui'**
  String dashboardPresenceTodaySummary(Object active, Object present);

  /// No description provided for @dashboardPresenceTodayEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune donnee de presence disponible.'**
  String get dashboardPresenceTodayEmpty;

  /// No description provided for @dashboardPortfoliopriorities.
  ///
  /// In fr, this message translates to:
  /// **'Priorites Portefeuille'**
  String get dashboardPortfoliopriorities;

  /// No description provided for @dashboardClient.
  ///
  /// In fr, this message translates to:
  /// **'Client'**
  String get dashboardClient;

  /// No description provided for @dashboardHealth.
  ///
  /// In fr, this message translates to:
  /// **'Sante'**
  String get dashboardHealth;

  /// No description provided for @dashboardRisk.
  ///
  /// In fr, this message translates to:
  /// **'Risque'**
  String get dashboardRisk;

  /// No description provided for @dashboardActions.
  ///
  /// In fr, this message translates to:
  /// **'Actions'**
  String get dashboardActions;

  /// No description provided for @dashboardSeeall.
  ///
  /// In fr, this message translates to:
  /// **'Voir tout'**
  String get dashboardSeeall;

  /// No description provided for @dashboardNoprioritycompanies.
  ///
  /// In fr, this message translates to:
  /// **'Aucune entreprise prioritaire pour le moment.'**
  String get dashboardNoprioritycompanies;

  /// No description provided for @dashboardPendingregistrations.
  ///
  /// In fr, this message translates to:
  /// **'Inscriptions en attente'**
  String get dashboardPendingregistrations;

  /// No description provided for @dashboardNopendingrequests.
  ///
  /// In fr, this message translates to:
  /// **'Aucune demande en attente.'**
  String get dashboardNopendingrequests;

  /// No description provided for @dashboardAdoption.
  ///
  /// In fr, this message translates to:
  /// **'Adoption'**
  String get dashboardAdoption;

  /// No description provided for @dashboardCheckins30d.
  ///
  /// In fr, this message translates to:
  /// **'Pointages 30j'**
  String get dashboardCheckins30d;

  /// No description provided for @dashboardActiveemployees.
  ///
  /// In fr, this message translates to:
  /// **'Employés actifs'**
  String get dashboardActiveemployees;

  /// No description provided for @dashboardClientsatrisk.
  ///
  /// In fr, this message translates to:
  /// **'Clients a risque'**
  String get dashboardClientsatrisk;

  /// No description provided for @dashboardRevenue.
  ///
  /// In fr, this message translates to:
  /// **'Revenus'**
  String get dashboardRevenue;

  /// No description provided for @dashboardCollected30d.
  ///
  /// In fr, this message translates to:
  /// **'Encaisse 30j'**
  String get dashboardCollected30d;

  /// No description provided for @dashboardOverdue.
  ///
  /// In fr, this message translates to:
  /// **'Impayes'**
  String get dashboardOverdue;

  /// No description provided for @dashboardActivesubscriptions.
  ///
  /// In fr, this message translates to:
  /// **'Abonnements actifs'**
  String get dashboardActivesubscriptions;

  /// No description provided for @dashboardShortcuts.
  ///
  /// In fr, this message translates to:
  /// **'Raccourcis'**
  String get dashboardShortcuts;

  /// No description provided for @dashboardClientportfolio.
  ///
  /// In fr, this message translates to:
  /// **'Portefeuille clients'**
  String get dashboardClientportfolio;

  /// No description provided for @dashboardSubscriptions.
  ///
  /// In fr, this message translates to:
  /// **'Abonnements'**
  String get dashboardSubscriptions;

  /// No description provided for @dashboardClientrequests.
  ///
  /// In fr, this message translates to:
  /// **'Demandes clients'**
  String get dashboardClientrequests;

  /// No description provided for @dashboardCreateactivateclient.
  ///
  /// In fr, this message translates to:
  /// **'Creer ou activer un client'**
  String get dashboardCreateactivateclient;

  /// No description provided for @dashboardOpenclientportfolio.
  ///
  /// In fr, this message translates to:
  /// **'Ouvrir le portefeuille clients'**
  String get dashboardOpenclientportfolio;

  /// No description provided for @dashboardProcessincomingrequests.
  ///
  /// In fr, this message translates to:
  /// **'Traiter les demandes entrantes'**
  String get dashboardProcessincomingrequests;

  /// No description provided for @dashboardViewclientrequests.
  ///
  /// In fr, this message translates to:
  /// **'Voir les demandes clients'**
  String get dashboardViewclientrequests;

  /// No description provided for @dashboardMonitoratriskclients.
  ///
  /// In fr, this message translates to:
  /// **'Surveiller les clients a risque'**
  String get dashboardMonitoratriskclients;

  /// No description provided for @dashboardAnalyzepriorities.
  ///
  /// In fr, this message translates to:
  /// **'Analyser les priorites'**
  String get dashboardAnalyzepriorities;

  /// No description provided for @dashboardManagesubscriptionsrevenue.
  ///
  /// In fr, this message translates to:
  /// **'Piloter abonnements et revenus'**
  String get dashboardManagesubscriptionsrevenue;

  /// No description provided for @dashboardOpensubscriptions.
  ///
  /// In fr, this message translates to:
  /// **'Ouvrir abonnements'**
  String get dashboardOpensubscriptions;

  /// No description provided for @dashboardChecksystemsecurity.
  ///
  /// In fr, this message translates to:
  /// **'Verifier systeme et securite'**
  String get dashboardChecksystemsecurity;

  /// No description provided for @dashboardOpensystem.
  ///
  /// In fr, this message translates to:
  /// **'Ouvrir systeme'**
  String get dashboardOpensystem;

  /// No description provided for @dashboardPreparepartnerintegrations.
  ///
  /// In fr, this message translates to:
  /// **'Preparer integrations partenaires'**
  String get dashboardPreparepartnerintegrations;

  /// No description provided for @dashboardOpenwebhooks.
  ///
  /// In fr, this message translates to:
  /// **'Ouvrir les webhooks'**
  String get dashboardOpenwebhooks;

  /// No description provided for @dashboardLoaderror.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger le cockpit plateforme.'**
  String get dashboardLoaderror;

  /// No description provided for @dashboardRiskhigh.
  ///
  /// In fr, this message translates to:
  /// **'Risque eleve'**
  String get dashboardRiskhigh;

  /// No description provided for @dashboardRiskmedium.
  ///
  /// In fr, this message translates to:
  /// **'Risque moyen'**
  String get dashboardRiskmedium;

  /// No description provided for @dashboardRisklow.
  ///
  /// In fr, this message translates to:
  /// **'Risque faible'**
  String get dashboardRisklow;

  /// No description provided for @dashboardNotprovided.
  ///
  /// In fr, this message translates to:
  /// **'Non renseigne'**
  String get dashboardNotprovided;

  /// No description provided for @dashboardPendingAbsences.
  ///
  /// In fr, this message translates to:
  /// **'Absences en attente'**
  String get dashboardPendingAbsences;

  /// No description provided for @dashboardDepartments.
  ///
  /// In fr, this message translates to:
  /// **'Départements'**
  String get dashboardDepartments;

  /// No description provided for @dashboardKpiTotal.
  ///
  /// In fr, this message translates to:
  /// **'total'**
  String get dashboardKpiTotal;

  /// No description provided for @dashboardKpiToProcess.
  ///
  /// In fr, this message translates to:
  /// **'à traiter'**
  String get dashboardKpiToProcess;

  /// No description provided for @dashboardKpiActive.
  ///
  /// In fr, this message translates to:
  /// **'actifs'**
  String get dashboardKpiActive;

  /// No description provided for @dashboardPriorityProcessAbsences.
  ///
  /// In fr, this message translates to:
  /// **'Traiter les absences en attente'**
  String get dashboardPriorityProcessAbsences;

  /// No description provided for @dashboardPriorityCheckPresences.
  ///
  /// In fr, this message translates to:
  /// **'Vérifier les présences du jour'**
  String get dashboardPriorityCheckPresences;

  /// No description provided for @dashboardRecentActivityEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune activité récente à afficher pour ce tenant.'**
  String get dashboardRecentActivityEmpty;

  /// No description provided for @dashboardDashboardLoadError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger les données du dashboard.'**
  String get dashboardDashboardLoadError;

  /// No description provided for @dashboardTenantLoading.
  ///
  /// In fr, this message translates to:
  /// **'Chargement des données tenant...'**
  String get dashboardTenantLoading;

  /// No description provided for @dashboardWelcomeToday.
  ///
  /// In fr, this message translates to:
  /// **'Bienvenue ! Voici ce qui se passe aujourd\'hui.'**
  String get dashboardWelcomeToday;

  /// No description provided for @dashboardGoLiveReady.
  ///
  /// In fr, this message translates to:
  /// **'Votre espace est prêt pour le go-live'**
  String get dashboardGoLiveReady;

  /// No description provided for @dashboardGoLiveRequired.
  ///
  /// In fr, this message translates to:
  /// **'Actions requises avant le go-live'**
  String get dashboardGoLiveRequired;

  /// No description provided for @dashboardGoLiveScore.
  ///
  /// In fr, this message translates to:
  /// **'Score {score}/100 basé sur les données tenant, la communication, la paie, le pointage et l\'instrumentation client.'**
  String dashboardGoLiveScore(Object score);

  /// No description provided for @dashboardTabToday.
  ///
  /// In fr, this message translates to:
  /// **'Aujourd\'hui'**
  String get dashboardTabToday;

  /// No description provided for @dashboardTabWeek.
  ///
  /// In fr, this message translates to:
  /// **'Cette semaine'**
  String get dashboardTabWeek;

  /// No description provided for @dashboardSystemFallback.
  ///
  /// In fr, this message translates to:
  /// **'Système'**
  String get dashboardSystemFallback;

  /// No description provided for @dashboardShortcutEmployees.
  ///
  /// In fr, this message translates to:
  /// **'Employés'**
  String get dashboardShortcutEmployees;

  /// No description provided for @dashboardShortcutLeave.
  ///
  /// In fr, this message translates to:
  /// **'Congés'**
  String get dashboardShortcutLeave;

  /// No description provided for @dashboardShortcutAttendance.
  ///
  /// In fr, this message translates to:
  /// **'Pointage'**
  String get dashboardShortcutAttendance;

  /// No description provided for @dashboardShortcutAttendanceHint.
  ///
  /// In fr, this message translates to:
  /// **'Voir votre état du jour.'**
  String get dashboardShortcutAttendanceHint;

  /// No description provided for @dashboardShortcutAbsences.
  ///
  /// In fr, this message translates to:
  /// **'Absences'**
  String get dashboardShortcutAbsences;

  /// No description provided for @dashboardShortcutPayrollHint.
  ///
  /// In fr, this message translates to:
  /// **'Consulter vos documents de paie.'**
  String get dashboardShortcutPayrollHint;

  /// No description provided for @dashboardShortcutPreferencesHint.
  ///
  /// In fr, this message translates to:
  /// **'Votre interface suit vos préférences.'**
  String get dashboardShortcutPreferencesHint;

  /// No description provided for @dashboardJournal.
  ///
  /// In fr, this message translates to:
  /// **'Journal'**
  String get dashboardJournal;

  /// No description provided for @dashboardSeeAllActivity.
  ///
  /// In fr, this message translates to:
  /// **'Voir toute l\'activité'**
  String get dashboardSeeAllActivity;

  /// No description provided for @dashboardAiAssistant.
  ///
  /// In fr, this message translates to:
  /// **'Assistant intelligent'**
  String get dashboardAiAssistant;

  /// No description provided for @dashboardMessageSent.
  ///
  /// In fr, this message translates to:
  /// **'Message envoyé à l\'équipe'**
  String get dashboardMessageSent;

  /// No description provided for @dashboardSending.
  ///
  /// In fr, this message translates to:
  /// **'Envoi...'**
  String get dashboardSending;

  /// No description provided for @dashboardSendYes.
  ///
  /// In fr, this message translates to:
  /// **'Oui, envoyer'**
  String get dashboardSendYes;

  /// No description provided for @dashboardLater.
  ///
  /// In fr, this message translates to:
  /// **'Plus tard'**
  String get dashboardLater;

  /// No description provided for @dashboardQuickActions.
  ///
  /// In fr, this message translates to:
  /// **'Actions rapides'**
  String get dashboardQuickActions;

  /// No description provided for @dashboardQuickReports.
  ///
  /// In fr, this message translates to:
  /// **'Rapports'**
  String get dashboardQuickReports;

  /// No description provided for @dashboardQuickExport.
  ///
  /// In fr, this message translates to:
  /// **'Export'**
  String get dashboardQuickExport;

  /// No description provided for @dashboardEmpCheckin.
  ///
  /// In fr, this message translates to:
  /// **'Pointage'**
  String get dashboardEmpCheckin;

  /// No description provided for @dashboardEmpCheckinHint.
  ///
  /// In fr, this message translates to:
  /// **'Voir votre état du jour.'**
  String get dashboardEmpCheckinHint;

  /// No description provided for @dashboardEmpAbsences.
  ///
  /// In fr, this message translates to:
  /// **'Absences'**
  String get dashboardEmpAbsences;

  /// No description provided for @dashboardEmpAbsencesHint.
  ///
  /// In fr, this message translates to:
  /// **'Suivre vos demandes et soldes.'**
  String get dashboardEmpAbsencesHint;

  /// No description provided for @dashboardEmpPaystubs.
  ///
  /// In fr, this message translates to:
  /// **'Bulletins'**
  String get dashboardEmpPaystubs;

  /// No description provided for @dashboardEmpPaystubsHint.
  ///
  /// In fr, this message translates to:
  /// **'Consulter vos documents de paie.'**
  String get dashboardEmpPaystubsHint;

  /// No description provided for @dashboardEmpLanguage.
  ///
  /// In fr, this message translates to:
  /// **'Langue'**
  String get dashboardEmpLanguage;

  /// No description provided for @dashboardEmpLanguageHint.
  ///
  /// In fr, this message translates to:
  /// **'Votre interface suit vos préférences.'**
  String get dashboardEmpLanguageHint;

  /// No description provided for @dashboardEmployeeSpace.
  ///
  /// In fr, this message translates to:
  /// **'Espace employé'**
  String get dashboardEmployeeSpace;

  /// No description provided for @dashboardHello.
  ///
  /// In fr, this message translates to:
  /// **'Bonjour {name}'**
  String dashboardHello(Object name);

  /// No description provided for @dashboardEmployeeIntro.
  ///
  /// In fr, this message translates to:
  /// **'Retrouvez vos actions utiles sans passer par les vues manager : pointage, absences, bulletins et langue.'**
  String get dashboardEmployeeIntro;

  /// No description provided for @dashboardSuperadminIntro.
  ///
  /// In fr, this message translates to:
  /// **'Cette surface est optimisée pour les espaces clients. L\'administration plateforme se fait depuis le dashboard admin dédié.'**
  String get dashboardSuperadminIntro;

  /// No description provided for @dashboardOpenAdminDashboard.
  ///
  /// In fr, this message translates to:
  /// **'Ouvrir le dashboard admin'**
  String get dashboardOpenAdminDashboard;

  /// No description provided for @dashboardAdminUrlHint.
  ///
  /// In fr, this message translates to:
  /// **'Configurez NEXT_PUBLIC_ADMIN_URL pour ajouter le lien direct vers l\'administration plateforme.'**
  String get dashboardAdminUrlHint;

  /// No description provided for @dashboardPlatformHealth.
  ///
  /// In fr, this message translates to:
  /// **'Santé plateforme'**
  String get dashboardPlatformHealth;

  /// No description provided for @dashboardClientRequests.
  ///
  /// In fr, this message translates to:
  /// **'Demandes clients'**
  String get dashboardClientRequests;

  /// No description provided for @dashboardTenantsAtRisk.
  ///
  /// In fr, this message translates to:
  /// **'Tenants à risque'**
  String get dashboardTenantsAtRisk;

  /// No description provided for @dashboardPlatformDashboardHint.
  ///
  /// In fr, this message translates to:
  /// **'Disponible dans le dashboard plateforme.'**
  String get dashboardPlatformDashboardHint;

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
  /// **'Essai gratuit 14 jours'**
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

  /// No description provided for @signupTrialdaysunit.
  ///
  /// In fr, this message translates to:
  /// **'jours'**
  String get signupTrialdaysunit;

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

  /// No description provided for @signupValidationCountryrequired.
  ///
  /// In fr, this message translates to:
  /// **'Le pays est requis.'**
  String get signupValidationCountryrequired;

  /// No description provided for @signupLabelcountry.
  ///
  /// In fr, this message translates to:
  /// **'Pays'**
  String get signupLabelcountry;

  /// No description provided for @signupCountryplaceholder.
  ///
  /// In fr, this message translates to:
  /// **'Sélectionnez votre pays'**
  String get signupCountryplaceholder;

  /// No description provided for @companiesToastLoadFailed.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger la fiche entreprise.'**
  String get companiesToastLoadFailed;

  /// No description provided for @companiesToastTicketsFailed.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger les tickets support.'**
  String get companiesToastTicketsFailed;

  /// No description provided for @companiesToastSubscriptionFailed.
  ///
  /// In fr, this message translates to:
  /// **'Échec de la mise à jour de l\'abonnement.'**
  String get companiesToastSubscriptionFailed;

  /// No description provided for @companiesToastFeaturesFailed.
  ///
  /// In fr, this message translates to:
  /// **'Échec de la configuration des modules.'**
  String get companiesToastFeaturesFailed;

  /// No description provided for @companiesPortfolio.
  ///
  /// In fr, this message translates to:
  /// **'Portefeuille Clients'**
  String get companiesPortfolio;

  /// No description provided for @companiesDirectory.
  ///
  /// In fr, this message translates to:
  /// **'Repertoire des Entreprises'**
  String get companiesDirectory;

  /// No description provided for @companiesDirectorysub.
  ///
  /// In fr, this message translates to:
  /// **'Liste classee par score de sante et priorite commerciale.'**
  String get companiesDirectorysub;

  /// No description provided for @companiesSyncing.
  ///
  /// In fr, this message translates to:
  /// **'Synchronisation du portefeuille...'**
  String get companiesSyncing;

  /// No description provided for @companiesRetry.
  ///
  /// In fr, this message translates to:
  /// **'Reessayer'**
  String get companiesRetry;

  /// No description provided for @companiesCompany.
  ///
  /// In fr, this message translates to:
  /// **'Entreprise'**
  String get companiesCompany;

  /// No description provided for @companiesPlanmrr.
  ///
  /// In fr, this message translates to:
  /// **'Plan & MRR'**
  String get companiesPlanmrr;

  /// No description provided for @companiesHealthop.
  ///
  /// In fr, this message translates to:
  /// **'Sante Oper.'**
  String get companiesHealthop;

  /// No description provided for @companiesCheckins30d.
  ///
  /// In fr, this message translates to:
  /// **'Pointage (30j)'**
  String get companiesCheckins30d;

  /// No description provided for @companiesRecommendedaction.
  ///
  /// In fr, this message translates to:
  /// **'Action Recommandee'**
  String get companiesRecommendedaction;

  /// No description provided for @companiesManagement.
  ///
  /// In fr, this message translates to:
  /// **'Gestion'**
  String get companiesManagement;

  /// No description provided for @companiesSystem.
  ///
  /// In fr, this message translates to:
  /// **'Systeme'**
  String get companiesSystem;

  /// No description provided for @companiesCompanyname.
  ///
  /// In fr, this message translates to:
  /// **'Nom entreprise *'**
  String get companiesCompanyname;

  /// No description provided for @companiesContactemail.
  ///
  /// In fr, this message translates to:
  /// **'Email contact *'**
  String get companiesContactemail;

  /// No description provided for @companiesCountry.
  ///
  /// In fr, this message translates to:
  /// **'Pays *'**
  String get companiesCountry;

  /// No description provided for @companiesCity.
  ///
  /// In fr, this message translates to:
  /// **'Ville de deploiement *'**
  String get companiesCity;

  /// No description provided for @companiesCurrency.
  ///
  /// In fr, this message translates to:
  /// **'Devise'**
  String get companiesCurrency;

  /// No description provided for @companiesTimezone.
  ///
  /// In fr, this message translates to:
  /// **'Fuseau Horaire'**
  String get companiesTimezone;

  /// No description provided for @companiesDefaultlang.
  ///
  /// In fr, this message translates to:
  /// **'Langue Defaut'**
  String get companiesDefaultlang;

  /// No description provided for @companiesManagerfirst.
  ///
  /// In fr, this message translates to:
  /// **'Prenom Manager *'**
  String get companiesManagerfirst;

  /// No description provided for @companiesManagerlast.
  ///
  /// In fr, this message translates to:
  /// **'Nom Manager *'**
  String get companiesManagerlast;

  /// No description provided for @companiesManageremail.
  ///
  /// In fr, this message translates to:
  /// **'Email Manager Principal *'**
  String get companiesManageremail;

  /// No description provided for @companiesActivatenow.
  ///
  /// In fr, this message translates to:
  /// **'Activer immediatement'**
  String get companiesActivatenow;

  /// No description provided for @companiesActivateclientnow.
  ///
  /// In fr, this message translates to:
  /// **'Activer le client immediatement'**
  String get companiesActivateclientnow;

  /// No description provided for @companiesActivateclienthint.
  ///
  /// In fr, this message translates to:
  /// **'Sinon le client reste en essai (trial).'**
  String get companiesActivateclienthint;

  /// No description provided for @seoPricingDescription.
  ///
  /// In fr, this message translates to:
  /// **'Tarification transparente : plan Free, Pilot 29 €/mois, Operations 99 €/mois, Enterprise sur devis. Essai gratuit 14 jours.'**
  String get seoPricingDescription;

  /// No description provided for @adminchatConversation.
  ///
  /// In fr, this message translates to:
  /// **'Conversation'**
  String get adminchatConversation;

  /// No description provided for @adminchatError.
  ///
  /// In fr, this message translates to:
  /// **'Désolé, une erreur est survenue. Veuillez réessayer.'**
  String get adminchatError;

  /// No description provided for @adminchatHistoryempty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune conversation.'**
  String get adminchatHistoryempty;

  /// No description provided for @adminchatNew.
  ///
  /// In fr, this message translates to:
  /// **'Nouvelle conversation'**
  String get adminchatNew;

  /// No description provided for @adminchatPlaceholder.
  ///
  /// In fr, this message translates to:
  /// **'Tapez votre message...'**
  String get adminchatPlaceholder;

  /// No description provided for @adminchatSend.
  ///
  /// In fr, this message translates to:
  /// **'Envoyer'**
  String get adminchatSend;

  /// No description provided for @adminchatStart.
  ///
  /// In fr, this message translates to:
  /// **'Commencez une conversation avec l’assistant IA.'**
  String get adminchatStart;

  /// No description provided for @adminchatSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Posez vos questions RH, paie, recrutement...'**
  String get adminchatSubtitle;

  /// No description provided for @adminchatThinking.
  ///
  /// In fr, this message translates to:
  /// **'Réflexion en cours...'**
  String get adminchatThinking;

  /// No description provided for @adminchatTitle.
  ///
  /// In fr, this message translates to:
  /// **'Assistant IA Leopardo'**
  String get adminchatTitle;

  /// No description provided for @adminchatUnavailablebadge.
  ///
  /// In fr, this message translates to:
  /// **'Indisponible'**
  String get adminchatUnavailablebadge;

  /// No description provided for @adminchatUnavailablebody.
  ///
  /// In fr, this message translates to:
  /// **'Le chat IA n’est pas activé pour la console super-admin. Utilisez un espace entreprise pris en charge pour accéder à cet assistant.'**
  String get adminchatUnavailablebody;

  /// No description provided for @adminchatUnavailabletitle.
  ///
  /// In fr, this message translates to:
  /// **'Assistant IA indisponible au niveau plateforme'**
  String get adminchatUnavailabletitle;

  /// No description provided for @navigationAnalytics.
  ///
  /// In fr, this message translates to:
  /// **'Analytique'**
  String get navigationAnalytics;

  /// No description provided for @navigationAudit.
  ///
  /// In fr, this message translates to:
  /// **'Journal d\'audit'**
  String get navigationAudit;

  /// No description provided for @navigationChat.
  ///
  /// In fr, this message translates to:
  /// **'Chat IA'**
  String get navigationChat;

  /// No description provided for @navigationCompanies.
  ///
  /// In fr, this message translates to:
  /// **'Entreprises'**
  String get navigationCompanies;

  /// No description provided for @navigationContracts.
  ///
  /// In fr, this message translates to:
  /// **'Contrats'**
  String get navigationContracts;

  /// No description provided for @navigationCrm.
  ///
  /// In fr, this message translates to:
  /// **'Pipeline CRM'**
  String get navigationCrm;

  /// No description provided for @navigationDashboard.
  ///
  /// In fr, this message translates to:
  /// **'Tableau de bord'**
  String get navigationDashboard;

  /// No description provided for @navigationEdge.
  ///
  /// In fr, this message translates to:
  /// **'Nœuds Edge'**
  String get navigationEdge;

  /// No description provided for @navigationExports.
  ///
  /// In fr, this message translates to:
  /// **'Exports & Rapports'**
  String get navigationExports;

  /// No description provided for @navigationFleet.
  ///
  /// In fr, this message translates to:
  /// **'Flotte véhicules'**
  String get navigationFleet;

  /// No description provided for @navigationGlobe.
  ///
  /// In fr, this message translates to:
  /// **'Globe Temps Réel'**
  String get navigationGlobe;

  /// No description provided for @navigationGrowth.
  ///
  /// In fr, this message translates to:
  /// **'Administration Growth'**
  String get navigationGrowth;

  /// No description provided for @navigationLeaves.
  ///
  /// In fr, this message translates to:
  /// **'Congés & Absences'**
  String get navigationLeaves;

  /// No description provided for @navigationMainmenu.
  ///
  /// In fr, this message translates to:
  /// **'Menu principal'**
  String get navigationMainmenu;

  /// No description provided for @navigationMarketing.
  ///
  /// In fr, this message translates to:
  /// **'Marketing'**
  String get navigationMarketing;

  /// No description provided for @navigationPayroll.
  ///
  /// In fr, this message translates to:
  /// **'Paie'**
  String get navigationPayroll;

  /// No description provided for @navigationPredictions.
  ///
  /// In fr, this message translates to:
  /// **'Dashboard Prédictif IA'**
  String get navigationPredictions;

  /// No description provided for @navigationRecruitment.
  ///
  /// In fr, this message translates to:
  /// **'Recrutement'**
  String get navigationRecruitment;

  /// No description provided for @navigationReports.
  ///
  /// In fr, this message translates to:
  /// **'Rapports RH'**
  String get navigationReports;

  /// No description provided for @navigationSubscriptions.
  ///
  /// In fr, this message translates to:
  /// **'Abonnements'**
  String get navigationSubscriptions;

  /// No description provided for @navigationSupport.
  ///
  /// In fr, this message translates to:
  /// **'Support'**
  String get navigationSupport;

  /// No description provided for @navigationSupporttickets.
  ///
  /// In fr, this message translates to:
  /// **'Centre support client'**
  String get navigationSupporttickets;

  /// No description provided for @navigationSystem.
  ///
  /// In fr, this message translates to:
  /// **'Système'**
  String get navigationSystem;

  /// No description provided for @navigationTraining.
  ///
  /// In fr, this message translates to:
  /// **'Formations'**
  String get navigationTraining;

  /// No description provided for @navigationUsers.
  ///
  /// In fr, this message translates to:
  /// **'Utilisateurs'**
  String get navigationUsers;

  /// No description provided for @navigationWebhooks.
  ///
  /// In fr, this message translates to:
  /// **'Webhooks'**
  String get navigationWebhooks;

  /// No description provided for @navigationLogin.
  ///
  /// In fr, this message translates to:
  /// **'Connexion'**
  String get navigationLogin;

  /// No description provided for @navigationLogout.
  ///
  /// In fr, this message translates to:
  /// **'Deconnexion'**
  String get navigationLogout;

  /// No description provided for @navigationCompanydetail.
  ///
  /// In fr, this message translates to:
  /// **'Detail Entreprise'**
  String get navigationCompanydetail;

  /// No description provided for @navigationContributions.
  ///
  /// In fr, this message translates to:
  /// **'Cotisations sociales'**
  String get navigationContributions;

  /// No description provided for @navigationTaxbrackets.
  ///
  /// In fr, this message translates to:
  /// **'Baremes fiscaux'**
  String get navigationTaxbrackets;

  /// No description provided for @navigationLegalrates.
  ///
  /// In fr, this message translates to:
  /// **'Taux legaux'**
  String get navigationLegalrates;

  /// No description provided for @navigationAccount.
  ///
  /// In fr, this message translates to:
  /// **'Mon compte'**
  String get navigationAccount;

  /// No description provided for @navigationNotfound.
  ///
  /// In fr, this message translates to:
  /// **'Page non trouvee'**
  String get navigationNotfound;

  /// No description provided for @webhooksConfirmDelete.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer ce webhook ?'**
  String get webhooksConfirmDelete;

  /// No description provided for @a11ySkipToContent.
  ///
  /// In fr, this message translates to:
  /// **'Aller au contenu principal'**
  String get a11ySkipToContent;

  /// No description provided for @a11yClose.
  ///
  /// In fr, this message translates to:
  /// **'Fermer'**
  String get a11yClose;

  /// No description provided for @a11yPreviousMonth.
  ///
  /// In fr, this message translates to:
  /// **'Mois précédent'**
  String get a11yPreviousMonth;

  /// No description provided for @a11yNextMonth.
  ///
  /// In fr, this message translates to:
  /// **'Mois suivant'**
  String get a11yNextMonth;

  /// No description provided for @shellConnected.
  ///
  /// In fr, this message translates to:
  /// **'Connecte'**
  String get shellConnected;

  /// No description provided for @shellFallbackpolling.
  ///
  /// In fr, this message translates to:
  /// **'Mode secours (polling)'**
  String get shellFallbackpolling;

  /// No description provided for @shellPushunconfigured.
  ///
  /// In fr, this message translates to:
  /// **'Push non configure'**
  String get shellPushunconfigured;

  /// No description provided for @shellDisconnected.
  ///
  /// In fr, this message translates to:
  /// **'Deconnecte'**
  String get shellDisconnected;

  /// No description provided for @shellSearch.
  ///
  /// In fr, this message translates to:
  /// **'Rechercher'**
  String get shellSearch;

  /// No description provided for @shellNotifications.
  ///
  /// In fr, this message translates to:
  /// **'Notifications'**
  String get shellNotifications;

  /// No description provided for @shellNonotifications.
  ///
  /// In fr, this message translates to:
  /// **'Aucune notification'**
  String get shellNonotifications;

  /// No description provided for @shellCriticalalerts.
  ///
  /// In fr, this message translates to:
  /// **'Alertes critiques'**
  String get shellCriticalalerts;

  /// No description provided for @shellLevel.
  ///
  /// In fr, this message translates to:
  /// **'Niveau :'**
  String get shellLevel;

  /// No description provided for @shellFallbackpollingtitle.
  ///
  /// In fr, this message translates to:
  /// **'Notifications via polling de secours (push indisponible)'**
  String get shellFallbackpollingtitle;

  /// No description provided for @shellTenantonly.
  ///
  /// In fr, this message translates to:
  /// **'Fonctionnalite entreprise — reservee aux espaces client'**
  String get shellTenantonly;

  /// No description provided for @exportsReportemployees.
  ///
  /// In fr, this message translates to:
  /// **'Employes'**
  String get exportsReportemployees;

  /// No description provided for @exportsReportemployeesdesc.
  ///
  /// In fr, this message translates to:
  /// **'Liste complete avec postes, contrats, departements.'**
  String get exportsReportemployeesdesc;

  /// No description provided for @exportsReportattendance.
  ///
  /// In fr, this message translates to:
  /// **'Pointage'**
  String get exportsReportattendance;

  /// No description provided for @exportsReportattendancedesc.
  ///
  /// In fr, this message translates to:
  /// **'Registre de presence avec heures et anomalies.'**
  String get exportsReportattendancedesc;

  /// No description provided for @exportsReportpayslips.
  ///
  /// In fr, this message translates to:
  /// **'Bulletins de paie'**
  String get exportsReportpayslips;

  /// No description provided for @exportsReportpayslipsdesc.
  ///
  /// In fr, this message translates to:
  /// **'Export mensuel bulletins avec details salaire.'**
  String get exportsReportpayslipsdesc;

  /// No description provided for @exportsReportabsences.
  ///
  /// In fr, this message translates to:
  /// **'Absences & conges'**
  String get exportsReportabsences;

  /// No description provided for @exportsReportabsencesdesc.
  ///
  /// In fr, this message translates to:
  /// **'Historique demandes et soldes par employe.'**
  String get exportsReportabsencesdesc;

  /// No description provided for @exportsReporttraining.
  ///
  /// In fr, this message translates to:
  /// **'Formations'**
  String get exportsReporttraining;

  /// No description provided for @exportsReporttrainingdesc.
  ///
  /// In fr, this message translates to:
  /// **'Catalogue, sessions, inscriptions et progression.'**
  String get exportsReporttrainingdesc;

  /// No description provided for @exportsReportvehicles.
  ///
  /// In fr, this message translates to:
  /// **'Vehicules'**
  String get exportsReportvehicles;

  /// No description provided for @exportsReportvehiclesdesc.
  ///
  /// In fr, this message translates to:
  /// **'Flotte, kilometrage, maintenances.'**
  String get exportsReportvehiclesdesc;

  /// No description provided for @exportsHrreportstitle.
  ///
  /// In fr, this message translates to:
  /// **'Rapports RH personnalises'**
  String get exportsHrreportstitle;

  /// No description provided for @exportsHrreportssub.
  ///
  /// In fr, this message translates to:
  /// **'Generez des rapports avances avec filtres de periode et departement.'**
  String get exportsHrreportssub;

  /// No description provided for @exportsReporttype.
  ///
  /// In fr, this message translates to:
  /// **'Type de rapport'**
  String get exportsReporttype;

  /// No description provided for @exportsTypeheadcount.
  ///
  /// In fr, this message translates to:
  /// **'Effectifs'**
  String get exportsTypeheadcount;

  /// No description provided for @exportsTypeturnover.
  ///
  /// In fr, this message translates to:
  /// **'Turnover'**
  String get exportsTypeturnover;

  /// No description provided for @exportsTypeabsenteeism.
  ///
  /// In fr, this message translates to:
  /// **'Absenteisme'**
  String get exportsTypeabsenteeism;

  /// No description provided for @exportsTypepayrollsummary.
  ///
  /// In fr, this message translates to:
  /// **'Resume paie'**
  String get exportsTypepayrollsummary;

  /// No description provided for @exportsTypetrainingprogress.
  ///
  /// In fr, this message translates to:
  /// **'Formations'**
  String get exportsTypetrainingprogress;

  /// No description provided for @exportsStartdate.
  ///
  /// In fr, this message translates to:
  /// **'Date debut'**
  String get exportsStartdate;

  /// No description provided for @exportsEnddate.
  ///
  /// In fr, this message translates to:
  /// **'Date fin'**
  String get exportsEnddate;

  /// No description provided for @exportsGenerate.
  ///
  /// In fr, this message translates to:
  /// **'Generer'**
  String get exportsGenerate;

  /// No description provided for @exportsGenerating.
  ///
  /// In fr, this message translates to:
  /// **'Generation...'**
  String get exportsGenerating;

  /// No description provided for @exportsStatusdone.
  ///
  /// In fr, this message translates to:
  /// **'Termine'**
  String get exportsStatusdone;

  /// No description provided for @exportsStatusinprogress.
  ///
  /// In fr, this message translates to:
  /// **'En cours'**
  String get exportsStatusinprogress;

  /// No description provided for @exportsStatusfailed.
  ///
  /// In fr, this message translates to:
  /// **'Echec'**
  String get exportsStatusfailed;

  /// No description provided for @exportsDownload.
  ///
  /// In fr, this message translates to:
  /// **'Telecharger'**
  String get exportsDownload;

  /// No description provided for @exportsDownloading.
  ///
  /// In fr, this message translates to:
  /// **'Telechargement...'**
  String get exportsDownloading;

  /// No description provided for @companydetailAnalyzing.
  ///
  /// In fr, this message translates to:
  /// **'Analyse des donnees client...'**
  String get companydetailAnalyzing;

  /// No description provided for @companydetailRetry.
  ///
  /// In fr, this message translates to:
  /// **'Reessayer'**
  String get companydetailRetry;

  /// No description provided for @companydetailFieldadoption.
  ///
  /// In fr, this message translates to:
  /// **'Adoption Terrain'**
  String get companydetailFieldadoption;

  /// No description provided for @companydetailOnboarding.
  ///
  /// In fr, this message translates to:
  /// **'Onboarding'**
  String get companydetailOnboarding;

  /// No description provided for @companydetailAnomalies30d.
  ///
  /// In fr, this message translates to:
  /// **'Anomalies 30j'**
  String get companydetailAnomalies30d;

  /// No description provided for @companydetailPayrollready.
  ///
  /// In fr, this message translates to:
  /// **'Paie Prete'**
  String get companydetailPayrollready;

  /// No description provided for @companydetailActiveemployees30d.
  ///
  /// In fr, this message translates to:
  /// **'Employes Actifs (30j)'**
  String get companydetailActiveemployees30d;

  /// No description provided for @companydetailNopriorityblockers.
  ///
  /// In fr, this message translates to:
  /// **'Aucun blocage prioritaire detecte.'**
  String get companydetailNopriorityblockers;

  /// No description provided for @companydetailModulesconfig.
  ///
  /// In fr, this message translates to:
  /// **'Configuration des Modules'**
  String get companydetailModulesconfig;

  /// No description provided for @companydetailServiceplan.
  ///
  /// In fr, this message translates to:
  /// **'Plan de services'**
  String get companydetailServiceplan;

  /// No description provided for @companydetailCommercialstatus.
  ///
  /// In fr, this message translates to:
  /// **'Statut Commercial'**
  String get companydetailCommercialstatus;

  /// No description provided for @companydetailStatustrial.
  ///
  /// In fr, this message translates to:
  /// **'Essai (Trial)'**
  String get companydetailStatustrial;

  /// No description provided for @companydetailStatusactive.
  ///
  /// In fr, this message translates to:
  /// **'Actif'**
  String get companydetailStatusactive;

  /// No description provided for @companydetailStatussuspended.
  ///
  /// In fr, this message translates to:
  /// **'Suspendu'**
  String get companydetailStatussuspended;

  /// No description provided for @companydetailStatusexpired.
  ///
  /// In fr, this message translates to:
  /// **'Expire'**
  String get companydetailStatusexpired;

  /// No description provided for @companydetailStartdate.
  ///
  /// In fr, this message translates to:
  /// **'Debut'**
  String get companydetailStartdate;

  /// No description provided for @companydetailInternalnotes.
  ///
  /// In fr, this message translates to:
  /// **'Notes Internes'**
  String get companydetailInternalnotes;

  /// No description provided for @companydetailNosupporttickets.
  ///
  /// In fr, this message translates to:
  /// **'Aucun ticket de support pour ce client.'**
  String get companydetailNosupporttickets;

  /// No description provided for @companydetailTechnicalidentity.
  ///
  /// In fr, this message translates to:
  /// **'Identite Technique'**
  String get companydetailTechnicalidentity;

  /// No description provided for @companydetailPlatformid.
  ///
  /// In fr, this message translates to:
  /// **'ID Plateforme'**
  String get companydetailPlatformid;

  /// No description provided for @companydetailSlug.
  ///
  /// In fr, this message translates to:
  /// **'Slug'**
  String get companydetailSlug;

  /// No description provided for @companydetailCountrycurrency.
  ///
  /// In fr, this message translates to:
  /// **'Pays / Devise'**
  String get companydetailCountrycurrency;

  /// No description provided for @companydetailRegisteredon.
  ///
  /// In fr, this message translates to:
  /// **'Inscrit le'**
  String get companydetailRegisteredon;

  /// No description provided for @companydetailLastactivity.
  ///
  /// In fr, this message translates to:
  /// **'Derniere Activite'**
  String get companydetailLastactivity;

  /// No description provided for @reportsAttendanceTitle.
  ///
  /// In fr, this message translates to:
  /// **'Résumé Présences'**
  String get reportsAttendanceTitle;

  /// No description provided for @reportsAttendanceDesc.
  ///
  /// In fr, this message translates to:
  /// **'Rapport mensuel des présences, retards et absences par employé.'**
  String get reportsAttendanceDesc;

  /// No description provided for @reportsMonthLabel.
  ///
  /// In fr, this message translates to:
  /// **'Mois'**
  String get reportsMonthLabel;

  /// No description provided for @reportsPayrollTitle.
  ///
  /// In fr, this message translates to:
  /// **'Résumé Paie'**
  String get reportsPayrollTitle;

  /// No description provided for @reportsPayrollDesc.
  ///
  /// In fr, this message translates to:
  /// **'Total brut/net, cotisations et charges par période de paie.'**
  String get reportsPayrollDesc;

  /// No description provided for @reportsPeriodLabel.
  ///
  /// In fr, this message translates to:
  /// **'Période'**
  String get reportsPeriodLabel;

  /// No description provided for @reportsLeaveTitle.
  ///
  /// In fr, this message translates to:
  /// **'Soldes Congés'**
  String get reportsLeaveTitle;

  /// No description provided for @reportsLeaveDesc.
  ///
  /// In fr, this message translates to:
  /// **'État des soldes de congés pour tous les employés.'**
  String get reportsLeaveDesc;

  /// No description provided for @reportsYearLabel.
  ///
  /// In fr, this message translates to:
  /// **'Année'**
  String get reportsYearLabel;

  /// No description provided for @reportsHeadcountTitle.
  ///
  /// In fr, this message translates to:
  /// **'Effectifs'**
  String get reportsHeadcountTitle;

  /// No description provided for @reportsHeadcountDesc.
  ///
  /// In fr, this message translates to:
  /// **'Répartition des effectifs actifs par département, type de contrat et genre.'**
  String get reportsHeadcountDesc;

  /// No description provided for @reportsTrainingTitle.
  ///
  /// In fr, this message translates to:
  /// **'Suivi Formations'**
  String get reportsTrainingTitle;

  /// No description provided for @reportsTrainingDesc.
  ///
  /// In fr, this message translates to:
  /// **'Taux de participation et complétion des formations.'**
  String get reportsTrainingDesc;

  /// No description provided for @reportsContractTitle.
  ///
  /// In fr, this message translates to:
  /// **'Échéances Contrats'**
  String get reportsContractTitle;

  /// No description provided for @reportsContractDesc.
  ///
  /// In fr, this message translates to:
  /// **'Contrats arrivant à échéance dans les 30, 60, 90 prochains jours.'**
  String get reportsContractDesc;

  /// No description provided for @reportsDaysLabel.
  ///
  /// In fr, this message translates to:
  /// **'Jours'**
  String get reportsDaysLabel;

  /// No description provided for @reportsGenerate.
  ///
  /// In fr, this message translates to:
  /// **'Générer'**
  String get reportsGenerate;

  /// No description provided for @reportsSuccess.
  ///
  /// In fr, this message translates to:
  /// **'Rapport téléchargé avec succès.'**
  String get reportsSuccess;

  /// No description provided for @reportsError.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors de la génération du rapport.'**
  String get reportsError;

  /// No description provided for @reportsSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Générez et téléchargez vos rapports RH : présences, paie, congés, effectifs, formations et contrats.'**
  String get reportsSubtitle;

  /// No description provided for @notificationsChannelInapp.
  ///
  /// In fr, this message translates to:
  /// **'Dans l\'app'**
  String get notificationsChannelInapp;

  /// No description provided for @notificationsChannelEmailDesc.
  ///
  /// In fr, this message translates to:
  /// **'Messages importants et confirmations.'**
  String get notificationsChannelEmailDesc;

  /// No description provided for @notificationsChannelPushDesc.
  ///
  /// In fr, this message translates to:
  /// **'Alertes rapides sur les appareils enregistrés.'**
  String get notificationsChannelPushDesc;

  /// No description provided for @notificationsChannelSmsDesc.
  ///
  /// In fr, this message translates to:
  /// **'Canal court pour urgences, activé après opt-in.'**
  String get notificationsChannelSmsDesc;

  /// No description provided for @notificationsChannelWhatsappDesc.
  ///
  /// In fr, this message translates to:
  /// **'Canal conversationnel futur, avec opt-in explicite.'**
  String get notificationsChannelWhatsappDesc;

  /// No description provided for @notificationsCategoryPayroll.
  ///
  /// In fr, this message translates to:
  /// **'Paie'**
  String get notificationsCategoryPayroll;

  /// No description provided for @notificationsCategorySecurity.
  ///
  /// In fr, this message translates to:
  /// **'Sécurité'**
  String get notificationsCategorySecurity;

  /// No description provided for @notificationsCategorySystem.
  ///
  /// In fr, this message translates to:
  /// **'Système'**
  String get notificationsCategorySystem;

  /// No description provided for @notificationsCategoryProductTips.
  ///
  /// In fr, this message translates to:
  /// **'Conseils produit'**
  String get notificationsCategoryProductTips;

  /// No description provided for @notificationsCategoriesTitle.
  ///
  /// In fr, this message translates to:
  /// **'Catégories'**
  String get notificationsCategoriesTitle;

  /// No description provided for @notificationsSaveError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible d\'enregistrer les préférences pour le moment.'**
  String get notificationsSaveError;

  /// No description provided for @notificationsChannelInappDesc.
  ///
  /// In fr, this message translates to:
  /// **'Centre de notifications web et mobile.'**
  String get notificationsChannelInappDesc;

  /// No description provided for @employeesLoadError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger les employés.'**
  String get employeesLoadError;

  /// No description provided for @employeesTitle.
  ///
  /// In fr, this message translates to:
  /// **'Équipe'**
  String get employeesTitle;

  /// No description provided for @employeesSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Vue manager branchée à l\'API RH : liste des collaborateurs, statut et points d\'entrée essentiels.'**
  String get employeesSubtitle;

  /// No description provided for @employeesTotalTeam.
  ///
  /// In fr, this message translates to:
  /// **'Total équipe'**
  String get employeesTotalTeam;

  /// No description provided for @employeesSource.
  ///
  /// In fr, this message translates to:
  /// **'Source'**
  String get employeesSource;

  /// No description provided for @employeesState.
  ///
  /// In fr, this message translates to:
  /// **'État'**
  String get employeesState;

  /// No description provided for @employeesLoadingShort.
  ///
  /// In fr, this message translates to:
  /// **'Chargement'**
  String get employeesLoadingShort;

  /// No description provided for @employeesConnectedApi.
  ///
  /// In fr, this message translates to:
  /// **'Connecté à l\'API'**
  String get employeesConnectedApi;

  /// No description provided for @employeesRecentCollaborators.
  ///
  /// In fr, this message translates to:
  /// **'Collaborateurs récents'**
  String get employeesRecentCollaborators;

  /// No description provided for @employeesListLoading.
  ///
  /// In fr, this message translates to:
  /// **'Chargement de la liste équipe...'**
  String get employeesListLoading;

  /// No description provided for @employeesEmptyList.
  ///
  /// In fr, this message translates to:
  /// **'Aucun employé visible pour ce compte.'**
  String get employeesEmptyList;

  /// No description provided for @userAuthCompanyRequestTitle.
  ///
  /// In fr, this message translates to:
  /// **'Demande soumise !'**
  String get userAuthCompanyRequestTitle;

  /// No description provided for @userAuthCompanyRequestBody.
  ///
  /// In fr, this message translates to:
  /// **'Un administrateur examinera votre demande. Vous recevrez une notification dès qu\'elle sera traitée.'**
  String get userAuthCompanyRequestBody;

  /// No description provided for @userAuthCompanyRequestInfo.
  ///
  /// In fr, this message translates to:
  /// **'Remplissez les informations de votre entreprise. Un administrateur validera votre demande.'**
  String get userAuthCompanyRequestInfo;

  /// No description provided for @userAuthBackToHome.
  ///
  /// In fr, this message translates to:
  /// **'À l\'accueil'**
  String get userAuthBackToHome;

  /// No description provided for @userAuthCreateCompany.
  ///
  /// In fr, this message translates to:
  /// **'Créer une entreprise'**
  String get userAuthCreateCompany;

  /// No description provided for @userAuthCompanyName.
  ///
  /// In fr, this message translates to:
  /// **'Nom de l\'entreprise'**
  String get userAuthCompanyName;

  /// No description provided for @userAuthCompanyEmail.
  ///
  /// In fr, this message translates to:
  /// **'Email entreprise'**
  String get userAuthCompanyEmail;

  /// No description provided for @userAuthSector.
  ///
  /// In fr, this message translates to:
  /// **'Secteur d\'activité'**
  String get userAuthSector;

  /// No description provided for @userAuthCountry.
  ///
  /// In fr, this message translates to:
  /// **'Pays'**
  String get userAuthCountry;

  /// No description provided for @userAuthCity.
  ///
  /// In fr, this message translates to:
  /// **'Ville'**
  String get userAuthCity;

  /// No description provided for @userAuthPhone.
  ///
  /// In fr, this message translates to:
  /// **'Téléphone'**
  String get userAuthPhone;

  /// No description provided for @userAuthDescription.
  ///
  /// In fr, this message translates to:
  /// **'Description'**
  String get userAuthDescription;

  /// No description provided for @userAuthSubmitRequest.
  ///
  /// In fr, this message translates to:
  /// **'Soumettre la demande'**
  String get userAuthSubmitRequest;

  /// No description provided for @userAuthSubmitError.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors de la soumission'**
  String get userAuthSubmitError;

  /// No description provided for @userAuthAlreadyAccount.
  ///
  /// In fr, this message translates to:
  /// **'Deja un compte ? Se connecter'**
  String get userAuthAlreadyAccount;

  /// No description provided for @userAuthFirstName.
  ///
  /// In fr, this message translates to:
  /// **'Prenom'**
  String get userAuthFirstName;

  /// No description provided for @userAuthGoogleError.
  ///
  /// In fr, this message translates to:
  /// **'Erreur Google : {error}'**
  String userAuthGoogleError(Object error);

  /// No description provided for @userAuthLastName.
  ///
  /// In fr, this message translates to:
  /// **'Nom'**
  String get userAuthLastName;

  /// No description provided for @userAuthLoginSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Retrouvez votre espace, vos documents et vos demandes.'**
  String get userAuthLoginSubtitle;

  /// No description provided for @userAuthNoAccount.
  ///
  /// In fr, this message translates to:
  /// **'Pas encore de compte ? S\'inscrire'**
  String get userAuthNoAccount;

  /// No description provided for @userAuthPersonalLogin.
  ///
  /// In fr, this message translates to:
  /// **'Connexion personnelle'**
  String get userAuthPersonalLogin;

  /// No description provided for @userAuthPhoneOptional.
  ///
  /// In fr, this message translates to:
  /// **'Telephone (optionnel)'**
  String get userAuthPhoneOptional;

  /// No description provided for @userAuthRegisterButton.
  ///
  /// In fr, this message translates to:
  /// **'Creer mon compte'**
  String get userAuthRegisterButton;

  /// No description provided for @userAuthRegisterSubtitleAlt.
  ///
  /// In fr, this message translates to:
  /// **'Accedez a votre espace personnel et organisez vos documents.'**
  String get userAuthRegisterSubtitleAlt;

  /// No description provided for @userAuthRegisterTitle.
  ///
  /// In fr, this message translates to:
  /// **'Creer mon compte'**
  String get userAuthRegisterTitle;

  /// No description provided for @partnerpageLoading.
  ///
  /// In fr, this message translates to:
  /// **'Chargement de votre espace...'**
  String get partnerpageLoading;

  /// No description provided for @partnerpageApplyerrorprefix.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors de la candidature : '**
  String get partnerpageApplyerrorprefix;

  /// No description provided for @partnerpageNotappliedTitle.
  ///
  /// In fr, this message translates to:
  /// **'Devenir Partenaire'**
  String get partnerpageNotappliedTitle;

  /// No description provided for @partnerpageNotappliedSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Rejoignez l\'écosystème Leopardo RH et gagnez des commissions sur chaque entreprise que vous parrainez. Jusqu\'à 20 % de commission récurrente.'**
  String get partnerpageNotappliedSubtitle;

  /// No description provided for @partnerpageNotappliedIndividual.
  ///
  /// In fr, this message translates to:
  /// **'Postuler en tant qu\'Individuel'**
  String get partnerpageNotappliedIndividual;

  /// No description provided for @partnerpageNotappliedAgency.
  ///
  /// In fr, this message translates to:
  /// **'Postuler en tant qu\'Agence'**
  String get partnerpageNotappliedAgency;

  /// No description provided for @partnerpagePendingTitle.
  ///
  /// In fr, this message translates to:
  /// **'Candidature en cours'**
  String get partnerpagePendingTitle;

  /// No description provided for @partnerpagePendingBody.
  ///
  /// In fr, this message translates to:
  /// **'Votre demande est en cours de validation par notre équipe commerciale. Vous recevrez un email dès que votre accès sera activé.'**
  String get partnerpagePendingBody;

  /// No description provided for @partnerpageDashboardTitle.
  ///
  /// In fr, this message translates to:
  /// **'Dashboard Partenaire'**
  String get partnerpageDashboardTitle;

  /// No description provided for @partnerpageDashboardSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Suivez vos conversions et vos commissions Leopardo RH — statut partenaire actif.'**
  String get partnerpageDashboardSubtitle;

  /// No description provided for @partnerpageMetricsConversions.
  ///
  /// In fr, this message translates to:
  /// **'Conversions'**
  String get partnerpageMetricsConversions;

  /// No description provided for @partnerpageMetricsTotalearned.
  ///
  /// In fr, this message translates to:
  /// **'Gains totaux'**
  String get partnerpageMetricsTotalearned;

  /// No description provided for @partnerpageMetricsPending.
  ///
  /// In fr, this message translates to:
  /// **'En attente'**
  String get partnerpageMetricsPending;

  /// No description provided for @partnerpageMetricsWithdrawable.
  ///
  /// In fr, this message translates to:
  /// **'Solde retirable'**
  String get partnerpageMetricsWithdrawable;

  /// No description provided for @partnerpageCommissionsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Dernières commissions'**
  String get partnerpageCommissionsTitle;

  /// No description provided for @partnerpageCommissionsEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune commission enregistrée.'**
  String get partnerpageCommissionsEmpty;

  /// No description provided for @partnerpageTableTenantid.
  ///
  /// In fr, this message translates to:
  /// **'Tenant ID'**
  String get partnerpageTableTenantid;

  /// No description provided for @partnerpageTableDate.
  ///
  /// In fr, this message translates to:
  /// **'Date'**
  String get partnerpageTableDate;

  /// No description provided for @partnerpageTableStatus.
  ///
  /// In fr, this message translates to:
  /// **'Statut'**
  String get partnerpageTableStatus;

  /// No description provided for @partnerpageTableAmount.
  ///
  /// In fr, this message translates to:
  /// **'Montant'**
  String get partnerpageTableAmount;

  /// No description provided for @partnerpageTableStatuspaid.
  ///
  /// In fr, this message translates to:
  /// **'Payée'**
  String get partnerpageTableStatuspaid;

  /// No description provided for @partnerpageTableStatuspending.
  ///
  /// In fr, this message translates to:
  /// **'En attente'**
  String get partnerpageTableStatuspending;

  /// No description provided for @partnerpagePayoutTitle.
  ///
  /// In fr, this message translates to:
  /// **'Paiement'**
  String get partnerpagePayoutTitle;

  /// No description provided for @partnerpagePayoutBody.
  ///
  /// In fr, this message translates to:
  /// **'Vos commissions sont payées une fois le seuil atteint. Vérifiez que vos coordonnées bancaires sont à jour.'**
  String get partnerpagePayoutBody;

  /// No description provided for @partnerpagePayoutRequest.
  ///
  /// In fr, this message translates to:
  /// **'Demander un virement'**
  String get partnerpagePayoutRequest;

  /// No description provided for @partnerpagePayoutSending.
  ///
  /// In fr, this message translates to:
  /// **'Envoi...'**
  String get partnerpagePayoutSending;

  /// No description provided for @partnerpagePayoutInsufficient.
  ///
  /// In fr, this message translates to:
  /// **'Solde insuffisant pour demander un virement (minimum 100,00 €).'**
  String get partnerpagePayoutInsufficient;

  /// No description provided for @partnerpagePayoutSuccess.
  ///
  /// In fr, this message translates to:
  /// **'Demande de virement envoyée avec succès.'**
  String get partnerpagePayoutSuccess;

  /// No description provided for @partnerpagePayoutErrorprefix.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors de la demande de virement : '**
  String get partnerpagePayoutErrorprefix;

  /// No description provided for @partnerpageReferralTitle.
  ///
  /// In fr, this message translates to:
  /// **'Lien de parrainage'**
  String get partnerpageReferralTitle;

  /// No description provided for @partnerpageReferralUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'Lien indisponible'**
  String get partnerpageReferralUnavailable;

  /// No description provided for @partnerpageReferralCopy.
  ///
  /// In fr, this message translates to:
  /// **'Copier mon lien'**
  String get partnerpageReferralCopy;

  /// No description provided for @partnerpageReferralCopied.
  ///
  /// In fr, this message translates to:
  /// **'Copié !'**
  String get partnerpageReferralCopied;

  /// No description provided for @partnerpageReferralCopyerror.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de copier le lien. Copiez-le manuellement.'**
  String get partnerpageReferralCopyerror;

  /// No description provided for @apiSessionexpired.
  ///
  /// In fr, this message translates to:
  /// **'Session expirée. Reconnexion en cours...'**
  String get apiSessionexpired;

  /// No description provided for @apiAccessdenied.
  ///
  /// In fr, this message translates to:
  /// **'Accès refusé sur :endpoint. Permissions insuffisantes.'**
  String get apiAccessdenied;

  /// No description provided for @apiNotfound.
  ///
  /// In fr, this message translates to:
  /// **'Ressource introuvable : :endpoint'**
  String get apiNotfound;

  /// No description provided for @apiToomanyrequests.
  ///
  /// In fr, this message translates to:
  /// **'Trop de requêtes. Veuillez patienter quelques secondes.'**
  String get apiToomanyrequests;

  /// No description provided for @apiServererror.
  ///
  /// In fr, this message translates to:
  /// **'Erreur serveur sur :endpoint. :detail'**
  String get apiServererror;

  /// No description provided for @apiServererrorretry.
  ///
  /// In fr, this message translates to:
  /// **'Réessayez plus tard.'**
  String get apiServererrorretry;

  /// No description provided for @apiServerunavailable.
  ///
  /// In fr, this message translates to:
  /// **'Le serveur est temporairement indisponible (:status). Réessayez dans quelques instants.'**
  String get apiServerunavailable;

  /// No description provided for @apiGenericerror.
  ///
  /// In fr, this message translates to:
  /// **'Erreur :status sur :endpoint.'**
  String get apiGenericerror;

  /// No description provided for @apiInvaliddata.
  ///
  /// In fr, this message translates to:
  /// **'Données invalides.'**
  String get apiInvaliddata;

  /// No description provided for @apiConnectionerror.
  ///
  /// In fr, this message translates to:
  /// **'Erreur de connexion. Vérifiez votre connexion internet.'**
  String get apiConnectionerror;

  /// No description provided for @settingspageCancel.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get settingspageCancel;

  /// No description provided for @settingspageConfirmpassword.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer le mot de passe'**
  String get settingspageConfirmpassword;

  /// No description provided for @settingspageCurrentpassword.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe actuel'**
  String get settingspageCurrentpassword;

  /// No description provided for @settingspageDisable2fa.
  ///
  /// In fr, this message translates to:
  /// **'Désactiver le 2FA'**
  String get settingspageDisable2fa;

  /// No description provided for @settingspageDisabled.
  ///
  /// In fr, this message translates to:
  /// **'Désactivé'**
  String get settingspageDisabled;

  /// No description provided for @settingspageEmail.
  ///
  /// In fr, this message translates to:
  /// **'Adresse email'**
  String get settingspageEmail;

  /// No description provided for @settingspageEnable2fa.
  ///
  /// In fr, this message translates to:
  /// **'Activer le 2FA'**
  String get settingspageEnable2fa;

  /// No description provided for @settingspageEnabled.
  ///
  /// In fr, this message translates to:
  /// **'Activé'**
  String get settingspageEnabled;

  /// No description provided for @settingspageEntercodestep.
  ///
  /// In fr, this message translates to:
  /// **'2. Entrez le code à 6 chiffres généré'**
  String get settingspageEntercodestep;

  /// No description provided for @settingspageFullname.
  ///
  /// In fr, this message translates to:
  /// **'Nom complet'**
  String get settingspageFullname;

  /// No description provided for @settingspageGeneratesecret.
  ///
  /// In fr, this message translates to:
  /// **'Générer un secret 2FA'**
  String get settingspageGeneratesecret;

  /// No description provided for @settingspageGeneratesecrethint.
  ///
  /// In fr, this message translates to:
  /// **'Générez un secret et scannez-le avec une application d\'authentification (Google Authenticator, Authy, 1Password...).'**
  String get settingspageGeneratesecrethint;

  /// No description provided for @settingspageManualsecret.
  ///
  /// In fr, this message translates to:
  /// **'Secret manuel :'**
  String get settingspageManualsecret;

  /// No description provided for @settingspageMinlengthhint.
  ///
  /// In fr, this message translates to:
  /// **'Minimum 8 caractères.'**
  String get settingspageMinlengthhint;

  /// No description provided for @settingspageNewpassword.
  ///
  /// In fr, this message translates to:
  /// **'Nouveau mot de passe'**
  String get settingspageNewpassword;

  /// No description provided for @settingspagePassword.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe'**
  String get settingspagePassword;

  /// No description provided for @settingspagePasswordsubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Changer votre mot de passe déconnectera automatiquement toutes vos autres sessions actives.'**
  String get settingspagePasswordsubtitle;

  /// No description provided for @settingspagePasswordtitle.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe'**
  String get settingspagePasswordtitle;

  /// No description provided for @settingspagePasswordupdated.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe mis à jour avec succès.'**
  String get settingspagePasswordupdated;

  /// No description provided for @settingspagePasswordsmismatch.
  ///
  /// In fr, this message translates to:
  /// **'Les mots de passe ne correspondent pas.'**
  String get settingspagePasswordsmismatch;

  /// No description provided for @settingspageProfilesubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Nom et adresse email utilisés pour vous connecter.'**
  String get settingspageProfilesubtitle;

  /// No description provided for @settingspageProfiletitle.
  ///
  /// In fr, this message translates to:
  /// **'Informations du profil'**
  String get settingspageProfiletitle;

  /// No description provided for @settingspageProfileupdated.
  ///
  /// In fr, this message translates to:
  /// **'Profil mis à jour avec succès.'**
  String get settingspageProfileupdated;

  /// No description provided for @settingspageSavechanges.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer les modifications'**
  String get settingspageSavechanges;

  /// No description provided for @settingspageScanstep.
  ///
  /// In fr, this message translates to:
  /// **'1. Scannez ce lien / secret dans votre application 2FA :'**
  String get settingspageScanstep;

  /// No description provided for @settingspageSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Gérez vos informations, votre mot de passe et la sécurité de votre compte super-administrateur.'**
  String get settingspageSubtitle;

  /// No description provided for @settingspageTitle.
  ///
  /// In fr, this message translates to:
  /// **'Mon compte'**
  String get settingspageTitle;

  /// No description provided for @settingspageTwofactoractivehint.
  ///
  /// In fr, this message translates to:
  /// **'Le 2FA est actif. Pour le désactiver, confirmez votre mot de passe.'**
  String get settingspageTwofactoractivehint;

  /// No description provided for @settingspageTwofactordisabled.
  ///
  /// In fr, this message translates to:
  /// **'2FA désactivé.'**
  String get settingspageTwofactordisabled;

  /// No description provided for @settingspageTwofactorenabled.
  ///
  /// In fr, this message translates to:
  /// **'2FA activé avec succès.'**
  String get settingspageTwofactorenabled;

  /// No description provided for @settingspageTwofactorsubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Ajoutez une couche de sécurité supplémentaire à votre compte de super-administrateur.'**
  String get settingspageTwofactorsubtitle;

  /// No description provided for @settingspageTwofactortitle.
  ///
  /// In fr, this message translates to:
  /// **'Authentification à deux facteurs (2FA)'**
  String get settingspageTwofactortitle;

  /// No description provided for @settingspageUpdatepassword.
  ///
  /// In fr, this message translates to:
  /// **'Mettre à jour le mot de passe'**
  String get settingspageUpdatepassword;

  /// No description provided for @systempageApierror.
  ///
  /// In fr, this message translates to:
  /// **'Erreur : :error'**
  String get systempageApierror;

  /// No description provided for @systempageApioperational.
  ///
  /// In fr, this message translates to:
  /// **'API opérationnelle'**
  String get systempageApioperational;

  /// No description provided for @systempageApioperationaldb.
  ///
  /// In fr, this message translates to:
  /// **'API opérationnelle — DB :ms ms'**
  String get systempageApioperationaldb;

  /// No description provided for @systempageApiservices.
  ///
  /// In fr, this message translates to:
  /// **'Services API'**
  String get systempageApiservices;

  /// No description provided for @systempageApiunavailable.
  ///
  /// In fr, this message translates to:
  /// **'Non disponible — GET /health/live'**
  String get systempageApiunavailable;

  /// No description provided for @systempageDatabase.
  ///
  /// In fr, this message translates to:
  /// **'Base de Données'**
  String get systempageDatabase;

  /// No description provided for @systempageDberror.
  ///
  /// In fr, this message translates to:
  /// **'Erreur : :error'**
  String get systempageDberror;

  /// No description provided for @systempageDblatency.
  ///
  /// In fr, this message translates to:
  /// **'Latence : :ms ms'**
  String get systempageDblatency;

  /// No description provided for @systempageDbunavailable.
  ///
  /// In fr, this message translates to:
  /// **'Non disponible — lancez un Health Check.'**
  String get systempageDbunavailable;

  /// No description provided for @systempageDbunreachable.
  ///
  /// In fr, this message translates to:
  /// **'base injoignable'**
  String get systempageDbunreachable;

  /// No description provided for @systempageGlobalerror.
  ///
  /// In fr, this message translates to:
  /// **'Sonde agrégée : base de données injoignable.'**
  String get systempageGlobalerror;

  /// No description provided for @systempageGlobalhealthy.
  ///
  /// In fr, this message translates to:
  /// **'Sonde agrégée DB + Redis opérationnelle.'**
  String get systempageGlobalhealthy;

  /// No description provided for @systempageGlobalstatus.
  ///
  /// In fr, this message translates to:
  /// **'Statut Global'**
  String get systempageGlobalstatus;

  /// No description provided for @systempageGlobalunavailable.
  ///
  /// In fr, this message translates to:
  /// **'Non disponible — GET /admin/dashboard/stats'**
  String get systempageGlobalunavailable;

  /// No description provided for @systempageGlobalwarning.
  ///
  /// In fr, this message translates to:
  /// **'Sonde agrégée : dégradation détectée.'**
  String get systempageGlobalwarning;

  /// No description provided for @systempageHealthcheck.
  ///
  /// In fr, this message translates to:
  /// **'Health Check'**
  String get systempageHealthcheck;

  /// No description provided for @systempageHealthcheckrunning.
  ///
  /// In fr, this message translates to:
  /// **'Analyse...'**
  String get systempageHealthcheckrunning;

  /// No description provided for @systempageHealtherror.
  ///
  /// In fr, this message translates to:
  /// **'Health check terminé — base de données en erreur'**
  String get systempageHealtherror;

  /// No description provided for @systempageHealthliveunreachable.
  ///
  /// In fr, this message translates to:
  /// **'Sonde /health/live injoignable.'**
  String get systempageHealthliveunreachable;

  /// No description provided for @systempageHealthok.
  ///
  /// In fr, this message translates to:
  /// **'Health check terminé — base de données opérationnelle'**
  String get systempageHealthok;

  /// No description provided for @systempageHealthunreachable.
  ///
  /// In fr, this message translates to:
  /// **'Health check terminé — base de données injoignable'**
  String get systempageHealthunreachable;

  /// No description provided for @systempageInfradetails.
  ///
  /// In fr, this message translates to:
  /// **':active compagnies actives · PHP :php · queue :queue'**
  String get systempageInfradetails;

  /// No description provided for @systempageInfraunavailable.
  ///
  /// In fr, this message translates to:
  /// **'Non disponible — GET /platform/metrics/overview'**
  String get systempageInfraunavailable;

  /// No description provided for @systempageInfrastructure.
  ///
  /// In fr, this message translates to:
  /// **'Infrastructure'**
  String get systempageInfrastructure;

  /// No description provided for @systempageMetricsloaderror.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors du chargement des métriques plateforme'**
  String get systempageMetricsloaderror;

  /// No description provided for @systempageNotifobsloaderror.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors du chargement de l\'observabilité des notifications'**
  String get systempageNotifobsloaderror;

  /// No description provided for @systempageQueueobsloaderror.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors du chargement de l\'observabilité des jobs'**
  String get systempageQueueobsloaderror;

  /// No description provided for @systempageRetry.
  ///
  /// In fr, this message translates to:
  /// **'Réessayer'**
  String get systempageRetry;

  /// No description provided for @systempageServiceunreachable.
  ///
  /// In fr, this message translates to:
  /// **'service injoignable'**
  String get systempageServiceunreachable;

  /// No description provided for @systempageStatsloaderror.
  ///
  /// In fr, this message translates to:
  /// **'Erreur lors du chargement des stats système'**
  String get systempageStatsloaderror;

  /// No description provided for @systempageSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Monitoring, configuration et automatisation de la plateforme Leopardo RH.'**
  String get systempageSubtitle;

  /// No description provided for @systempageTitle.
  ///
  /// In fr, this message translates to:
  /// **'Administration Système'**
  String get systempageTitle;

  /// No description provided for @retry.
  ///
  /// In fr, this message translates to:
  /// **'Réessayer'**
  String get retry;

  /// No description provided for @settingsJourneyLoadError.
  String get settingsJourneyLoadError;

  /// No description provided for @settingsStatsLoadError.
  String get settingsStatsLoadError;
  /// No description provided for @featureComingSoon.
  ///
  /// In fr, this message translates to:
  /// **'Fonction bientôt disponible'**
  String get featureComingSoon;

  /// No description provided for @backToHome.
  ///
  /// In fr, this message translates to:
  /// **'Retour à l\'accueil'**
  String get backToHome;

  /// No description provided for @pageNotFound.
  ///
  /// In fr, this message translates to:
  /// **'La page demandée est introuvable ou la navigation a échoué.'**
  String get pageNotFound;

  /// No description provided for @registerCreateAccount.
  ///
  /// In fr, this message translates to:
  /// **'Créer votre compte'**
  String get registerCreateAccount;

  /// No description provided for @registerFirstName.
  ///
  /// In fr, this message translates to:
  /// **'Prénom'**
  String get registerFirstName;

  /// No description provided for @registerRequired.
  ///
  /// In fr, this message translates to:
  /// **'Obligatoire'**
  String get registerRequired;

  /// No description provided for @registerPassword.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe'**
  String get registerPassword;

  /// No description provided for @registerMinChars.
  ///
  /// In fr, this message translates to:
  /// **'8 caractères minimum'**
  String get registerMinChars;

  /// No description provided for @registerCreating.
  ///
  /// In fr, this message translates to:
  /// **'Création de compte en cours...'**
  String get registerCreating;

  /// No description provided for @registerSubmit.
  ///
  /// In fr, this message translates to:
  /// **'Créer mon compte'**
  String get registerSubmit;

  /// No description provided for @accessDeniedTitle.
  ///
  /// In fr, this message translates to:
  /// **'Accès refusé'**
  String get accessDeniedTitle;

  /// No description provided for @accessDeniedBody.
  ///
  /// In fr, this message translates to:
  /// **'Votre compte n\'a pas le rôle Manager requis pour cette application. Utilisez l\'application correspondant à votre rôle (Employee, RH…) ou contactez votre administrateur.'**
  String get accessDeniedBody;

  /// No description provided for @accessDeniedLogout.
  ///
  /// In fr, this message translates to:
  /// **'Se déconnecter'**
  String get accessDeniedLogout;

  /// No description provided for @accessDeniedBodyHr.
  ///
  /// In fr, this message translates to:
  /// **'Votre compte n\'a pas le rôle RH requis pour cette application. Utilisez l\'application correspondant à votre rôle (Employee, Manager…) ou contactez votre administrateur.'**
  String get accessDeniedBodyHr;

  /// No description provided for @evaluationsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Mes Évaluations'**
  String get evaluationsTitle;

  /// No description provided for @evaluationsEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune évaluation'**
  String get evaluationsEmpty;

  /// No description provided for @evaluationsEmptyHint.
  ///
  /// In fr, this message translates to:
  /// **'Vous n\'avez pas encore d\'évaluation enregistrée.'**
  String get evaluationsEmptyHint;

  /// No description provided for @evaluationPeriod.
  ///
  /// In fr, this message translates to:
  /// **'Période : {period}'**
  String evaluationPeriod(String period);

  /// No description provided for @attendanceOnTime.
  ///
  /// In fr, this message translates to:
  /// **'à l\'heure'**
  String get attendanceOnTime;

  /// No description provided for @attendanceLate.
  ///
  /// In fr, this message translates to:
  /// **'en retard'**
  String get attendanceLate;

  /// No description provided for @attendanceAbsent.
  ///
  /// In fr, this message translates to:
  /// **'absent'**
  String get attendanceAbsent;

  /// No description provided for @attendanceInProgress.
  ///
  /// In fr, this message translates to:
  /// **'en cours'**
  String get attendanceInProgress;

  /// No description provided for @attendanceNoClock.
  ///
  /// In fr, this message translates to:
  /// **'pas de pointage'**
  String get attendanceNoClock;

  /// No description provided for @attendanceTimeRange.
  ///
  /// In fr, this message translates to:
  /// **'de {from} à {to}'**
  String attendanceTimeRange(String from, String to);

  /// No description provided for @attendanceHourWorked.
  ///
  /// In fr, this message translates to:
  /// **'heure travaillée'**
  String get attendanceHourWorked;

  /// No description provided for @attendanceHoursWorked.
  ///
  /// In fr, this message translates to:
  /// **'heures travaillées'**
  String get attendanceHoursWorked;

  /// No description provided for @attendanceDaySummary.
  ///
  /// In fr, this message translates to:
  /// **'Journée du {date}, statut {status}, {range}, {hours}.'**
  String attendanceDaySummary(
      String date, String status, String range, String hours);

  /// No description provided for @sessionApproved.
  ///
  /// In fr, this message translates to:
  /// **'Session approuvée ✓'**
  String get sessionApproved;

  /// No description provided for @sessionRejected.
  ///
  /// In fr, this message translates to:
  /// **'Session rejetée'**
  String get sessionRejected;

  /// No description provided for @pendingSessionsToValidate.
  ///
  /// In fr, this message translates to:
  /// **'À valider'**
  String get pendingSessionsToValidate;

  /// No description provided for @pendingSessionsUpToDate.
  ///
  /// In fr, this message translates to:
  /// **'Tout est à jour'**
  String get pendingSessionsUpToDate;

  /// No description provided for @pendingSessionsEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune session GPS en attente de validation.'**
  String get pendingSessionsEmpty;

  /// No description provided for @employeeNumber.
  ///
  /// In fr, this message translates to:
  /// **'Employé #{id}'**
  String employeeNumber(String id);

  /// No description provided for @sessionEntryAt.
  ///
  /// In fr, this message translates to:
  /// **'Entrée : {time}'**
  String sessionEntryAt(String time);

  /// No description provided for @sessionsToValidate.
  ///
  /// In fr, this message translates to:
  /// **'Sessions à valider'**
  String get sessionsToValidate;

  /// No description provided for @errorPrefix.
  ///
  /// In fr, this message translates to:
  /// **'Erreur : {message}'**
  String errorPrefix(String message);

  /// No description provided for @saDashboardTitle.
  ///
  /// In fr, this message translates to:
  /// **'Pointage GPS — tableau de bord équipe'**
  String get saDashboardTitle;

  /// No description provided for @saDetected.
  ///
  /// In fr, this message translates to:
  /// **'Détectées'**
  String get saDetected;

  /// No description provided for @saApproved.
  ///
  /// In fr, this message translates to:
  /// **'Approuvées'**
  String get saApproved;

  /// No description provided for @saRejected.
  ///
  /// In fr, this message translates to:
  /// **'Rejetées'**
  String get saRejected;

  /// No description provided for @saRecentSessions.
  ///
  /// In fr, this message translates to:
  /// **'Sessions récentes'**
  String get saRecentSessions;

  /// No description provided for @saForced.
  ///
  /// In fr, this message translates to:
  /// **'Imposé'**
  String get saForced;

  /// No description provided for @saPresenceInProgress.
  ///
  /// In fr, this message translates to:
  /// **'Présence en cours depuis {time}'**
  String saPresenceInProgress(String time);

  /// No description provided for @saGpsZoneNotConfigured.
  ///
  /// In fr, this message translates to:
  /// **'La zone GPS de votre entreprise n\'est pas encore configurée.'**
  String get saGpsZoneNotConfigured;

  /// No description provided for @saDisableAutoGps.
  ///
  /// In fr, this message translates to:
  /// **'Désactiver le GPS automatique'**
  String get saDisableAutoGps;

  /// No description provided for @saStatusApproved.
  ///
  /// In fr, this message translates to:
  /// **'Approuvée'**
  String get saStatusApproved;

  /// No description provided for @saStatusDetected.
  ///
  /// In fr, this message translates to:
  /// **'Détectée'**
  String get saStatusDetected;

  /// No description provided for @saStatusRejected.
  ///
  /// In fr, this message translates to:
  /// **'Rejetée'**
  String get saStatusRejected;

  /// No description provided for @saStatusCancelled.
  ///
  /// In fr, this message translates to:
  /// **'Annulée'**
  String get saStatusCancelled;

  /// No description provided for @saStatusPending.
  ///
  /// In fr, this message translates to:
  /// **'En validation'**
  String get saStatusPending;

  /// No description provided for @saEnableAutoGps.
  ///
  /// In fr, this message translates to:
  /// **'Activer le GPS automatique'**
  String get saEnableAutoGps;

  /// No description provided for @attendanceOvertime.
  ///
  /// In fr, this message translates to:
  /// **'Heures supplémentaires'**
  String get attendanceOvertime;

  /// No description provided for @approvalsUpToDate.
  ///
  /// In fr, this message translates to:
  /// **'Tout est à jour'**
  String get approvalsUpToDate;

  /// No description provided for @approvalsEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune approbation en attente.'**
  String get approvalsEmpty;

  /// No description provided for @saConfigLoadError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger la configuration.\n{error}'**
  String saConfigLoadError(String error);

  /// No description provided for @ampAutoDetectDesc.
  ///
  /// In fr, this message translates to:
  /// **'Votre présence est détectée automatiquement dès que vous entrez dans la zone de l\'entreprise. Aucune action requise de votre part.'**
  String get ampAutoDetectDesc;

  /// No description provided for @ampRecommended.
  ///
  /// In fr, this message translates to:
  /// **'Recommandé'**
  String get ampRecommended;

  /// No description provided for @ampQrScanDesc.
  ///
  /// In fr, this message translates to:
  /// **'Scannez le QR Code affiché à l\'entrée de l\'entreprise pour pointer votre arrivée et votre départ.'**
  String get ampQrScanDesc;

  /// No description provided for @ampManualDesc.
  ///
  /// In fr, this message translates to:
  /// **'Pointez manuellement en appuyant sur les boutons Arrivée et Départ dans l\'écran de présence.'**
  String get ampManualDesc;

  /// No description provided for @ampSaveError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de sauvegarder votre préférence. Vérifiez votre connexion.'**
  String get ampSaveError;

  /// No description provided for @ampTitle.
  ///
  /// In fr, this message translates to:
  /// **'Choisissez comment vous souhaitez pointer votre présence chaque jour.'**
  String get ampTitle;

  /// No description provided for @ampModeTitle.
  ///
  /// In fr, this message translates to:
  /// **'Mode de pointage'**
  String get ampModeTitle;

  /// No description provided for @back.
  ///
  /// In fr, this message translates to:
  /// **'Retour'**
  String get back;
  String get cabinetScreenAddDocument;
  String get cabinetScreenAddDocumentSubtitle;
  String get cabinetScreenCancel;
  String get cabinetScreenCreate;
  String get cabinetScreenCreateShareLink;
  String get cabinetScreenDelete;
  String cabinetScreenDeleteBody(Object name);
  String get cabinetScreenDeleteTitle;

  String cabinetScreenDocumentsCount(num count);
  String get cabinetScreenDocumentAdded;
  String get cabinetScreenDocuments;
  String get cabinetScreenEmailHint;
  String get cabinetScreenEmptyDescription;
  String get cabinetScreenEmptyTitle;
  String get cabinetScreenFolderNameHint;
  String get cabinetScreenFolders;
  String cabinetScreenLinkCopied(Object url);
  String get cabinetScreenNewFolder;
  String get cabinetScreenSend;
  String get cabinetScreenShareByEmail;
  String cabinetScreenShareSent(Object email);
  String cabinetScreenShareTitle(Object name);
  String get cabinetScreenTitleRoot;
  String get cabinetScreenUploadFailed;
  String get cabinetScreenUploading;

  /// No description provided for @notificationsMarkAllReadError.
  String get notificationsMarkAllReadError;

  /// No description provided for @notificationsMarkReadError.
  String get notificationsMarkReadError;

  /// No description provided for @notificationsDeleteError.
  String get notificationsDeleteError;

  /// No description provided for @notificationsDeleted.
  String get notificationsDeleted;

  /// No description provided for @attendanceFutureTimeError.
  String get attendanceFutureTimeError;

  /// No description provided for @refresh.
  ///
  /// In fr, this message translates to:
  /// **'Actualiser'**
  String get refresh;

  /// No description provided for @saSessionsLoadError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger les sessions GPS. Vérifiez votre connexion.'**
  String get saSessionsLoadError;

  /// No description provided for @saStartMonitoringError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de démarrer la surveillance GPS. Vérifiez les permissions de localisation et réessayez.'**
  String get saStartMonitoringError;

  /// No description provided for @shellTeam.
  ///
  /// In fr, this message translates to:
  /// **'Équipe'**
  String get shellTeam;

  /// No description provided for @shellSettings.
  ///
  /// In fr, this message translates to:
  /// **'Réglages'**
  String get shellSettings;

  /// No description provided for @homeCompleteOnboarding.
  ///
  /// In fr, this message translates to:
  /// **'Compléter mon onboarding'**
  String get homeCompleteOnboarding;

  /// No description provided for @homeOnboardingHint.
  ///
  /// In fr, this message translates to:
  /// **'Configurez votre espace en quelques étapes.'**
  String get homeOnboardingHint;

  /// No description provided for @welcomeMyTeam.
  ///
  /// In fr, this message translates to:
  /// **'Mon équipe'**
  String get welcomeMyTeam;

  /// No description provided for @welcomePresences.
  ///
  /// In fr, this message translates to:
  /// **'Présences'**
  String get welcomePresences;

  /// No description provided for @welcomeTasks.
  ///
  /// In fr, this message translates to:
  /// **'Tâches'**
  String get welcomeTasks;

  /// No description provided for @welcomeLeaves.
  ///
  /// In fr, this message translates to:
  /// **'Congés'**
  String get welcomeLeaves;

  /// No description provided for @monthlySummaryLoading.
  ///
  /// In fr, this message translates to:
  /// **'Chargement du résumé mensuel...'**
  String get monthlySummaryLoading;

  /// No description provided for @orgChartEmpty.
  ///
  /// In fr, this message translates to:
  /// **'L\'organigramme sera disponible une fois les employés configurés.'**
  String get orgChartEmpty;

  /// No description provided for @orgChartCollapse.
  ///
  /// In fr, this message translates to:
  /// **'Réduire'**
  String get orgChartCollapse;

  /// No description provided for @orgChartExpand.
  ///
  /// In fr, this message translates to:
  /// **'Développer'**
  String get orgChartExpand;

  /// No description provided for @errorUnexpected.
  ///
  /// In fr, this message translates to:
  /// **'Une erreur est survenue'**
  String get errorUnexpected;

  /// No description provided for @approvalApproved.
  ///
  /// In fr, this message translates to:
  /// **'Demande approuvée'**
  String get approvalApproved;

  /// No description provided for @approvalRejected.
  ///
  /// In fr, this message translates to:
  /// **'Demande refusée'**
  String get approvalRejected;

  /// No description provided for @saPermissionDenied.
  ///
  /// In fr, this message translates to:
  /// **'Autorisation de localisation refusée. Activez le GPS dans les réglages pour activer la surveillance.'**
  String get saPermissionDenied;
  String get absencesApprove;

  String get absencesApproveBody;

  String get absencesApproveTitle;

  String get absencesApprovedSnack;

  String get absencesAttachProof;

  String get absencesBalancesLoading;

  String get absencesCancel;

  String get absencesCancelBody;

  String get absencesCancelRequest;

  String get absencesCancelTitle;

  String get absencesCancelledSnack;

  String get absencesCompanyLabel;

  String get absencesCurrentCompany;

  String get absencesDateMissing;

  String get absencesDaysAvailable;
  String get absencesDaysShort;

  String get absencesEmployeeLabel;

  String get absencesEmptyHint;

  String get absencesEmptyTitle;

  String get absencesEnd;

  String get absencesFailure;

  String get absencesKeep;

  String get absencesLoading;

  String get absencesNewAbsence;

  String get absencesNewAbsenceHint;

  String get absencesNoTypeAvailable;

  String get absencesProofAttached;

  String get absencesProofDownloaded;

  String get absencesReason;

  String get absencesReasonLabel;

  String get absencesReasonMissing;

  String get absencesReasonNotProvided;

  String get absencesReasonhint;

  String get absencesReasonrequired;

  String get absencesReject;

  String get absencesRejectHelper;

  String get absencesRejectTitle;

  String get absencesRejectedSnack;

  String get absencesRequest;

  String get absencesRequestLabel;

  String get absencesRequesterLabel;

  String get absencesStart;

  String get absencesStatusApproved;

  String get absencesStatusCancelled;

  String get absencesStatusPending;

  String get absencesStatusRejected;

  String get absencesSubmitToHr;

  String get absencesSubmittedSnack;

  String get absencesSubtitle;

  String get absencesTitle;

  String get absencesType;

  String get absencesTypeFallback;

  String get absencesTypeRequired;

  String get absencesViewProof;

  String get apiLoginBackendError;

  String get apiLoginInvalidJson;

  String get apiLoginNetworkError;

  String get apiLoginTimeout;

  String get billingCancelSubscriptionConfirm;

  String get billingCheckoutFailed;

  String get billingCheckoutSandboxMessage;

  String get billingCheckoutUnavailable;

  String get billingNoActivePeriod;

  String get billingNoActiveSubscription;

  String get billingPeriodLabel;

  String get contractsAllstatuses;

  String get contractsListSubtitle;

  String get contractsSearchplaceholder;

  String get dashboardModulesactivesentence;

  String get dashboardSearchplaceholder;

  String get dashboardYourcompany;

  String get marketingPostcontentplaceholder;

  String get marketingSocialexampleplaceholder;

  String get trainingDurationplaceholder;

  String get trainingMaxparticipantsplaceholder;

  String get trainingOnline;

  String get trainingTitleplaceholder;


  /// No description provided for @attendanceFutureTimeError.
  String get attendanceFutureTimeError;

  /// No description provided for @notificationsMarkAllReadError.
  String get notificationsMarkAllReadError;

  /// No description provided for @notificationsMarkReadError.
  String get notificationsMarkReadError;

  /// No description provided for @notificationsDeleteError.
  String get notificationsDeleteError;

  /// No description provided for @notificationsDeleted.
  String get notificationsDeleted;

  /// No description provided for @settingsJourneyLoadError.
  String get settingsJourneyLoadError;

  /// No description provided for @settingsStatsLoadError.
  String get settingsStatsLoadError;

  /// No description provided for @emptyAbsences.
  String get emptyAbsences;

  /// No description provided for @cancelRequest.
  String get cancelRequest;

  /// No description provided for @emptySessions.
  String get emptySessions;

  /// No description provided for @emptyHistory.
  String get emptyHistory;

  /// No description provided for @emptyPayslips.
  String get emptyPayslips;

  /// No description provided for @emptyAdvances.
  String get emptyAdvances;

  /// No description provided for @noReason.
  String get noReason;

  /// No description provided for @confirmReceipt.
  String get confirmReceipt;

  /// No description provided for @saveProfile.
  String get saveProfile;

  /// No description provided for @savingProfile.
  String get savingProfile;

  /// No description provided for @loadError.
  String get loadError;

  /// No description provided for @noData.
  String get noData;

  /// No description provided for @noTasksToday.
  String get noTasksToday;

  /// No description provided for @salaryAdvanceListTitle.
  String get salaryAdvanceListTitle;

  /// No description provided for @salaryAdvanceListSubtitle.
  String get salaryAdvanceListSubtitle;

  /// No description provided for @salaryAdvanceRequest.
  String get salaryAdvanceRequest;

  /// No description provided for @salaryAdvancesEmpty.
  String get salaryAdvancesEmpty;

  /// No description provided for @salaryAdvancesEmptyHint.
  String get salaryAdvancesEmptyHint;

  /// No description provided for @salaryAdvanceNoReason.
  String get salaryAdvanceNoReason;

  /// No description provided for @salaryAdvancesLoading.
  String get salaryAdvancesLoading;

  /// No description provided for @salaryAdvancePaymentDeclared.
  String get salaryAdvancePaymentDeclared;

  /// No description provided for @salaryAdvanceConfirmReceived.
  String get salaryAdvanceConfirmReceived;

  /// No description provided for @salaryAdvanceCancelRequest.
  String get salaryAdvanceCancelRequest;

  /// No description provided for @salaryAdvanceViewProof.
  String get salaryAdvanceViewProof;

  /// No description provided for @salaryAdvanceProofDownloaded.
  String salaryAdvanceProofDownloaded(Object path);

  /// No description provided for @salaryAdvanceError.
  String salaryAdvanceError(Object error);

  /// No description provided for @salaryAdvanceCancelTitle.
  String get salaryAdvanceCancelTitle;

  /// No description provided for @salaryAdvanceCancelBody.
  String get salaryAdvanceCancelBody;

  /// No description provided for @salaryAdvanceKeep.
  String get salaryAdvanceKeep;

  /// No description provided for @salaryAdvanceCancelAction.
  String get salaryAdvanceCancelAction;

  /// No description provided for @salaryAdvanceCancelled.
  String get salaryAdvanceCancelled;

  /// No description provided for @salaryAdvanceMonths.
  String salaryAdvanceMonths(Object reason, Object months);

  /// No description provided for @salaryAdvanceSemantics.
  String salaryAdvanceSemantics(Object amount, Object reason, Object status);

  /// No description provided for @salaryStatusValidated.
  String get salaryStatusValidated;

  /// No description provided for @salaryStatusToConfirm.
  String get salaryStatusToConfirm;

  /// No description provided for @salaryStatusReceived.
  String get salaryStatusReceived;

  /// No description provided for @salaryStatusActive.
  String get salaryStatusActive;

  /// No description provided for @salaryStatusApproved.
  String get salaryStatusApproved;

  /// No description provided for @salaryStatusPending.
  String get salaryStatusPending;

  /// No description provided for @salaryStatusRejected.
  String get salaryStatusRejected;

  /// No description provided for @salaryStatusCancelled.
  String get salaryStatusCancelled;

  /// No description provided for @salaryAdvanceConfirmReceivedTitle.
  String get salaryAdvanceConfirmReceivedTitle;

  /// No description provided for @salaryAdvanceConfirmReceivedBody.
  String get salaryAdvanceConfirmReceivedBody;

  /// No description provided for @salaryAdvanceConfirmAction.
  String get salaryAdvanceConfirmAction;

  /// No description provided for @salaryAdvanceRequestTitle.
  String get salaryAdvanceRequestTitle;

  /// No description provided for @salaryAdvanceAttachmentLabel.
  String get salaryAdvanceAttachmentLabel;

  /// No description provided for @salaryAdvanceAttachHint.
  String get salaryAdvanceAttachHint;

  /// No description provided for @salaryAdvanceSubmitted.
  String get salaryAdvanceSubmitted;

  /// No description provided for @absencesListTitle.
  String get absencesListTitle;

  /// No description provided for @absencesListSubtitle.
  String get absencesListSubtitle;

  /// No description provided for @absenceRequest.
  String get absenceRequest;

  /// No description provided for @absencesEmpty.
  String get absencesEmpty;

  /// No description provided for @absenceLabel.
  String get absenceLabel;

  /// No description provided for @absencesDaysCount.
  String absencesDaysCount(Object date, Object days);

  /// No description provided for @absenceViewProof.
  String get absenceViewProof;

  /// No description provided for @absenceCancelRequest.
  String get absenceCancelRequest;

  /// No description provided for @absenceProofDownloaded.
  String absenceProofDownloaded(Object path);

  /// No description provided for @absenceCancelTitle.
  String get absenceCancelTitle;

  /// No description provided for @absenceCancelBody.
  String get absenceCancelBody;

  /// No description provided for @absenceCancelled.
  String get absenceCancelled;

  /// No description provided for @absenceNewTitle.
  String get absenceNewTitle;

  /// No description provided for @absenceNewHint.
  String get absenceNewHint;

  /// No description provided for @absenceNoType.
  String get absenceNoType;

  /// No description provided for @attendanceRoleEmployee.
  String get attendanceRoleEmployee;

  /// No description provided for @attendanceWeekUnavailable.
  String get attendanceWeekUnavailable;

  /// No description provided for @attendanceWorkTypeTitle.
  String get attendanceWorkTypeTitle;

  /// No description provided for @attendanceBreakTitle.
  String get attendanceBreakTitle;

  /// No description provided for @attendanceBreakHint.
  String get attendanceBreakHint;

  /// No description provided for @attendanceBreakLoading.
  String get attendanceBreakLoading;

  /// No description provided for @attendanceBreakSuccess.
  String get attendanceBreakSuccess;

  /// No description provided for @attendanceBreakFailure.
  String get attendanceBreakFailure;

  /// No description provided for @attendanceResumeTitle.
  String get attendanceResumeTitle;

  /// No description provided for @attendanceResumeHint.
  String get attendanceResumeHint;

  /// No description provided for @attendanceResumeLoading.
  String get attendanceResumeLoading;

  /// No description provided for @attendanceResumeSuccess.
  String get attendanceResumeSuccess;

  /// No description provided for @attendanceResumeFailure.
  String get attendanceResumeFailure;

  /// No description provided for @attendanceOvertimeTitle.
  String get attendanceOvertimeTitle;

  /// No description provided for @attendanceOvertimeHint.
  String get attendanceOvertimeHint;

  /// No description provided for @attendanceOvertimeLoading.
  String get attendanceOvertimeLoading;

  /// No description provided for @attendanceOvertimeSuccess.
  String get attendanceOvertimeSuccess;

  /// No description provided for @attendanceOvertimeFailure.
  String get attendanceOvertimeFailure;

  /// No description provided for @attendanceMissionTitle.
  String get attendanceMissionTitle;

  /// No description provided for @attendanceMissionHint.
  String get attendanceMissionHint;

  /// No description provided for @attendanceMissionLoading.
  String get attendanceMissionLoading;

  /// No description provided for @attendanceMissionSuccess.
  String get attendanceMissionSuccess;

  /// No description provided for @attendanceMissionFailure.
  String get attendanceMissionFailure;

  /// No description provided for @attendanceTravelTitle.
  String get attendanceTravelTitle;

  /// No description provided for @attendanceTravelHint.
  String get attendanceTravelHint;

  /// No description provided for @attendanceTravelLoading.
  String get attendanceTravelLoading;

  /// No description provided for @attendanceTravelSuccess.
  String get attendanceTravelSuccess;

  /// No description provided for @attendanceTravelFailure.
  String get attendanceTravelFailure;

  /// No description provided for @attendanceTasksTitle.
  String get attendanceTasksTitle;

  /// No description provided for @attendanceHistoryTitle.
  String get attendanceHistoryTitle;

  /// No description provided for @attendancePreferencesTitle.
  String get attendancePreferencesTitle;

  /// No description provided for @attendanceSettingsTitle.
  String get attendanceSettingsTitle;

  /// No description provided for @attendanceSyncTitle.
  String get attendanceSyncTitle;

  /// No description provided for @attendanceSaving.
  String get attendanceSaving;

  /// No description provided for @attendancePressToCheckout.
  String get attendancePressToCheckout;

  /// No description provided for @attendancePressToCheckin.
  String get attendancePressToCheckin;

  /// No description provided for @attendanceOvertimeShort.
  String get attendanceOvertimeShort;

  /// No description provided for @attendancePauseLabel.
  String get attendancePauseLabel;

  /// No description provided for @attendanceTrainingLabel.
  String get attendanceTrainingLabel;

  /// No description provided for @attendanceOtherLabel.
  String get attendanceOtherLabel;

  /// No description provided for @settingsEdgeSaved.
  String get settingsEdgeSaved;

  /// No description provided for @settingsAccountTitle.
  String get settingsAccountTitle;

  /// No description provided for @settingsAccountSubtitle.
  String get settingsAccountSubtitle;

  /// No description provided for @settingsEmployeeProfileHint.
  String get settingsEmployeeProfileHint;

  /// No description provided for @settingsSaving.
  String get settingsSaving;

  /// No description provided for @settingsSaveProfile.
  String get settingsSaveProfile;

  /// No description provided for @settingsKioskBiometricTitle.
  String get settingsKioskBiometricTitle;

  /// No description provided for @settingsNotificationsTitle.
  String get settingsNotificationsTitle;

  /// No description provided for @settingsAccountPortableHint.
  String get settingsAccountPortableHint;

  /// No description provided for @settingsNoJourney.
  String get settingsNoJourney;

  /// No description provided for @settingsNoCompanyQr.
  String get settingsNoCompanyQr;

  /// No description provided for @settingsLanguageTitle.
  String get settingsLanguageTitle;

  /// No description provided for @settingsPreferredLanguage.
  String get settingsPreferredLanguage;

  /// No description provided for @settingsSecurityTitle.
  String get settingsSecurityTitle;

  /// No description provided for @settingsCurrentPassword.
  String get settingsCurrentPassword;

  /// No description provided for @settingsSaveEnrollment.
  String get settingsSaveEnrollment;

  /// No description provided for @settingsSave.
  String get settingsSave;

  /// No description provided for @settingsLogout.
  String get settingsLogout;

  /// No description provided for @approvalsTitle.
  String get approvalsTitle;

  /// No description provided for @approvalsRejectReasonLabel.
  String get approvalsRejectReasonLabel;

  /// No description provided for @approvalsRejectReasonHint.
  String get approvalsRejectReasonHint;

  /// No description provided for @approvalsLoading.
  String get approvalsLoading;

  /// No description provided for @actionApprove.
  String get actionApprove;

  /// No description provided for @actionReject.
  String get actionReject;

  /// No description provided for @actionCancel.
  String get actionCancel;


  /// No description provided for @attendanceThisWeek.
  String get attendanceThisWeek;
  /// No description provided for @attendanceToday.
  String get attendanceToday;
  /// No description provided for @attendanceCheckinLabel.
  String get attendanceCheckinLabel;
  /// No description provided for @attendanceCheckoutLabel.
  String get attendanceCheckoutLabel;
  /// No description provided for @attendanceDailyEstimate.
  String get attendanceDailyEstimate;
  /// No description provided for @attendanceWeekHours.
  String get attendanceWeekHours;
  /// No description provided for @attendanceWeekEarnings.
  String get attendanceWeekEarnings;
  /// No description provided for @attendanceWeekLate.
  String get attendanceWeekLate;
  /// No description provided for @attendanceMenuEdit.
  String get attendanceMenuEdit;
  /// No description provided for @attendanceMenuMonthly.
  String get attendanceMenuMonthly;
  /// No description provided for @attendanceMenuProfile.
  String get attendanceMenuProfile;
  /// No description provided for @attendanceCheckoutSending.
  String get attendanceCheckoutSending;
  /// No description provided for @attendanceCheckinSending.
  String get attendanceCheckinSending;
  /// No description provided for @attendanceCheckoutSuccess.
  String get attendanceCheckoutSuccess;
  /// No description provided for @attendanceCheckoutFailure.
  String get attendanceCheckoutFailure;
  /// No description provided for @attendanceCheckinSuccess.
  String get attendanceCheckinSuccess;
  /// No description provided for @attendanceCheckinFailure.
  String get attendanceCheckinFailure;
  /// No description provided for @attendanceFingerprintEnabled.
  String get attendanceFingerprintEnabled;
  /// No description provided for @attendanceFingerprintEnable.
  String get attendanceFingerprintEnable;
  /// No description provided for @attendanceAbsent.
  String get attendanceAbsent;
  /// No description provided for @attendanceStatusPointer.
  String get attendanceStatusPointer;
  /// No description provided for @attendanceStatusInProgress.
  String get attendanceStatusInProgress;
  /// No description provided for @attendanceStatusLate.
  String get attendanceStatusLate;
  /// No description provided for @attendanceStatusComplete.
  String get attendanceStatusComplete;
  /// No description provided for @attendanceCorrectionTitle.
  String get attendanceCorrectionTitle;
  /// No description provided for @attendanceCorrectionDirectHint.
  String get attendanceCorrectionDirectHint;
  /// No description provided for @attendanceCorrectionRequestHint.
  String get attendanceCorrectionRequestHint;
  /// No description provided for @attendanceCorrectionCheckinLabel.
  String get attendanceCorrectionCheckinLabel;
  /// No description provided for @attendanceCorrectionCheckoutLabel.
  String get attendanceCorrectionCheckoutLabel;
  /// No description provided for @attendanceCorrectionReasonHint.
  String get attendanceCorrectionReasonHint;
  /// No description provided for @attendanceCorrectionReasonRequired.
  String get attendanceCorrectionReasonRequired;
  /// No description provided for @attendanceCorrectionNoLogWarning.
  String get attendanceCorrectionNoLogWarning;
  /// No description provided for @attendanceCorrectionSubmitDirect.
  String get attendanceCorrectionSubmitDirect;
  /// No description provided for @attendanceCorrectionSubmitRequest.
  String get attendanceCorrectionSubmitRequest;
  /// No description provided for @attendanceRoleEmployee2.
  String get attendanceRoleEmployee2;
  /// No description provided for @attendanceRolePrincipal.
  String get attendanceRolePrincipal;
  /// No description provided for @attendanceRoleHr.
  String get attendanceRoleHr;
  /// No description provided for @attendanceRoleFinance.
  String get attendanceRoleFinance;
  /// No description provided for @attendanceRoleManager.
  String get attendanceRoleManager;
  /// No description provided for @attendanceNone.
  String get attendanceNone;


  /// No description provided for @settingsMobileAccess.
  String get settingsMobileAccess;
  /// No description provided for @settingsManagerProfileHint.
  String get settingsManagerProfileHint;
  /// No description provided for @settingsTeamDrive.
  String get settingsTeamDrive;
  /// No description provided for @settingsTeamDriveHint.
  String get settingsTeamDriveHint;
  /// No description provided for @settingsSessionTitle.
  String get settingsSessionTitle;
  /// No description provided for @settingsSessionSubtitle.
  String get settingsSessionSubtitle;
  /// No description provided for @settingsOverview.
  String get settingsOverview;
  /// No description provided for @settingsManagerAccountHint.
  String get settingsManagerAccountHint;
  /// No description provided for @settingsMyProfile.
  String get settingsMyProfile;
  /// No description provided for @settingsFirstName.
  String get settingsFirstName;
  /// No description provided for @settingsLastNameLabel.
  String get settingsLastNameLabel;
  /// No description provided for @settingsEmailLabel.
  String get settingsEmailLabel;
  /// No description provided for @settingsEmailRequired.
  String get settingsEmailRequired;
  /// No description provided for @settingsEmailInvalid.
  String get settingsEmailInvalid;
  /// No description provided for @settingsFirstNameRequired.
  String get settingsFirstNameRequired;
  /// No description provided for @settingsLastNameRequired.
  String get settingsLastNameRequired;
  /// No description provided for @settingsPersonalContacts.
  String get settingsPersonalContacts;
  /// No description provided for @settingsPersonalEmail.
  String get settingsPersonalEmail;
  /// No description provided for @settingsRecoveryEmail.
  String get settingsRecoveryEmail;
  /// No description provided for @settingsPersonalPhone.
  String get settingsPersonalPhone;
  /// No description provided for @settingsNewPassword.
  String get settingsNewPassword;
  /// No description provided for @settingsConfirmPassword.
  String get settingsConfirmPassword;
  /// No description provided for @settingsPasswordMinLength.
  String get settingsPasswordMinLength;
  /// No description provided for @settingsPasswordMismatch.
  String get settingsPasswordMismatch;
  /// No description provided for @settingsPasswordChanged.
  String get settingsPasswordChanged;
  /// No description provided for @settingsShareProfile.
  String get settingsShareProfile;
  /// No description provided for @settingsMyQrManager.
  String get settingsMyQrManager;
  /// No description provided for @settingsMyQrEmployee.
  String get settingsMyQrEmployee;
  /// No description provided for @settingsQrManagerHint.
  String get settingsQrManagerHint;
  /// No description provided for @settingsQrCopyToken.
  String get settingsQrCopyToken;
  /// No description provided for @settingsPasteQr.
  String get settingsPasteQr;
  /// No description provided for @settingsJourneyTitle.
  String get settingsJourneyTitle;
  /// No description provided for @settingsJourneyUnknownDate.
  String get settingsJourneyUnknownDate;
  /// No description provided for @settingsJourneyToday.
  String get settingsJourneyToday;
  /// No description provided for @settingsJourneyInProgress.
  String get settingsJourneyInProgress;
  /// No description provided for @settingsJourneyUnknownPosition.
  String get settingsJourneyUnknownPosition;
  /// No description provided for @settingsJourneyUnknownCompany.
  String get settingsJourneyUnknownCompany;
  /// No description provided for @settingsNotificationsSubtitle.
  String get settingsNotificationsSubtitle;
  /// No description provided for @settingsLanguageSubtitle.
  String get settingsLanguageSubtitle;
  /// No description provided for @settingsProfileSaved.
  String get settingsProfileSaved;
  /// No description provided for @settingsBiometricManagerHint.
  String get settingsBiometricManagerHint;
  /// No description provided for @settingsBiometricTerminalHint.
  String get settingsBiometricTerminalHint;
  /// No description provided for @settingsBiometricNote.
  String get settingsBiometricNote;
  /// No description provided for @settingsBiometricDevice.
  String get settingsBiometricDevice;
  /// No description provided for @settingsBiometricFace.
  String get settingsBiometricFace;
  /// No description provided for @settingsBiometricFingerprint.
  String get settingsBiometricFingerprint;
  /// No description provided for @settingsBiometricConsent.
  String get settingsBiometricConsent;
  /// No description provided for @settingsBiometricSaved.
  String get settingsBiometricSaved;
  /// No description provided for @settingsBiometricEnrollmentStatus.
  String get settingsBiometricEnrollmentStatus;
  /// No description provided for @settingsBiometricNone.
  String get settingsBiometricNone;
  /// No description provided for @settingsBiometricPending.
  String get settingsBiometricPending;
  /// No description provided for @settingsBiometricApproved.
  String get settingsBiometricApproved;
  /// No description provided for @settingsBiometricRejected.
  String get settingsBiometricRejected;
  /// No description provided for @settingsPreferredLanguageLabel.
  String get settingsPreferredLanguageLabel;
  /// No description provided for @settingsLanguageSaved.
  String get settingsLanguageSaved;
  /// No description provided for @settingsPortableAccountHint.
  String get settingsPortableAccountHint;


  /// No description provided for @settingsPasswordError.
  String settingsPasswordError(Object error);
  /// No description provided for @settingsProfileError.
  String settingsProfileError(Object error);
  /// No description provided for @settingsBiometricError.
  String settingsBiometricError(Object error);


  /// No description provided for @teamTitle.
  String get teamTitle;
  /// No description provided for @teamSubtitle.
  String get teamSubtitle;
  /// No description provided for @teamManagerRequired.
  String get teamManagerRequired;
  /// No description provided for @teamManagerRequiredHint.
  String get teamManagerRequiredHint;
  /// No description provided for @teamEmployeesTab.
  String get teamEmployeesTab;
  /// No description provided for @teamInvitationsTab.
  String get teamInvitationsTab;
  /// No description provided for @teamAdd.
  String get teamAdd;
  /// No description provided for @teamAddCollaborator.
  String get teamAddCollaborator;
  /// No description provided for @teamAddManualForm.
  String get teamAddManualForm;
  /// No description provided for @teamAddManualHint.
  String get teamAddManualHint;
  /// No description provided for @teamAddFromQr.
  String get teamAddFromQr;
  /// No description provided for @teamAddFromQrHint.
  String get teamAddFromQrHint;
  /// No description provided for @teamLoading.
  String get teamLoading;
  /// No description provided for @teamEmpty.
  String get teamEmpty;
  /// No description provided for @teamEmptyHint.
  String get teamEmptyHint;
  /// No description provided for @teamEmployeeLabel.
  String get teamEmployeeLabel;
  /// No description provided for @teamManagerLabel.
  String get teamManagerLabel;
  /// No description provided for @teamViewProfile.
  String get teamViewProfile;
  /// No description provided for @teamViewProfileHint.
  String get teamViewProfileHint;
  /// No description provided for @teamEditProfile.
  String get teamEditProfile;
  /// No description provided for @teamEditProfileHint.
  String get teamEditProfileHint;
  /// No description provided for @teamViewAttendance.
  String get teamViewAttendance;
  /// No description provided for @teamViewAttendanceHint.
  String get teamViewAttendanceHint;
  /// No description provided for @teamViewTasks.
  String get teamViewTasks;
  /// No description provided for @teamViewTasksHint.
  String get teamViewTasksHint;
  /// No description provided for @teamMakeHr.
  String get teamMakeHr;
  /// No description provided for @teamRevokeHr.
  String get teamRevokeHr;
  /// No description provided for @teamMakeHrHint.
  String get teamMakeHrHint;
  /// No description provided for @teamRevokeHrHint.
  String get teamRevokeHrHint;
  /// No description provided for @teamArchive.
  String get teamArchive;
  /// No description provided for @teamMakeHrConfirmTitle.
  String get teamMakeHrConfirmTitle;
  /// No description provided for @teamRevokeHrConfirmTitle.
  String get teamRevokeHrConfirmTitle;
  /// No description provided for @teamArchiveConfirmTitle.
  String get teamArchiveConfirmTitle;
  /// No description provided for @teamConfirmCancel.
  String get teamConfirmCancel;
  /// No description provided for @teamMakeHrConfirmAction.
  String get teamMakeHrConfirmAction;
  /// No description provided for @teamRevokeHrConfirmAction.
  String get teamRevokeHrConfirmAction;
  /// No description provided for @teamArchiveConfirmAction.
  String get teamArchiveConfirmAction;
  /// No description provided for @teamMakeHrSuccess.
  String get teamMakeHrSuccess;
  /// No description provided for @teamRevokeHrSuccess.
  String get teamRevokeHrSuccess;
  /// No description provided for @teamArchiveSuccess.
  String get teamArchiveSuccess;

  /// No description provided for @teamActionError.
  String teamActionError(Object error);
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
