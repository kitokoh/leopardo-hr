# 📄 DPA — Accord de traitement des données (RGPD) — F-18

> Programme FOCUS — contrat de sous-traitance type pour les clients de Leopardo RH.
> Statut : **brouillon à faire relire par un conseil juridique** (ne constitue pas un avis juridique).

## Parties
- **Responsable de traitement** : le client (entreprise utilisant Leopardo RH).
- **Sous-traitant** : l'éditeur (Kitokoh.com / Leopardo RH).

## Données traitées
Catégories : identité des employés, données de paie (salaires, IBAN), pointage et biométrie (kiosk/mobile), absences, congés, documents RH. Durées de conservation conformes à la matrice RGPD existante (`docs/security/MATRICE_CONFORMITE_RGPD_LOI_18_07.md`).

## Sous-traitants ultérieurs (à tenir à jour)
- Hébergement : Render · Cache/queue : Upstash (Redis) · Push/stockage mobile : Firebase · Email : selon fournisseur configuré.
- Tout nouveau sous-traitant = mise à jour de ce document + information du client.

## Obligations
- Traiter uniquement sur instruction documentée du client.
- Confidentialité (personnels liés par obligation), sécurité (chiffrement au repos/en transit — F-17), notification de violation ≤ 72 h.
- Aide au client pour les droits des personnes (accès, rectification, **effacement** — purge employé testée).
- Audit : accès aux registres de traitements sur demande motivée.

## Durée / résiliation
- Durée du contrat client ; à résiliation : restitution ou **suppression certifiée** des données sous 30 jours.

## Registre des traitements
- Document existant : `docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md` — à référencer dans la DPA.
