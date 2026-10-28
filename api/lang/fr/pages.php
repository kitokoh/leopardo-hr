<?php

/**
 * BC-39 PAGES — messages serveur (fr).
 *
 * Parite stricte des cles avec en/ar/tr (garde check-i18n-catalog-parity.sh).
 * Aucune phrase francaise accentuee ne doit apparaitre en dur dans les
 * controleurs ou les exceptions (PA2-I18N-007, CONVENTIONS.md).
 */
return [
    // #8723 — publication et rattachement a un etablissement
    'publish_seo_title_required' => 'Une Page sans titre SEO ne peut pas etre publiee : elle produirait une URL indexable sans intitule.',
    'site_not_found' => 'Etablissement introuvable pour la Page (site_id=:site).',
    'site_cross_tenant' => "L'etablissement :site n'appartient pas a la societe :company : une Page ne peut pas etre rattachee au lieu d'un autre tenant.",

    // #8724 — registre de blocs
    'block_type_duplicate' => 'Le type de bloc « :type » est deja fourni par le BC « :owner » : « :new » ne peut pas l\'enregistrer a nouveau.',

    // #8725 — dossier de verification
    'verification_method_disabled' => 'La methode de verification « :method » n\'est pas activee sur cette plateforme.',
    'verification_already_open' => 'Un dossier de verification est deja ouvert pour cette Page.',
    'verification_closed' => 'Ce dossier de verification est clos : ouvrez-en un nouveau pour redemander une verification.',
    'verification_suspended' => 'Une Page suspendue ne peut pas faire l\'objet d\'une demande de verification.',
    'verification_wrong_method' => 'Cette operation exige la methode « :expected » (dossier en « :actual »).',
    'verification_cooldown' => 'Un code vient d\'etre envoye : reessayez dans :seconds seconde(s).',
    'verification_code_not_found' => 'Aucun code n\'a ete emis pour ce numero sur ce dossier.',
    'verification_code_expired' => 'Ce code a expire : demandez-en un nouveau.',
    'verification_code_consumed' => 'Ce code a deja ete utilise.',
    'verification_code_locked' => 'Trop de tentatives : demandez un nouveau code.',
    'verification_code_mismatch' => 'Code incorrect (:remaining tentative(s) restante(s)).',
    'verification_no_evidence' => 'Un dossier documentaire ne peut pas etre approuve sans aucune piece justificative.',
    'verification_evidence_invalid' => 'Piece justificative refusee : :errors',
    'verification_evidence_path_public' => 'Le chemin de stockage d\'une piece ne peut pas etre une URL publique ou un chemin absolu.',
    'verification_reason_invalid' => 'Motif de decision inconnu : « :reason ».',
    'verification_transition_invalid' => 'Transition de verification impossible : :from -> :to.',

    // #8726 — domaines personnalises
    'domain_invalid' => 'Ce domaine n\'est pas valide : :reason',
    'domain_taken' => 'Ce domaine est deja rattache a une Page. Si vous en etes proprietaire, contactez le support.',
    'domain_already_linked' => 'Ce domaine est deja rattache a cette Page.',
    'domain_entitlement_missing' => 'Les domaines personnalises ne sont pas inclus dans votre offre actuelle.',
    'domain_quota_exceeded' => 'Votre offre autorise :limit domaine(s) personnalise(s).',
    'domain_removed' => 'Ce domaine a ete supprime et ne peut plus etre verifie.',
    'domain_plan_expired' => 'Votre abonnement a expire : les domaines personnalises sont desactives jusqu\'au renouvellement.',

    // #8730 — rendu public SSR
    'public' => [
        'verified' => 'Etablissement verifie',
        'contact' => 'Contact',
        'map' => 'Voir sur la carte',
        'actions' => [
            'directions' => 'Y aller',
            'contact' => 'Ecrire',
        ],
    ],
];
