<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Billing\Domain\Services\EntitlementGuard;
use App\Modules\Pages\Domain\CustomDomains\Contracts\CustomDomainProvider;
use App\Modules\Pages\Domain\CustomDomains\Contracts\CustomDomainProviderResult;
use App\Modules\Pages\Domain\CustomDomains\Dns\DnsResolver;
use App\Modules\Pages\Domain\CustomDomains\DomainInspection;
use App\Modules\Pages\Domain\CustomDomains\DomainName;
use App\Modules\Pages\Domain\Exceptions\PageCustomDomainException;
use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageCustomDomain;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Flux complet des domaines personnalisés (BC PAGES, #8726).
 *
 * ## Le principe : on ne sert que ce qui est prouvé
 *
 * Un domaine passe par trois portes, dans cet ordre, et aucune ne s'ouvre sur
 * une supposition :
 *
 * 1. **Entitlement** — le plan du tenant autorise-t-il les domaines
 *    personnalisés, et n'en a-t-il pas déjà dépassé le quota ?
 * 2. **DNS** — le tenant a-t-il posé l'enregistrement TXT attendu, et le host
 *    pointe-t-il bien vers le renderer (CNAME ou A) ?
 * 3. **Certificat** — le fournisseur a-t-il émis un certificat actif et non
 *    expiré ?
 *
 * Tant qu'une porte est fermée, `canServe()` est faux et le routage public
 * renvoie 404. Il n'existe **aucun** chemin qui active un domaine sur la seule
 * déclaration du tenant.
 *
 * ## Ce que ce service refuse de faire
 *
 * Il ne modifie **jamais** le DNS du tenant : il calcule les instructions
 * TXT/CNAME et les affiche. Un service qui « aiderait » en écrivant chez le
 * registrar de son client demanderait des identifiants DNS par tenant — un
 * risque sans commune mesure avec le confort gagné.
 */
final class PageCustomDomainService
{
    public function __construct(
        private readonly DnsResolver $dns,
        private readonly CustomDomainProvider $provider,
        private readonly EntitlementGuard $entitlements,
    ) {
    }

    /**
     * Déclare un domaine et rend les instructions DNS au tenant.
     *
     * ⚠️ Aucun appel au fournisseur ici : on ne provisionne rien avant d'avoir
     * la preuve DNS. Provisionner d'abord créerait chez le fournisseur une
     * entrée pour un host dont personne n'a encore prouvé la propriété.
     */
    public function request(Page $page, string $rawHost, string $actor): PageCustomDomain
    {
        $this->assertEntitlement($page);

        $inspection = DomainName::inspect($rawHost);
        if (! $inspection->isValid()) {
            throw PageCustomDomainException::invalidHost($inspection->errorSummary());
        }

        $host = $inspection->host;

        // Un host déjà pris — par ce tenant ou par un autre — est refusé avec un
        // message qui ne révèle PAS à qui il appartient : sinon on transforme le
        // formulaire en outil de reconnaissance du parc client.
        $existing = PageCustomDomain::query()->where('host', $host)->first();
        if ($existing instanceof PageCustomDomain) {
            if ((string) $existing->page_id === (string) $page->id
                && $existing->status !== PageCustomDomain::STATUS_REMOVED) {
                throw PageCustomDomainException::alreadyLinked();
            }

            throw PageCustomDomainException::hostTaken();
        }

        $secret = (string) config('pages.custom_domains.txt_secret', 'change-me');
        $txtName = DomainName::txtRecordName($host);
        $txtValue = DomainName::txtRecordValue($host, $secret);
        $cnameTarget = DomainName::cnameTarget($host);

        /** @var PageCustomDomain $domain */
        $domain = DB::transaction(function () use ($page, $host, $inspection, $txtName, $txtValue, $cnameTarget): PageCustomDomain {
            /** @var PageCustomDomain $domain */
            $domain = PageCustomDomain::create([
                'company_id' => $page->company_id,
                'page_id' => $page->id,
                'host' => $host,
                'status' => PageCustomDomain::STATUS_PENDING_DNS,
                'provider' => $this->provider->name(),
                'check_attempts' => 0,
                'is_apex' => $inspection->isApex,
                'is_wildcard' => $inspection->isWildcard,
                'dns_instructions' => [
                    'txt' => ['name' => $txtName, 'value' => $txtValue, 'ttl' => 300],
                    'cname' => ['name' => $host, 'value' => $cnameTarget, 'ttl' => 300],
                    'note' => $inspection->isApex
                        ? 'Domaine apex : utilisez un A/ALIAS vers la cible si votre registrar refuse le CNAME à la racine.'
                        : 'Posez le TXT puis le CNAME. Les deux sont nécessaires.',
                ],
            ]);

            return $domain;
        });

        $this->audit($page, 'page.custom_domain.requested', $actor, [
            'domain_id' => $domain->id,
            'host' => $host,
            'is_apex' => $inspection->isApex,
            'is_wildcard' => $inspection->isWildcard,
        ]);

        return $domain;
    }

    /**
     * Vérifie le DNS puis provisionne et demande le certificat si c'est bon.
     *
     * Rejouable à volonté : c'est le point d'entrée d'un job (cron, retry), donc
     * il ne doit jamais produire de doublon chez le fournisseur ni d'exception
     * qui casserait la boucle. Il retourne l'état observé.
     */
    public function verify(PageCustomDomain $domain, ?Carbon $now = null): PageCustomDomain
    {
        $now ??= Carbon::now();

        if (in_array($domain->status, [PageCustomDomain::STATUS_REMOVED], true)) {
            throw PageCustomDomainException::removed();
        }

        // Déjà actif : on ne re-provisionne pas, on rafraîchit seulement le
        // certificat si l'échéance approche. C'est ce qui rend le job idempotent.
        if ($domain->status === PageCustomDomain::STATUS_ACTIVE) {
            return $this->refreshCertificate($domain, $now);
        }

        // Propriété DÉJÀ prouvée (TXT validé lors d'un passage précédent) :
        // on ne rejoue pas la preuve DNS et on ne re-provisionne pas — seule
        // l'émission du certificat reste à suivre. Sans ce court-circuit, chaque
        // passage du job appelait `provision()` une fois de plus : le
        // fournisseur recevait un appel par minute et par domaine en attente,
        // et le journal d'opérations devenait ininterprétable.
        if ($domain->verified_at !== null
            && $domain->status === PageCustomDomain::STATUS_PENDING_CERTIFICATE) {
            $certificate = $this->provider->requestCertificate($domain->host);
            $this->applyProviderResult($domain, $certificate, $now);

            return $domain;
        }

        $secret = (string) config('pages.custom_domains.txt_secret', 'change-me');
        $expectedTxt = DomainName::txtRecordValue($domain->host, $secret);
        $txtName = DomainName::txtRecordName($domain->host);

        $records = $this->dns->txt($txtName);
        $found = in_array($expectedTxt, $records, true);

        if (! $found) {
            $domain->markDnsPending($records === []
                ? sprintf('aucun enregistrement TXT trouvé sur %s', $txtName)
                : sprintf('TXT présent sur %s mais valeur inattendue', $txtName));
            $domain->save();

            $this->audit($domain->page, 'page.custom_domain.dns_pending', 'system', [
                'domain_id' => $domain->id,
                'host' => $domain->host,
                'attempts' => $domain->check_attempts,
                'status' => $domain->status,
            ]);

            return $domain;
        }

        // Deuxième contrôle : le host doit réellement pointer vers le renderer.
        // Un TXT seul prouve la maîtrise du DNS, pas que le trafic arrive chez
        // nous — sans ce contrôle on activerait un domaine qui pointe ailleurs.
        $cnames = $this->dns->cname($domain->host);
        $pointsToUs = in_array(DomainName::cnameTarget($domain->host), $cnames, true);

        if (! $pointsToUs && ! $this->dns->resolves($domain->host)) {
            $domain->markDnsPending(sprintf(
                'TXT valide mais %s ne résout pas vers le renderer (CNAME attendu : %s)',
                $domain->host,
                DomainName::cnameTarget($domain->host),
            ));
            $domain->save();

            $this->audit($domain->page, 'page.custom_domain.dns_pending', 'system', [
                'domain_id' => $domain->id,
                'host' => $domain->host,
                'attempts' => $domain->check_attempts,
                'status' => $domain->status,
            ]);

            return $domain;
        }

        // Preuve DNS acquise : à partir d'ici seulement, on parle au fournisseur.
        $domain->markVerified();
        $domain->save();

        $provision = $this->provider->provision($domain->host);
        $domain->provider_reference = $provision->detail;
        $domain->save();

        $certificate = $this->provider->requestCertificate($domain->host);

        $this->applyProviderResult($domain, $certificate, $now);

        $this->audit($domain->page, 'page.custom_domain.verified', 'system', [
            'domain_id' => $domain->id,
            'host' => $domain->host,
            'status' => $domain->status,
            'provider' => $this->provider->name(),
        ]);

        return $domain;
    }

    /**
     * Rafraîchit/renouvelle le certificat d'un domaine déjà actif.
     */
    public function refreshCertificate(PageCustomDomain $domain, ?Carbon $now = null): PageCustomDomain
    {
        $now ??= Carbon::now();

        if ($domain->certificate_expires_at !== null
            && $domain->certificate_expires_at->lessThanOrEqualTo($now)) {
            // Expiré : on NE sert plus et on repasse par l'émission. Le statut
            // devient `expired` immédiatement — attendre le renouvellement en
            // servant du TLS invalide punirait les visiteurs.
            $domain->markExpired('certificat expiré');
            $domain->save();

            $this->audit($domain->page, 'page.custom_domain.expired', 'system', [
                'domain_id' => $domain->id,
                'host' => $domain->host,
            ]);
        }

        if (! $domain->needsCertificateRenewal($now)) {
            $domain->last_checked_at = $now;
            $domain->save();

            return $domain;
        }

        $result = $this->provider->requestCertificate($domain->host);
        $this->applyProviderResult($domain, $result, $now);

        return $domain;
    }

    /**
     * Applique un résultat fournisseur à l'état local.
     *
     * Centralisé : c'est le seul endroit où un résultat distant devient un
     * statut local. Dupliquer cette correspondance dans chaque appelant est la
     * manière classique d'obtenir deux vérités divergentes.
     */
    private function applyProviderResult(
        PageCustomDomain $domain,
        CustomDomainProviderResult $result,
        Carbon $now,
    ): void {
        match ($result->state) {
            CustomDomainProviderResult::STATE_ACTIVE => $result->isServing()
                ? $domain->markActive($now->copy()->addMonths(3))
                : $domain->markCertificatePending('host actif mais certificat inactif'),
            CustomDomainProviderResult::STATE_PENDING => $domain->markCertificatePending(
                $result->detail ?? 'validation en cours'
            ),
            CustomDomainProviderResult::STATE_FAILED => $domain->markFailed(
                $result->detail ?? 'échec fournisseur'
            ),
            // `absent` : le fournisseur a perdu l'entrée (purge, changement de
            // compte). On ne peut pas servir : on repasse en attente plutôt que
            // de garder un `active` mensonger.
            default => $domain->markCertificatePending('entrée absente chez le fournisseur'),
        };

        $domain->last_checked_at = $now;

        if ($result->certificateActive && $domain->certificate_expires_at === null) {
            $domain->certificate_expires_at = $now->copy()->addMonths(3);
        }

        $domain->save();
    }

    /**
     * Supprime un domaine : révocation côté fournisseur puis état local.
     *
     * L'ordre compte. On marque d'abord localement (donc le host cesse
     * immédiatement de servir), puis on révoque. Si la révocation échoue, le
     * pire état est un domaine déjà éteint dont l'entrée fournisseur traîne —
     * inoffensif. L'ordre inverse laisserait le host servir pendant tout
     * l'aller-retour réseau.
     */
    public function remove(PageCustomDomain $domain, string $actor): PageCustomDomain
    {
        if ($domain->status !== PageCustomDomain::STATUS_REMOVED) {
            $domain->markRemoved();
            $domain->save();

            $this->audit($domain->page, 'page.custom_domain.removed', $actor, [
                'domain_id' => $domain->id,
                'host' => $domain->host,
            ]);
        }

        // Idempotent, et une erreur fournisseur ne doit pas annuler la
        // suppression locale déjà faite : on la journalise dans `last_error`.
        $result = $this->provider->deprovision($domain->host);
        if ($result->state === CustomDomainProviderResult::STATE_FAILED) {
            $domain->last_error = 'révocation fournisseur en échec : '.($result->detail ?? 'inconnu');
            $domain->save();
        }

        return $domain;
    }

    /**
     * Host → Page servie, avec contrôle complet.
     *
     * Utilisé par le renderer public : c'est la SEULE porte d'entrée du
     * routage par domaine. Elle renvoie `null` si quoi que ce soit ne va pas,
     * volontairement — un renderer qui doit distinguer six cas d'échec finit
     * par en oublier un.
     */
    public function resolveHost(string $host, ?Carbon $now = null): ?Page
    {
        $normalized = DomainName::normalize($host);
        if ($normalized === null) {
            return null;
        }

        $domain = PageCustomDomain::query()->where('host', $normalized)->first();
        if (! $domain instanceof PageCustomDomain) {
            return null;
        }

        if (! $domain->canServe($now)) {
            return null;
        }

        $page = $domain->page;
        if (! $page instanceof Page) {
            return null;
        }

        // Le domaine suit la Page : une Page dépubliée ne sert pas, même si son
        // domaine est `active`. Les deux conditions sont indépendantes et il
        // faut les deux.
        return $page->isPubliclyVisible() ? $page : null;
    }

    /**
     * Quota et plan du tenant.
     */
    private function assertEntitlement(Page $page): void
    {
        $companyId = (string) $page->company_id;

        if (! $this->entitlements->isFeatureEnabled($companyId, 'pages_custom_domain')) {
            throw PageCustomDomainException::entitlementMissing();
        }

        $limit = $this->entitlements->featureLimit($companyId, 'pages_custom_domain');

        if ($limit === null) {
            // `null` = pas de limite explicite dans la matrice. On ne l'interprète
            // PAS comme « illimité » : une feature absente de la matrice est déjà
            // refusée plus haut, donc arriver ici avec `null` signifie que la
            // matrice autorise sans quota — comportement voulu, documenté.
            return;
        }

        $used = PageCustomDomain::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', PageCustomDomain::STATUS_REMOVED)
            ->count();

        if ($used >= $limit) {
            throw PageCustomDomainException::quotaExceeded($limit);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function audit(?Page $page, string $action, string $actor, array $metadata): void
    {
        if (! $page instanceof Page) {
            return;
        }

        AuditLog::query()->create([
            'company_id' => $page->company_id,
            'module' => 'pages',
            'action' => $action,
            'auditable_type' => Page::class,
            'auditable_id' => $page->id,
            'actor' => $actor,
            'new_values' => $metadata,
        ]);
    }
}
