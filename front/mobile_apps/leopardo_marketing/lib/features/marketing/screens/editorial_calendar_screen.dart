import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:table_calendar/table_calendar.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import '../repositories/providers.dart';

class EditorialCalendarScreen extends ConsumerStatefulWidget {
  const EditorialCalendarScreen({super.key});

  @override
  ConsumerState<EditorialCalendarScreen> createState() => _EditorialCalendarScreenState();
}

class _EditorialCalendarScreenState extends ConsumerState<EditorialCalendarScreen> {
  DateTime _focusedDay = DateTime.now();
  DateTime? _selectedDay;

  List<Map<String, dynamic>> _getEventsForDay(DateTime day, List<Map<String, dynamic>> posts) {
    return posts.where((post) {
      if (post['scheduled_at'] == null) return false;
      final postDate = DateTime.parse(post['scheduled_at']);
      return postDate.year == day.year && postDate.month == day.month && postDate.day == day.day;
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final postsAsyncValue = ref.watch(postsProvider);

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
          child: postsAsyncValue.when(
            data: (posts) => TableCalendar(
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
              eventLoader: (day) => _getEventsForDay(day, posts),
              calendarStyle: CalendarStyle(
                markerDecoration: const BoxDecoration(
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
            loading: () => const Center(child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator())),
            error: (err, stack) => Center(child: Text('Erreur: $err')),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          'Posts du jour',
          style: AppTypography.headlineMedium(context),
        ),
        const SizedBox(height: 8),
        ...postsAsyncValue.maybeWhen(
          data: (posts) {
            final dailyPosts = _getEventsForDay(_selectedDay ?? _focusedDay, posts);
            if (dailyPosts.isEmpty) {
              return [const Padding(padding: EdgeInsets.all(16), child: Text('Aucun post planifié ce jour.'))];
            }
            return dailyPosts.map((post) => Padding(
              padding: const EdgeInsets.only(bottom: 8.0),
              child: GlassCard(
                child: ListTile(
                  leading: CircleAvatar(
                    backgroundColor: AppColors.primary.withValues(alpha: 0.2),
                    child: const Icon(Icons.article, color: AppColors.primary),
                  ),
                  title: Text(post['content']?.toString().split('\n').first ?? 'Post', maxLines: 1, overflow: TextOverflow.ellipsis),
                  subtitle: Text('Statut: ${post['status'] ?? 'Planifié'}'),
                  trailing: const Icon(Icons.chevron_right),
                ),
              ),
            ));
          },
          orElse: () => [],
        ),
      ],
    );
  }
}
