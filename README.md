# Stock&Shop

Mini-application de catalogue produits et gestion de stock avec paiement Stripe (mode test).
Symfony PHP + React.js + MySQL.

## Stack technique

| Couche | Technologies |
|--------|-------------|
| Backend | Symfony 8, PHP 8.2, Doctrine ORM, Stripe PHP SDK |
| Base de données | MySQL 8.0 |
| Frontend | React.js, Vite, Tailwind CSS, Axios, Stripe.js |
| Outils | Docker Compose, Git, PHPUnit |

## Fonctionnalités

- Catalogue avec recherche et filtres par disponibilité
- Fiche produit détaillée avec image
- Panier géré en localStorage
- Paiement Stripe en mode test (PaymentElement)
- Page de confirmation de paiement avec vérification backend
- Espace admin avec CRUD produits complet
- Indicateurs de stock (En stock / Stock faible / Rupture)

## Lancer le projet

```bash
# 1. Démarrer la base de données MySQL
docker-compose up -d

# 2. Backend Symfony
cd backend
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
symfony serve --port=8001

# 3. Frontend React (dans un autre terminal)
cd frontend
npm install
npm run dev
```

L'application est accessible sur **http://localhost:5173**
L'API tourne sur **http://127.0.0.1:8001**

## Configuration Stripe

Dans `backend/.env` :

```
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
```

## Compte admin

```
Email    : admin@stockshop.test
Mot de passe : password
```

Accès : http://localhost:5173/login → redirige vers `/admin`

## Cartes de test Stripe

| Carte | Résultat |
|-------|---------|
| 4242 4242 4242 4242 | Paiement réussi ✅ |
| 4000 0000 0000 0002 | Paiement refusé ❌ |

Date : n'importe quelle date future — CVC : 3 chiffres quelconques

## Endpoints API

```
POST   /api/login                  — Authentification admin
GET    /api/categories             — Liste des catégories

GET    /api/products               — Liste des produits (filtres: ?search=, ?availability=)
GET    /api/products/{id}          — Détail d'un produit
POST   /api/products               — Créer un produit (auth requise)
PUT    /api/products/{id}          — Modifier un produit (auth requise)
DELETE /api/products/{id}          — Supprimer un produit (auth requise)

POST   /api/payment/create-intent  — Créer un PaymentIntent Stripe
POST   /api/payment/confirm        — Vérifier le statut d'un paiement
```

## Tests

```bash
cd backend
php bin/phpunit --testdox
```

Les tests fonctionnels créent et recréent le schéma `stockshop_test` à chaque exécution pour garantir l'isolation.

## Architecture du paiement Stripe

```
Frontend                     Stripe                  Backend
   │                            │                       │
   │── POST /create-intent ─────────────────────────────►│
   │◄── clientSecret ───────────────────────────────────│
   │                            │                       │
   │── confirmPayment() ────────►│                       │
   │◄── redirect /payment/success ─────────────────────│
   │                            │                       │
   │── POST /confirm ───────────────────────────────────►│
   │◄── { success: true } ──────────────────────────────│
```

Les données de carte transitent directement du navigateur vers Stripe (PCI DSS SAQ A).
Notre serveur ne voit jamais le numéro de carte.

## Limites (projet portfolio)

- Paiement mode test uniquement (pas de production Stripe configurée)
- Commandes non sauvegardées en base de données
- Panier stocké en localStorage (perdu si on vide le navigateur)
- Authentification simplifiée (pas de JWT, pas de refresh token)

## Améliorations possibles

- Sauvegarder les commandes et le détail en base de données
- JWT avec refresh token pour l'authentification
- Pagination de la liste produits
- Tests end-to-end avec Cypress ou Playwright
- Webhooks Stripe pour une confirmation de paiement fiable côté serveur
- Passage en mode production Stripe
