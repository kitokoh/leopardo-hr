<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Database\Seeders;

use App\Modules\Connectors\Domain\Models\ConnectorCatalogEntry;
use Illuminate\Database\Seeder;

/**
 * #9838 (BC-14 INTEGRATION) — catalogue initial des connecteurs.
 *
 * Ce seeder est IDEMPOTENT (`updateOrCreate` sur `key`) : le rejouer met à
 * jour les schémas sans dupliquer les entrées, et sans toucher aux connexions
 * déjà créées par les utilisateurs.
 *
 * Le contenu est volontairement RÉEL : les banques et opérateurs mobile money
 * effectivement utilisés par les clients de la plateforme (Afrique de l'Ouest
 * et centrale, plus les banques internationales), les PSP déjà supportés par
 * le code (Stripe, Chargily), et les plateformes sur lesquelles un tenant vend
 * réellement des produits (WordPress/WooCommerce, Shopify, Facebook, Instagram,
 * TikTok Shop, Google Merchant, Amazon).
 *
 * Chaque `field_schema` est la description EXACTE du formulaire que le front
 * génère : ajouter un champ ici suffit à le faire apparaître dans les deux
 * écrans, sans redéploiement du front.
 */
class ConnectorCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->entries() as $entry) {
            ConnectorCatalogEntry::query()->updateOrCreate(
                ['key' => $entry['key']],
                $entry,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entries(): array
    {
        return [
            // ── Banques de la PLATEFORME (reçoivent les paiements des tenants) ──
            [
                'key' => 'bank_transfer',
                'category' => 'bank',
                'audience' => 'platform',
                'label' => 'Compte bancaire (virement)',
                'provider' => null,
                'docs_url' => null,
                'sort_order' => 10,
                'field_schema' => [
                    ['key' => 'bank_name', 'label' => 'Nom de la banque', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex. Ecobank'],
                    ['key' => 'account_holder', 'label' => 'Titulaire du compte', 'type' => 'text', 'required' => true],
                    ['key' => 'iban', 'label' => 'IBAN / RIB', 'type' => 'text', 'secret' => true, 'required' => true, 'help' => 'Chiffré au repos. Jamais renvoyé en clair.'],
                    ['key' => 'bic', 'label' => 'BIC / SWIFT', 'type' => 'text', 'required' => false, 'placeholder' => 'ECOCFRPP'],
                    ['key' => 'currency', 'label' => 'Devise du compte', 'type' => 'select', 'required' => true, 'options' => [
                        ['value' => 'EUR', 'label' => 'EUR — Euro'],
                        ['value' => 'USD', 'label' => 'USD — Dollar'],
                        ['value' => 'XOF', 'label' => 'XOF — Franc CFA (UEMOA)'],
                        ['value' => 'XAF', 'label' => 'XAF — Franc CFA (CEMAC)'],
                        ['value' => 'MAD', 'label' => 'MAD — Dirham'],
                        ['value' => 'TRY', 'label' => 'TRY — Livre turque'],
                    ]],
                ],
            ],
            [
                'key' => 'bank_ecobank',
                'category' => 'bank',
                'audience' => 'platform',
                'label' => 'Ecobank',
                'provider' => 'Ecobank',
                'docs_url' => 'https://www.ecobank.com/',
                'sort_order' => 20,
                'field_schema' => [
                    ['key' => 'account_holder', 'label' => 'Titulaire du compte', 'type' => 'text', 'required' => true],
                    ['key' => 'iban', 'label' => 'IBAN / Numéro de compte', 'type' => 'text', 'secret' => true, 'required' => true],
                    ['key' => 'bic', 'label' => 'BIC / SWIFT', 'type' => 'text', 'required' => false, 'placeholder' => 'ECOCFRPP'],
                    ['key' => 'branch', 'label' => 'Agence', 'type' => 'text', 'required' => false],
                ],
            ],
            [
                'key' => 'bank_bnp',
                'category' => 'bank',
                'audience' => 'platform',
                'label' => 'BNP Paribas',
                'provider' => 'BNP Paribas',
                'docs_url' => 'https://group.bnpparibas/',
                'sort_order' => 30,
                'field_schema' => [
                    ['key' => 'account_holder', 'label' => 'Titulaire du compte', 'type' => 'text', 'required' => true],
                    ['key' => 'iban', 'label' => 'IBAN', 'type' => 'text', 'secret' => true, 'required' => true],
                    ['key' => 'bic', 'label' => 'BIC / SWIFT', 'type' => 'text', 'required' => false, 'placeholder' => 'BNPAFRPP'],
                ],
            ],
            [
                'key' => 'bank_societe_generale',
                'category' => 'bank',
                'audience' => 'platform',
                'label' => 'Société Générale',
                'provider' => 'Société Générale',
                'docs_url' => 'https://www.societegenerale.com/',
                'sort_order' => 40,
                'field_schema' => [
                    ['key' => 'account_holder', 'label' => 'Titulaire du compte', 'type' => 'text', 'required' => true],
                    ['key' => 'iban', 'label' => 'IBAN', 'type' => 'text', 'secret' => true, 'required' => true],
                    ['key' => 'bic', 'label' => 'BIC / SWIFT', 'type' => 'text', 'required' => false, 'placeholder' => 'SOGEFRPP'],
                ],
            ],
            [
                'key' => 'mobile_money_orange',
                'category' => 'bank',
                'audience' => 'platform',
                'label' => 'Orange Money',
                'provider' => 'Orange',
                'docs_url' => 'https://developer.orange.com/',
                'sort_order' => 50,
                'field_schema' => [
                    ['key' => 'account_holder', 'label' => 'Titulaire du compte', 'type' => 'text', 'required' => true],
                    ['key' => 'phone_number', 'label' => 'Numéro marchand', 'type' => 'tel', 'secret' => true, 'required' => true],
                    ['key' => 'merchant_code', 'label' => 'Code marchand', 'type' => 'text', 'required' => false],
                    ['key' => 'api_key', 'label' => 'Clé API', 'type' => 'password', 'secret' => true, 'required' => false],
                ],
            ],
            [
                'key' => 'mobile_money_wave',
                'category' => 'bank',
                'audience' => 'platform',
                'label' => 'Wave',
                'provider' => 'Wave Mobile Money',
                'docs_url' => 'https://docs.wave.com/',
                'sort_order' => 60,
                'field_schema' => [
                    ['key' => 'account_holder', 'label' => 'Titulaire du compte', 'type' => 'text', 'required' => true],
                    ['key' => 'phone_number', 'label' => 'Numéro marchand', 'type' => 'tel', 'secret' => true, 'required' => true],
                    ['key' => 'api_key', 'label' => 'Clé API Wave', 'type' => 'password', 'secret' => true, 'required' => false],
                ],
            ],
            [
                'key' => 'mobile_money_mtn',
                'category' => 'bank',
                'audience' => 'platform',
                'label' => 'MTN Mobile Money',
                'provider' => 'MTN',
                'docs_url' => 'https://momodeveloper.mtn.com/',
                'sort_order' => 70,
                'field_schema' => [
                    ['key' => 'account_holder', 'label' => 'Titulaire du compte', 'type' => 'text', 'required' => true],
                    ['key' => 'phone_number', 'label' => 'Numéro marchand', 'type' => 'tel', 'secret' => true, 'required' => true],
                    ['key' => 'subscription_key', 'label' => 'Subscription key', 'type' => 'password', 'secret' => true, 'required' => false],
                ],
            ],

            // ── Paiement (les deux surfaces) ──
            [
                'key' => 'stripe',
                'category' => 'payment',
                'audience' => 'both',
                'label' => 'Stripe',
                'provider' => 'Stripe',
                'docs_url' => 'https://dashboard.stripe.com/apikeys',
                'sort_order' => 100,
                'field_schema' => [
                    ['key' => 'account_id', 'label' => 'Identifiant de compte (acct_…)', 'type' => 'text', 'required' => false],
                    ['key' => 'secret_key', 'label' => 'Clé secrète', 'type' => 'password', 'secret' => true, 'required' => true, 'placeholder' => 'sk_live_…'],
                    ['key' => 'publishable_key', 'label' => 'Clé publique', 'type' => 'text', 'required' => false, 'placeholder' => 'pk_live_…'],
                    ['key' => 'webhook_secret', 'label' => 'Secret de webhook', 'type' => 'password', 'secret' => true, 'required' => false, 'placeholder' => 'whsec_…'],
                ],
            ],
            [
                'key' => 'chargily',
                'category' => 'payment',
                'audience' => 'both',
                'label' => 'Chargily',
                'provider' => 'Chargily',
                'docs_url' => 'https://dev.chargily.com/',
                'sort_order' => 110,
                'field_schema' => [
                    ['key' => 'api_key', 'label' => 'Clé API', 'type' => 'password', 'secret' => true, 'required' => true],
                    ['key' => 'webhook_secret', 'label' => 'Secret de webhook', 'type' => 'password', 'secret' => true, 'required' => false],
                    ['key' => 'mode', 'label' => 'Mode', 'type' => 'select', 'required' => true, 'options' => [
                        ['value' => 'test', 'label' => 'Test'],
                        ['value' => 'live', 'label' => 'Live'],
                    ]],
                ],
            ],
            [
                'key' => 'paypal',
                'category' => 'payment',
                'audience' => 'both',
                'label' => 'PayPal',
                'provider' => 'PayPal',
                'docs_url' => 'https://developer.paypal.com/',
                'sort_order' => 120,
                'field_schema' => [
                    ['key' => 'merchant_email', 'label' => 'E-mail marchand', 'type' => 'email', 'required' => true],
                    ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => false],
                    ['key' => 'client_secret', 'label' => 'Client secret', 'type' => 'password', 'secret' => true, 'required' => false],
                ],
            ],

            // ── Site & e-commerce (TENANT) ──
            [
                'key' => 'wordpress',
                'category' => 'website',
                'audience' => 'tenant',
                'label' => 'Site WordPress',
                'provider' => 'WordPress',
                'docs_url' => 'https://developer.wordpress.org/rest-api/',
                'sort_order' => 200,
                'field_schema' => [
                    ['key' => 'site_url', 'label' => 'URL du site', 'type' => 'url', 'required' => true, 'placeholder' => 'https://mon-site.com'],
                    ['key' => 'username', 'label' => 'Utilisateur', 'type' => 'text', 'required' => false],
                    ['key' => 'app_password', 'label' => 'Mot de passe d\'application', 'type' => 'password', 'secret' => true, 'required' => false, 'help' => 'WordPress → Utilisateurs → Profil → Mots de passe d\'application.'],
                ],
            ],
            [
                'key' => 'woocommerce',
                'category' => 'ecommerce',
                'audience' => 'tenant',
                'label' => 'Boutique WooCommerce',
                'provider' => 'WooCommerce',
                'docs_url' => 'https://woocommerce.github.io/woocommerce-rest-api-docs/',
                'sort_order' => 210,
                'field_schema' => [
                    ['key' => 'site_url', 'label' => 'URL de la boutique', 'type' => 'url', 'required' => true, 'placeholder' => 'https://boutique.mon-site.com'],
                    ['key' => 'consumer_key', 'label' => 'Consumer key', 'type' => 'text', 'required' => true, 'placeholder' => 'ck_…'],
                    ['key' => 'consumer_secret', 'label' => 'Consumer secret', 'type' => 'password', 'secret' => true, 'required' => true, 'placeholder' => 'cs_…'],
                ],
            ],
            [
                'key' => 'shopify',
                'category' => 'ecommerce',
                'audience' => 'tenant',
                'label' => 'Boutique Shopify',
                'provider' => 'Shopify',
                'docs_url' => 'https://shopify.dev/docs/api/admin',
                'sort_order' => 220,
                'field_schema' => [
                    ['key' => 'shop_domain', 'label' => 'Domaine de la boutique', 'type' => 'text', 'required' => true, 'placeholder' => 'ma-boutique.myshopify.com'],
                    ['key' => 'access_token', 'label' => 'Jeton d\'accès Admin API', 'type' => 'password', 'secret' => true, 'required' => true, 'placeholder' => 'shpat_…'],
                    ['key' => 'api_version', 'label' => 'Version d\'API', 'type' => 'text', 'required' => false, 'placeholder' => '2026-01'],
                ],
            ],
            [
                'key' => 'prestashop',
                'category' => 'ecommerce',
                'audience' => 'tenant',
                'label' => 'Boutique PrestaShop',
                'provider' => 'PrestaShop',
                'docs_url' => 'https://devdocs.prestashop-project.org/',
                'sort_order' => 230,
                'field_schema' => [
                    ['key' => 'site_url', 'label' => 'URL de la boutique', 'type' => 'url', 'required' => true],
                    ['key' => 'api_key', 'label' => 'Clé webservice', 'type' => 'password', 'secret' => true, 'required' => true],
                ],
            ],
            [
                'key' => 'magento',
                'category' => 'ecommerce',
                'audience' => 'tenant',
                'label' => 'Boutique Magento',
                'provider' => 'Adobe Commerce',
                'docs_url' => 'https://developer.adobe.com/commerce/webapi/',
                'sort_order' => 240,
                'field_schema' => [
                    ['key' => 'site_url', 'label' => 'URL de la boutique', 'type' => 'url', 'required' => true],
                    ['key' => 'access_token', 'label' => 'Jeton d\'intégration', 'type' => 'password', 'secret' => true, 'required' => true],
                ],
            ],

            // ── Réseaux sociaux & marketplaces (TENANT) ──
            [
                'key' => 'facebook_page',
                'category' => 'social',
                'audience' => 'tenant',
                'label' => 'Page produit Facebook',
                'provider' => 'Meta',
                'docs_url' => 'https://developers.facebook.com/docs/pages-api/',
                'sort_order' => 300,
                'field_schema' => [
                    ['key' => 'page_url', 'label' => 'URL de la page', 'type' => 'url', 'required' => true, 'placeholder' => 'https://facebook.com/ma-page'],
                    ['key' => 'page_id', 'label' => 'Identifiant de page', 'type' => 'text', 'required' => true],
                    ['key' => 'catalog_id', 'label' => 'Identifiant du catalogue produits', 'type' => 'text', 'required' => false],
                    ['key' => 'access_token', 'label' => 'Jeton d\'accès de page', 'type' => 'password', 'secret' => true, 'required' => true],
                ],
            ],
            [
                'key' => 'instagram_shop',
                'category' => 'social',
                'audience' => 'tenant',
                'label' => 'Boutique Instagram',
                'provider' => 'Meta',
                'docs_url' => 'https://developers.facebook.com/docs/instagram-api/',
                'sort_order' => 310,
                'field_schema' => [
                    ['key' => 'account_url', 'label' => 'URL du compte', 'type' => 'url', 'required' => true],
                    ['key' => 'ig_user_id', 'label' => 'Identifiant du compte professionnel', 'type' => 'text', 'required' => true],
                    ['key' => 'access_token', 'label' => 'Jeton d\'accès', 'type' => 'password', 'secret' => true, 'required' => true],
                ],
            ],
            [
                'key' => 'tiktok_shop',
                'category' => 'marketplace',
                'audience' => 'tenant',
                'label' => 'TikTok Shop',
                'provider' => 'TikTok',
                'docs_url' => 'https://partner.tiktokshop.com/doc',
                'sort_order' => 320,
                'field_schema' => [
                    ['key' => 'shop_id', 'label' => 'Identifiant de boutique', 'type' => 'text', 'required' => true],
                    ['key' => 'app_key', 'label' => 'App key', 'type' => 'text', 'required' => true],
                    ['key' => 'app_secret', 'label' => 'App secret', 'type' => 'password', 'secret' => true, 'required' => true],
                    ['key' => 'access_token', 'label' => 'Jeton d\'accès', 'type' => 'password', 'secret' => true, 'required' => false],
                ],
            ],
            [
                'key' => 'google_merchant',
                'category' => 'marketplace',
                'audience' => 'tenant',
                'label' => 'Google Merchant Center',
                'provider' => 'Google',
                'docs_url' => 'https://developers.google.com/shopping-content',
                'sort_order' => 330,
                'field_schema' => [
                    ['key' => 'merchant_id', 'label' => 'Identifiant marchand', 'type' => 'text', 'required' => true],
                    ['key' => 'service_account_email', 'label' => 'E-mail du compte de service', 'type' => 'email', 'required' => false],
                    ['key' => 'service_account_key', 'label' => 'Clé du compte de service (JSON)', 'type' => 'textarea', 'secret' => true, 'required' => false],
                ],
            ],
            [
                'key' => 'amazon_seller',
                'category' => 'marketplace',
                'audience' => 'tenant',
                'label' => 'Amazon Seller Central',
                'provider' => 'Amazon',
                'docs_url' => 'https://developer-docs.amazon.com/sp-api/',
                'sort_order' => 340,
                'field_schema' => [
                    ['key' => 'seller_id', 'label' => 'Seller ID', 'type' => 'text', 'required' => true],
                    ['key' => 'marketplace_id', 'label' => 'Marketplace ID', 'type' => 'text', 'required' => false],
                    ['key' => 'refresh_token', 'label' => 'Refresh token', 'type' => 'password', 'secret' => true, 'required' => true],
                ],
            ],

            // ── Messagerie (TENANT) ──
            [
                'key' => 'whatsapp_business',
                'category' => 'messaging',
                'audience' => 'tenant',
                'label' => 'WhatsApp Business',
                'provider' => 'Meta',
                'docs_url' => 'https://developers.facebook.com/docs/whatsapp/cloud-api',
                'sort_order' => 400,
                'field_schema' => [
                    ['key' => 'phone_number_id', 'label' => 'Phone number ID', 'type' => 'text', 'required' => true],
                    ['key' => 'business_account_id', 'label' => 'WhatsApp Business Account ID', 'type' => 'text', 'required' => false],
                    ['key' => 'access_token', 'label' => 'Jeton d\'accès permanent', 'type' => 'password', 'secret' => true, 'required' => true],
                ],
            ],

            // ── Analytics (TENANT) ──
            [
                'key' => 'google_analytics_4',
                'category' => 'analytics',
                'audience' => 'tenant',
                'label' => 'Google Analytics 4',
                'provider' => 'Google',
                'docs_url' => 'https://developers.google.com/analytics/devguides/reporting/data/v1',
                'sort_order' => 500,
                'field_schema' => [
                    ['key' => 'property_id', 'label' => 'Property ID', 'type' => 'text', 'required' => true, 'placeholder' => '123456789'],
                    ['key' => 'service_account_key', 'label' => 'Clé du compte de service (JSON)', 'type' => 'textarea', 'secret' => true, 'required' => false],
                ],
            ],
            [
                'key' => 'google_search_console',
                'category' => 'analytics',
                'audience' => 'tenant',
                'label' => 'Google Search Console',
                'provider' => 'Google',
                'docs_url' => 'https://developers.google.com/webmaster-tools',
                'sort_order' => 510,
                'field_schema' => [
                    ['key' => 'site_url', 'label' => 'Propriété vérifiée', 'type' => 'url', 'required' => true],
                    ['key' => 'service_account_key', 'label' => 'Clé du compte de service (JSON)', 'type' => 'textarea', 'secret' => true, 'required' => false],
                ],
            ],
        ];
    }
}
