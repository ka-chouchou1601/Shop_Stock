/**
 * AdminProductsPage — Interface d'administration des produits.
 *
 * Fonctionnalités :
 *   - Redirige vers /login si aucun utilisateur connecté
 *   - Affiche 4 indicateurs : Total, En stock, Stock faible, Rupture
 *   - Liste tous les produits dans un tableau (nom, catégorie, prix, quantité,
 *     disponibilité, dates de création/modification)
 *   - Création d'un produit via modale + ProductForm
 *   - Modification d'un produit existant via la même modale pré-remplie
 *   - Suppression avec confirmation (window.confirm)
 *   - Message de succès vert affiché 3 secondes après chaque opération CRUD
 *
 * Accès protégé :
 *   L'utilisateur connecté est stocké dans localStorage sous la clé 'user'
 *   (mis en place dans LoginPage après POST /api/login).
 *   Si la clé est absente, on redirige vers /login.
 */
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { getProducts, createProduct, updateProduct, deleteProduct } from '../api/productApi';
import { getCategories } from '../api/categoryApi';
import { logout } from '../api/authApi';
import ProductForm from '../components/ProductForm';
import AvailabilityBadge from '../components/AvailabilityBadge';
import StatCard from '../components/StatCard';

// ── Formatage de date ──────────────────────────────────────────────────────
// Convertit une chaîne ISO 8601 en date lisible : "12/05/2025 14:30"
function formatDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('fr-FR', {
    day:    '2-digit',
    month:  '2-digit',
    year:   'numeric',
    hour:   '2-digit',
    minute: '2-digit',
  });
}

