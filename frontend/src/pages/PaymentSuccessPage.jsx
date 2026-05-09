/**
 * PaymentSuccessPage — Page de confirmation après paiement Stripe.
 *
 * ════════════════════════════════════════════════════════════════════════
 * POURQUOI VÉRIFIER LE PAIEMENT CÔTÉ BACKEND ET PAS UNIQUEMENT CÔTÉ FRONTEND ?
 * ════════════════════════════════════════════════════════════════════════
 *
 * Après stripe.confirmPayment(), Stripe redirige le navigateur vers cette
 * page avec des paramètres dans l'URL. On pourrait penser que "si on est
 * redirigé ici, c'est que le paiement a réussi". C'est FAUX pour 2 raisons :
 *
 *   1. MANIPULATION CÔTÉ CLIENT
 *      Un utilisateur malveillant peut naviguer directement vers cette URL
 *      (avec ou sans paramètres) pour déclencher une logique "paiement réussi"
 *      sans avoir payé. Si on ne vérifie que l'URL, il bypasse le paiement.
 *
 *   2. STATUTS INTERMÉDIAIRES
 *      Stripe peut rediriger vers return_url même quand le paiement n'est pas
 *      encore finalisé (ex: virement bancaire "processing", 3D Secure en attente).
 *      La seule source de vérité est le statut du PaymentIntent côté Stripe.
 *
 * La solution : appeler notre backend qui interroge l'API Stripe avec la
 * clé secrète et retourne le vrai statut. Seul un statut "succeeded" confirme
 * que l'argent a bien été prélevé.
 *
 * ════════════════════════════════════════════════════════════════════════
 * CE QUE CONTIENT LE PARAMÈTRE payment_intent DANS L'URL
 * ════════════════════════════════════════════════════════════════════════
 *
 * Après un paiement, Stripe redirige vers :
 *   /payment/success?payment_intent=pi_3xxx&payment_intent_client_secret=pi_3xxx_secret_xxx&redirect_status=succeeded
 *
 * Les paramètres ajoutés par Stripe :
 *
 *   payment_intent              → l'ID du PaymentIntent (ex: pi_3NxYyZ2eZvKYlo2C)
 *                                  Utilisé pour interroger l'API Stripe côté backend
 *
 *   payment_intent_client_secret → le clientSecret (on ne l'utilise pas ici,
 *                                  notre backend a la clé secrète pour accéder au PI)
 *
 *   redirect_status             → statut apparent retourné par Stripe.js
 *                                  ("succeeded", "processing", "requires_action"...)
 *                                  On NE se fie PAS à ce paramètre seul : on vérifie
 *                                  via le backend pour éviter toute manipulation.
 *
 * On utilise uniquement payment_intent pour appeler notre backend,
 * qui lui-même appelle l'API Stripe avec sa clé secrète pour obtenir
 * le vrai statut du PaymentIntent.
 */
import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { confirmPayment } from '../api/paymentApi';

export default function PaymentSuccessPage() {
  const navigate       = useNavigate();
  const [searchParams] = useSearchParams();

  // Trois états possibles : "loading" | "success" | "error"
  const [status,  setStatus]  = useState('loading');
  const [message, setMessage] = useState('');

  useEffect(() => {
    // ── Lecture du payment_intent depuis l'URL ──────────────────────────
    // Stripe ajoute automatiquement ?payment_intent=pi_xxx à l'URL return_url
    // fournie dans stripe.confirmPayment({ confirmParams: { return_url } }).
    const paymentIntentId = searchParams.get('payment_intent');

    if (!paymentIntentId) {
      // Quelqu'un a navigué directement sur cette page sans venir de Stripe
      setStatus('error');
      setMessage('Aucun identifiant de paiement trouvé dans l\'URL.');
      return;
    }

    // ── Vérification backend du statut du paiement ─────────────────────
    // On envoie le paymentIntentId à notre backend (POST /api/payment/confirm).
    // Le backend appelle l'API Stripe avec sa clé secrète et retourne le
    // vrai statut du PaymentIntent.
    //
    // Pourquoi ne pas appeler l'API Stripe directement depuis le frontend ?
    // → La clé secrète Stripe (sk_test_...) ne peut JAMAIS être dans le frontend
    //   (elle serait exposée dans le code source JS, visible par n'importe qui).
    confirmPayment(paymentIntentId)
      .then(({ data }) => {
        if (data.success && data.status === 'succeeded') {
          // ✅ Paiement confirmé — on vide le panier
          // Important : on ne vide le panier QU'APRÈS confirmation backend,
          // pas avant, pour éviter de le perdre si la vérification échoue.
          localStorage.removeItem('cart');
          setStatus('success');
        } else {
          // Le paiement existe mais n'est pas finalisé (ex: "processing", "requires_action")
          setStatus('error');
          setMessage(`Paiement non abouti (statut Stripe : ${data.status})`);
        }
      })
      .catch((err) => {
        // Erreur réseau ou erreur retournée par notre backend
        setStatus('error');
        setMessage(err.response?.data?.error ?? 'Une erreur est survenue lors de la confirmation.');
      });
  }, []);

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4">
      <div className="bg-white rounded-2xl shadow-lg p-10 max-w-sm w-full text-center">

        {/* ── État : vérification en cours ──────────────────────────── */}
        {status === 'loading' && (
          <div className="space-y-4">
            <div className="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto" />
            <p className="text-gray-500">Vérification du paiement en cours…</p>
          </div>
        )}

        {/* ── État : paiement confirmé ───────────────────────────────── */}
        {status === 'success' && (
          <>
            {/* Grande icône verte ✓ */}
            <div className="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-6">
              <svg className="w-10 h-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </div>

            <h2 className="text-2xl font-bold text-green-600 mb-3">Paiement confirmé !</h2>
            <p className="text-gray-500 text-sm mb-8">
              Merci pour votre commande. Votre paiement a été traité avec succès.
            </p>

            <button
              onClick={() => navigate('/')}
              className="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl font-semibold transition"
            >
              Retour au catalogue
            </button>
          </>
        )}

        {/* ── État : erreur ──────────────────────────────────────────── */}
        {status === 'error' && (
          <>
            {/* Grande icône rouge ✗ */}
            <div className="w-20 h-20 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-6">
              <svg className="w-10 h-10 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </div>

            <h2 className="text-2xl font-bold text-red-600 mb-3">Paiement échoué</h2>
            <p className="text-gray-500 text-sm mb-2">
              Une erreur est survenue lors de la confirmation.
            </p>
            {message && (
              <p className="text-red-400 text-xs mb-8 bg-red-50 rounded-lg px-3 py-2">{message}</p>
            )}

            <button
              onClick={() => navigate('/cart')}
              className="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 py-3 rounded-xl font-semibold transition"
            >
              Retour au panier
            </button>
          </>
        )}

      </div>
    </div>
  );
}
