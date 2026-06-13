# Diagramme de séquence — Authentification Multi-Tenant

```mermaid
sequenceDiagram
    autonumber

    participant App as Mobile App (Flutter)
    participant AC as AuthController
    participant UL as user_lookups (public)
    participant EM as employees (tenant schema)
    participant CSM as CheckSubscription<br/>Middleware
    participant ED as employee_devices (tenant)

    App->>AC: POST /auth/login<br/>{email, password, device_name, fcm_token}

    AC->>UL: SELECT * FROM user_lookups<br/>WHERE email = :email

    alt Utilisateur NON trouvé
        UL-->>AC: NULL
        AC-->>App: 401 INVALID_CREDENTIALS
    else Utilisateur trouvé
        UL-->>AC: {company_id, schema_name, employee_id}

        AC->>UL: SET search_path TO company_schema_name

        AC->>EM: SELECT password_hash FROM employees<br/>WHERE id = :employee_id

        alt Mot de passe INCORRECT
            EM-->>AC: password_hash
            AC->>EM: UPDATE employees SET failed_attempts = failed_attempts + 1

            alt failed_attempts >= 5
                AC->>EM: UPDATE employees SET blocked_until = NOW() + 15 min
                AC-->>App: 401 ACCOUNT_BLOCKED_15_MIN
            else failed_attempts < 5
                AC-->>App: 401 INVALID_CREDENTIALS
            end
        else Mot de passe CORRECT
            EM-->>AC: employee record

            AC->>EM: SELECT status FROM employees WHERE id = :id

            alt status = 'suspended'
                AC-->>App: 403 ACCOUNT_SUSPENDED
            else status actif
                AC->>CSM: Verifier statut company

                alt Company suspended
                    CSM-->>AC: company.status = 'suspended'
                    AC-->>App: 403 ACCOUNT_SUSPENDED
                else Company active
                    CSM->>CSM: Verifier subscription_end vs today()

                    alt Abonnement expiré + grace_days dépassé
                        CSM-->>AC: subscription expired
                        AC-->>App: 403 SUBSCRIPTION_EXPIRED
                    else Abonnement valide
                        CSM-->>AC: subscription OK

                        AC->>EM: Create Sanctum Token<br/>(personal_access_token)
                        EM-->>AC: {token, abilities}

                        AC->>ED: UPSERT employee_devices<br/>{fcm_token, platform, device_name}
                        ED-->>AC: device enregistré

                        AC-->>App: 200 {token, user_data, company_info}

                        App->>App: Stocker token dans<br/>flutter_secure_storage
                        App->>App: Naviguer vers HomeScreen
                    end
                end
            end
        end
    end
```

---

## Explication des interactions

| Étape | Interaction | Détail |
|--------|-------------|---------|
| 1 | **Requête de connexion** | L'application mobile envoie les identifiants (email, mot de passe) avec les informations de l'appareil (nom, FCM token). |
| 2-3 | **Recherche dans user_lookups (public schema)** | Le controlleur interroge la table publique `user_lookups` pour retrouver le schel0me tenant associé à l'email. Si l'utilisateur n'existe pas, une erreur 401 est renvoyée immédiatement. |
| 4-5 | **Changement de contexte tenant** | Le `search_path` PostgreSQL est basculé vers le schéma de l'entreprise pour toutes les requêtes suivantes. |
| 6-8 | **Vérification du mot de passe** | Le mot de passe est vérifié dans la table `employees` du schéma tenant. En cas d'échec, le compteur `failed_attempts` est incrémenté. Au bout de 5 échecs, le compte est bloqué pendant 15 minutes. |
| 9 | **Vérification du statut employé** | Un employé ou une entreprise suspendus bloquent toute connexion (403). |
| 10 | **Vérification de l'abonnement** | Le middleware `CheckSubscription` compare la date de fin d'abonnement avec la date du jour, en tenant compte de la période de grâce définie dans le plan. |
| 11 | **Création du token Sanctum** | Un `personal_access_token` Sanctum est généré pour les requêtes authentifiées ultérieures. |
| 12 | **Enregistrement de l'appareil** | Le token FCM est upserté dans `employee_devices` pour les notifications push. |
| 13-15 | **Réponse et navigation** | L'application stocke le token de manière sécurisée et navigue vers l'écran d'accueil. |