export default function AdminProductsPage() {
  const navigate = useNavigate();

  // ── Lecture de l'utilisateur connecté ─────────────────────────────────────
  // localStorage.getItem('user') retourne la réponse JSON de POST /api/login :
  // { id, email, name, role }
  const [currentUser] = useState(() => {
    const raw = localStorage.getItem('user');
    return raw ? JSON.parse(raw) : null;
  });

  const [products,    setProducts]    = useState([]);
  const [categories,  setCategories]  = useState([]);
  // modal : null (fermée) | { mode: 'create' | 'edit', product?: Object }
  const [modal,       setModal]       = useState(null);
  const [loading,     setLoading]     = useState(false);
  const [error,       setError]       = useState('');
  // successMsg : message affiché 3 secondes après une opération CRUD réussie
  const [successMsg,  setSuccessMsg]  = useState('');

  // ── Redirection si non connecté ────────────────────────────────────────────
  useEffect(() => {
    if (!currentUser) navigate('/login');
  }, []);

  // ── Chargement initial ─────────────────────────────────────────────────────
  const load = () => {
    getProducts()
      .then(({ data }) => setProducts(data))
      .catch(console.error);
  };

  useEffect(() => {
    load();
    getCategories()
      .then(({ data }) => setCategories(data))
      .catch(console.error);
  }, []);

  // ── Affichage d'un message de succès temporaire ───────────────────────────
  // Le message disparaît automatiquement après 3 secondes.
  const showSuccess = (msg) => {
    setSuccessMsg(msg);
    setTimeout(() => setSuccessMsg(''), 3000);
  };

  // ── Stats calculées à partir de la liste des produits ─────────────────────
  // availability est calculé côté backend selon la quantité :
  //   0        → "Rupture"
  //   1 à 5    → "Stock faible"
  //   6+       → "En stock"
  const enStock     = products.filter((p) => p.availability === 'En stock').length;
  const stockFaible = products.filter((p) => p.availability === 'Stock faible').length;
  const ruptures    = products.filter((p) => p.availability === 'Rupture').length;

  // ── Handlers CRUD ──────────────────────────────────────────────────────────

  const handleSubmit = async (formData) => {
    setLoading(true);
    setError('');
    try {
      if (modal.mode === 'create') {
        await createProduct(formData);
        showSuccess('Produit créé avec succès.');
      } else {
        await updateProduct(modal.product.id, formData);
        showSuccess('Produit modifié avec succès.');
      }
      setModal(null);
      load();
    } catch (err) {
      // Le backend retourne les erreurs de validation dans err.response.data.errors
      const msgs = err.response?.data?.errors;
      setError(msgs ? msgs.join(', ') : 'Une erreur est survenue.');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (product) => {
    // Confirmation native du navigateur avant suppression irréversible
    if (!window.confirm(`Supprimer le produit "${product.name}" ? Cette action est irréversible.`)) return;
    try {
      await deleteProduct(product.id);
      showSuccess(`"${product.name}" a été supprimé.`);
      load();
    } catch {
      alert('Erreur lors de la suppression.');
    }
  };

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  const openCreate = () => {
    setError('');
    setModal({ mode: 'create' });
  };

  const openEdit = (product) => {
    setError('');
    setModal({ mode: 'edit', product });
  };

  return (
    <div className="min-h-screen bg-gray-50">

      {/* ── Header ─────────────────────────────────────────────────────────── */}
      <header className="bg-white shadow-sm px-6 py-4 flex items-center justify-between">
        <h1 className="text-lg font-bold text-indigo-700">Stock&Shop — Administration</h1>
        <div className="flex items-center gap-4">
          <button
            onClick={() => navigate('/')}
            className="text-sm text-gray-500 hover:underline"
          >
            Voir le catalogue
          </button>
          {/* Affiche le nom de l'administrateur connecté */}
          <span className="text-sm text-gray-600 font-medium">
            {currentUser?.name}
          </span>
          <button
            onClick={handleLogout}
            className="text-sm text-red-500 hover:underline"
          >
            Déconnexion
          </button>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-4 py-8">

        {/* ── Message de succès (disparaît après 3 s) ──────────────────────── */}
        {successMsg && (
          <div className="mb-6 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm font-medium flex items-center gap-2">
            {/* Icône coche verte */}
            <svg className="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            {successMsg}
          </div>
        )}

        {/* ── 4 indicateurs de stock ───────────────────────────────────────── */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
          <StatCard title="Total produits" count={products.length}  color="border-indigo-500" />
          <StatCard title="En stock"        count={enStock}          color="border-green-500"  />
          <StatCard title="Stock faible"    count={stockFaible}      color="border-yellow-500" />
          <StatCard title="Ruptures"        count={ruptures}         color="border-red-500"    />
        </div>

        {/* ── Barre d'actions ──────────────────────────────────────────────── */}
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold text-gray-800">Produits</h2>
          <button
            onClick={openCreate}
            className="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700 transition"
          >
            + Ajouter un produit
          </button>
        </div>

        {/* ── Tableau des produits ─────────────────────────────────────────── */}
        <div className="bg-white rounded-xl shadow overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="text-left px-4 py-3 text-gray-600 font-medium">Nom</th>
                <th className="text-left px-4 py-3 text-gray-600 font-medium">Catégorie</th>
                <th className="text-right px-4 py-3 text-gray-600 font-medium">Prix</th>
                <th className="text-right px-4 py-3 text-gray-600 font-medium">Quantité</th>
                <th className="text-center px-4 py-3 text-gray-600 font-medium">Disponibilité</th>
                <th className="text-left px-4 py-3 text-gray-600 font-medium whitespace-nowrap">Créé le</th>
                <th className="text-left px-4 py-3 text-gray-600 font-medium whitespace-nowrap">Modifié le</th>
                <th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody>
              {products.length === 0 && (
                <tr>
                  <td colSpan={8} className="text-center px-4 py-8 text-gray-400">
                    Aucun produit. Cliquez sur "Ajouter un produit" pour commencer.
                  </td>
                </tr>
              )}
              {products.map((product) => (
                <tr key={product.id} className="border-b border-gray-100 hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium text-gray-800">{product.name}</td>
                  <td className="px-4 py-3 text-gray-500">{product.category?.name ?? '—'}</td>
                  <td className="px-4 py-3 text-right tabular-nums">{parseFloat(product.price).toFixed(2)} €</td>
                  <td className="px-4 py-3 text-right tabular-nums">{product.quantity}</td>
                  <td className="px-4 py-3 text-center">
                    <AvailabilityBadge availability={product.availability} />
                  </td>
                  <td className="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">
                    {formatDate(product.createdAt)}
                  </td>
                  <td className="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">
                    {formatDate(product.updatedAt)}
                  </td>
                  <td className="px-4 py-3 text-right whitespace-nowrap">
                    <button
                      onClick={() => openEdit(product)}
                      className="text-indigo-600 hover:underline mr-4"
                    >
                      Modifier
                    </button>
                    <button
                      onClick={() => handleDelete(product)}
                      className="text-red-500 hover:underline"
                    >
                      Supprimer
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </main>

      {/* ── Modale création / modification ───────────────────────────────────── */}
      {modal && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-8">

            <h2 className="text-lg font-bold text-gray-800 mb-6">
              {modal.mode === 'create' ? 'Ajouter un produit' : `Modifier : ${modal.product.name}`}
            </h2>

            {/* Erreur retournée par le backend (ex : validation Symfony) */}
            {error && (
              <p className="text-red-500 text-sm bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-4">
                {error}
              </p>
            )}

            {/*
             * ProductForm reçoit le produit à modifier (ou null pour la création).
             * La prop `product` permet au formulaire de se pré-remplir avec
             * les valeurs existantes et d'adapter le libellé du bouton submit.
             */}
            <ProductForm
              product={modal.product ?? null}
              categories={categories}
              onSubmit={handleSubmit}
              onCancel={() => setModal(null)}
              loading={loading}
            />
          </div>
        </div>
      )}
    </div>
  );
}
