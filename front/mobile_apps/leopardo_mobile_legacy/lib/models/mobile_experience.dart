class MobileExperience {
  const MobileExperience({
    required this.stage,
    required this.modules,
    required this.quickActions,
  });

  final String stage;
  final List<MobileModule> modules;
  final List<MobileQuickAction> quickActions;

  factory MobileExperience.fromJson(Map<String, dynamic>? json) {
    final modules = <MobileModule>[];
    final quickActions = <MobileQuickAction>[];

    if (json case {'modules': final List rawModules}) {
      for (final item in rawModules) {
        if (item is Map) {
          modules.add(MobileModule.fromJson(item.cast<String, dynamic>()));
        }
      }
    }

    if (json case {'quick_actions': final List rawQuickActions}) {
      for (final item in rawQuickActions) {
        if (item is Map) {
          quickActions.add(
            MobileQuickAction.fromJson(item.cast<String, dynamic>()),
          );
        }
      }
    }

    return MobileExperience(
      stage: (json?['stage'] ?? 'regular') as String,
      modules: modules,
      quickActions: quickActions,
    );
  }

  List<MobileModule> get activeModules =>
      modules.where((module) => module.status == 'active').toList();

  List<MobileModule> get upcomingModules =>
      modules.where((module) => module.status != 'active').toList();
}

class MobileModule {
  const MobileModule({
    required this.key,
    required this.title,
    required this.description,
    required this.domain,
    required this.route,
    required this.status,
  });

  final String key;
  final String title;
  final String description;
  final String domain;
  final String? route;
  final String status;

  factory MobileModule.fromJson(Map<String, dynamic> json) {
    return MobileModule(
      key: (json['key'] ?? '') as String,
      title: (json['title'] ?? '') as String,
      description: (json['description'] ?? '') as String,
      domain: (json['domain'] ?? 'rh') as String,
      route: json['route'] as String?,
      status: (json['status'] ?? 'active') as String,
    );
  }

  bool get isActive => status == 'active' && route != null && route!.isNotEmpty;
}

class MobileQuickAction {
  const MobileQuickAction({
    required this.key,
    required this.title,
    required this.description,
    required this.domain,
    required this.icon,
    required this.route,
  });

  final String key;
  final String title;
  final String description;
  final String domain;
  final String icon;
  final String route;

  factory MobileQuickAction.fromJson(Map<String, dynamic> json) {
    return MobileQuickAction(
      key: (json['key'] ?? '') as String,
      title: (json['title'] ?? '') as String,
      description: (json['description'] ?? '') as String,
      domain: (json['domain'] ?? 'rh') as String,
      icon: (json['icon'] ?? 'apps') as String,
      route: (json['route'] ?? '/') as String,
    );
  }
}
