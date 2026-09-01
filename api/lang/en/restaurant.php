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
    "kitchenTitle" => "Kitchen display",
    "kitchenSubtitle" => "Real-time order queue — preparation and pickup",
    "branch" => "Branch",
    "branchAll" => "All branches",
    "inPreparation" => "In preparation",
    "ready" => "Ready",
    "empty" => "No pending orders. The queue is empty.",
    "start" => "Start",
    "markReady" => "Ready",
    "orderRef" => "Order",
    "table" => "Table",
    "covers" => "covers",
    "takeaway" => "Takeaway",
    "delivery" => "Delivery",
    "dineIn" => "Dine-in",
    "loadError" => "Unable to load the kitchen queue.",
    "actionError" => "Action failed, please retry.",
    "loading" => "Loading kitchen queue…",
    "kiosk" => [
        "title" => "Self-service kiosk",
        "tokenInvalid" => "Invalid or missing shop token.",
        "loading" => "Loading menu…",
        "emptyMenu" => "The menu is empty right now.",
        "loadError" => "Unable to load the menu.",
        "items" => "items",
        "branch" => "Branch",
        "cart" => "Cart",
        "takeaway" => "Takeaway",
        "delivery" => "Delivery",
        "empty" => "Your cart is empty.",
        "total" => "Total",
        "checkout" => "Place order",
        "add" => "Add",
        "remove" => "Remove",
        "orderPlaced" => "Order sent to the kitchen!",
        "orderReference" => "Reference",
        "payCash" => "Pay with cash",
        "payMobileMoney" => "Pay with mobile money",
        "paid" => "Payment confirmed. Enjoy!",
        "pendingPayment" => "Payment pending confirmation…",
        "startNewOrder" => "New order",
        "orderError" => "Unable to place the order.",
        "paymentError" => "Unable to process the payment.",
    ],
    "public_shop" => [
        "captcha_required" => "Anti-bot validation required (X-Captcha-Token).",
        "token_missing" => "Missing shop token (X-Restaurant-Shop-Token).",
        "token_invalid" => "Invalid shop token.",
        "tenant_not_found" => "No tenant found for this token.",
        "product_unavailable" => "Product is not available for this tenant.",
        "product_not_served" => "Product is not served by this branch.",
        "currency_mismatch" => "Product currency does not match the order currency.",
        "quantity_invalid" => "Quantity must be strictly positive.",
        "empty_order" => "An order must contain at least one item.",
    ],
    "marketplace" => [
        "invalid_payload" => "Invalid marketplace payload.",
        "missing_event_id" => "Missing marketplace event id.",
        "unknown_customer" => "Marketplace customer",
        "unknown_provider" => "Unknown delivery app provider.",
        "invalid_signature" => "Invalid marketplace signature.",
        "product_not_found" => "Product not found for code {code}.",
        "product_not_served" => "Product {code} is not served by the target branch.",
        "quantity_invalid" => "Invalid quantity in the marketplace order.",
        "empty_order" => "The marketplace order contains no items.",
        "no_branch" => "No active branch for this tenant.",
    ],
];
