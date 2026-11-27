import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useAuth } from '../api/AuthContext';
import { LoginScreen } from '../screens/auth/LoginScreen';
import { TwoFactorScreen } from '../screens/auth/TwoFactorScreen';
import { SplashScreen } from '../screens/auth/SplashScreen';
import { HomeScreen } from '../screens/main/HomeScreen';
import { AttendanceScreen } from '../screens/main/AttendanceScreen';
import { AbsencesScreen } from '../screens/main/AbsencesScreen';
import { PayrollScreen } from '../screens/main/PayrollScreen';
import { SalaryAdvancesScreen } from '../screens/main/SalaryAdvancesScreen';
import { TasksScreen } from '../screens/main/TasksScreen';
import { SchedulesScreen } from '../screens/main/SchedulesScreen';
import { TeamScreen } from '../screens/main/TeamScreen';
import { NotificationsScreen } from '../screens/main/NotificationsScreen';
import { ProfileScreen } from '../screens/main/ProfileScreen';
import { VerticalsScreen } from '../screens/modules/VerticalsScreen';
import { ModuleScreen } from '../screens/modules/ModuleScreen';
import { ModuleActionScreen } from '../screens/modules/ModuleActionScreen';
import { ModulesPlanScreen } from '../screens/modules/ModulesPlanScreen';
import { MODULE_WRITES } from '../verticals/actions';
import { canManageTeam } from '../models/employee';
import { getBusinessModules } from '../verticals/access';
import type { ClientModuleKey } from '../verticals/types';
import { colors, spacing, typography } from '../theme';

export type RootStackParamList = {
  Login: undefined;
  TwoFactor: undefined;
  Main: undefined;
};

export type MainTabParamList = {
  Home: undefined;
  Verticals: undefined;
  Attendance: undefined;
  Absences: undefined;
  Payroll: undefined;
  Profile: undefined;
};

export type MainStackParamList = {
  Tabs: undefined;
  Tasks: undefined;
  Schedules: undefined;
  SalaryAdvances: undefined;
  Team: undefined;
  Notifications: undefined;
  /** Ecran generique d'un module metier (verticale ou socle). */
  Module: { moduleKey: ClientModuleKey };
  /** Formulaire d'action d'ecriture d'un module. */
  ModuleAction: { moduleKey: ClientModuleKey; actionKey: string; row?: boolean };
  /** Panneau « Modules & plan » : catalogue complet + etats. */
  ModulesPlan: undefined;
};

const RootStack = createNativeStackNavigator<RootStackParamList>();
const MainStack = createNativeStackNavigator<MainStackParamList>();
const Tab = createBottomTabNavigator<MainTabParamList>();

/** Icone textuelle legere : evite une dependance d'icones supplementaire. */
function TabIcon({ label, focused }: { label: string; focused: boolean }) {
  return (
    <View style={[styles.tabIcon, focused && styles.tabIconFocused]}>
      <Text style={[styles.tabIconText, focused && styles.tabIconTextFocused]}>
        {label}
      </Text>
    </View>
  );
}

function MainTabs() {
  const { employee } = useAuth();
  const isManager = canManageTeam(employee);
  // L'onglet « Metier » n'apparait que si au moins une verticale est active :
  // un tenant sans module metier ne voit pas un onglet vide.
  const hasVerticals = getBusinessModules(employee).length > 0;

  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: false,
        tabBarStyle: styles.tabBar,
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.textFaint,
        tabBarLabelStyle: styles.tabLabel,
      }}
    >
      <Tab.Screen
        name="Home"
        options={{
          title: 'Accueil',
          tabBarIcon: ({ focused }) => <TabIcon label="⌂" focused={focused} />,
        }}
      >
        {({ navigation }) => (
          <HomeScreen
            onNavigate={(route) => {
              if (route === 'Team' && !isManager) return;
              navigation.getParent()?.navigate(route as never);
            }}
          />
        )}
      </Tab.Screen>

      {hasVerticals ? (
        <Tab.Screen
          name="Verticals"
          options={{
            title: 'Metier',
            tabBarIcon: ({ focused }) => <TabIcon label="▦" focused={focused} />,
          }}
        >
          {({ navigation }) => (
            <VerticalsScreen
              onOpenModule={(moduleKey) =>
                navigation.getParent()?.navigate('Module', { moduleKey })
              }
            />
          )}
        </Tab.Screen>
      ) : null}

      <Tab.Screen
        name="Attendance"
        component={AttendanceScreen}
        options={{
          title: 'Pointage',
          tabBarIcon: ({ focused }) => <TabIcon label="◷" focused={focused} />,
        }}
      />

      <Tab.Screen
        name="Absences"
        component={AbsencesScreen}
        options={{
          title: 'Absences',
          tabBarIcon: ({ focused }) => <TabIcon label="▤" focused={focused} />,
        }}
      />

      <Tab.Screen
        name="Payroll"
        component={PayrollScreen}
        options={{
          title: 'Paie',
          tabBarIcon: ({ focused }) => <TabIcon label="◈" focused={focused} />,
        }}
      />

      <Tab.Screen
        name="Profile"
        options={{
          title: 'Compte',
          tabBarIcon: ({ focused }) => <TabIcon label="◉" focused={focused} />,
        }}
      >
        {({ navigation }) => (
          <ProfileScreen
            onNavigate={(route) => navigation.getParent()?.navigate(route as never)}
          />
        )}
      </Tab.Screen>
    </Tab.Navigator>
  );
}

