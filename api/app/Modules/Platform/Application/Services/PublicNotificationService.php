<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Services;

use App\Core\Auth\Domain\Models\User;
use App\Modules\Platform\Domain\Models\PublicNotification;
use App\Shared\Contracts\Notification\PublicClientNotifier;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * COULOIR 16 — M3-bis (#9142) · Notifications IN-APP du client public.
 *
 * ## Ce que ce service est, et ce qu'il n'est pas
 *
 * C'est la **première habitude quotidienne** du couloir : une raison de
 * revenir. Il ne crée aucun Bounded Context et ne duplique rien — il écrit
 * dans `public_notifications`, la boîte du client, exactement comme
 * `PublicActivityService` écrit dans `public_favorites`.
 *
 * Il ne réimplémente **pas** les préférences, les heures calmes, les quotas ni
 * l'audit : ceux-ci appartiennent à `CommunicationService` et concernent les
 * employés d'un tenant. Un client public n'a ni tenant, ni préférence de
 * canal, ni quota mensuel — il a une langue et une boîte. Le push (FCM/APNs)
 * arrive en M2 avec `public_device_tokens` ; ici, tout est in-app.
 *
 * ## Best-effort, jamais bloquant
 *
 * Une notification manquée ne doit **jamais** faire échouer l'écriture qui l'a
 * déclenchée : un message envoyé reste envoyé même si la boîte du destinataire
 * est indisponible. Même arbitrage que `notifyTenantManagers()` (#8760), qui
 * documente déjà ce choix. L'échec est journalisé, pas propagé.
 *
 * ## Aucune donnée personnelle
 *
 * Le corps est rendu depuis le catalogue i18n avec des valeurs **non
 * personnelles** (nom commercial, référence de commande, statut). Jamais de
 * numéro de téléphone, jamais d'adresse, jamais de prix.
 */
final class PublicNotificationService implements PublicClientNotifier
{
    /** Nombre maximal de notifications renvoyées (borne de charge utile). */
    public const MAX_NOTIFICATIONS = 50;

    /**
     * Un commerçant a répondu sur un fil public.
     *
     * `$merchantName` est le nom COMMERCIAL du professionnel — une donnée
     * publique, pas une donnée personnelle d'employé. Le nom du manager qui a
     * répondu n'est jamais transmis : le client parle à un commerce, pas à une
     * personne.
     */
    public function notifyMerchantReply(
        User $client,
        string $merchantName,
        int $threadId,
        string $companyId,
    ): ?PublicNotification {
        return $this->write(
            client: $client,
            type: PublicNotification::TYPE_MERCHANT_REPLY,
            titleKey: 'notifications.public_merchant_reply_title',
            bodyKey: 'notifications.public_merchant_reply_body',
            replacements: ['merchant' => $merchantName],
            data: [
                'thread_id' => $threadId,
                'company_id' => $companyId,
            ],
            // Un fil ne produit qu'UNE notification de réponse tant que le
            // client n'a pas lu : rejouer l'événement ne spamme pas la boîte.
            dedupeKey: 'thread:'.$threadId.':reply',
        );
    }

    /**
     * Le statut d'une commande ou d'une réservation a changé.
     *
     * Implémentation du contrat partagé `PublicClientNotifier` : une verticale
     * (BC-25 RESTAURANT, …) appelle cette méthode sans jamais importer Platform
     * (garde d'isolation #5584). Elle ne reçoit qu'un `users.id`, jamais un
     * objet `User` — elle n'a pas à connaître le modèle d'identité.
     *
     * `$status` est le statut RELU côté tenant par le provider de la verticale
     * — jamais la projection de `public_activity_index`. Le libellé du statut
     * est traduit ici, côté plateforme, pour que le client le lise dans SA
     * langue et non dans celle du commerçant.
     *
     * Best-effort : renvoie `false` si le client n'existe pas ou si l'écriture
     * échoue, sans jamais lever — la transition d'état est la donnée métier.
     */
    public function notifyActivityStatus(
        int $clientId,
        string $merchantName,
        string $vertical,
        string $activityType,
        string $externalRef,
        string $status,
    ): bool {
        /** @var User|null $client */
        $client = User::query()->find($clientId);

        if (! $client instanceof User) {
            return false;
        }

        return $this->write(
            client: $client,
            type: PublicNotification::TYPE_ACTIVITY_STATUS,
            titleKey: 'notifications.public_activity_status_title',
            bodyKey: 'notifications.public_activity_status_body',
            replacements: [
                'merchant' => $merchantName,
                'reference' => $externalRef,
                'status' => $this->statusLabel($status),
            ],
            data: [
                'vertical' => $vertical,
                'activity_type' => $activityType,
                'external_ref' => $externalRef,
                'status' => $status,
            ],
            // Une transition de statut = une notification. Rejouer le même
            // événement (retry de queue) ne duplique pas la ligne.
            dedupeKey: 'activity:'.$vertical.':'.$externalRef.':'.$status,
        ) instanceof PublicNotification;
    }

    /**
     * Notifications d'un client, les plus récentes d'abord.
     *
     * Le filtre `user_id` est la SEULE autorisation : il n'existe aucun
     * paramètre d'entrée permettant de viser la boîte d'un autre client.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, type: string, title: string, body: string, data: array<string, mixed>, read: bool, created_at: string|null}>
     */
    public function forClient(User $client, int $limit = self::MAX_NOTIFICATIONS): \Illuminate\Support\Collection
    {
        $limit = max(1, min(self::MAX_NOTIFICATIONS, $limit));

        return PublicNotification::query()
            ->where('user_id', $client->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (PublicNotification $notification): array => [
                'id' => (int) $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'body' => $notification->body,
                'data' => $notification->data ?? [],
                'read' => $notification->isRead(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ])
            ->values();
    }

    /**
     * Nombre de notifications non lues — la pastille de l'onglet.
     */
    public function unreadCount(User $client): int
    {
        return PublicNotification::query()
            ->where('user_id', $client->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Marque une notification comme lue. Idempotent : relire une notification
     * déjà lue ne change pas sa date de première lecture (c'est cette date qui
     * mesure l'habitude).
     *
     * Retourne `false` si la notification n'appartient pas au client — le
     * contrôleur répond alors 404, jamais 403 (un 403 révélerait l'existence
     * d'une notification qui ne le concerne pas).
     */
    public function markRead(User $client, int $notificationId): bool
    {
        $notification = PublicNotification::query()
            ->where('user_id', $client->id)
            ->whereKey($notificationId)
            ->first();

        if (! $notification instanceof PublicNotification) {
            return false;
        }

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return true;
    }

    /**
     * Marque TOUTES les notifications du client comme lues.
     *
     * @return int nombre de lignes effectivement modifiées
     */
    public function markAllRead(User $client): int
    {
        return PublicNotification::query()
            ->where('user_id', $client->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Écriture commune, best-effort.
     *
     * @param  array<string, string>  $replacements
     * @param  array<string, mixed>  $data
     */
    private function write(
        User $client,
        string $type,
        string $titleKey,
        string $bodyKey,
        array $replacements,
        array $data,
        string $dedupeKey,
    ): ?PublicNotification {
        try {
            // `updateOrCreate` sur `(user_id, dedupe_key)` : rejouer un
            // événement met à jour la ligne existante au lieu d'en créer une
            // seconde. La contrainte unique de la table est le filet de
            // sécurité en cas de course entre deux workers.
            return PublicNotification::query()->updateOrCreate(
                [
                    'user_id' => $client->id,
                    'dedupe_key' => $dedupeKey,
                ],
                [
                    'type' => $type,
                    'title' => (string) __($titleKey, $replacements, $this->localeFor($client)),
                    'body' => (string) __($bodyKey, $replacements, $this->localeFor($client)),
                    'data' => $data,
                ],
            );
        } catch (Throwable $e) {
            // Best-effort : l'écriture métier qui a déclenché la notification
            // ne doit jamais échouer à cause d'elle.
            Log::warning('public_notification.write_failed', [
                'user_id' => $client->id,
                'type' => $type,
                'dedupe_key' => $dedupeKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Langue du client, avec repli sur la locale applicative.
     *
     * `users.locale` existe (#8736) ; une valeur absente ou inconnue ne doit
     * pas produire une notification vide.
     */
    private function localeFor(User $client): string
    {
        $locale = $client->locale ?? null;

        if (! is_string($locale) || $locale === '') {
            return (string) config('app.locale', 'fr');
        }

        return in_array($locale, ['fr', 'en', 'ar', 'tr'], true)
            ? $locale
            : (string) config('app.locale', 'fr');
    }

    /**
     * Libellé traduit d'un statut d'activité.
     *
     * Un statut inconnu (nouvelle valeur côté verticale) est renvoyé tel quel
     * plutôt que masqué : mieux vaut un mot technique qu'une notification
     * muette. Le catalogue couvre les statuts connus.
     */
    private function statusLabel(string $status): string
    {
        $key = 'notifications.public_status_'.$status;
        $translated = __($key);

        return $translated === $key ? $status : (string) $translated;
    }
}
