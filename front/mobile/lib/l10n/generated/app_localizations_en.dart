// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appTitle => 'Leopardo HR';

  @override
  String get welcomeBrandSubtitle => 'Conversational, mobile-first, modular.';

  @override
  String get welcomeHeroTitle =>
      'Your workday starts here, not in a back office.';

  @override
  String get welcomeHeroDescription =>
      'Clock-ins, personal tracking, and active HR modules open first on the phone, with a simple and readable experience.';

  @override
  String get welcomeStoryClarityTitle =>
      'A home screen that speaks before it overwhelms';

  @override
  String get welcomeStoryClarityBody =>
      'Leopardo HR starts with a few clear actions: clock in, follow the month, and find the information that matters.';

  @override
  String get welcomeStoryFieldTitle => 'Mobile-first for field teams';

  @override
  String get welcomeStoryFieldBody =>
      'The phone is the employee\'s main surface. Clock-ins, absences, and documents live here.';

  @override
  String get welcomeStoryModulesTitle => 'Active modules, visible roadmap';

  @override
  String get welcomeStoryModulesBody =>
      'The product opens what is useful today first, then keeps Finance, Security, and Leo on a readable path.';

  @override
  String get login => 'Sign in';

  @override
  String get employeeInvitationAccess => 'Employee access (invitation)';

  @override
  String get createPersonalAccount => 'Create a personal account';

  @override
  String get personalAccountExplanation =>
      'Personal account: organize your documents, then create or join a company from your space.';

  @override
  String get commonLanguageLabel => 'Language';

  @override
  String get commonLanguageVariantsFrFr => 'French (France)';

  @override
  String get commonLanguageVariantsFrBe => 'French (Belgium)';

  @override
  String get commonLanguageVariantsFrCa => 'French (Canada)';

  @override
  String get commonLanguageVariantsArSa => 'Arabic (Saudi Arabia)';

  @override
  String get commonLanguageVariantsArMa => 'Arabic (Morocco)';

  @override
  String get commonLanguageVariantsTrTr => 'Turkish (Turkey)';

  @override
  String get commonLanguageVariantsEnUs => 'English (United States)';

  @override
  String get commonLanguageVariantsEnGb => 'English (United Kingdom)';

  @override
  String get modulesAttendance => 'Attendance';

  @override
  String get modulesPayroll => 'Payroll';

  @override
  String get modulesCabinet => 'Document vault';

  @override
  String get modulesNotifications => 'Notifications';

  @override
  String get modulesEvaluations => 'Evaluations';

  @override
  String get emailsInvitationSubject =>
      'You are invited to join :company on Leopardo HR';

  @override
  String get emailsInvitationGreeting => 'Hello :name,';

  @override
  String get emailsInvitationBody =>
      'You have been invited to join :company. Click the link below to activate your account.';

  @override
  String get emailsInvitationAction => 'Activate my account';

  @override
  String get emailsInvitationFooter =>
      'If you did not request this action, ignore this email.';

  @override
  String get emailsResetPasswordSubject => 'Reset your password';

  @override
  String get emailsResetPasswordGreeting => 'Hello :name,';

  @override
  String get emailsResetPasswordBody =>
      'Click the link below to reset your password.';

  @override
  String get emailsResetPasswordAction => 'Reset password';

  @override
  String get emailsResetPasswordFooter =>
      'If you did not request this action, ignore this email.';

  @override
  String get emailsPayrollReadySubject => 'Your payslip is ready';

  @override
  String get emailsPayrollReadyGreeting => 'Hello :name,';

  @override
  String get emailsPayrollReadyBody =>
      'Your payslip for :period is ready. You can review it in Leopardo HR.';

  @override
  String get emailsPayrollReadyAction => 'View my payslip';

  @override
  String get emailsPayrollReadyFooter =>
      'Please review your information before accounting export.';

  @override
  String get emailsAbsenceApprovedSubject =>
      'Your leave request has been approved';

  @override
  String get emailsAbsenceApprovedGreeting => 'Hello :name,';

  @override
  String get emailsAbsenceApprovedBody =>
      'Your leave request for :period has been approved.';

  @override
  String get emailsAbsenceApprovedAction => 'View request';

  @override
  String get emailsAbsenceApprovedFooter =>
      'The team schedule has been updated.';

  @override
  String get emailsAbsenceRejectedSubject =>
      'Your leave request has been rejected';

  @override
  String get emailsAbsenceRejectedGreeting => 'Hello :name,';

  @override
  String get emailsAbsenceRejectedBody =>
      'Your leave request for :period has been rejected.';

  @override
  String get emailsAbsenceRejectedAction => 'View request';

  @override
  String get emailsAbsenceRejectedFooter =>
      'Please contact your manager if you need more details.';
}
