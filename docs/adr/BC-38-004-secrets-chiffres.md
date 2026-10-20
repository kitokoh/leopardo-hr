# ADR BC-38-004 — Secrets chiffrés at-rest et masqués en sortie

- **Statut** : accepté
- **BC** : BC-38 PAIEMENTS
- **Issues** : #8685, #8688

## Contexte

Une instance de fournisseur porte des identifiants et des secrets (clés d'API,
secrets de signature de webhook). Ces valeurs sont saisies par un admin dans le
Superadmin et doivent être utilisables par le serveur — mais ne doivent jamais
être lisibles par un humain qui consulte la base, un log, un export ou une
réponse d'API.

## Décision

1. **Chiffrement at-rest** via le coffre applicatif Laravel : casts
   `encrypted:array` (`PaymentProviderInstance::$config`) et `encrypted`
   (`PaymentWebhookEndpoint::$secret_signature`). La colonne est du texte
   illisible en base.
2. **Masquage systématique en sortie** : `toSafeArray()` remplace chaque valeur
   de configuration par `••••`. L'API de lecture ne renvoie **jamais** un secret.
3. **Aucune fuite par les exceptions** : les messages d'erreur métier ne
   contiennent pas de valeur de configuration.
4. **Fail-closed** : sans secret de signature configuré, tout webhook est rejeté.

## Conséquences

- Un dump de base ou un log ne divulgue aucun secret.
- Un admin ne peut pas relire un secret qu'il a saisi : il peut seulement le
  remplacer. C'est volontaire — un secret lisible est un secret compromis.

## Alternatives écartées

- **Stockage en clair** : rejetée — un dump de base suffirait à compromettre
  tous les fournisseurs.
- **Hashage** : impossible — le serveur doit présenter le secret au fournisseur,
  donc il doit pouvoir le déchiffrer.
- **Coffre externe (Vault/KMS)** : écartée pour ce lot — le coffre applicatif
  Laravel (`APP_KEY`) couvre le besoin ; un KMS dédié reste une évolution
  possible sans changer le contrat des modèles.
