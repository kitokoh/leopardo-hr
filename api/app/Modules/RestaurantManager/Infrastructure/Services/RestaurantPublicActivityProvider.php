<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use App\Shared\Contracts\Discovery\PublicActivityProvider;

/**
 * COULOIR 16 — M3 (#9105) · Activités publiques de la verticale restaurant.
 *
 * ## Ce que ce provider fait, et rien de plus
 *
 * Il relit UNE commande ou UNE réservation DANS le schéma de son tenant, et
 * vérifie qu'elle appartient bien au client authentifié. C'est la seule
 * implémentation de `PublicActivityProvider` livrée en M3 : la catégorie
 * moteur du couloir est `restaurants`, et un module sans contrat ne doit pas
 * apparaître dans l'onglet. Les autres verticales ajouteront la leur sans
 * toucher au couloir 16 — c'est tout l'intérêt du tag.
 *
 * ## Pourquoi la vérification d'appartenance est ici et pas dans l'index
 *
 * `public_activity_index` est une projection : elle peut être en retard, et
 * surtout elle ne doit jamais autoriser une lecture. La question « cette
 * commande est-elle à vous ? » se répond dans le schéma qui détient la
 * commande, avec `public_client_id` — colonne ajoutée par la migration
 * `2026_11_02_000400_9105`. Sans elle, connaître une référence suffirait à lire
 * une commande : c'est précisément ce qu'on refuse.
 *
 * ## Fail-closed
 *
 * Tenant introuvable, commande inexistante, commande d'un autre client,
 * `public_client_id` NULL (commande POS/kiosque) → `null`. L'appelant traduit
 * ce `null` en « activité introuvable », indistinguable d'une activité qui
 * n'existe pas.
 */
final class RestaurantPublicActivityProvider implements PublicActivityProvider
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function vertical(): string
    {
        return 'restaurant';
    }

    /**
     * @return list<string>
     */
    public function activityTypes(): array
    {
        return ['order', 'booking'];
    }

    public function findForClient(string $activityType, string $externalRef, int $userId): ?array
    {
        return match ($activityType) {
            'order' => $this->findOrder($externalRef, $userId),
            'booking' => $this->findReservation($externalRef, $userId),
            default => null,
        };
    }

    /**
     * @return array{reference: string, status: string, occurred_at: string, title: string}|null
     */
    private function findOrder(string $reference, int $userId): ?array
    {
        return $this->withinOwningTenant(
            $reference,
            $userId,
            RestaurantOrder::class,
            static fn (RestaurantOrder $order): array => [
                'reference' => (string) $order->reference,
                'status' => $order->status instanceof \BackedEnum
                    ? (string) $order->status->value
                    : (string) $order->status,
                'occurred_at' => (string) $order->created_at?->toIso8601String(),
                'title' => (string) __('publicApp.activityOrderTitle'),
            ],
        );
    }

    /**
     * @return array{reference: string, status: string, occurred_at: string, title: string}|null
     */
    private function findReservation(string $reference, int $userId): ?array
    {
        return $this->withinOwningTenant(
            $reference,
            $userId,
            RestaurantReservation::class,
            static fn (RestaurantReservation $reservation): array => [
                'reference' => (string) $reservation->reference,
                'status' => $reservation->status instanceof \BackedEnum
                    ? (string) $reservation->status->value
                    : (string) $reservation->status,
                'occurred_at' => (string) ($reservation->reserved_at ?? $reservation->created_at)?->toIso8601String(),
                'title' => (string) __('publicApp.activityBookingTitle'),
            ],
        );
    }

    /**
     * Ouvre le contexte du tenant propriétaire de la référence, puis relit la
     * ligne en exigeant `public_client_id = $userId`.
     *
     * La référence est unique PAR TENANT (`restaurant_orders_company_reference_unique`),
     * pas globalement : on résout donc d'abord le tenant, puis on lit dedans.
     * Une référence inconnue de tous les tenants renvoie `null` sans révéler
     * combien de tenants existent.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  callable(TModel): array{reference: string, status: string, occurred_at: string, title: string}  $present
     * @return array{reference: string, status: string, occurred_at: string, title: string}|null
     */
    private function withinOwningTenant(string $reference, int $userId, string $modelClass, callable $present): ?array
    {
        // `shared_tenants` porte les tables tenant ; la résolution se fait donc
        // hors contexte tenant, sur la seule référence + l'appartenance.
        $companyId = $modelClass::query()
            ->where('reference', $reference)
            ->where('public_client_id', $userId)
            ->value('company_id');

        if (! is_string($companyId) || $companyId === '') {
            return null;
        }

        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            return null;
        }

        return $this->tenants->withinTenant($company, function () use ($modelClass, $reference, $userId, $present): ?array {
            /** @var TModel|null $model */
            $model = $modelClass::query()
                ->where('reference', $reference)
                // Rejoué DANS le contexte tenant : la première requête a pu
                // s'exécuter sur un `search_path` différent.
                ->where('public_client_id', $userId)
                ->first();

            return $model === null ? null : $present($model);
        });
    }
}
