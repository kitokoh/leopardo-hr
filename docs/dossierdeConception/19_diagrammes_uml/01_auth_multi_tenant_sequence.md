# Diagramme de se9quence — Authentification Multi-Tenant

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

    alt Utilisateur NON trouve9
        UL-->>AC: NULL
        AC-->>App: 401 INVALID_CREDENTIALS
    else Utilisateur trouve9
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

                    alt Abonnement expire9 + grace_days de9passe9
                        CSM-->>AC: subscription expired
                        AC-->>App: 403 SUBSCRIPTION_EXPIRED
                    else Abonnement valide
                        CSM-->>AC: subscription OK

                        AC->>EM: Create Sanctum Token<br/>(personal_access_token)
                        EM-->>AC: {token, abilities}

                        AC->>ED: UPSERT employee_devices<br/>{fcm_token, platform, device_name}
                        ED-->>AC: device enregistre9

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

| E9tape | Interaction | De9tail |
|--------|-------------|---------|
| 1 | **Reque2te de connexion** | L'application mobile envoie les identifiants (email, mot de passe) avec les informations de l'appareil (nom, FCM token). |
| 2-3 | **Recherche dans user_lookups (public schema)** | Le controlleur interroge la table publique `user_lookups` pour retrouver le schel0me tenant associ03 3 l'email. Si l'utilisateur n'existe pas, une erreur 401 est renvoy03e imm03diatement. |
| 4-5 | **Changement de contexte tenant** | Le `search_path` PostgreSQL est bascul03 vers le sch03ma de l'entreprise pour toutes les requ00etes suivantes. |
| 6-8 | **V03rification du mot de passe** | Le mot de passe est v03rifi03 dans la table `employees` du sch03ma tenant. En cas d'03chec, le compteur `failed_attempts` est incr03ment03. Au bout de 5 03checs, le compte est bloqu03 pendant 15 minutes. |
| 9 | **V03rification du statut employ03** | Un employ03 ou une entreprise suspendus bloquent toute connexion (403). |
| 10 | **V03rification de l'abonnement** | Le middleware `CheckSubscription` compare la date de fin d'abonnement avec la date du jour, en tenant compte de la p03riode de gr02ce d03finie dans le plan. |
| 11 | **Cr03ation du token Sanctum** | Un `personal_access_token` Sanctum est g03n03r03 pour les requ00etes authentifi03es ult03rieures. |
| 12 | **Enregistrement de l'appareil** | Le token FCM est upsert03 dans `employee_devices` pour les notifications push. |
| 13-15 | **R03ponse et navigation** | L'application stocke le token de mani03re s03curis03e et navigue vers l'03cran d'accueil. |
