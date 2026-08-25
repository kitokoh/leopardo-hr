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

  /// No description provided for @attendanceToProcess.
  ///
  /// In fr, this message translates to:
  /// **'\"A traiter\"'**
  String get attendanceToProcess;

  /// No description provided for @settingsBiometryEnableFirst.
  ///
  /// In fr, this message translates to:
  /// **'\"Active d abord la preparation biometrie.\"'**
  String get settingsBiometryEnableFirst;

  /// No description provided for @settingsBiometryEnableAction.
  ///
  /// In fr, this message translates to:
  /// **'\"Activer la preparation biometrie\"'**
  String get settingsBiometryEnableAction;

  /// No description provided for @settingsEdgeNodeAddress.
  ///
  /// In fr, this message translates to:
  /// **'\"Adresse du noeud Edge\"'**
  String get settingsEdgeNodeAddress;

  /// No description provided for @settingsBiometryAddFaceCapture.
  ///
  /// In fr, this message translates to:
  /// **'\"Ajoute une capture visage avant soumission.\"'**
  String get settingsBiometryAddFaceCapture;

  /// No description provided for @notifTitle.
  ///
  /// In fr, this message translates to:
  /// **'\"Alertes RH, paie et validations\"'**
  String get notifTitle;

  /// No description provided for @settingsPushInApp.
  ///
  /// In fr, this message translates to:
  /// **'\"Alertes dans l application\"'**
  String get settingsPushInApp;

  /// No description provided for @attendanceAnalyzingAnomalies.
  ///
  /// In fr, this message translates to:
  /// **'\"Analyse des anomalies...\"'**
  String get attendanceAnalyzingAnomalies;

  /// No description provided for @settingsEdgePairingRemoved.
  ///
  /// In fr, this message translates to:
  /// **'\"Appairage Edge supprime.\"'**
  String get settingsEdgePairingRemoved;

  /// No description provided for @teamQrNoneInClipboard.
  ///
  /// In fr, this message translates to:
  /// **'\"Aucun code QR dans le presse-papiers.\"'**
  String get teamQrNoneInClipboard;

  /// No description provided for @teamNoScheduleYet.
  ///
  /// In fr, this message translates to:
  /// **'\"Aucun horaire cree. Vous pourrez en definir dans le module Horaires.\"'**
  String get teamNoScheduleYet;

  /// No description provided for @attendanceNoPunchToday.
  ///
  /// In fr, this message translates to:
  /// **'\"Aucun pointage aujourd hui\"'**
  String get attendanceNoPunchToday;

  /// No description provided for @attendanceNoRecentAnomalies.
  ///
  /// In fr, this message translates to:
  /// **'\"Aucune anomalie recente\"'**
  String get attendanceNoRecentAnomalies;

  /// No description provided for @teamNoPendingInvites.
  ///
  /// In fr, this message translates to:
  /// **'\"Aucune invitation en cours\"'**
  String get teamNoPendingInvites;

  /// No description provided for @settingsLockerDocsAdmin.
  ///
  /// In fr, this message translates to:
  /// **'\"CV, contrats, diplomes et documents administratifs.\"'**
  String get settingsLockerDocsAdmin;

  /// No description provided for @settingsLockerDocsVisibility.
  ///
  /// In fr, this message translates to:
  /// **'\"CV, contrats, diplomes et documents avec visibilite controlee.\"'**
  String get settingsLockerDocsVisibility;

  /// No description provided for @settingsNotifChannelChat.
  ///
  /// In fr, this message translates to:
  /// **'\"Canal conversationnel, necessite votre opt-in explicite.\"'**
  String get settingsNotifChannelChat;

  /// No description provided for @settingsNotifChannelSms.
  ///
  /// In fr, this message translates to:
  /// **'\"Canal court reserve aux urgences, actif apres opt-in.\"'**
  String get settingsNotifChannelSms;

  /// No description provided for @settingsNotifChannelsSummary.
  ///
  /// In fr, this message translates to:
  /// **'\"Canaux, heures calmes et alertes operationnelles.\"'**
  String get settingsNotifChannelsSummary;

  /// No description provided for @settingsBiometryCaptureFace.
  ///
  /// In fr, this message translates to:
  /// **'\"Capturer / choisir mon visage\"'**
  String get settingsBiometryCaptureFace;

  /// No description provided for @attendanceEmployeeNotPunchedToday.
  ///
  /// In fr, this message translates to:
  /// **'\"Cet employe n a pas encore pointe pour la journee en cours.\"'**
  String get attendanceEmployeeNotPunchedToday;

  /// No description provided for @settingsFieldRequired.
  ///
  /// In fr, this message translates to:
  /// **'\"Champ requis\"'**
  String get settingsFieldRequired;

  /// No description provided for @attendanceLoadingRequests.
  ///
  /// In fr, this message translates to:
  /// **'\"Chargement des demandes...\"'**
  String get attendanceLoadingRequests;

  /// No description provided for @teamLoadingInvites.
  ///
  /// In fr, this message translates to:
  /// **'\"Chargement des invitations\"'**
  String get teamLoadingInvites;

  /// No description provided for @attendanceLoadingEmployeeDetail.
  ///
  /// In fr, this message translates to:
  /// **'\"Chargement du detail employe...\"'**
  String get attendanceLoadingEmployeeDetail;

  /// No description provided for @settingsNotifChannelsHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Choisissez les canaux utiles sans perdre les alertes RH importantes.\"'**
  String get settingsNotifChannelsHint;

  /// No description provided for @teamEmployeeQrCode.
  ///
  /// In fr, this message translates to:
  /// **'\"Code QR employe\"'**
  String get teamEmployeeQrCode;

  /// No description provided for @settingsPasteCompanyQr.
  ///
  /// In fr, this message translates to:
  /// **'\"Coller le QR entreprise\"'**
  String get settingsPasteCompanyQr;

  /// No description provided for @settingsPasteManagerQr.
  ///
  /// In fr, this message translates to:
  /// **'\"Coller le QR fourni par le manager\"'**
  String get settingsPasteManagerQr;

  /// No description provided for @teamPasteScannedQr.
  ///
  /// In fr, this message translates to:
  /// **'\"Coller le QR scanne\"'**
  String get teamPasteScannedQr;

  /// No description provided for @settingsPasteCompanyQrHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Collez le QR entreprise.\"'**
  String get settingsPasteCompanyQrHint;

  /// No description provided for @teamPasteQrHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Collez le code QR.\"'**
  String get teamPasteQrHint;

  /// No description provided for @settingsBiometryConfirmIdentity.
  ///
  /// In fr, this message translates to:
  /// **'\"Confirmer votre identite pour soumettre votre demande biometrie\"'**
  String get settingsBiometryConfirmIdentity;

  /// No description provided for @settingsEdgeConnectedCloud.
  ///
  /// In fr, this message translates to:
  /// **'\"Connecte au Cloud\"'**
  String get settingsEdgeConnectedCloud;

  /// No description provided for @settingsEdgeConnectedLocal.
  ///
  /// In fr, this message translates to:
  /// **'\"Connecte au noeud Edge local\"'**
  String get settingsEdgeConnectedLocal;

  /// No description provided for @settingsBiometryConsentTitle.
  ///
  /// In fr, this message translates to:
  /// **'\"Consentement au futur pointage biometrie\"'**
  String get settingsBiometryConsentTitle;

  /// No description provided for @attendanceCorrectionAppliedToast.
  ///
  /// In fr, this message translates to:
  /// **'\"Correction appliquee.\"'**
  String get attendanceCorrectionAppliedToast;

  /// No description provided for @attendanceCorrectionRejected.
  ///
  /// In fr, this message translates to:
  /// **'\"Correction refusee.\"'**
  String get attendanceCorrectionRejected;

  /// No description provided for @teamCreateFromQrAndInvite.
  ///
  /// In fr, this message translates to:
  /// **'\"Creer depuis QR et inviter\"'**
  String get teamCreateFromQrAndInvite;

  /// No description provided for @teamHireDate.
  ///
  /// In fr, this message translates to:
  /// **'\"Date d embauche\"'**
  String get teamHireDate;

  /// No description provided for @settingsRequestSent.
  ///
  /// In fr, this message translates to:
  /// **'\"Demande envoyee\"'**
  String get settingsRequestSent;

  /// No description provided for @settingsBiometryRequestSentHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Demande envoyee au manager / RH pour validation.\"'**
  String get settingsBiometryRequestSentHint;

  /// No description provided for @settingsRequestJoin.
  ///
  /// In fr, this message translates to:
  /// **'\"Demander l integration\"'**
  String get settingsRequestJoin;

  /// No description provided for @attendanceEmployeeRequestsPending.
  ///
  /// In fr, this message translates to:
  /// **'\"Demandes employees en attente RH\"'**
  String get attendanceEmployeeRequestsPending;

  /// No description provided for @teamDepartmentOptional.
  ///
  /// In fr, this message translates to:
  /// **'\"Departement (optionnel)\"'**
  String get teamDepartmentOptional;

  /// No description provided for @settingsAvailableForNewCompany.
  ///
  /// In fr, this message translates to:
  /// **'\"Disponible pour une nouvelle entreprise\"'**
  String get settingsAvailableForNewCompany;

  /// No description provided for @settingsRecoveryEmailLabel.
  ///
  /// In fr, this message translates to:
  /// **'\"Email de récupération\"'**
  String get settingsRecoveryEmailLabel;

  /// No description provided for @settingsPersonalEmailLabel.
  ///
  /// In fr, this message translates to:
  /// **'\"Email personnel\"'**
  String get settingsPersonalEmailLabel;

  /// No description provided for @teamEmployeeAdded.
  ///
  /// In fr, this message translates to:
  /// **'\"Employe ajoute.\"'**
  String get teamEmployeeAdded;

  /// No description provided for @settingsBiometryFingerprintDesired.
  ///
  /// In fr, this message translates to:
  /// **'\"Empreinte digitale souhaitee\"'**
  String get settingsBiometryFingerprintDesired;

  /// No description provided for @settingsBiometrySaveEnrollment.
  ///
  /// In fr, this message translates to:
  /// **'\"Enregistrer la preparation\"'**
  String get settingsBiometrySaveEnrollment;

  /// No description provided for @teamSendInvite.
  ///
  /// In fr, this message translates to:
  /// **'\"Envoyer l invitation\"'**
  String get teamSendInvite;

  /// No description provided for @settingsBiometryFpExample.
  ///
  /// In fr, this message translates to:
  /// **'\"Exemple: FP-ENTREE-01 ou matricule biometrie\"'**
  String get settingsBiometryFpExample;

  /// No description provided for @teamEmployeeRecordUpdated.
  ///
  /// In fr, this message translates to:
  /// **'\"Fiche collaborateur mise a jour.\"'**
  String get teamEmployeeRecordUpdated;

  /// No description provided for @attendanceEmptyCorrectionQueue.
  ///
  /// In fr, this message translates to:
  /// **'\"File de correction vide\"'**
  String get attendanceEmptyCorrectionQueue;

  /// No description provided for @settingsEdgeTokenFromAdmin.
  ///
  /// In fr, this message translates to:
  /// **'\"Fourni par votre administrateur\"'**
  String get settingsEdgeTokenFromAdmin;

  /// No description provided for @settingsEdgeTokenOneTime.
  ///
  /// In fr, this message translates to:
  /// **'\"Fourni une seule fois a l enregistrement\"'**
  String get settingsEdgeTokenOneTime;

  /// No description provided for @settingsQuietHours.
  ///
  /// In fr, this message translates to:
  /// **'\"Heures calmes\"'**
  String get settingsQuietHours;

  /// No description provided for @settingsJourneyHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Historique entreprise, poste, statut et disponibilite.\"'**
  String get settingsJourneyHint;

  /// No description provided for @teamWorkSchedule.
  ///
  /// In fr, this message translates to:
  /// **'\"Horaire de travail\"'**
  String get teamWorkSchedule;

  /// No description provided for @teamDefaultSchedule.
  ///
  /// In fr, this message translates to:
  /// **'\"Horaire par defaut\"'**
  String get teamDefaultSchedule;

  /// No description provided for @commonOffline.
  ///
  /// In fr, this message translates to:
  /// **'\"Hors ligne\"'**
  String get commonOffline;

  /// No description provided for @settingsBiometrySensorId.
  ///
  /// In fr, this message translates to:
  /// **'\"Identifiant capteur empreinte / borne\"'**
  String get settingsBiometrySensorId;

  /// No description provided for @settingsEdgeNodeId.
  ///
  /// In fr, this message translates to:
  /// **'\"Identifiant du noeud (UUID)\"'**
  String get settingsEdgeNodeId;

  /// No description provided for @settingsPortableIdentity.
  ///
  /// In fr, this message translates to:
  /// **'\"Identite portable\"'**
  String get settingsPortableIdentity;

  /// No description provided for @settingsBiometryFaceSelected.
  ///
  /// In fr, this message translates to:
  /// **'\"Image visage selectionnee\"'**
  String get settingsBiometryFaceSelected;

  /// No description provided for @teamImportFromQr.
  ///
  /// In fr, this message translates to:
  /// **'\"Importer depuis QR\"'**
  String get teamImportFromQr;

  /// No description provided for @teamInviteResent.
  ///
  /// In fr, this message translates to:
  /// **'\"Invitation renvoyee.\"'**
  String get teamInviteResent;

  /// No description provided for @settingsEdgeToken.
  ///
  /// In fr, this message translates to:
  /// **'\"Jeton Edge\"'**
  String get settingsEdgeToken;

  /// No description provided for @settingsPasswordConfirmationMismatch.
  ///
  /// In fr, this message translates to:
  /// **'\"La confirmation ne correspond pas\"'**
  String get settingsPasswordConfirmationMismatch;

  /// No description provided for @settingsNotifLanguage.
  ///
  /// In fr, this message translates to:
  /// **'\"Langue des notifications\"'**
  String get settingsNotifLanguage;

  /// No description provided for @settingsBiometryConsentRequired.
  ///
  /// In fr, this message translates to:
  /// **'\"Le consentement est requis avant toute soumission.\"'**
  String get settingsBiometryConsentRequired;

  /// No description provided for @settingsQrManagerScanHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Le manager le scanne pour pre-remplir une invitation.\"'**
  String get settingsQrManagerScanHint;

  /// No description provided for @teamWorkLocation.
  ///
  /// In fr, this message translates to:
  /// **'\"Lieu de travail\"'**
  String get teamWorkLocation;

  /// No description provided for @teamWorkLocationOptional.
  ///
  /// In fr, this message translates to:
  /// **'\"Lieu de travail (optionnel)\"'**
  String get teamWorkLocationOptional;

  /// No description provided for @settingsQuietHoursHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Limiter les canaux externes hors horaires.\"'**
  String get settingsQuietHoursHint;

  /// No description provided for @teamReadAndPrefill.
  ///
  /// In fr, this message translates to:
  /// **'\"Lire et pre-remplir\"'**
  String get teamReadAndPrefill;

  /// No description provided for @notifMarkAsRead.
  ///
  /// In fr, this message translates to:
  /// **'\"Marquer comme lue\"'**
  String get notifMarkAsRead;

  /// No description provided for @teamEmployeeIdOptional.
  ///
  /// In fr, this message translates to:
  /// **'\"Matricule (optionnel)\"'**
  String get teamEmployeeIdOptional;

  /// No description provided for @teamMonthlyFixed.
  ///
  /// In fr, this message translates to:
  /// **'\"Mensuel / fixe\"'**
  String get teamMonthlyFixed;

  /// No description provided for @settingsPasswordUpdateTitle.
  ///
  /// In fr, this message translates to:
  /// **'\"Mettre a jour le mot de passe\"'**
  String get settingsPasswordUpdateTitle;

  /// No description provided for @settingsPasswordMinCharacters.
  ///
  /// In fr, this message translates to:
  /// **'\"Minimum 8 caracteres\"'**
  String get settingsPasswordMinCharacters;

  /// No description provided for @teamSalaryMode.
  ///
  /// In fr, this message translates to:
  /// **'\"Mode salaire\"'**
  String get teamSalaryMode;

  /// No description provided for @settingsMyEmployeeQr.
  ///
  /// In fr, this message translates to:
  /// **'\"Mon QR employe\"'**
  String get settingsMyEmployeeQr;

  /// No description provided for @teamAmountRequired.
  ///
  /// In fr, this message translates to:
  /// **'\"Montant obligatoire\"'**
  String get teamAmountRequired;

  /// No description provided for @settingsPasswordUpdated.
  ///
  /// In fr, this message translates to:
  /// **'\"Mot de passe mis a jour.\"'**
  String get settingsPasswordUpdated;

  /// No description provided for @settingsEdgeNodeLocal.
  ///
  /// In fr, this message translates to:
  /// **'\"Noeud Edge (reseau local)\"'**
  String get settingsEdgeNodeLocal;

  /// No description provided for @settingsBiometryNotesConsent.
  ///
  /// In fr, this message translates to:
  /// **'\"Notes et consentement\"'**
  String get settingsBiometryNotesConsent;

  /// No description provided for @settingsPushImmediateHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Notifications immediates sur ce telephone.\"'**
  String get settingsPushImmediateHint;

  /// No description provided for @teamNewEmployee.
  ///
  /// In fr, this message translates to:
  /// **'\"Nouvel employe\"'**
  String get teamNewEmployee;

  /// No description provided for @teamNewEmployeeViaQr.
  ///
  /// In fr, this message translates to:
  /// **'\"Nouvel employe via QR\"'**
  String get teamNewEmployeeViaQr;

  /// No description provided for @settingsRecoveryEmailOptionalHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Optionnel pour recuperer l acces\"'**
  String get settingsRecoveryEmailOptionalHint;

  /// No description provided for @settingsPersonalEmailHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Optionnel, conserve votre compte hors entreprise\"'**
  String get settingsPersonalEmailHint;

  /// No description provided for @settingsPhoneHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Optionnel, visible selon vos choix futurs\"'**
  String get settingsPhoneHint;

  /// No description provided for @settingsOpenMyLocker.
  ///
  /// In fr, this message translates to:
  /// **'\"Ouvrir mon placard\"'**
  String get settingsOpenMyLocker;

  /// No description provided for @settingsShareProfileOrScan.
  ///
  /// In fr, this message translates to:
  /// **'\"Partager votre profil ou scanner une entreprise.\"'**
  String get settingsShareProfileOrScan;

  /// No description provided for @settingsShareProfileOrScanQr.
  ///
  /// In fr, this message translates to:
  /// **'\"Partagez votre profil ou scannez le QR d une entreprise.\"'**
  String get settingsShareProfileOrScanQr;

  /// No description provided for @settingsDigitalLocker.
  ///
  /// In fr, this message translates to:
  /// **'\"Placard numerique\"'**
  String get settingsDigitalLocker;

  /// No description provided for @attendanceTodayPunchesOpenSessions.
  ///
  /// In fr, this message translates to:
  /// **'\"Pointages du jour et sessions ouvertes\"'**
  String get attendanceTodayPunchesOpenSessions;

  /// No description provided for @teamPositionOptional.
  ///
  /// In fr, this message translates to:
  /// **'\"Poste (optionnel)\"'**
  String get teamPositionOptional;

  /// No description provided for @settingsNotifPrefsUpdated.
  ///
  /// In fr, this message translates to:
  /// **'\"Preferences notifications mises a jour.\"'**
  String get settingsNotifPrefsUpdated;

  /// No description provided for @settingsBiometryEnrollment.
  ///
  /// In fr, this message translates to:
  /// **'\"Preparation biometrie\"'**
  String get settingsBiometryEnrollment;

  /// No description provided for @settingsBiometrySavedLocally.
  ///
  /// In fr, this message translates to:
  /// **'\"Preparation biometrie enregistree localement.\"'**
  String get settingsBiometrySavedLocally;

  /// No description provided for @settingsBiometryEnrollHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Preparer doigt et visage pour les bornes terrain.\"'**
  String get settingsBiometryEnrollHint;

  /// No description provided for @attendanceTeamPresence.
  ///
  /// In fr, this message translates to:
  /// **'\"Presences equipe\"'**
  String get attendanceTeamPresence;

  /// No description provided for @settingsProfileUpdated.
  ///
  /// In fr, this message translates to:
  /// **'\"Profil mis a jour.\"'**
  String get settingsProfileUpdated;

  /// No description provided for @settingsPushMobile.
  ///
  /// In fr, this message translates to:
  /// **'\"Push mobile\"'**
  String get settingsPushMobile;

  /// No description provided for @commonCompanyQr.
  ///
  /// In fr, this message translates to:
  /// **'\"QR entreprise\"'**
  String get commonCompanyQr;

  /// No description provided for @teamCompanyQrScannable.
  ///
  /// In fr, this message translates to:
  /// **'\"QR entreprise scannable\"'**
  String get teamCompanyQrScannable;

  /// No description provided for @settingsQrUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'\"QR indisponible pour le moment.\"'**
  String get settingsQrUnavailable;

  /// No description provided for @settingsQrOnboarding.
  ///
  /// In fr, this message translates to:
  /// **'\"QR onboarding\"'**
  String get settingsQrOnboarding;

  /// No description provided for @settingsQrProfessional.
  ///
  /// In fr, this message translates to:
  /// **'\"QR professionnel\"'**
  String get settingsQrProfessional;

  /// No description provided for @settingsEdgeLogoutHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Quitter proprement cet espace sur ce telephone.\"'**
  String get settingsEdgeLogoutHint;

  /// No description provided for @teamReloadSchedules.
  ///
  /// In fr, this message translates to:
  /// **'\"Recharger les horaires\"'**
  String get teamReloadSchedules;

  /// No description provided for @settingsBiometryFaceRecognitionDesired.
  ///
  /// In fr, this message translates to:
  /// **'\"Reconnaissance faciale souhaitee\"'**
  String get settingsBiometryFaceRecognitionDesired;

  /// No description provided for @settingsNotifSummaryHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Resume et confirmations importantes.\"'**
  String get settingsNotifSummaryHint;

  /// No description provided for @attendanceLateMissedToCheck.
  ///
  /// In fr, this message translates to:
  /// **'\"Retards, oublis et pointages a verifier\"'**
  String get attendanceLateMissedToCheck;

  /// No description provided for @teamBaseSalary.
  ///
  /// In fr, this message translates to:
  /// **'\"Salaire de base\"'**
  String get teamBaseSalary;

  /// No description provided for @teamDailySalary.
  ///
  /// In fr, this message translates to:
  /// **'\"Salaire journalier\"'**
  String get teamDailySalary;

  /// No description provided for @teamMonthlyGrossSalary.
  ///
  /// In fr, this message translates to:
  /// **'\"Salaire mensuel brut\"'**
  String get teamMonthlyGrossSalary;

  /// No description provided for @teamSelectType.
  ///
  /// In fr, this message translates to:
  /// **'\"Selectionnez un type\"'**
  String get teamSelectType;

  /// No description provided for @attendanceTodaySessions.
  ///
  /// In fr, this message translates to:
  /// **'\"Sessions du jour\"'**
  String get attendanceTodaySessions;

  /// No description provided for @settingsBiometrySubmit.
  ///
  /// In fr, this message translates to:
  /// **'\"Soumettre au manager / RH\"'**
  String get settingsBiometrySubmit;

  /// No description provided for @attendanceSyncingPresence.
  ///
  /// In fr, this message translates to:
  /// **'\"Synchronisation des presences...\"'**
  String get attendanceSyncingPresence;

  /// No description provided for @settingsNotifTasksHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Taches, decisions RH, pointage et rappels.\"'**
  String get settingsNotifTasksHint;

  /// No description provided for @teamHourlyRate.
  ///
  /// In fr, this message translates to:
  /// **'\"Taux horaire\"'**
  String get teamHourlyRate;

  /// No description provided for @settingsPersonalPhoneLabel.
  ///
  /// In fr, this message translates to:
  /// **'\"Téléphone personnel\"'**
  String get settingsPersonalPhoneLabel;

  /// No description provided for @notifMarkAllAsRead.
  ///
  /// In fr, this message translates to:
  /// **'\"Tout marquer comme lu\"'**
  String get notifMarkAllAsRead;

  /// No description provided for @teamManagerType.
  ///
  /// In fr, this message translates to:
  /// **'\"Type de manager\"'**
  String get teamManagerType;

  /// No description provided for @teamPayType.
  ///
  /// In fr, this message translates to:
  /// **'\"Type de paie\"'**
  String get teamPayType;

  /// No description provided for @settingsBiometryLocalCheckCancelled.
  ///
  /// In fr, this message translates to:
  /// **'\"Verification biometrie locale annulee.\"'**
  String get settingsBiometryLocalCheckCancelled;

  /// No description provided for @settingsViewMyProfile.
  ///
  /// In fr, this message translates to:
  /// **'\"Voir mon profil\"'**
  String get settingsViewMyProfile;

  /// No description provided for @notifUpToDate.
  ///
  /// In fr, this message translates to:
  /// **'\"Vous etes a jour. Cette page se rafraichit automatiquement.\"'**
  String get notifUpToDate;

  /// No description provided for @teamOperationalView.
  ///
  /// In fr, this message translates to:
  /// **'\"Vue operationnelle\"'**
  String get teamOperationalView;

  /// No description provided for @commonLanguageArabic.
  ///
  /// In fr, this message translates to:
  /// **'\"العربية\"'**
  String get commonLanguageArabic;

  /// No description provided for @settingsPrefSyncAccount.
  ///
  /// In fr, this message translates to:
  /// **'\"Cette preference est synchronisee avec votre compte et pilote aussi le mode RTL.\"'**
  String get settingsPrefSyncAccount;

  /// No description provided for @settingsPasswordModernizeHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Changez votre mot de passe avant les prochaines etapes de modernisation.\"'**
  String get settingsPasswordModernizeHint;

  /// No description provided for @teamPasteEmployeeQrHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Collez le code QR employe. Le formulaire restera modifiable avant invitation.\"'**
  String get teamPasteEmployeeQrHint;

  /// No description provided for @teamInviteSummary.
  ///
  /// In fr, this message translates to:
  /// **'\"Invitation, role, date d embauche et base salariale sont envoyes a l API.\"'**
  String get teamInviteSummary;

  /// No description provided for @teamQrEmployeeScanHint.
  ///
  /// In fr, this message translates to:
  /// **'\"L employe le scanne depuis son espace compte pour demander son integration.\"'**
  String get teamQrEmployeeScanHint;

  /// No description provided for @settingsBiometryFaceHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Le visage peut etre capture depuis le mobile puis soumis a validation manager / RH. Pour l empreinte, Android/iOS permettent de verifier localement que vous utilisez bien un doigt enregistre, mais ne donnent pas acces au gabarit brut; l activation effective cote pointage restera donc approuvee puis exploitee par la borne entreprise.\"'**
  String get settingsBiometryFaceHint;

  /// No description provided for @attendanceAnomaliesHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Les alertes de pointage, sorties manquantes et heures supplementaires apparaitront ici.\"'**
  String get attendanceAnomaliesHint;

  /// No description provided for @attendanceRequestsHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Les demandes envoyees depuis les trois points du pointage seront listees ici.\"'**
  String get attendanceRequestsHint;

  /// No description provided for @teamInvitesHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Les invitations envoyees a vos futurs collaborateurs s afficheront ici.\"'**
  String get teamInvitesHint;

  /// No description provided for @attendanceTeamPunchesHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Les pointages equipe apparaitront ici des qu ils arrivent depuis mobile ou kiosque.\"'**
  String get attendanceTeamPunchesHint;

  /// No description provided for @settingsEdgeOptionalHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Optionnel: pointer vers un serveur Edge installe sur site pour pointer sans Internet.\"'**
  String get settingsEdgeOptionalHint;

  /// No description provided for @settingsPrefsUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'\"Preferences indisponibles pour le moment. Tire pour recharger plus tard.\"'**
  String get settingsPrefsUnavailable;

  /// No description provided for @teamQrPrefilledHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Profil pre-rempli depuis QR. Renseignez l email professionnel unique de cette entreprise.\"'**
  String get teamQrPrefilledHint;

  /// No description provided for @settingsBiometryPendingHint.
  ///
  /// In fr, this message translates to:
  /// **'Une fois soumises, vos donnees biometrie restent en attente. Toute premiere activation ou modification necessite une approbation manager/RH.'**
  String get settingsBiometryPendingHint;

  /// No description provided for @settingsEdgeRemoved.
  ///
  /// In fr, this message translates to:
  /// **'\"Appairage Edge supprimé.\"'**
  String get settingsEdgeRemoved;

  /// No description provided for @settingsViewProfile.
  ///
  /// In fr, this message translates to:
  /// **'\"Voir mon profil\"'**
  String get settingsViewProfile;

  /// No description provided for @settingsRecoveryEmailHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Optionnel pour récupérer l\'accès\"'**
  String get settingsRecoveryEmailHint;

  /// No description provided for @settingsPersonalPhoneHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Optionnel, visible selon vos choix futurs\"'**
  String get settingsPersonalPhoneHint;

  /// No description provided for @settingsPortableIdentityTitle.
  ///
  /// In fr, this message translates to:
  /// **'\"Identité portable\"'**
  String get settingsPortableIdentityTitle;

  /// No description provided for @settingsPortableIdentitySubtitle.
  ///
  /// In fr, this message translates to:
  /// **'\"Historique entreprise, poste, statut et disponibilité.\"'**
  String get settingsPortableIdentitySubtitle;

  /// No description provided for @settingsPortableIdentityHint.
  ///
  /// In fr, this message translates to:
  /// **'\"Email personnel, récupération et téléphone restent attachés au compte.\"'**
  String get settingsPortableIdentityHint;

  /// No description provided for @settingsDigitalLockerTitle.
  ///
  /// In fr, this message translates to:
  /// **'\"Placard numérique\"'**
  String get settingsDigitalLockerTitle;

  /// No description provided for @settingsDigitalLockerSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'CV, contrats, diplômes et documents avec visibilité contrôlée.'**
  String get settingsDigitalLockerSubtitle;
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
