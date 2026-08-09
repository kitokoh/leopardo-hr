import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:table_calendar/table_calendar.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

class EditorialCalendarScreen extends StatefulWidget {
  const EditorialCalendarScreen({super.key});

  @override
  State<EditorialCalendarScreen> createState() =>
      _EditorialCalendarScreenState();
}

class _EditorialCalendarScreenState extends State<EditorialCalendarScreen> {
  DateTime _focusedDay = DateTime.now();
  DateTime? _selectedDay;

  final Map<DateTime, List<String>> _events = {
    DateTime.now(): ['Post LinkedIn - Offre emploi', 'Post Twitter - Annonce'],
    DateTime.now().add(const Duration(days: 2)): [
      'Post Meta - Culture d\'entreprise',
    ],
  };

  List<String> _getEventsForDay(DateTime day) {
    // Basic date matching ignoring time
    for (var key in _events.keys) {
      if (key.year == day.year &&
          key.month == day.month &&
          key.day == day.day) {
        return _events[key]!;
      }
    }
    return [];
  }

  @override
  Widget build(BuildContext context) {
    return MobileSurface(
      title: 'Calendrier Éditorial',
      actions: [
        IconButton(
          icon: const Icon(Icons.add),
          onPressed: () => context.push('/create-post'),
        ),
      ],
      children: [
        GlassCard(
          padding: const EdgeInsets.all(8.0),
          child: TableCalendar(
            firstDay: DateTime.utc(2020, 10, 16),
            lastDay: DateTime.utc(2030, 3, 14),
            focusedDay: _focusedDay,
            selectedDayPredicate: (day) => isSameDay(_selectedDay, day),
            onDaySelected: (selectedDay, focusedDay) {
              setState(() {
                _selectedDay = selectedDay;
                _focusedDay = focusedDay;
              });
            },
            eventLoader: _getEventsForDay,
            calendarStyle: CalendarStyle(
              markerDecoration: BoxDecoration(
                color: AppColors.primary,
                shape: BoxShape.circle,
              ),
              selectedDecoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.5),
                shape: BoxShape.circle,
              ),
              todayDecoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.2),
                shape: BoxShape.circle,
              ),
            ),
            headerStyle: const HeaderStyle(
              formatButtonVisible: false,
              titleCentered: true,
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text('Posts du jour', style: AppTypography.headlineMedium(context)),
        const SizedBox(height: 8),
        ..._getEventsForDay(_selectedDay ?? _focusedDay).map(
          (event) => Padding(
            padding: const EdgeInsets.only(bottom: 8.0),
            child: GlassCard(
              child: ListTile(
                leading: CircleAvatar(
                  backgroundColor: AppColors.primary.withValues(alpha: 0.2),
                  child: Icon(Icons.article, color: AppColors.primary),
                ),
                title: Text(event),
                subtitle: const Text('Statut: Planifié'),
                trailing: const Icon(Icons.chevron_right),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
