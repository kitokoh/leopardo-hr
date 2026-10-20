<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Platform\Domain\Models\PublicContactRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * #8760 — Une demande de contact a été reçue via la passerelle publique.
 *
 * Contrat cross-BC (même mécanisme que `CatalogInquiryReceived` #6884) :
 * Platform ne touche JAMAIS aux tables d'un autre bounded context — les
 * listeners (CRM BC-11, notification BC-13) consomment cet événement et
 * écrivent dans les leurs.
 *
 * ⚠️ Cet événement porte un message d'un HUMAIN vers un professionnel. Il ne
 * doit jamais alimenter la mémoire de Léa (assistante IA) : aucun listener IA
 * n'est enregistré sur cet événement, et le modèle `PublicContactRequest` n'a
 * aucun lien avec `ai_conversations`.
 *
 * ⚠️ L'événement est émis APRÈS le commit (jamais dans la transaction) : un
 * rollback ne doit pas laisser un lead orphelin ni une notification pour une
 * demande inexistante.
 *
 * Référentiel : docs/architecture/event-catalogue.yaml (`public.contact_request_received`).
 */
class PublicContactRequestReceived
{
    use Dispatchable;

    public function __construct(
        public readonly PublicContactRequest $contactRequest,
    ) {}
}
