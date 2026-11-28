import type { NativeStackScreenProps } from '@react-navigation/native-stack';

/**
 * Carte de navigation de l'app grand public.
 *
 * Chaque verticale a son point d'entree, et les parcours transactionnels ont
 * leurs ecrans dedies : un ecran unique « fourre-tout » ne permettrait ni un
 * retour correct, ni un etat de panier propre.
 */
export type RootStackParamList = {
  Home: undefined;
  Explore: { type?: string | null } | undefined;

  Restaurant: { slug: string };
  RestaurantCheckout: { slug: string; restaurantName: string };

  Sellers: undefined;
  Shop: { sellerSlug: string; sellerName: string };
  ShopCheckout: { sellerSlug: string; sellerName: string };

  Stay: { slug: string };
  StayBooking: { slug: string; propertyName: string; roomTypeId: number; roomName: string };

  Travel: undefined;
  TravelTrip: { tripId: number };

  Vtc: undefined;
  VtcRide: { operatorSlug: string; operatorName: string };

  Trade: { slug: string };
  Pharmacy: undefined;
  Education: undefined;
  Fuel: undefined;

  Contact: { targetType: string; targetSlug: string; targetName: string };
  Track: undefined;
  Confirmation: {
    title: string;
    reference: string;
    code?: string | null;
    message: string;
    totalMinor?: number | null;
    currency?: string | null;
  };
};

export type ScreenProps<T extends keyof RootStackParamList> = NativeStackScreenProps<
  RootStackParamList,
  T
>;
