<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Onboarding\Infrastructure\Services\CompanyOnboardingCompletionWriter;

/**
 * Use Case : persiste l'acquittement de l'écran de bienvenue de première
 * connexion (#7604, tranche du critère 2 de #7490).
 *
 * Pourquoi un drapeau serveur : l'écran de bienvenue doit s'afficher **une
 * seule fois**. Le poser dans le `localStorage` du navigateur le ferait
 * réapparaître sur un autre appareil (ou après nettoyage du stockage) — c'est
 * exactement le défaut qui avait été corrigé pour la fin d'onboarding
 * (#7262 → `SyncOnboardingCompletion`). La source de vérité est donc
 * `public.companies.metadata.welcome_seen_at`, déjà exposé au portail par
 * `/auth/me` (`EmployeeResource`, #R8) : l'écran sait s'afficher sans appel
 * supplémentaire, et l'acquittement est la seule écriture.
 *
 * Idempotent : la date d'acquittement n'est **jamais** réécrite ; un second
 * appel (rechargement, double rendu React, autre onglet) renvoie la date
 * d'origine et `already_acknowledged: true`.
 *
 * L'écriture est déléguée à `CompanyOnboardingCompletionWriter`
 * (Infrastructure) : la couche Application n'importe pas de façade Laravel
 * (garde `dev-hub/tools/check-layer-purity.sh`, #6568) et la requête est
 * qualifiée `public.companies` — le `search_path` du tenant ne doit pas
 * détourner l'écriture vers un schéma de tenant.
 */
final class AcknowledgeWelcomeScreen
{
    public function __construct(
        private readonly CompanyOnboardingCompletionWriter $writer,
    ) {}

    /**
     * @return array{seen_at: string, already_acknowledged: bool}
     */
    public function execute(Company $company): array
    {
        // Lecture FRAÎCHE : le modèle résolu par le middleware `tenant`
        // (`currentCompany()`) peut être une instance mise en cache dans le
        // conteneur, donc ANTÉRIEURE à un premier acquittement (constaté : le
        // second appel d'une même instance d'application relisait l'ancien
        // `metadata` et réécrivait la date). L'idempotence doit reposer sur
        // l'état PERSISTÉ, pas sur l'instance reçue.
        $fresh = Company::query()->find($company->id);
        $source = $fresh ?? $company;

        $metadata = $source->metadata ?? [];
        $existing = $metadata['welcome_seen_at'] ?? null;

        // Idempotence : une date déjà posée est la vérité, on n'y touche pas.
        if (is_string($existing) && $existing !== '') {
            return ['seen_at' => $existing, 'already_acknowledged' => true];
        }

        $seenAt = now()->toIso8601String();
        $metadata['welcome_seen_at'] = $seenAt;

        // Écriture PARTIELLE assumée : les autres clés de `metadata`
        // (onboarding_completed, branding, payroll, …) sont recopiées telles
        // quelles — test `WelcomeScreenAckTest` (« aucune clé perdue »).
        $this->writer->persist((string) $source->id, $metadata);

        return ['seen_at' => $seenAt, 'already_acknowledged' => false];
    }
}
