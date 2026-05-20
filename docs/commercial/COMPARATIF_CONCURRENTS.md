# Comparatif Concurrentiel — Leopardo RH vs Solutions du Marche

## Vue d'ensemble

| Critere | Leopardo RH | Sage HR | OrangeHRM | PaieNA | Kiwi HR |
|---------|------------|---------|-----------|--------|---------|
| **Type** | SaaS multi-tenant | On-premise / SaaS | Open source / SaaS | Desktop | SaaS |
| **Marche cible** | PME Maghreb + Afrique | Enterprises FR/EU | PME monde | PME DZ | PME EU |
| **Prix** | 3-5 USD/emp/mois | 10-25 EUR/emp/mois | Gratuit / 5-10 USD | Licence unique | 3.5-6 EUR/emp/mois |

---

## Comparaison detaillee

### Paie multi-pays

| Fonctionnalite | Leopardo RH | Sage HR | OrangeHRM | PaieNA | Kiwi HR |
|---------------|------------|---------|-----------|--------|---------|
| Paie DZ (IRG + CNAS) | **Oui** | Non | Non | **Oui** | Non |
| Paie MA (IR + CNSS) | **Oui** | Partiel | Non | Non | Non |
| Paie SN/TN/CI | **Oui** | Non | Non | Non | Non |
| Paie FR/TR | **Oui** | **Oui** | Non | Non | Partiel |
| Declaration CNAS/CNSS auto | **Oui** | Non | Non | Partiel | Non |
| Export SEPA XML | **Oui** | **Oui** | Non | Non | Non |
| Export CPA/BNA (DZ) | **Oui** | Non | Non | Partiel | Non |
| Bulletins PDF conformes | **Oui** | **Oui** | Non | **Oui** | Non |

**Avantage Leopardo :** Seule solution couvrant les baremes fiscaux de 6+ pays africains et europeens avec declarations sociales automatisees.

### Pointage et presences

| Fonctionnalite | Leopardo RH | Sage HR | OrangeHRM | PaieNA | Kiwi HR |
|---------------|------------|---------|-----------|--------|---------|
| QR Code | **Oui** | Non | Non | Non | Non |
| Biometrique ZKTeco | **Oui** | Non | Non | Non | Non |
| Geolocalisation mobile | **Oui** | Non | Addon payant | Non | Non |
| Detection anomalies | **Oui** | Non | Basique | Non | Non |
| Mode hors ligne | **Oui** | Non | Non | Non | Non |
| Kiosque dedie | **Oui** | Non | Non | Non | Non |

**Avantage Leopardo :** Integration native ZKTeco + QR + geolocalisation + kiosque — zero materiel supplementaire requis (telephone ou tablette existant).

### Gestion RH

| Fonctionnalite | Leopardo RH | Sage HR | OrangeHRM | PaieNA | Kiwi HR |
|---------------|------------|---------|-----------|--------|---------|
| Gestion conges | **Oui** | **Oui** | **Oui** | Basique | **Oui** |
| Recrutement kanban | **Oui** | Addon | **Oui** | Non | Non |
| Formations | **Oui** | Addon | **Oui** | Non | Non |
| Notes de frais | **Oui** | Addon | **Oui** | Non | **Oui** |
| Vehicules | **Oui** | Non | Non | Non | Non |
| Contrats + alertes | **Oui** | **Oui** | **Oui** | Basique | **Oui** |
| Prets employes | **Oui** | Non | Non | Non | Non |

### Technique et securite

| Critere | Leopardo RH | Sage HR | OrangeHRM | PaieNA | Kiwi HR |
|---------|------------|---------|-----------|--------|---------|
| API REST documentee | **Oui** (OpenAPI) | Limitee | **Oui** | Non | Limitee |
| Webhooks | **Oui** | Non | Non | Non | Non |
| Multi-tenant isole | **Oui** (schema PG) | Par instance | Par instance | N/A | Par instance |
| Chiffrement donnees sensibles | **Oui** (AES-256) | **Oui** | Non | Non | **Oui** |
| RGPD conforme | **Oui** | **Oui** | Partiel | Non | **Oui** |
| Loi 18-07 DZ conforme | **Oui** | Non | Non | Non documenté | Non |
| 2FA admin | **Oui** (TOTP) | **Oui** | Non | Non | **Oui** |
| Scan securite CI | **Oui** (ZAP + CodeQL) | N/A | N/A | N/A | N/A |
| App mobile native | **Oui** (Flutter) | **Oui** | **Oui** | Non | Non |
| Mode offline | **Oui** | Non | Non | Oui (desktop) | Non |

### Prix et modele commercial

| Critere | Leopardo RH | Sage HR | OrangeHRM | PaieNA | Kiwi HR |
|---------|------------|---------|-----------|--------|---------|
| Essai gratuit | 14 jours | Sur demande | Community gratuit | Non | 14 jours |
| Plan starter | 3 USD/emp/mois | ~10 EUR/emp/mois | 0 (limited) | ~500 USD licence | 3.50 EUR/emp/mois |
| Plan pro | 5 USD/emp/mois | ~25 EUR/emp/mois | ~5 USD/emp/mois | N/A | 6 EUR/emp/mois |
| Cout pour 50 employes/an | **1 800 USD** | ~6 000 EUR | ~3 000 USD | ~500 USD (sans support) | ~2 100 EUR |
| Paiement local DZ | **Oui** (Chargily) | Non | Non | Cash/virement | Non |
| Support en arabe | **Oui** | Non | Non | Partiel | Non |
| Support en francais | **Oui** | **Oui** | Partiel | **Oui** | Partiel |

---

## Synthese des avantages competitifs Leopardo RH

1. **Multi-pays Afrique + Maghreb** — Seule solution couvrant DZ, MA, SN, TN, CI, CM, TR, FR avec baremes fiscaux natifs
2. **Pointage multimodal** — QR + biometrique + geo + kiosque dans un seul produit
3. **Prix agressif** — 3 USD/emp/mois, 60-80% moins cher que Sage HR
4. **Paiement local** — Chargily pour l'Algerie (CIB, EDAHABIA)
5. **API ouverte** — OpenAPI documentee + webhooks pour les integrateurs
6. **Conformite locale** — RGPD + loi 18-07 DZ + declarations CNAS/CNSS automatisees
7. **Mobile native** — App Flutter avec mode offline, pas juste un responsive web
8. **Open-source friendly** — Architecture transparente, documentation publique
