<?php

declare(strict_types=1);

namespace App\Modules\Trade\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\CRM\Domain\Models\CrmLead;
use Illuminate\Database\DatabaseManager;

/**
 * Demande de devis d'un visiteur PUBLIC vers un vendeur B2B — #9779.
 *
 * ## Le trou que cette action comble
 *
 * `GET /public/trade/companies/{slug}` et `.../products` rendaient le vendeur
 * VISIBLE : fiche, catalogue, MOQ, prix indicatif. Mais un acheteur qui
 * trouvait le bon fournisseur n'avait **aucun moyen de le joindre**. Le
 * catalogue public etait une vitrine sans porte : le vendeur ne pouvait pas
 * etre promu au sens ou la mission l'entend, parce que la visite ne produisait
 * jamais de contact.
 *
 * ## Ce que cette action fait
 *
 * Elle cree un **lead CRM** dans le tenant du vendeur, avec `source` explicite
 * (`trade.public.inquiry`) pour que le commercial sache d'ou vient le contact
 * et puisse mesurer la conversion de sa fiche publique.
 *
 * ## Ce qu'elle ne fait PAS
 *
 * - Elle ne cree AUCUNE RFQ : une demande de devis publique n'est pas une
 *   consultation structuree (pas de lignes, pas de quantites validees, pas de
 *   contrepartie identifiee). La transformer en RFQ inventerait des donnees
 *   que le visiteur n'a pas fournies. Le commercial qualifie le lead, puis
 *   cree la RFQ s'il y a lieu.
 * - Elle n'envoie AUCUN email : le vendeur voit le lead dans son CRM. Un
 *   envoi non configure serait un mensonge d'interface.
 * - Elle ne revele RIEN au visiteur : la reponse est un `202` uniforme, sans
 *   identifiant de lead ni nom du tenant. Un visiteur ne doit pas pouvoir
 *   sonder l'existence d'un tenant par ce canal.
 *
 * ## Perimetre
 *
 * Le `company_id` vient de `TradeProfileResolver` (opt-in `is_public`, slug
 * unique global, societe non suspendue, feature `trade` active) — jamais du
 * corps de la requete. Un visiteur ne peut donc pas deposer un lead chez un
 * vendeur qui n'a pas publie sa fiche.
 */
final class SubmitPublicTradeInquiryAction
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array{name: string, email: string, company_name?: string|null, phone?: string|null, message: string, product_name?: string|null}  $data
     */
    public function execute(Company $company, array $data): void
    {
        $name = trim($data['name']);
        $email = strtolower(trim($data['email']));

        // Le nom complet est decoupe : `crm_leads` porte first_name/last_name,
        // et un lead dont le nom est entasse dans un seul champ est illisible
        // dans les listes du CRM.
        $parts = preg_split('/\s+/', $name, 2) ?: [$name];
        $firstName = $parts[0] ?? $name;
        $lastName = $parts[1] ?? null;

        $this->tenants->withinTenant($company, function () use ($company, $data, $firstName, $lastName, $email): void {
            $this->db->transaction(function () use ($company, $data, $firstName, $lastName, $email): void {
                CrmLead::query()->create([
                    'company_id' => $company->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'company_name' => $data['company_name'] ?? null,
                    'email' => $email,
                    'phone' => $data['phone'] ?? null,
                    // `source` est ce qui rend la fiche publique MESURABLE : sans
                    // elle, le commercial ne distingue pas un lead venu du
                    // catalogue public d'un lead saisi a la main.
                    'source' => 'trade.public.inquiry',
                    'status' => 'new',
                    'notes' => $this->composeNotes($data),
                ]);
            });
        });
    }

    /**
     * Le message du visiteur et le produit vise sont conserves dans les notes :
     * `crm_leads` n'a pas de colonne `message`, et perdre le texte rendrait le
     * lead inexploitable (on saurait qu'on a ete contacte, pas pourquoi).
     *
     * @param  array{message: string, product_name?: string|null}  $data
     */
    private function composeNotes(array $data): string
    {
        $lines = [];

        $product = trim((string) ($data['product_name'] ?? ''));

        if ($product !== '') {
            $lines[] = 'Produit demande : '.$product;
        }

        $lines[] = trim($data['message']);

        return implode("\n\n", $lines);
    }
}
