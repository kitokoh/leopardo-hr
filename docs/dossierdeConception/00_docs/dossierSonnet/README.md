# dossierSonnet/ — PDFs de la session de conception (avril 2026)

Ce sous-dossier contient les 16 PDF produits pendant la session de conception initiale d'avril 2026 (voir `Leopardo_RH_INDEX_DOCUMENTS.pdf` pour l'index de bibliotheque original : 17 documents, ~501 pages, 7 categories). Ils sont conserves ici **tels que produits**, comme piece d'archive de la session, en complement du cahier des charges brut de `../` (CDC_v3, DCT_v1, etc.).

## Canonicite : ne pas lire ces PDF en cas de doute, lire `../../../vision/README.md`

4 de ces PDF existent **egalement** dans `docs/vision/` avec un statut de canonicite explicite (`docs/vision/README.md` documente pour chacun s'il est "canonical", "historique" ou "archive Phase 2", et par quel document court il est remplace) :

| Fichier ici | Copie dans `docs/vision/` | Statut selon `docs/vision/README.md` |
|---|---|---|
| `Leopardo_RH_APV_v2.pdf` | `01_architecture_produit/Leopardo_RH_APV_v2.pdf` | Canonical (remplace par `docs/REFERENTIEL_PRODUIT/APV.md`) |
| `Leopardo_RH_Architecture_Vivante.pdf` | `99_historique/Leopardo_RH_Architecture_Vivante.pdf` | Historique (v1, remplace par APV v2) |
| `Leopardo_RH_Design_System_v3.pdf` | `02_design_system/Leopardo_RH_Design_System_v3.pdf` | Canonical (remplace par `docs/REFERENTIEL_PRODUIT/APV.md` + `COULEURS.md`) |
| `Leopardo_RH_Finance_Complet.pdf` | `03_modules_phase2/Leopardo_RH_Finance_Complet.pdf` | Archive Phase 2, module non implemente |

**En cas de divergence entre une copie ici et sa copie dans `docs/vision/`, la copie de `docs/vision/` fait foi** — c'est elle qui est indexee et versionnee explicitement par un README de canonicite dedie.

### Note sur `Leopardo_RH_Finance_Complet.pdf` : deux copies non identiques (verifie 2026-07-29)

Les 2 copies de ce fichier ont un contenu textuel identique (meme texte extrait des 48 pages, memes metadonnees PDF, meme date de creation `2026-04-21`), mais une taille binaire differente : 150491 octets ici, 151573 octets dans `docs/vision/`. Verification git : la copie de `docs/vision/` a ete re-ecrite (memes octets de contenu, resultat probable d'un ré-export ou d'une regeneration du PDF) par le commit `75056c6e` (2026-05-29, PR #618) qui touchait par ailleurs des PDF de `docs/GESTION_PROJET/`/marketing sans lien fonctionnel avec Finance — la copie de ce dossier (`dossierSonnet/`) n'a pas ete mise a jour en meme temps. Il n'y a pas de perte d'information (texte identique), donc pas d'action corrective necessaire au-dela de cette note explicative.

## Les autres PDF (non dupliques dans `docs/vision/`)

Les 12 autres PDF (`Leopardo_RH_Admin_Dashboard_Marketing`, `Leopardo_RH_Architecture_Deploiement`, `Leopardo_RH_Architecture_IA_Native`, `Leopardo_RH_Gestion_Utilisateurs_v2`, `Leopardo_RH_GoToMarket`, `Leopardo_RH_INDEX_DOCUMENTS`, `Leopardo_RH_Multilinguisme_Complet`, `Leopardo_RH_Pointage_Validation_Finale`, `Leopardo_RH_Positionnement_Marche` (x2), `Leopardo_RH_Production_Creative`, `Leopardo_RH_Protocole_Qualite_API`) n'ont pas de copie ailleurs dans le depot et restent l'unique trace ecrite de leur sujet respectif issue de cette session. Deux d'entre eux sont explicitement rattaches ailleurs par `docs/vision/README.md`, section "Hors de ce dossier" :

- `Leopardo_RH_Architecture_Deploiement.pdf` -> reference d'architecture non canonique, voir aussi `docs/infra/03_archives_pdf/`
- `Leopardo_RH_Pointage_Validation_Finale.pdf` -> referentiel QA du module pointage, voir aussi `docs/validation/01_pointage/`

Pour les autres (marketing, IA, i18n, etc.), aucune source de verite courte n'existe encore ailleurs dans le depot : ces PDF restent donc la reference de fait sur leur sujet jusqu'a ce qu'un document court equivalent soit cree dans `docs/REFERENTIEL_PRODUIT/` ou `docs/GTM/`.
