/**
 * ProductDetailPage — Fiche détaillée d'un produit.
 *
 * Charge le produit par son id (depuis l'URL via useParams).
 * Affiche l'image, la description complète, le prix et le badge de dispo.
 * Permet d'ajouter au panier.
 */
import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { getProduct } from '../api/productApi';
import AvailabilityBadge from '../components/AvailabilityBadge';

export default function ProductDetailPage() {
  const { id }    = useParams();
  const navigate  = useNavigate();
  const [product, setProduct] = useState(null);
  const [loading, setLoading] = useState(true);
  // Affiche "Ajouté ✓" pendant 2 secondes après l'ajout au panier
  const [added,   setAdded]   = useState(false);

  useEffect(() => {
    getProduct(id)
      .then(({ data }) => setProduct(data))
      .catch(() => navigate('/'))
      .finally(() => setLoading(false));
  }, [id]);

  const handleAddToCart = () => {
    const raw  = localStorage.getItem('cart');
    const cart = raw ? JSON.parse(raw) : [];
    const existing = cart.find((item) => item.id === product.id);
    if (existing) {
      existing.quantity += 1;
    } else {
      cart.push({ id: product.id, name: product.name, price: product.price, quantity: 1 });
    }
    localStorage.setItem('cart', JSON.stringify(cart));
    // Feedback visuel inline : affiche "Ajouté ✓" puis revient au texte normal après 2s
    setAdded(true);
    setTimeout(() => setAdded(false), 2000);
  };

  if (loading) return <p className="text-center mt-20 text-gray-500">Chargement…</p>;
  if (!product) return null;

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white shadow-sm px-6 py-4 flex items-center gap-4">
        <button onClick={() => navigate(-1)} className="text-indigo-600 hover:underline text-sm">
          ← Retour au catalogue
        </button>
        <h1 className="text-lg font-bold text-indigo-700">Stock&Shop</h1>
      </header>

      <main className="max-w-3xl mx-auto px-4 py-10">
        <div className="bg-white rounded-2xl shadow p-8 flex flex-col md:flex-row gap-8">
          {/* Image */}
          <div className="md:w-1/2 h-64 bg-gray-100 rounded-xl overflow-hidden">
            {product.imageUrl ? (
              <img src={product.imageUrl} alt={product.name} className="w-full h-full object-cover" />
            ) : (
              <div className="w-full h-full flex items-center justify-center text-gray-400">Pas d'image</div>
            )}
          </div>

          {/* Infos */}
          <div className="md:w-1/2 flex flex-col gap-4">
            <div>
              <span className="text-xs text-gray-400">{product.category?.name}</span>
              <h2 className="text-2xl font-bold text-gray-800 mt-1">{product.name}</h2>
            </div>
            <AvailabilityBadge availability={product.availability} />
            <p className="text-gray-600 text-sm">{product.description}</p>
            <p className="text-3xl font-bold text-indigo-700">{parseFloat(product.price).toFixed(2)} €</p>

            <button
              disabled={product.availability === 'Rupture' || added}
              onClick={handleAddToCart}
              className="mt-auto bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-xl font-medium transition disabled:opacity-40"
            >
              {added
                ? 'Ajouté ✓'
                : product.availability === 'Rupture'
                  ? 'Rupture de stock'
                  : 'Ajouter au panier'}
            </button>
          </div>
        </div>
      </main>
    </div>
  );
}
