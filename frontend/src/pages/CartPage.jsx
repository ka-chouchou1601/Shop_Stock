/**
 * CartPage — Page du panier d'achat.
 *
 * Lit le panier depuis localStorage (clé 'cart').
 * Permet de modifier les quantités et de supprimer des articles.
 * Redirige vers /checkout pour passer à la commande.
 *
 * Le panier est stocké localement (pas de base de données) :
 *   [{ id, name, price, quantity }, ...]
 */
import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import CartItem from '../components/CartItem';

export default function CartPage() {
  const navigate = useNavigate();
  const [cart, setCart] = useState([]);

  // Charge le panier depuis localStorage au montage
  useEffect(() => {
    const raw = localStorage.getItem('cart');
    setCart(raw ? JSON.parse(raw) : []);
  }, []);

  // Persiste le panier à chaque modification
  const persist = (updated) => {
    setCart(updated);
    localStorage.setItem('cart', JSON.stringify(updated));
  };

  const handleQuantityChange = (id, quantity) => {
    persist(cart.map((item) => item.id === id ? { ...item, quantity } : item));
  };

  const handleRemove = (id) => {
    persist(cart.filter((item) => item.id !== id));
  };

  const handleClear = () => persist([]);

  const total = cart.reduce((sum, item) => sum + parseFloat(item.price) * item.quantity, 0);

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white shadow-sm px-6 py-4 flex items-center gap-4">
        <button onClick={() => navigate(-1)} className="text-indigo-600 hover:underline text-sm">← Retour au catalogue</button>
        <h1 className="text-lg font-bold text-indigo-700">Mon panier</h1>
      </header>

      <main className="max-w-2xl mx-auto px-4 py-8">
        {cart.length === 0 ? (
          <div className="text-center mt-16">
            <p className="text-gray-500 mb-4">Votre panier est vide.</p>
            <button onClick={() => navigate('/')} className="text-indigo-600 hover:underline text-sm">
              Retourner au catalogue
            </button>
          </div>
        ) : (
          <div className="bg-white rounded-2xl shadow p-6">
            {cart.map((item) => (
              <CartItem
                key={item.id}
                item={item}
                onRemove={handleRemove}
                onQuantityChange={handleQuantityChange}
              />
            ))}

            <div className="flex items-center justify-between pt-6 mt-4 border-t border-gray-100">
              <span className="font-semibold text-gray-700">Total</span>
              <span className="text-xl font-bold text-indigo-700">{total.toFixed(2)} €</span>
            </div>

            {/* Vider le panier — supprime tous les articles d'un coup */}
            <button
              onClick={handleClear}
              className="mt-4 w-full border border-red-300 text-red-500 hover:bg-red-50 py-2 rounded-xl text-sm transition"
            >
              Vider le panier
            </button>

            <button
              onClick={() => navigate('/checkout')}
              className="mt-3 w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl font-semibold transition"
            >
              Procéder au paiement
            </button>
          </div>
        )}
      </main>
    </div>
  );
}
