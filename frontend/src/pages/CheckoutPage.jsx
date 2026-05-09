/**
 * CheckoutPage — Page de paiement avec Stripe Elements.
 *
 * ════════════════════════════════════════════════════════════════════════
 * FLUX COMPLET DE PAIEMENT STRIPE
 * ════════════════════════════════════════════════════════════════════════
 *
 * Le paiement Stripe se déroule en 3 étapes distinctes :
 *
 *   ÉTAPE 1 — Notre backend crée un PaymentIntent (cette page, au montage)
 *     → On appelle POST /api/payment/create-intent avec les articles du panier
 *     → Stripe génère un objet "PaymentIntent" et retourne un "clientSecret"
 *     → Notre backend renvoie ce clientSecret au frontend
 *
 *   ÉTAPE 2 — Le frontend collecte les données de carte (ce formulaire)
 *     → Stripe.js / PaymentElement affiche le formulaire de carte
 *     → Quand l'utilisateur soumet, stripe.confirmPayment() envoie les données
 *       de carte DIRECTEMENT aux serveurs Stripe (pas via notre backend)
 *     → Si le paiement est accepté, Stripe redirige vers /payment/success
 *
 *   ÉTAPE 3 — Notre backend vérifie le résultat (PaymentSuccessPage)
 *     → On interroge Stripe avec le paymentIntentId pour confirmer le statut
 *     → C'est la seule source de vérité fiable
 *
 * ════════════════════════════════════════════════════════════════════════
 * QU'EST-CE QU'UN PAYMENTINTENT ?
 * ════════════════════════════════════════════════════════════════════════
 *
 * Un PaymentIntent est un objet Stripe qui représente l'intention de
 * collecter un paiement. Il est créé côté serveur avec le montant et la
 * devise, et suit le cycle de vie du paiement :
 *   requires_payment_method → requires_confirmation → processing → succeeded
 *
 * LE CLIENTSECRET : c'est un jeton unique et temporaire associé au
 * PaymentIntent. Il permet à Stripe.js (côté client) de :
 *   1. Relier le formulaire de carte au bon PaymentIntent
 *   2. Confirmer le paiement sans exposer la clé secrète Stripe
 *
 * Le clientSecret peut être partagé avec le frontend : il ne permet QUE
 * de confirmer CE paiement précis, et expire à la fin du PaymentIntent.
 *
 * ════════════════════════════════════════════════════════════════════════
 * POURQUOI LES DONNÉES DE CARTE NE PASSENT PAS PAR NOTRE SERVEUR (PCI DSS)
 * ════════════════════════════════════════════════════════════════════════
 *
 * La norme PCI DSS (Payment Card Industry Data Security Standard) impose
 * des exigences très strictes à tout serveur qui "touche" des données de
 * carte bancaire (numéro, CVV, date d'expiration).
 *
 * En utilisant Stripe.js + PaymentElement :
 *   - Les champs de carte sont des iframes hébergées sur stripe.com
 *   - Les données de carte transitent DIRECTEMENT du navigateur vers Stripe
 *   - Notre serveur ne voit jamais le numéro de carte → PCI simplifié (SAQ A)
 *
 * Si on géraient nous-mêmes les champs carte et qu'on envoyait les données
 * à notre backend, on deviendrait responsables de les stocker et sécuriser,
 * ce qui nécessite un audit PCI DSS complet (très coûteux).
 *
 * ════════════════════════════════════════════════════════════════════════
 * PAYMENTELEMENT vs CARDELEMENT
 * ════════════════════════════════════════════════════════════════════════
 *
 * CardElement (ancienne API) :
 *   - Affiche uniquement les champs carte classiques (numéro, CVV, date)
 *   - Supporte une seule méthode de paiement : la carte bancaire
 *   - Moins de maintenance : pas de gestion des nouvelles méthodes
 *
 * PaymentElement (nouvelle API recommandée) :
 *   - Affiche dynamiquement les méthodes de paiement disponibles selon
 *     la devise, le pays et la configuration du compte Stripe
 *   - Gère automatiquement : carte, Apple Pay, Google Pay, SEPA, iDEAL...
 *   - Adapte son interface selon le contexte (mobile, desktop)
 *   - Gère nativement l'authentification forte (3D Secure / SCA)
 *   → On utilise PaymentElement car il est plus flexible et maintenu
 */
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { loadStripe } from '@stripe/stripe-js';
import { Elements, PaymentElement, useStripe, useElements } from '@stripe/react-stripe-js';
import { createPaymentIntent } from '../api/paymentApi';

