/**
 * ProductCard — Carte produit affichée dans le catalogue.
 *
 * Affiche l'image, le nom, le prix, la disponibilité et un bouton
 * "Ajouter au panier". Un clic sur la carte navigue vers le détail.
 *
 * Props :
 *   product   {Object}   — objet produit complet retourné par l'API
 *   onAddCart {Function} — callback appelé avec le produit quand on clique "Ajouter"
 */
import { useNavigate } from 'react-router-dom';
import AvailabilityBadge from './AvailabilityBadge';

export default function ProductCard({ product, onAddCart }) {
  const navigate = useNavigate();

  return (
    <div
      className="bg-white rounded-xl shadow hover:shadow-md transition cursor-pointer flex flex-col"
      onClick={() => navigate(`/products/${product.id}`)}
    >
      {/* Image du produit — fallback sur un placeholder gris si absente */}
      <div className="h-48 bg-gray-100 rounded-t-xl overflow-hidden">
        {product.imageUrl ? (
          <img
            src={product.imageUrl}
            alt={product.name}
            className="w-full h-full object-cover"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-gray-400 text-sm">
            Pas d'image
          </div>
        )}
      </div>

      <div className="p-4 flex flex-col gap-2 flex-1">
        {/* Catégorie en petit au-dessus du nom */}
        <p className="text-xs text-gray-400 font-medium">{product.category?.name}</p>
        <h3 className="font-semibold text-gray-800 text-sm line-clamp-2">{product.name}</h3>
        <p className="text-gray-500 text-xs line-clamp-2">{product.shortDescription}</p>

        <div className="mt-auto flex items-center justify-between pt-2">
          <span className="font-bold text-indigo-700">
            {parseFloat(product.price).toFixed(2)} €
          </span>
          <AvailabilityBadge availability={product.availability} />
        </div>

        {/* Empêche le clic "Ajouter" de déclencher la navigation vers le détail */}
        <button
          className="mt-2 w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm py-1.5 rounded-lg transition disabled:opacity-40"
          disabled={product.availability === 'Rupture'}
          onClick={(e) => {
            e.stopPropagation();
            onAddCart?.(product);
          }}
        >
          {product.availability === 'Rupture' ? 'Rupture de stock' : 'Ajouter au panier'}
        </button>

        {/* Bouton fiche détail — stopPropagation car la carte entière est aussi cliquable */}
        <button
          className="mt-1 w-full border border-indigo-300 text-indigo-600 hover:bg-indigo-50 text-sm py-1.5 rounded-lg transition"
          onClick={(e) => {
            e.stopPropagation();
            navigate(`/products/${product.id}`);
          }}
        >
          Voir le détail →
        </button>
      </div>
    </div>
  );
}
