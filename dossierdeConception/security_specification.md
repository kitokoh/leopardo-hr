# SECURITY SPECIFICATION – LEOPARDO RH

## 1. Authentification
- JWT sécurisé
- Expiration token
- Refresh token

## 2. Protection brute force
- Limite login (5 tentatives)
- Blocage temporaire

## 3. Encryption
- Mots de passe → bcrypt
- Données sensibles → encryptées

## 4. HTTPS obligatoire

## 5. RBAC strict
- Vérification permissions backend

## 6. Audit Logs
Tracer :
- login
- actions critiques
- modifications données

## 7. Validation données
- Backend obligatoire
- Sanitization inputs

## 8. API Security
- Rate limiting
- Token obligatoire

## 9. RGPD / Data privacy
- Suppression compte
- Export données

## 10. Backup
- Backup quotidien DB

## 11. Accès admin
- IP whitelist (optionnel)

## 12. Mobile sécurité
- Token sécurisé
- Pas de stockage sensible local
