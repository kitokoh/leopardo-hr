import React, { useCallback, useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import {
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from '../../api/hr';
import { extractErrorMessage } from '../../api/client';
import { ScreenState } from '../../components/ScreenState';
import { Card, EmptyState } from '../../components/Card';
import { ScreenHeader } from '../../components/ScreenHeader';
import type { AppNotification } from '../../models/notification';
import { isUnread, notificationText } from '../../models/notification';
import { relativeTime } from '../../utils/format';
import { colors, radius, spacing, typography } from '../../theme';

export function NotificationsScreen() {
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setError(null);
    try {
      setNotifications(await fetchNotifications());
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const unreadCount = notifications.filter(isUnread).length;

  async function handleRead(notification: AppNotification) {
    if (!isUnread(notification)) return;
    // Mise a jour optimiste : l'utilisateur voit l'effet immediatement.
    setNotifications((current) =>
      current.map((item) =>
        item.id === notification.id
          ? { ...item, is_read: true, read_at: new Date().toISOString() }
          : item,
      ),
    );
    try {
      await markNotificationRead(notification.id);
    } catch {
      load(true);
    }
  }

  async function handleReadAll() {
    if (unreadCount === 0) return;
    setNotifications((current) =>
      current.map((item) => ({
        ...item,
        is_read: true,
        read_at: item.read_at ?? new Date().toISOString(),
      })),
    );
    try {
      await markAllNotificationsRead();
    } catch {
      load(true);
    }
  }

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader
        title="Notifications"
        subtitle={unreadCount > 0 ? `${unreadCount} non lue${unreadCount > 1 ? 's' : ''}` : 'Tout est a jour'}
        right={
          unreadCount > 0 ? (
            <Pressable accessibilityRole="button" onPress={handleReadAll}>
              <Text style={styles.readAll}>Tout lire</Text>
            </Pressable>
          ) : undefined
        }
      />

      <ScreenState
        loading={loading}
        error={error}
        onRetry={() => load()}
        refreshing={refreshing}
        onRefresh={() => load(true)}
      >
        {notifications.length === 0 ? (
          <EmptyState
            title="Aucune notification"
            message="Vos alertes et rappels apparaitront ici."
          />
        ) : (
          notifications.map((notification) => {
            const unread = isUnread(notification);
            return (
              <Pressable
                key={notification.id}
                accessibilityRole="button"
                accessibilityLabel={`${notification.title ?? 'Notification'}${unread ? ' — non lue' : ''}`}
                onPress={() => handleRead(notification)}
              >
                <Card style={[styles.card, unread && styles.cardUnread]}>
                  <View style={styles.row}>
                    {unread ? <View style={styles.dot} /> : <View style={styles.dotSpacer} />}
                    <View style={styles.texts}>
                      <Text style={[styles.title, unread && styles.titleUnread]}>
                        {notification.title ?? 'Notification'}
                      </Text>
                      <Text style={styles.message} numberOfLines={3}>
                        {notificationText(notification)}
                      </Text>
                      <Text style={styles.time}>{relativeTime(notification.created_at)}</Text>
                    </View>
                  </View>
                </Card>
              </Pressable>
            );
          })
        )}
      </ScreenState>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  readAll: { color: colors.primary, fontSize: typography.body, fontWeight: '600' },
  card: { marginBottom: spacing.sm },
  cardUnread: { borderColor: `${colors.primary}66`, backgroundColor: colors.surfaceAlt },
  row: { flexDirection: 'row', alignItems: 'flex-start' },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: colors.primary,
    marginTop: 6,
    marginRight: spacing.md,
  },
  dotSpacer: { width: 8, marginRight: spacing.md },
  texts: { flex: 1 },
  title: { color: colors.text, fontSize: typography.body, fontWeight: '600' },
  titleUnread: { fontWeight: '800' },
  message: { color: colors.textMuted, fontSize: typography.caption, marginTop: 2, lineHeight: 18 },
  time: { color: colors.textFaint, fontSize: 10, marginTop: spacing.xs },
});
