<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * #9323 — activation DIRECTE d'un espace d'essai, sans e-mail et sans worker.
 *
 * ## Pourquoi cette Action existe
 *
 * Le repli #9305 (`provisionSandbox`) ne dépend plus du mailer, mais il dépend
 * encore de la **file** : il fait `ProvisionDemoTenantJob::dispatch(...)`. Sur
 * un environnement où aucun worker ne consomme la file, la ligne
 * `trial_provisionings` reste `pending` indéfiniment — le prospect détient un
 * `provisioning_token` mais aucun espace. Un repli qui ne s'exécute jamais
 * n'est pas un repli.
 *
 * Cette Action exécute le provisioning **dans la requête HTTP** : elle appelle
 * `ProvisionGuidedTrial` (le même code que le job, donc aucune divergence de
 * comportement), marque la ligne `ready`, et applique le mot de passe choisi à
 * l'inscription. Le responsable peut se connecter immédiatement.
 *
 * ## Garanties
 *
 * 1. **Aucun faux succès.** Toute exception remonte à l'appelant : le
 *    contrôleur répond 503 explicite plutôt que d'annoncer un espace
 *    inexistant (leçon de #9308).
 * 2. **Idempotence.** `ProvisionGuidedTrial` réutilise un tenant sandbox
 *    existant pour le même e-mail ; un double POST ne crée pas deux espaces.
 * 3. **Mot de passe optionnel.** Sans mot de passe, l'espace est créé et un
 *    `activation_url` est exposé : le prospect choisit son mot de passe via
 *    `POST /trial/set-password` avec le token qu'il détient déjà.
 * 4. **Aucune PII en clair dans les logs** (BOS-002 / #8144) : seul
 *    `company_id` est journalisé.
 */
final class ActivateTrialDirectly
{
    public function __construct(
        private readonly ProvisionGuidedTrial $provisioner,
        private readonly ConnectionInterface $db,
        private readonly Hasher $hasher,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param  list<string>  $solutions
     * @param  list<string>  $modules
     * @return array{
     *     company: Company,
     *     manager: Employee,
     *     login_url: string,
     *     password_set: bool,
     *     activation_url: string
     * }
     */
    public function execute(
        string $email,
        string $companyName,
        string $country,
        string $provisioningToken,
        array $solutions = [],
        ?string $companyType = null,
        array $modules = [],
        ?string $locale = null,
        ?string $marketType = null,
        ?string $tradeProfile = null,
        ?string $password = null,
    ): array {
        // Le MÊME provisioning que le job : une seule implémentation métier,
        // donc aucun risque de divergence entre le chemin file et le chemin
        // synchrone.
        /** @var array{company: Company, manager: Employee} $result */
        $result = $this->provisioner->execute(
            $email,
            $companyName,
            $country,
            $solutions,
            $companyType,
            $modules,
            $locale,
            $marketType,
            $tradeProfile,
        );

        $company = $result['company'];
        $manager = $result['manager'];

        $passwordSet = false;

        if ($password !== null && $password !== '') {
            $manager->forceFill(['password_hash' => $this->hasher->make($password)])->save();
            $passwordSet = true;
        }

        $loginUrl = '/auth/login';

        // La ligne de suivi devient `ready` : `GET /trial/status` expose alors
        // `login_url`, et `POST /trial/set-password` accepte le token tant que
        // `password_set_at` est vide.
        $this->db->table('trial_provisionings')
            ->where('provisioning_token', $provisioningToken)
            ->update([
                'status' => 'ready',
                'company_id' => $company->id,
                'login_url' => $loginUrl,
                // #7218 : ne jamais affirmer un accès envoyé — aucun e-mail
                // n'est parti sur ce chemin.
                'access_sent_at' => null,
                'provisioned_at' => now(),
                'password_set_at' => $passwordSet ? now() : null,
                'updated_at' => now(),
            ]);

        $this->logger->info('trial.direct_activation_success', [
            'company_id' => $company->id,
            'password_set' => $passwordSet,
        ]);

        return [
            'company' => $company,
            'manager' => $manager,
            'login_url' => $loginUrl,
            'password_set' => $passwordSet,
            'activation_url' => $this->activationUrl($provisioningToken),
        ];
    }

    /**
     * Écran de définition du mot de passe, consommé par
     * `POST /trial/set-password` (même token). `/auth/activate` est réservé au
     * flux d'invitation collaborateur — l'utiliser ici enverrait le manager
     * sur un lien invalide.
     */
    private function activationUrl(string $token): string
    {
        $frontend = config('app.frontend_url');

        return rtrim(is_string($frontend) ? $frontend : '', '/')
            .'/auth/set-password?token='.$token;
    }
}
