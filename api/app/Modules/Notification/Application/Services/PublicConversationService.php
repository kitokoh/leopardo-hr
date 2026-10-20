<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Notification\Application\DTOs\MessageCursor;
use App\Modules\Notification\Domain\Exceptions\ConversationThreadNotFoundException;
use App\Modules\Notification\Domain\Models\ConversationMessage;
use App\Modules\Notification\Domain\Models\ConversationThread;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Shared\Discovery\PublicProfileLocator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * #8760 [BOS-CHAT] — Messagerie client public ↔ professionnel.
 *
 * ⚠️ Ce service n'introduit AUCUN système de chat : il réutilise le fil
 * existant (`ConversationThread` / `ConversationMessage`, BC-13/Notification)
 * et n'ajoute que les trois éléments qu'un client public rend nécessaires :
 *
 *   1. **Un type de fil `public`** — le client est un `users.id` plateforme,
 *      pas un `employees.id` ; il n'a donc aucune ligne `conversation_participants`.
 *   2. **La résolution du professionnel côté serveur** — le client ne fournit
 *      qu'un `target_type` + `target_slug` (déjà visibles dans `/decouvrir`),
 *      exactement comme le contact public : il ne peut pas router un message
 *      vers un tenant arbitraire en changeant un identifiant.
 *   3. **Deux contrôles d'accès explicites** — `threadForClient()` et
 *      `threadForManager()` : un identifiant changé dans l'URL produit un 404,
 *      jamais une lecture.
 *
 * ⚠️ Séparation stricte avec Léa (IA) : ce service n'écrit rien dans `ai_*`
 * et n'appelle aucun service IA. Un message humain vers un professionnel
 * n'est jamais de la mémoire conversationnelle d'assistante.
 *
 * ⚠️ Pas de faux temps réel : le transport est le polling par curseur `seq`
 * déjà en place (#8747/#8749). Aucun WebSocket n'est ouvert ici — l'hébergement
 * actuel ne le garantit pas.
 */
final class PublicConversationService
{
    /** Longueur maximale d'un message client (miroir de la validation HTTP). */
    public const MAX_BODY_LENGTH = 2000;

    public function __construct(
        private readonly PublicProfileLocator $locator,
        private readonly TenantManager $tenants,
        private readonly CommunicationService $communicationService,
    ) {}

    /**
     * Ouvre (ou retrouve) le fil d'un client authentifié avec un professionnel.
     *
     * Idempotent par construction : `(company_id, public_client_id, public_target_slug)`
     * identifie UN fil. Un client qui revient sur la même fiche retrouve sa
     * conversation au lieu d'en créer une seconde — c'est ce qui rend l'historique
     * lisible côté client ET côté professionnel.
     *
     * @return array{thread: ConversationThread, created: bool}
     */
    public function startConversation(User $client, string $targetType, string $targetSlug, string $body): array
    {
        $target = $this->locator->locate($targetType, $targetSlug);

        // Fail-closed : profil inexistant, dépublié, suspendu ou verticale
        // inactive → même signal, indistinguable de l'extérieur (anti-énumération).
        if ($target === null) {
            throw new ConversationThreadNotFoundException(0);
        }

        $company = Company::query()->find($target['company_id']);

        if (! $company instanceof Company) {
            throw new ConversationThreadNotFoundException(0);
        }

        return $this->tenants->withinTenant($company, function () use ($client, $company, $targetType, $targetSlug, $body, $target): array {
            $existing = ConversationThread::query()
                ->where('company_id', $company->id)
                ->where('kind', ConversationThread::KIND_PUBLIC)
                ->where('public_client_id', $client->id)
                ->where('public_target_slug', $targetSlug)
                ->first();

            if ($existing instanceof ConversationThread) {
                // Le client relance la conversation : son message est ajouté au
                // fil existant, jamais perdu dans un second fil.
                $this->postFromClient($existing, $client, $body);

                return ['thread' => $existing->fresh(['messages']) ?? $existing, 'created' => false];
            }

            $thread = DB::transaction(function () use ($client, $company, $targetType, $targetSlug, $body, $target): ConversationThread {
                /** @var ConversationThread $thread */
                $thread = ConversationThread::query()->create([
                    'company_id' => $company->id,
                    // Aucun employé propriétaire : le fil appartient au tenant,
                    // pas à une personne. `employee_id` est nullable depuis la
                    // migration #8760.
                    'employee_id' => null,
                    'manager_id' => null,
                    'kind' => ConversationThread::KIND_PUBLIC,
                    'public_client_id' => $client->id,
                    'public_target_slug' => $targetSlug,
                    'created_by' => null,
                    'subject_type' => 'professional_space',
                    'subject_id' => null,
                    'title' => $this->titleFor($target['display_name'], $targetType),
                    'status' => 'open',
                ]);

                $this->postFromClient($thread, $client, $body);

                return $thread;
            });

            // Trace de ROUTAGE plateforme : quel schéma interroger pour lister
            // « mes conversations ». Aucun contenu de message n'y figure, et
            // elle n'autorise aucune lecture à elle seule.
            DB::table('public_conversation_index')->updateOrInsert(
                [
                    'user_id' => $client->id,
                    'company_id' => $company->id,
                    'target_slug' => $targetSlug,
                ],
                [
                    'thread_id' => $thread->id,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            return ['thread' => $thread->fresh(['messages']) ?? $thread, 'created' => true];
        });
    }

    /**
     * Fils d'un client public, tous professionnels confondus.
     *
     * Le filtre `public_client_id` est la SEULE autorisation : il n'y a pas de
     * paramètre d'entrée permettant de viser le fil d'un autre client.
     *
     * @return Collection<int, ConversationThread>
     */
    public function threadsForClient(User $client, int $perPage = 30): Collection
    {
        // Table de ROUTAGE plateforme (aucun contenu de message) : elle dit
        // uniquement dans QUEL schéma tenant chercher. Chaque lecture de fil
        // rejoue ensuite le contrôle d'accès dans son propre contexte.
        $rows = DB::table('public_conversation_index')
            ->where('user_id', $client->id)
            ->orderByDesc('updated_at')
            ->limit($perPage)
            ->get();

        $threads = new Collection;

        foreach ($rows as $row) {
            $companyId = $row->company_id ?? null;
            $threadId = $row->thread_id ?? null;

            if (! is_string($companyId) || $companyId === '' || ! is_numeric($threadId)) {
                continue;
            }

            $company = Company::query()->find($companyId);

            if (! $company instanceof Company) {
                continue;
            }

            /** @var ConversationThread|null $found */
            $found = $this->tenants->withinTenant($company, fn (): ?ConversationThread => ConversationThread::query()
                ->where('company_id', $company->id)
                ->whereKey((int) $threadId)
                ->where('kind', ConversationThread::KIND_PUBLIC)
                ->where('public_client_id', $client->id)
                ->with('messages')
                ->first());

            if ($found instanceof ConversationThread) {
                $threads->push($found);
            }
        }

        return $threads;
    }

    /**
     * Fil public d'un client, ou 404. Contrôle d'accès AUTORITAIRE.
     */
    public function threadForClient(User $client, string $companyId, int $threadId): ConversationThread
    {
        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            throw new ConversationThreadNotFoundException($threadId);
        }

        return $this->tenants->withinTenant($company, function () use ($client, $company, $threadId): ConversationThread {
            $thread = ConversationThread::query()
                ->where('company_id', $company->id)
                ->whereKey($threadId)
                ->with(['messages'])
                ->first();

            // 404 uniforme : un fil d'un AUTRE client, d'un autre tenant, ou
            // inexistant sont indistinguables. Aucune donnée ne fuit par le
            // code de statut.
            if (! $thread instanceof ConversationThread || ! $thread->belongsToPublicClient($client->id)) {
                throw new ConversationThreadNotFoundException($threadId);
            }

            return $thread;
        });
    }

    /**
     * Fil public côté professionnel, ou 404. Seuls les managers du tenant cible
     * y accèdent (cf. `ConversationThread::hasParticipant()`).
     */
    public function threadForManager(Employee $manager, int $threadId): ConversationThread
    {
        $thread = ConversationThread::query()
            ->where('company_id', $manager->company_id)
            ->whereKey($threadId)
            ->with(['messages'])
            ->first();

        if (! $thread instanceof ConversationThread || ! $thread->hasParticipant($manager)) {
            throw new ConversationThreadNotFoundException($threadId);
        }

        return $thread;
    }

    /**
     * Fils publics d'un tenant, pour la boîte de réception du professionnel.
     *
     * @return Collection<int, ConversationThread>
     */
    public function threadsForManager(Employee $manager, int $perPage = 30): Collection
    {
        if (! $manager->isManager()) {
            return new Collection;
        }

        return ConversationThread::query()
            ->where('company_id', $manager->company_id)
            ->where('kind', ConversationThread::KIND_PUBLIC)
            ->with(['messages'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit($perPage)
            ->get();
    }

    /**
     * Écrit un message du CLIENT dans son fil. Le client ne peut écrire que
     * dans un fil qui lui appartient — le contrôle est refait ici, pas délégué
     * au contrôleur.
     */
    public function postFromClient(ConversationThread $thread, User $client, string $body): ConversationMessage
    {
        if (! $thread->belongsToPublicClient($client->id)) {
            throw new ConversationThreadNotFoundException($thread->id);
        }

        $trimmed = $this->normalizeBody($body);

        $company = Company::query()->find($thread->company_id);
        $context = $company instanceof Company ? $company : null;

        $write = function () use ($thread, $client, $trimmed): ConversationMessage {
            [$message] = $this->appendMessage(
                thread: $thread,
                employeeAuthor: null,
                publicAuthorId: $client->id,
                body: $trimmed,
            );

            return $message;
        };

        $message = $context instanceof Company
            ? $this->tenants->withinTenant($context, $write)
            : $write();

        // Rafraîchit la récence du fil dans l'index de routage (tri « mes
        // conversations »). Best-effort : un échec ici ne perd pas le message.
        try {
            DB::table('public_conversation_index')
                ->where('user_id', $client->id)
                ->where('company_id', $thread->company_id)
                ->where('thread_id', $thread->id)
                ->update(['updated_at' => now()]);
        } catch (\Throwable) {
            // Volontairement silencieux : le message est déjà persisté.
        }

        $this->notifyTenantManagers($thread, $client);

        return $message;
    }

    /**
     * Réponse du PROFESSIONNEL dans un fil public. L'auteur est un employé du
     * tenant cible, identifié par son token Sanctum.
     */
    public function postFromManager(ConversationThread $thread, Employee $manager, string $body): ConversationMessage
    {
        if (! $thread->hasParticipant($manager)) {
            throw new ConversationThreadNotFoundException($thread->id);
        }

        $trimmed = $this->normalizeBody($body);

        [$message] = $this->appendMessage(
            thread: $thread,
            employeeAuthor: $manager,
            publicAuthorId: null,
            body: $trimmed,
        );

        return $message;
    }

    /**
     * Historique paginé par curseur `seq` — même contrat que #8747 : le client
     * public poll `after_seq` pour rattraper ce qui est arrivé pendant une
     * déconnexion, et `before_seq` pour remonter l'historique.
     *
     * @return array{messages: Collection<int, ConversationMessage>, has_more: bool, next_before_seq: int|null, last_seq: int}
     */
    public function messagesFor(ConversationThread $thread, MessageCursor $cursor): array
    {
        $query = ConversationMessage::query()
            ->where('conversation_thread_id', $thread->id)
            ->orderBy('seq');

        if ($cursor->isCatchUp()) {
            $messages = $query
                ->where('seq', '>', (int) $cursor->afterSeq)
                ->limit($cursor->perPage + 1)
                ->get();

            $hasMore = $messages->count() > $cursor->perPage;
            $messages = $messages->take($cursor->perPage)->values();

            return [
                'messages' => $messages,
                'has_more' => $hasMore,
                'next_before_seq' => null,
                'last_seq' => $thread->lastSeq(),
            ];
        }

        $descending = $query
            ->when($cursor->beforeSeq !== null, static fn ($q) => $q->where('seq', '<', (int) $cursor->beforeSeq))
            ->reorder()
            ->orderByDesc('seq')
            ->limit($cursor->perPage + 1)
            ->get();

        $hasMore = $descending->count() > $cursor->perPage;
        $page = $descending->take($cursor->perPage)->values();

        return [
            'messages' => $page->reverse()->values(),
            'has_more' => $hasMore,
            'next_before_seq' => $page->isNotEmpty() ? (int) $page->last()->seq : null,
            'last_seq' => $thread->lastSeq(),
        ];
    }

    /**
     * Marque le fil comme lu côté client (best-effort : un échec d'accusé de
     * lecture ne doit jamais faire échouer l'affichage de l'historique).
     */
    public function markReadByClient(ConversationThread $thread): void
    {
        $company = Company::query()->find($thread->company_id);

        if (! $company instanceof Company) {
            return;
        }

        $this->tenants->withinTenant($company, function () use ($thread): void {
            if ($thread->last_message_id !== null) {
                $thread->forceFill(['employee_last_read_at' => now()]);
                $thread->save();
            }
        });
    }

    /**
     * Écriture réelle, idempotente sur `seq` : le compteur atomique du fil
     * (`UPDATE ... RETURNING`) garantit un ordre total même sous concurrence.
     * Aucun `max() + 1`, aucune course.
     *
     * @return array{0: ConversationMessage, 1: bool}
     */
    private function appendMessage(
        ConversationThread $thread,
        ?Employee $employeeAuthor,
        ?int $publicAuthorId,
        string $body,
    ): array {
        return DB::transaction(function () use ($thread, $employeeAuthor, $publicAuthorId, $body): array {
            /** @var object{last_message_seq: int|string}|null $row */
            $row = DB::selectOne(
                'UPDATE conversation_threads SET last_message_seq = last_message_seq + 1 WHERE id = ? RETURNING last_message_seq',
                [$thread->id],
            );

            if ($row === null) {
                throw new ConversationThreadNotFoundException($thread->id);
            }

            $seq = (int) $row->last_message_seq;

            /** @var ConversationMessage $message */
            $message = ConversationMessage::query()->create([
                'company_id' => $thread->company_id,
                'conversation_thread_id' => $thread->id,
                'author_id' => $employeeAuthor?->id,
                'author_type' => $publicAuthorId !== null ? 'public_client' : 'employee',
                'public_author_id' => $publicAuthorId,
                'body' => $body,
                'seq' => $seq,
            ]);

            $thread->forceFill([
                'last_message_id' => $message->id,
                'last_message_seq' => $seq,
                'last_message_at' => $message->created_at,
            ]);

            if ($employeeAuthor !== null) {
                $thread->markReadFor($employeeAuthor);
            }

            $thread->save();

            return [$message, true];
        });
    }

    private function normalizeBody(string $body): string
    {
        $trimmed = trim($body);

        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'body' => ['Le message ne peut pas être vide.'],
            ]);
        }

        if (mb_strlen($trimmed) > self::MAX_BODY_LENGTH) {
            throw ValidationException::withMessages([
                'body' => [sprintf('Le message ne peut pas dépasser %d caractères.', self::MAX_BODY_LENGTH)],
            ]);
        }

        return $trimmed;
    }

    private function titleFor(string $displayName, string $targetType): string
    {
        $name = trim($displayName) !== '' ? trim($displayName) : $targetType;

        return mb_substr('Conversation avec '.$name, 0, 200);
    }

    /**
     * Notifie les managers du tenant qu'un client a écrit.
     *
     * Réutilise `CommunicationService` (préférences, heures calmes, quotas,
     * audit) : rien n'est ré-implémenté ici. L'envoi est best-effort — une
     * notification manquée ne doit pas faire échouer l'écriture du message,
     * qui est la donnée métier.
     */
    private function notifyTenantManagers(ConversationThread $thread, User $client): void
    {
        try {
            $managers = Employee::query()
                ->where('company_id', $thread->company_id)
                ->where('status', 'active')
                ->where('role', 'manager')
                ->limit(20)
                ->get();

            foreach ($managers as $manager) {
                $this->communicationService->notifyEmployee(
                    $manager,
                    'conversation_message_received',
                    [
                        'author' => trim(($client->first_name ?? '').' '.($client->last_name ?? '')) ?: 'Client',
                        'thread' => $thread->title,
                        'conversation_thread_id' => $thread->id,
                        'category' => 'communication',
                    ],
                    ['app'],
                );
            }
        } catch (\Throwable $e) {
            Log::warning('public-conversation: notification des managers impossible', [
                'conversation_thread_id' => $thread->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
