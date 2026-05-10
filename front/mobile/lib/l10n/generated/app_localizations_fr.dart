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
  String get welcomeBrandSubtitle =>
      'Conversationnelle, mobile-first, modulaire.';

  @override
  String get welcomeHeroTitle =>
      'Votre journee commence ici, pas dans un back-office.';

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
  String get login => 'Se connecter';

  @override
  String get employeeInvitationAccess => 'Acces employe (invitation)';

  @override
  String get createPersonalAccount => 'Creer un compte personnel';

  @override
  String get personalAccountExplanation =>
      'Compte personnel : organisez vos documents, puis creez ou rejoignez une entreprise depuis votre espace.';

  @override
  String get commonLanguageLabel => 'Langue';

  @override
  String get commonLanguageVariantsFrFr => 'Francais (France)';

  @override
  String get commonLanguageVariantsFrBe => 'Francais (Belgique)';

  @override
  String get commonLanguageVariantsFrCa => 'Francais (Canada)';

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
}
