<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PaymentController — Intégration Stripe pour le traitement des paiements.
 *
 * ════════════════════════════════════════════════════════════════════════
 * COMPRENDRE LE FLUX DE PAIEMENT STRIPE (important pour l'entretien)
 * ════════════════════════════════════════════════════════════════════════
 *
 * Stripe utilise le modèle "PaymentIntent" qui représente le cycle de vie
 * complet d'un paiement, de la création jusqu'à la confirmation.
 *
 * Le flux complet se déroule en 3 étapes :
 *
 *   1. BACKEND crée le PaymentIntent (cette route : POST /api/payment/create-intent)
 *      → Stripe génère un "clientSecret", jeton unique et temporaire
 *      → Le backend renvoie ce clientSecret au frontend (jamais la clé secrète !)
 *
 *   2. FRONTEND (Stripe.js / Stripe Elements) traite la carte bancaire
 *      → Stripe.js envoie les données sensibles (numéro de carte, CVV, etc.)
 *        directement aux serveurs Stripe, sans jamais transiter par notre backend
 *      → Cela garantit la conformité PCI-DSS : notre serveur ne touche jamais
 *        les données brutes de carte bancaire
 *      → Stripe confirme ou refuse le paiement via le clientSecret
 *
 *   3. BACKEND vérifie le résultat (cette route : POST /api/payment/confirm)
 *      → On ne fait pas confiance au frontend pour confirmer un paiement réussi
 *      → On interroge directement l'API Stripe avec notre clé secrète
 *      → Seul un status "succeeded" retourné par Stripe est fiable
 *
 * ════════════════════════════════════════════════════════════════════════
 * POURQUOI TRAVAILLER EN CENTIMES ?
 * ════════════════════════════════════════════════════════════════════════
 *
 * Stripe exige que tous les montants soient exprimés en plus petite unité
 * monétaire de la devise (pour EUR : les centimes).
 *
 * Raison technique : éviter les erreurs d'arrondi des nombres flottants.
 * En informatique, 29.90 en float peut valoir 29.899999... ou 29.900001...
 * Les centimes sont des entiers (2990), ce qui garantit un calcul exact.
 *
 * Routes exposées :
 *   POST /api/payment/create-intent  → crée un PaymentIntent Stripe
 *   POST /api/payment/confirm        → vérifie qu'un paiement a réussi
 */
#[Route('/api/payment')]
class PaymentController extends AbstractController
{
    // ─────────────────────────────────────────────────────────
    // POST /api/payment/create-intent
    // ─────────────────────────────────────────────────────────

    /**
     * Crée un PaymentIntent Stripe et retourne le clientSecret au frontend.
     *
     * ────────────────────────────────────────────────────────
     * QU'EST-CE QU'UN PAYMENTINTENT ?
     * ────────────────────────────────────────────────────────
     * Un PaymentIntent est un objet Stripe qui représente l'intention
     * de collecter un paiement. Il suit le cycle de vie du paiement :
     *   created → processing → succeeded (ou requires_action, canceled...)
     *
     * On l'utilise plutôt que l'ancien "Charge" car il gère automatiquement :
     *   - L'authentification forte (3D Secure / SCA, obligatoire en Europe)
     *   - Les différentes méthodes de paiement (carte, virement, etc.)
     *   - Les retry en cas d'échec temporaire
     *
     * ────────────────────────────────────────────────────────
     * QU'EST-CE QUE LE CLIENTSECRET ?
     * ────────────────────────────────────────────────────────
     * Le clientSecret est un jeton unique généré par Stripe pour chaque
     * PaymentIntent. Il permet au frontend (via Stripe.js) de confirmer
     * le paiement côté client SANS avoir besoin de la clé secrète Stripe.
     *
     * Seul le frontend en a besoin : il est passé à stripe.confirmPayment()
     * pour que Stripe.js puisse finaliser le paiement avec les données de carte.
     *
     * Le clientSecret NE DOIT PAS être stocké côté backend ni loggué —
     * il suffit à confirmer un paiement si quelqu'un l'intercepte.
     *
     * Body JSON attendu :
     *   { "items": [{ "id": 1, "name": "Produit X", "price": 29.90, "quantity": 2 }] }
     *
     * Réponse (200) :
     *   {
     *     "clientSecret": "pi_xxx_secret_yyy",
     *     "publishableKey": "pk_test_...",
     *     "amount": 5980,
     *     "amountFormatted": "59,80 €"
     *   }
     *
     * Réponse (400) : { "error": "..." }  si body invalide ou montant nul
     * Réponse (500) : { "error": "..." }  si erreur Stripe
     */
    #[Route('/create-intent', name: 'api_payment_create_intent', methods: ['POST'])]
    public function createIntent(Request $request): JsonResponse
    {
        // Décodage du body JSON envoyé par le frontend
        $data  = json_decode($request->getContent(), true) ?? [];
        $items = $data['items'] ?? [];

        // Validation : on vérifie qu'on a bien un tableau d'articles non vide
        if (empty($items) || !is_array($items)) {
            return $this->json(
                ['error' => 'La liste des articles est manquante ou invalide'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        // ── Calcul du montant total en euros (valeur décimale) ──────────────
        // On parcourt chaque article et on multiplie prix × quantité.
        // On utilise (float) et (int) pour s'assurer des types corrects.
        $total = 0.0;

        foreach ($items as $item) {
            $price    = (float) ($item['price']    ?? 0);
            $quantity = (int)   ($item['quantity'] ?? 0);
            $total   += $price * $quantity;
        }

        // ── Conversion en centimes (unité requise par Stripe) ───────────────
        // On multiplie par 100 et on arrondit à l'entier le plus proche.
        // Exemple : 29.90 € × 100 = 2990.0 → (int)round() = 2990 centimes
        // Sans round(), 29.90 * 100 pourrait donner 2989.9999... en float.
        $totalCentimes = (int) round($total * 100);

        // Validation : le montant doit être strictement positif
        // (Stripe refuserait un paiement à 0 centime)
        if ($totalCentimes <= 0) {
            return $this->json(
                ['error' => 'Le montant total doit être supérieur à zéro'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        try {
            // ── Configuration de la clé secrète Stripe ──────────────────────
            // La clé secrète (sk_test_... ou sk_live_...) est strictement
            // côté serveur. Elle ne doit JAMAIS apparaître dans le frontend
            // ni dans les logs. Elle est lue depuis la variable d'environnement.
            \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

            // ── Création du PaymentIntent via l'API Stripe ──────────────────
            // Stripe enregistre cette intention de paiement dans ses serveurs
            // et nous retourne un objet avec un identifiant (pi_xxx) et
            // le fameux clientSecret.
            $intent = \Stripe\PaymentIntent::create([
                // Montant en centimes — obligatoire, entier
                'amount'   => $totalCentimes,

                // Devise ISO 4217 en minuscules
                'currency' => 'eur',

                // automatic_payment_methods : délègue à Stripe Dashboard
                // la gestion des méthodes de paiement disponibles (carte,
                // PayPal, virement SEPA...). Simplifie la configuration.
                'automatic_payment_methods' => ['enabled' => true],

                // metadata : champ libre pour stocker des informations annexes.
                // Ici on stocke le détail du panier pour pouvoir le retrouver
                // dans le Dashboard Stripe ou dans les webhooks.
                'metadata' => [
                    'items' => json_encode($items),
                ],
            ]);

            // ── Formatage du montant pour l'affichage côté frontend ─────────
            // number_format(29.9, 2, ',', ' ') → "29,90"
            // On ajoute "€" manuellement pour le format français.
            $amountFormatted = number_format($total, 2, ',', ' ') . ' €';

            // ── Réponse au frontend ─────────────────────────────────────────
            // On renvoie :
            //   - clientSecret    : utilisé par Stripe.js pour confirmer le paiement
            //   - publishableKey  : clé publique pour initialiser Stripe.js côté client
            //   - amount          : montant en centimes (pour affichage ou vérification)
            //   - amountFormatted : montant formaté pour l'UI ("29,90 €")
            return $this->json([
                'clientSecret'    => $intent->client_secret,
                'publishableKey'  => $_ENV['STRIPE_PUBLISHABLE_KEY'],
                'amount'          => $totalCentimes,
                'amountFormatted' => $amountFormatted,
            ]);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Erreur retournée par l'API Stripe (clé invalide, paramètre manquant,
            // problème réseau avec les serveurs Stripe, etc.)
            return $this->json(
                ['error' => $e->getMessage()],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ─────────────────────────────────────────────────────────
    // POST /api/payment/confirm
    // ─────────────────────────────────────────────────────────

    /**
     * Vérifie côté backend que le paiement a bien été confirmé par Stripe.
     *
     * ────────────────────────────────────────────────────────
     * POURQUOI VÉRIFIER CÔTÉ BACKEND ? (sécurité critique)
     * ────────────────────────────────────────────────────────
     * Après que Stripe.js a traité la carte, le frontend reçoit une confirmation.
     * MAIS on ne peut pas faire confiance au frontend pour déclarer un paiement
     * réussi, car :
     *
     *   1. Un utilisateur malveillant peut modifier la réponse JavaScript
     *      et prétendre que le paiement a réussi alors qu'il a échoué.
     *
     *   2. Un bug frontend peut envoyer un faux signal de succès.
     *
     * La seule source de vérité est l'API Stripe interrogée avec notre
     * clé secrète côté serveur. Le statut "succeeded" retourné directement
     * par Stripe est infalsifiable par le client.
     *
     * Ce pattern est essentiel en e-commerce : on ne valide la commande
     * (stock, email de confirmation, accès au produit) qu'après vérification
     * backend du statut Stripe.
     *
     * Body JSON attendu :
     *   { "paymentIntentId": "pi_3NxYyZ2eZvKYlo2C..." }
     *
     * Réponse (200) : { "success": true,  "status": "succeeded" }
     * Réponse (400) : { "success": false, "status": "requires_payment_method" }
     *                  (ou tout autre statut non-succeeded)
     * Réponse (500) : { "error": "..." } si erreur Stripe
     */
    #[Route('/confirm', name: 'api_payment_confirm', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        // Décodage du body JSON
        $data            = json_decode($request->getContent(), true) ?? [];
        $paymentIntentId = $data['paymentIntentId'] ?? null;

        // Validation : l'ID du PaymentIntent est obligatoire
        if (empty($paymentIntentId)) {
            return $this->json(
                ['error' => 'Le paymentIntentId est requis'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        try {
            // Configuration de la clé secrète (même que pour create-intent)
            \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

            // ── Récupération du PaymentIntent depuis l'API Stripe ───────────
            // retrieve() interroge directement les serveurs Stripe avec l'ID.
            // Le résultat est l'état actuel du paiement, infalsifiable.
            $intent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

            // ── Vérification du statut ──────────────────────────────────────
            // "succeeded" est le seul statut qui garantit un paiement réussi.
            //
            // Autres statuts possibles :
            //   - "requires_payment_method" : paiement échoué, carte refusée
            //   - "requires_confirmation"   : pas encore confirmé par le client
            //   - "requires_action"         : 3D Secure requis (authentification)
            //   - "processing"              : en cours de traitement (virements)
            //   - "canceled"                : PaymentIntent annulé
            if ($intent->status === 'succeeded') {
                // Paiement confirmé — ici, en production, on déclencherait :
                // la mise à jour du stock, l'envoi d'un email de confirmation,
                // la création d'une commande en base de données, etc.
                return $this->json([
                    'success' => true,
                    'status'  => 'succeeded',
                ]);
            }

            // Paiement non abouti — on retourne le statut exact pour que
            // le frontend puisse afficher un message adapté à l'utilisateur.
            return $this->json(
                [
                    'success' => false,
                    'status'  => $intent->status,
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );

        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Erreur API Stripe : ID inexistant, clé invalide, etc.
            return $this->json(
                ['error' => $e->getMessage()],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