function MainNavigator() {
  const { employee } = useAuth();

  return (
    <MainStack.Navigator
      screenOptions={{
        headerShown: false,
        contentStyle: { backgroundColor: colors.background },
      }}
    >
      <MainStack.Screen name="Tabs" component={MainTabs} />
      <MainStack.Screen name="Tasks" component={TasksScreen} />
      <MainStack.Screen name="Schedules" component={SchedulesScreen} />
      <MainStack.Screen name="SalaryAdvances" component={SalaryAdvancesScreen} />
      <MainStack.Screen name="Team" component={TeamScreen} />
      <MainStack.Screen name="Notifications" component={NotificationsScreen} />
      <MainStack.Screen
        name="Module"
        options={({ route }) => ({ title: route.params.moduleKey })}
      >
        {({ route, navigation }) => (
          <ModuleScreen
            moduleKey={route.params.moduleKey}
            currency={employee?.currency ?? undefined}
            onBack={() => navigation.goBack()}
            onOpenAction={(moduleKey, actionKey) =>
              navigation.navigate('ModuleAction', { moduleKey, actionKey })
            }
            onOpenRowAction={(moduleKey, actionKey) =>
              navigation.navigate('ModuleAction', { moduleKey, actionKey, row: true })
            }
          />
        )}
      </MainStack.Screen>
      <MainStack.Screen name="ModuleAction">
        {({ route, navigation }) => {
          const { moduleKey, actionKey, row } = route.params;
          const writes = MODULE_WRITES[moduleKey];
          const action = row ? undefined : writes?.actions?.find((a) => a.key === actionKey);
          const rowAction = row ? writes?.rowActions?.find((a) => a.key === actionKey) : undefined;

          return (
            <ModuleActionScreen
              title={action?.label ?? rowAction?.label ?? 'Action'}
              subtitle={action?.description ?? rowAction?.description}
              action={action}
              rowAction={rowAction}
              onBack={() => navigation.goBack()}
              onDone={() => navigation.goBack()}
            />
          );
        }}
      </MainStack.Screen>
      <MainStack.Screen name="ModulesPlan">
        {({ navigation }) => (
          <ModulesPlanScreen
            onOpenModule={(moduleKey) => navigation.navigate('Module', { moduleKey })}
          />
        )}
      </MainStack.Screen>
    </MainStack.Navigator>
  );
}

export function AppNavigator() {
  const { employee, booting, pendingChallenge } = useAuth();

  if (booting) return <SplashScreen />;

  return (
    <RootStack.Navigator
      screenOptions={{
        headerShown: false,
        contentStyle: { backgroundColor: colors.background },
      }}
    >
      {employee ? (
        <RootStack.Screen name="Main" component={MainNavigator} />
      ) : pendingChallenge ? (
        <RootStack.Screen name="TwoFactor" component={TwoFactorScreen} />
      ) : (
        <RootStack.Screen name="Login" component={LoginScreen} />
      )}
    </RootStack.Navigator>
  );
}

const styles = StyleSheet.create({
  tabBar: {
    backgroundColor: colors.surface,
    borderTopColor: colors.border,
    borderTopWidth: 1,
    height: 62,
    paddingTop: spacing.xs,
    paddingBottom: spacing.sm,
  },
  tabLabel: { fontSize: 10, fontWeight: '600' },
  tabIcon: {
    width: 26,
    height: 26,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  tabIconFocused: { backgroundColor: `${colors.primary}22` },
  tabIconText: { color: colors.textFaint, fontSize: 15 },
  tabIconTextFocused: { color: colors.primary },
});
