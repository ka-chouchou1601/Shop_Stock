/**
 * ProductCatalogPage — Page principale du catalogue produits (vitrine client).
 *
 * Charge les produits et catégories depuis le backend.
 * Applique les filtres search/category/availability via l'API (côté serveur).
 * Gère l'ajout au panier : stocke dans localStorage sous la clé 'cart'.
 */
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { getProducts } from '../api/productApi';
import { getCategories } from '../api/categoryApi';
import ProductCard from '../components/ProductCard';
import ProductFilters from '../components/ProductFilters';

/** Calcule le nombre total d'articles dans le panier localStorage. */
const readCartCount = () => {
  const raw = localStorage.getItem('cart');
  if (!raw) return 0;
  return JSON.parse(raw).reduce((sum, item) => sum + item.quantity, 0);
};

export default function ProductCatalogPage() {
  const navigate    = useNavigate();
  const [products,   setProducts]   = useState([]);
  const [categories, setCategories] = useState([]);
  const [filters,    setFilters]    = useState({});
  const [loading,    setLoading]    = useState(true);
  // Nombre total d'articles dans le panier — initialisé depuis localStorage
  const [cartCount,  setCartCount]  = useState(readCartCount);

  // Charge catégories une seule fois au montage
  useEffect(() => {
    getCategories().then(({ data }) => setCategories(data)).catch(console.error);
  }, []);

  // Recharge les produits à chaque changement de filtre
  useEffect(() => {
    setLoading(true);
    // Les filtres vides sont ignorés par Axios (paramètres non envoyés)
    const activeFilters = Object.fromEntries(
      Object.entries(filters).filter(([, v]) => v !== '')
    );
    getProducts(activeFilters)
      .then(({ data }) => setProducts(data))
      .catch(console.error)
      .finally(() => setLoading(false));
  }, [filters]);

  // Ajoute un produit au panier dans localStorage et met à jour le badge
  const handleAddToCart = (product) => {
    const raw  = localStorage.getItem('cart');
    const cart = raw ? JSON.parse(raw) : [];

    const existing = cart.find((item) => item.id === product.id);
    if (existing) {
      existing.quantity += 1;
    } else {
      cart.push({ id: product.id, name: product.name, price: product.price, quantity: 1 });
    }

    localStorage.setItem('cart', JSON.stringify(cart));
    // Met à jour le badge immédiatement après l'écriture en localStorage
    setCartCount(readCartCount());
    alert(`"${product.name}" ajouté au panier !`);
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white shadow-sm px-6 py-4 flex items-center justify-between">
        <h1 className="text-xl font-bold text-indigo-700">Stock&Shop</h1>

        {/* Icône panier avec badge rouge indiquant le nombre total d'articles */}
        <button
          onClick={() => navigate('/cart')}
          className="relative p-2.5 bg-indigo-600 rounded-lg hover:bg-indigo-700 transition"
          aria-label={`Panier (${cartCount} article${cartCount > 1 ? 's' : ''})`}
        >
          {/* Icône SVG panier — Heroicons outline/shopping-cart */}
          <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
          {/* Badge rouge — affiché uniquement si le panier contient au moins 1 article */}
          {cartCount > 0 && (
            <span className="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-xs min-w-[20px] h-5 px-1 rounded-full flex items-center justify-center font-bold leading-none">
              {cartCount > 99 ? '99+' : cartCount}
            </span>
          )}
        </button>
      </header>

      <main className="max-w-6xl mx-auto px-4 py-8">
        <ProductFilters
          filters={filters}
          categories={categories}
          onChange={setFilters}
        />

        {loading ? (
          <p className="text-center text-gray-500 mt-12">Chargement…</p>
        ) : products.length === 0 ? (
          <p className="text-center text-gray-500 mt-12">Aucun produit trouvé.</p>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
            {products.map((product) => (
              <ProductCard
                key={product.id}
                product={product}
                onAddCart={handleAddToCart}
              />
            ))}
          </div>
        )}
      </main>
    </div>
  );
}
