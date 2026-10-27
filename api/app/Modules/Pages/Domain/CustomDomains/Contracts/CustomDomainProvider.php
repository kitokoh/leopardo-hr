<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\CustomDomains\Contracts;

/**
 * Adaptateur de fournisseur de domaines personnalisés (BC PAGES, #8726).
 *
 * ## Pourquoi une interface alors qu'un seul fournisseur est branché
 *
 * L'issue laisse explicitement ouvert le choix entre Vercel et Cloudflare for
 * SaaS. Ce choix est **produit/infra**, pas technique : il dépend du prix, du
 * SLA et de l'endroit où vit déjà le DNS de la plateforme. Le figer maintenant
 * dans le service métier obligerait à réécrire la vérification le jour de la
 * bascule.
 *
 * Le contrat est donc volontairement **pauvre** : on ne demande au fournisseur
 * ni stockage, ni état, ni événements — seulement trois opérations
 * idempotentes. Tout l'état vit chez nous (`page_custom_domains`), ce qui rend
 * une reprise après panne fournisseur possible sans resynchronisation.
 *
 * ## Idempotence exigée
 *
 * `provision` et `deprovision` doivent être **rejouables** : un webhook perdu,
 * un retry de job, un double-clic d'admin ne doivent ni échouer ni dupliquer.
 * C'est la seule contrainte réellement dure du contrat.
 */
interface CustomDomainProvider
{
    /**
     * Nom du fournisseur, journalisé et stocké sur le domaine (traçabilité).
     */
    public function name(): string;

    /**
     * Crée (ou confirme) le rattachement du host au renderer.
     *
     * Doit être idempotent : rappeler avec le même host ne crée pas de doublon.
     *
     * @return CustomDomainProviderResult
     */
    public function provision(string $host): CustomDomainProviderResult;

    /**
     * Demande l'émission/renouvellement du certificat pour ce host.
     *
     * Retourne l'état observé — `pending` tant que le fournisseur n'a pas fini
     * de valider le challenge. Ne bloque pas : le service rappellera.
     */
    public function requestCertificate(string $host): CustomDomainProviderResult;

    /**
     * Supprime le rattachement et le certificat. Idempotent : supprimer un host
     * inconnu est un succès, pas une erreur.
     */
    public function deprovision(string $host): CustomDomainProviderResult;
}
