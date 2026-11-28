import React from 'react';
import { StatusBar } from 'expo-status-bar';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AppNavigator } from './src/navigation/AppNavigator';

/**
 * LEOPARDO — application GRAND PUBLIC.
 *
 * Distincte de `leopardo_mobile_expo` (l'app RH interne) : celle-ci s'adresse
 * aux clients et particuliers, sans compte ni jeton. Elle couvre les 9
 * verticales du web, chacune avec son parcours reel.
 *
 * Aucun ecran d'authentification : toutes les routes appelees sont publiques.
 */
export default function App() {
  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <StatusBar style="dark" />
        <AppNavigator />
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}