// ══════════════════════════════════════════════════════════════════════════
// COMPOSANT CheckoutForm — Formulaire de paiement Stripe
// ══════════════════════════════════════════════════════════════════════════
//
// Ce composant est VOLONTAIREMENT séparé de CheckoutPage.
// Raison : useStripe() et useElements() ne fonctionnent qu'à l'intérieur
// d'un composant enfant de <Elements>. On ne peut pas les appeler dans le
// même composant qui rend <Elements>.

function CheckoutForm({ amountFormatted }) {
  // useStripe() donne accès à l'instance Stripe.js (chargée via loadStripe)
  const stripe   = useStripe();
  // useElements() donne accès aux éléments de formulaire Stripe montés dans le DOM
  const elements = useElements();

  const [errorMessage, setErrorMessage] = useState('');
  // submitting = true pendant que stripe.confirmPayment() traite la requête
  const [submitting,   setSubmitting]   = useState(false);

  /**
   * Gestion de la soumission du paiement.
   *
   * stripe.confirmPayment() fait deux choses :
   *   1. Collecte les données de carte depuis les éléments Stripe (iframes)
   *   2. Les envoie DIRECTEMENT à l'API Stripe (sans passer par notre serveur)
   *
   * En cas de succès, Stripe redirige automatiquement le navigateur vers
   * return_url avec les paramètres ?payment_intent=pi_xxx&payment_intent_client_secret=...
   *
   * Si on revient dans ce handler après l'await, c'est forcément une erreur
   * (une redirection réussie quitte la page, donc le code suivant ne s'exécute pas).
   */
  const handleSubmit = async (e) => {
    e.preventDefault();

    // Stripe.js n'est pas encore chargé (situation rare au démarrage)
    if (!stripe || !elements) return;

    setSubmitting(true);
    setErrorMessage('');

    const result = await stripe.confirmPayment({
      // elements contient le PaymentElement avec les données saisies par l'utilisateur
      elements,
      confirmParams: {
        // URL de redirection après paiement réussi.
        // Stripe y ajoute automatiquement ?payment_intent=pi_xxx
        // que PaymentSuccessPage utilise pour vérifier le paiement côté backend.
        return_url: window.location.origin + '/payment/success',
      },
    });

    // On n'arrive ici QUE si le paiement a échoué.
    // En cas de succès, Stripe a déjà redirigé le navigateur (ligne ci-dessus).
    if (result.error) {
      // Exemples d'erreurs : carte refusée, fonds insuffisants, CVC incorrect...
      setErrorMessage(result.error.message ?? 'Une erreur est survenue lors du paiement.');
    }

    setSubmitting(false);
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/*
       * <PaymentElement /> est le formulaire de carte officiel Stripe.
       * Il rend des iframes sécurisées hébergées sur stripe.com :
       * les données saisies ne transitent jamais par notre serveur.
       *
       * Il adapte automatiquement son contenu selon :
       *   - Les méthodes de paiement activées dans le Stripe Dashboard
       *   - Le pays et la devise du PaymentIntent
       *   - Le type d'appareil (mobile / desktop)
       */}
      <PaymentElement />

      {/* Message d'erreur Stripe (carte refusée, CVC incorrect...) */}
      {errorMessage && (
        <p className="text-red-500 text-sm bg-red-50 border border-red-200 rounded-lg px-4 py-3">
          {errorMessage}
        </p>
      )}

      <button
        type="submit"
        disabled={!stripe || submitting}
        className="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed"
      >
        {submitting ? 'Traitement en cours…' : `Payer ${amountFormatted}`}
      </button>
    </form>
  );
}

// ══════════════════════════════════════════════════════════════════════════
// PAGE CheckoutPage — Orchestration du paiement
// ══════════════════════════════════════════════════════════════════════════

