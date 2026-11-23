<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Infrastructure\Services;

use App\Modules\Attendance\Domain\Models\CalendarConnection;
use App\Modules\Attendance\Domain\Models\ZktecoDevice;
use App\Modules\Billing\Domain\Models\PaymentGatewaySetting;
use App\Modules\Billing\Domain\Models\TenantPaymentProfile;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use App\Modules\Paiements\Domain\Models\PaymentProviderInstance;
use App\Modules\Recruitment\Domain\Models\JobSource;
use App\Modules\SearchIntelligence\Domain\Models\Integration as SearchIntelligenceIntegration;
use Throwable;

/**
 * #9838 (BC-14 INTEGRATION) — index des intégrations DÉJÀ existantes.
 *
 * L'onglet « Connecteurs » doit « regrouper tout ce qui existe déjà comme
 * intégrations extérieures ». Il le fait en LECTURE, pas en duplication :
 *
 *   - chaque intégration existante garde son écran natif et sa propre
 *     configuration — c'est lui qui reste la source de vérité ;
 *   - l'onglet en donne une vue d'ensemble avec un état RÉEL (nombre
 *     d'éléments configurés) et un lien vers l'écran natif.
 *
 * Dupliquer la saisie aurait créé deux vérités divergentes : un webhook modifié
 * dans son écran natif n'aurait pas été reflété ici, et l'utilisateur n'aurait
 * plus su laquelle fait foi. C'est exactement le doublon que la mission demande
 * d'éviter.
 *
 * Robustesse : chaque source est lue dans son propre `try/catch`. Une table
 * absente (module non activé pour ce tenant, migration non jouée) retire la
 * ligne de l'index au lieu de faire échouer tout l'écran — un onglet
 * d'inventaire qui plante parce qu'un module optionnel n'est pas installé
 * serait inutilisable.
 */
class ConnectorIntegrationIndex
{
    /**
     * Intégrations de la PLATEFORME (Superadmin).
     *
     * @return list<array<string, mixed>>
     */
    public function forPlatform(): array
    {
        return array_values(array_filter([
            $this->source(
                key: 'payment_gateways',
                label: 'Passerelles de paiement (abonnements)',
                category: 'payment',
                href: '/billing/gateways',
                count: fn (): int => PaymentGatewaySetting::query()->where('is_active', true)->count(),
                detail: 'Stripe et Chargily : encaissement des abonnements de la plateforme.',
            ),
            $this->source(
                key: 'payment_provider_instances',
                label: 'Instances de fournisseur de paiement',
                category: 'payment',
                href: '/payments/provider-instances',
                count: fn (): int => PaymentProviderInstance::query()->count(),
                detail: 'Socle de paiement interne (BC-38) : bascule d\'un marché d\'une instance à une autre.',
            ),
            $this->source(
                key: 'job_sources',
                label: 'Agrégateurs d\'offres d\'emploi',
                category: 'marketplace',
                href: '/recruitment/sources',
                count: fn (): int => JobSource::query()->count(),
                detail: 'Connecteurs de diffusion d\'offres (BC-10) : publication vers les job boards.',
            ),
            $this->source(
                key: 'search_intelligence',
                label: 'Connecteurs SEO / SEA (GSI)',
                category: 'analytics',
                href: '/search-intel/integrations',
                count: fn (): int => SearchIntelligenceIntegration::query()->count(),
                detail: 'Search Console, GA4, Google Ads, Microsoft Ads.',
            ),
        ]));
    }

    /**
     * Intégrations d'un TENANT (espace client).
     *
     * Les modèles portent `BelongsToCompany` : les comptages sont donc
     * automatiquement bornés au tenant courant, sans filtre explicite à
     * maintenir ici.
     *
     * @return list<array<string, mixed>>
     */
    public function forTenant(): array
    {
        return array_values(array_filter([
            $this->source(
                key: 'payment_profiles',
                label: 'Encaissements',
                category: 'payment',
                href: '/settings/encaissements',
                count: fn (): int => TenantPaymentProfile::query()->count(),
                detail: 'Vos comptes d\'encaissement : Stripe, IBAN, mobile money, espèces.',
            ),
            $this->source(
                key: 'webhooks',
                label: 'Webhooks sortants',
                category: 'other',
                href: '/settings/webhooks',
                count: fn (): int => WebhookEndpoint::query()->count(),
                detail: 'Notifications envoyées vers vos systèmes quand un événement se produit.',
            ),
            $this->source(
                key: 'calendar',
                label: 'Synchronisation calendrier',
                category: 'other',
                href: '/settings/integrations',
                count: fn (): int => CalendarConnection::query()->where('is_active', true)->count(),
                detail: 'Congés et formations poussés vers Google Calendar / Outlook.',
            ),
            $this->source(
                key: 'zkteco_devices',
                label: 'Pointeuses ZKTeco',
                category: 'other',
                href: '/settings/integrations',
                count: fn (): int => ZktecoDevice::query()->count(),
                detail: 'Appareils biométriques de pointage connectés à la plateforme.',
            ),
        ]));
    }

    /**
     * Construit une ligne d'index, ou `null` si la source est indisponible.
     *
     * @param  callable(): int  $count
     * @return array<string, mixed>|null
     */
    private function source(
        string $key,
        string $label,
        string $category,
        string $href,
        callable $count,
        string $detail,
    ): ?array {
        try {
            $count = $count();
        } catch (Throwable) {
            // Table absente ou module non activé : la ligne disparaît de
            // l'index plutôt que de casser l'écran entier.
            return null;
        }

        return [
            'key' => $key,
            'label' => $label,
            'category' => $category,
            'href' => $href,
            'count' => $count,
            'configured' => $count > 0,
            'detail' => $detail,
        ];
    }
}
