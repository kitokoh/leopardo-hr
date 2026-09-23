export 'package:leopardo_core/features/settings/data/settings_repository.dart'
    show
        SettingsRepository,
        LocalBiometricSettings,
        CabinetStats,
        EmployeeCareer,
        EmployeeCareerEntry,
        EmployeeQrPayload;

/// Leopardo employee — repository settings partagé (leopardo_core, #7652).
/// Le message par défaut de demande RH passe par `deviceL10n.settingsRequestSent`
/// (amélioration l10n portée dans le core par cette tranche).
