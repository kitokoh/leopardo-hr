class NotificationPreferences {
  const NotificationPreferences({
    required this.appEnabled,
    required this.emailEnabled,
    required this.pushEnabled,
    required this.smsEnabled,
    required this.whatsappEnabled,
    required this.categories,
    required this.quietHoursEnabled,
    this.locale,
    this.timezone,
    this.quietHoursStart,
    this.quietHoursEnd,
  });

  final bool appEnabled;
  final bool emailEnabled;
  final bool pushEnabled;
  final bool smsEnabled;
  final bool whatsappEnabled;
  final String? locale;
  final String? timezone;
  final Map<String, bool> categories;
  final bool quietHoursEnabled;
  final String? quietHoursStart;
  final String? quietHoursEnd;

  factory NotificationPreferences.fromJson(Map<String, dynamic> json) {
    final rawCategories = json['categories'];
    final rawQuietHours = json['quiet_hours'];
    final quietHours =
        rawQuietHours is Map
            ? rawQuietHours.cast<String, dynamic>()
            : const <String, dynamic>{};

    return NotificationPreferences(
      appEnabled: json['app_enabled'] != false,
      emailEnabled: json['email_enabled'] != false,
      pushEnabled: json['push_enabled'] != false,
      smsEnabled: json['sms_enabled'] == true,
      whatsappEnabled: json['whatsapp_enabled'] == true,
      locale: json['locale']?.toString(),
      timezone: json['timezone']?.toString(),
      categories:
          rawCategories is Map
              ? rawCategories.map(
                (key, value) => MapEntry(key.toString(), value == true),
              )
              : const <String, bool>{},
      quietHoursEnabled: quietHours['enabled'] == true,
      quietHoursStart: quietHours['start']?.toString(),
      quietHoursEnd: quietHours['end']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'app_enabled': appEnabled,
      'email_enabled': emailEnabled,
      'push_enabled': pushEnabled,
      'sms_enabled': smsEnabled,
      'whatsapp_enabled': whatsappEnabled,
      'locale': locale,
      'timezone': timezone,
      'categories': categories,
      'quiet_hours': {
        'enabled': quietHoursEnabled,
        'start': quietHoursStart,
        'end': quietHoursEnd,
      },
    };
  }

  NotificationPreferences copyWith({
    bool? appEnabled,
    bool? emailEnabled,
    bool? pushEnabled,
    bool? smsEnabled,
    bool? whatsappEnabled,
    String? locale,
    String? timezone,
    Map<String, bool>? categories,
    bool? quietHoursEnabled,
    String? quietHoursStart,
    String? quietHoursEnd,
  }) {
    return NotificationPreferences(
      appEnabled: appEnabled ?? this.appEnabled,
      emailEnabled: emailEnabled ?? this.emailEnabled,
      pushEnabled: pushEnabled ?? this.pushEnabled,
      smsEnabled: smsEnabled ?? this.smsEnabled,
      whatsappEnabled: whatsappEnabled ?? this.whatsappEnabled,
      locale: locale ?? this.locale,
      timezone: timezone ?? this.timezone,
      categories: categories ?? this.categories,
      quietHoursEnabled: quietHoursEnabled ?? this.quietHoursEnabled,
      quietHoursStart: quietHoursStart ?? this.quietHoursStart,
      quietHoursEnd: quietHoursEnd ?? this.quietHoursEnd,
    );
  }
}
