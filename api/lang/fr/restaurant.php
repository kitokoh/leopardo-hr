<?php

declare(strict_types=1);

/**
 * Catalogue de traduction de la verticale RestaurantManager (BC-25 RESTAURANT).
 *
 * RESTO-906 (#6235) — clés restaurant.* dans les 4 locales (fr/en/ar/tr).
 * Miroir des clés partagées `shared/i18n/locales/*.json` (namespace
 * `restaurant`) pour la surface backend PHP — garde #5432 : toujours via
 * __() / trans(), jamais de chaîne accentuée en dur dans le code.
 */
return [
    "kitchenTitle" => "Écran cuisine",
    "kitchenSubtitle" => "File de commandes en temps réel — préparation et mise à disposition",
    "branch" => "Branche",
    "branchAll" => "Toutes les branches",
    "inPreparation" => "En préparation",
    "ready" => "Prêtes",
    "empty" => "Aucune commande en attente. La file est vide.",
    "start" => "Démarrer",
    "markReady" => "Prête",
    "orderRef" => "Commande",
    "table" => "Table",
    "covers" => "couverts",
    "takeaway" => "À emporter",
    "delivery" => "Livraison",
    "dineIn" => "Sur place",
    "loadError" => "Impossible de charger la file cuisine.",
    "actionError" => "Action impossible, réessayez.",
    "loading" => "Chargement de la file cuisine…",
    "kiosk" => [
        "title" => "Borne de commande",
        "tokenInvalid" => "Jeton de boutique invalide ou absent.",
        "loading" => "Chargement du menu…",
        "emptyMenu" => "Le menu est vide pour le moment.",
        "loadError" => "Impossible de charger le menu.",
        "items" => "articles",
        "branch" => "Branche",
        "cart" => "Panier",
        "takeaway" => "À emporter",
        "delivery" => "Livraison",
        "empty" => "Votre panier est vide.",
        "total" => "Total",
        "checkout" => "Valider la commande",
        "add" => "Ajouter",
        "remove" => "Retirer",
        "orderPlaced" => "Commande envoyée en cuisine !",
        "orderReference" => "Référence",
        "payCash" => "Payer en espèces",
        "payMobileMoney" => "Payer par mobile money",
        "paid" => "Paiement confirmé. Bon appétit !",
        "pendingPayment" => "Paiement en attente de confirmation…",
        "startNewOrder" => "Nouvelle commande",
        "orderError" => "Impossible de passer la commande.",
        "paymentError" => "Impossible de traiter le paiement.",
    ],
    "public_shop" => [
        "captcha_required" => "Validation anti-bot requise (X-Captcha-Token).",
        "token_missing" => "Jeton boutique manquant (X-Restaurant-Shop-Token).",
        "token_invalid" => "Jeton boutique invalide.",
        "tenant_not_found" => "Tenant introuvable pour ce jeton.",
        "product_unavailable" => "Produit indisponible pour ce tenant.",
        "product_not_served" => "Produit non servi par cette branche.",
        "currency_mismatch" => "La devise du produit ne correspond pas à celle de la commande.",
        "quantity_invalid" => "La quantité doit être strictement positive.",
        "empty_order" => "Une commande doit contenir au moins un article.",
    ],
    "marketplace" => [
        "invalid_payload" => "Payload marketplace invalide.",
        "missing_event_id" => "Identifiant d'événement marketplace manquant.",
        "unknown_customer" => "Client marketplace",
        "unknown_provider" => "Fournisseur d'app de livraison inconnu.",
        "invalid_signature" => "Signature marketplace invalide.",
        "product_not_found" => "Produit introuvable pour le code {code}.",
        "product_not_served" => "Produit {code} non servi par la branche cible.",
        "quantity_invalid" => "Quantité invalide dans la commande marketplace.",
        "empty_order" => "La commande marketplace ne contient aucun article.",
        "no_branch" => "Aucune branche active pour ce tenant.",
    ],
];
