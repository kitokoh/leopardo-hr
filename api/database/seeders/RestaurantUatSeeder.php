<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Database\Seeders\Concerns\GuardsPilotSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * #9725 — Jeu de données de recette terrain RestaurantManager (R2–R11).
 *
 * Provisionne un tenant de recette DÉTERMINISTE et complet, sur lequel la
 * checklist opérateur (`docs/architecture/restaurant/UAT_KIT_R2_R11.md`) peut
 * être exécutée sans préparation manuelle :
 *
 *  - 1 succursale publique (slug `chez-leo-recette`) + 1 succursale privée ;
 *  - 3 zones de salle, 8 tables (capacités variées, une table non fusionnable) ;
 *  - 1 catégorie, 1 unité, 1 taux de taxe, 6 produits dont 1 indisponible ;
 *  - 4 ingrédients + niveaux de stock (dont 1 sous le seuil d'alerte) ;
 *  - 1 menu publié avec 3 plats ;
 *  - 2 zones de livraison (frais différents) + 1 livreur ;
 *  - 1 programme de fidélité + 2 clients ;
 *  - 1 promotion active ;
 *  - 6 commandes couvrant les stades du parcours (open, in_preparation,
 *    ready, served, paid, closed) + 1 commande bloquée en cuisine ;
 *  - 3 avis : 1 publié, 1 en attente, 1 rejeté (file de modération non vide) ;
 *  - 2 réservations (1 confirmée, 1 en attente) ;
 *  - 1 bon d'achat + 1 réception (parcours stock) ;
 *  - 4 employés : principal, gérant de succursale, serveur, livreur.
 *
 * Zéro donnée réelle, zéro secret : le mot de passe de démonstration est
 * documenté (`recette123`) et n'est jamais un secret de production.
 *
 * Réentrant : si le tenant existe déjà, le seed est ignoré. Nettoyable :
 * `php artisan pilot:seed resto --clean`.
 *
 * Usage : php artisan pilot:seed resto
 *         php artisan pilot:seed resto --clean
 */
class RestaurantUatSeeder extends Seeder
{
    use GuardsPilotSeeding;

    public const SLUG = 'resto-pilot-001';

    public const PUBLIC_SLUG = 'chez-leo-recette';

    /** Mot de passe de démonstration documenté (jamais un secret réel). */
    public const DEMO_PASSWORD = 'recette123';

    private const SHARED_SCHEMA = 'shared_tenants';

    /**
     * Domaine des comptes de démonstration.
     *
     * `example.com` est le domaine réservé par la RFC 2606 : il ne peut
     * appartenir à personne, donc aucun courriel réel n'est publié — et il
     * correspond exactement à ce qu'annonce le kit de recette
     * (docs/ops/RECETTE_UAT_RESTAURANTMANAGER_KIT.md). Un domaine inventé
     * ferait échouer la connexion de l'opérateur qui suit la doc.
     */
    private const DOMAIN = 'example.com';

    public function run(): void
    {
        $this->assertPilotEnvironmentAllowed('resto');

        $existing = Company::query()->where('slug', self::SLUG)->first();

        if ($existing instanceof Company) {
            $this->command?->warn('Tenant de recette '.self::SLUG.' déjà présent — skip (réentrant).');

            return;
        }

        if (! Schema::hasTable('restaurant_branches') && ! Schema::hasTable(self::SHARED_SCHEMA.'.restaurant_branches')) {
            $this->command?->warn('Tables RestaurantManager absentes — seed de recette ignoré.');

            return;
        }

        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => 'Restaurant Recette 001',
            'slug' => self::SLUG,
            'schema_name' => self::SHARED_SCHEMA,
            'tenancy_type' => 'shared',
            'country' => 'CM',
            'currency' => 'XAF',
            'status' => 'active',
            'features' => ['rh' => true, 'restaurantmanager' => true],
        ]);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            $this->seedRestaurantData($company);
        });

        $this->command?->info('Tenant de recette RestaurantManager créé : '.self::SLUG);
        $this->command?->info('  Connexion : principal@'.self::DOMAIN.' / '.self::DEMO_PASSWORD);
        $this->command?->info('  Menu public : /restaurants/'.self::PUBLIC_SLUG);
    }

    private function seedRestaurantData(Company $company): void
    {
        $companyId = (string) $company->id;
        $now = now();
        $hash = Hash::make(self::DEMO_PASSWORD);

        // ── Équipe ─────────────────────────────────────────────────────────
        $principalId = $this->employee($companyId, $hash, 'Amina', 'Directrice', 'principal@', 'manager', 'principal', $now);
        $managerId = $this->employee($companyId, $hash, 'Karim', 'Gerant', 'gerant@', 'manager', 'rh', $now);
        $serverId = $this->employee($companyId, $hash, 'Awa', 'Serveuse', 'serveur@', 'employee', null, $now);
        $riderEmployeeId = $this->employee($companyId, $hash, 'Moussa', 'Livreur', 'livreur@', 'employee', null, $now);

        // ── Succursales ────────────────────────────────────────────────────
        $branchId = $this->insert('restaurant_branches', [
            'company_id' => $companyId,
            'code' => 'BR-CENTRE',
            'name' => 'Chez Leo — Centre',
            'address' => '12 avenue de la Recette',
            'city' => 'Douala',
            'phone' => '+237600000001',
            'timezone' => 'Africa/Douala',
            'currency' => 'XAF',
            'status' => 'active',
            'is_public' => true,
            'public_slug' => self::PUBLIC_SLUG,
            'establishment_type' => 'restaurant',
            'cuisine_types' => json_encode(['africaine', 'grillades']),
            'public_description' => 'Restaurant de recette — données synthétiques.',
            'latitude' => 4.0511,
            'longitude' => 9.7679,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $privateBranchId = $this->insert('restaurant_branches', [
            'company_id' => $companyId,
            'code' => 'BR-NORD',
            'name' => 'Chez Leo — Nord',
            'address' => '3 rue du Nord',
            'city' => 'Douala',
            'phone' => '+237600000002',
            'timezone' => 'Africa/Douala',
            'currency' => 'XAF',
            'status' => 'active',
            'is_public' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── Référentiel ────────────────────────────────────────────────────
        $unitId = $this->insert('restaurant_units', [
            'company_id' => $companyId,
            'code' => 'kg',
            'label' => 'Kilogramme',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $taxId = $this->insert('restaurant_tax_rates', [
            'company_id' => $companyId,
            'code' => 'TVA-19',
            'label' => 'TVA 19,25 %',
            'rate_bps' => 1925,
            'is_default' => true,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $categoryId = $this->insert('restaurant_categories', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Plats principaux',
            'color' => '#0f172a',
            'sort_order' => 1,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── Zones et tables ────────────────────────────────────────────────
        /** @var array<string, int> $zoneIds */
        $zoneIds = [];
        foreach ([['Salle', '#0ea5e9', 1], ['Terrasse', '#22c55e', 2], ['Étage', '#f59e0b', 3]] as [$name, $color, $order]) {
            $zoneIds[$name] = $this->insert('restaurant_zones', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => $name,
                'color' => $color,
                'sort_order' => $order,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        /** @var array<string, int> $tableIds */
        $tableIds = [];
        $tableSpecs = [
            ['T1', 'Salle', 2, true],
            ['T2', 'Salle', 4, true],
            ['T3', 'Salle', 4, true],
            ['T4', 'Terrasse', 6, true],
            ['T5', 'Terrasse', 2, false],
            ['T6', 'Étage', 8, true],
            ['T7', 'Étage', 4, true],
            ['T8', 'Étage', 10, true],
        ];
        foreach ($tableSpecs as [$label, $zone, $capacity, $mergeable]) {
            $tableIds[$label] = $this->insert('restaurant_tables', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'zone_id' => $zoneIds[$zone],
                'label' => $label,
                'capacity' => $capacity,
                'min_covers' => 1,
                'is_mergeable' => $mergeable,
                'status' => 'available',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ── Ingrédients et stock ───────────────────────────────────────────
        $ingredientSpecs = [
            ['ING-POULET', 'Poulet fermier', 250000, 40.0, 10.0],
            ['ING-RIZ', 'Riz parfumé', 90000, 120.0, 20.0],
            ['ING-HUILE', 'Huile de palme', 150000, 8.0, 15.0], // sous le seuil → alerte
            ['ING-EPICE', 'Épices maison', 400000, 5.0, 2.0],
        ];
        /** @var array<string, int> $ingredientIds */
        $ingredientIds = [];
        foreach ($ingredientSpecs as [$code, $name, $cost, $qty, $threshold]) {
            $ingredientIds[$code] = $this->insert('restaurant_ingredients', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'code' => $code,
                'name' => $name,
                'unit_code' => 'kg',
                'avg_cost_minor' => $cost,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->insert('restaurant_stock_levels', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'ingredient_id' => $ingredientIds[$code],
                'quantity' => $qty,
                'avg_cost_minor' => $cost,
                'reorder_level' => $threshold * 2,
                'alert_threshold' => $threshold,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ── Produits ───────────────────────────────────────────────────────
        $productSpecs = [
            ['PLAT-POULET', 'Poulet DG', 750000, 320000, true],
            ['PLAT-RIZ', 'Riz sauté au poulet', 500000, 180000, true],
            ['PLAT-BROCH', 'Brochettes de bœuf', 600000, 250000, true],
            ['PLAT-POISSON', 'Poisson braisé', 850000, 400000, true],
            ['PLAT-ENTREE', 'Salade maison', 300000, 90000, true],
            ['PLAT-RUPTURE', 'Plat en rupture (recette)', 450000, 150000, false],
        ];
        /** @var array<string, int> $productIds */
        $productIds = [];
        foreach ($productSpecs as [$code, $name, $price, $cost, $available]) {
            $productIds[$code] = $this->insert('restaurant_products', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'category_id' => $categoryId,
                'code' => $code,
                'name' => $name,
                'price_minor' => $price,
                'currency' => 'XAF',
                'cost_minor' => $cost,
                'tax_rate_id' => $taxId,
                'is_available' => $available,
                'is_published_online' => $available,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ── Menu publié ────────────────────────────────────────────────────
        $menuId = $this->insert('restaurant_menus', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'code' => 'MENU-MIDI',
            'name' => 'Menu du midi',
            'price_minor' => 1200000,
            'currency' => 'XAF',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $position = 1;
        foreach (['PLAT-POULET', 'PLAT-RIZ', 'PLAT-ENTREE'] as $code) {
            $this->insert('restaurant_menu_items', [
                'company_id' => $companyId,
                'menu_id' => $menuId,
                'product_id' => $productIds[$code],
                'position' => $position++,
                'is_optional' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ── Livraison ──────────────────────────────────────────────────────
        $this->insert('restaurant_delivery_zones', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Centre-ville',
            'fee_minor' => 100000,
            'min_order_minor' => 500000,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insert('restaurant_delivery_zones', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Périphérie',
            'fee_minor' => 250000,
            'min_order_minor' => 800000,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insert('restaurant_delivery_riders', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'employee_id' => $riderEmployeeId,
            'name' => 'Moussa Livreur',
            'phone' => '+237600000004',
            'vehicle_code' => 'MOTO-01',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── Fidélité et promotion ──────────────────────────────────────────
        $this->insert('restaurant_loyalty_programs', [
            'company_id' => $companyId,
            'points_per_amount_minor' => 1000,
            'redeem_rate_minor' => 100,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        // `customer_contact_id` est un identifiant opaque (pas de FK) : on
        // utilise des valeurs de recette stables et non ambiguës.
        foreach ([[900001, 'Client Fidèle 1', 120], [900002, 'Client Fidèle 2', 40]] as [$contactId, $name, $points]) {
            $this->insert('restaurant_loyalty_customers', [
                'company_id' => $companyId,
                'customer_contact_id' => $contactId,
                'points' => $points,
                'tier_code' => $points >= 100 ? 'gold' : 'silver',
                'opted_in_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            unset($name);
        }
        $this->insert('restaurant_promotions', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'code' => 'RECETTE10',
            'title' => 'Remise recette 10 %',
            'discount_type' => 'percent',
            'value_minor' => 1000,
            'min_order_minor' => 500000,
            'starts_at' => $now->copy()->subDay(),
            'ends_at' => $now->copy()->addDays(30),
            'max_uses' => 100,
            'used_count' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── Commandes couvrant tous les stades du parcours ─────────────────
        $orderSpecs = [
            ['RST-UAT-OPEN', 'open', 'dine_in', 'T1', 0],
            ['RST-UAT-PREP', 'in_preparation', 'dine_in', 'T2', 0],
            ['RST-UAT-READY', 'ready', 'dine_in', 'T3', 0],
            ['RST-UAT-SERVED', 'served', 'dine_in', 'T4', 0],
            ['RST-UAT-PAID', 'paid', 'takeaway', null, 0],
            ['RST-UAT-CLOSED', 'closed', 'delivery', null, 0],
            // Commande bloquée en cuisine depuis 2 h (métrique observabilité).
            ['RST-UAT-STUCK', 'in_preparation', 'dine_in', 'T5', 120],
        ];

        /** @var array<string, int> $orderIds */
        $orderIds = [];
        foreach ($orderSpecs as [$reference, $status, $type, $table, $minutesAgo]) {
            $createdAt = $now->copy()->subMinutes($minutesAgo);

            $orderId = $this->insert('restaurant_orders', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'reference' => $reference,
                'order_type' => $type,
                'table_id' => $table !== null ? $tableIds[$table] : null,
                'zone_id' => $table !== null ? $zoneIds['Salle'] : null,
                'covers' => $table !== null ? 2 : null,
                'status' => $status,
                'subtotal_minor' => 750000,
                'tax_minor' => 144375,
                'discount_minor' => 0,
                'total_minor' => 894375,
                'currency' => 'XAF',
                'source' => $type === 'delivery' ? 'online' : 'pos',
                'version' => 1,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $orderIds[$reference] = $orderId;

            $this->insert('restaurant_order_items', [
                'company_id' => $companyId,
                'order_id' => $orderId,
                'product_id' => $productIds['PLAT-POULET'],
                'quantity' => 1,
                'unit_price_minor' => 750000,
                'line_total_minor' => 750000,
                'tax_rate_id' => $taxId,
                'tax_minor' => 144375,
                'status' => 'served',
                'line_index' => 1,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        // ── Avis : file de modération non vide ─────────────────────────────
        $reviewSpecs = [
            ['RST-UAT-SERVED', 5, 'Service impeccable, poulet DG excellent.', 'Awa', 'published'],
            ['RST-UAT-PAID', 3, 'Correct mais un peu d\'attente.', 'Bertrand', 'pending'],
            ['RST-UAT-CLOSED', 1, 'Commande froide à la livraison.', 'Chantal', 'rejected'],
        ];
        foreach ($reviewSpecs as [$orderRef, $rating, $comment, $author, $status]) {
            $this->insert('restaurant_reviews', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'order_reference' => $orderRef,
                'rating' => $rating,
                'comment' => $comment,
                'author_name' => $author,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ── Réservations ───────────────────────────────────────────────────
        $this->insert('restaurant_reservations', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'reference' => 'RSV-UAT-001',
            'contact_name' => 'Famille Recette',
            'contact_phone' => '+237600000010',
            'reserved_at' => $now->copy()->addHours(3),
            'covers' => 4,
            'table_id' => $tableIds['T2'],
            'zone_id' => $zoneIds['Salle'],
            'status' => 'confirmed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insert('restaurant_reservations', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'reference' => 'RSV-UAT-002',
            'contact_name' => 'Groupe Recette',
            'contact_phone' => '+237600000011',
            'reserved_at' => $now->copy()->addDay(),
            'covers' => 8,
            'table_id' => $tableIds['T6'],
            'zone_id' => $zoneIds['Étage'],
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── Parcours stock : fournisseur, bon d'achat, réception ───────────
        $supplierId = $this->insert('restaurant_suppliers', [
            'company_id' => $companyId,
            'name' => 'Fournisseur Recette SARL',
            'contact_phone' => '+237600000020',
            'email' => 'fournisseur@'.self::DOMAIN,
            'address' => 'Zone industrielle, Douala',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $poId = $this->insert('restaurant_purchase_orders', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'reference' => 'PO-UAT-001',
            'status' => 'received',
            'expected_at' => $now->copy()->subDay(),
            'received_at' => $now,
            'total_minor' => 500000,
            'currency' => 'XAF',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insert('restaurant_purchase_order_items', [
            'company_id' => $companyId,
            'purchase_order_id' => $poId,
            'ingredient_id' => $ingredientIds['ING-POULET'],
            'quantity' => 2,
            'unit_price_minor' => 250000,
            'line_total_minor' => 500000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insert('restaurant_receivings', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'purchase_order_id' => $poId,
            'supplier_id' => $supplierId,
            'reference' => 'RCV-UAT-001',
            'received_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── Caisse : une session ouverte (parcours POS) ────────────────────
        $this->insert('restaurant_pos_sessions', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'opened_at' => $now->copy()->subHours(2),
            'opened_by_user_id' => $serverId,
            'opening_cash_minor' => 5000000,
            'status' => 'open',
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── Outbox : un événement en échec (métrique observabilité) ────────
        $this->insert('restaurant_outbox_events', [
            'company_id' => $companyId,
            'event_type' => 'order.created',
            'payload_redacted' => json_encode(['order_id' => $orderIds['RST-UAT-OPEN']]),
            'status' => 'failed',
            'attempts' => 2,
            'available_at' => $now,
            'last_error' => 'transient: recette',
            'idempotency_key' => 'uat-outbox-001',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Le gérant de succursale reçoit le niveau `manage` sur la succursale
        // publique : la recette R6 (modération) doit être exécutable avec un
        // compte non-principal.
        if (Schema::hasTable('employee_resource_assignments')) {
            $this->insert('employee_resource_assignments', [
                'company_id' => $companyId,
                'employee_id' => $managerId,
                'resource_type' => 'restaurant_branch',
                'resource_id' => $branchId,
                'access_level' => 'manage',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Le serveur reçoit `operate` sur la succursale publique.
        if (Schema::hasTable('employee_resource_assignments')) {
            $this->insert('employee_resource_assignments', [
                'company_id' => $companyId,
                'employee_id' => $serverId,
                'resource_type' => 'restaurant_branch',
                'resource_id' => $branchId,
                'access_level' => 'operate',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // La succursale privée reste sans assignation : elle sert à vérifier
        // le cloisonnement (R9) — un gérant de la publique ne la voit pas.
        unset($privateBranchId, $principalId, $unitId);
    }

    private function employee(
        string $companyId,
        string $hash,
        string $firstName,
        string $lastName,
        string $emailPrefix,
        string $role,
        ?string $managerRole,
        \Illuminate\Support\Carbon $now,
    ): int {
        return $this->insert('employees', [
            'company_id' => $companyId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $emailPrefix.self::DOMAIN,
            'password_hash' => $hash,
            'role' => $role,
            'manager_role' => $managerRole,
            'contract_type' => 'CDI',
            'contract_start' => $now->copy()->subYear()->toDateString(),
            'salary_base' => 0,
            'salary_type' => 'fixed',
            'payment_method' => 'bank_transfer',
            'leave_balance' => 0,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insert(string $table, array $values): int
    {
        return (int) DB::table($table)->insertGetId($values);
    }
}
