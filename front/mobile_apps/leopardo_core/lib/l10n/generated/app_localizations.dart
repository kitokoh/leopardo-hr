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
  /// **'Votre journée commence ici, pas dans un back-office.'**
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

  /// No description provided for @welcomeLeaves.
  ///
  /// In fr, this message translates to:
  /// **'Congés'**
  String get welcomeLeaves;

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
  /// **'Créer un compte personnel'**
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
  /// **'Français (France)'**
  String get commonLanguageVariantsFrFr;

  /// No description provided for @commonLanguageVariantsFrBe.
  ///
  /// In fr, this message translates to:
  /// **'Français (Belgique)'**
  String get commonLanguageVariantsFrBe;

  /// No description provided for @commonLanguageVariantsFrCa.
  ///
  /// In fr, this message translates to:
  /// **'Français (Canada)'**
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
  /// **'Ouvrir les paramètres du compte'**
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
  /// **'Utilisateur créé avec succès'**
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
  /// **'Vérifier système et sécurité'**
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

  /// No description provided for @dashboardSearchplaceholder.
  ///
  /// In fr, this message translates to:
  /// **'Rechercher...'**
  String get dashboardSearchplaceholder;

  /// No description provided for @dashboardModulesactivesentence.
  ///
  /// In fr, this message translates to:
  /// **'{active} modules actifs, {locked} a activer selon votre plan.'**
  String dashboardModulesactivesentence(Object active, Object locked);

  /// No description provided for @dashboardYourcompany.
  ///
  /// In fr, this message translates to:
  /// **'Votre entreprise'**
  String get dashboardYourcompany;

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

  /// No description provided for @marketingSocialexampleplaceholder.
  ///
  /// In fr, this message translates to:
  /// **'Ex: Leopardo RH — Reseaux sociaux'**
  String get marketingSocialexampleplaceholder;

  /// No description provided for @marketingPostcontentplaceholder.
  ///
  /// In fr, this message translates to:
  /// **'Contenu de la publication...'**
  String get marketingPostcontentplaceholder;

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

  /// No description provided for @attendanceAbsent.
  ///
  /// In fr, this message translates to:
  /// **'absent'**
  String get attendanceAbsent;

  /// No description provided for @attendanceDaySummary.
  ///
  /// In fr, this message translates to:
  /// **'Journée du {date}, statut {status}, {range}, {hours}.'**
  String attendanceDaySummary(
      Object date, Object hours, Object range, Object status);

  /// No description provided for @attendanceDayToday.
  ///
  /// In fr, this message translates to:
  /// **'Aujourd\'hui'**
  String get attendanceDayToday;

  /// No description provided for @attendanceDayYesterday.
  ///
  /// In fr, this message translates to:
  /// **'Hier'**
  String get attendanceDayYesterday;

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

  /// No description provided for @attendanceInProgress.
  ///
  /// In fr, this message translates to:
  /// **'en cours'**
  String get attendanceInProgress;

  /// No description provided for @attendanceLate.
  ///
  /// In fr, this message translates to:
  /// **'en retard'**
  String get attendanceLate;

  /// No description provided for @attendanceNoClock.
  ///
  /// In fr, this message translates to:
  /// **'pas de pointage'**
  String get attendanceNoClock;

  /// No description provided for @attendanceOnTime.
  ///
  /// In fr, this message translates to:
  /// **'à l\'heure'**
  String get attendanceOnTime;

  /// No description provided for @attendanceOvertime.
  ///
  /// In fr, this message translates to:
  /// **'Heures supplémentaires'**
  String get attendanceOvertime;

  /// No description provided for @attendanceTimeRange.
  ///
  /// In fr, this message translates to:
  /// **'de {from} à {to}'**
  String attendanceTimeRange(Object from, Object to);

  /// No description provided for @attendanceFutureTimeError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de saisir une heure future'**
  String get attendanceFutureTimeError;

  /// No description provided for @attendanceBreakFailure.
  ///
  /// In fr, this message translates to:
  /// **'Pause non confirmée'**
  String get attendanceBreakFailure;

  /// No description provided for @attendanceBreakHint.
  ///
  /// In fr, this message translates to:
  /// **'Ferme la session et marque une pause'**
  String get attendanceBreakHint;

  /// No description provided for @attendanceBreakLoading.
  ///
  /// In fr, this message translates to:
  /// **'Envoi de la pause'**
  String get attendanceBreakLoading;

  /// No description provided for @attendanceBreakSuccess.
  ///
  /// In fr, this message translates to:
  /// **'Pause confirmée.'**
  String get attendanceBreakSuccess;

  /// No description provided for @attendanceBreakTitle.
  ///
  /// In fr, this message translates to:
  /// **'Partir en pause'**
  String get attendanceBreakTitle;

  /// No description provided for @attendanceCheckinFailure.
  ///
  /// In fr, this message translates to:
  /// **'Arrivée non confirmée. Réessayez.'**
  String get attendanceCheckinFailure;

  /// No description provided for @attendanceCheckinLabel.
  ///
  /// In fr, this message translates to:
  /// **'Arrivée'**
  String get attendanceCheckinLabel;

  /// No description provided for @attendanceCheckinSending.
  ///
  /// In fr, this message translates to:
  /// **'Envoi de l\'arrivée vers le serveur...'**
  String get attendanceCheckinSending;

  /// No description provided for @attendanceCheckinSuccess.
  ///
  /// In fr, this message translates to:
  /// **'Arrivée confirmée.'**
  String get attendanceCheckinSuccess;

  /// No description provided for @attendanceCheckoutFailure.
  ///
  /// In fr, this message translates to:
  /// **'Départ non confirmé. Réessayez.'**
  String get attendanceCheckoutFailure;

  /// No description provided for @attendanceCheckoutLabel.
  ///
  /// In fr, this message translates to:
  /// **'Départ'**
  String get attendanceCheckoutLabel;

  /// No description provided for @attendanceCheckoutSending.
  ///
  /// In fr, this message translates to:
  /// **'Envoi du départ vers le serveur...'**
  String get attendanceCheckoutSending;

  /// No description provided for @attendanceCheckoutSuccess.
  ///
  /// In fr, this message translates to:
  /// **'Départ confirmé.'**
  String get attendanceCheckoutSuccess;

  /// No description provided for @attendanceCorrectionCheckinLabel.
  ///
  /// In fr, this message translates to:
  /// **'Arrivée réelle *'**
  String get attendanceCorrectionCheckinLabel;

  /// No description provided for @attendanceCorrectionCheckoutLabel.
  ///
  /// In fr, this message translates to:
  /// **'Départ réel'**
  String get attendanceCorrectionCheckoutLabel;

  /// No description provided for @attendanceCorrectionDirectHint.
  ///
  /// In fr, this message translates to:
  /// **'La correction sera appliquée au dossier de pointage.'**
  String get attendanceCorrectionDirectHint;

  /// No description provided for @attendanceCorrectionNoLogWarning.
  ///
  /// In fr, this message translates to:
  /// **'Aucune ligne de pointage existante à modifier pour ce jour.'**
  String get attendanceCorrectionNoLogWarning;

  /// No description provided for @attendanceCorrectionReasonHint.
  ///
  /// In fr, this message translates to:
  /// **'Motif (ex: oubli de pointage à 8h)'**
  String get attendanceCorrectionReasonHint;

  /// No description provided for @attendanceCorrectionReasonRequired.
  ///
  /// In fr, this message translates to:
  /// **'Motif obligatoire'**
  String get attendanceCorrectionReasonRequired;

  /// No description provided for @attendanceCorrectionRequestHint.
  ///
  /// In fr, this message translates to:
  /// **'La demande sera transmise au RH pour validation.'**
  String get attendanceCorrectionRequestHint;

  /// No description provided for @attendanceCorrectionSubmitDirect.
  ///
  /// In fr, this message translates to:
  /// **'Modifier'**
  String get attendanceCorrectionSubmitDirect;

  /// No description provided for @attendanceCorrectionSubmitRequest.
  ///
  /// In fr, this message translates to:
  /// **'Demander une modification'**
  String get attendanceCorrectionSubmitRequest;

  /// No description provided for @attendanceCorrectionTitle.
  ///
  /// In fr, this message translates to:
  /// **'Modifier le pointage'**
  String get attendanceCorrectionTitle;

  /// No description provided for @attendanceCorrectionCheckinTime.
  ///
  /// In fr, this message translates to:
  /// **'Heure d\'arrivée réelle'**
  String get attendanceCorrectionCheckinTime;

  /// No description provided for @attendanceCorrectionCheckoutTime.
  ///
  /// In fr, this message translates to:
  /// **'Heure de départ réelle'**
  String get attendanceCorrectionCheckoutTime;

  /// No description provided for @attendanceCorrectionCheckinRequired.
  ///
  /// In fr, this message translates to:
  /// **'Saisir l\'heure d\'arrivée réelle'**
  String get attendanceCorrectionCheckinRequired;

  /// No description provided for @attendanceCorrectionSubmitError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible d\'envoyer la modification pour le moment.'**
  String get attendanceCorrectionSubmitError;

  /// No description provided for @attendanceDailyEstimate.
  ///
  /// In fr, this message translates to:
  /// **'Gain estimé du jour'**
  String get attendanceDailyEstimate;

  /// No description provided for @attendanceFingerprintEnable.
  ///
  /// In fr, this message translates to:
  /// **'Activer l\'empreinte (optionnel)'**
  String get attendanceFingerprintEnable;

  /// No description provided for @attendanceFingerprintEnabled.
  ///
  /// In fr, this message translates to:
  /// **'Empreinte activée (optionnel)'**
  String get attendanceFingerprintEnabled;

  /// No description provided for @attendanceHistoryTitle.
  ///
  /// In fr, this message translates to:
  /// **'Historique'**
  String get attendanceHistoryTitle;

  /// No description provided for @attendanceMenuEdit.
  ///
  /// In fr, this message translates to:
  /// **'Modifier'**
  String get attendanceMenuEdit;

  /// No description provided for @attendanceMenuMonthly.
  ///
  /// In fr, this message translates to:
  /// **'Mon mois complet'**
  String get attendanceMenuMonthly;

  /// No description provided for @attendanceMenuProfile.
  ///
  /// In fr, this message translates to:
  /// **'Mon profil'**
  String get attendanceMenuProfile;

  /// No description provided for @attendanceMissionFailure.
  ///
  /// In fr, this message translates to:
  /// **'Mission non confirmée'**
  String get attendanceMissionFailure;

  /// No description provided for @attendanceMissionHint.
  ///
  /// In fr, this message translates to:
  /// **'Temps de travail hors site habituel'**
  String get attendanceMissionHint;

  /// No description provided for @attendanceMissionLoading.
  ///
  /// In fr, this message translates to:
  /// **'Envoi mission'**
  String get attendanceMissionLoading;

  /// No description provided for @attendanceMissionSuccess.
  ///
  /// In fr, this message translates to:
  /// **'Mission démarrée.'**
  String get attendanceMissionSuccess;

  /// No description provided for @attendanceMissionTitle.
  ///
  /// In fr, this message translates to:
  /// **'Mission'**
  String get attendanceMissionTitle;

  /// No description provided for @attendanceNone.
  ///
  /// In fr, this message translates to:
  /// **'Aucun'**
  String get attendanceNone;

  /// No description provided for @attendanceOtherLabel.
  ///
  /// In fr, this message translates to:
  /// **'Autre'**
  String get attendanceOtherLabel;

  /// No description provided for @attendanceOvertimeFailure.
  ///
  /// In fr, this message translates to:
  /// **'Heures supplémentaires non confirmées'**
  String get attendanceOvertimeFailure;

  /// No description provided for @attendanceOvertimeHint.
  ///
  /// In fr, this message translates to:
  /// **'Démarrer une session d\'heures supp'**
  String get attendanceOvertimeHint;

  /// No description provided for @attendanceOvertimeLoading.
  ///
  /// In fr, this message translates to:
  /// **'Envoi heures supplémentaires'**
  String get attendanceOvertimeLoading;

  /// No description provided for @attendanceOvertimeShort.
  ///
  /// In fr, this message translates to:
  /// **'Heure supp'**
  String get attendanceOvertimeShort;

  /// No description provided for @attendanceOvertimeSuccess.
  ///
  /// In fr, this message translates to:
  /// **'Heures supplémentaires démarrées.'**
  String get attendanceOvertimeSuccess;

  /// No description provided for @attendanceOvertimeTitle.
  ///
  /// In fr, this message translates to:
  /// **'Heures supplémentaires'**
  String get attendanceOvertimeTitle;

  /// No description provided for @attendancePauseLabel.
  ///
  /// In fr, this message translates to:
  /// **'Pause'**
  String get attendancePauseLabel;

  /// No description provided for @attendancePreferencesTitle.
  ///
  /// In fr, this message translates to:
  /// **'Préférences'**
  String get attendancePreferencesTitle;

  /// No description provided for @attendancePressToCheckin.
  ///
  /// In fr, this message translates to:
  /// **'Appuyez pour enregistrer votre arrivée'**
  String get attendancePressToCheckin;

  /// No description provided for @attendancePressToCheckout.
  ///
  /// In fr, this message translates to:
  /// **'Appuyez pour enregistrer votre départ'**
  String get attendancePressToCheckout;

  /// No description provided for @attendanceResumeFailure.
  ///
  /// In fr, this message translates to:
  /// **'Reprise non confirmée'**
  String get attendanceResumeFailure;

  /// No description provided for @attendanceResumeHint.
  ///
  /// In fr, this message translates to:
  /// **'Reprendre après une pause ou une sortie'**
  String get attendanceResumeHint;

  /// No description provided for @attendanceResumeLoading.
  ///
  /// In fr, this message translates to:
  /// **'Envoi reprise'**
  String get attendanceResumeLoading;

  /// No description provided for @attendanceResumeSuccess.
  ///
  /// In fr, this message translates to:
  /// **'Reprise confirmée.'**
  String get attendanceResumeSuccess;

  /// No description provided for @attendanceResumeTitle.
  ///
  /// In fr, this message translates to:
  /// **'Reprise'**
  String get attendanceResumeTitle;

  /// No description provided for @attendanceRoleEmployee.
  ///
  /// In fr, this message translates to:
  /// **'Employé'**
  String get attendanceRoleEmployee;

  /// No description provided for @attendanceRoleEmployee2.
  ///
  /// In fr, this message translates to:
  /// **'Employé'**
  String get attendanceRoleEmployee2;

  /// No description provided for @attendanceRoleFinance.
  ///
  /// In fr, this message translates to:
  /// **'Finance'**
  String get attendanceRoleFinance;

  /// No description provided for @attendanceRoleHr.
  ///
  /// In fr, this message translates to:
  /// **'Responsable RH'**
  String get attendanceRoleHr;

  /// No description provided for @attendanceRoleManager.
  ///
  /// In fr, this message translates to:
  /// **'Manager'**
  String get attendanceRoleManager;

  /// No description provided for @attendanceRolePrincipal.
  ///
  /// In fr, this message translates to:
  /// **'Manager principal'**
  String get attendanceRolePrincipal;

  /// No description provided for @attendanceSaving.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrement en cours...'**
  String get attendanceSaving;

  /// No description provided for @attendanceSettingsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Paramètres'**
  String get attendanceSettingsTitle;

  /// No description provided for @attendanceStatusComplete.
  ///
  /// In fr, this message translates to:
  /// **'Complet'**
  String get attendanceStatusComplete;

  /// No description provided for @attendanceStatusInProgress.
  ///
  /// In fr, this message translates to:
  /// **'En cours'**
  String get attendanceStatusInProgress;

  /// No description provided for @attendanceStatusLate.
  ///
  /// In fr, this message translates to:
  /// **'Retard'**
  String get attendanceStatusLate;

  /// No description provided for @attendanceStatusPointer.
  ///
  /// In fr, this message translates to:
  /// **'À pointer'**
  String get attendanceStatusPointer;

  /// No description provided for @attendanceSyncTitle.
  ///
  /// In fr, this message translates to:
  /// **'Synchronisation'**
  String get attendanceSyncTitle;

  /// No description provided for @attendanceTasksTitle.
  ///
  /// In fr, this message translates to:
  /// **'Tâches du jour'**
  String get attendanceTasksTitle;

  /// No description provided for @attendanceTasksLoading.
  ///
  /// In fr, this message translates to:
  /// **'Chargement des tâches du jour...'**
  String get attendanceTasksLoading;

  /// No description provided for @attendanceTasksUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'Tâches indisponibles'**
  String get attendanceTasksUnavailable;

  /// No description provided for @attendanceTasksUnavailableHint.
  ///
  /// In fr, this message translates to:
  /// **'Le pointage reste utilisable. Réessayez après synchronisation.'**
  String get attendanceTasksUnavailableHint;

  /// No description provided for @attendanceTasksEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune tâche aujourd\'hui'**
  String get attendanceTasksEmpty;

  /// No description provided for @attendanceTasksEmptyHint.
  ///
  /// In fr, this message translates to:
  /// **'Vous pourrez pointer normalement. Les tâches assignées apparaîtront ici.'**
  String get attendanceTasksEmptyHint;

  /// No description provided for @attendanceTasksCloseHint.
  ///
  /// In fr, this message translates to:
  /// **'Clôturez ce qui est réalisé avant votre départ.'**
  String get attendanceTasksCloseHint;

  /// No description provided for @attendanceThisWeek.
  ///
  /// In fr, this message translates to:
  /// **'CETTE SEMAINE'**
  String get attendanceThisWeek;

  /// No description provided for @attendanceToday.
  ///
  /// In fr, this message translates to:
  /// **'AUJOURD\'HUI'**
  String get attendanceToday;

  /// No description provided for @attendanceTrainingLabel.
  ///
  /// In fr, this message translates to:
  /// **'Formation'**
  String get attendanceTrainingLabel;

  /// No description provided for @attendanceTravelFailure.
  ///
  /// In fr, this message translates to:
  /// **'Déplacement non confirmé'**
  String get attendanceTravelFailure;

  /// No description provided for @attendanceTravelHint.
  ///
  /// In fr, this message translates to:
  /// **'Temps de déplacement professionnel'**
  String get attendanceTravelHint;

  /// No description provided for @attendanceTravelLoading.
  ///
  /// In fr, this message translates to:
  /// **'Envoi déplacement'**
  String get attendanceTravelLoading;

  /// No description provided for @attendanceTravelSuccess.
  ///
  /// In fr, this message translates to:
  /// **'Déplacement démarré.'**
  String get attendanceTravelSuccess;

  /// No description provided for @attendanceTravelTitle.
  ///
  /// In fr, this message translates to:
  /// **'Déplacement'**
  String get attendanceTravelTitle;

  /// No description provided for @attendanceWeekEarnings.
  ///
  /// In fr, this message translates to:
  /// **'Gain estimé'**
  String get attendanceWeekEarnings;

  /// No description provided for @attendanceWeekHours.
  ///
  /// In fr, this message translates to:
  /// **'Heures semaine'**
  String get attendanceWeekHours;

  /// No description provided for @attendanceWeekLate.
  ///
  /// In fr, this message translates to:
  /// **'Retard cumulé'**
  String get attendanceWeekLate;

  /// No description provided for @attendanceWeekUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'Semaine indisponible pour l\'instant. Le pointage reste utilisable.'**
  String get attendanceWeekUnavailable;

  /// No description provided for @attendanceWorkTypeTitle.
  ///
  /// In fr, this message translates to:
  /// **'Type de pointage'**
  String get attendanceWorkTypeTitle;

  /// No description provided for @attendanceSessions.
  ///
  /// In fr, this message translates to:
  /// **'Sessions'**
  String get attendanceSessions;

  /// No description provided for @attendanceBreakMinutes.
  ///
  /// In fr, this message translates to:
  /// **'{minutes} min'**
  String attendanceBreakMinutes(Object minutes);

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

  /// No description provided for @pricingPageHeroBadge.
  ///
  /// In fr, this message translates to:
  /// **'Tarification transparente'**
  String get pricingPageHeroBadge;

  /// No description provided for @pricingPageHeroHeadline.
  ///
  /// In fr, this message translates to:
  /// **'Des tarifs pensés pour les équipes terrain'**
  String get pricingPageHeroHeadline;

  /// No description provided for @pricingPageHeroSubheadline.
  ///
  /// In fr, this message translates to:
  /// **'Commencez gratuitement — sans carte bancaire — et passez à un plan payant quand vous êtes prêt.'**
  String get pricingPageHeroSubheadline;

  /// No description provided for @pricingPageHeroPrimary.
  ///
  /// In fr, this message translates to:
  /// **'Commencer gratuitement'**
  String get pricingPageHeroPrimary;

  /// No description provided for @pricingPageHeroSecondary.
  ///
  /// In fr, this message translates to:
  /// **'Parler à un expert'**
  String get pricingPageHeroSecondary;

  /// No description provided for @pricingPagePlansBadge.
  ///
  /// In fr, this message translates to:
  /// **'Nos plans'**
  String get pricingPagePlansBadge;

  /// No description provided for @pricingPagePlansTitle.
  ///
  /// In fr, this message translates to:
  /// **'Un plan pour chaque étape de votre croissance'**
  String get pricingPagePlansTitle;

  /// No description provided for @pricingPagePlansSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Commencez petit, montez en puissance sans changer de plateforme.'**
  String get pricingPagePlansSubtitle;

  /// No description provided for @pricingPagePlansMonthly.
  ///
  /// In fr, this message translates to:
  /// **'Mensuel'**
  String get pricingPagePlansMonthly;

  /// No description provided for @pricingPagePlansAnnual.
  ///
  /// In fr, this message translates to:
  /// **'Annuel'**
  String get pricingPagePlansAnnual;

  /// No description provided for @pricingPagePlansCustomprice.
  ///
  /// In fr, this message translates to:
  /// **'Sur devis'**
  String get pricingPagePlansCustomprice;

  /// No description provided for @pricingPagePlansPeriodmonthly.
  ///
  /// In fr, this message translates to:
  /// **'/mois'**
  String get pricingPagePlansPeriodmonthly;

  /// No description provided for @pricingPagePlansPeriodannual.
  ///
  /// In fr, this message translates to:
  /// **'/mois facturé annuellement'**
  String get pricingPagePlansPeriodannual;

  /// No description provided for @pricingPagePlansTrialnote.
  ///
  /// In fr, this message translates to:
  /// **'Essai gratuit · Aucune CB requise'**
  String get pricingPagePlansTrialnote;

  /// No description provided for @pricingPageCurrencyLabel.
  ///
  /// In fr, this message translates to:
  /// **'Afficher les prix en'**
  String get pricingPageCurrencyLabel;

  /// No description provided for @pricingPageCurrencyApprox.
  ///
  /// In fr, this message translates to:
  /// **'Conversion approximative depuis le prix de référence en EUR ; le tarif contractuel reste fixé en EUR.'**
  String get pricingPageCurrencyApprox;

  /// No description provided for @pricingPageTrustItems0.
  ///
  /// In fr, this message translates to:
  /// **'Plan gratuit sans CB'**
  String get pricingPageTrustItems0;

  /// No description provided for @pricingPageTrustItems1.
  ///
  /// In fr, this message translates to:
  /// **'Support inclus dès le premier jour'**
  String get pricingPageTrustItems1;

  /// No description provided for @pricingPageTrustItems2.
  ///
  /// In fr, this message translates to:
  /// **'Données hébergées en Europe'**
  String get pricingPageTrustItems2;

  /// No description provided for @pricingPageTrustItems3.
  ///
  /// In fr, this message translates to:
  /// **'Résiliation à tout moment'**
  String get pricingPageTrustItems3;

  /// No description provided for @pricingPageComparisonBadge.
  ///
  /// In fr, this message translates to:
  /// **'Comparaison complète'**
  String get pricingPageComparisonBadge;

  /// No description provided for @pricingPageComparisonTitle.
  ///
  /// In fr, this message translates to:
  /// **'Tout ce qui est inclus'**
  String get pricingPageComparisonTitle;

  /// No description provided for @pricingPageComparisonSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'par plan'**
  String get pricingPageComparisonSubtitle;

  /// No description provided for @pricingPageComparisonFeaturecolumn.
  ///
  /// In fr, this message translates to:
  /// **'Fonctionnalité'**
  String get pricingPageComparisonFeaturecolumn;

  /// No description provided for @pricingPageComparisonCategories0Name.
  ///
  /// In fr, this message translates to:
  /// **'Gestion RH'**
  String get pricingPageComparisonCategories0Name;

  /// No description provided for @pricingPageComparisonCategories0Features0Name.
  ///
  /// In fr, this message translates to:
  /// **'Pointage web & mobile'**
  String get pricingPageComparisonCategories0Features0Name;

  /// No description provided for @pricingPageComparisonCategories0Features0Free.
  ///
  /// In fr, this message translates to:
  /// **'Web seulement'**
  String get pricingPageComparisonCategories0Features0Free;

  /// No description provided for @pricingPageComparisonCategories0Features1Name.
  ///
  /// In fr, this message translates to:
  /// **'Absences & congés'**
  String get pricingPageComparisonCategories0Features1Name;

  /// No description provided for @pricingPageComparisonCategories0Features2Name.
  ///
  /// In fr, this message translates to:
  /// **'Calendrier partagé'**
  String get pricingPageComparisonCategories0Features2Name;

  /// No description provided for @pricingPageComparisonCategories0Features3Name.
  ///
  /// In fr, this message translates to:
  /// **'Onboarding guidé'**
  String get pricingPageComparisonCategories0Features3Name;

  /// No description provided for @pricingPageComparisonCategories0Features4Name.
  ///
  /// In fr, this message translates to:
  /// **'Évaluations & performance'**
  String get pricingPageComparisonCategories0Features4Name;

  /// No description provided for @pricingPageComparisonCategories0Features5Name.
  ///
  /// In fr, this message translates to:
  /// **'Organigramme dynamique'**
  String get pricingPageComparisonCategories0Features5Name;

  /// No description provided for @pricingPageComparisonCategories1Name.
  ///
  /// In fr, this message translates to:
  /// **'Paie & finance'**
  String get pricingPageComparisonCategories1Name;

  /// No description provided for @pricingPageComparisonCategories1Features0Name.
  ///
  /// In fr, this message translates to:
  /// **'Calcul automatisé de la paie'**
  String get pricingPageComparisonCategories1Features0Name;

  /// No description provided for @pricingPageComparisonCategories1Features1Name.
  ///
  /// In fr, this message translates to:
  /// **'Bulletins de paie PDF'**
  String get pricingPageComparisonCategories1Features1Name;

  /// No description provided for @pricingPageComparisonCategories1Features2Name.
  ///
  /// In fr, this message translates to:
  /// **'Exports comptables'**
  String get pricingPageComparisonCategories1Features2Name;

  /// No description provided for @pricingPageComparisonCategories1Features3Name.
  ///
  /// In fr, this message translates to:
  /// **'Avances sur salaire'**
  String get pricingPageComparisonCategories1Features3Name;

  /// No description provided for @pricingPageComparisonCategories1Features4Name.
  ///
  /// In fr, this message translates to:
  /// **'Multi-pays & multi-devises'**
  String get pricingPageComparisonCategories1Features4Name;

  /// No description provided for @pricingPageComparisonCategories1Features5Name.
  ///
  /// In fr, this message translates to:
  /// **'Conformité légale avancée'**
  String get pricingPageComparisonCategories1Features5Name;

  /// No description provided for @pricingPageComparisonCategories2Name.
  ///
  /// In fr, this message translates to:
  /// **'Terrain & mobile'**
  String get pricingPageComparisonCategories2Name;

  /// No description provided for @pricingPageComparisonCategories2Features0Name.
  ///
  /// In fr, this message translates to:
  /// **'App mobile Employee'**
  String get pricingPageComparisonCategories2Features0Name;

  /// No description provided for @pricingPageComparisonCategories2Features1Name.
  ///
  /// In fr, this message translates to:
  /// **'App mobile Manager'**
  String get pricingPageComparisonCategories2Features1Name;

  /// No description provided for @pricingPageComparisonCategories2Features2Name.
  ///
  /// In fr, this message translates to:
  /// **'Mode hors-ligne'**
  String get pricingPageComparisonCategories2Features2Name;

  /// No description provided for @pricingPageComparisonCategories2Features3Name.
  ///
  /// In fr, this message translates to:
  /// **'Intégration ZKTeco biométrie'**
  String get pricingPageComparisonCategories2Features3Name;

  /// No description provided for @pricingPageComparisonCategories2Features4Name.
  ///
  /// In fr, this message translates to:
  /// **'Kiosque RH dédié'**
  String get pricingPageComparisonCategories2Features4Name;

  /// No description provided for @pricingPageComparisonCategories2Features5Name.
  ///
  /// In fr, this message translates to:
  /// **'GPS & géofencing'**
  String get pricingPageComparisonCategories2Features5Name;

  /// No description provided for @pricingPageComparisonCategories3Name.
  ///
  /// In fr, this message translates to:
  /// **'Sécurité & intégrations'**
  String get pricingPageComparisonCategories3Name;

  /// No description provided for @pricingPageComparisonCategories3Features0Name.
  ///
  /// In fr, this message translates to:
  /// **'Coffre-fort documentaire'**
  String get pricingPageComparisonCategories3Features0Name;

  /// No description provided for @pricingPageComparisonCategories3Features1Name.
  ///
  /// In fr, this message translates to:
  /// **'API REST & Webhooks'**
  String get pricingPageComparisonCategories3Features1Name;

  /// No description provided for @pricingPageComparisonCategories3Features2Name.
  ///
  /// In fr, this message translates to:
  /// **'SSO SAML / OIDC'**
  String get pricingPageComparisonCategories3Features2Name;

  /// No description provided for @pricingPageComparisonCategories3Features3Name.
  ///
  /// In fr, this message translates to:
  /// **'Audit trail immuable'**
  String get pricingPageComparisonCategories3Features3Name;

  /// No description provided for @pricingPageComparisonCategories3Features4Name.
  ///
  /// In fr, this message translates to:
  /// **'Schéma PostgreSQL isolé'**
  String get pricingPageComparisonCategories3Features4Name;

  /// No description provided for @pricingPageComparisonCategories3Features5Name.
  ///
  /// In fr, this message translates to:
  /// **'SLA dédié & support prioritaire'**
  String get pricingPageComparisonCategories3Features5Name;

  /// No description provided for @pricingPageFaqBadge.
  ///
  /// In fr, this message translates to:
  /// **'FAQ tarifs'**
  String get pricingPageFaqBadge;

  /// No description provided for @pricingPageFaqTitle.
  ///
  /// In fr, this message translates to:
  /// **'Questions fréquentes'**
  String get pricingPageFaqTitle;

  /// No description provided for @pricingPageFaqSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Les points à vérifier avant de démarrer'**
  String get pricingPageFaqSubtitle;

  /// No description provided for @pricingPageFaqAll.
  ///
  /// In fr, this message translates to:
  /// **'Tous'**
  String get pricingPageFaqAll;

  /// No description provided for @pricingPageFaqCategories0.
  ///
  /// In fr, this message translates to:
  /// **'Facturation'**
  String get pricingPageFaqCategories0;

  /// No description provided for @pricingPageFaqCategories1.
  ///
  /// In fr, this message translates to:
  /// **'Essai'**
  String get pricingPageFaqCategories1;

  /// No description provided for @pricingPageFaqCategories2.
  ///
  /// In fr, this message translates to:
  /// **'Support'**
  String get pricingPageFaqCategories2;

  /// No description provided for @pricingPageFaqCategories3.
  ///
  /// In fr, this message translates to:
  /// **'Sécurité'**
  String get pricingPageFaqCategories3;

  /// No description provided for @pricingPageFaqCategories4.
  ///
  /// In fr, this message translates to:
  /// **'Technique'**
  String get pricingPageFaqCategories4;

  /// No description provided for @pricingPageFaqItems0Question.
  ///
  /// In fr, this message translates to:
  /// **'Que comprend le plan Pilot ?'**
  String get pricingPageFaqItems0Question;

  /// No description provided for @pricingPageFaqItems0Answer.
  ///
  /// In fr, this message translates to:
  /// **'Le plan Pilot à 29 €/mois inclut jusqu\'à 30 employés, le pointage web et mobile, les absences et congés, les dossiers employés et les bulletins de paie PDF. Essai gratuit de 14 jours, sans carte bancaire.'**
  String get pricingPageFaqItems0Answer;

  /// No description provided for @pricingPageFaqItems0Category.
  ///
  /// In fr, this message translates to:
  /// **'Essai'**
  String get pricingPageFaqItems0Category;

  /// No description provided for @pricingPageFaqItems1Question.
  ///
  /// In fr, this message translates to:
  /// **'Puis-je changer de plan ?'**
  String get pricingPageFaqItems1Question;

  /// No description provided for @pricingPageFaqItems1Answer.
  ///
  /// In fr, this message translates to:
  /// **'Oui, à tout moment. Upgrade immédiat, downgrade au prochain cycle. Aucun frais caché.'**
  String get pricingPageFaqItems1Answer;

  /// No description provided for @pricingPageFaqItems1Category.
  ///
  /// In fr, this message translates to:
  /// **'Facturation'**
  String get pricingPageFaqItems1Category;

  /// No description provided for @pricingPageFaqItems2Question.
  ///
  /// In fr, this message translates to:
  /// **'Comment fonctionne la facturation ?'**
  String get pricingPageFaqItems2Question;

  /// No description provided for @pricingPageFaqItems2Answer.
  ///
  /// In fr, this message translates to:
  /// **'Chaque plan inclut un prix fixe par mois avec un plafond d\'employés inclus (30 pour Pilot, 200 pour Operations, illimité pour Enterprise). Pas de supplément par employé actif.'**
  String get pricingPageFaqItems2Answer;

  /// No description provided for @pricingPageFaqItems2Category.
  ///
  /// In fr, this message translates to:
  /// **'Facturation'**
  String get pricingPageFaqItems2Category;

  /// No description provided for @pricingPageFaqItems3Question.
  ///
  /// In fr, this message translates to:
  /// **'L\'essai est-il vraiment gratuit ?'**
  String get pricingPageFaqItems3Question;

  /// No description provided for @pricingPageFaqItems3Answer.
  ///
  /// In fr, this message translates to:
  /// **'Oui. Essai gratuit complet — 30 jours sur le plan Free, 14 jours sur les plans payants. Aucune carte bancaire requise pour s\'inscrire.'**
  String get pricingPageFaqItems3Answer;

  /// No description provided for @pricingPageFaqItems3Category.
  ///
  /// In fr, this message translates to:
  /// **'Essai'**
  String get pricingPageFaqItems3Category;

  /// No description provided for @pricingPageFaqItems4Question.
  ///
  /// In fr, this message translates to:
  /// **'Que se passe-t-il à la fin de l\'essai ?'**
  String get pricingPageFaqItems4Question;

  /// No description provided for @pricingPageFaqItems4Answer.
  ///
  /// In fr, this message translates to:
  /// **'Vous choisissez un plan ou vos données restent archivées 14 jours supplémentaires. Aucune facturation automatique sans votre accord.'**
  String get pricingPageFaqItems4Answer;

  /// No description provided for @pricingPageFaqItems4Category.
  ///
  /// In fr, this message translates to:
  /// **'Essai'**
  String get pricingPageFaqItems4Category;

  /// No description provided for @pricingPageFaqItems5Question.
  ///
  /// In fr, this message translates to:
  /// **'Quel support est disponible ?'**
  String get pricingPageFaqItems5Question;

  /// No description provided for @pricingPageFaqItems5Answer.
  ///
  /// In fr, this message translates to:
  /// **'Pilot : support email sous 48h. Operations : support prioritaire sous 24h. Enterprise : account manager dédié + SLA contractuel.'**
  String get pricingPageFaqItems5Answer;

  /// No description provided for @pricingPageFaqItems5Category.
  ///
  /// In fr, this message translates to:
  /// **'Support'**
  String get pricingPageFaqItems5Category;

  /// No description provided for @pricingPageFaqItems6Question.
  ///
  /// In fr, this message translates to:
  /// **'Où sont hébergées mes données ?'**
  String get pricingPageFaqItems6Question;

  /// No description provided for @pricingPageFaqItems6Answer.
  ///
  /// In fr, this message translates to:
  /// **'En Europe (Render EU / Supabase EU). Chiffrement AES-256 au repos, TLS 1.3 en transit. Isolation par tenant garantie.'**
  String get pricingPageFaqItems6Answer;

  /// No description provided for @pricingPageFaqItems6Category.
  ///
  /// In fr, this message translates to:
  /// **'Sécurité'**
  String get pricingPageFaqItems6Category;

  /// No description provided for @pricingPageFaqItems7Question.
  ///
  /// In fr, this message translates to:
  /// **'Êtes-vous conformes RGPD ?'**
  String get pricingPageFaqItems7Question;

  /// No description provided for @pricingPageFaqItems7Answer.
  ///
  /// In fr, this message translates to:
  /// **'Oui. DPA disponible, données exclusivement en Europe, droit à l\'effacement implémenté, exports de données sur demande.'**
  String get pricingPageFaqItems7Answer;

  /// No description provided for @pricingPageFaqItems7Category.
  ///
  /// In fr, this message translates to:
  /// **'Sécurité'**
  String get pricingPageFaqItems7Category;

  /// No description provided for @pricingPageFaqItems8Question.
  ///
  /// In fr, this message translates to:
  /// **'L\'API est-elle disponible ?'**
  String get pricingPageFaqItems8Question;

  /// No description provided for @pricingPageFaqItems8Answer.
  ///
  /// In fr, this message translates to:
  /// **'L\'API REST et les webhooks sont disponibles à partir du plan Operations. Sur Pilot, vous pouvez exporter vos données en CSV/Excel.'**
  String get pricingPageFaqItems8Answer;

  /// No description provided for @pricingPageFaqItems8Category.
  ///
  /// In fr, this message translates to:
  /// **'Technique'**
  String get pricingPageFaqItems8Category;

  /// No description provided for @pricingPageFaqMoretitle.
  ///
  /// In fr, this message translates to:
  /// **'Une autre question ?'**
  String get pricingPageFaqMoretitle;

  /// No description provided for @pricingPageFaqContactsupport.
  ///
  /// In fr, this message translates to:
  /// **'Contacter le support'**
  String get pricingPageFaqContactsupport;

  /// No description provided for @pricingPageCtaBadge.
  ///
  /// In fr, this message translates to:
  /// **'Prêt à démarrer'**
  String get pricingPageCtaBadge;

  /// No description provided for @pricingPageCtaHeadline.
  ///
  /// In fr, this message translates to:
  /// **'Lancez vos RH terrain dès aujourd\'hui'**
  String get pricingPageCtaHeadline;

  /// No description provided for @pricingPageCtaSubheadline.
  ///
  /// In fr, this message translates to:
  /// **'Rejoignez les équipes qui ont réduit leur temps de paie de 2h à 8 minutes.'**
  String get pricingPageCtaSubheadline;

  /// No description provided for @pricingPageCtaPrimary.
  ///
  /// In fr, this message translates to:
  /// **'Démarrer gratuitement'**
  String get pricingPageCtaPrimary;

  /// No description provided for @pricingPageCtaSecondary.
  ///
  /// In fr, this message translates to:
  /// **'Contacter les ventes'**
  String get pricingPageCtaSecondary;

  /// No description provided for @pricingPageBadgesPopular.
  ///
  /// In fr, this message translates to:
  /// **'Le plus populaire'**
  String get pricingPageBadgesPopular;

  /// No description provided for @pricingPageBadgesFree.
  ///
  /// In fr, this message translates to:
  /// **'100% Gratuit'**
  String get pricingPageBadgesFree;

  /// No description provided for @pricingPageBadgesFreeprice.
  ///
  /// In fr, this message translates to:
  /// **'Gratuit'**
  String get pricingPageBadgesFreeprice;

  /// No description provided for @pricingPageBadgesFreenote.
  ///
  /// In fr, this message translates to:
  /// **'Sans carte bancaire · Pour toujours'**
  String get pricingPageBadgesFreenote;

  /// No description provided for @pricingPageBadgesFreetag.
  ///
  /// In fr, this message translates to:
  /// **'gratuit'**
  String get pricingPageBadgesFreetag;

  /// No description provided for @pricingPlansCustomprice.
  ///
  /// In fr, this message translates to:
  /// **'Sur devis'**
  String get pricingPlansCustomprice;

  /// No description provided for @pricingPlansFreeDescription.
  ///
  /// In fr, this message translates to:
  /// **'Pour démarrer sans engagement — idéal pour les équipes de 5 personnes'**
  String get pricingPlansFreeDescription;

  /// No description provided for @pricingPlansFreePricenote.
  ///
  /// In fr, this message translates to:
  /// **'14 jours d\'essai gratuits. Jusqu\'à 5 employés.'**
  String get pricingPlansFreePricenote;

  /// No description provided for @pricingPlansFreeEmployeelimit.
  ///
  /// In fr, this message translates to:
  /// **'Jusqu\'à 5 employés'**
  String get pricingPlansFreeEmployeelimit;

  /// No description provided for @pricingPlansFreeCta.
  ///
  /// In fr, this message translates to:
  /// **'Commencer gratuitement'**
  String get pricingPlansFreeCta;

  /// No description provided for @pricingPlansFreeFeatures0.
  ///
  /// In fr, this message translates to:
  /// **'Pointage web et mobile basique'**
  String get pricingPlansFreeFeatures0;

  /// No description provided for @pricingPlansFreeFeatures1.
  ///
  /// In fr, this message translates to:
  /// **'Absences et congés (consultation)'**
  String get pricingPlansFreeFeatures1;

  /// No description provided for @pricingPlansFreeFeatures2.
  ///
  /// In fr, this message translates to:
  /// **'Dossiers employés essentiels'**
  String get pricingPlansFreeFeatures2;

  /// No description provided for @pricingPlansFreeFeatures3.
  ///
  /// In fr, this message translates to:
  /// **'Bulletins de paie PDF'**
  String get pricingPlansFreeFeatures3;

  /// No description provided for @pricingPlansFreeFeatures4.
  ///
  /// In fr, this message translates to:
  /// **'App Employee incluse'**
  String get pricingPlansFreeFeatures4;

  /// No description provided for @pricingPlansFreeFeatures5.
  ///
  /// In fr, this message translates to:
  /// **'Support communautaire'**
  String get pricingPlansFreeFeatures5;

  /// No description provided for @pricingPlansFreePeriod.
  ///
  /// In fr, this message translates to:
  /// **'/mois'**
  String get pricingPlansFreePeriod;

  /// No description provided for @pricingPlansFreeAnnualperiod.
  ///
  /// In fr, this message translates to:
  /// **'/mois'**
  String get pricingPlansFreeAnnualperiod;

  /// No description provided for @pricingPlansPilotDescription.
  ///
  /// In fr, this message translates to:
  /// **'Pour piloter Leopardo sur un site, une équipe ou une agence'**
  String get pricingPlansPilotDescription;

  /// No description provided for @pricingPlansPilotPricenote.
  ///
  /// In fr, this message translates to:
  /// **'14 jours offerts. Jusqu\'à 30 employés.'**
  String get pricingPlansPilotPricenote;

  /// No description provided for @pricingPlansPilotEmployeelimit.
  ///
  /// In fr, this message translates to:
  /// **'Jusqu\'à 30 employés'**
  String get pricingPlansPilotEmployeelimit;

  /// No description provided for @pricingPlansPilotCta.
  ///
  /// In fr, this message translates to:
  /// **'Lancer un essai gratuit'**
  String get pricingPlansPilotCta;

  /// No description provided for @pricingPlansPilotFeatures0.
  ///
  /// In fr, this message translates to:
  /// **'Pointage web et mobile'**
  String get pricingPlansPilotFeatures0;

  /// No description provided for @pricingPlansPilotFeatures1.
  ///
  /// In fr, this message translates to:
  /// **'Absences, congés et soldes'**
  String get pricingPlansPilotFeatures1;

  /// No description provided for @pricingPlansPilotFeatures2.
  ///
  /// In fr, this message translates to:
  /// **'Dossiers employés et documents RH'**
  String get pricingPlansPilotFeatures2;

  /// No description provided for @pricingPlansPilotFeatures3.
  ///
  /// In fr, this message translates to:
  /// **'Bulletins de paie PDF et exports essentiels'**
  String get pricingPlansPilotFeatures3;

  /// No description provided for @pricingPlansPilotFeatures4.
  ///
  /// In fr, this message translates to:
  /// **'Portail client et espace manager'**
  String get pricingPlansPilotFeatures4;

  /// No description provided for @pricingPlansPilotFeatures5.
  ///
  /// In fr, this message translates to:
  /// **'Apps Employee et Manager incluses'**
  String get pricingPlansPilotFeatures5;

  /// No description provided for @pricingPlansPilotFeatures6.
  ///
  /// In fr, this message translates to:
  /// **'Support email sous 48h'**
  String get pricingPlansPilotFeatures6;

  /// No description provided for @pricingPlansPilotPeriod.
  ///
  /// In fr, this message translates to:
  /// **'/mois'**
  String get pricingPlansPilotPeriod;

  /// No description provided for @pricingPlansPilotAnnualperiod.
  ///
  /// In fr, this message translates to:
  /// **'/mois · 290 €/an facturé annuellement'**
  String get pricingPlansPilotAnnualperiod;

  /// No description provided for @pricingPlansOperationsDescription.
  ///
  /// In fr, this message translates to:
  /// **'Pour les PME multi-équipes qui pilotent terrain, RH et paie'**
  String get pricingPlansOperationsDescription;

  /// No description provided for @pricingPlansOperationsPricenote.
  ///
  /// In fr, this message translates to:
  /// **'14 jours offerts. Jusqu\'à 200 employés.'**
  String get pricingPlansOperationsPricenote;

  /// No description provided for @pricingPlansOperationsEmployeelimit.
  ///
  /// In fr, this message translates to:
  /// **'Jusqu\'à 200 employés'**
  String get pricingPlansOperationsEmployeelimit;

  /// No description provided for @pricingPlansOperationsCta.
  ///
  /// In fr, this message translates to:
  /// **'Essayer Operations'**
  String get pricingPlansOperationsCta;

  /// No description provided for @pricingPlansOperationsFeatures0.
  ///
  /// In fr, this message translates to:
  /// **'Tout Pilot, plus :'**
  String get pricingPlansOperationsFeatures0;

  /// No description provided for @pricingPlansOperationsFeatures1.
  ///
  /// In fr, this message translates to:
  /// **'Paie multi-pays et validations RH'**
  String get pricingPlansOperationsFeatures1;

  /// No description provided for @pricingPlansOperationsFeatures2.
  ///
  /// In fr, this message translates to:
  /// **'Managers, équipes et workflows d\'approbation'**
  String get pricingPlansOperationsFeatures2;

  /// No description provided for @pricingPlansOperationsFeatures3.
  ///
  /// In fr, this message translates to:
  /// **'Pointage ZKTeco, kiosque et mobile'**
  String get pricingPlansOperationsFeatures3;

  /// No description provided for @pricingPlansOperationsFeatures4.
  ///
  /// In fr, this message translates to:
  /// **'Analytics RH, readiness et exports avancés'**
  String get pricingPlansOperationsFeatures4;

  /// No description provided for @pricingPlansOperationsFeatures5.
  ///
  /// In fr, this message translates to:
  /// **'API, webhooks et intégrations'**
  String get pricingPlansOperationsFeatures5;

  /// No description provided for @pricingPlansOperationsFeatures6.
  ///
  /// In fr, this message translates to:
  /// **'Support prioritaire sous 24h'**
  String get pricingPlansOperationsFeatures6;

  /// No description provided for @pricingPlansOperationsPeriod.
  ///
  /// In fr, this message translates to:
  /// **'/mois'**
  String get pricingPlansOperationsPeriod;

  /// No description provided for @pricingPlansOperationsAnnualperiod.
  ///
  /// In fr, this message translates to:
  /// **'/mois · 790 €/an facturé annuellement'**
  String get pricingPlansOperationsAnnualperiod;

  /// No description provided for @pricingPlansEnterpriseDescription.
  ///
  /// In fr, this message translates to:
  /// **'Pour groupes multi-pays, franchises, réseaux de sites et exigences fortes'**
  String get pricingPlansEnterpriseDescription;

  /// No description provided for @pricingPlansEnterprisePricenote.
  ///
  /// In fr, this message translates to:
  /// **'14 jours offerts. Employés illimités.'**
  String get pricingPlansEnterprisePricenote;

  /// No description provided for @pricingPlansEnterpriseEmployeelimit.
  ///
  /// In fr, this message translates to:
  /// **'Employés illimités'**
  String get pricingPlansEnterpriseEmployeelimit;

  /// No description provided for @pricingPlansEnterpriseCta.
  ///
  /// In fr, this message translates to:
  /// **'Contacter les ventes'**
  String get pricingPlansEnterpriseCta;

  /// No description provided for @pricingPlansEnterpriseFeatures0.
  ///
  /// In fr, this message translates to:
  /// **'Tout Operations, plus :'**
  String get pricingPlansEnterpriseFeatures0;

  /// No description provided for @pricingPlansEnterpriseFeatures1.
  ///
  /// In fr, this message translates to:
  /// **'SSO SAML/OIDC et politiques avancées'**
  String get pricingPlansEnterpriseFeatures1;

  /// No description provided for @pricingPlansEnterpriseFeatures2.
  ///
  /// In fr, this message translates to:
  /// **'SLA, accompagnement migration et formation'**
  String get pricingPlansEnterpriseFeatures2;

  /// No description provided for @pricingPlansEnterpriseFeatures3.
  ///
  /// In fr, this message translates to:
  /// **'Environnements dédiés ou région cloud choisie'**
  String get pricingPlansEnterpriseFeatures3;

  /// No description provided for @pricingPlansEnterpriseFeatures4.
  ///
  /// In fr, this message translates to:
  /// **'Audit trail, exports compliance et support prioritaire'**
  String get pricingPlansEnterpriseFeatures4;

  /// No description provided for @pricingPlansEnterpriseFeatures5.
  ///
  /// In fr, this message translates to:
  /// **'Options IA, connecteurs et gouvernance sur mesure'**
  String get pricingPlansEnterpriseFeatures5;

  /// No description provided for @pricingFaqItems0Question.
  ///
  /// In fr, this message translates to:
  /// **'Que comprend le plan Pilot ?'**
  String get pricingFaqItems0Question;

  /// No description provided for @pricingFaqItems0Answer.
  ///
  /// In fr, this message translates to:
  /// **'Le plan Pilot à 29 €/mois inclut jusqu\'à 30 employés, le pointage web et mobile, les absences et congés, les dossiers employés et les bulletins de paie PDF. Essai gratuit de 14 jours, sans carte bancaire.'**
  String get pricingFaqItems0Answer;

  /// No description provided for @pricingFaqItems0Category.
  ///
  /// In fr, this message translates to:
  /// **'Essai'**
  String get pricingFaqItems0Category;

  /// No description provided for @pricingFaqItems1Question.
  ///
  /// In fr, this message translates to:
  /// **'Puis-je changer de plan ?'**
  String get pricingFaqItems1Question;

  /// No description provided for @pricingFaqItems1Answer.
  ///
  /// In fr, this message translates to:
  /// **'Oui, à tout moment. Upgrade immédiat, downgrade au prochain cycle. Aucun frais caché.'**
  String get pricingFaqItems1Answer;

  /// No description provided for @pricingFaqItems1Category.
  ///
  /// In fr, this message translates to:
  /// **'Facturation'**
  String get pricingFaqItems1Category;

  /// No description provided for @pricingFaqItems2Question.
  ///
  /// In fr, this message translates to:
  /// **'Comment fonctionne la facturation ?'**
  String get pricingFaqItems2Question;

  /// No description provided for @pricingFaqItems2Answer.
  ///
  /// In fr, this message translates to:
  /// **'Chaque plan inclut un prix fixe par mois avec un plafond d\'employés inclus (5 pour Free, 30 pour Pilot, 200 pour Operations, illimité pour Enterprise). Pas de supplément par employé actif.'**
  String get pricingFaqItems2Answer;

  /// No description provided for @pricingFaqItems2Category.
  ///
  /// In fr, this message translates to:
  /// **'Facturation'**
  String get pricingFaqItems2Category;

  /// No description provided for @pricingFaqItems3Question.
  ///
  /// In fr, this message translates to:
  /// **'L\'essai est-il vraiment gratuit ?'**
  String get pricingFaqItems3Question;

  /// No description provided for @pricingFaqItems3Answer.
  ///
  /// In fr, this message translates to:
  /// **'Oui. 14 jours complets avec toutes les fonctionnalités payantes. Aucune carte bancaire requise pour s\'inscrire.'**
  String get pricingFaqItems3Answer;

  /// No description provided for @pricingFaqItems3Category.
  ///
  /// In fr, this message translates to:
  /// **'Essai'**
  String get pricingFaqItems3Category;

  /// No description provided for @pricingFaqItems4Question.
  ///
  /// In fr, this message translates to:
  /// **'Le plan Free est-il vraiment gratuit ?'**
  String get pricingFaqItems4Question;

  /// No description provided for @pricingFaqItems4Answer.
  ///
  /// In fr, this message translates to:
  /// **'Oui. Le plan Free (0 €/mois) inclut jusqu\'à 5 employés : pointage web, absences et congés, dossiers employés et l\'app mobile Employee. Aucune carte bancaire.'**
  String get pricingFaqItems4Answer;

  /// No description provided for @pricingFaqItems4Category.
  ///
  /// In fr, this message translates to:
  /// **'Essai'**
  String get pricingFaqItems4Category;

  /// No description provided for @pricingFaqItems5Question.
  ///
  /// In fr, this message translates to:
  /// **'Que se passe-t-il à la fin de l\'essai ?'**
  String get pricingFaqItems5Question;

  /// No description provided for @pricingFaqItems5Answer.
  ///
  /// In fr, this message translates to:
  /// **'Vous choisissez un plan ou vos données restent archivées 14 jours supplémentaires. Aucune facturation automatique sans votre accord.'**
  String get pricingFaqItems5Answer;

  /// No description provided for @pricingFaqItems5Category.
  ///
  /// In fr, this message translates to:
  /// **'Essai'**
  String get pricingFaqItems5Category;

  /// No description provided for @pricingFaqItems6Question.
  ///
  /// In fr, this message translates to:
  /// **'Quel support est disponible ?'**
  String get pricingFaqItems6Question;

  /// No description provided for @pricingFaqItems6Answer.
  ///
  /// In fr, this message translates to:
  /// **'Pilot : support email sous 48h. Operations : support prioritaire sous 24h. Enterprise : account manager dédié + SLA contractuel.'**
  String get pricingFaqItems6Answer;

  /// No description provided for @pricingFaqItems6Category.
  ///
  /// In fr, this message translates to:
  /// **'Support'**
  String get pricingFaqItems6Category;

  /// No description provided for @pricingFaqItems7Question.
  ///
  /// In fr, this message translates to:
  /// **'Où sont hébergées mes données ?'**
  String get pricingFaqItems7Question;

  /// No description provided for @pricingFaqItems7Answer.
  ///
  /// In fr, this message translates to:
  /// **'En Europe (Render EU / Supabase EU). Chiffrement AES-256 au repos, TLS 1.3 en transit. Isolation par tenant garantie.'**
  String get pricingFaqItems7Answer;

  /// No description provided for @pricingFaqItems7Category.
  ///
  /// In fr, this message translates to:
  /// **'Sécurité'**
  String get pricingFaqItems7Category;

  /// No description provided for @pricingFaqItems8Question.
  ///
  /// In fr, this message translates to:
  /// **'Êtes-vous conformes RGPD ?'**
  String get pricingFaqItems8Question;

  /// No description provided for @pricingFaqItems8Answer.
  ///
  /// In fr, this message translates to:
  /// **'Oui. DPA disponible, données exclusivement en Europe, droit à l\'effacement implémenté, exports de données sur demande.'**
  String get pricingFaqItems8Answer;

  /// No description provided for @pricingFaqItems8Category.
  ///
  /// In fr, this message translates to:
  /// **'Sécurité'**
  String get pricingFaqItems8Category;

  /// No description provided for @pricingFaqItems9Question.
  ///
  /// In fr, this message translates to:
  /// **'L\'API est-elle disponible ?'**
  String get pricingFaqItems9Question;

  /// No description provided for @pricingFaqItems9Answer.
  ///
  /// In fr, this message translates to:
  /// **'L\'API REST et les webhooks sont disponibles à partir du plan Operations. Sur Pilot, vous pouvez exporter vos données en CSV/Excel.'**
  String get pricingFaqItems9Answer;

  /// No description provided for @pricingFaqItems9Category.
  ///
  /// In fr, this message translates to:
  /// **'Technique'**
  String get pricingFaqItems9Category;

  /// No description provided for @pricingSectionFreelabel.
  ///
  /// In fr, this message translates to:
  /// **'Gratuit'**
  String get pricingSectionFreelabel;

  /// No description provided for @pricingSectionTogglemonthly.
  ///
  /// In fr, this message translates to:
  /// **'Mensuel'**
  String get pricingSectionTogglemonthly;

  /// No description provided for @pricingSectionToggleannual.
  ///
  /// In fr, this message translates to:
  /// **'Annuel'**
  String get pricingSectionToggleannual;

  /// No description provided for @pricingSectionTogglearia.
  ///
  /// In fr, this message translates to:
  /// **'Changer la période de facturation'**
  String get pricingSectionTogglearia;

  /// No description provided for @pricingSectionFullcomparison.
  ///
  /// In fr, this message translates to:
  /// **'Voir la comparaison complete'**
  String get pricingSectionFullcomparison;

  /// No description provided for @pricingCardPerioddefault.
  ///
  /// In fr, this message translates to:
  /// **'/mois'**
  String get pricingCardPerioddefault;

  /// No description provided for @pricingCardCustompricedefault.
  ///
  /// In fr, this message translates to:
  /// **'Sur devis'**
  String get pricingCardCustompricedefault;

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
  /// **'Réessayer'**
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
  /// **'Déconnexion'**
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

  /// No description provided for @shellSettings.
  ///
  /// In fr, this message translates to:
  /// **'Réglages'**
  String get shellSettings;

  /// No description provided for @shellTeam.
  ///
  /// In fr, this message translates to:
  /// **'Équipe'**
  String get shellTeam;

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
  /// **'Générez des rapports avancés avec filtres de période et département.'**
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
  /// **'Analyse des données client...'**
  String get companydetailAnalyzing;

  /// No description provided for @companydetailRetry.
  ///
  /// In fr, this message translates to:
  /// **'Réessayer'**
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

  /// No description provided for @notificationsMarkAllReadError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de marquer les notifications comme lues.'**
  String get notificationsMarkAllReadError;

  /// No description provided for @notificationsMarkReadError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de marquer la notification comme lue.'**
  String get notificationsMarkReadError;

  /// No description provided for @notificationsDeleteError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de supprimer la notification.'**
  String get notificationsDeleteError;

  /// No description provided for @notificationsDeleted.
  ///
  /// In fr, this message translates to:
  /// **'Notification supprimée.'**
  String get notificationsDeleted;

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

  /// No description provided for @apiLoginInvalidJson.
  ///
  /// In fr, this message translates to:
  /// **'Corps de requête invalide.'**
  String get apiLoginInvalidJson;

  /// No description provided for @apiLoginTimeout.
  ///
  /// In fr, this message translates to:
  /// **'Le serveur met trop de temps à répondre. Réessayez dans quelques instants.'**
  String get apiLoginTimeout;

  /// No description provided for @apiLoginNetworkError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de contacter le serveur.'**
  String get apiLoginNetworkError;

  /// No description provided for @apiLoginBackendError.
  ///
  /// In fr, this message translates to:
  /// **'Réponse serveur inattendue.'**
  String get apiLoginBackendError;

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

  /// No description provided for @billingCancelSubscriptionConfirm.
  ///
  /// In fr, this message translates to:
  /// **'Annuler votre abonnement ? Vous perdrez l\'accès aux modules premium à la fin de la période en cours.'**
  String get billingCancelSubscriptionConfirm;

  /// No description provided for @billingNoActivePeriod.
  ///
  /// In fr, this message translates to:
  /// **'Aucune période active'**
  String get billingNoActivePeriod;

  /// No description provided for @billingNoActiveSubscription.
  ///
  /// In fr, this message translates to:
  /// **'Aucun abonnement active'**
  String get billingNoActiveSubscription;

  /// No description provided for @billingPeriodLabel.
  ///
  /// In fr, this message translates to:
  /// **'Période'**
  String get billingPeriodLabel;

  /// No description provided for @billingCheckoutSandboxMessage.
  ///
  /// In fr, this message translates to:
  /// **'Paiement simulé (mode sandbox). Aucune carte débitée.'**
  String get billingCheckoutSandboxMessage;

  /// No description provided for @billingCheckoutUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'Le paiement en ligne est temporairement indisponible. Contactez le support à support@leopardo-rh.com.'**
  String get billingCheckoutUnavailable;

  /// No description provided for @billingCheckoutFailed.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de créer la session de paiement.'**
  String get billingCheckoutFailed;

  /// No description provided for @billingRedirectUrlInvalid.
  ///
  /// In fr, this message translates to:
  /// **'Les URLs de redirection doivent appartenir au site autorisé.'**
  String get billingRedirectUrlInvalid;

  /// No description provided for @contractsListSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Gestion des contrats employés : suivi des statuts, échéances et export PDF, branchée directement sur l\'API RH.'**
  String get contractsListSubtitle;

  /// No description provided for @contractsSearchplaceholder.
  ///
  /// In fr, this message translates to:
  /// **'Rechercher un employe ou un type de contrat...'**
  String get contractsSearchplaceholder;

  /// No description provided for @contractsAllstatuses.
  ///
  /// In fr, this message translates to:
  /// **'Tous les statuts'**
  String get contractsAllstatuses;

  /// No description provided for @trainingTitleplaceholder.
  ///
  /// In fr, this message translates to:
  /// **'Titre *'**
  String get trainingTitleplaceholder;

  /// No description provided for @trainingDurationplaceholder.
  ///
  /// In fr, this message translates to:
  /// **'Duree (h)'**
  String get trainingDurationplaceholder;

  /// No description provided for @trainingMaxparticipantsplaceholder.
  ///
  /// In fr, this message translates to:
  /// **'Participants max'**
  String get trainingMaxparticipantsplaceholder;

  /// No description provided for @trainingOnline.
  ///
  /// In fr, this message translates to:
  /// **'En ligne'**
  String get trainingOnline;

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

  /// No description provided for @accessDeniedTitle.
  ///
  /// In fr, this message translates to:
  /// **'Accès refusé'**
  String get accessDeniedTitle;

  /// No description provided for @accessDeniedBodyHr.
  ///
  /// In fr, this message translates to:
  /// **'Votre compte n\'a pas le rôle RH requis pour cette application. Utilisez l\'application correspondant à votre rôle (Employee, Manager…) ou contactez votre administrateur.'**
  String get accessDeniedBodyHr;

  /// No description provided for @ampAutoDetectDesc.
  ///
  /// In fr, this message translates to:
  /// **'Votre présence est détectée automatiquement dès que vous entrez dans la zone de l\'entreprise. Aucune action requise de votre part.'**
  String get ampAutoDetectDesc;

  /// No description provided for @ampManualDesc.
  ///
  /// In fr, this message translates to:
  /// **'Pointez manuellement en appuyant sur les boutons Arrivée et Départ dans l\'écran de présence.'**
  String get ampManualDesc;

  /// No description provided for @ampModeTitle.
  ///
  /// In fr, this message translates to:
  /// **'Mode de pointage'**
  String get ampModeTitle;

  /// No description provided for @ampQrScanDesc.
  ///
  /// In fr, this message translates to:
  /// **'Scannez le QR Code affiché à l\'entrée de l\'entreprise pour pointer votre arrivée et votre départ.'**
  String get ampQrScanDesc;

  /// No description provided for @ampRecommended.
  ///
  /// In fr, this message translates to:
  /// **'Recommandé'**
  String get ampRecommended;

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

  /// No description provided for @approvalsEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune approbation en attente.'**
  String get approvalsEmpty;

  /// No description provided for @approvalsUpToDate.
  ///
  /// In fr, this message translates to:
  /// **'Tout est à jour'**
  String get approvalsUpToDate;

  /// No description provided for @approvalsLoading.
  ///
  /// In fr, this message translates to:
  /// **'Chargement des approbations...'**
  String get approvalsLoading;

  /// No description provided for @approvalsRejectReasonHint.
  ///
  /// In fr, this message translates to:
  /// **'Expliquez la raison...'**
  String get approvalsRejectReasonHint;

  /// No description provided for @approvalsRejectReasonLabel.
  ///
  /// In fr, this message translates to:
  /// **'Motif du refus'**
  String get approvalsRejectReasonLabel;

  /// No description provided for @approvalsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Approbations'**
  String get approvalsTitle;

  /// No description provided for @back.
  ///
  /// In fr, this message translates to:
  /// **'Retour'**
  String get back;

  /// No description provided for @employeeNumber.
  ///
  /// In fr, this message translates to:
  /// **'Employé #{id}'**
  String employeeNumber(Object id);

  /// No description provided for @errorPrefix.
  ///
  /// In fr, this message translates to:
  /// **'Erreur : {message}'**
  String errorPrefix(Object message);

  /// No description provided for @errorUnexpected.
  ///
  /// In fr, this message translates to:
  /// **'Une erreur est survenue'**
  String get errorUnexpected;

  /// No description provided for @evaluationPeriod.
  ///
  /// In fr, this message translates to:
  /// **'Période : {period}'**
  String evaluationPeriod(Object period);

  /// No description provided for @evaluationsEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune évaluation'**
  String get evaluationsEmpty;

  /// No description provided for @evaluationsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Mes Évaluations'**
  String get evaluationsTitle;

  /// No description provided for @evaluationsEmptyHint.
  ///
  /// In fr, this message translates to:
  /// **'Vous n\'avez pas encore d\'évaluation enregistrée.'**
  String get evaluationsEmptyHint;

  /// No description provided for @featureComingSoon.
  ///
  /// In fr, this message translates to:
  /// **'Fonction bientôt disponible'**
  String get featureComingSoon;

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

  /// No description provided for @monthlySummaryLoading.
  ///
  /// In fr, this message translates to:
  /// **'Chargement du résumé mensuel...'**
  String get monthlySummaryLoading;

  /// No description provided for @orgChartCollapse.
  ///
  /// In fr, this message translates to:
  /// **'Réduire'**
  String get orgChartCollapse;

  /// No description provided for @orgChartEmpty.
  ///
  /// In fr, this message translates to:
  /// **'L\'organigramme sera disponible une fois les employés configurés.'**
  String get orgChartEmpty;

  /// No description provided for @orgChartExpand.
  ///
  /// In fr, this message translates to:
  /// **'Développer'**
  String get orgChartExpand;

  /// No description provided for @pageNotFound.
  ///
  /// In fr, this message translates to:
  /// **'La page demandée est introuvable ou la navigation a échoué.'**
  String get pageNotFound;

  /// No description provided for @pendingSessionsEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune session GPS en attente de validation.'**
  String get pendingSessionsEmpty;

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

  /// No description provided for @refresh.
  ///
  /// In fr, this message translates to:
  /// **'Actualiser'**
  String get refresh;

  /// No description provided for @registerCreateAccount.
  ///
  /// In fr, this message translates to:
  /// **'Créer votre compte'**
  String get registerCreateAccount;

  /// No description provided for @registerCreating.
  ///
  /// In fr, this message translates to:
  /// **'Création de compte en cours...'**
  String get registerCreating;

  /// No description provided for @registerFirstName.
  ///
  /// In fr, this message translates to:
  /// **'Prénom'**
  String get registerFirstName;

  /// No description provided for @registerMinChars.
  ///
  /// In fr, this message translates to:
  /// **'8 caractères minimum'**
  String get registerMinChars;

  /// No description provided for @registerPassword.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe'**
  String get registerPassword;

  /// No description provided for @registerRequired.
  ///
  /// In fr, this message translates to:
  /// **'Obligatoire'**
  String get registerRequired;

  /// No description provided for @registerSubmit.
  ///
  /// In fr, this message translates to:
  /// **'Créer mon compte'**
  String get registerSubmit;

  /// No description provided for @retry.
  ///
  /// In fr, this message translates to:
  /// **'Réessayer'**
  String get retry;

  /// No description provided for @saApproved.
  ///
  /// In fr, this message translates to:
  /// **'Approuvées'**
  String get saApproved;

  /// No description provided for @saConfigLoadError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger la configuration.\n{error}'**
  String saConfigLoadError(Object error);

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

  /// No description provided for @saDisableAutoGps.
  ///
  /// In fr, this message translates to:
  /// **'Désactiver le GPS automatique'**
  String get saDisableAutoGps;

  /// No description provided for @saEnableAutoGps.
  ///
  /// In fr, this message translates to:
  /// **'Activer le GPS automatique'**
  String get saEnableAutoGps;

  /// No description provided for @saForced.
  ///
  /// In fr, this message translates to:
  /// **'Imposé'**
  String get saForced;

  /// No description provided for @saGpsZoneNotConfigured.
  ///
  /// In fr, this message translates to:
  /// **'La zone GPS de votre entreprise n\'est pas encore configurée.'**
  String get saGpsZoneNotConfigured;

  /// No description provided for @saPermissionDenied.
  ///
  /// In fr, this message translates to:
  /// **'Autorisation de localisation refusée. Activez le GPS dans les réglages pour activer la surveillance.'**
  String get saPermissionDenied;

  /// No description provided for @saPresenceInProgress.
  ///
  /// In fr, this message translates to:
  /// **'Présence en cours depuis {time}'**
  String saPresenceInProgress(Object time);

  /// No description provided for @saRecentSessions.
  ///
  /// In fr, this message translates to:
  /// **'Sessions récentes'**
  String get saRecentSessions;

  /// No description provided for @saRejected.
  ///
  /// In fr, this message translates to:
  /// **'Rejetées'**
  String get saRejected;

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

  /// No description provided for @saStatusApproved.
  ///
  /// In fr, this message translates to:
  /// **'Approuvée'**
  String get saStatusApproved;

  /// No description provided for @saStatusCancelled.
  ///
  /// In fr, this message translates to:
  /// **'Annulée'**
  String get saStatusCancelled;

  /// No description provided for @saStatusDetected.
  ///
  /// In fr, this message translates to:
  /// **'Détectée'**
  String get saStatusDetected;

  /// No description provided for @saStatusPending.
  ///
  /// In fr, this message translates to:
  /// **'En validation'**
  String get saStatusPending;

  /// No description provided for @saStatusRejected.
  ///
  /// In fr, this message translates to:
  /// **'Rejetée'**
  String get saStatusRejected;

  /// No description provided for @sessionApproved.
  ///
  /// In fr, this message translates to:
  /// **'Session approuvée ✓'**
  String get sessionApproved;

  /// No description provided for @sessionEntryAt.
  ///
  /// In fr, this message translates to:
  /// **'Entrée : {time}'**
  String sessionEntryAt(Object time);

  /// No description provided for @sessionRejected.
  ///
  /// In fr, this message translates to:
  /// **'Session rejetée'**
  String get sessionRejected;

  /// No description provided for @sessionsToValidate.
  ///
  /// In fr, this message translates to:
  /// **'Sessions à valider'**
  String get sessionsToValidate;

  /// No description provided for @backToHome.
  ///
  /// In fr, this message translates to:
  /// **'Retour à l\'accueil'**
  String get backToHome;

  /// No description provided for @absencesTitle.
  ///
  /// In fr, this message translates to:
  /// **'Mes absences'**
  String get absencesTitle;

  /// No description provided for @absencesSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Demandes, soldes et décisions RH'**
  String get absencesSubtitle;

  /// No description provided for @absencesRequest.
  ///
  /// In fr, this message translates to:
  /// **'Demander'**
  String get absencesRequest;

  /// No description provided for @absencesEmptyTitle.
  ///
  /// In fr, this message translates to:
  /// **'Aucune absence'**
  String get absencesEmptyTitle;

  /// No description provided for @absencesEmptyHint.
  ///
  /// In fr, this message translates to:
  /// **'Demandez une absence depuis le bouton principal, puis suivez la décision RH ici.'**
  String get absencesEmptyHint;

  /// No description provided for @absencesEmployeeLabel.
  ///
  /// In fr, this message translates to:
  /// **'Employé'**
  String get absencesEmployeeLabel;

  /// No description provided for @absencesTypeFallback.
  ///
  /// In fr, this message translates to:
  /// **'Absence'**
  String get absencesTypeFallback;

  /// No description provided for @absencesLoading.
  ///
  /// In fr, this message translates to:
  /// **'Chargement des absences'**
  String get absencesLoading;

  /// No description provided for @absencesApprove.
  ///
  /// In fr, this message translates to:
  /// **'Approuver'**
  String get absencesApprove;

  /// No description provided for @absencesReject.
  ///
  /// In fr, this message translates to:
  /// **'Refuser'**
  String get absencesReject;

  /// No description provided for @absencesCancelRequest.
  ///
  /// In fr, this message translates to:
  /// **'Annuler la demande'**
  String get absencesCancelRequest;

  /// No description provided for @absencesViewProof.
  ///
  /// In fr, this message translates to:
  /// **'Voir le justificatif'**
  String get absencesViewProof;

  /// No description provided for @absencesProofDownloaded.
  ///
  /// In fr, this message translates to:
  /// **'Justificatif téléchargé : '**
  String get absencesProofDownloaded;

  /// No description provided for @absencesFailure.
  ///
  /// In fr, this message translates to:
  /// **'Échec : '**
  String get absencesFailure;

  /// No description provided for @absencesReasonMissing.
  ///
  /// In fr, this message translates to:
  /// **'Motif non renseigné'**
  String get absencesReasonMissing;

  /// No description provided for @absencesDateMissing.
  ///
  /// In fr, this message translates to:
  /// **'Date de demande non renseignée'**
  String get absencesDateMissing;

  /// No description provided for @absencesCurrentCompany.
  ///
  /// In fr, this message translates to:
  /// **'Entreprise courante'**
  String get absencesCurrentCompany;

  /// No description provided for @absencesRequesterLabel.
  ///
  /// In fr, this message translates to:
  /// **'Demandeur : '**
  String get absencesRequesterLabel;

  /// No description provided for @absencesCompanyLabel.
  ///
  /// In fr, this message translates to:
  /// **'Entreprise : '**
  String get absencesCompanyLabel;

  /// No description provided for @absencesRequestLabel.
  ///
  /// In fr, this message translates to:
  /// **'Demande : '**
  String get absencesRequestLabel;

  /// No description provided for @absencesReasonLabel.
  ///
  /// In fr, this message translates to:
  /// **'Motif : '**
  String get absencesReasonLabel;

  /// No description provided for @absencesApproveTitle.
  ///
  /// In fr, this message translates to:
  /// **'Approuver cette absence ?'**
  String get absencesApproveTitle;

  /// No description provided for @absencesReasonNotProvided.
  ///
  /// In fr, this message translates to:
  /// **'non renseigné'**
  String get absencesReasonNotProvided;

  /// No description provided for @absencesApproveBody.
  ///
  /// In fr, this message translates to:
  /// **'La demande passera en statut approuvé et l\'employé sera notifié.'**
  String get absencesApproveBody;

  /// No description provided for @absencesApprovedSnack.
  ///
  /// In fr, this message translates to:
  /// **'Absence approuvée.'**
  String get absencesApprovedSnack;

  /// No description provided for @absencesRejectTitle.
  ///
  /// In fr, this message translates to:
  /// **'Refuser l\'absence'**
  String get absencesRejectTitle;

  /// No description provided for @absencesRejectHelper.
  ///
  /// In fr, this message translates to:
  /// **'Le motif sera visible par l\'employé.'**
  String get absencesRejectHelper;

  /// No description provided for @absencesRejectedSnack.
  ///
  /// In fr, this message translates to:
  /// **'Absence refusée.'**
  String get absencesRejectedSnack;

  /// No description provided for @absencesCancelTitle.
  ///
  /// In fr, this message translates to:
  /// **'Annuler cette demande ?'**
  String get absencesCancelTitle;

  /// No description provided for @absencesCancelBody.
  ///
  /// In fr, this message translates to:
  /// **'La demande en attente sera retirée et le RH verra le statut annulé.'**
  String get absencesCancelBody;

  /// No description provided for @absencesKeep.
  ///
  /// In fr, this message translates to:
  /// **'Garder'**
  String get absencesKeep;

  /// No description provided for @absencesCancel.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get absencesCancel;

  /// No description provided for @absencesCancelledSnack.
  ///
  /// In fr, this message translates to:
  /// **'Demande d\'absence annulée.'**
  String get absencesCancelledSnack;

  /// No description provided for @absencesStatusApproved.
  ///
  /// In fr, this message translates to:
  /// **'approuvée'**
  String get absencesStatusApproved;

  /// No description provided for @absencesStatusPending.
  ///
  /// In fr, this message translates to:
  /// **'en attente'**
  String get absencesStatusPending;

  /// No description provided for @absencesStatusRejected.
  ///
  /// In fr, this message translates to:
  /// **'rejetée'**
  String get absencesStatusRejected;

  /// No description provided for @absencesStatusCancelled.
  ///
  /// In fr, this message translates to:
  /// **'annulée'**
  String get absencesStatusCancelled;

  /// No description provided for @absencesNewAbsence.
  ///
  /// In fr, this message translates to:
  /// **'Nouvelle absence'**
  String get absencesNewAbsence;

  /// No description provided for @absencesNewAbsenceHint.
  ///
  /// In fr, this message translates to:
  /// **'Choisissez le type de solde et la période à transmettre au RH.'**
  String get absencesNewAbsenceHint;

  /// No description provided for @absencesNoTypeAvailable.
  ///
  /// In fr, this message translates to:
  /// **'Aucun type d\'absence disponible pour ce compte. Contactez le RH pour configurer les soldes.'**
  String get absencesNoTypeAvailable;

  /// No description provided for @absencesType.
  ///
  /// In fr, this message translates to:
  /// **'Type'**
  String get absencesType;

  /// No description provided for @absencesTypeRequired.
  ///
  /// In fr, this message translates to:
  /// **'Type d\'absence requis'**
  String get absencesTypeRequired;

  /// No description provided for @absencesBalancesLoading.
  ///
  /// In fr, this message translates to:
  /// **'Chargement des soldes'**
  String get absencesBalancesLoading;

  /// No description provided for @absencesStart.
  ///
  /// In fr, this message translates to:
  /// **'Début'**
  String get absencesStart;

  /// No description provided for @absencesEnd.
  ///
  /// In fr, this message translates to:
  /// **'Fin'**
  String get absencesEnd;

  /// No description provided for @absencesReason.
  ///
  /// In fr, this message translates to:
  /// **'Motif'**
  String get absencesReason;

  /// No description provided for @absencesReasonhint.
  ///
  /// In fr, this message translates to:
  /// **'Ex : rendez-vous médical, congé familial…'**
  String get absencesReasonhint;

  /// No description provided for @absencesReasonrequired.
  ///
  /// In fr, this message translates to:
  /// **'Motif obligatoire'**
  String get absencesReasonrequired;

  /// No description provided for @absencesAttachProof.
  ///
  /// In fr, this message translates to:
  /// **'Joindre un justificatif (optionnel)'**
  String get absencesAttachProof;

  /// No description provided for @absencesProofAttached.
  ///
  /// In fr, this message translates to:
  /// **'Justificatif joint'**
  String get absencesProofAttached;

  /// No description provided for @absencesSubmitToHr.
  ///
  /// In fr, this message translates to:
  /// **'Soumettre au RH'**
  String get absencesSubmitToHr;

  /// No description provided for @absencesSubmittedSnack.
  ///
  /// In fr, this message translates to:
  /// **'Demande d\'absence transmise au RH.'**
  String get absencesSubmittedSnack;

  /// No description provided for @absencesDaysAvailable.
  ///
  /// In fr, this message translates to:
  /// **' j disponibles'**
  String get absencesDaysAvailable;

  /// No description provided for @absencesDaysShort.
  ///
  /// In fr, this message translates to:
  /// **' j'**
  String get absencesDaysShort;

  /// No description provided for @absencesEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune absence'**
  String get absencesEmpty;

  /// No description provided for @absencesListSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Demandes, soldes et décisions RH'**
  String get absencesListSubtitle;

  /// No description provided for @absencesListTitle.
  ///
  /// In fr, this message translates to:
  /// **'Mes Absences'**
  String get absencesListTitle;

  /// No description provided for @settingsJourneyLoadError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger votre parcours.'**
  String get settingsJourneyLoadError;

  /// No description provided for @settingsJourneyInProgress.
  ///
  /// In fr, this message translates to:
  /// **'En cours'**
  String get settingsJourneyInProgress;

  /// No description provided for @settingsJourneyTitle.
  ///
  /// In fr, this message translates to:
  /// **'Parcours professionnel'**
  String get settingsJourneyTitle;

  /// No description provided for @settingsJourneyUnknownCompany.
  ///
  /// In fr, this message translates to:
  /// **'Entreprise'**
  String get settingsJourneyUnknownCompany;

  /// No description provided for @settingsJourneyUnknownDate.
  ///
  /// In fr, this message translates to:
  /// **'Date inconnue'**
  String get settingsJourneyUnknownDate;

  /// No description provided for @settingsJourneyUnknownPosition.
  ///
  /// In fr, this message translates to:
  /// **'Poste non renseigné'**
  String get settingsJourneyUnknownPosition;

  /// No description provided for @settingsStatsLoadError.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger les statistiques.'**
  String get settingsStatsLoadError;

  /// No description provided for @settingsAccountPortableHint.
  ///
  /// In fr, this message translates to:
  /// **'Votre compte reste utile même quand vous changez d\'entreprise.'**
  String get settingsAccountPortableHint;

  /// No description provided for @settingsAccountSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Profil, langue et sécurité'**
  String get settingsAccountSubtitle;

  /// No description provided for @settingsAccountTitle.
  ///
  /// In fr, this message translates to:
  /// **'Compte'**
  String get settingsAccountTitle;

  /// No description provided for @settingsBiometricApproved.
  ///
  /// In fr, this message translates to:
  /// **'Approuvé'**
  String get settingsBiometricApproved;

  /// No description provided for @settingsBiometricConsent.
  ///
  /// In fr, this message translates to:
  /// **'Je consens au traitement de mes données biométriques.'**
  String get settingsBiometricConsent;

  /// No description provided for @settingsBiometricDevice.
  ///
  /// In fr, this message translates to:
  /// **'Appareil de référence (optionnel)'**
  String get settingsBiometricDevice;

  /// No description provided for @settingsBiometricFace.
  ///
  /// In fr, this message translates to:
  /// **'Visage'**
  String get settingsBiometricFace;

  /// No description provided for @settingsBiometricFingerprint.
  ///
  /// In fr, this message translates to:
  /// **'Empreinte digitale'**
  String get settingsBiometricFingerprint;

  /// No description provided for @settingsBiometricManagerHint.
  ///
  /// In fr, this message translates to:
  /// **'Réservée aux profils employés dans cette app manager.'**
  String get settingsBiometricManagerHint;

  /// No description provided for @settingsBiometricNone.
  ///
  /// In fr, this message translates to:
  /// **'Aucun enrôlement'**
  String get settingsBiometricNone;

  /// No description provided for @settingsBiometricNote.
  ///
  /// In fr, this message translates to:
  /// **'Note (optionnel)'**
  String get settingsBiometricNote;

  /// No description provided for @settingsBiometricPending.
  ///
  /// In fr, this message translates to:
  /// **'En attente'**
  String get settingsBiometricPending;

  /// No description provided for @settingsBiometricRejected.
  ///
  /// In fr, this message translates to:
  /// **'Rejeté'**
  String get settingsBiometricRejected;

  /// No description provided for @settingsBiometricSaved.
  ///
  /// In fr, this message translates to:
  /// **'Préparation biométrique enregistrée.'**
  String get settingsBiometricSaved;

  /// No description provided for @settingsBiometricTerminalHint.
  ///
  /// In fr, this message translates to:
  /// **'Préparation doigt et visage pour les bornes terrain.'**
  String get settingsBiometricTerminalHint;

  /// No description provided for @settingsConfirmPassword.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer le mot de passe'**
  String get settingsConfirmPassword;

  /// No description provided for @settingsCurrentPassword.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe actuel'**
  String get settingsCurrentPassword;

  /// No description provided for @settingsEdgeSaved.
  ///
  /// In fr, this message translates to:
  /// **'Paramètres Edge enregistrés.'**
  String get settingsEdgeSaved;

  /// No description provided for @settingsEmailInvalid.
  ///
  /// In fr, this message translates to:
  /// **'Email invalide'**
  String get settingsEmailInvalid;

  /// No description provided for @settingsEmailLabel.
  ///
  /// In fr, this message translates to:
  /// **'Email'**
  String get settingsEmailLabel;

  /// No description provided for @settingsEmailRequired.
  ///
  /// In fr, this message translates to:
  /// **'Email requis'**
  String get settingsEmailRequired;

  /// No description provided for @settingsEmployeeProfileHint.
  ///
  /// In fr, this message translates to:
  /// **'Profil employé : accès au pointage, à l\'historique personnel et aux paramètres de préparation biométrie.'**
  String get settingsEmployeeProfileHint;

  /// No description provided for @settingsFirstName.
  ///
  /// In fr, this message translates to:
  /// **'Prénom'**
  String get settingsFirstName;

  /// No description provided for @settingsFirstNameRequired.
  ///
  /// In fr, this message translates to:
  /// **'Prénom requis'**
  String get settingsFirstNameRequired;

  /// No description provided for @settingsKioskBiometricTitle.
  ///
  /// In fr, this message translates to:
  /// **'Biométrie kiosk'**
  String get settingsKioskBiometricTitle;

  /// No description provided for @settingsLanguageSaved.
  ///
  /// In fr, this message translates to:
  /// **'Langue enregistrée.'**
  String get settingsLanguageSaved;

  /// No description provided for @settingsLanguageSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'La langue choisie pilote aussi les notifications et textes futurs.'**
  String get settingsLanguageSubtitle;

  /// No description provided for @settingsLanguageTitle.
  ///
  /// In fr, this message translates to:
  /// **'Langue'**
  String get settingsLanguageTitle;

  /// No description provided for @settingsLastNameLabel.
  ///
  /// In fr, this message translates to:
  /// **'Nom'**
  String get settingsLastNameLabel;

  /// No description provided for @settingsLastNameRequired.
  ///
  /// In fr, this message translates to:
  /// **'Nom requis'**
  String get settingsLastNameRequired;

  /// No description provided for @settingsLogout.
  ///
  /// In fr, this message translates to:
  /// **'Déconnexion'**
  String get settingsLogout;

  /// No description provided for @settingsManagerAccountHint.
  ///
  /// In fr, this message translates to:
  /// **'Un compte manager doit rester clair, sécurisé et prêt pour les décisions terrain.'**
  String get settingsManagerAccountHint;

  /// No description provided for @settingsMobileAccess.
  ///
  /// In fr, this message translates to:
  /// **'Accès mobile'**
  String get settingsMobileAccess;

  /// No description provided for @settingsMyProfile.
  ///
  /// In fr, this message translates to:
  /// **'Mon profil'**
  String get settingsMyProfile;

  /// No description provided for @settingsMyQrEmployee.
  ///
  /// In fr, this message translates to:
  /// **'Mon QR code'**
  String get settingsMyQrEmployee;

  /// No description provided for @settingsMyQrManager.
  ///
  /// In fr, this message translates to:
  /// **'Mon QR manager'**
  String get settingsMyQrManager;

  /// No description provided for @settingsNewPassword.
  ///
  /// In fr, this message translates to:
  /// **'Nouveau mot de passe'**
  String get settingsNewPassword;

  /// No description provided for @settingsNoCompanyQr.
  ///
  /// In fr, this message translates to:
  /// **'Aucun QR entreprise dans le presse-papiers.'**
  String get settingsNoCompanyQr;

  /// No description provided for @settingsNoJourney.
  ///
  /// In fr, this message translates to:
  /// **'Aucun parcours enregistré pour le moment.'**
  String get settingsNoJourney;

  /// No description provided for @settingsNotificationsSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Canaux, heures calmes et alertes manager opérationnelles.'**
  String get settingsNotificationsSubtitle;

  /// No description provided for @settingsNotificationsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Notifications'**
  String get settingsNotificationsTitle;

  /// No description provided for @settingsPasswordChanged.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe modifié.'**
  String get settingsPasswordChanged;

  /// No description provided for @settingsPasswordMinLength.
  ///
  /// In fr, this message translates to:
  /// **'8 caractères minimum'**
  String get settingsPasswordMinLength;

  /// No description provided for @settingsPasswordMismatch.
  ///
  /// In fr, this message translates to:
  /// **'Mots de passe différents'**
  String get settingsPasswordMismatch;

  /// No description provided for @settingsPasteQr.
  ///
  /// In fr, this message translates to:
  /// **'Coller le QR fourni par le manager ou le RH'**
  String get settingsPasteQr;

  /// No description provided for @settingsPersonalContacts.
  ///
  /// In fr, this message translates to:
  /// **'Contacts personnels'**
  String get settingsPersonalContacts;

  /// No description provided for @settingsPersonalEmail.
  ///
  /// In fr, this message translates to:
  /// **'Email personnel (optionnel)'**
  String get settingsPersonalEmail;

  /// No description provided for @settingsPersonalPhone.
  ///
  /// In fr, this message translates to:
  /// **'Téléphone personnel (optionnel)'**
  String get settingsPersonalPhone;

  /// No description provided for @settingsPortableAccountHint.
  ///
  /// In fr, this message translates to:
  /// **'Vos informations personnelles restent attachées au compte.'**
  String get settingsPortableAccountHint;

  /// No description provided for @settingsPreferredLanguage.
  ///
  /// In fr, this message translates to:
  /// **'Langue préférée'**
  String get settingsPreferredLanguage;

  /// No description provided for @settingsPreferredLanguageLabel.
  ///
  /// In fr, this message translates to:
  /// **'Langue préférée'**
  String get settingsPreferredLanguageLabel;

  /// No description provided for @settingsProfileSaved.
  ///
  /// In fr, this message translates to:
  /// **'Profil enregistré.'**
  String get settingsProfileSaved;

  /// No description provided for @settingsQrCopyToken.
  ///
  /// In fr, this message translates to:
  /// **'Copier aussi le jeton'**
  String get settingsQrCopyToken;

  /// No description provided for @settingsQrManagerHint.
  ///
  /// In fr, this message translates to:
  /// **'Un collègue ou un RH peut le scanner pour pré-remplir une invitation.'**
  String get settingsQrManagerHint;

  /// No description provided for @settingsRecoveryEmail.
  ///
  /// In fr, this message translates to:
  /// **'Email de secours (optionnel)'**
  String get settingsRecoveryEmail;

  /// No description provided for @settingsSave.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer'**
  String get settingsSave;

  /// No description provided for @settingsSaveEnrollment.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer la préparation'**
  String get settingsSaveEnrollment;

  /// No description provided for @settingsSaveProfile.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer le profil'**
  String get settingsSaveProfile;

  /// No description provided for @settingsSaving.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrement...'**
  String get settingsSaving;

  /// No description provided for @settingsSecurityTitle.
  ///
  /// In fr, this message translates to:
  /// **'Sécurité'**
  String get settingsSecurityTitle;

  /// No description provided for @settingsSessionSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'La déconnexion reste volontairement en bas de page.'**
  String get settingsSessionSubtitle;

  /// No description provided for @settingsSessionTitle.
  ///
  /// In fr, this message translates to:
  /// **'Session'**
  String get settingsSessionTitle;

  /// No description provided for @settingsTeamDrive.
  ///
  /// In fr, this message translates to:
  /// **'Pilotage équipe'**
  String get settingsTeamDrive;

  /// No description provided for @settingsTeamDriveHint.
  ///
  /// In fr, this message translates to:
  /// **'Profil, rôle et permissions restent lisibles pour les actions RH.'**
  String get settingsTeamDriveHint;

  /// No description provided for @cabinetScreenTitleRoot.
  ///
  /// In fr, this message translates to:
  /// **'Mon placard'**
  String get cabinetScreenTitleRoot;

  /// No description provided for @cabinetScreenEmptyTitle.
  ///
  /// In fr, this message translates to:
  /// **'Placard vide'**
  String get cabinetScreenEmptyTitle;

  /// No description provided for @cabinetScreenEmptyDescription.
  ///
  /// In fr, this message translates to:
  /// **'Ajoutez des dossiers et documents pour organiser votre espace.'**
  String get cabinetScreenEmptyDescription;

  /// No description provided for @cabinetScreenFolders.
  ///
  /// In fr, this message translates to:
  /// **'Dossiers'**
  String get cabinetScreenFolders;

  /// No description provided for @cabinetScreenDocuments.
  ///
  /// In fr, this message translates to:
  /// **'Documents'**
  String get cabinetScreenDocuments;

  /// No description provided for @cabinetScreenNewFolder.
  ///
  /// In fr, this message translates to:
  /// **'Nouveau dossier'**
  String get cabinetScreenNewFolder;

  /// No description provided for @cabinetScreenAddDocument.
  ///
  /// In fr, this message translates to:
  /// **'Ajouter un document'**
  String get cabinetScreenAddDocument;

  /// No description provided for @cabinetScreenAddDocumentSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Depuis vos fichiers ou la camera'**
  String get cabinetScreenAddDocumentSubtitle;

  /// No description provided for @cabinetScreenFolderNameHint.
  ///
  /// In fr, this message translates to:
  /// **'Nom du dossier'**
  String get cabinetScreenFolderNameHint;

  /// No description provided for @cabinetScreenCancel.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get cabinetScreenCancel;

  /// No description provided for @cabinetScreenCreate.
  ///
  /// In fr, this message translates to:
  /// **'Créer'**
  String get cabinetScreenCreate;

  /// No description provided for @cabinetScreenUploading.
  ///
  /// In fr, this message translates to:
  /// **'Envoi en cours...'**
  String get cabinetScreenUploading;

  /// No description provided for @cabinetScreenDocumentAdded.
  ///
  /// In fr, this message translates to:
  /// **'Document ajouté avec succès'**
  String get cabinetScreenDocumentAdded;

  /// No description provided for @cabinetScreenUploadFailed.
  ///
  /// In fr, this message translates to:
  /// **'Échec de l\'envoi du document. Réessayez.'**
  String get cabinetScreenUploadFailed;

  /// No description provided for @cabinetScreenShareTitle.
  ///
  /// In fr, this message translates to:
  /// **'Partager « {name} »'**
  String cabinetScreenShareTitle(Object name);

  /// No description provided for @cabinetScreenCreateShareLink.
  ///
  /// In fr, this message translates to:
  /// **'Créer un lien de partage'**
  String get cabinetScreenCreateShareLink;

  /// No description provided for @cabinetScreenLinkCopied.
  ///
  /// In fr, this message translates to:
  /// **'Lien copié : {url}'**
  String cabinetScreenLinkCopied(Object url);

  /// No description provided for @cabinetScreenShareByEmail.
  ///
  /// In fr, this message translates to:
  /// **'Partager par email'**
  String get cabinetScreenShareByEmail;

  /// No description provided for @cabinetScreenEmailHint.
  ///
  /// In fr, this message translates to:
  /// **'Email du destinataire'**
  String get cabinetScreenEmailHint;

  /// No description provided for @cabinetScreenSend.
  ///
  /// In fr, this message translates to:
  /// **'Envoyer'**
  String get cabinetScreenSend;

  /// No description provided for @cabinetScreenShareSent.
  ///
  /// In fr, this message translates to:
  /// **'Partage envoyé à {email}'**
  String cabinetScreenShareSent(Object email);

  /// No description provided for @cabinetScreenDeleteTitle.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer le document ?'**
  String get cabinetScreenDeleteTitle;

  /// No description provided for @cabinetScreenDeleteBody.
  ///
  /// In fr, this message translates to:
  /// **'Le document « {name} » sera supprimé définitivement.'**
  String cabinetScreenDeleteBody(Object name);

  /// No description provided for @cabinetScreenDelete.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer'**
  String get cabinetScreenDelete;

  /// No description provided for @cabinetScreenDocumentsCount.
  ///
  /// In fr, this message translates to:
  /// **'{count, plural, =1{1 doc} other{{count} docs}}'**
  String cabinetScreenDocumentsCount(num count);

  /// No description provided for @absenceCancelBody.
  ///
  /// In fr, this message translates to:
  /// **'La demande en attente sera retirée et le RH verra le statut annulé.'**
  String get absenceCancelBody;

  /// No description provided for @absenceCancelRequest.
  ///
  /// In fr, this message translates to:
  /// **'Annuler la demande'**
  String get absenceCancelRequest;

  /// No description provided for @absenceCancelTitle.
  ///
  /// In fr, this message translates to:
  /// **'Annuler cette demande ?'**
  String get absenceCancelTitle;

  /// No description provided for @absenceCancelled.
  ///
  /// In fr, this message translates to:
  /// **'Demande d\'absence annulée.'**
  String get absenceCancelled;

  /// No description provided for @absenceLabel.
  ///
  /// In fr, this message translates to:
  /// **'Absence'**
  String get absenceLabel;

  /// No description provided for @absenceNewHint.
  ///
  /// In fr, this message translates to:
  /// **'Choisissez le type de solde et la période à transmettre au RH.'**
  String get absenceNewHint;

  /// No description provided for @absenceNewTitle.
  ///
  /// In fr, this message translates to:
  /// **'Nouvelle absence'**
  String get absenceNewTitle;

  /// No description provided for @absenceNoType.
  ///
  /// In fr, this message translates to:
  /// **'Aucun type d\'absence disponible pour ce compte. Contactez le RH pour configurer les soldes.'**
  String get absenceNoType;

  /// No description provided for @absenceRequest.
  ///
  /// In fr, this message translates to:
  /// **'Demander'**
  String get absenceRequest;

  /// No description provided for @absenceViewProof.
  ///
  /// In fr, this message translates to:
  /// **'Voir le justificatif'**
  String get absenceViewProof;

  /// No description provided for @actionApprove.
  ///
  /// In fr, this message translates to:
  /// **'Approuver'**
  String get actionApprove;

  /// No description provided for @actionCancel.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get actionCancel;

  /// No description provided for @actionReject.
  ///
  /// In fr, this message translates to:
  /// **'Refuser'**
  String get actionReject;

  /// No description provided for @cancelRequest.
  ///
  /// In fr, this message translates to:
  /// **'Annuler la demande'**
  String get cancelRequest;

  /// No description provided for @confirmReceipt.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer la réception'**
  String get confirmReceipt;

  /// No description provided for @emptyAbsences.
  ///
  /// In fr, this message translates to:
  /// **'Aucune absence'**
  String get emptyAbsences;

  /// No description provided for @emptyAdvances.
  ///
  /// In fr, this message translates to:
  /// **'Aucune avance'**
  String get emptyAdvances;

  /// No description provided for @emptyHistory.
  ///
  /// In fr, this message translates to:
  /// **'Aucun historique'**
  String get emptyHistory;

  /// No description provided for @emptyPayslips.
  ///
  /// In fr, this message translates to:
  /// **'Aucune fiche de paie'**
  String get emptyPayslips;

  /// No description provided for @emptySessions.
  ///
  /// In fr, this message translates to:
  /// **'Aucune session'**
  String get emptySessions;

  /// No description provided for @loadError.
  ///
  /// In fr, this message translates to:
  /// **'Erreur de chargement'**
  String get loadError;

  /// No description provided for @noData.
  ///
  /// In fr, this message translates to:
  /// **'Aucune donnée'**
  String get noData;

  /// No description provided for @noReason.
  ///
  /// In fr, this message translates to:
  /// **'Aucun motif'**
  String get noReason;

  /// No description provided for @noTasksToday.
  ///
  /// In fr, this message translates to:
  /// **'Aucune tâche aujourd\'hui'**
  String get noTasksToday;

  /// No description provided for @salaryAdvanceAttachHint.
  ///
  /// In fr, this message translates to:
  /// **'Joindre une pièce (optionnel)'**
  String get salaryAdvanceAttachHint;

  /// No description provided for @salaryAdvanceAttachmentLabel.
  ///
  /// In fr, this message translates to:
  /// **'Pièce jointe'**
  String get salaryAdvanceAttachmentLabel;

  /// No description provided for @salaryAdvanceCancelAction.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get salaryAdvanceCancelAction;

  /// No description provided for @salaryAdvanceCancelBody.
  ///
  /// In fr, this message translates to:
  /// **'La demande en attente sera retirée avant décision RH.'**
  String get salaryAdvanceCancelBody;

  /// No description provided for @salaryAdvanceCancelRequest.
  ///
  /// In fr, this message translates to:
  /// **'Annuler la demande'**
  String get salaryAdvanceCancelRequest;

  /// No description provided for @salaryAdvanceCancelTitle.
  ///
  /// In fr, this message translates to:
  /// **'Annuler cette avance ?'**
  String get salaryAdvanceCancelTitle;

  /// No description provided for @salaryAdvanceCancelled.
  ///
  /// In fr, this message translates to:
  /// **'Demande d\'avance annulée.'**
  String get salaryAdvanceCancelled;

  /// No description provided for @salaryAdvanceConfirmAction.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer'**
  String get salaryAdvanceConfirmAction;

  /// No description provided for @salaryAdvanceConfirmReceived.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer réception'**
  String get salaryAdvanceConfirmReceived;

  /// No description provided for @salaryAdvanceConfirmReceivedBody.
  ///
  /// In fr, this message translates to:
  /// **'Confirmez seulement si le montant est effectivement arrivé. Cette action sera historisée.'**
  String get salaryAdvanceConfirmReceivedBody;

  /// No description provided for @salaryAdvanceConfirmReceivedTitle.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer la réception ?'**
  String get salaryAdvanceConfirmReceivedTitle;

  /// No description provided for @salaryAdvanceKeep.
  ///
  /// In fr, this message translates to:
  /// **'Garder'**
  String get salaryAdvanceKeep;

  /// No description provided for @salaryAdvanceListSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Demandes, statuts et remboursement'**
  String get salaryAdvanceListSubtitle;

  /// No description provided for @salaryAdvanceListTitle.
  ///
  /// In fr, this message translates to:
  /// **'Avances'**
  String get salaryAdvanceListTitle;

  /// No description provided for @salaryAdvanceNoReason.
  ///
  /// In fr, this message translates to:
  /// **'Aucun motif'**
  String get salaryAdvanceNoReason;

  /// No description provided for @salaryAdvancePaymentDeclared.
  ///
  /// In fr, this message translates to:
  /// **'Le manager a déclaré le paiement. Confirmez uniquement après réception effective.'**
  String get salaryAdvancePaymentDeclared;

  /// No description provided for @salaryAdvanceRequest.
  ///
  /// In fr, this message translates to:
  /// **'Demander'**
  String get salaryAdvanceRequest;

  /// No description provided for @salaryAdvanceRequestTitle.
  ///
  /// In fr, this message translates to:
  /// **'Demande d\'avance'**
  String get salaryAdvanceRequestTitle;

  /// No description provided for @salaryAdvanceSubmitted.
  ///
  /// In fr, this message translates to:
  /// **'Demande d\'avance transmise au RH.'**
  String get salaryAdvanceSubmitted;

  /// No description provided for @salaryAdvanceViewProof.
  ///
  /// In fr, this message translates to:
  /// **'Voir la pièce jointe'**
  String get salaryAdvanceViewProof;

  /// No description provided for @salaryAdvancesEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune avance'**
  String get salaryAdvancesEmpty;

  /// No description provided for @salaryAdvancesEmptyHint.
  ///
  /// In fr, this message translates to:
  /// **'Demandez une avance en quelques secondes, puis suivez la décision RH ici.'**
  String get salaryAdvancesEmptyHint;

  /// No description provided for @salaryAdvancesLoading.
  ///
  /// In fr, this message translates to:
  /// **'Chargement des avances'**
  String get salaryAdvancesLoading;

  /// No description provided for @salaryStatusActive.
  ///
  /// In fr, this message translates to:
  /// **'active'**
  String get salaryStatusActive;

  /// No description provided for @salaryStatusApproved.
  ///
  /// In fr, this message translates to:
  /// **'approuvée'**
  String get salaryStatusApproved;

  /// No description provided for @salaryStatusCancelled.
  ///
  /// In fr, this message translates to:
  /// **'annulée'**
  String get salaryStatusCancelled;

  /// No description provided for @salaryStatusPending.
  ///
  /// In fr, this message translates to:
  /// **'en attente'**
  String get salaryStatusPending;

  /// No description provided for @salaryStatusReceived.
  ///
  /// In fr, this message translates to:
  /// **'reçue'**
  String get salaryStatusReceived;

  /// No description provided for @salaryStatusRejected.
  ///
  /// In fr, this message translates to:
  /// **'rejetée'**
  String get salaryStatusRejected;

  /// No description provided for @salaryStatusToConfirm.
  ///
  /// In fr, this message translates to:
  /// **'à confirmer'**
  String get salaryStatusToConfirm;

  /// No description provided for @salaryStatusValidated.
  ///
  /// In fr, this message translates to:
  /// **'validée'**
  String get salaryStatusValidated;

  /// No description provided for @saveProfile.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer le profil'**
  String get saveProfile;

  /// No description provided for @savingProfile.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrement…'**
  String get savingProfile;

  /// No description provided for @taxSlabsSimCompare.
  ///
  /// In fr, this message translates to:
  /// **'Salaire à comparer'**
  String get taxSlabsSimCompare;

  /// No description provided for @teamAdd.
  ///
  /// In fr, this message translates to:
  /// **'Ajouter'**
  String get teamAdd;

  /// No description provided for @teamAddCollaborator.
  ///
  /// In fr, this message translates to:
  /// **'Ajouter un collaborateur'**
  String get teamAddCollaborator;

  /// No description provided for @teamAddFromQr.
  ///
  /// In fr, this message translates to:
  /// **'Depuis QR employé'**
  String get teamAddFromQr;

  /// No description provided for @teamAddFromQrHint.
  ///
  /// In fr, this message translates to:
  /// **'Coller le code fourni'**
  String get teamAddFromQrHint;

  /// No description provided for @teamAddManualForm.
  ///
  /// In fr, this message translates to:
  /// **'Formulaire classique'**
  String get teamAddManualForm;

  /// No description provided for @teamAddManualHint.
  ///
  /// In fr, this message translates to:
  /// **'Saisie manuelle complète'**
  String get teamAddManualHint;

  /// No description provided for @teamArchive.
  ///
  /// In fr, this message translates to:
  /// **'Archiver'**
  String get teamArchive;

  /// No description provided for @teamArchiveConfirmAction.
  ///
  /// In fr, this message translates to:
  /// **'Archiver'**
  String get teamArchiveConfirmAction;

  /// No description provided for @teamArchiveConfirmTitle.
  ///
  /// In fr, this message translates to:
  /// **'Archiver cet employé ?'**
  String get teamArchiveConfirmTitle;

  /// No description provided for @teamArchiveSuccess.
  ///
  /// In fr, this message translates to:
  /// **'Employé archivé.'**
  String get teamArchiveSuccess;

  /// No description provided for @teamConfirmCancel.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get teamConfirmCancel;

  /// No description provided for @teamEditProfile.
  ///
  /// In fr, this message translates to:
  /// **'Modifier la fiche'**
  String get teamEditProfile;

  /// No description provided for @teamEditProfileHint.
  ///
  /// In fr, this message translates to:
  /// **'Mettre à jour les champs RH essentiels'**
  String get teamEditProfileHint;

  /// No description provided for @teamEmployeeLabel.
  ///
  /// In fr, this message translates to:
  /// **'Employé'**
  String get teamEmployeeLabel;

  /// No description provided for @teamEmployeesTab.
  ///
  /// In fr, this message translates to:
  /// **'Employés'**
  String get teamEmployeesTab;

  /// No description provided for @teamEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucun collaborateur'**
  String get teamEmpty;

  /// No description provided for @teamEmptyHint.
  ///
  /// In fr, this message translates to:
  /// **'Commencez par ajouter votre équipe avec le bouton ci-dessous.'**
  String get teamEmptyHint;

  /// No description provided for @teamInvitationsTab.
  ///
  /// In fr, this message translates to:
  /// **'Invitations'**
  String get teamInvitationsTab;

  /// No description provided for @teamMakeHr.
  ///
  /// In fr, this message translates to:
  /// **'Nommer RH'**
  String get teamMakeHr;

  /// No description provided for @teamMakeHrConfirmAction.
  ///
  /// In fr, this message translates to:
  /// **'Nommer RH'**
  String get teamMakeHrConfirmAction;

  /// No description provided for @teamMakeHrConfirmTitle.
  ///
  /// In fr, this message translates to:
  /// **'Nommer RH ?'**
  String get teamMakeHrConfirmTitle;

  /// No description provided for @teamMakeHrHint.
  ///
  /// In fr, this message translates to:
  /// **'Donner les permissions RH à ce collaborateur'**
  String get teamMakeHrHint;

  /// No description provided for @teamMakeHrSuccess.
  ///
  /// In fr, this message translates to:
  /// **'RH nommé.'**
  String get teamMakeHrSuccess;

  /// No description provided for @teamManagerLabel.
  ///
  /// In fr, this message translates to:
  /// **'Manager'**
  String get teamManagerLabel;

  /// No description provided for @teamManagerRequired.
  ///
  /// In fr, this message translates to:
  /// **'Accès manager/RH requis'**
  String get teamManagerRequired;

  /// No description provided for @teamRevokeHr.
  ///
  /// In fr, this message translates to:
  /// **'Révoquer RH'**
  String get teamRevokeHr;

  /// No description provided for @teamRevokeHrConfirmAction.
  ///
  /// In fr, this message translates to:
  /// **'Révoquer'**
  String get teamRevokeHrConfirmAction;

  /// No description provided for @teamRevokeHrConfirmTitle.
  ///
  /// In fr, this message translates to:
  /// **'Révoquer RH ?'**
  String get teamRevokeHrConfirmTitle;

  /// No description provided for @teamRevokeHrHint.
  ///
  /// In fr, this message translates to:
  /// **'Retirer les permissions RH de ce compte'**
  String get teamRevokeHrHint;

  /// No description provided for @teamRevokeHrSuccess.
  ///
  /// In fr, this message translates to:
  /// **'Permissions RH retirées.'**
  String get teamRevokeHrSuccess;

  /// No description provided for @teamSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Collaborateurs et invitations'**
  String get teamSubtitle;

  /// No description provided for @teamTitle.
  ///
  /// In fr, this message translates to:
  /// **'Équipe'**
  String get teamTitle;

  /// No description provided for @teamViewAttendance.
  ///
  /// In fr, this message translates to:
  /// **'Statistiques et pointages'**
  String get teamViewAttendance;

  /// No description provided for @teamViewAttendanceHint.
  ///
  /// In fr, this message translates to:
  /// **'Présence, anomalies, historique'**
  String get teamViewAttendanceHint;

  /// No description provided for @teamViewProfile.
  ///
  /// In fr, this message translates to:
  /// **'Voir la fiche'**
  String get teamViewProfile;

  /// No description provided for @teamViewProfileHint.
  ///
  /// In fr, this message translates to:
  /// **'Coordonnées, poste, salaire, horaire'**
  String get teamViewProfileHint;

  /// No description provided for @teamViewTasks.
  ///
  /// In fr, this message translates to:
  /// **'Tâches'**
  String get teamViewTasks;

  /// No description provided for @teamViewTasksHint.
  ///
  /// In fr, this message translates to:
  /// **'Voir ou assigner des tâches terrain'**
  String get teamViewTasksHint;

  /// No description provided for @settingsManagerProfileHint.
  ///
  /// In fr, this message translates to:
  /// **'Profil RH/manager : accès au suivi de l\'équipe et à l\'historique.'**
  String get settingsManagerProfileHint;

  /// No description provided for @settingsOverview.
  ///
  /// In fr, this message translates to:
  /// **'Vue d\'ensemble'**
  String get settingsOverview;

  /// No description provided for @teamLoading.
  ///
  /// In fr, this message translates to:
  /// **'Chargement de l\'équipe'**
  String get teamLoading;

  /// No description provided for @teamManagerRequiredHint.
  ///
  /// In fr, this message translates to:
  /// **'Seuls les managers principaux et RH peuvent gérer l\'équipe depuis le mobile.'**
  String get teamManagerRequiredHint;

  /// No description provided for @salaryAdvanceError.
  ///
  /// In fr, this message translates to:
  /// **'Échec : {error}'**
  String salaryAdvanceError(Object error);

  /// No description provided for @salaryAdvanceMonths.
  ///
  /// In fr, this message translates to:
  /// **'{reason} - {months} mois'**
  String salaryAdvanceMonths(Object months, Object reason);

  /// No description provided for @salaryAdvanceProofDownloaded.
  ///
  /// In fr, this message translates to:
  /// **'Pièce jointe téléchargée: {path}'**
  String salaryAdvanceProofDownloaded(Object path);

  /// No description provided for @salaryAdvanceSemantics.
  ///
  /// In fr, this message translates to:
  /// **'Avance de {amount}, motif : {reason}, statut {status}.'**
  String salaryAdvanceSemantics(Object amount, Object reason, Object status);

  /// No description provided for @settingsJourneyToday.
  ///
  /// In fr, this message translates to:
  /// **'Aujourd\'hui'**
  String get settingsJourneyToday;

  /// No description provided for @settingsShareProfile.
  ///
  /// In fr, this message translates to:
  /// **'Partagez votre profil ou scannez le QR d\'une entreprise.'**
  String get settingsShareProfile;

  /// No description provided for @teamActionError.
  ///
  /// In fr, this message translates to:
  /// **'Échec : {error}'**
  String teamActionError(Object error);

  /// No description provided for @attendanceAccountSuspended.
  ///
  /// In fr, this message translates to:
  /// **'Compte suspendu ou accès refusé.'**
  String get attendanceAccountSuspended;

  /// No description provided for @attendanceBeforeDeductions.
  ///
  /// In fr, this message translates to:
  /// **'Avant déductions légales'**
  String get attendanceBeforeDeductions;

  /// No description provided for @attendanceBreakRegistered.
  ///
  /// In fr, this message translates to:
  /// **'Pause enregistrée.'**
  String get attendanceBreakRegistered;

  /// No description provided for @attendanceCheckinConfirmed.
  ///
  /// In fr, this message translates to:
  /// **'Arrivée confirmée.'**
  String get attendanceCheckinConfirmed;

  /// No description provided for @attendanceCheckinNormal.
  ///
  /// In fr, this message translates to:
  /// **'Arrivée normale'**
  String get attendanceCheckinNormal;

  /// No description provided for @attendanceCheckinNotConfirmed.
  ///
  /// In fr, this message translates to:
  /// **'Arrivée non confirmée'**
  String get attendanceCheckinNotConfirmed;

  /// No description provided for @attendanceCheckinRegistered.
  ///
  /// In fr, this message translates to:
  /// **'Arrivée enregistrée à l\'instant.'**
  String get attendanceCheckinRegistered;

  /// No description provided for @attendanceCheckoutConfirmed.
  ///
  /// In fr, this message translates to:
  /// **'Départ confirmé.'**
  String get attendanceCheckoutConfirmed;

  /// No description provided for @attendanceCheckoutNotConfirmed.
  ///
  /// In fr, this message translates to:
  /// **'Départ non confirmé'**
  String get attendanceCheckoutNotConfirmed;

  /// No description provided for @attendanceCheckoutRegistered.
  ///
  /// In fr, this message translates to:
  /// **'Départ enregistré à l\'instant.'**
  String get attendanceCheckoutRegistered;

  /// No description provided for @attendanceCloseTask.
  ///
  /// In fr, this message translates to:
  /// **'Clôturer la tâche'**
  String get attendanceCloseTask;

  /// No description provided for @attendanceCorrectionApplied.
  ///
  /// In fr, this message translates to:
  /// **'La correction sera appliquée au dossier de pointage.'**
  String get attendanceCorrectionApplied;

  /// No description provided for @attendanceCorrectionDirectBody.
  ///
  /// In fr, this message translates to:
  /// **'Corriger directement cette ligne de pointage.'**
  String get attendanceCorrectionDirectBody;

  /// No description provided for @attendanceCorrectionDirectSnack.
  ///
  /// In fr, this message translates to:
  /// **'Pointage du {date} modifié.'**
  String attendanceCorrectionDirectSnack(Object date);

  /// No description provided for @attendanceCorrectionEditDateTitle.
  ///
  /// In fr, this message translates to:
  /// **'Modifier le {date}'**
  String attendanceCorrectionEditDateTitle(Object date);

  /// No description provided for @attendanceCorrectionRequestBody.
  ///
  /// In fr, this message translates to:
  /// **'Soumettre une correction au RH pour validation.'**
  String get attendanceCorrectionRequestBody;

  /// No description provided for @attendanceCorrectionRequestSnack.
  ///
  /// In fr, this message translates to:
  /// **'Demande du {date} soumise au RH - vous serez notifié de la décision.'**
  String attendanceCorrectionRequestSnack(Object date);

  /// No description provided for @attendanceCorrectionSentToHr.
  ///
  /// In fr, this message translates to:
  /// **'La demande sera transmise au RH pour validation.'**
  String get attendanceCorrectionSentToHr;

  /// No description provided for @attendanceCurrentMonth.
  ///
  /// In fr, this message translates to:
  /// **'Mois actuel'**
  String get attendanceCurrentMonth;

  /// No description provided for @attendanceDayDetail.
  ///
  /// In fr, this message translates to:
  /// **'Détail par jour'**
  String get attendanceDayDetail;

  /// No description provided for @attendanceDayDetails.
  ///
  /// In fr, this message translates to:
  /// **'Détails de la journée'**
  String get attendanceDayDetails;

  /// No description provided for @attendanceDayDetailsBody.
  ///
  /// In fr, this message translates to:
  /// **'Voir les pointages, pauses, heures supp et temps réel.'**
  String get attendanceDayDetailsBody;

  /// No description provided for @attendanceDayNoSessionsYet.
  ///
  /// In fr, this message translates to:
  /// **'Cette journée ne contient pas encore de pointage.'**
  String get attendanceDayNoSessionsYet;

  /// No description provided for @attendanceDaySessionsSummary.
  ///
  /// In fr, this message translates to:
  /// **'{sessions} session(s) - {hours} travaillées.'**
  String attendanceDaySessionsSummary(Object hours, Object sessions);

  /// No description provided for @attendanceDayTodayLabel.
  ///
  /// In fr, this message translates to:
  /// **'Aujourd\'hui'**
  String get attendanceDayTodayLabel;

  /// No description provided for @attendanceDayYesterdayLabel.
  ///
  /// In fr, this message translates to:
  /// **'Hier'**
  String get attendanceDayYesterdayLabel;

  /// No description provided for @attendanceDaysAbsentShort.
  ///
  /// In fr, this message translates to:
  /// **'{count} abs.'**
  String attendanceDaysAbsentShort(Object count);

  /// No description provided for @attendanceDaysPresentRatio.
  ///
  /// In fr, this message translates to:
  /// **'{present} jours présents / {working} ouvrés'**
  String attendanceDaysPresentRatio(Object present, Object working);

  /// No description provided for @attendanceDeductionsLabel.
  ///
  /// In fr, this message translates to:
  /// **'Retenues'**
  String get attendanceDeductionsLabel;

  /// No description provided for @attendanceDeductionsSub.
  ///
  /// In fr, this message translates to:
  /// **'Déductions : {amount}'**
  String attendanceDeductionsSub(Object amount);

  /// No description provided for @attendanceEndWork.
  ///
  /// In fr, this message translates to:
  /// **'Terminer le travail'**
  String get attendanceEndWork;

  /// No description provided for @attendanceEstimateDisclaimer.
  ///
  /// In fr, this message translates to:
  /// **'Estimation non officielle. Le bulletin de paie fait foi.'**
  String get attendanceEstimateDisclaimer;

  /// No description provided for @attendanceEstimatedEarnings.
  ///
  /// In fr, this message translates to:
  /// **'Gain estimé'**
  String get attendanceEstimatedEarnings;

  /// No description provided for @attendanceFinish.
  ///
  /// In fr, this message translates to:
  /// **'Terminer'**
  String get attendanceFinish;

  /// No description provided for @attendanceGrossEstimate.
  ///
  /// In fr, this message translates to:
  /// **'Gain brut estimé'**
  String get attendanceGrossEstimate;

  /// No description provided for @attendanceGrossLabel.
  ///
  /// In fr, this message translates to:
  /// **'Brut'**
  String get attendanceGrossLabel;

  /// No description provided for @attendanceHistoryEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Rien ici pour le moment. Vos pointages apparaîtront au fur et à mesure.'**
  String get attendanceHistoryEmpty;

  /// No description provided for @attendanceHoursLabel.
  ///
  /// In fr, this message translates to:
  /// **'Heures'**
  String get attendanceHoursLabel;

  /// No description provided for @attendanceHoursWorkedLabel.
  ///
  /// In fr, this message translates to:
  /// **'Heures travaillées'**
  String get attendanceHoursWorkedLabel;

  /// No description provided for @attendanceIncludedGross.
  ///
  /// In fr, this message translates to:
  /// **'Incluses dans le gain brut'**
  String get attendanceIncludedGross;

  /// No description provided for @attendanceIncludedGrossShort.
  ///
  /// In fr, this message translates to:
  /// **'Incluses brut'**
  String get attendanceIncludedGrossShort;

  /// No description provided for @attendanceInvalidDuration.
  ///
  /// In fr, this message translates to:
  /// **'Durée invalide'**
  String get attendanceInvalidDuration;

  /// No description provided for @attendanceInvalidPayload.
  ///
  /// In fr, this message translates to:
  /// **'Données de pointage invalides.'**
  String get attendanceInvalidPayload;

  /// No description provided for @attendanceLateMinutes.
  ///
  /// In fr, this message translates to:
  /// **'{minutes} min'**
  String attendanceLateMinutes(Object minutes);

  /// No description provided for @attendanceLoadDegradedNotice.
  ///
  /// In fr, this message translates to:
  /// **'Les données du jour prennent plus de temps que prévu. L\'écran reste utilisable, vous pouvez actualiser.'**
  String get attendanceLoadDegradedNotice;

  /// No description provided for @attendanceLoadFailed.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger les données : {error}'**
  String attendanceLoadFailed(Object error);

  /// No description provided for @attendanceMarkDone.
  ///
  /// In fr, this message translates to:
  /// **'Marquer terminée'**
  String get attendanceMarkDone;

  /// No description provided for @attendanceMonthEmptyHint.
  ///
  /// In fr, this message translates to:
  /// **'Si aucune donnée n\'existe encore, un résumé vide sera affiché.'**
  String get attendanceMonthEmptyHint;

  /// No description provided for @attendanceMonthLoadedHint.
  ///
  /// In fr, this message translates to:
  /// **'Le mois est bien chargé. Les gains et heures resteront à zéro tant qu\'aucun pointage valide n\'existe.'**
  String get attendanceMonthLoadedHint;

  /// No description provided for @attendanceMonthSyncing.
  ///
  /// In fr, this message translates to:
  /// **'Synchronisation du mois...'**
  String get attendanceMonthSyncing;

  /// No description provided for @attendanceMyMonth.
  ///
  /// In fr, this message translates to:
  /// **'Mon mois'**
  String get attendanceMyMonth;

  /// No description provided for @attendanceNetEstimate.
  ///
  /// In fr, this message translates to:
  /// **'Net estimé'**
  String get attendanceNetEstimate;

  /// No description provided for @attendanceNextMonth.
  ///
  /// In fr, this message translates to:
  /// **'Mois suivant'**
  String get attendanceNextMonth;

  /// No description provided for @attendanceNoHistory.
  ///
  /// In fr, this message translates to:
  /// **'Aucun historique'**
  String get attendanceNoHistory;

  /// No description provided for @attendanceNoLogToEdit.
  ///
  /// In fr, this message translates to:
  /// **'Aucune ligne de pointage existante à modifier pour ce jour.'**
  String get attendanceNoLogToEdit;

  /// No description provided for @attendanceNoPunchForDay.
  ///
  /// In fr, this message translates to:
  /// **'Aucun pointage enregistré pour cette journée.'**
  String get attendanceNoPunchForDay;

  /// No description provided for @attendanceNoSession.
  ///
  /// In fr, this message translates to:
  /// **'Aucune session'**
  String get attendanceNoSession;

  /// No description provided for @attendanceOutsideZoneManagerNotice.
  ///
  /// In fr, this message translates to:
  /// **'{fallback} Pointage hors zone détecté; contrôlez le contexte avant validation RH.'**
  String attendanceOutsideZoneManagerNotice(Object fallback);

  /// No description provided for @attendanceOutsideZoneNotice.
  ///
  /// In fr, this message translates to:
  /// **'{fallback} Vous semblez hors de la zone autorisée; votre manager sera notifié si la règle entreprise l\'exige.'**
  String attendanceOutsideZoneNotice(Object fallback);

  /// No description provided for @attendanceOvertimeLabel.
  ///
  /// In fr, this message translates to:
  /// **'Heures supplémentaires'**
  String get attendanceOvertimeLabel;

  /// No description provided for @attendanceOvertimeShortLabel.
  ///
  /// In fr, this message translates to:
  /// **'Heures supp'**
  String get attendanceOvertimeShortLabel;

  /// No description provided for @attendancePersonalTracking.
  ///
  /// In fr, this message translates to:
  /// **'Suivi personnel'**
  String get attendancePersonalTracking;

  /// No description provided for @attendancePresence.
  ///
  /// In fr, this message translates to:
  /// **'Présence'**
  String get attendancePresence;

  /// No description provided for @attendancePreviousMonth.
  ///
  /// In fr, this message translates to:
  /// **'Mois précédent'**
  String get attendancePreviousMonth;

  /// No description provided for @attendancePunchFailed.
  ///
  /// In fr, this message translates to:
  /// **'Le pointage n\'a pas pu être confirmé. Vérifiez la connexion puis réessayez.'**
  String get attendancePunchFailed;

  /// No description provided for @attendanceRealCheckinRequired.
  ///
  /// In fr, this message translates to:
  /// **'Arrivée réelle *'**
  String get attendanceRealCheckinRequired;

  /// No description provided for @attendanceRealCheckout.
  ///
  /// In fr, this message translates to:
  /// **'Départ réel'**
  String get attendanceRealCheckout;

  /// No description provided for @attendanceRealTime.
  ///
  /// In fr, this message translates to:
  /// **'Temps réel'**
  String get attendanceRealTime;

  /// No description provided for @attendanceRealTimeHint.
  ///
  /// In fr, this message translates to:
  /// **'Indiquez le temps réel et une note courte avant le départ.'**
  String get attendanceRealTimeHint;

  /// No description provided for @attendanceRefresh.
  ///
  /// In fr, this message translates to:
  /// **'Actualiser'**
  String get attendanceRefresh;

  /// No description provided for @attendanceRequestCorrection.
  ///
  /// In fr, this message translates to:
  /// **'Demander une modification'**
  String get attendanceRequestCorrection;

  /// No description provided for @attendanceRetry.
  ///
  /// In fr, this message translates to:
  /// **'Réessayer'**
  String get attendanceRetry;

  /// No description provided for @attendanceRoleForbidden.
  ///
  /// In fr, this message translates to:
  /// **'Votre rôle ne permet pas cette action de pointage.'**
  String get attendanceRoleForbidden;

  /// No description provided for @attendanceSaveDeparture.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer le départ de cette session'**
  String get attendanceSaveDeparture;

  /// No description provided for @attendanceSeeHistory.
  ///
  /// In fr, this message translates to:
  /// **'Voir l\'historique'**
  String get attendanceSeeHistory;

  /// No description provided for @attendanceSendCheckin.
  ///
  /// In fr, this message translates to:
  /// **'Envoi de l\'arrivée'**
  String get attendanceSendCheckin;

  /// No description provided for @attendanceSendCheckout.
  ///
  /// In fr, this message translates to:
  /// **'Envoi du départ'**
  String get attendanceSendCheckout;

  /// No description provided for @attendanceSendModificationFailed.
  ///
  /// In fr, this message translates to:
  /// **'Impossible d\'envoyer la modification pour le moment.'**
  String get attendanceSendModificationFailed;

  /// No description provided for @attendanceSessionRange.
  ///
  /// In fr, this message translates to:
  /// **'{from} -> {to}'**
  String attendanceSessionRange(Object from, Object to);

  /// No description provided for @attendanceStartDay.
  ///
  /// In fr, this message translates to:
  /// **'Démarrer la journée'**
  String get attendanceStartDay;

  /// No description provided for @attendanceSummaryUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'Résumé indisponible'**
  String get attendanceSummaryUnavailable;

  /// No description provided for @attendanceTask.
  ///
  /// In fr, this message translates to:
  /// **'Tâche'**
  String get attendanceTask;

  /// No description provided for @attendanceTaskDone.
  ///
  /// In fr, this message translates to:
  /// **'Tâche terminée.'**
  String get attendanceTaskDone;

  /// No description provided for @attendanceTaskFailed.
  ///
  /// In fr, this message translates to:
  /// **'Échec : {error}'**
  String attendanceTaskFailed(Object error);

  /// No description provided for @attendanceTaskNote.
  ///
  /// In fr, this message translates to:
  /// **'Note de réalisation'**
  String get attendanceTaskNote;

  /// No description provided for @attendanceTasksSectionTitle.
  ///
  /// In fr, this message translates to:
  /// **'TACHES DU JOUR'**
  String get attendanceTasksSectionTitle;

  /// No description provided for @attendanceTasksSyncing.
  ///
  /// In fr, this message translates to:
  /// **'Synchronisation des tâches du jour...'**
  String get attendanceTasksSyncing;

  /// No description provided for @attendanceToPunch.
  ///
  /// In fr, this message translates to:
  /// **'À pointer'**
  String get attendanceToPunch;

  /// No description provided for @attendanceTotalDays.
  ///
  /// In fr, this message translates to:
  /// **'Total jours'**
  String get attendanceTotalDays;

  /// No description provided for @attendanceTotalHours.
  ///
  /// In fr, this message translates to:
  /// **'Total heures'**
  String get attendanceTotalHours;

  /// No description provided for @attendanceTotalLate.
  ///
  /// In fr, this message translates to:
  /// **'Retard cumulé'**
  String get attendanceTotalLate;

  /// No description provided for @attendanceWorkedTime.
  ///
  /// In fr, this message translates to:
  /// **'Temps travaillé'**
  String get attendanceWorkedTime;

  /// No description provided for @payrollAdvancesDeducted.
  ///
  /// In fr, this message translates to:
  /// **'Avances déduites'**
  String get payrollAdvancesDeducted;

  /// No description provided for @payrollAlreadyPaid.
  ///
  /// In fr, this message translates to:
  /// **'Déjà payé'**
  String get payrollAlreadyPaid;

  /// No description provided for @payrollBalanceUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'Solde temporairement indisponible'**
  String get payrollBalanceUnavailable;

  /// No description provided for @payrollDocsUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'Documents temporairement indisponibles'**
  String get payrollDocsUnavailable;

  /// No description provided for @payrollDocumentDownloaded.
  ///
  /// In fr, this message translates to:
  /// **'Document téléchargé : {path}'**
  String payrollDocumentDownloaded(Object path);

  /// No description provided for @payrollDownloadPayslip.
  ///
  /// In fr, this message translates to:
  /// **'Télécharger le bulletin PDF'**
  String get payrollDownloadPayslip;

  /// No description provided for @payrollDownloading.
  ///
  /// In fr, this message translates to:
  /// **'Téléchargement en cours'**
  String get payrollDownloading;

  /// No description provided for @payrollEmptyHint.
  ///
  /// In fr, this message translates to:
  /// **'Vos fiches de paie apparaîtront ici dès qu\'elles seront validées.'**
  String get payrollEmptyHint;

  /// No description provided for @payrollError.
  ///
  /// In fr, this message translates to:
  /// **'Erreur : {error}'**
  String payrollError(Object error);

  /// No description provided for @payrollLoading.
  ///
  /// In fr, this message translates to:
  /// **'Chargement des fiches de paie'**
  String get payrollLoading;

  /// No description provided for @payrollMonthLabel.
  ///
  /// In fr, this message translates to:
  /// **'Mois {month}/{year}'**
  String payrollMonthLabel(Object month, Object year);

  /// No description provided for @payrollMyBalance.
  ///
  /// In fr, this message translates to:
  /// **'Mon solde'**
  String get payrollMyBalance;

  /// No description provided for @payrollNextPayment.
  ///
  /// In fr, this message translates to:
  /// **'Prochaine paie prévue le {date}'**
  String payrollNextPayment(Object date);

  /// No description provided for @payrollNoCycleDocuments.
  ///
  /// In fr, this message translates to:
  /// **'Aucun document généré pour ce cycle. Les reçus apparaîtront après paiement.'**
  String get payrollNoCycleDocuments;

  /// No description provided for @payrollNoPayslips.
  ///
  /// In fr, this message translates to:
  /// **'Aucune fiche de paie'**
  String get payrollNoPayslips;

  /// No description provided for @payrollNoReceipts.
  ///
  /// In fr, this message translates to:
  /// **'Aucun reçu ou bordereau disponible pour le moment.'**
  String get payrollNoReceipts;

  /// No description provided for @payrollOvertimeLabel.
  ///
  /// In fr, this message translates to:
  /// **'heures supp'**
  String get payrollOvertimeLabel;

  /// No description provided for @payrollPaymentDocuments.
  ///
  /// In fr, this message translates to:
  /// **'Documents paiement'**
  String get payrollPaymentDocuments;

  /// No description provided for @payrollPaymentDocumentsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Documents de paiement'**
  String get payrollPaymentDocumentsTitle;

  /// No description provided for @payrollPdfDownloaded.
  ///
  /// In fr, this message translates to:
  /// **'PDF téléchargé : {path}'**
  String payrollPdfDownloaded(Object path);

  /// No description provided for @payrollPeriodRange.
  ///
  /// In fr, this message translates to:
  /// **'{start} - {end}'**
  String payrollPeriodRange(Object end, Object start);

  /// No description provided for @payrollRecentPayslips.
  ///
  /// In fr, this message translates to:
  /// **'Bulletins récents'**
  String get payrollRecentPayslips;

  /// No description provided for @payrollRemainingToPay.
  ///
  /// In fr, this message translates to:
  /// **'Reste à payer'**
  String get payrollRemainingToPay;

  /// No description provided for @payrollRemainingToReceive.
  ///
  /// In fr, this message translates to:
  /// **'Reste à recevoir'**
  String get payrollRemainingToReceive;

  /// No description provided for @payrollSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Solde courant, avances et bulletins'**
  String get payrollSubtitle;

  /// No description provided for @payrollSummaryUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'Résumé paie temporairement indisponible'**
  String get payrollSummaryUnavailable;

  /// No description provided for @payrollTeamBalance.
  ///
  /// In fr, this message translates to:
  /// **'Solde équipe'**
  String get payrollTeamBalance;

  /// No description provided for @payrollTeamMembers.
  ///
  /// In fr, this message translates to:
  /// **'{count} collaborateur(s)'**
  String payrollTeamMembers(Object count);

  /// No description provided for @payrollTeamSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Soldes, avances et bulletins'**
  String get payrollTeamSubtitle;

  /// No description provided for @payrollTeamTitle.
  ///
  /// In fr, this message translates to:
  /// **'Paie équipe'**
  String get payrollTeamTitle;

  /// No description provided for @payrollTitle.
  ///
  /// In fr, this message translates to:
  /// **'Paie et solde'**
  String get payrollTitle;

  /// No description provided for @payrollValidatedHint.
  ///
  /// In fr, this message translates to:
  /// **'Les bulletins valides apparaîtront ici après traitement.'**
  String get payrollValidatedHint;

  /// No description provided for @smartAttendanceActiveMode.
  ///
  /// In fr, this message translates to:
  /// **'Mode actif'**
  String get smartAttendanceActiveMode;

  /// No description provided for @smartAttendanceApprove.
  ///
  /// In fr, this message translates to:
  /// **'Approuver'**
  String get smartAttendanceApprove;

  /// No description provided for @smartAttendanceCancel.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get smartAttendanceCancel;

  /// No description provided for @smartAttendanceChangeMode.
  ///
  /// In fr, this message translates to:
  /// **'Changer mon mode de pointage'**
  String get smartAttendanceChangeMode;

  /// No description provided for @smartAttendanceConfirm.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer'**
  String get smartAttendanceConfirm;

  /// No description provided for @smartAttendanceDashboard.
  ///
  /// In fr, this message translates to:
  /// **'Pointage GPS — tableau de bord'**
  String get smartAttendanceDashboard;

  /// No description provided for @smartAttendanceDashboardTitle.
  ///
  /// In fr, this message translates to:
  /// **'Pointage GPS — tableau de bord'**
  String get smartAttendanceDashboardTitle;

  /// No description provided for @smartAttendanceError.
  ///
  /// In fr, this message translates to:
  /// **'Erreur : {message}'**
  String smartAttendanceError(Object message);

  /// No description provided for @smartAttendanceForced.
  ///
  /// In fr, this message translates to:
  /// **'Imposé'**
  String get smartAttendanceForced;

  /// No description provided for @smartAttendanceGpsAuto.
  ///
  /// In fr, this message translates to:
  /// **'GPS Automatique'**
  String get smartAttendanceGpsAuto;

  /// No description provided for @smartAttendanceGpsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Smart Attendance — GPS'**
  String get smartAttendanceGpsTitle;

  /// No description provided for @smartAttendanceManual.
  ///
  /// In fr, this message translates to:
  /// **'Manuel'**
  String get smartAttendanceManual;

  /// No description provided for @smartAttendanceNoGpsSessions.
  ///
  /// In fr, this message translates to:
  /// **'Aucune session GPS pour le moment.'**
  String get smartAttendanceNoGpsSessions;

  /// No description provided for @smartAttendanceNoPending.
  ///
  /// In fr, this message translates to:
  /// **'Aucune session en attente'**
  String get smartAttendanceNoPending;

  /// No description provided for @smartAttendanceNoPendingSessions.
  ///
  /// In fr, this message translates to:
  /// **'Aucune session en attente de validation'**
  String get smartAttendanceNoPendingSessions;

  /// No description provided for @smartAttendancePending.
  ///
  /// In fr, this message translates to:
  /// **'En attente'**
  String get smartAttendancePending;

  /// No description provided for @smartAttendancePendingCount.
  ///
  /// In fr, this message translates to:
  /// **'{count} en attente'**
  String smartAttendancePendingCount(Object count);

  /// No description provided for @smartAttendanceQr.
  ///
  /// In fr, this message translates to:
  /// **'QR Code'**
  String get smartAttendanceQr;

  /// No description provided for @smartAttendanceReject.
  ///
  /// In fr, this message translates to:
  /// **'Rejeter'**
  String get smartAttendanceReject;

  /// No description provided for @smartAttendanceRejectHint.
  ///
  /// In fr, this message translates to:
  /// **'Expliquez la raison du rejet...'**
  String get smartAttendanceRejectHint;

  /// No description provided for @smartAttendanceRejectReason.
  ///
  /// In fr, this message translates to:
  /// **'Motif du rejet'**
  String get smartAttendanceRejectReason;

  /// No description provided for @smartAttendanceSessionExit.
  ///
  /// In fr, this message translates to:
  /// **'Sortie : {time} · {duration}'**
  String smartAttendanceSessionExit(Object duration, Object time);

  /// No description provided for @smartAttendanceSessionsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Sessions Smart Attendance'**
  String get smartAttendanceSessionsTitle;

  /// No description provided for @smartAttendanceSmart.
  ///
  /// In fr, this message translates to:
  /// **'Pointage Intelligent'**
  String get smartAttendanceSmart;

  /// No description provided for @smartAttendanceSurveillanceActive.
  ///
  /// In fr, this message translates to:
  /// **'Surveillance active'**
  String get smartAttendanceSurveillanceActive;

  /// No description provided for @smartAttendanceSurveillanceInactive.
  ///
  /// In fr, this message translates to:
  /// **'Surveillance inactive'**
  String get smartAttendanceSurveillanceInactive;

  /// No description provided for @smartAttendanceTapToReview.
  ///
  /// In fr, this message translates to:
  /// **'Appuyez pour valider ou rejeter'**
  String get smartAttendanceTapToReview;

  /// No description provided for @smartAttendanceTitle.
  ///
  /// In fr, this message translates to:
  /// **'Smart Attendance'**
  String get smartAttendanceTitle;

  /// No description provided for @smartAttendanceTodayTitle.
  ///
  /// In fr, this message translates to:
  /// **'Aujourd\'hui — {date}'**
  String smartAttendanceTodayTitle(Object date);

  /// No description provided for @smartAttendanceZoneSurveillance.
  ///
  /// In fr, this message translates to:
  /// **'Surveillance de zone'**
  String get smartAttendanceZoneSurveillance;

  /// No description provided for @attendanceToProcess.
  ///
  /// In fr, this message translates to:
  /// **'A traiter'**
  String get attendanceToProcess;

  /// No description provided for @settingsBiometryEnableFirst.
  ///
  /// In fr, this message translates to:
  /// **'Active d abord la preparation biometrie.'**
  String get settingsBiometryEnableFirst;

  /// No description provided for @settingsBiometryEnableAction.
  ///
  /// In fr, this message translates to:
  /// **'Activer la preparation biometrie'**
  String get settingsBiometryEnableAction;

  /// No description provided for @settingsEdgeNodeAddress.
  ///
  /// In fr, this message translates to:
  /// **'Adresse du noeud Edge'**
  String get settingsEdgeNodeAddress;

  /// No description provided for @settingsBiometryAddFaceCapture.
  ///
  /// In fr, this message translates to:
  /// **'Ajoute une capture visage avant soumission.'**
  String get settingsBiometryAddFaceCapture;

  /// No description provided for @notifTitle.
  ///
  /// In fr, this message translates to:
  /// **'Alertes RH, paie et validations'**
  String get notifTitle;

  /// No description provided for @settingsPushInApp.
  ///
  /// In fr, this message translates to:
  /// **'Alertes dans l application'**
  String get settingsPushInApp;

  /// No description provided for @attendanceAnalyzingAnomalies.
  ///
  /// In fr, this message translates to:
  /// **'Analyse des anomalies...'**
  String get attendanceAnalyzingAnomalies;

  /// No description provided for @settingsEdgePairingRemoved.
  ///
  /// In fr, this message translates to:
  /// **'Appairage Edge supprime.'**
  String get settingsEdgePairingRemoved;

  /// No description provided for @teamQrNoneInClipboard.
  ///
  /// In fr, this message translates to:
  /// **'Aucun code QR dans le presse-papiers.'**
  String get teamQrNoneInClipboard;

  /// No description provided for @teamNoScheduleYet.
  ///
  /// In fr, this message translates to:
  /// **'Aucun horaire cree. Vous pourrez en definir dans le module Horaires.'**
  String get teamNoScheduleYet;

  /// No description provided for @attendanceNoPunchToday.
  ///
  /// In fr, this message translates to:
  /// **'Aucun pointage aujourd hui'**
  String get attendanceNoPunchToday;

  /// No description provided for @attendanceNoRecentAnomalies.
  ///
  /// In fr, this message translates to:
  /// **'Aucune anomalie recente'**
  String get attendanceNoRecentAnomalies;

  /// No description provided for @teamNoPendingInvites.
  ///
  /// In fr, this message translates to:
  /// **'Aucune invitation en cours'**
  String get teamNoPendingInvites;

  /// No description provided for @settingsLockerDocsAdmin.
  ///
  /// In fr, this message translates to:
  /// **'CV, contrats, diplomes et documents administratifs.'**
  String get settingsLockerDocsAdmin;

  /// No description provided for @settingsLockerDocsVisibility.
  ///
  /// In fr, this message translates to:
  /// **'CV, contrats, diplomes et documents avec visibilite controlee.'**
  String get settingsLockerDocsVisibility;

  /// No description provided for @settingsNotifChannelChat.
  ///
  /// In fr, this message translates to:
  /// **'Canal conversationnel, necessite votre opt-in explicite.'**
  String get settingsNotifChannelChat;

  /// No description provided for @settingsNotifChannelSms.
  ///
  /// In fr, this message translates to:
  /// **'Canal court reserve aux urgences, actif apres opt-in.'**
  String get settingsNotifChannelSms;

  /// No description provided for @settingsNotifChannelsSummary.
  ///
  /// In fr, this message translates to:
  /// **'Canaux, heures calmes et alertes operationnelles.'**
  String get settingsNotifChannelsSummary;

  /// No description provided for @settingsBiometryCaptureFace.
  ///
  /// In fr, this message translates to:
  /// **'Capturer / choisir mon visage'**
  String get settingsBiometryCaptureFace;

  /// No description provided for @attendanceEmployeeNotPunchedToday.
  ///
  /// In fr, this message translates to:
  /// **'Cet employe n a pas encore pointe pour la journee en cours.'**
  String get attendanceEmployeeNotPunchedToday;

  /// No description provided for @settingsFieldRequired.
  ///
  /// In fr, this message translates to:
  /// **'Champ requis'**
  String get settingsFieldRequired;

  /// No description provided for @attendanceLoadingRequests.
  ///
  /// In fr, this message translates to:
  /// **'Chargement des demandes...'**
  String get attendanceLoadingRequests;

  /// No description provided for @teamLoadingInvites.
  ///
  /// In fr, this message translates to:
  /// **'Chargement des invitations'**
  String get teamLoadingInvites;

  /// No description provided for @attendanceLoadingEmployeeDetail.
  ///
  /// In fr, this message translates to:
  /// **'Chargement du detail employe...'**
  String get attendanceLoadingEmployeeDetail;

  /// No description provided for @settingsNotifChannelsHint.
  ///
  /// In fr, this message translates to:
  /// **'Choisissez les canaux utiles sans perdre les alertes RH importantes.'**
  String get settingsNotifChannelsHint;

  /// No description provided for @teamEmployeeQrCode.
  ///
  /// In fr, this message translates to:
  /// **'Code QR employe'**
  String get teamEmployeeQrCode;

  /// No description provided for @settingsPasteCompanyQr.
  ///
  /// In fr, this message translates to:
  /// **'Coller le QR entreprise'**
  String get settingsPasteCompanyQr;

  /// No description provided for @settingsPasteManagerQr.
  ///
  /// In fr, this message translates to:
  /// **'Coller le QR fourni par le manager'**
  String get settingsPasteManagerQr;

  /// No description provided for @teamPasteScannedQr.
  ///
  /// In fr, this message translates to:
  /// **'Coller le QR scanne'**
  String get teamPasteScannedQr;

  /// No description provided for @settingsPasteCompanyQrHint.
  ///
  /// In fr, this message translates to:
  /// **'Collez le QR entreprise.'**
  String get settingsPasteCompanyQrHint;

  /// No description provided for @teamPasteQrHint.
  ///
  /// In fr, this message translates to:
  /// **'Collez le code QR.'**
  String get teamPasteQrHint;

  /// No description provided for @settingsBiometryConfirmIdentity.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer votre identite pour soumettre votre demande biometrie'**
  String get settingsBiometryConfirmIdentity;

  /// No description provided for @settingsEdgeConnectedCloud.
  ///
  /// In fr, this message translates to:
  /// **'Connecte au Cloud'**
  String get settingsEdgeConnectedCloud;

  /// No description provided for @settingsEdgeConnectedLocal.
  ///
  /// In fr, this message translates to:
  /// **'Connecte au noeud Edge local'**
  String get settingsEdgeConnectedLocal;

  /// No description provided for @settingsBiometryConsentTitle.
  ///
  /// In fr, this message translates to:
  /// **'Consentement au futur pointage biometrie'**
  String get settingsBiometryConsentTitle;

  /// No description provided for @attendanceCorrectionAppliedToast.
  ///
  /// In fr, this message translates to:
  /// **'Correction appliquee.'**
  String get attendanceCorrectionAppliedToast;

  /// No description provided for @attendanceCorrectionRejected.
  ///
  /// In fr, this message translates to:
  /// **'Correction refusee.'**
  String get attendanceCorrectionRejected;

  /// No description provided for @teamCreateFromQrAndInvite.
  ///
  /// In fr, this message translates to:
  /// **'Creer depuis QR et inviter'**
  String get teamCreateFromQrAndInvite;

  /// No description provided for @teamHireDate.
  ///
  /// In fr, this message translates to:
  /// **'Date d embauche'**
  String get teamHireDate;

  /// No description provided for @settingsRequestSent.
  ///
  /// In fr, this message translates to:
  /// **'Demande envoyee'**
  String get settingsRequestSent;

  /// No description provided for @settingsBiometryRequestSentHint.
  ///
  /// In fr, this message translates to:
  /// **'Demande envoyee au manager / RH pour validation.'**
  String get settingsBiometryRequestSentHint;

  /// No description provided for @settingsRequestJoin.
  ///
  /// In fr, this message translates to:
  /// **'Demander l integration'**
  String get settingsRequestJoin;

  /// No description provided for @attendanceEmployeeRequestsPending.
  ///
  /// In fr, this message translates to:
  /// **'Demandes employees en attente RH'**
  String get attendanceEmployeeRequestsPending;

  /// No description provided for @teamDepartmentOptional.
  ///
  /// In fr, this message translates to:
  /// **'Departement (optionnel)'**
  String get teamDepartmentOptional;

  /// No description provided for @settingsAvailableForNewCompany.
  ///
  /// In fr, this message translates to:
  /// **'Disponible pour une nouvelle entreprise'**
  String get settingsAvailableForNewCompany;

  /// No description provided for @settingsRecoveryEmailLabel.
  ///
  /// In fr, this message translates to:
  /// **'Email de recuperation'**
  String get settingsRecoveryEmailLabel;

  /// No description provided for @settingsPersonalEmailLabel.
  ///
  /// In fr, this message translates to:
  /// **'Email personnel'**
  String get settingsPersonalEmailLabel;

  /// No description provided for @teamEmployeeAdded.
  ///
  /// In fr, this message translates to:
  /// **'Employe ajoute.'**
  String get teamEmployeeAdded;

  /// No description provided for @settingsBiometryFingerprintDesired.
  ///
  /// In fr, this message translates to:
  /// **'Empreinte digitale souhaitee'**
  String get settingsBiometryFingerprintDesired;

  /// No description provided for @settingsBiometrySaveEnrollment.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer la preparation'**
  String get settingsBiometrySaveEnrollment;

  /// No description provided for @teamSendInvite.
  ///
  /// In fr, this message translates to:
  /// **'Envoyer l invitation'**
  String get teamSendInvite;

  /// No description provided for @settingsBiometryFpExample.
  ///
  /// In fr, this message translates to:
  /// **'Exemple: FP-ENTREE-01 ou matricule biometrie'**
  String get settingsBiometryFpExample;

  /// No description provided for @teamEmployeeRecordUpdated.
  ///
  /// In fr, this message translates to:
  /// **'Fiche collaborateur mise a jour.'**
  String get teamEmployeeRecordUpdated;

  /// No description provided for @attendanceEmptyCorrectionQueue.
  ///
  /// In fr, this message translates to:
  /// **'File de correction vide'**
  String get attendanceEmptyCorrectionQueue;

  /// No description provided for @settingsEdgeTokenFromAdmin.
  ///
  /// In fr, this message translates to:
  /// **'Fourni par votre administrateur'**
  String get settingsEdgeTokenFromAdmin;

  /// No description provided for @settingsEdgeTokenOneTime.
  ///
  /// In fr, this message translates to:
  /// **'Fourni une seule fois a l enregistrement'**
  String get settingsEdgeTokenOneTime;

  /// No description provided for @settingsQuietHours.
  ///
  /// In fr, this message translates to:
  /// **'Heures calmes'**
  String get settingsQuietHours;

  /// No description provided for @settingsJourneyHint.
  ///
  /// In fr, this message translates to:
  /// **'Historique entreprise, poste, statut et disponibilite.'**
  String get settingsJourneyHint;

  /// No description provided for @teamWorkSchedule.
  ///
  /// In fr, this message translates to:
  /// **'Horaire de travail'**
  String get teamWorkSchedule;

  /// No description provided for @teamDefaultSchedule.
  ///
  /// In fr, this message translates to:
  /// **'Horaire par defaut'**
  String get teamDefaultSchedule;

  /// No description provided for @commonOffline.
  ///
  /// In fr, this message translates to:
  /// **'Hors ligne'**
  String get commonOffline;

  /// No description provided for @settingsBiometrySensorId.
  ///
  /// In fr, this message translates to:
  /// **'Identifiant capteur empreinte / borne'**
  String get settingsBiometrySensorId;

  /// No description provided for @settingsEdgeNodeId.
  ///
  /// In fr, this message translates to:
  /// **'Identifiant du noeud (UUID)'**
  String get settingsEdgeNodeId;

  /// No description provided for @settingsPortableIdentity.
  ///
  /// In fr, this message translates to:
  /// **'Identite portable'**
  String get settingsPortableIdentity;

  /// No description provided for @settingsBiometryFaceSelected.
  ///
  /// In fr, this message translates to:
  /// **'Image visage selectionnee'**
  String get settingsBiometryFaceSelected;

  /// No description provided for @teamImportFromQr.
  ///
  /// In fr, this message translates to:
  /// **'Importer depuis QR'**
  String get teamImportFromQr;

  /// No description provided for @teamInviteResent.
  ///
  /// In fr, this message translates to:
  /// **'Invitation renvoyee.'**
  String get teamInviteResent;

  /// No description provided for @settingsEdgeToken.
  ///
  /// In fr, this message translates to:
  /// **'Jeton Edge'**
  String get settingsEdgeToken;

  /// No description provided for @settingsPasswordConfirmationMismatch.
  ///
  /// In fr, this message translates to:
  /// **'La confirmation ne correspond pas'**
  String get settingsPasswordConfirmationMismatch;

  /// No description provided for @settingsNotifLanguage.
  ///
  /// In fr, this message translates to:
  /// **'Langue des notifications'**
  String get settingsNotifLanguage;

  /// No description provided for @settingsBiometryConsentRequired.
  ///
  /// In fr, this message translates to:
  /// **'Le consentement est requis avant toute soumission.'**
  String get settingsBiometryConsentRequired;

  /// No description provided for @settingsQrManagerScanHint.
  ///
  /// In fr, this message translates to:
  /// **'Le manager le scanne pour pre-remplir une invitation.'**
  String get settingsQrManagerScanHint;

  /// No description provided for @teamWorkLocation.
  ///
  /// In fr, this message translates to:
  /// **'Lieu de travail'**
  String get teamWorkLocation;

  /// No description provided for @teamWorkLocationOptional.
  ///
  /// In fr, this message translates to:
  /// **'Lieu de travail (optionnel)'**
  String get teamWorkLocationOptional;

  /// No description provided for @settingsQuietHoursHint.
  ///
  /// In fr, this message translates to:
  /// **'Limiter les canaux externes hors horaires.'**
  String get settingsQuietHoursHint;

  /// No description provided for @teamReadAndPrefill.
  ///
  /// In fr, this message translates to:
  /// **'Lire et pre-remplir'**
  String get teamReadAndPrefill;

  /// No description provided for @notifMarkAsRead.
  ///
  /// In fr, this message translates to:
  /// **'Marquer comme lue'**
  String get notifMarkAsRead;

  /// No description provided for @teamEmployeeIdOptional.
  ///
  /// In fr, this message translates to:
  /// **'Matricule (optionnel)'**
  String get teamEmployeeIdOptional;

  /// No description provided for @teamMonthlyFixed.
  ///
  /// In fr, this message translates to:
  /// **'Mensuel / fixe'**
  String get teamMonthlyFixed;

  /// No description provided for @settingsPasswordUpdateTitle.
  ///
  /// In fr, this message translates to:
  /// **'Mettre a jour le mot de passe'**
  String get settingsPasswordUpdateTitle;

  /// No description provided for @settingsPasswordMinCharacters.
  ///
  /// In fr, this message translates to:
  /// **'Minimum 8 caracteres'**
  String get settingsPasswordMinCharacters;

  /// No description provided for @teamSalaryMode.
  ///
  /// In fr, this message translates to:
  /// **'Mode salaire'**
  String get teamSalaryMode;

  /// No description provided for @settingsMyEmployeeQr.
  ///
  /// In fr, this message translates to:
  /// **'Mon QR employe'**
  String get settingsMyEmployeeQr;

  /// No description provided for @teamAmountRequired.
  ///
  /// In fr, this message translates to:
  /// **'Montant obligatoire'**
  String get teamAmountRequired;

  /// No description provided for @settingsPasswordUpdated.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe mis a jour.'**
  String get settingsPasswordUpdated;

  /// No description provided for @settingsEdgeNodeLocal.
  ///
  /// In fr, this message translates to:
  /// **'Noeud Edge (reseau local)'**
  String get settingsEdgeNodeLocal;

  /// No description provided for @settingsBiometryNotesConsent.
  ///
  /// In fr, this message translates to:
  /// **'Notes et consentement'**
  String get settingsBiometryNotesConsent;

  /// No description provided for @settingsPushImmediateHint.
  ///
  /// In fr, this message translates to:
  /// **'Notifications immediates sur ce telephone.'**
  String get settingsPushImmediateHint;

  /// No description provided for @teamNewEmployee.
  ///
  /// In fr, this message translates to:
  /// **'Nouvel employe'**
  String get teamNewEmployee;

  /// No description provided for @teamNewEmployeeViaQr.
  ///
  /// In fr, this message translates to:
  /// **'Nouvel employe via QR'**
  String get teamNewEmployeeViaQr;

  /// No description provided for @settingsRecoveryEmailOptionalHint.
  ///
  /// In fr, this message translates to:
  /// **'Optionnel pour recuperer l acces'**
  String get settingsRecoveryEmailOptionalHint;

  /// No description provided for @settingsPersonalEmailHint.
  ///
  /// In fr, this message translates to:
  /// **'Optionnel, conserve votre compte hors entreprise'**
  String get settingsPersonalEmailHint;

  /// No description provided for @settingsPhoneHint.
  ///
  /// In fr, this message translates to:
  /// **'Optionnel, visible selon vos choix futurs'**
  String get settingsPhoneHint;

  /// No description provided for @settingsOpenMyLocker.
  ///
  /// In fr, this message translates to:
  /// **'Ouvrir mon placard'**
  String get settingsOpenMyLocker;

  /// No description provided for @settingsShareProfileOrScan.
  ///
  /// In fr, this message translates to:
  /// **'Partager votre profil ou scanner une entreprise.'**
  String get settingsShareProfileOrScan;

  /// No description provided for @settingsShareProfileOrScanQr.
  ///
  /// In fr, this message translates to:
  /// **'Partagez votre profil ou scannez le QR d une entreprise.'**
  String get settingsShareProfileOrScanQr;

  /// No description provided for @settingsDigitalLocker.
  ///
  /// In fr, this message translates to:
  /// **'Placard numerique'**
  String get settingsDigitalLocker;

  /// No description provided for @attendanceTodayPunchesOpenSessions.
  ///
  /// In fr, this message translates to:
  /// **'Pointages du jour et sessions ouvertes'**
  String get attendanceTodayPunchesOpenSessions;

  /// No description provided for @teamPositionOptional.
  ///
  /// In fr, this message translates to:
  /// **'Poste (optionnel)'**
  String get teamPositionOptional;

  /// No description provided for @settingsNotifPrefsUpdated.
  ///
  /// In fr, this message translates to:
  /// **'Preferences notifications mises a jour.'**
  String get settingsNotifPrefsUpdated;

  /// No description provided for @settingsBiometryEnrollment.
  ///
  /// In fr, this message translates to:
  /// **'Preparation biometrie'**
  String get settingsBiometryEnrollment;

  /// No description provided for @settingsBiometrySavedLocally.
  ///
  /// In fr, this message translates to:
  /// **'Preparation biometrie enregistree localement.'**
  String get settingsBiometrySavedLocally;

  /// No description provided for @settingsBiometryEnrollHint.
  ///
  /// In fr, this message translates to:
  /// **'Preparer doigt et visage pour les bornes terrain.'**
  String get settingsBiometryEnrollHint;

  /// No description provided for @attendanceTeamPresence.
  ///
  /// In fr, this message translates to:
  /// **'Presences equipe'**
  String get attendanceTeamPresence;

  /// No description provided for @settingsProfileUpdated.
  ///
  /// In fr, this message translates to:
  /// **'Profil mis a jour.'**
  String get settingsProfileUpdated;

  /// No description provided for @settingsPushMobile.
  ///
  /// In fr, this message translates to:
  /// **'Push mobile'**
  String get settingsPushMobile;

  /// No description provided for @commonCompanyQr.
  ///
  /// In fr, this message translates to:
  /// **'QR entreprise'**
  String get commonCompanyQr;

  /// No description provided for @teamCompanyQrScannable.
  ///
  /// In fr, this message translates to:
  /// **'QR entreprise scannable'**
  String get teamCompanyQrScannable;

  /// No description provided for @settingsQrUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'QR indisponible pour le moment.'**
  String get settingsQrUnavailable;

  /// No description provided for @settingsQrOnboarding.
  ///
  /// In fr, this message translates to:
  /// **'QR onboarding'**
  String get settingsQrOnboarding;

  /// No description provided for @settingsQrProfessional.
  ///
  /// In fr, this message translates to:
  /// **'QR professionnel'**
  String get settingsQrProfessional;

  /// No description provided for @settingsEdgeLogoutHint.
  ///
  /// In fr, this message translates to:
  /// **'Quitter proprement cet espace sur ce telephone.'**
  String get settingsEdgeLogoutHint;

  /// No description provided for @teamReloadSchedules.
  ///
  /// In fr, this message translates to:
  /// **'Recharger les horaires'**
  String get teamReloadSchedules;

  /// No description provided for @settingsBiometryFaceRecognitionDesired.
  ///
  /// In fr, this message translates to:
  /// **'Reconnaissance faciale souhaitee'**
  String get settingsBiometryFaceRecognitionDesired;

  /// No description provided for @settingsNotifSummaryHint.
  ///
  /// In fr, this message translates to:
  /// **'Resume et confirmations importantes.'**
  String get settingsNotifSummaryHint;

  /// No description provided for @attendanceLateMissedToCheck.
  ///
  /// In fr, this message translates to:
  /// **'Retards, oublis et pointages a verifier'**
  String get attendanceLateMissedToCheck;

  /// No description provided for @teamBaseSalary.
  ///
  /// In fr, this message translates to:
  /// **'Salaire de base'**
  String get teamBaseSalary;

  /// No description provided for @teamDailySalary.
  ///
  /// In fr, this message translates to:
  /// **'Salaire journalier'**
  String get teamDailySalary;

  /// No description provided for @teamMonthlyGrossSalary.
  ///
  /// In fr, this message translates to:
  /// **'Salaire mensuel brut'**
  String get teamMonthlyGrossSalary;

  /// No description provided for @teamSelectType.
  ///
  /// In fr, this message translates to:
  /// **'Selectionnez un type'**
  String get teamSelectType;

  /// No description provided for @attendanceTodaySessions.
  ///
  /// In fr, this message translates to:
  /// **'Sessions du jour'**
  String get attendanceTodaySessions;

  /// No description provided for @settingsBiometrySubmit.
  ///
  /// In fr, this message translates to:
  /// **'Soumettre au manager / RH'**
  String get settingsBiometrySubmit;

  /// No description provided for @attendanceSyncingPresence.
  ///
  /// In fr, this message translates to:
  /// **'Synchronisation des presences...'**
  String get attendanceSyncingPresence;

  /// No description provided for @settingsNotifTasksHint.
  ///
  /// In fr, this message translates to:
  /// **'Taches, decisions RH, pointage et rappels.'**
  String get settingsNotifTasksHint;

  /// No description provided for @teamHourlyRate.
  ///
  /// In fr, this message translates to:
  /// **'Taux horaire'**
  String get teamHourlyRate;

  /// No description provided for @settingsPersonalPhoneLabel.
  ///
  /// In fr, this message translates to:
  /// **'Telephone personnel'**
  String get settingsPersonalPhoneLabel;

  /// No description provided for @notifMarkAllAsRead.
  ///
  /// In fr, this message translates to:
  /// **'Tout marquer comme lu'**
  String get notifMarkAllAsRead;

  /// No description provided for @teamManagerType.
  ///
  /// In fr, this message translates to:
  /// **'Type de manager'**
  String get teamManagerType;

  /// No description provided for @teamPayType.
  ///
  /// In fr, this message translates to:
  /// **'Type de paie'**
  String get teamPayType;

  /// No description provided for @settingsBiometryLocalCheckCancelled.
  ///
  /// In fr, this message translates to:
  /// **'Verification biometrie locale annulee.'**
  String get settingsBiometryLocalCheckCancelled;

  /// No description provided for @settingsViewMyProfile.
  ///
  /// In fr, this message translates to:
  /// **'Voir mon profil'**
  String get settingsViewMyProfile;

  /// No description provided for @notifUpToDate.
  ///
  /// In fr, this message translates to:
  /// **'Vous etes a jour. Cette page se rafraichit automatiquement.'**
  String get notifUpToDate;

  /// No description provided for @teamOperationalView.
  ///
  /// In fr, this message translates to:
  /// **'Vue operationnelle'**
  String get teamOperationalView;

  /// No description provided for @commonLanguageArabic.
  ///
  /// In fr, this message translates to:
  /// **'العربية'**
  String get commonLanguageArabic;

  /// No description provided for @settingsPrefSyncAccount.
  ///
  /// In fr, this message translates to:
  /// **'Cette preference est synchronisee avec votre compte et pilote aussi le mode RTL.'**
  String get settingsPrefSyncAccount;

  /// No description provided for @settingsPasswordModernizeHint.
  ///
  /// In fr, this message translates to:
  /// **'Changez votre mot de passe avant les prochaines etapes de modernisation.'**
  String get settingsPasswordModernizeHint;

  /// No description provided for @teamPasteEmployeeQrHint.
  ///
  /// In fr, this message translates to:
  /// **'Collez le code QR employe. Le formulaire restera modifiable avant invitation.'**
  String get teamPasteEmployeeQrHint;

  /// No description provided for @teamInviteSummary.
  ///
  /// In fr, this message translates to:
  /// **'Invitation, role, date d embauche et base salariale sont envoyes a l API.'**
  String get teamInviteSummary;

  /// No description provided for @teamQrEmployeeScanHint.
  ///
  /// In fr, this message translates to:
  /// **'L employe le scanne depuis son espace compte pour demander son integration.'**
  String get teamQrEmployeeScanHint;

  /// No description provided for @settingsBiometryFaceHint.
  ///
  /// In fr, this message translates to:
  /// **'Le visage peut etre capture depuis le mobile puis soumis a validation manager / RH. Pour l empreinte, Android/iOS permettent de verifier localement que vous utilisez bien un doigt enregistre, mais ne donnent pas acces au gabarit brut; l activation effective cote pointage restera donc approuvee puis exploitee par la borne entreprise.'**
  String get settingsBiometryFaceHint;

  /// No description provided for @attendanceAnomaliesHint.
  ///
  /// In fr, this message translates to:
  /// **'Les alertes de pointage, sorties manquantes et heures supplementaires apparaitront ici.'**
  String get attendanceAnomaliesHint;

  /// No description provided for @attendanceRequestsHint.
  ///
  /// In fr, this message translates to:
  /// **'Les demandes envoyees depuis les trois points du pointage seront listees ici.'**
  String get attendanceRequestsHint;

  /// No description provided for @teamInvitesHint.
  ///
  /// In fr, this message translates to:
  /// **'Les invitations envoyees a vos futurs collaborateurs s afficheront ici.'**
  String get teamInvitesHint;

  /// No description provided for @attendanceTeamPunchesHint.
  ///
  /// In fr, this message translates to:
  /// **'Les pointages equipe apparaitront ici des qu ils arrivent depuis mobile ou kiosque.'**
  String get attendanceTeamPunchesHint;

  /// No description provided for @settingsEdgeOptionalHint.
  ///
  /// In fr, this message translates to:
  /// **'Optionnel: pointer vers un serveur Edge installe sur site pour pointer sans Internet.'**
  String get settingsEdgeOptionalHint;

  /// No description provided for @settingsPrefsUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'Preferences indisponibles pour le moment. Tire pour recharger plus tard.'**
  String get settingsPrefsUnavailable;

  /// No description provided for @teamQrPrefilledHint.
  ///
  /// In fr, this message translates to:
  /// **'Profil pre-rempli depuis QR. Renseignez l email professionnel unique de cette entreprise.'**
  String get teamQrPrefilledHint;

  /// No description provided for @settingsBiometryPendingHint.
  ///
  /// In fr, this message translates to:
  /// **'Une fois soumises, vos donnees biometrie restent en attente. Toute premiere activation ou modification necessite une approbation manager/RH.'**
  String get settingsBiometryPendingHint;

  /// No description provided for @companiesRequiredField.
  ///
  /// In fr, this message translates to:
  /// **'Champ requis'**
  String get companiesRequiredField;

  /// No description provided for @companiesCompanyCreated.
  ///
  /// In fr, this message translates to:
  /// **'Entreprise créée'**
  String get companiesCompanyCreated;

  /// No description provided for @companiesNewClient.
  ///
  /// In fr, this message translates to:
  /// **'Nouveau client'**
  String get companiesNewClient;

  /// No description provided for @companiesProvisioning.
  ///
  /// In fr, this message translates to:
  /// **'Provisionnement plateforme'**
  String get companiesProvisioning;

  /// No description provided for @companiesCompanyEmail.
  ///
  /// In fr, this message translates to:
  /// **'Email entreprise'**
  String get companiesCompanyEmail;

  /// No description provided for @companiesCreateClient.
  ///
  /// In fr, this message translates to:
  /// **'Créer le client'**
  String get companiesCreateClient;

  /// No description provided for @companiesCreating.
  ///
  /// In fr, this message translates to:
  /// **'Création...'**
  String get companiesCreating;

  /// No description provided for @companiesActiveImmediatelyHint.
  ///
  /// In fr, this message translates to:
  /// **'Le client sera créé en statut actif.'**
  String get companiesActiveImmediatelyHint;

  /// No description provided for @companiesTrialHint.
  ///
  /// In fr, this message translates to:
  /// **'Le client démarre en essai, puis peut être activé depuis sa fiche.'**
  String get companiesTrialHint;

  /// No description provided for @companydetailClientFile.
  ///
  /// In fr, this message translates to:
  /// **'Fiche client'**
  String get companydetailClientFile;

  /// No description provided for @companydetailProductAdoption.
  ///
  /// In fr, this message translates to:
  /// **'Adoption produit'**
  String get companydetailProductAdoption;

  /// No description provided for @companydetailActiveEmployees.
  ///
  /// In fr, this message translates to:
  /// **'Employés actifs'**
  String get companydetailActiveEmployees;

  /// No description provided for @companydetailAnomaliesCritical.
  ///
  /// In fr, this message translates to:
  /// **'Anomalies critiques'**
  String get companydetailAnomaliesCritical;

  /// No description provided for @commonBack.
  ///
  /// In fr, this message translates to:
  /// **'Retour'**
  String get commonBack;
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