export default function CheckoutPage() {
  const navigate = useNavigate();

  // Panier lu depuis localStorage — source de vérité pour le résumé et le montant
  const [cart,           setCart]           = useState([]);
  // clientSecret reçu du backend, passé à <Elements> pour lier le formulaire au PaymentIntent
  const [clientSecret,   setClientSecret]   = useState('');
  // stripePromise : promesse retournée par loadStripe(), passée à <Elements>
  const [stripePromise,  setStripePromise]  = useState(null);
  // Montant formaté ("29,90 €") retourné par le backend pour l'affichage
  const [amountFormatted, setAmountFormatted] = useState('');
  // loading = true pendant l'appel à createPaymentIntent
  const [loading,        setLoading]        = useState(true);
  const [error,          setError]          = useState('');

  useEffect(() => {
    const raw      = localStorage.getItem('cart');
    const cartData = raw ? JSON.parse(raw) : [];

    // Panier vide → redirection vers /cart
    if (cartData.length === 0) {
      navigate('/cart');
      return;
    }

    setCart(cartData);

    // ── Étape 1 : demander au backend de créer le PaymentIntent ─────────
    // On envoie les articles du panier (id, name, price, quantity).
    // Le backend calcule le montant total en centimes, crée le PaymentIntent
    // chez Stripe et nous retourne le clientSecret + la clé publique.
    createPaymentIntent(cartData)
      .then(({ data }) => {
        // clientSecret : lien entre notre formulaire et le PaymentIntent Stripe
        setClientSecret(data.clientSecret);
        setAmountFormatted(data.amountFormatted);

        // loadStripe(publishableKey) charge la librairie Stripe.js de manière asynchrone.
        // La clé publiable (pk_test_...) identifie notre compte Stripe côté client.
        // Elle peut être exposée publiquement — contrairement à la clé secrète (sk_test_...).
        setStripePromise(loadStripe(data.publishableKey));
      })
      .catch(() => setError('Impossible d\'initialiser le paiement. Vérifiez votre connexion.'))
      .finally(() => setLoading(false));
  }, []);

  // Calcul du total pour l'affichage du résumé (redondant avec amountFormatted mais local)
  const total = cart.reduce((sum, item) => sum + parseFloat(item.price) * item.quantity, 0);

  // ── États de chargement et d'erreur ──────────────────────────────────────

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-3" />
          <p className="text-gray-500">Préparation du paiement…</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <p className="text-red-500 mb-4">{error}</p>
          <button onClick={() => navigate('/cart')} className="text-indigo-600 hover:underline text-sm">
            ← Retour au panier
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white shadow-sm px-6 py-4 flex items-center gap-4">
        <button onClick={() => navigate(-1)} className="text-indigo-600 hover:underline text-sm">
          ← Retour au catalogue
        </button>
        <h1 className="text-lg font-bold text-indigo-700">Paiement sécurisé 🔒</h1>
      </header>

      <main className="max-w-lg mx-auto px-4 py-10 space-y-6">

        {/* ── Résumé de la commande ─────────────────────────────────── */}
        <div className="bg-white rounded-2xl shadow p-6">
          <h2 className="font-semibold text-gray-700 mb-4">Résumé de la commande</h2>
          <ul className="divide-y divide-gray-100">
            {cart.map((item) => (
              <li key={item.id} className="flex justify-between py-2 text-sm text-gray-600">
                <span>{item.name} × {item.quantity}</span>
                <span className="font-medium">{(parseFloat(item.price) * item.quantity).toFixed(2)} €</span>
              </li>
            ))}
          </ul>
          <div className="flex justify-between pt-4 mt-2 border-t border-gray-100 font-semibold text-gray-800">
            <span>Total</span>
            <span className="text-indigo-700">{total.toFixed(2)} €</span>
          </div>
        </div>

        {/* ── Encadré carte de test ─────────────────────────────────── */}
        {/*
         * En mode test Stripe, aucune vraie carte n'est débitée.
         * Le numéro 4242 4242 4242 4242 simule toujours un paiement accepté.
         * D'autres numéros Stripe permettent de simuler des refus, 3DS, etc.
         */}
        <div className="bg-blue-50 border border-blue-200 rounded-xl px-5 py-4 text-sm text-blue-700">
          <p className="font-semibold mb-1">Mode test Stripe</p>
          <p>💳 Carte de test : <strong>4242 4242 4242 4242</strong></p>
          <p className="mt-1">Date : <strong>12/26</strong> — CVC : <strong>424</strong> — CP : n'importe lequel</p>
        </div>

        {/* ── Formulaire Stripe Elements ────────────────────────────── */}
        <div className="bg-white rounded-2xl shadow p-6">
          {/*
           * <Elements> est le Provider React de Stripe.
           * Il injecte le contexte Stripe dans tous ses composants enfants,
           * ce qui permet à useStripe() et useElements() de fonctionner.
           *
           * stripe  : la promesse Stripe.js (chargement asynchrone de la lib)
           * options : { clientSecret } lie le formulaire au PaymentIntent créé
           *           côté backend — sans ça, Stripe ne saurait pas quel
           *           paiement confirmer.
           */}
          <Elements stripe={stripePromise} options={{ clientSecret }}>
            <CheckoutForm amountFormatted={amountFormatted} />
          </Elements>
        </div>

      </main>
    </div>
  );
}
