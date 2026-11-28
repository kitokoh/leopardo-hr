import React from 'react';
import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { colors } from '../theme';
import type { RootStackParamList } from './types';

import { HomeScreen } from '../screens/HomeScreen';
import { ExploreScreen } from '../screens/ExploreScreen';
import { ConfirmationScreen } from '../screens/ConfirmationScreen';
import { ContactScreen } from '../screens/ContactScreen';
import { TrackScreen } from '../screens/TrackScreen';

import { RestaurantScreen } from '../screens/restaurant/RestaurantScreen';
import { RestaurantCheckoutScreen } from '../screens/restaurant/RestaurantCheckoutScreen';

import { SellersScreen } from '../screens/shop/SellersScreen';
import { ShopScreen } from '../screens/shop/ShopScreen';
import { ShopCheckoutScreen } from '../screens/shop/ShopCheckoutScreen';

import { StayScreen } from '../screens/stay/StayScreen';
import { StayBookingScreen } from '../screens/stay/StayBookingScreen';

import { TravelScreen } from '../screens/travel/TravelScreen';
import { TravelTripScreen } from '../screens/travel/TravelTripScreen';

import { VtcScreen } from '../screens/vtc/VtcScreen';
import { VtcRideScreen } from '../screens/vtc/VtcRideScreen';

import { TradeScreen } from '../screens/trade/TradeScreen';
import { PharmacyScreen } from '../screens/pharmacy/PharmacyScreen';
import { EducationScreen } from '../screens/education/EducationScreen';
import { FuelScreen } from '../screens/fuel/FuelScreen';

const Stack = createNativeStackNavigator<RootStackParamList>();

const theme = {
  ...DefaultTheme,
  colors: {
    ...DefaultTheme.colors,
    background: colors.background,
    card: colors.surface,
    text: colors.text,
    primary: colors.primary,
    border: colors.border,
  },
};

/**
 * NAVIGATION DE L'APP GRAND PUBLIC.
 *
 * Pile unique, en-tetes masques : chaque ecran dessine son propre
 * `ScreenHeader` (retour accessible, sous-titre contextuel). Un en-tete natif
 * en plus du notre afficherait deux barres de titre.
 */
export function AppNavigator() {
  return (
    <NavigationContainer theme={theme}>
      <Stack.Navigator
        initialRouteName="Home"
        screenOptions={{
          headerShown: false,
          contentStyle: { backgroundColor: colors.background },
          animation: 'slide_from_right',
        }}
      >
        <Stack.Screen name="Home" component={HomeScreen} />
        <Stack.Screen name="Explore" component={ExploreScreen} />

        <Stack.Screen name="Restaurant" component={RestaurantScreen} />
        <Stack.Screen name="RestaurantCheckout" component={RestaurantCheckoutScreen} />

        <Stack.Screen name="Sellers" component={SellersScreen} />
        <Stack.Screen name="Shop" component={ShopScreen} />
        <Stack.Screen name="ShopCheckout" component={ShopCheckoutScreen} />

        <Stack.Screen name="Stay" component={StayScreen} />
        <Stack.Screen name="StayBooking" component={StayBookingScreen} />

        <Stack.Screen name="Travel" component={TravelScreen} />
        <Stack.Screen name="TravelTrip" component={TravelTripScreen} />

        <Stack.Screen name="Vtc" component={VtcScreen} />
        <Stack.Screen name="VtcRide" component={VtcRideScreen} />

        <Stack.Screen name="Trade" component={TradeScreen} />
        <Stack.Screen name="Pharmacy" component={PharmacyScreen} />
        <Stack.Screen name="Education" component={EducationScreen} />
        <Stack.Screen name="Fuel" component={FuelScreen} />

        <Stack.Screen name="Contact" component={ContactScreen} />
        <Stack.Screen name="Track" component={TrackScreen} />
        <Stack.Screen
          name="Confirmation"
          component={ConfirmationScreen}
          options={{ gestureEnabled: false }}
        />
      </Stack.Navigator>
    </NavigationContainer>
  );
}
