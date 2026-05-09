/**
 * paymentApi.js — Intégration Stripe via le backend Symfony.
 *
 * Flux de paiement (rappel) :
 *   1. createPaymentIntent() → backend crée le PaymentIntent, retourne clientSecret
 *   2. Stripe.js (côté client) traite la carte avec le clientSecret
 *   3. confirmPayment()     → backend vérifie le statut auprès de Stripe
 *
 * Le clientSecret est un jeton temporaire qui permet à Stripe.js de
 * finaliser le paiement sans exposer la clé secrète Stripe au frontend.
 */
import client from './client';

/**
 * Crée un PaymentIntent côté backend et retourne le clientSecret.
 *
 * @param {Array} items - Articles du panier :
 *   [{ id, name, price, quantity }, ...]
 *
 * Retourne : { clientSecret, publishableKey, amount, amountFormatted }
 */
export const createPaymentIntent = (items) =>
  client.post('/api/payment/create-intent', { items });

/**
 * Vérifie côté backend que le paiement a bien été confirmé par Stripe.
 *
 * @param {string} paymentIntentId - L'ID du PaymentIntent (pi_xxx)
 *   retourné par Stripe.js après confirmation de la carte.
 *
 * Retourne : { success: true, status: "succeeded" } ou { success: false, status: "..." }
 */
export const confirmPayment = (paymentIntentId) =>
  client.post('/api/payment/confirm', { paymentIntentId });
