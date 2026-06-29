# Edge License Keys — RS256

## Génération

```bash
# Clé privée (Cloud uniquement — NE PAS partager)
openssl genrsa -out edge_license_private.pem 2048

# Clé publique (embarquée dans les Edge nodes — safe)
openssl rsa -in edge_license_private.pem -pubout -out edge_license_public.pem
```

## Configuration

### Cloud (`api/.env`)
```env
EDGE_LICENSE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nMIIE...\n-----END PRIVATE KEY-----"
EDGE_LICENSE_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\nMIIB...\n-----END PUBLIC KEY-----"
```

> Remplacer les sauts de ligne par `\n` dans la valeur `.env`

### Edge node (`edge/.env`)
```env
# Soit par variable :
EDGE_LICENSE_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\nMIIB...\n-----END PUBLIC KEY-----"

# Soit par fichier monté :
LICENSE_PUBLIC_KEY_PATH=/var/leopardo/license.pub
```

## Sécurité

| Clé | Visibilité | Usage |
|-----|-----------|-------|
| Privée | Cloud uniquement 🔒 | Signer les licences JWT |
| Publique | Edge + Cloud ✅ | Vérifier les licences |

- La clé **privée** ne doit JAMAIS quitter le serveur Cloud
- La clé **publique** est safe à distribuer (elle ne permet que la vérification)
- Rotation recommandée : tous les 12 mois
- En cas de compromission : révoquer toutes les licences (`EdgeLicense::query()->update(['validation_status' => 'revoked'])`) + régénérer

## Clé publique de production

La clé publique actuelle est disponible via l'API Cloud :
```bash
curl https://api.leopardo.app/edge/license-public-key
```

Elle est aussi téléchargée automatiquement par `install.sh` lors de l'installation Edge.
