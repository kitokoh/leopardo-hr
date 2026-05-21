import 'package:flutter/material.dart';

import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';

class DemoUser {
  const DemoUser({
    required this.email,
    required this.name,
    required this.role,
    this.managerRole,
    this.password = 'password123',
  });

  final String email;
  final String name;
  final String role;
  final String? managerRole;
  final String password;
}

class DemoCompany {
  const DemoCompany({
    required this.name,
    required this.country,
    required this.users,
  });

  final String name;
  final String country;
  final List<DemoUser> users;
}

const List<DemoCompany> demoCompanies = [
  DemoCompany(
    name: 'TechCorp Algerie SARL',
    country: 'DZ',
    users: [
      DemoUser(email: 'ahmed.benali@techcorp-algerie.dz', name: 'Ahmed Benali', role: 'manager', managerRole: 'principal'),
      DemoUser(email: 'fatima.meziane@techcorp-algerie.dz', name: 'Fatima Meziane', role: 'manager', managerRole: 'rh'),
      DemoUser(email: 'karim.aouad@techcorp-algerie.dz', name: 'Karim Aouad', role: 'employee'),
    ],
  ),
  DemoCompany(
    name: 'PharmaPlus Casablanca',
    country: 'MA',
    users: [
      DemoUser(email: 'amina.tahiri@pharmaplus.ma', name: 'Amina Tahiri', role: 'manager', managerRole: 'principal'),
      DemoUser(email: 'sara.mansouri@pharmaplus.ma', name: 'Sara Mansouri', role: 'manager', managerRole: 'rh'),
      DemoUser(email: 'youssef.bennani@pharmaplus.ma', name: 'Youssef Bennani', role: 'employee'),
    ],
  ),
  DemoCompany(
    name: 'DigitalFlow Tunis',
    country: 'TN',
    users: [
      DemoUser(email: 'sofiane.mrad@digitalflow.tn', name: 'Sofiane Mrad', role: 'manager', managerRole: 'principal'),
      DemoUser(email: 'olfa.trabelsi@digitalflow.tn', name: 'Olfa Trabelsi', role: 'manager', managerRole: 'rh'),
      DemoUser(email: 'aziz.khelifi@digitalflow.tn', name: 'Aziz Khelifi', role: 'employee'),
    ],
  ),
];

Future<DemoUser?> showDemoUserBottomSheet(BuildContext context) {
  return showModalBottomSheet<DemoUser>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.surfaceFor(context),
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    builder: (context) => DraggableScrollableSheet(
      initialChildSize: 0.7,
      maxChildSize: 0.9,
      minChildSize: 0.4,
      expand: false,
      builder: (context, scrollController) => Column(
        children: [
          Container(
            margin: const EdgeInsets.only(top: 12, bottom: 8),
            width: 40,
            height: 4,
            decoration: BoxDecoration(
              color: AppColors.borderFor(context),
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Choisir un compte demo',
                  style: AppTypography.title.copyWith(
                    color: AppColors.textPrimaryFor(context),
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView.builder(
              controller: scrollController,
              padding: const EdgeInsets.all(16),
              itemCount: demoCompanies.length,
              itemBuilder: (context, companyIndex) {
                final company = demoCompanies[companyIndex];
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (companyIndex > 0) const SizedBox(height: 16),
                    Text(
                      '${company.name} (${company.country})',
                      style: AppTypography.caption.copyWith(
                        color: AppColors.textSecondaryFor(context),
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.5,
                      ),
                    ),
                    const SizedBox(height: 8),
                    ...company.users.map((user) => Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Material(
                        color: Colors.transparent,
                        child: InkWell(
                          borderRadius: BorderRadius.circular(14),
                          onTap: () => Navigator.pop(context, user),
                          child: Container(
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              border: Border.all(color: AppColors.borderFor(context)),
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        user.name,
                                        style: AppTypography.body.copyWith(
                                          fontWeight: FontWeight.w600,
                                          color: AppColors.textPrimaryFor(context),
                                        ),
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        user.email,
                                        style: AppTypography.caption.copyWith(
                                          color: AppColors.textSecondaryFor(context),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                _RoleBadge(user: user),
                              ],
                            ),
                          ),
                        ),
                      ),
                    )),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    ),
  );
}

class _RoleBadge extends StatelessWidget {
  const _RoleBadge({required this.user});

  final DemoUser user;

  @override
  Widget build(BuildContext context) {
    final label = user.managerRole ?? user.role;
    final Color bg;
    final Color fg;

    switch (user.managerRole) {
      case 'principal':
        bg = Colors.purple.shade50;
        fg = Colors.purple.shade700;
      case 'rh':
        bg = Colors.blue.shade50;
        fg = Colors.blue.shade700;
      default:
        bg = Colors.grey.shade100;
        fg = Colors.grey.shade700;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: AppTypography.caption.copyWith(
          color: fg,
          fontWeight: FontWeight.w600,
          fontSize: 11,
        ),
      ),
    );
  }
}
