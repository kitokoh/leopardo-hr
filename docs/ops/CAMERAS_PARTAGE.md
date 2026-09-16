# Partager une caméra avec un tiers (lien de partage)

**Issue** : #7425 (tranche 2) · **Dernière mise à jour** : 2026-09-16 · **Public** : responsable d'entreprise, exploitant.

---

## 1. Le parcours, en deux endroits

| Étape | Où | Ce qui se passe |
|---|---|---|
| 1 | Espace client → **Caméras** → bouton **Partager** sur une caméra | Le responsable crée un lien : destinataire (libellé libre), durée de validité (1 h / 1 j / 7 j / 30 j) |
| 2 | Le lien est **copié automatiquement** | Il n'est affiché **qu'une fois** : le jeton brut n'est jamais relu ensuite (l'API ne le renvoie qu'à la création). Perdu ? On en crée un autre. |
| 3 | Le tiers ouvre le lien → **`/view/cam#t=…`** | Page publique, **sans compte** : elle lit le jeton dans le fragment, interroge l'API et affiche à quoi le lien donne droit |
| 4 | Espace client → **Partager** → **Révoquer** | L'accès est coupé immédiatement (le lien restant reçoit « lien invalide ou expiré ») |

## 2. Pourquoi le jeton est dans le **fragment** (`#t=…`) et jamais dans la chaîne de requête

Un lien de partage est un **jeton porteur** : qui l'a voit le flux. Sa forme dans l'URL n'est donc pas un détail cosmétique :

- un jeton en **chaîne de requête** (`?t=…`) est écrit dans les **journaux** du proxy, du CDN et du serveur applicatif, et transmis à des sites tiers via l'en-tête **`Referer`** ;
- un jeton dans le **fragment** (`#t=…`) n'est **jamais envoyé au serveur** : le navigateur le garde côté client. Le contrôleur public `PublicCameraViewerController` le lit uniquement dans l'en-tête **`X-Token`** — le repli `?t=` a été **supprimé** lors de l'audit sécurité (#4931/#6560).

Conséquence assumée : la page `/view/cam` **n'utilise pas** un jeton reçu en chaîne de requête. Elle le dit explicitement au visiteur (« lien obsolète : le jeton est journalisé par les proxys ; demandez un lien à jour ») au lieu de l'accepter en silence. Un ancien lien `?t=` ne peut pas être « réparé » : le jeton est déjà sorti.

Côté API, `share_url` est construit **avec le fragment** ; un test le verrouille (`CameraAccessTokensTest::test_share_url_keeps_the_token_out_of_the_query_string`).

## 3. Ce que la page de visionnage fait — et ne fait pas

**Fait** : vérifier le lien, afficher le nom et l'emplacement de la caméra, le destinataire, la date d'expiration, l'adresse du flux, et l'état réel de la chaîne vidéo. Elle est en `robots: noindex, nofollow` : un lien privé ne doit pas être indexé.

**Ne fait pas** : afficher un lecteur vidéo. Le direct est servi par un **nœud Edge installé chez le client** (ADR-0021) : sans ce nœud, l'accès est bien autorisé mais aucun flux n'existe. Afficher une image simulée serait mentir sur l'état du système (#7477).

## 4. Sécurité — les quatre règles

1. **Le jeton ne va jamais en chaîne de requête** (cf. §2) ;
2. **Le jeton brut n'est affiché qu'une fois** (à la création) ; la liste ne montre que les métadonnées ;
3. **Toute émission de lien est journalisée** (`camera_access_logs` : qui a partagé, quand) et toute lecture alimente le même journal ;
4. **La révocation est immédiate** et ne dépend d'aucun cache : le lien révoqué répond « invalide ou expiré ».

## 5. Exploitation

- Un lien qui « ne marche pas » : d'abord vérifier qu'il contient bien `#t=` (et non `?t=`), puis qu'il n'est ni expiré, ni révoqué (espace client → Partager).
- Le **nœud Edge** est un prérequis pour voir une image : sans lui, la page est correcte mais vide de flux — c'est l'état normal d'un client non équipé.
- Durées autorisées : `cameras.access_token_durations` (1 h, 1 j, 7 j, 30 j) ; la durée maximale est bornée par `CAMERAS_ACCESS_TOKEN_MAX`.
