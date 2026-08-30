<?php

declare(strict_types=1);

namespace App\Core\Outbox\Domain\Contracts;

/**
 * MAT-008 (#5866) — Marqueur des consommateurs d'outbox nécessitant le
 * contexte tenant.
 *
 * Un consommateur qui accède à des données métier du tenant (tables du
 * schéma `shared_tenants`/tenant) implémente cette interface : le
 * dispatcher exécute alors son `handle()` dans `TenantManager::withinTenant`
 * (résolution `current_company` + `search_path`).
 *
 * Les consommateurs de plateforme (événements globaux, audit, webhooks
 * sortants) n'implémentent PAS cette interface : ils tournent au
 * search_path par défaut (`shared_tenants,public`) où vivent les tables
 * de plateforme et d'audit.
 */
interface TenantScopedOutboxConsumer extends OutboxConsumer {}
