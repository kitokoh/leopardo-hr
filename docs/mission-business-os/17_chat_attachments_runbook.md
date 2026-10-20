# Runbook — persistance des pièces jointes du chat (risque R1)

> **Statut du risque : NON LEVÉ en production.**
> Le code est prêt (variable `CHAT_ATTACHMENT_DISK`, disque enregistré par
> message, repli documenté), mais **la variable n'est pas positionnée sur
> l'environnement Render**. Tant que ce n'est pas fait, les pièces jointes
> sont écrites sur le disque `local`, qui vit dans le conteneur et **disparaît
> à chaque redéploiement**. Ce document décrit l'activation ; il ne l'a pas
> exécutée.

Contexte : `docs/mission-business-os/16_chat_horizontal_audit.md` (risque R1),
issue [#8748](https://github.com/kitokoh/leopardo-hr/issues/8748).

---

## 1. Ce qui se passe aujourd'hui, exactement

| Étape | Comportement actuel |
|---|---|
| Envoi d'un message avec pièce jointe | écrite sur le disque renvoyé par `config('conversations.attachments.disk')` |
| Valeur de cette config | `env('CHAT_ATTACHMENT_DISK', env('FILESYSTEM_DISK', 'local'))` |
| `CHAT_ATTACHMENT_DISK` en production | **non définie** → repli sur `FILESYSTEM_DISK` → `local` |
| Nom du disque utilisé | **persisté sur chaque message** (`conversation_messages.attachment_disk`) |
| Redéploiement Render | le conteneur est reconstruit → **le fichier n'existe plus** |
| Téléchargement ensuite | la ligne en base subsiste, le disque enregistré est relu, le fichier est absent → **`410 Gone`** |

Le `410` est le comportement correct : le message existe, le fichier non. Il ne
faut **pas** transformer cela en `404` — cela ferait croire que le message a
disparu, alors que seul le binaire a été perdu.

**Preuve de la résolution du disque** : `api/tests/Feature/ConversationAttachmentPersistenceTest.php`
(4 tests). Ils montrent qu'une pièce écrite sur le disque A reste téléchargeable
après bascule de la configuration vers B, que les nouvelles pièces suivent le
nouveau disque, qu'un fichier réellement perdu répond `410`, et qu'un
non-participant reçoit `404`.

⚠️ **Ces tests ne prouvent PAS que le disque configuré en production est
persistant.** Aucun test ne peut le prouver depuis le code : cela dépend du
montage d'un volume ou d'un bucket. C'est précisément l'objet de ce runbook.

---

## 2. Choisir le disque de destination

Deux options compatibles avec la stack actuelle, **sans nouveau fournisseur
payant** :

### Option A — disque `s3` déjà déclaré (recommandée si un bucket existe)

`config/filesystems.php` déclare déjà un disque `s3`. Aucun code à écrire.

- **Avantage** : durable par nature, survit à tout redéploiement et à tout
  changement de dyno/worker.
- **Coût** : le stockage objet existant. Les pièces du chat restent bornées à
  **5 Mo par fichier** (`conversations.attachments.max_kb`, valeur par défaut
  `5120`), ce qui garde le volume prévisible.
- **Prérequis** : les clés du fournisseur sont déjà dans l'environnement —
  ne les recopiez pas ailleurs, ne les commitez jamais.

### Option B — disque `private` monté sur un volume persistant

Utile si aucun bucket n'est disponible. Nécessite un volume Render monté sur
l'arborescence servie par le disque `private`, déclaré dans
`config/filesystems.php`.

- **Avantage** : pas de dépendance à un service externe.
- **Limite réelle** : un volume est attaché à **une seule** instance. Dès que
  l'API tourne sur deux instances (montée en charge), les deux ne voient pas les
  mêmes fichiers. C'est une contrainte d'infrastructure, pas un bug du code —
  mais elle rend l'option B **incompatible avec l'autoscaling**.

> 👉 Pour un lancement avec les 10 premiers professionnels, l'option B suffit et
> ne coûte rien de plus. Pour la croissance vers plusieurs centaines, seule
> l'option A tient. Ce basculement est justement ce que le test
> `test_attachment_remains_downloadable_after_the_configured_disk_is_switched`
> sécurise : **les anciennes pièces restent lisibles** après le changement.

---

## 3. Procédure d'activation

### 3.1 Poser la variable (aucun secret dans ce document)

Dans le tableau de bord Render → service API → *Environment* :

```
CHAT_ATTACHMENT_DISK=<nom-du-disque>
```

où `<nom-du-disque>` est **la clé du disque dans `config/filesystems.php`**
(`s3` ou `private`), pas un chemin et pas une URL. C'est un choix
d'infrastructure, il n'a rien de confidentiel — contrairement aux clés
d'accès du même écran, qui ne doivent jamais quitter le coffre de la
plateforme.

Variables optionnelles, mêmes règles :

```
CHAT_ATTACHMENT_MAX_KB=5120      # taille max par fichier
CHAT_ATTACHMENT_URL_TTL=300      # durée des URLs temporaires (disques qui savent en produire)
```

### 3.2 Vérifier la parité de configuration

Toute variable lue par un fichier de `config/` doit exister dans
`api/.env.example`, sinon le check `Hygiene Guards` échoue :

```bash
bash dev-hub/tools/check-env-example-parity.sh
# attendu : "Parité config/ ↔ .env.example : 445 clés, 0 manquante."
```

Les trois variables `CHAT_ATTACHMENT_*` y sont déjà : **rien à ajouter**. Si
vous en introduisez une nouvelle, ajoutez-la **sans valeur** (une ligne
commentée ne satisfait pas le garde).

### 3.3 Redéployer, puis vérifier sur l'environnement réel

Après redéploiement :

1. envoyer un message avec une pièce jointe dans un fil de test ;
2. confirmer en base que `attachment_disk` vaut bien le nouveau disque :

   ```sql
   SELECT id, attachment_disk, attachment_path
   FROM conversation_messages
   WHERE attachment_path IS NOT NULL
   ORDER BY id DESC LIMIT 5;
   ```

3. télécharger la pièce (doit répondre `200`) ;
4. **provoquer un redéploiement**, puis retélécharger la **même** pièce.
   - `200` → R1 est levé sur cet environnement ;
   - `410` → le disque choisi n'est pas persistant ; revenir à l'étape 2.

L'étape 4 est la seule qui prouve quoi que ce soit. Un `200` avant
redéploiement ne prouve rien : le fichier est encore dans le conteneur courant.

### 3.4 Surveiller le taux de `410`

`410` sur `GET /conversations/{thread}/messages/{message}/attachment` est le
signal d'alerte de R1. Une valeur non nulle et stable signifie « des fichiers
sont perdus » ; une valeur qui apparaît **après un déploiement** signifie que le
disque n'est pas persistant.

---

## 4. Repli et réversibilité

- **Retirer `CHAT_ATTACHMENT_DISK`** revient au comportement historique
  (`FILESYSTEM_DISK`, puis `local`). Aucune migration, aucun code à changer.
- **Les pièces déjà écrites restent lisibles** quel que soit le nouveau
  réglage : le disque est lu depuis le message, pas depuis la configuration
  courante. C'est l'objet du test de bascule.
- **Rien n'est supprimé** par un changement de disque. Les fichiers de l'ancien
  disque restent où ils sont ; seule la lecture les retrouve.

---

## 5. Ce qui reste à faire (et qui n'est pas fait ici)

- [ ] Choisir le disque (A ou B) — **décision d'exploitation, pas de code**.
- [ ] Poser `CHAT_ATTACHMENT_DISK` sur l'environnement Render.
- [ ] Exécuter la vérification §3.3 **avec** un redéploiement.
- [ ] Ajouter une alerte sur le taux de `410`.
- [ ] Migrer, si nécessaire, les pièces historiques du disque `local` vers le
      nouveau disque (script ponctuel : lire `attachment_disk`, copier, mettre à
      jour la colonne). **Non écrit** : sans inventaire des fichiers réellement
      présents, un tel script ferait plus de dégâts que de bien.

Tant que les deux premières cases ne sont pas cochées, **R1 reste ouvert**.
