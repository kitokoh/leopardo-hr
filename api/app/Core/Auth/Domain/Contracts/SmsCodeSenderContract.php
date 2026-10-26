<?php

declare(strict_types=1);

namespace App\Core\Auth\Domain\Contracts;

/**
 * Envoi d'un code a usage unique par SMS (BC-03 IDENTITE, #8738).
 *
 * **Pourquoi un contrat dans Core/Auth et pas `Modules/CRM`.** L'adaptateur SMS
 * du CRM (`SmsAdapter`) est excellent mais il est *dans* un module, et la garde
 * d'isolation interdit a `Core/Auth` de l'importer. Le besoin d'authentification
 * est pourtant plus etroit : envoyer 6 chiffres a un numero deja normalise.
 *
 * **Contrat volontairement minimal.** Trois methodes seulement :
 *  - `send()` rend un identifiant de trace, ou `null` si l'envoi n'a pas pu
 *    etre pris en charge ;
 *  - `isConfigured()` permet a l'appelant de decider AVANT de generer un code
 *    s'il doit replier sur un autre canal (jamais de code genere puis perdu) ;
 *  - `channel()` nomme le canal dans les traces, sans jamais porter de PII.
 *
 * **Ce que le contrat interdit.** Aucune implementation ne doit journaliser le
 * code ni le numero complet : le critere d'acceptation de #8738 exige que le
 * code n'apparaisse jamais en clair dans les logs.
 */
interface SmsCodeSenderContract
{
    /**
     * Nom stable du canal, pour les traces (`sms`, `log`, ...).
     */
    public function channel(): string;

    /**
     * Vrai si le canal peut reellement delivrer un message.
     *
     * Un canal non configure doit le dire AVANT l'envoi : generer un code puis
     * decouvrir qu'aucun transport n'existe laisserait l'utilisateur bloque
     * sans code et sans explication.
     */
    public function isConfigured(): bool;

    /**
     * Envoie un code a un numero **deja normalise en E.164**.
     *
     * Le contrat prend l'E.164, pas une saisie libre : la normalisation est le
     * role de `App\Support\PhoneNumber`, et un canal ne doit pas re-deviner un
     * pays. Si le numero n'est pas un E.164 valide, l'implementation renvoie
     * `null` plutot que d'envoyer a un numero douteux.
     *
     * @return string|null identifiant de trace, ou `null` si non envoye
     */
    public function send(string $e164, string $code, string $locale): ?string;
}
