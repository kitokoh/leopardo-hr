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
