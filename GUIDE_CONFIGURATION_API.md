# Guide de Configuration des Clés API (Render)

Ce guide détaille étape par étape comment récupérer les clés nécessaires pour les variables d'environnement de l'API sur Render.

## 1. Stripe (Paiement et Facturation)

**Pourquoi ?** Pour permettre à vos clients de payer leur abonnement et synchroniser automatiquement les statuts (actif, impayé, annulé).

1. Connectez-vous à [dashboard.stripe.com](https://dashboard.stripe.com).
2. Vérifiez que le mode **"Test"** n'est pas activé en haut à droite (pour utiliser les vrais paiements).
3. Allez dans **Développeurs > Clés API**.
   - Copiez la **Clé publique** (`pk_live_...`) ➔ Variable : `STRIPE_KEY`
   - Copiez la **Clé secrète** (`sk_live_...`) ➔ Variable : `STRIPE_SECRET`
4. Allez dans **Catalogue de produits**.
   - Créez vos 3 plans (Starter, Business, Enterprise) avec leurs prix.
   - Pour chaque prix créé, récupérez l'identifiant de l'API (ex: `price_1Nxy...`) ➔ Variables : `STRIPE_PRICE_STARTER`, `STRIPE_PRICE_BUSINESS`, `STRIPE_PRICE_ENTERPRISE`.
5. Allez dans **Développeurs > Webhooks** et ajoutez l'endpoint : `https://gestionemployerbackend.onrender.com/api/v1/webhooks/stripe`
   - Écoutez les événements : `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted`, `charge.refunded`.
   - Cliquez sur **Révéler** sous "Secret de signature" et copiez la clé (`whsec_...`) ➔ Variable : `STRIPE_WEBHOOK_SECRET`

---

## 2. Google Connexion (OAuth)

**Pourquoi ?** Pour permettre aux employés et managers de se connecter en un clic avec "Se connecter avec Google".

1. Allez sur la [Google Cloud Console](https://console.cloud.google.com/).
2. Dans **API et services > Identifiants**, cliquez sur **Créer des identifiants > ID client OAuth**.
3. Type : **Application Web**.
4. Origines JavaScript autorisées : `https://gestionemployerbackend.onrender.com`
5. URI de redirection autorisés : `https://gestionemployerbackend.onrender.com/api/v1/auth/google/callback`
6. Validez. La popup vous donnera :
   - L'ID Client ➔ Variable : `GOOGLE_CLIENT_ID`
   - Le code secret ➔ Variable : `GOOGLE_CLIENT_SECRET`
7. Ajoutez aussi sur Render ➔ Variable : `GOOGLE_REDIRECT_URL` avec la valeur `https://gestionemployerbackend.onrender.com/api/v1/auth/google/callback`.

---

## 3. Firebase (Notifications Push Mobile)

**Pourquoi ?** Pour que les applications mobiles reçoivent les alertes en temps réel (pointage, congés, annonces).

1. Allez sur la [Firebase Console](https://console.firebase.google.com/).
2. Ouvrez les **Paramètres du projet** (⚙️).
3. Dans l'onglet **Général**, récupérez l'"ID du projet" ➔ Variable : `FIREBASE_PROJECT_ID`.
4. Allez dans l'onglet **Comptes de service** et cliquez sur **Générer une nouvelle clé privée**.
5. Ouvrez le fichier JSON téléchargé, **supprimez tous les retours à la ligne** (utilisez un outil de type "JSON Minifier" en ligne pour mettre le texte sur une seule ligne).
6. Copiez ce texte JSON d'une ligne ➔ Variable : `FIREBASE_SERVICE_ACCOUNT_JSON`.
